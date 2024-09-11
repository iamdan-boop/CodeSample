<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class GoogleGeocodingService 
{
    protected string $apiKey;

    public function __construct()
    {
        $this->apiKey = config('services.google.api_key');
    }


    public function findCoordinates(string $fullAddress): array
    {
        $result = Http::get('https://maps.googleapis.com/maps/api/geocode/json', [
            'address' => $fullAddress,
            'key' => $this->apiKey,
        ]);

        if (!$result->ok()) {
            throw new \Exception('Service not available');
        }

        if (!$result['results']) {
            throw new \Exception('No address found');
        }

        $coordinates = $result['results'][0]['geometry']['location'];
        return [
            'latitude' => $coordinates['lat'],
            'longitude' => $coordinates['lng'],
        ];
    }


    public function getFullAddress(float $latitude, float $longitude): string
    {
        $result = Http::get('https://maps.googleapis.com/maps/api/geocode/json', [
            'latlng' => $latitude . ',' . $longitude,
            'key' => $this->apiKey,
        ]);

        if (!$result->ok()) {
            throw new \Exception('Service not available');
        }

        $fullAddress = $result['results'][0]['formatted_address'];
        return $fullAddress;
    }

}