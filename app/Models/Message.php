<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Message extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['internal' => 'boolean'];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
