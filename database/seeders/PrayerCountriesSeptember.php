<?php

namespace Database\Seeders;

use DB;
use Illuminate\Database\Seeder;

class PrayerCountriesSeptember extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // database/seeders/SeptemberPrayerSeeder.php (excerpt)
        DB::table('prayer_countries')->insert([
            ['day' => '9-1',  'name' => 'Federated States of Micronesia and Marshall Islands and Northern Mariana Islands and Palau', 'operation_world_url' => 'https://operationworld.org/prayer-calendar/09-01/', 'prayer_cast_url' => 'https://prayercast.com/prayer-topic/micronesia/'],
            ['day' => '9-2',  'name' => 'Moldova and Monaco', 'operation_world_url' => 'https://operationworld.org/prayer-calendar/09-02/', 'prayer_cast_url' => 'https://prayercast.com/prayer-topic/moldova/'],
            ['day' => '9-3',  'name' => 'Mongolia', 'operation_world_url' => 'https://operationworld.org/prayer-calendar/09-03/', 'prayer_cast_url' => 'https://prayercast.com/prayer-topic/mongolia/'],
            ['day' => '9-4',  'name' => 'Montenegro and Montserrat', 'operation_world_url' => 'https://operationworld.org/prayer-calendar/09-04/', 'prayer_cast_url' => 'https://prayercast.com/prayer-topic/montenegro/'],
            ['day' => '9-5',  'name' => 'Morocco', 'operation_world_url' => 'https://operationworld.org/prayer-calendar/09-05/', 'prayer_cast_url' => 'https://prayercast.com/prayer-topic/morocco/'],
            ['day' => '9-6',  'name' => 'Morocco and Western Sahara', 'operation_world_url' => 'https://operationworld.org/prayer-calendar/09-06/', 'prayer_cast_url' => 'https://prayercast.com/prayer-topic/morocco/'],
            ['day' => '9-7',  'name' => 'Mozambique', 'operation_world_url' => 'https://operationworld.org/prayer-calendar/09-07/', 'prayer_cast_url' => 'https://prayercast.com/prayer-topic/mozambique/'],
            ['day' => '9-8',  'name' => 'Mozambique', 'operation_world_url' => 'https://operationworld.org/prayer-calendar/09-08/', 'prayer_cast_url' => 'https://prayercast.com/prayer-topic/mozambique/'],
            ['day' => '9-9',  'name' => 'Myanmar', 'operation_world_url' => 'https://operationworld.org/prayer-calendar/09-09/', 'prayer_cast_url' => 'https://prayercast.com/prayer-topic/myanmar-burma/'],
            ['day' => '9-10', 'name' => 'Myanmar', 'operation_world_url' => 'https://operationworld.org/prayer-calendar/09-10/', 'prayer_cast_url' => 'https://prayercast.com/prayer-topic/myanmar-burma/'],
            ['day' => '9-11', 'name' => 'Namibia and Nauru', 'operation_world_url' => 'https://operationworld.org/prayer-calendar/09-11/', 'prayer_cast_url' => 'https://prayercast.com/prayer-topic/namibia/'],
            ['day' => '9-12', 'name' => 'Nepal', 'operation_world_url' => 'https://operationworld.org/prayer-calendar/09-12/', 'prayer_cast_url' => 'https://prayercast.com/prayer-topic/nepal/'],
            ['day' => '9-13', 'name' => 'Nepal', 'operation_world_url' => 'https://operationworld.org/prayer-calendar/09-13/', 'prayer_cast_url' => 'https://prayercast.com/prayer-topic/nepal/'],
            ['day' => '9-14', 'name' => 'Netherlands', 'operation_world_url' => 'https://operationworld.org/prayer-calendar/09-14/', 'prayer_cast_url' => 'https://prayercast.com/prayer-topic/netherlands/'],
            ['day' => '9-15', 'name' => 'New Caledonia', 'operation_world_url' => 'https://operationworld.org/prayer-calendar/09-15/', 'prayer_cast_url' => 'https://prayercast.com/prayer-topic/new-caledonia/'],
            ['day' => '9-16', 'name' => 'New Zealand', 'operation_world_url' => 'https://operationworld.org/prayer-calendar/09-16/', 'prayer_cast_url' => 'https://prayercast.com/prayer-topic/new-zealand/'],
            ['day' => '9-17', 'name' => 'Nicaragua', 'operation_world_url' => 'https://operationworld.org/prayer-calendar/09-17/', 'prayer_cast_url' => 'https://prayercast.com/prayer-topic/nicaragua/'],
            ['day' => '9-18', 'name' => 'Niger', 'operation_world_url' => 'https://operationworld.org/prayer-calendar/09-18/', 'prayer_cast_url' => 'https://prayercast.com/prayer-topic/niger/'],
            ['day' => '9-19', 'name' => 'Niger', 'operation_world_url' => 'https://operationworld.org/prayer-calendar/09-19/', 'prayer_cast_url' => 'https://prayercast.com/prayer-topic/niger/'],
            ['day' => '9-20', 'name' => 'Nigeria', 'operation_world_url' => 'https://operationworld.org/prayer-calendar/09-20/', 'prayer_cast_url' => 'https://prayercast.com/prayer-topic/nigeria/'],
            ['day' => '9-21', 'name' => 'Nigeria', 'operation_world_url' => 'https://operationworld.org/prayer-calendar/09-21/', 'prayer_cast_url' => 'https://prayercast.com/prayer-topic/nigeria/'],
            ['day' => '9-22', 'name' => 'Nigeria', 'operation_world_url' => 'https://operationworld.org/prayer-calendar/09-22/', 'prayer_cast_url' => 'https://prayercast.com/prayer-topic/nigeria/'],
            ['day' => '9-23', 'name' => 'Norway', 'operation_world_url' => 'https://operationworld.org/prayer-calendar/09-23/', 'prayer_cast_url' => 'https://prayercast.com/prayer-topic/norway/'],
            ['day' => '9-24', 'name' => 'Oman', 'operation_world_url' => 'https://operationworld.org/prayer-calendar/09-24/', 'prayer_cast_url' => 'https://prayercast.com/prayer-topic/oman/'],
            ['day' => '9-25', 'name' => 'Pakistan', 'operation_world_url' => 'https://operationworld.org/prayer-calendar/09-25/', 'prayer_cast_url' => 'https://prayercast.com/prayer-topic/pakistan/'],
            ['day' => '9-26', 'name' => 'Pakistan', 'operation_world_url' => 'https://operationworld.org/prayer-calendar/09-26/', 'prayer_cast_url' => 'https://prayercast.com/prayer-topic/pakistan/'],
            ['day' => '9-27', 'name' => 'Pakistan', 'operation_world_url' => 'https://operationworld.org/prayer-calendar/09-27/', 'prayer_cast_url' => 'https://prayercast.com/prayer-topic/pakistan/'],
            ['day' => '9-28', 'name' => 'Pakistan', 'operation_world_url' => 'https://operationworld.org/prayer-calendar/09-28/', 'prayer_cast_url' => 'https://prayercast.com/prayer-topic/pakistan/'],
            ['day' => '9-29', 'name' => 'Palestine and Panama', 'operation_world_url' => 'https://operationworld.org/prayer-calendar/09-29/', 'prayer_cast_url' => 'https://prayercast.com/prayer-topic/gaza-strip-and-west-bank/'],
            ['day' => '9-30', 'name' => 'Papua New Guinea', 'operation_world_url' => 'https://operationworld.org/prayer-calendar/09-30/', 'prayer_cast_url' => 'https://prayercast.com/prayer-topic/papua-new-guinea/'],
        ]);
    }
}
