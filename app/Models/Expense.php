<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Expense extends Model
{
    protected $fillable = [
        'date', 'name', 'amount', 'category', 'transaction_id', 'hpp_status', 'note'
    ];

    public function transaction()
    {
        return $this->belongsTo(Transaction::class);
    }

    public function scopeAffectsProfit($query)
    {
        return $query
            ->where('category', '<>', 'pribadi_pemilik')
            ->where(function ($query) {
                $query
                    ->where('category', '<>', 'hpp_pesanan')
                    ->orWhere('hpp_status', '<>', 'sudah_masuk_hpp')
                    ->orWhereNull('hpp_status');
            });
    }
}
