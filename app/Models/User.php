<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use Notifiable;

    protected $guarded = ['id'];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return ['password' => 'hashed', 'email_verified_at' => 'datetime'];
    }

    public function studies()
    {
        return $this->belongsToMany(Study::class, 'study_memberships')->withPivot('role');
    }

    public function companies()
    {
        return $this->belongsToMany(Company::class, 'company_memberships');
    }
}
