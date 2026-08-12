<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Platform\StorePlatformClientRequest;
use App\Http\Requests\Platform\UpdatePlatformClientRequest;
use App\Http\Resources\CompanyResource;
use App\Http\Resources\UserResource;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\PlatformTicket;
use App\Models\Scopes\CompanyScope;
use App\Models\User;
use App\Services\Platform\PlatformClientService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PlatformClientController extends Controller
{
    public function __construct(protected PlatformClientService $platformClientService) {}

    public function index(Request $request): JsonResponse
    {
        $clients = $this->platformClientService->paginate(
            $request->only('status', 'search'),
            $request->integer('per_page', 15),
        );

        return $this->paginated(CompanyResource::collection($clients));
    }

    public function store(StorePlatformClientRequest $request): JsonResponse
    {
        $result = $this->platformClientService->createClient($request->validated());

        return $this->success([
            'company' => new CompanyResource($result['company']),
            'admin_user' => new UserResource($result['admin_user']),
            // Shown exactly once — the frontend must present this as "copy now, it
            // won't be shown again" (also mailed to the admin via ClientWelcomeEmail).
            'temp_password' => $result['temp_password'],
        ], 'Client onboarded successfully.', 201);
    }

    public function show(Company $client): JsonResponse
    {
        $this->assertIsClient($client);

        // Company has no CompanyScope of its own (it IS the tenant root), but Users
        // and Branches do — the target client's company_id differs from the acting
        // platform staff's own, so those two reads need the scope bypassed.
        $adminUsers = User::withoutGlobalScope(CompanyScope::class)
            ->where('company_id', $client->id)
            ->with('roles')
            ->get();

        $billing = null;
        if ($client->billing_customer_id) {
            $invoices = Invoice::where('customer_id', $client->billing_customer_id)->get();
            $billing = [
                'total_invoiced' => round((float) $invoices->sum('total_amount'), 2),
                'total_paid' => round((float) $invoices->sum('amount_paid'), 2),
                'outstanding_balance' => round((float) $invoices->sum(fn ($invoice) => (float) $invoice->total_amount - (float) $invoice->amount_paid), 2),
                'invoice_count' => $invoices->count(),
            ];
        }

        $tickets = PlatformTicket::withoutGlobalScope(CompanyScope::class)
            ->where('company_id', $client->id)
            ->latest()
            ->limit(20)
            ->get();

        return $this->success([
            'company' => new CompanyResource($client),
            'admin_users' => UserResource::collection($adminUsers),
            'billing' => $billing,
            'usage' => [
                'user_count' => $adminUsers->count(),
                'last_login_at' => $adminUsers->max('last_login_at')?->toIso8601String(),
            ],
            'tickets' => $tickets->map(fn (PlatformTicket $ticket) => [
                'id' => $ticket->id,
                'subject' => $ticket->subject,
                'status' => $ticket->status,
                'priority' => $ticket->priority,
                'created_at' => $ticket->created_at->toIso8601String(),
            ]),
        ]);
    }

    public function update(UpdatePlatformClientRequest $request, Company $client): JsonResponse
    {
        $this->assertIsClient($client);

        $client->update($request->validated());

        return $this->success(new CompanyResource($client->fresh()), 'Client updated successfully.');
    }

    public function suspend(Company $client): JsonResponse
    {
        $this->assertIsClient($client);

        $company = $this->platformClientService->suspend($client);

        return $this->success(new CompanyResource($company), 'Client suspended.');
    }

    public function activate(Company $client): JsonResponse
    {
        $this->assertIsClient($client);

        $company = $this->platformClientService->activate($client);

        return $this->success(new CompanyResource($company), 'Client activated.');
    }

    protected function assertIsClient(Company $client): void
    {
        abort_if($client->is_platform, 404);
    }
}
