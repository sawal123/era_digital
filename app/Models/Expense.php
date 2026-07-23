<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Expense extends Model
{
    protected $fillable = [
        'date', 'name', 'amount', 'category', 'transaction_id', 'hpp_status', 'note'
    ];

    protected $casts = [
        'amount' => 'decimal:2',
    ];

    protected $appends = [
        'allocated_amount',
        'unallocated_amount',
        'allocation_status',
    ];

    public function transaction()
    {
        return $this->belongsTo(Transaction::class);
    }

    public function allocations()
    {
        return $this->hasMany(ExpenseAllocation::class);
    }

    public function scopeAffectsProfit($query)
    {
        return $query->where('category', 'operasional_rutin');
    }

    public function getAllocatedAmountAttribute(): float
    {
        if ($this->relationLoaded('allocations')) {
            return (float) $this->allocations->sum(fn ($allocation) => (float) $allocation->amount);
        }

        if (! $this->exists) {
            return 0.0;
        }

        return (float) $this->allocations()->sum('amount');
    }

    public function getUnallocatedAmountAttribute(): float
    {
        return max(0, (float) $this->amount - $this->allocated_amount);
    }

    public function getAllocationStatusAttribute(): string
    {
        $allocated = $this->allocated_amount;
        $amount = (float) $this->amount;

        if ($allocated <= 0) {
            return 'belum_dialokasikan';
        }

        return $allocated >= $amount ? 'penuh' : 'sebagian';
    }
}
