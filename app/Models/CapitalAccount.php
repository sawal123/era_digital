<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CapitalAccount extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'type',
        'current_balance',
        'contributed_capital',
        'is_active',
    ];

    protected $casts = [
        'current_balance' => 'decimal:2',
        'contributed_capital' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function entries()
    {
        return $this->hasMany(CapitalLedgerEntry::class);
    }
}
