<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Company extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['archived_at' => 'datetime', 'monthly_fee' => 'integer', 'starts_on' => 'date:Y-m-d'];
    }

    public function responsible()
    {
        return $this->belongsTo(User::class, 'responsible_id');
    }

    public function members()
    {
        return $this->belongsToMany(User::class, 'company_memberships');
    }
}
