<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PrintVendor extends Model
{
    protected $fillable = ['name', 'phone', 'address', 'is_active'];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function transactionItems()
    {
        return $this->hasMany(TransactionItem::class);
    }
}
