<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DocumentRequest extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['due_on' => 'date:Y-m-d', 'required' => 'boolean', 'version' => 'integer'];
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function work()
    {
        return $this->belongsTo(WorkItem::class, 'work_item_id');
    }

    public function files()
    {
        return $this->hasMany(FileVersion::class)->orderByDesc('id');
    }
}
