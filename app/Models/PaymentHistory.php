<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentHistory extends Model
{
    protected $fillable = [
        'transaction_id',
        'jumlah_bayar',
        'tanggal_bayar',
        'metode_bayar',
        'keterangan'
    ];

    protected $casts = [
        'tanggal_bayar' => 'datetime',
    ];

    public function transaction()
    {
        return $this->belongsTo(Transaction::class);
    }
}
