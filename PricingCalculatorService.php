<?php

namespace App\Services;

use App\Models\Settings;
use Illuminate\Support\Facades\Cache;


class PricingCalculatorService
{
    public function calculatePrice(float $distance) : float
    {
        $settings = Cache::remember('settings', now()->addMinutes(5), fn () => Settings::first());
        if (!$settings) {
            throw new \RuntimeException("Application settings not found!");
        }

        $totalAmount = $settings->base_price;
        if ($settings->base_km >= $distance) {
            $excessKm = $distance - $settings->base_km;
            $totalExcessKmInAmount = $excessKm * $settings->price_for_exceeding_base_km;
            $totalAmount += $totalExcessKmInAmount;
        }

        return $totalAmount;
    }
}