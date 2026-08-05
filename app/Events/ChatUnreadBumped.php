<?php

namespace App\Events;

use App\Models\ChatConversation;
use App\Models\ChatMessage;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;

/**
 * A lightweight ping for the chat launcher's unread badge and conversation
 * list preview — fired only at recipients who don't currently have this
 * conversation open (see ChatService::sendMessage()), so the widget can
 * bump a count without needing the full message payload ChatMessageSent
 * carries on the conversation channel.
 *
 * ShouldBroadcastNow, not ShouldBroadcast — see ChatMessageSent for why:
 * ShouldBroadcast alone still goes through the queue connection, which
 * silently never runs without a worker process.
 */
class ChatUnreadBumped implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public int $recipientUserId,
        public ChatConversation $conversation,
        public ChatMessage $message,
    ) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel("user.{$this->recipientUserId}")];
    }

    public function broadcastAs(): string
    {
        return 'chat.unread';
    }

    public function broadcastWith(): array
    {
        return [
            'conversation_id' => $this->conversation->id,
            'sender_name' => $this->message->sender->name,
            'preview' => $this->message->body ? Str::limit($this->message->body, 80) : 'Sent an attachment',
        ];
    }
}
