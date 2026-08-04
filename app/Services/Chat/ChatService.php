<?php

namespace App\Services\Chat;

use App\Events\ChatMessageSent;
use App\Events\ChatUnreadBumped;
use App\Models\ChatConversation;
use App\Models\ChatMessage;
use App\Models\ChatMessageAttachment;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ChatService
{
    /**
     * Two sequential calls with the same pair return the same conversation
     * (see the idempotency test) — this doesn't lock against two truly
     * simultaneous first-contact clicks creating a duplicate thread, which
     * is an acceptable gap for a "start chat" button (not a financial or
     * consistency-critical action like the POS/accounting flows that do
     * use row locking elsewhere in this app).
     */
    public function findOrCreateDirectConversation(User $currentUser, int $otherUserId): ChatConversation
    {
        $otherUser = User::query()->where('company_id', $currentUser->company_id)->find($otherUserId);

        if (! $otherUser) {
            throw ValidationException::withMessages([
                'user_id' => ['That user was not found in your company.'],
            ]);
        }

        if ($otherUser->id === $currentUser->id) {
            throw ValidationException::withMessages([
                'user_id' => ['You cannot start a conversation with yourself.'],
            ]);
        }

        $existing = ChatConversation::query()
            ->where('company_id', $currentUser->company_id)
            ->where('type', 'direct')
            ->whereHas('participants', fn ($query) => $query->where('user_id', $currentUser->id))
            ->whereHas('participants', fn ($query) => $query->where('user_id', $otherUser->id))
            ->first();

        if ($existing) {
            return $existing;
        }

        return DB::transaction(function () use ($currentUser, $otherUser) {
            $conversation = ChatConversation::create(['type' => 'direct']);

            $conversation->participants()->createMany([
                ['user_id' => $currentUser->id],
                ['user_id' => $otherUser->id],
            ]);

            return $conversation;
        });
    }

    /**
     * @return Collection<int, ChatConversation>
     */
    public function conversationsFor(User $user): Collection
    {
        return ChatConversation::query()
            ->whereHas('participants', fn ($query) => $query->where('user_id', $user->id))
            ->with(['users', 'messages' => fn ($query) => $query->latest('id')->limit(1)])
            ->get()
            ->sortByDesc(fn (ChatConversation $conversation) => $conversation->messages->first()?->created_at ?? $conversation->created_at)
            ->values();
    }

    public function unreadCountFor(ChatConversation $conversation, User $user): int
    {
        $lastReadAt = $conversation->participants()->where('user_id', $user->id)->value('last_read_at');

        return $conversation->messages()
            ->where('sender_id', '!=', $user->id)
            ->when($lastReadAt, fn ($query, $value) => $query->where('created_at', '>', $value))
            ->count();
    }

    /**
     * @return Collection<int, ChatMessage>
     */
    public function paginateMessages(ChatConversation $conversation, ?int $beforeId, int $perPage = 30): Collection
    {
        return $conversation->messages()
            ->with(['sender', 'attachments'])
            ->when($beforeId, fn ($query, $id) => $query->where('id', '<', $id))
            ->latest('id')
            ->limit($perPage)
            ->get()
            ->reverse()
            ->values();
    }

    /**
     * @param  UploadedFile[]  $attachments
     */
    public function sendMessage(ChatConversation $conversation, User $sender, ?string $body, array $attachments): ChatMessage
    {
        if (! $body && empty($attachments)) {
            throw ValidationException::withMessages([
                'body' => ['A message needs text or at least one attachment.'],
            ]);
        }

        $message = DB::transaction(function () use ($conversation, $sender, $body, $attachments) {
            $message = ChatMessage::create([
                'conversation_id' => $conversation->id,
                'sender_id' => $sender->id,
                'body' => $body,
            ]);

            foreach ($attachments as $file) {
                $this->storeAttachment($file, $message);
            }

            // The sender's own act of sending counts as having read up to now.
            $conversation->participants()->where('user_id', $sender->id)->update(['last_read_at' => now()]);

            return $message->load(['sender', 'attachments']);
        });

        broadcast(new ChatMessageSent($message))->toOthers();

        $recipients = $conversation->participants()->where('user_id', '!=', $sender->id)->get();

        foreach ($recipients as $recipient) {
            // A participant who read within the last minute is treated as
            // still looking at the conversation — the open thread already
            // gets the message live via the conversation channel, so this
            // only needs to bump the launcher badge for anyone who isn't.
            $currentlyViewing = $recipient->last_read_at && $recipient->last_read_at->diffInSeconds(now()) < 60;

            if (! $currentlyViewing) {
                broadcast(new ChatUnreadBumped($recipient->user_id, $conversation, $message));
            }
        }

        return $message;
    }

    public function markRead(ChatConversation $conversation, User $user): void
    {
        $conversation->participants()->where('user_id', $user->id)->update(['last_read_at' => now()]);
    }

    protected function storeAttachment(UploadedFile $file, ChatMessage $message): ChatMessageAttachment
    {
        $path = $file->store('chat-attachments', 'public');

        return ChatMessageAttachment::create([
            'message_id' => $message->id,
            'file_path' => $path,
            'file_name' => $file->getClientOriginalName(),
            'file_size' => $file->getSize(),
            'mime_type' => $file->getMimeType(),
        ]);
    }
}
