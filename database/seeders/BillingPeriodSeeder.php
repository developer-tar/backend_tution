<?php

namespace Database\Seeders;

use App\Models\BillingPeriod;
use Illuminate\Database\Seeder;

class BillingPeriodSeeder extends Seeder
{
    public function run(): void
    {
        $billingCycles = config('constants.billing_cycle');
        foreach ($billingCycles as $billingCycle) {
          BillingPeriod::firstOrCreate(['name' => $billingCycle['name'], 'period' => $billingCycle['period']]);
        }
    }
}
