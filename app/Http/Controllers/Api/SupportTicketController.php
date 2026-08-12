<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Support\StoreSupportTicketReplyRequest;
use App\Http\Requests\Support\StoreSupportTicketRequest;
use App\Http\Resources\PlatformTicketResource;
use App\Models\PlatformTicket;
use App\Models\Scopes\CompanyScope;
use App\Models\User;
use App\Notifications\NewSupportTicket;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Client-facing side of the platform_tickets table. PlatformTicket extends
 * TenantModel, so every query here is automatically scoped to the caller's own
 * company via CompanyScope — a client can never see or act on another
 * company's ticket, with zero manual filtering needed. The platform-staff side
 * of the same table (all companies, internal notes) is PlatformTicketController.
 */
class SupportTicketController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $tickets = PlatformTicket::query()
            ->when($request->query('status'), fn ($query, $status) => $query->where('status', $status))
            ->latest()
            ->paginate($request->integer('per_page', 15));

        return $this->paginated(PlatformTicketResource::collection($tickets));
    }

    public function show(PlatformTicket $ticket): JsonResponse
    {
        // is_internal_note replies must never reach a client view.
        $ticket->setRelation('replies', $ticket->replies()->where('is_internal_note', false)->with('author')->get());
        $ticket->load('assignee');

        return $this->success(new PlatformTicketResource($ticket));
    }

    public function store(StoreSupportTicketRequest $request): JsonResponse
    {
        $ticket = PlatformTicket::create([
            ...$request->validated(),
            'raised_by_user_id' => Auth::id(),
            'source' => 'contact_support_widget',
        ]);

        $platformStaff = User::withoutGlobalScope(CompanyScope::class)->where('is_platform_staff', true)->get();
        foreach ($platformStaff as $staff) {
            $staff->notify(new NewSupportTicket($ticket));
        }

        return $this->success(new PlatformTicketResource($ticket->load('company')), 'Support ticket submitted.', 201);
    }

    public function reply(StoreSupportTicketReplyRequest $request, PlatformTicket $ticket): JsonResponse
    {
        $ticket->replies()->create([
            'author_id' => Auth::id(),
            'body' => $request->validated('body'),
            'is_internal_note' => false,
        ]);

        $ticket->setRelation('replies', $ticket->replies()->where('is_internal_note', false)->with('author')->get());

        return $this->success(new PlatformTicketResource($ticket), 'Reply posted.', 201);
    }
}
