<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class SlotsTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Location
        $locationId = 1;

        // 2. Turf
        $turfId = 1;

        // 3. Categories
        $categories = [
            'Morning' => null,
            'Noon' => null,
            'Evening' => null,
        ];

        foreach ($categories as $name => &$id) {
            $existingId = DB::table('slot_categories')->where('category_name', $name)->value('id');
            if ($existingId) {
                $id = $existingId;
            } else {
                $id = DB::table('slot_categories')->insertGetId([
                    'category_name' => $name,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        // 4. Clear existing slots for this turf
        DB::table('slots')->where('turf_id', $turfId)->delete();

        // 5. Generate Slots
        $slotsToInsert = [];

        $ranges = [
            [
                'start' => '05:00',
                'end' => '10:00',
                'category' => $categories['Morning'],
                'weekday_price' => 800,
                'weekend_price' => 1000,
            ],
            [
                'start' => '10:00',
                'end' => '16:00',
                'category' => $categories['Noon'],
                'weekday_price' => 600,
                'weekend_price' => 800,
            ],
            [
                'start' => '16:00',
                'end' => '24:00',
                'category' => $categories['Evening'],
                'weekday_price' => 800,
                'weekend_price' => 1000,
            ],
        ];

        foreach ($ranges as $range) {
            $start = Carbon::createFromFormat('H:i', $range['start']);
            
            // Handle midnight
            if ($range['end'] === '24:00') {
                $end = Carbon::createFromFormat('H:i', '00:00')->addDay();
            } else {
                $end = Carbon::createFromFormat('H:i', $range['end']);
            }

            while ($start < $end) {
                $from = $start->format('H:i:s');
                $start->addMinutes(30);
                
                // If it reached exactly midnight of next day
                if ($start->format('H:i') === '00:00') {
                    $to = '23:59:59';
                } else {
                    $to = $start->format('H:i:s');
                }

                $slotsToInsert[] = [
                    'location_id' => $locationId,
                    'turf_id' => $turfId,
                    'slot_category_id' => $range['category'],
                    'from' => $from,
                    'to' => $to,
                    'minutes' => 30,
                    'mon_amount' => $range['weekday_price'],
                    'tue_amount' => $range['weekday_price'],
                    'wed_amount' => $range['weekday_price'],
                    'thu_amount' => $range['weekday_price'],
                    'fri_amount' => $range['weekday_price'],
                    'sat_amount' => $range['weekend_price'],
                    'sun_amount' => $range['weekend_price'],
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
        }

        DB::table('slots')->insert($slotsToInsert);
    }
}
