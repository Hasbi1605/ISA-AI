<?php

namespace App\Policies;

use App\Models\Memo;
use App\Models\User;

class MemoPolicy
{
    public function view(User $user, Memo $memo): bool
    {
        return $user->id === $memo->user_id;
    }

    public function update(User $user, Memo $memo): bool
    {
        return $user->id === $memo->user_id;
    }
}
