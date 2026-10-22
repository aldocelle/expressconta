<?php

namespace App\Policies;

use App\Models\Study;
use App\Models\User;

class StudyPolicy
{
    public function view(User $u, Study $s): bool
    {
        return $u->studies()->whereKey($s->id)->exists();
    }
}
