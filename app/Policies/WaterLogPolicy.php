<?php

namespace App\Policies;

use App\Models\User;
use App\Models\WaterLog;

class WaterLogPolicy
{
    public function delete(User $user, WaterLog $waterLog): bool
    {
        return $waterLog->user_id === $user->id;
    }
}
