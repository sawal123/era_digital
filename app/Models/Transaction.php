<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Transaction extends Model
{
    protected $fillable = [
        'invoice_number', 'cashier_id', 'customer_id', 'customer_name', 'customer_phone',
        'total_base_price', 'total_price', 'total_profit',
        'payment_method', 'payment_method_id', 'payment_status', 'status_bayar',
        'jumlah_dibayar', 'uang_diterima', 'kembalian', 'sisa_tagihan', 'keterangan'
    ];

    public function cashier()
    {
        return $this->belongsTo(User::class, 'cashier_id');
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function items()
    {
        return $this->hasMany(TransactionItem::class);
    }

    public function paymentHistories()
    {
        return $this->hasMany(PaymentHistory::class);
    }

    public function paymentMethodMaster()
    {
        return $this->belongsTo(PaymentMethod::class, 'payment_method_id');
    }
}
