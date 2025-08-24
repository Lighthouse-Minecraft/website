<?php

namespace Database\Seeders;

use DB;
use Illuminate\Database\Seeder;

class PrayerCountriesJanuary extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('prayer_countries')->insert([
            // The World (Jan 1–11)
            ['day' => '1-1',  'name' => 'The World',  'operation_world_url' => 'https://operationworld.org/prayer-calendar/01-01/', 'prayer_cast_url' => null],
            ['day' => '1-2',  'name' => 'The World',  'operation_world_url' => 'https://operationworld.org/prayer-calendar/01-02/', 'prayer_cast_url' => null],
            ['day' => '1-3',  'name' => 'The World',  'operation_world_url' => 'https://operationworld.org/prayer-calendar/01-03/', 'prayer_cast_url' => null],
            ['day' => '1-4',  'name' => 'The World',  'operation_world_url' => 'https://operationworld.org/prayer-calendar/01-04/', 'prayer_cast_url' => null],
            ['day' => '1-5',  'name' => 'The World',  'operation_world_url' => 'https://operationworld.org/prayer-calendar/01-05/', 'prayer_cast_url' => null],
            ['day' => '1-6',  'name' => 'The World',  'operation_world_url' => 'https://operationworld.org/prayer-calendar/01-06/', 'prayer_cast_url' => null],
            ['day' => '1-7',  'name' => 'The World',  'operation_world_url' => 'https://operationworld.org/prayer-calendar/01-07/', 'prayer_cast_url' => null],
            ['day' => '1-8',  'name' => 'The World',  'operation_world_url' => 'https://operationworld.org/prayer-calendar/01-08/', 'prayer_cast_url' => null],
            ['day' => '1-9',  'name' => 'The World',  'operation_world_url' => 'https://operationworld.org/prayer-calendar/01-09/', 'prayer_cast_url' => null],
            ['day' => '1-10', 'name' => 'The World',  'operation_world_url' => 'https://operationworld.org/prayer-calendar/01-10/', 'prayer_cast_url' => null],
            ['day' => '1-11', 'name' => 'The World',  'operation_world_url' => 'https://operationworld.org/prayer-calendar/01-11/', 'prayer_cast_url' => null],

            // Africa (Jan 12–18)
            ['day' => '1-12', 'name' => 'Africa',     'operation_world_url' => 'https://operationworld.org/prayer-calendar/01-12/', 'prayer_cast_url' => 'https://prayercast.com/topic-category/nations/africa/'],
            ['day' => '1-13', 'name' => 'Africa',     'operation_world_url' => 'https://operationworld.org/prayer-calendar/01-13/', 'prayer_cast_url' => 'https://prayercast.com/topic-category/nations/africa/'],
            ['day' => '1-14', 'name' => 'Africa',     'operation_world_url' => 'https://operationworld.org/prayer-calendar/01-14/', 'prayer_cast_url' => 'https://prayercast.com/topic-category/nations/africa/'],
            ['day' => '1-15', 'name' => 'Africa',     'operation_world_url' => 'https://operationworld.org/prayer-calendar/01-15/', 'prayer_cast_url' => 'https://prayercast.com/topic-category/nations/africa/'],
            ['day' => '1-16', 'name' => 'Africa',     'operation_world_url' => 'https://operationworld.org/prayer-calendar/01-16/', 'prayer_cast_url' => 'https://prayercast.com/topic-category/nations/africa/'],
            ['day' => '1-17', 'name' => 'Africa',     'operation_world_url' => 'https://operationworld.org/prayer-calendar/01-17/', 'prayer_cast_url' => 'https://prayercast.com/topic-category/nations/africa/'],
            ['day' => '1-18', 'name' => 'Africa',     'operation_world_url' => 'https://operationworld.org/prayer-calendar/01-18/', 'prayer_cast_url' => 'https://prayercast.com/topic-category/nations/africa/'],

            // The Americas (Jan 19–24)
            ['day' => '1-19', 'name' => 'The Americas', 'operation_world_url' => 'https://operationworld.org/prayer-calendar/01-19/', 'prayer_cast_url' => null],
            ['day' => '1-20', 'name' => 'The Americas', 'operation_world_url' => 'https://operationworld.org/prayer-calendar/01-20/', 'prayer_cast_url' => null],
            ['day' => '1-21', 'name' => 'The Americas', 'operation_world_url' => 'https://operationworld.org/prayer-calendar/01-21/', 'prayer_cast_url' => null],
            ['day' => '1-22', 'name' => 'The Americas', 'operation_world_url' => 'https://operationworld.org/prayer-calendar/01-22/', 'prayer_cast_url' => null],
            ['day' => '1-23', 'name' => 'The Americas', 'operation_world_url' => 'https://operationworld.org/prayer-calendar/01-23/', 'prayer_cast_url' => null],
            ['day' => '1-24', 'name' => 'The Americas', 'operation_world_url' => 'https://operationworld.org/prayer-calendar/01-24/', 'prayer_cast_url' => null],

            // Asia (Jan 25–31)
            ['day' => '1-25', 'name' => 'Asia',       'operation_world_url' => 'https://operationworld.org/prayer-calendar/01-25/', 'prayer_cast_url' => 'https://prayercast.com/topic-category/nations/asia/'],
            ['day' => '1-26', 'name' => 'Asia',       'operation_world_url' => 'https://operationworld.org/prayer-calendar/01-26/', 'prayer_cast_url' => 'https://prayercast.com/topic-category/nations/asia/'],
            ['day' => '1-27', 'name' => 'Asia',       'operation_world_url' => 'https://operationworld.org/prayer-calendar/01-27/', 'prayer_cast_url' => 'https://prayercast.com/topic-category/nations/asia/'],
            ['day' => '1-28', 'name' => 'Asia',       'operation_world_url' => 'https://operationworld.org/prayer-calendar/01-28/', 'prayer_cast_url' => 'https://prayercast.com/topic-category/nations/asia/'],
            ['day' => '1-29', 'name' => 'Asia',       'operation_world_url' => 'https://operationworld.org/prayer-calendar/01-29/', 'prayer_cast_url' => 'https://prayercast.com/topic-category/nations/asia/'],
            ['day' => '1-30', 'name' => 'Asia',       'operation_world_url' => 'https://operationworld.org/prayer-calendar/01-30/', 'prayer_cast_url' => 'https://prayercast.com/topic-category/nations/asia/'],
            ['day' => '1-31', 'name' => 'Asia',       'operation_world_url' => 'https://operationworld.org/prayer-calendar/01-31/', 'prayer_cast_url' => 'https://prayercast.com/topic-category/nations/asia/'],
        ]);
    }
}
