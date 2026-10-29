<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Customer extends Model
{
    protected $fillable = ['name', 'customer_type', 'phone', 'address'];

    protected static function booted(): void
    {
        static::saving(function (Customer $customer) {
            $customer->name_key = self::normalizeName($customer->name);
        });
    }

    public static function normalizeName(?string $name): string
    {
        return Str::squish(Str::lower((string) $name));
    }

    public function transactions()
    {
        return $this->hasMany(Transaction::class);
    }

    public function digitalAccounts()
    {
        return $this->hasMany(CustomerDigitalAccount::class);
    }
}
