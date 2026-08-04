<?php

use App\Models\ChatConversation;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

// Personal notifications — only the user themselves can listen on their own
// channel. Matches User::receivesBroadcastNotificationsOn().
Broadcast::channel('user.{id}', function (User $user, int $id) {
    return $user->id === $id;
});

// Online-presence roster for the company, used to build the "who's online"
// list for the chat widget. The join payload (returned array) is what each
// client's presence.here()/joining() callbacks receive — deliberately kept
// small (id/name/avatar), not the full user record.
Broadcast::channel('company.{companyId}', function (User $user, int $companyId) {
    if ($user->company_id !== $companyId) {
        return false;
    }

    return [
        'id' => $user->id,
        'name' => $user->name,
        'avatar' => null,
    ];
});

// Chat messages for one conversation — only its participants may listen.
Broadcast::channel('conversation.{conversationId}', function (User $user, int $conversationId) {
    return ChatConversation::query()
        ->where('id', $conversationId)
        ->where('company_id', $user->company_id)
        ->whereHas('participants', fn ($query) => $query->where('user_id', $user->id))
        ->exists();
});
