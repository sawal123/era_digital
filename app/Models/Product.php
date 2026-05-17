<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    protected $fillable = [
        'category_id', 'sku', 'name', 'image_path', 'unit', 'base_price', 
        'selling_price', 'admin_fee', 'stock', 'min_stock', 'is_active'
    ];

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function stockMovements()
    {
        return $this->hasMany(StockMovement::class);
    }

    public function purchases()
    {
        return $this->hasMany(Purchase::class);
    }
}
