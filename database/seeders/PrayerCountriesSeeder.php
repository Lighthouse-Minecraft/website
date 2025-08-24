<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class PrayerCountriesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->call([
            PrayerCountriesJanuary::class,
            PrayerCountriesFebruary::class,
            PrayerCountriesMarch::class,
            PrayerCountriesApril::class,
            PrayerCountriesMay::class,
            PrayerCountriesJune::class,
            PrayerCountriesJuly::class,
            PrayerCountriesAugust::class,
            PrayerCountriesSeptember::class,
            PrayerCountriesOctober::class,
            PrayerCountriesNovember::class,
            PrayerCountriesDecember::class,
        ]);
    }
}
