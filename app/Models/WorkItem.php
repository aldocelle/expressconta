<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WorkItem extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['checklist' => 'array', 'due_on' => 'date:Y-m-d', 'approved_at' => 'datetime', 'version' => 'integer'];
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function requests()
    {
        return $this->hasMany(DocumentRequest::class);
    }

    public function reports()
    {
        return $this->hasMany(Report::class);
    }
}
