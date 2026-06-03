<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TransactionItem extends Model
{
    protected $fillable = [
        'transaction_id', 'product_id', 'print_vendor_id', 'item_name', 'type', 'unit',
        'quantity', 'base_price', 'selling_price', 'subtotal_base',
        'subtotal_price', 'profit', 'service_status', 'metadata'
    ];

    protected $casts = [
        'metadata' => 'array',
    ];

    public function transaction()
    {
        return $this->belongsTo(Transaction::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function printVendor()
    {
        return $this->belongsTo(PrintVendor::class);
    }
}
