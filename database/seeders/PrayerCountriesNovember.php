<?php

namespace Database\Seeders;

use DB;
use Illuminate\Database\Seeder;

class PrayerCountriesNovember extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('prayer_countries')->insert([
            ['day' => '11-1',  'name' => 'South Africa', 'operation_world_url' => 'https://operationworld.org/prayer-calendar/11-01/', 'prayer_cast_url' => 'https://prayercast.com/prayer-topic/south-africa/'], // :contentReference[oaicite:1]{index=1}
            ['day' => '11-2',  'name' => 'Spain', 'operation_world_url' => 'https://operationworld.org/prayer-calendar/11-02/', 'prayer_cast_url' => 'https://prayercast.com/prayer-topic/spain/'], // :contentReference[oaicite:2]{index=2}
            ['day' => '11-3',  'name' => 'Spain', 'operation_world_url' => 'https://operationworld.org/prayer-calendar/11-03/', 'prayer_cast_url' => 'https://prayercast.com/prayer-topic/spain/'], // :contentReference[oaicite:3]{index=3}
            ['day' => '11-4',  'name' => 'Sri Lanka', 'operation_world_url' => 'https://operationworld.org/prayer-calendar/11-04/', 'prayer_cast_url' => 'https://prayercast.com/prayer-topic/sri-lanka/'], // :contentReference[oaicite:4]{index=4}
            ['day' => '11-5',  'name' => 'Sri Lanka', 'operation_world_url' => 'https://operationworld.org/prayer-calendar/11-05/', 'prayer_cast_url' => 'https://prayercast.com/prayer-topic/sri-lanka/'], // :contentReference[oaicite:5]{index=5}
            ['day' => '11-6',  'name' => 'Saint Barthelemy and Saint Helena and Saint Kitts & Nevis and Saint Lucia', 'operation_world_url' => 'https://operationworld.org/prayer-calendar/11-06/', 'prayer_cast_url' => 'https://prayercast.com/prayer-topic/saint-kitts-and-nevis/'], // first that exists :contentReference[oaicite:6]{index=6}
            ['day' => '11-7',  'name' => 'Saint Martin and Saint Pierre & Miquelon and Saint Vincent', 'operation_world_url' => 'https://operationworld.org/prayer-calendar/11-07/', 'prayer_cast_url' => 'https://prayercast.com/prayer-topic/saint-vincent-and-the-grenadines/'], // first that exists :contentReference[oaicite:7]{index=7}
            ['day' => '11-8',  'name' => 'Sudan', 'operation_world_url' => 'https://operationworld.org/prayer-calendar/11-08/', 'prayer_cast_url' => 'https://prayercast.com/prayer-topic/sudan/'], // :contentReference[oaicite:8]{index=8}
            ['day' => '11-9',  'name' => 'Sudan', 'operation_world_url' => 'https://operationworld.org/prayer-calendar/11-09/', 'prayer_cast_url' => 'https://prayercast.com/prayer-topic/sudan/'], // :contentReference[oaicite:9]{index=9}
            ['day' => '11-10', 'name' => 'South Sudan', 'operation_world_url' => 'https://operationworld.org/prayer-calendar/11-10/', 'prayer_cast_url' => 'https://prayercast.com/prayer-topic/south-sudan/'], // :contentReference[oaicite:10]{index=10}
            ['day' => '11-11', 'name' => 'Eswatini and Suriname', 'operation_world_url' => 'https://operationworld.org/prayer-calendar/11-11/', 'prayer_cast_url' => 'https://prayercast.com/prayer-topic/eswatini/'], // first that exists :contentReference[oaicite:11]{index=11}
            ['day' => '11-12', 'name' => 'Sweden', 'operation_world_url' => 'https://operationworld.org/prayer-calendar/11-12/', 'prayer_cast_url' => 'https://prayercast.com/prayer-topic/sweden/'], // :contentReference[oaicite:12]{index=12}
            ['day' => '11-13', 'name' => 'Switzerland', 'operation_world_url' => 'https://operationworld.org/prayer-calendar/11-13/', 'prayer_cast_url' => 'https://prayercast.com/prayer-topic/switzerland/'], // :contentReference[oaicite:13]{index=13}
            ['day' => '11-14', 'name' => 'Syria', 'operation_world_url' => 'https://operationworld.org/prayer-calendar/11-14/', 'prayer_cast_url' => 'https://prayercast.com/prayer-topic/syria/'], // :contentReference[oaicite:14]{index=14}
            ['day' => '11-15', 'name' => 'Tajikistan', 'operation_world_url' => 'https://operationworld.org/prayer-calendar/11-15/', 'prayer_cast_url' => 'https://prayercast.com/prayer-topic/tajikistan/'], // :contentReference[oaicite:15]{index=15}
            ['day' => '11-16', 'name' => 'Tanzania', 'operation_world_url' => 'https://operationworld.org/prayer-calendar/11-16/', 'prayer_cast_url' => 'https://prayercast.com/prayer-topic/tanzania/'], // :contentReference[oaicite:16]{index=16}
            ['day' => '11-17', 'name' => 'Tanzania', 'operation_world_url' => 'https://operationworld.org/prayer-calendar/11-17/', 'prayer_cast_url' => 'https://prayercast.com/prayer-topic/tanzania/'], // :contentReference[oaicite:17]{index=17}
            ['day' => '11-18', 'name' => 'Thailand', 'operation_world_url' => 'https://operationworld.org/prayer-calendar/11-18/', 'prayer_cast_url' => 'https://prayercast.com/prayer-topic/thailand/'], // :contentReference[oaicite:18]{index=18}
            ['day' => '11-19', 'name' => 'Thailand', 'operation_world_url' => 'https://operationworld.org/prayer-calendar/11-19/', 'prayer_cast_url' => 'https://prayercast.com/prayer-topic/thailand/'], // :contentReference[oaicite:19]{index=19}
            ['day' => '11-20', 'name' => 'Timor Leste', 'operation_world_url' => 'https://operationworld.org/prayer-calendar/11-20/', 'prayer_cast_url' => 'https://prayercast.com/prayer-topic/timor-leste/'], // :contentReference[oaicite:20]{index=20}
            ['day' => '11-21', 'name' => 'Togo', 'operation_world_url' => 'https://operationworld.org/prayer-calendar/11-21/', 'prayer_cast_url' => 'https://prayercast.com/prayer-topic/togo/'], //
            ['day' => '11-22', 'name' => 'Tonga and Trinidad & Tobago', 'operation_world_url' => 'https://operationworld.org/prayer-calendar/11-22/', 'prayer_cast_url' => 'https://prayercast.com/prayer-topic/tonga/'], // first that exists
            ['day' => '11-23', 'name' => 'Tunisia', 'operation_world_url' => 'https://operationworld.org/prayer-calendar/11-23/', 'prayer_cast_url' => 'https://prayercast.com/prayer-topic/tunisia/'], //
            ['day' => '11-24', 'name' => 'Turkey', 'operation_world_url' => 'https://operationworld.org/prayer-calendar/11-24/', 'prayer_cast_url' => 'https://prayercast.com/prayer-topic/turkey/'], //
            ['day' => '11-25', 'name' => 'Turkey', 'operation_world_url' => 'https://operationworld.org/prayer-calendar/11-25/', 'prayer_cast_url' => 'https://prayercast.com/prayer-topic/turkey/'], //
            ['day' => '11-26', 'name' => 'Turkey', 'operation_world_url' => 'https://operationworld.org/prayer-calendar/11-26/', 'prayer_cast_url' => 'https://prayercast.com/prayer-topic/turkey/'], //
            ['day' => '11-27', 'name' => 'Turkmenistan', 'operation_world_url' => 'https://operationworld.org/prayer-calendar/11-27/', 'prayer_cast_url' => 'https://prayercast.com/prayer-topic/turkmenistan/'], // :contentReference[oaicite:27]{index=27}
            ['day' => '11-28', 'name' => 'Turks & Caicos Islands and Tuvalu', 'operation_world_url' => 'https://operationworld.org/prayer-calendar/11-28/', 'prayer_cast_url' => 'https://prayercast.com/prayer-topic/turks-and-caicos/'], // first that exists :contentReference[oaicite:28]{index=28}
            ['day' => '11-29', 'name' => 'Uganda', 'operation_world_url' => 'https://operationworld.org/prayer-calendar/11-29/', 'prayer_cast_url' => 'https://prayercast.com/prayer-topic/uganda/'], //
            ['day' => '11-30', 'name' => 'Uganda', 'operation_world_url' => 'https://operationworld.org/prayer-calendar/11-30/', 'prayer_cast_url' => 'https://prayercast.com/prayer-topic/uganda/'], //
        ]);
    }
}
