<?php

namespace App\Imports;

use App\Models\Office;
use App\Models\Parcel;
use App\Models\Barangay;
use App\Enums\DeliveryType;
use App\Enums\ItemCategory;
use Illuminate\Support\Str;
use App\Enums\DeliveryProcess;
use App\Enums\DeliveryStatusEnum;
use App\Models\BatchParcelJob;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Concerns\ToModel;
use App\Services\GoogleGeocodingService;
use Illuminate\Queue\InteractsWithQueue;
use Maatwebsite\Excel\Events\AfterSheet;
use Illuminate\Database\Eloquent\Builder;
use App\Services\PricingCalculatorService;
use Maatwebsite\Excel\Concerns\WithEvents;
use App\Services\DistanceCalculatorService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\PersistRelations;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\RegistersEventListeners;

class ParcelImport implements ToModel, PersistRelations, WithChunkReading, WithHeadingRow, ShouldQueue, WithEvents
{
    use InteractsWithQueue;

    use RegistersEventListeners;

    private int $officeId;
    private int $batchParcelJobId;
    private DeliveryProcess $deliveryProcess;
    private DeliveryType $deliveryType;

    public function __construct(
        public GoogleGeocodingService $googleGeocodingService,
        public PricingCalculatorService $pricingCalculatorService,
        public DistanceCalculatorService $distanceCalculatorService,
    ) {
    }


    public function setOfficeId(int $officeId): ParcelImport
    {
        $this->officeId = $officeId;

        return $this;
    }


    public function setBatchParcelJobId(int $batchParcelJobId): ParcelImport
    {
        $this->batchParcelJobId = $batchParcelJobId;

        return $this;
    }


    public function setDeliveryType(DeliveryType $deliveryType): ParcelImport
    {
        $this->deliveryType = $deliveryType;

        return $this;
    }

    public function setDeliveryProcess(DeliveryProcess $deliveryProcess): ParcelImport
    {
        $this->deliveryProcess = $deliveryProcess;

        return $this;
    }

    /**
     * @param array $row
     *
     * @return \Illuminate\Database\Eloquent\Model|null
     */
    public function model(array $row): Parcel
    {
        Log::info("[ParcelImport]: Parcel Import initialized at " . now(), [
            'Office-Id' => $this->officeId,
            'BatchParcel-Id' => $this->batchParcelJobId,
        ]);

        $office = Office::find($this->officeId);

        $barangay = Barangay::where('name', $row['barangay'])
            ->whereHas('city', function (Builder $query) use ($row) {
                $query->where('name', $row['city'])
                    ->whereHas('province', fn(Builder $relationQuery) => $relationQuery->where('name', $row['province']));
            })
            ->first();
        if (!$barangay) {
            throw new \Exception('No Barangay found with ' . $row['barangay']);
        }

        $destinationCoordinates = $this->googleGeocodingService
            ->findCoordinates("{$row['nearest_landmark']} {$row['street_address']} {$barangay->name}");

        $totalDistance = $this->distanceCalculatorService->calculateDistanceBetween(
            fromLatitude: $office->latitude,
            fromLongitude: $office->longitude,
            toLatitude: $destinationCoordinates['latitude'],
            toLongitude: $destinationCoordinates['longitude'],
        );

        $totalAmount = $this->pricingCalculatorService->calculatePrice($totalDistance);
        if ($totalAmount == 0) {
            throw new \RuntimeException("Invalid Location {$totalDistance}");
        }

        $parcel = Parcel::create([
            'weight' => 0,
            'goods_value' => 0,
            'status' => DeliveryStatusEnum::Pending(),
            'reference_number' => Str::random(16),
            'item_category' => ItemCategory::Document,
            'delivery_type' => $this->deliveryType,
            'delivery_process' => $this->deliveryProcess,
            'office_id' => $this->officeId,
            'total_amount' => $totalAmount,
            'batch_parcel_job_id' => $this->batchParcelJobId,
            'valuation_fee' => 0,
        ]);


        $parcel->receiverShipmentAddress()->create([
            'name' => $row['recipient_name'],
            'mobile_number' => $row['mobile_number'],
            'street_address' => $row['street_address'],
            'nearest_landmark' => $row['nearest_landmark'],
            'barangay_id' => $barangay->id,
            'latitude' => $destinationCoordinates['latitude'],
            'longitude' => $destinationCoordinates['longitude']
        ]);

        return $parcel;
    }


    public function chunkSize(): int
    {
        return 1000;
    }


    public function afterSheet(AfterSheet $event): void
    {
        Log::info("[ParcelImport]: Parcel Import finished at " . now(), [
            'Office-Id' => $this->officeId,
            'BatchParcel-Id' => $this->batchParcelJobId,
        ]);

        BatchParcelJob::find($this->batchParcelJobId)
            ->update(['finished_at' => now()]);
    }
}