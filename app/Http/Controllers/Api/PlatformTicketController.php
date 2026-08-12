<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Platform\StorePlatformTicketReplyRequest;
use App\Http\Requests\Platform\UpdatePlatformTicketRequest;
use App\Http\Resources\PlatformTicketResource;
use App\Models\PlatformTicket;
use App\Models\Scopes\CompanyScope;
use App\Notifications\SupportTicketReplied;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Platform-staff side of the platform_tickets table — every method here needs
 * to see tickets belonging to ANY client company, not just the acting user's
 * own (platform) company. Route params are deliberately typed `int`, not
 * `PlatformTicket`: Laravel's implicit route-model binding would resolve
 * through PlatformTicket::query(), which (being a TenantModel) has CompanyScope
 * applied and would 404 every ticket that isn't the platform company's own —
 * exactly the opposite of what this controller needs. withoutGlobalScope(
 * CompanyScope::class) is used explicitly instead, everywhere.
 */
class PlatformTicketController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $tickets = PlatformTicket::withoutGlobalScope(CompanyScope::class)
            ->with(['company', 'assignee'])
            ->when($request->query('status'), fn ($query, $status) => $query->where('status', $status))
            ->when($request->query('priority'), fn ($query, $priority) => $query->where('priority', $priority))
            ->when($request->query('assigned_to'), fn ($query, $userId) => $query->where('assigned_to', $userId))
            ->when($request->query('company_id'), fn ($query, $companyId) => $query->where('company_id', $companyId))
            ->latest()
            ->paginate($request->integer('per_page', 15));

        return $this->paginated(PlatformTicketResource::collection($tickets));
    }

    public function show(int $ticket): JsonResponse
    {
        $ticket = PlatformTicket::withoutGlobalScope(CompanyScope::class)
            ->with(['company', 'raisedBy', 'assignee', 'replies.author'])
            ->findOrFail($ticket);

        return $this->success(new PlatformTicketResource($ticket));
    }

    public function update(UpdatePlatformTicketRequest $request, int $ticket): JsonResponse
    {
        $ticket = PlatformTicket::withoutGlobalScope(CompanyScope::class)->findOrFail($ticket);

        $ticket->update($request->validated());

        return $this->success(new PlatformTicketResource($ticket->fresh(['company', 'assignee'])), 'Ticket updated.');
    }

    public function reply(StorePlatformTicketReplyRequest $request, int $ticket): JsonResponse
    {
        $ticket = PlatformTicket::withoutGlobalScope(CompanyScope::class)->findOrFail($ticket);

        $isInternal = (bool) $request->validated('is_internal_note', false);

        $ticket->replies()->create([
            'author_id' => Auth::id(),
            'body' => $request->validated('body'),
            'is_internal_note' => $isInternal,
        ]);

        if (! $isInternal) {
            $ticket->raisedBy?->notify(new SupportTicketReplied($ticket));
        }

        $ticket->load(['company', 'raisedBy', 'assignee', 'replies.author']);

        return $this->success(new PlatformTicketResource($ticket), 'Reply posted.', 201);
    }
}
