<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CapitalLedgerEntry extends Model
{
    protected $fillable = [
        'capital_account_id',
        'entry_date',
        'entry_type',
        'direction',
        'amount',
        'description',
        'reference_type',
        'reference_id',
    ];

    protected $casts = [
        'entry_date' => 'date:Y-m-d',
        'amount' => 'decimal:2',
    ];

    protected $appends = ['signed_amount'];

    public function account()
    {
        return $this->belongsTo(CapitalAccount::class, 'capital_account_id');
    }

    public function getSignedAmountAttribute(): float
    {
        $amount = (float) $this->amount;

        return $this->direction === 'debit' ? -$amount : $amount;
    }
}
