<?php

namespace App\Events;

use App\Http\Resources\ChatMessageResource;
use App\Models\ChatMessage;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * ShouldBroadcastNow, not ShouldBroadcast — Laravel's dispatcher pushes
 * every ShouldBroadcast event through the actual queue connection
 * regardless of whether it implements ShouldQueue (that interface only
 * matters for queueing the *listener*, not the broadcast itself). With no
 * queue worker running, that meant every chat message silently sat in the
 * `jobs` table until something else (a refetch, a page revisit) happened
 * to surface it. ShouldBroadcastNow bypasses the queue entirely and
 * broadcasts synchronously within the same request.
 */
class ChatMessageSent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public ChatMessage $message) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel("conversation.{$this->message->conversation_id}")];
    }

    public function broadcastAs(): string
    {
        return 'message.sent';
    }

    public function broadcastWith(): array
    {
        return (new ChatMessageResource($this->message))->resolve();
    }
}
