<?php

namespace Database\Seeders;

use App\Models\MeasurementUnit;
use Illuminate\Database\Seeder;

class MeasurementUnitSeeder extends Seeder
{
    public function run(): void
    {
        foreach ([
            ['Bag', 'bag', 'count', 0], ['Kilogram', 'kg', 'weight', 3], ['Metric Ton', 't', 'weight', 3],
            ['Piece', 'pcs', 'count', 0], ['Cubic Foot', 'ft³', 'volume', 3], ['Cubic Meter', 'm³', 'volume', 3],
            ['Square Foot', 'ft²', 'area', 2], ['Meter', 'm', 'length', 3], ['Liter', 'L', 'volume', 3],
        ] as [$name,$symbol,$type,$precision]) {
            $exists = MeasurementUnit::query()->whereNull('company_id')->where('is_system', true)
                ->whereRaw('LOWER(TRIM(symbol)) = ?', [mb_strtolower($symbol)])->exists();
            if (! $exists) {
                MeasurementUnit::forceCreate(['company_id' => null, 'is_system' => true, 'symbol' => $symbol,
                    'name' => $name, 'unit_type' => $type, 'decimal_precision' => $precision, 'is_active' => true]);
            }
        }
    }
}
