<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Task extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['due_on' => 'date:Y-m-d'];
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }
}
