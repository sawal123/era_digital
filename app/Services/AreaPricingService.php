<?php

namespace App\Services;

use App\Models\Product;

/**
 * Helper for AREA-BASED printing products (spanduk/banner/stiker per m²).
 *
 * Semantics:
 *  - product.selling_price = selling RATE per m²
 *  - product.base_price    = HPP RATE per m²
 *  - quantity on the transaction item = number of physical pieces (pcs)
 *  - length / width / area_per_piece / total_area stored in metadata
 *  - effective (snapshot) selling/base price on the item = rate × area_per_piece
 *
 * All rounding lives here so the frontend estimate and backend snapshot
 * always agree on area & money values.
 */
class AreaPricingService
{
    /**
     * Round an area value (m²) consistently to 2 decimals.
     */
    public static function roundArea(float $value): float
    {
        return round($value, 2);
    }

    /**
     * area_per_piece = length × width (rounded).
     */
    public static function areaPerPiece(float $length, float $width): float
    {
        return self::roundArea($length * $width);
    }

    /**
     * total_area = area_per_piece × quantity (rounded).
     */
    public static function totalArea(float $areaPerPiece, float $quantity): float
    {
        return self::roundArea($areaPerPiece * $quantity);
    }

    /**
     * Effective price per piece = rate × area_per_piece (rounded).
     */
    public static function pricePerPiece(float $rate, float $area): float
    {
        return round($rate * $area, 2);
    }

    /**
     * Whether a product uses area-based pricing (rate per m²).
     *
     * Backward-compatible default: unit "meter" is treated as area-based.
     * If a future `pricing_mode` attribute is introduced it takes precedence.
     */
    public static function isAreaBased(?Product $product): bool
    {
        if (! $product) {
            return false;
        }

        if (isset($product->pricing_mode)) {
            return $product->pricing_mode === 'area';
        }

        return ($product->unit ?? null) === 'meter';
    }
}
