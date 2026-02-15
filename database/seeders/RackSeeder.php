<?php

namespace Database\Seeders;

use App\Models\Bin;
use App\Models\Rack;
use App\Models\Warehouse;
use Illuminate\Database\Seeder;

class RackSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $warehouse = Warehouse::first();

        $racks = [
            ['code' => 'A', 'label' => 'Fast Moving'],
            ['code' => 'B', 'label' => 'Standard'],
            ['code' => 'C', 'label' => 'Slow Moving'],
            ['code' => 'D', 'label' => 'Bulk / Overflow'],
        ];

        foreach ($racks as $rackData) {
            $rack = Rack::create([
                'warehouse_id' => $warehouse->id,
                'code' => $rackData['code'],
                'label' => $rackData['label'],
            ]);

            for ($i = 1; $i <= 8; $i++) {
                Bin::create([
                    'rack_id' => $rack->id,
                    'number' => $i,
                    'code' => $rackData['code'] . '-' . str_pad($i, 2, '0', STR_PAD_LEFT),
                    'is_available' => true,
                ]);
            }
        }
    }
}
