<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExpenseAllocation extends Model
{
    public const STATUS_BELUM_MASUK_HPP = 'belum_masuk_hpp';
    public const STATUS_SUDAH_MASUK_HPP = 'sudah_masuk_hpp';

    protected $fillable = [
        'expense_id',
        'transaction_id',
        'transaction_item_id',
        'amount',
        'hpp_status',
        'note',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
    ];

    public function expense()
    {
        return $this->belongsTo(Expense::class);
    }

    public function transaction()
    {
        return $this->belongsTo(Transaction::class);
    }

    public function transactionItem()
    {
        return $this->belongsTo(TransactionItem::class);
    }
}
