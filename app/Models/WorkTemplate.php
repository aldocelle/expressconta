<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WorkTemplate extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['checklist' => 'array', 'requests' => 'array', 'active' => 'boolean'];
    }
}
