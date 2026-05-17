<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CustomerDigitalAccount extends Model
{
    protected $fillable = ['customer_id', 'type', 'account_number', 'account_name'];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }
}
