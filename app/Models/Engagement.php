<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Engagement extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['starts_on' => 'date:Y-m-d', 'active' => 'boolean', 'monthly_fee' => 'integer'];
    }

    public function template()
    {
        return $this->belongsTo(WorkTemplate::class, 'work_template_id');
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }
}
