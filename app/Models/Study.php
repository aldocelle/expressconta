<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Study extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [];
    }
}
