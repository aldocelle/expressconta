<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FileVersion extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['size' => 'integer'];
    }

    protected $hidden = ['path'];

    public function report()
    {
        return $this->belongsTo(Report::class);
    }
}
