<?php

namespace App\Http\Resources;

use App\Services\Chat\ChatService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ChatConversationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $currentUser = $request->user();
        $otherUser = $this->users->first(fn ($user) => $user->id !== $currentUser->id);
        $lastMessage = $this->messages->first();

        return [
            'id' => $this->id,
            'other_user' => $otherUser ? [
                'id' => $otherUser->id,
                'name' => $otherUser->name,
            ] : null,
            'last_message' => $lastMessage ? [
                'body' => $lastMessage->body,
                'sender_id' => $lastMessage->sender_id,
                'created_at' => $lastMessage->created_at?->toIso8601String(),
            ] : null,
            'unread_count' => app(ChatService::class)->unreadCountFor($this->resource, $currentUser),
            'updated_at' => ($lastMessage?->created_at ?? $this->updated_at)?->toIso8601String(),
        ];
    }
}
