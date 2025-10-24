<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\CivilStatus;

class CivilStatusSeeder extends Seeder
{
    public function run(): void
    {
        $statuses = [
            ['name' => 'Single', 'code' => 'single', 'description' => 'Not married'],
            ['name' => 'Married', 'code' => 'married', 'description' => 'Legally married'],
            ['name' => 'Widowed', 'code' => 'widowed', 'description' => 'Spouse has passed away'],
            ['name' => 'Separated', 'code' => 'separated', 'description' => 'Legally separated'],
        ];

        foreach ($statuses as $status) {
            CivilStatus::updateOrCreate(
                ['name' => $status['name']],
                [
                    'code' => $status['code'],
                    'description' => $status['description'],
                    'status_id' => 1,
                    'created_by' => null,
                ]
            );
        }
    }
}