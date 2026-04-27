<?php

namespace Database\Seeders;

use App\Models\Package;
use Illuminate\Database\Seeder;

class PackageSeeder extends Seeder
{
    public function run(): void
    {
        $packages = [
            ['id' => 'starter',    'name' => 'Starter Pack',    'price_npr' => 999,  'credits' => 10,  'popular' => false],
            ['id' => 'growth',     'name' => 'Growth Pack',     'price_npr' => 2499, 'credits' => 30,  'popular' => true],
            ['id' => 'pro',        'name' => 'Pro Pack',        'price_npr' => 4999, 'credits' => 75,  'popular' => false],
            ['id' => 'enterprise', 'name' => 'Enterprise Pack', 'price_npr' => 9999, 'credits' => 200, 'popular' => false],
        ];

        foreach ($packages as $package) {
            Package::updateOrCreate(['id' => $package['id']], $package);
        }
    }
}
