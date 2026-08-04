<?php

namespace App\Policies;

use App\Models\ChatConversation;
use App\Models\User;

class ChatConversationPolicy
{
    public function view(User $user, ChatConversation $conversation): bool
    {
        return $conversation->company_id === $user->company_id
            && $conversation->participants()->where('user_id', $user->id)->exists();
    }
}
