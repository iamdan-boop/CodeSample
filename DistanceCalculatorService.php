<?php

class DistanceCalculatorService {

    public static function calculateDistanceBetween(
        float $fromLatitude,
        float $fromLongitude,
        float $toLatitude,
        float $toLongitude,
    ) : float {
        $earthRadius = 6371.0;

        // Convert latitude and longitude from degrees to radians
        $fromLat = deg2rad($fromLatitude);
        $fromLng = deg2rad($fromLongitude);
        $toLat = deg2rad($toLatitude);
        $toLng = deg2rad($toLongitude);

        // Difference in coordinates
        $dlat = $toLat - $fromLat;
        $dlon = $toLng - $fromLng;

        // Haversine formula
        $a = sin($dlat / 2) ** 2 + cos($fromLat) * cos($toLat) * sin($dlon / 2) ** 2;
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        // Distance in kilometers
        $distance = $earthRadius * $c;
        return $distance;
    }
}