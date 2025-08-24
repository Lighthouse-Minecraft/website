<?php

namespace Database\Seeders;

use DB;
use Illuminate\Database\Seeder;

class PrayerCountriesMay extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('prayer_countries')->insert([
            ['day' => '5-1',  'name' => 'Costa Rica',                                   'operation_world_url' => 'https://operationworld.org/prayer-calendar/05-01/', 'prayer_cast_url' => 'https://prayercast.com/prayer-topic/costa-rica/'],        // :contentReference[oaicite:1]{index=1}
            ['day' => '5-2',  'name' => 'Cote d’Ivoire',                                'operation_world_url' => 'https://operationworld.org/prayer-calendar/05-02/', 'prayer_cast_url' => 'https://prayercast.com/prayer-topic/cote-divoire/'],      // :contentReference[oaicite:2]{index=2}
            ['day' => '5-3',  'name' => 'Cote d’Ivoire',                                'operation_world_url' => 'https://operationworld.org/prayer-calendar/05-03/', 'prayer_cast_url' => 'https://prayercast.com/prayer-topic/cote-divoire/'],      // :contentReference[oaicite:3]{index=3}
            ['day' => '5-4',  'name' => 'Croatia',                                      'operation_world_url' => 'https://operationworld.org/prayer-calendar/05-04/', 'prayer_cast_url' => 'https://prayercast.com/prayer-topic/croatia/'],            // :contentReference[oaicite:4]{index=4}
            ['day' => '5-5',  'name' => 'Cuba',                                         'operation_world_url' => 'https://operationworld.org/prayer-calendar/05-05/', 'prayer_cast_url' => 'https://prayercast.com/prayer-topic/cuba/'],               // :contentReference[oaicite:5]{index=5}
            ['day' => '5-6',  'name' => 'Cyprus',                                       'operation_world_url' => 'https://operationworld.org/prayer-calendar/05-06/', 'prayer_cast_url' => 'https://prayercast.com/prayer-topic/cyprus/'],             // :contentReference[oaicite:6]{index=6}
            ['day' => '5-7',  'name' => 'Czechia',                                      'operation_world_url' => 'https://operationworld.org/prayer-calendar/05-07/', 'prayer_cast_url' => 'https://prayercast.com/prayer-topic/czech-republic/'],    // :contentReference[oaicite:7]{index=7}
            ['day' => '5-8',  'name' => 'Denmark',                                      'operation_world_url' => 'https://operationworld.org/prayer-calendar/05-08/', 'prayer_cast_url' => 'https://prayercast.com/prayer-topic/denmark/'],           // :contentReference[oaicite:8]{index=8}
            ['day' => '5-9',  'name' => 'Djibouti',                                     'operation_world_url' => 'https://operationworld.org/prayer-calendar/05-09/', 'prayer_cast_url' => 'https://prayercast.com/prayer-topic/djibouti/'],          // :contentReference[oaicite:9]{index=9}
            ['day' => '5-10', 'name' => 'Dominica and Dominican Republic',              'operation_world_url' => 'https://operationworld.org/prayer-calendar/05-10/', 'prayer_cast_url' => 'https://prayercast.com/prayer-topic/dominica/'],         // first available, per rule. :contentReference[oaicite:10]{index=10}
            ['day' => '5-11', 'name' => 'Ecuador',                                      'operation_world_url' => 'https://operationworld.org/prayer-calendar/05-11/', 'prayer_cast_url' => 'https://prayercast.com/prayer-topic/ecuador/'],           // :contentReference[oaicite:11]{index=11}
            ['day' => '5-12', 'name' => 'Egypt',                                        'operation_world_url' => 'https://operationworld.org/prayer-calendar/05-12/', 'prayer_cast_url' => 'https://prayercast.com/prayer-topic/egypt/'],             // :contentReference[oaicite:12]{index=12}
            ['day' => '5-13', 'name' => 'Egypt',                                        'operation_world_url' => 'https://operationworld.org/prayer-calendar/05-13/', 'prayer_cast_url' => 'https://prayercast.com/prayer-topic/egypt/'],             // :contentReference[oaicite:13]{index=13}
            ['day' => '5-14', 'name' => 'El Salvador',                                  'operation_world_url' => 'https://operationworld.org/prayer-calendar/05-14/', 'prayer_cast_url' => 'https://prayercast.com/prayer-topic/el-salvador/'],       // :contentReference[oaicite:14]{index=14}
            ['day' => '5-15', 'name' => 'Equatorial Guinea',                            'operation_world_url' => 'https://operationworld.org/prayer-calendar/05-15/', 'prayer_cast_url' => 'https://prayercast.com/prayer-topic/equatorial-guinea/'], // :contentReference[oaicite:15]{index=15}
            ['day' => '5-16', 'name' => 'Eritrea',                                      'operation_world_url' => 'https://operationworld.org/prayer-calendar/05-16/', 'prayer_cast_url' => 'https://prayercast.com/prayer-topic/eritrea/'],           // :contentReference[oaicite:16]{index=16}
            ['day' => '5-17', 'name' => 'Eritrea',                                      'operation_world_url' => 'https://operationworld.org/prayer-calendar/05-17/', 'prayer_cast_url' => 'https://prayercast.com/prayer-topic/eritrea/'],           // :contentReference[oaicite:17]{index=17}
            ['day' => '5-18', 'name' => 'Estonia',                                      'operation_world_url' => 'https://operationworld.org/prayer-calendar/05-18/', 'prayer_cast_url' => 'https://prayercast.com/prayer-topic/estonia/'],           // :contentReference[oaicite:18]{index=18}
            ['day' => '5-19', 'name' => 'Ethiopia',                                     'operation_world_url' => 'https://operationworld.org/prayer-calendar/05-19/', 'prayer_cast_url' => 'https://prayercast.com/prayer-topic/ethiopia/'],          // :contentReference[oaicite:19]{index=19}
            ['day' => '5-20', 'name' => 'Ethiopia',                                     'operation_world_url' => 'https://operationworld.org/prayer-calendar/05-20/', 'prayer_cast_url' => 'https://prayercast.com/prayer-topic/ethiopia/'],          // :contentReference[oaicite:20]{index=20}
            ['day' => '5-21', 'name' => 'Falkland Islands and Faroe Islands',           'operation_world_url' => 'https://operationworld.org/prayer-calendar/05-21/', 'prayer_cast_url' => null],                                                   // neither nation has a dedicated PrayerCast page. :contentReference[oaicite:21]{index=21}
            ['day' => '5-22', 'name' => 'Fiji',                                         'operation_world_url' => 'https://operationworld.org/prayer-calendar/05-22/', 'prayer_cast_url' => 'https://prayercast.com/prayer-topic/fiji/'],              // :contentReference[oaicite:22]{index=22}
            ['day' => '5-23', 'name' => 'Finland',                                      'operation_world_url' => 'https://operationworld.org/prayer-calendar/05-23/', 'prayer_cast_url' => 'https://prayercast.com/prayer-topic/finland/'],           // :contentReference[oaicite:23]{index=23}
            ['day' => '5-24', 'name' => 'France',                                       'operation_world_url' => 'https://operationworld.org/prayer-calendar/05-24/', 'prayer_cast_url' => 'https://prayercast.com/prayer-topic/france/'],            // :contentReference[oaicite:24]{index=24}
            ['day' => '5-25', 'name' => 'France',                                       'operation_world_url' => 'https://operationworld.org/prayer-calendar/05-25/', 'prayer_cast_url' => 'https://prayercast.com/prayer-topic/france/'],            // :contentReference[oaicite:25]{index=25}
            ['day' => '5-26', 'name' => 'French Guiana and French Polynesia',           'operation_world_url' => 'https://operationworld.org/prayer-calendar/05-26/', 'prayer_cast_url' => 'https://prayercast.com/prayer-topic/french-guiana/'],    // first available. :contentReference[oaicite:26]{index=26}
            ['day' => '5-27', 'name' => 'Gabon',                                        'operation_world_url' => 'https://operationworld.org/prayer-calendar/05-27/', 'prayer_cast_url' => 'https://prayercast.com/prayer-topic/gabon/'],             // :contentReference[oaicite:27]{index=27}
            ['day' => '5-28', 'name' => 'The Gambia',                                   'operation_world_url' => 'https://operationworld.org/prayer-calendar/05-28/', 'prayer_cast_url' => 'https://prayercast.com/prayer-topic/gambia/'],            // :contentReference[oaicite:28]{index=28}
            ['day' => '5-29', 'name' => 'Georgia',                                      'operation_world_url' => 'https://operationworld.org/prayer-calendar/05-29/', 'prayer_cast_url' => 'https://prayercast.com/prayer-topic/georgia/'],           // :contentReference[oaicite:29]{index=29}
            ['day' => '5-30', 'name' => 'Germany',                                      'operation_world_url' => 'https://operationworld.org/prayer-calendar/05-30/', 'prayer_cast_url' => 'https://prayercast.com/prayer-topic/germany/'],           // :contentReference[oaicite:30]{index=30}
            ['day' => '5-31', 'name' => 'Germany',                                      'operation_world_url' => 'https://operationworld.org/prayer-calendar/05-31/', 'prayer_cast_url' => 'https://prayercast.com/prayer-topic/germany/'],           // :contentReference[oaicite:31]{index=31}
        ]);
    }
}
