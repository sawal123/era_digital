<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CustomerDigitalAccount extends Model
{
    protected $fillable = ['customer_id', 'type', 'account_number', 'account_name'];

    protected static function booted(): void
    {
        static::saving(function (CustomerDigitalAccount $account) {
            $account->account_number = self::normalizeNumber($account->account_number);
        });
    }

    public static function normalizeNumber(?string $number): string
    {
        return preg_replace('/\s+/', '', trim((string) $number));
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }
}
