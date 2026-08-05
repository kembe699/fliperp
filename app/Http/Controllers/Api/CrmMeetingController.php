<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\CrmMeeting\StoreCrmMeetingRequest;
use App\Http\Requests\CrmMeeting\UpdateCrmMeetingRequest;
use App\Http\Requests\CrmMeeting\UpdateCrmMeetingStatusRequest;
use App\Http\Resources\CrmMeetingResource;
use App\Models\CrmMeeting;
use App\Services\Crm\MeetingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class CrmMeetingController extends Controller
{
    public function __construct(protected MeetingService $meetingService) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', CrmMeeting::class);

        $meetings = $this->meetingService->paginate(
            $request->only('lead_id', 'deal_id', 'customer_id', 'organizer_id', 'status', 'from', 'to'),
            $request->integer('per_page', 15),
        );

        return $this->paginated(CrmMeetingResource::collection($meetings));
    }

    public function store(StoreCrmMeetingRequest $request): JsonResponse
    {
        $this->authorize('create', CrmMeeting::class);

        $meeting = $this->meetingService->create($request->validated());

        return $this->success(new CrmMeetingResource($meeting), 'Meeting scheduled successfully.', 201);
    }

    public function show(CrmMeeting $crmMeeting): JsonResponse
    {
        $this->authorize('view', $crmMeeting);

        return $this->success(new CrmMeetingResource($crmMeeting->load(['lead', 'deal', 'customer', 'organizer', 'createdBy', 'attendees.user'])));
    }

    public function update(UpdateCrmMeetingRequest $request, CrmMeeting $crmMeeting): JsonResponse
    {
        $this->authorize('update', $crmMeeting);

        $crmMeeting->update($request->validated());

        return $this->success(new CrmMeetingResource($crmMeeting->fresh(['lead', 'deal', 'customer', 'organizer', 'createdBy', 'attendees.user'])), 'Meeting updated successfully.');
    }

    public function destroy(CrmMeeting $crmMeeting): JsonResponse
    {
        $this->authorize('delete', $crmMeeting);

        $crmMeeting->delete();

        return $this->success(null, 'Meeting deleted successfully.');
    }

    public function updateStatus(UpdateCrmMeetingStatusRequest $request, CrmMeeting $crmMeeting): JsonResponse
    {
        $this->authorize('update', $crmMeeting);

        $meeting = $this->meetingService->updateStatus($crmMeeting, $request->validated('status'));

        return $this->success(new CrmMeetingResource($meeting), 'Meeting status updated successfully.');
    }

    public function ics(CrmMeeting $crmMeeting): Response
    {
        $this->authorize('view', $crmMeeting);

        $ics = $this->meetingService->generateIcs($crmMeeting);

        return response($ics, 200, [
            'Content-Type' => 'text/calendar; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="meeting-'.$crmMeeting->id.'.ics"',
        ]);
    }
}
