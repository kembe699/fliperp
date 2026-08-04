<?php

namespace App\Policies;

use App\Models\GoodsReceivedNote;
use App\Models\User;

class GoodsReceivedNotePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('goods-received-notes.view');
    }

    public function view(User $user, GoodsReceivedNote $goodsReceivedNote): bool
    {
        return $user->can('goods-received-notes.view') && $goodsReceivedNote->company_id === $user->company_id;
    }

    public function create(User $user): bool
    {
        return $user->can('goods-received-notes.create');
    }

    public function update(User $user, GoodsReceivedNote $goodsReceivedNote): bool
    {
        return $user->can('goods-received-notes.update') && $goodsReceivedNote->company_id === $user->company_id;
    }

    public function delete(User $user, GoodsReceivedNote $goodsReceivedNote): bool
    {
        return $user->can('goods-received-notes.delete') && $goodsReceivedNote->company_id === $user->company_id;
    }

    public function confirm(User $user, GoodsReceivedNote $goodsReceivedNote): bool
    {
        return $user->can('goods-received-notes.confirm') && $goodsReceivedNote->company_id === $user->company_id;
    }
}
