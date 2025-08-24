<?php

namespace Database\Seeders;

use DB;
use Illuminate\Database\Seeder;

class PrayerCountriesOctober extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('prayer_countries')->insert([
            ['day' => '10-1',  'name' => 'Paraguay', 'operation_world_url' => 'https://operationworld.org/prayer-calendar/10-01/', 'prayer_cast_url' => 'https://prayercast.com/prayer-topic/paraguay/'],
            ['day' => '10-2',  'name' => 'Peru', 'operation_world_url' => 'https://operationworld.org/prayer-calendar/10-02/', 'prayer_cast_url' => 'https://prayercast.com/prayer-topic/peru/'],
            ['day' => '10-3',  'name' => 'Philippines', 'operation_world_url' => 'https://operationworld.org/prayer-calendar/10-03/', 'prayer_cast_url' => 'https://prayercast.com/prayer-topic/philippines/'],
            ['day' => '10-4',  'name' => 'Philippines', 'operation_world_url' => 'https://operationworld.org/prayer-calendar/10-04/', 'prayer_cast_url' => 'https://prayercast.com/prayer-topic/philippines/'],
            ['day' => '10-5',  'name' => 'Poland', 'operation_world_url' => 'https://operationworld.org/prayer-calendar/10-05/', 'prayer_cast_url' => 'https://prayercast.com/prayer-topic/poland/'],
            ['day' => '10-6',  'name' => 'Portugal', 'operation_world_url' => 'https://operationworld.org/prayer-calendar/10-06/', 'prayer_cast_url' => 'https://prayercast.com/prayer-topic/portugal/'],
            ['day' => '10-7',  'name' => 'Puerto Rico and Qatar', 'operation_world_url' => 'https://operationworld.org/prayer-calendar/10-07/', 'prayer_cast_url' => 'https://prayercast.com/prayer-topic/puerto-rico/'],
            ['day' => '10-8',  'name' => 'Réunion and Romania', 'operation_world_url' => 'https://operationworld.org/prayer-calendar/10-08/', 'prayer_cast_url' => 'https://prayercast.com/prayer-topic/reunion/'],
            ['day' => '10-9',  'name' => 'Russia', 'operation_world_url' => 'https://operationworld.org/prayer-calendar/10-09/', 'prayer_cast_url' => 'https://prayercast.com/prayer-topic/russia/'],
            ['day' => '10-10', 'name' => 'Russia', 'operation_world_url' => 'https://operationworld.org/prayer-calendar/10-10/', 'prayer_cast_url' => 'https://prayercast.com/prayer-topic/russia/'],
            ['day' => '10-11', 'name' => 'Rwanda and Saint Helena', 'operation_world_url' => 'https://operationworld.org/prayer-calendar/10-11/', 'prayer_cast_url' => 'https://prayercast.com/prayer-topic/rwanda/'],
            ['day' => '10-12', 'name' => 'Saint Kitts and Nevis and Saint Lucia', 'operation_world_url' => 'https://operationworld.org/prayer-calendar/10-12/', 'prayer_cast_url' => 'https://prayercast.com/prayer-topic/saint-kitts-and-nevis/'],
            ['day' => '10-13', 'name' => 'Saint Pierre and Miquelon and Saint Vincent and the Grenadines', 'operation_world_url' => 'https://operationworld.org/prayer-calendar/10-13/', 'prayer_cast_url' => 'https://prayercast.com/prayer-topic/saint-vincent-and-the-grenadines/'],
            ['day' => '10-14', 'name' => 'Samoa', 'operation_world_url' => 'https://operationworld.org/prayer-calendar/10-14/', 'prayer_cast_url' => 'https://prayercast.com/prayer-topic/samoa/'],
            ['day' => '10-15', 'name' => 'San Marino and São Tomé and Príncipe', 'operation_world_url' => 'https://operationworld.org/prayer-calendar/10-15/', 'prayer_cast_url' => 'https://prayercast.com/prayer-topic/san-marino/'],
            ['day' => '10-16', 'name' => 'Saudi Arabia', 'operation_world_url' => 'https://operationworld.org/prayer-calendar/10-16/', 'prayer_cast_url' => 'https://prayercast.com/prayer-topic/saudi-arabia/'],
            ['day' => '10-17', 'name' => 'Senegal', 'operation_world_url' => 'https://operationworld.org/prayer-calendar/10-17/', 'prayer_cast_url' => 'https://prayercast.com/prayer-topic/senegal/'],
            ['day' => '10-18', 'name' => 'Serbia', 'operation_world_url' => 'https://operationworld.org/prayer-calendar/10-18/', 'prayer_cast_url' => 'https://prayercast.com/prayer-topic/serbia/'],
            ['day' => '10-19', 'name' => 'Seychelles', 'operation_world_url' => 'https://operationworld.org/prayer-calendar/10-19/', 'prayer_cast_url' => 'https://prayercast.com/prayer-topic/seychelles/'],
            ['day' => '10-20', 'name' => 'Sierra Leone', 'operation_world_url' => 'https://operationworld.org/prayer-calendar/10-20/', 'prayer_cast_url' => 'https://prayercast.com/prayer-topic/sierra-leone/'],
            ['day' => '10-21', 'name' => 'Singapore', 'operation_world_url' => 'https://operationworld.org/prayer-calendar/10-21/', 'prayer_cast_url' => 'https://prayercast.com/prayer-topic/singapore/'],
            ['day' => '10-22', 'name' => 'Slovakia', 'operation_world_url' => 'https://operationworld.org/prayer-calendar/10-22/', 'prayer_cast_url' => 'https://prayercast.com/prayer-topic/slovakia/'],
            ['day' => '10-23', 'name' => 'Slovenia and Solomon Islands', 'operation_world_url' => 'https://operationworld.org/prayer-calendar/10-23/', 'prayer_cast_url' => 'https://prayercast.com/prayer-topic/slovenia/'],
            ['day' => '10-24', 'name' => 'Solomon Islands and Somalia', 'operation_world_url' => 'https://operationworld.org/prayer-calendar/10-24/', 'prayer_cast_url' => 'https://prayercast.com/prayer-topic/solomon-islands/'],
            ['day' => '10-25', 'name' => 'Somalia', 'operation_world_url' => 'https://operationworld.org/prayer-calendar/10-25/', 'prayer_cast_url' => 'https://prayercast.com/prayer-topic/somalia/'],
            ['day' => '10-26', 'name' => 'South Africa', 'operation_world_url' => 'https://operationworld.org/prayer-calendar/10-26/', 'prayer_cast_url' => 'https://prayercast.com/prayer-topic/south-africa/'],
            ['day' => '10-27', 'name' => 'South Africa', 'operation_world_url' => 'https://operationworld.org/prayer-calendar/10-27/', 'prayer_cast_url' => 'https://prayercast.com/prayer-topic/south-africa/'],
            ['day' => '10-28', 'name' => 'South Sudan', 'operation_world_url' => 'https://operationworld.org/prayer-calendar/10-28/', 'prayer_cast_url' => 'https://prayercast.com/prayer-topic/south-sudan/'],
            ['day' => '10-29', 'name' => 'Spain', 'operation_world_url' => 'https://operationworld.org/prayer-calendar/10-29/', 'prayer_cast_url' => 'https://prayercast.com/prayer-topic/spain/'],
            ['day' => '10-30', 'name' => 'Sri Lanka', 'operation_world_url' => 'https://operationworld.org/prayer-calendar/10-30/', 'prayer_cast_url' => 'https://prayercast.com/prayer-topic/sri-lanka/'],
            ['day' => '10-31', 'name' => 'Sudan', 'operation_world_url' => 'https://operationworld.org/prayer-calendar/10-31/', 'prayer_cast_url' => 'https://prayercast.com/prayer-topic/sudan/'],
        ]);
    }
}
