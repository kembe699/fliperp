<?php

namespace App\Policies;

use App\Models\JournalEntry;
use App\Models\User;

class JournalEntryPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('journal-entries.view');
    }

    public function view(User $user, JournalEntry $entry): bool
    {
        return $user->can('journal-entries.view') && $entry->company_id === $user->company_id;
    }

    public function create(User $user): bool
    {
        return $user->can('journal-entries.create');
    }

    public function update(User $user, JournalEntry $entry): bool
    {
        return $user->can('journal-entries.update') && $entry->company_id === $user->company_id;
    }

    public function delete(User $user, JournalEntry $entry): bool
    {
        return $user->can('journal-entries.delete') && $entry->company_id === $user->company_id;
    }

    public function post(User $user, JournalEntry $entry): bool
    {
        return $user->can('journal-entries.post') && $entry->company_id === $user->company_id;
    }

    public function reverse(User $user, JournalEntry $entry): bool
    {
        return $user->can('journal-entries.reverse') && $entry->company_id === $user->company_id;
    }
}
