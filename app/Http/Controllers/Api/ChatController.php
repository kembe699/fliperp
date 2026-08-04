<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Chat\SendChatMessageRequest;
use App\Http\Resources\ChatConversationResource;
use App\Http\Resources\ChatMessageResource;
use App\Models\ChatConversation;
use App\Services\Chat\ChatService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ChatController extends Controller
{
    public function __construct(protected ChatService $chatService) {}

    public function startConversation(int $userId): JsonResponse
    {
        $conversation = $this->chatService->findOrCreateDirectConversation(Auth::user(), $userId);
        $conversation->load(['users', 'messages' => fn ($query) => $query->latest('id')->limit(1)]);

        return $this->success(new ChatConversationResource($conversation));
    }

    public function index(): JsonResponse
    {
        $conversations = $this->chatService->conversationsFor(Auth::user());

        return $this->success(ChatConversationResource::collection($conversations));
    }

    public function messages(Request $request, ChatConversation $conversation): JsonResponse
    {
        $this->authorize('view', $conversation);

        $messages = $this->chatService->paginateMessages($conversation, $request->integer('before') ?: null);

        return $this->success(ChatMessageResource::collection($messages));
    }

    public function sendMessage(SendChatMessageRequest $request, ChatConversation $conversation): JsonResponse
    {
        $this->authorize('view', $conversation);

        $message = $this->chatService->sendMessage(
            $conversation,
            Auth::user(),
            $request->input('body'),
            $request->file('attachments', []),
        );

        return $this->success(new ChatMessageResource($message), 'Message sent.', 201);
    }

    public function markRead(ChatConversation $conversation): JsonResponse
    {
        $this->authorize('view', $conversation);

        $this->chatService->markRead($conversation, Auth::user());

        return $this->success(null, 'Marked as read.');
    }
}
