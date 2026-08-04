<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\JournalEntry\StoreJournalEntryRequest;
use App\Http\Requests\JournalEntry\UpdateJournalEntryRequest;
use App\Http\Resources\JournalEntryResource;
use App\Models\JournalEntry;
use App\Services\Finance\JournalEntryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class JournalEntryController extends Controller
{
    public function __construct(protected JournalEntryService $journalEntryService) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', JournalEntry::class);

        $entries = $this->journalEntryService->paginate($request->only('status'), $request->integer('per_page', 15));

        return $this->paginated(JournalEntryResource::collection($entries));
    }

    public function store(StoreJournalEntryRequest $request): JsonResponse
    {
        $this->authorize('create', JournalEntry::class);

        $entry = $this->journalEntryService->create($request->validated());

        return $this->success(new JournalEntryResource($entry), 'Journal entry created successfully.', 201);
    }

    public function show(JournalEntry $journalEntry): JsonResponse
    {
        $this->authorize('view', $journalEntry);

        return $this->success(new JournalEntryResource($journalEntry->load('lines.account')));
    }

    public function update(UpdateJournalEntryRequest $request, JournalEntry $journalEntry): JsonResponse
    {
        $this->authorize('update', $journalEntry);

        $journalEntry = $this->journalEntryService->update($journalEntry, $request->validated());

        return $this->success(new JournalEntryResource($journalEntry), 'Journal entry updated successfully.');
    }

    public function destroy(JournalEntry $journalEntry): JsonResponse
    {
        $this->authorize('delete', $journalEntry);

        $this->journalEntryService->delete($journalEntry);

        return $this->success(null, 'Journal entry deleted successfully.');
    }

    public function post(JournalEntry $journalEntry): JsonResponse
    {
        $this->authorize('post', $journalEntry);

        $journalEntry = $this->journalEntryService->post($journalEntry);

        return $this->success(new JournalEntryResource($journalEntry), 'Journal entry posted successfully.');
    }

    public function reverse(JournalEntry $journalEntry): JsonResponse
    {
        $this->authorize('reverse', $journalEntry);

        $reversal = $this->journalEntryService->reverse($journalEntry);

        return $this->success(new JournalEntryResource($reversal), 'Journal entry reversed successfully.', 201);
    }
}
