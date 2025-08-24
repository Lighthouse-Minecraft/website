<?php

namespace Database\Seeders;

use DB;
use Illuminate\Database\Seeder;

class PrayerCountriesFebruary extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('prayer_countries')->insert([
            ['day' => '2-1',  'name' => 'Europe',                                                       'operation_world_url' => 'https://operationworld.org/prayer-calendar/02-01/', 'prayer_cast_url' => 'https://prayercast.com/topic-category/nations/europe/'],
            ['day' => '2-2',  'name' => 'Europe',                                                       'operation_world_url' => 'https://operationworld.org/prayer-calendar/02-02/', 'prayer_cast_url' => 'https://prayercast.com/topic-category/nations/europe/'],
            ['day' => '2-3',  'name' => 'Europe',                                                       'operation_world_url' => 'https://operationworld.org/prayer-calendar/02-03/', 'prayer_cast_url' => 'https://prayercast.com/topic-category/nations/europe/'],
            ['day' => '2-4',  'name' => 'Europe',                                                       'operation_world_url' => 'https://operationworld.org/prayer-calendar/02-04/', 'prayer_cast_url' => 'https://prayercast.com/topic-category/nations/europe/'],
            ['day' => '2-5',  'name' => 'Europe',                                                       'operation_world_url' => 'https://operationworld.org/prayer-calendar/02-05/', 'prayer_cast_url' => 'https://prayercast.com/topic-category/nations/europe/'],

            ['day' => '2-6',  'name' => 'The Pacific',                                                  'operation_world_url' => 'https://operationworld.org/prayer-calendar/02-06/', 'prayer_cast_url' => 'https://prayercast.com/topic-category/nations/pacific/'],
            ['day' => '2-7',  'name' => 'The Pacific',                                                  'operation_world_url' => 'https://operationworld.org/prayer-calendar/02-07/', 'prayer_cast_url' => 'https://prayercast.com/topic-category/nations/pacific/'],
            ['day' => '2-8',  'name' => 'The Pacific',                                                  'operation_world_url' => 'https://operationworld.org/prayer-calendar/02-08/', 'prayer_cast_url' => 'https://prayercast.com/topic-category/nations/pacific/'],

            ['day' => '2-9',  'name' => 'Afghanistan',                                                  'operation_world_url' => 'https://operationworld.org/prayer-calendar/02-09/', 'prayer_cast_url' => 'https://prayercast.com/prayer-topic/afghanistan/'],
            ['day' => '2-10', 'name' => 'Afghanistan',                                                  'operation_world_url' => 'https://operationworld.org/prayer-calendar/02-10/', 'prayer_cast_url' => 'https://prayercast.com/prayer-topic/afghanistan/'],

            ['day' => '2-11', 'name' => 'Albania',                                                      'operation_world_url' => 'https://operationworld.org/prayer-calendar/02-11/', 'prayer_cast_url' => 'https://prayercast.com/prayer-topic/albania/'],
            ['day' => '2-12', 'name' => 'Algeria',                                                      'operation_world_url' => 'https://operationworld.org/prayer-calendar/02-12/', 'prayer_cast_url' => 'https://prayercast.com/prayer-topic/algeria/'],
            ['day' => '2-13', 'name' => 'Algeria',                                                      'operation_world_url' => 'https://operationworld.org/prayer-calendar/02-13/', 'prayer_cast_url' => 'https://prayercast.com/prayer-topic/algeria/'],

            // First with a Prayercast page on 2/14 is Andorra (American Samoa has no dedicated page)
            ['day' => '2-14', 'name' => 'American Samoa and Andorra',                                   'operation_world_url' => 'https://operationworld.org/prayer-calendar/02-14/', 'prayer_cast_url' => 'https://prayercast.com/prayer-topic/andorra/'],

            ['day' => '2-15', 'name' => 'Angola',                                                       'operation_world_url' => 'https://operationworld.org/prayer-calendar/02-15/', 'prayer_cast_url' => 'https://prayercast.com/prayer-topic/angola/'],
            ['day' => '2-16', 'name' => 'Angola',                                                       'operation_world_url' => 'https://operationworld.org/prayer-calendar/02-16/', 'prayer_cast_url' => 'https://prayercast.com/prayer-topic/angola/'],

            // 2/17 has two; Anguilla lacks a page, Antigua and Barbuda has one
            ['day' => '2-17', 'name' => 'Anguilla and Antigua and Barbuda',                             'operation_world_url' => 'https://operationworld.org/prayer-calendar/02-17/', 'prayer_cast_url' => 'https://prayercast.com/prayer-topic/antigua-and-barbuda/'],

            ['day' => '2-18', 'name' => 'Argentina',                                                    'operation_world_url' => 'https://operationworld.org/prayer-calendar/02-18/', 'prayer_cast_url' => 'https://prayercast.com/prayer-topic/argentina/'],
            ['day' => '2-19', 'name' => 'Argentina',                                                    'operation_world_url' => 'https://operationworld.org/prayer-calendar/02-19/', 'prayer_cast_url' => 'https://prayercast.com/prayer-topic/argentina/'],
            ['day' => '2-20', 'name' => 'Armenia',                                                      'operation_world_url' => 'https://operationworld.org/prayer-calendar/02-20/', 'prayer_cast_url' => 'https://prayercast.com/prayer-topic/armenia/'],
            ['day' => '2-21', 'name' => 'Armenia',                                                      'operation_world_url' => 'https://operationworld.org/prayer-calendar/02-21/', 'prayer_cast_url' => 'https://prayercast.com/prayer-topic/armenia/'],

            // 2/22 has three; Aruba is first and has a page
            ['day' => '2-22', 'name' => 'Aruba and Curaçao and Sint Maarten',                           'operation_world_url' => 'https://operationworld.org/prayer-calendar/02-22/', 'prayer_cast_url' => 'https://prayercast.com/prayer-topic/aruba/'],

            ['day' => '2-23', 'name' => 'Australia',                                                    'operation_world_url' => 'https://operationworld.org/prayer-calendar/02-23/', 'prayer_cast_url' => 'https://prayercast.com/prayer-topic/australia/'],

            // 2/24 has four; Australia is first and has a page
            ['day' => '2-24', 'name' => 'Australia and Christmas Island and Cocos (Keeling) Islands and Norfolk Island',
                'operation_world_url' => 'https://operationworld.org/prayer-calendar/02-24/', 'prayer_cast_url' => 'https://prayercast.com/prayer-topic/australia/'],

            ['day' => '2-25', 'name' => 'Austria',                                                      'operation_world_url' => 'https://operationworld.org/prayer-calendar/02-25/', 'prayer_cast_url' => 'https://prayercast.com/prayer-topic/austria/'],
            ['day' => '2-26', 'name' => 'Austria',                                                      'operation_world_url' => 'https://operationworld.org/prayer-calendar/02-26/', 'prayer_cast_url' => 'https://prayercast.com/prayer-topic/austria/'],
            ['day' => '2-27', 'name' => 'Azerbaijan',                                                   'operation_world_url' => 'https://operationworld.org/prayer-calendar/02-27/', 'prayer_cast_url' => 'https://prayercast.com/prayer-topic/azerbaijan/'],

            // 2/28 has two; Bahrain is first and has a page
            ['day' => '2-28', 'name' => 'Bahrain and The Bahamas',                                      'operation_world_url' => 'https://operationworld.org/prayer-calendar/02-28/', 'prayer_cast_url' => 'https://prayercast.com/prayer-topic/bahrain/'],

            // Leap day
            ['day' => '2-29', 'name' => 'Statelessness',                                                'operation_world_url' => 'https://operationworld.org/prayer-calendar/02-29/', 'prayer_cast_url' => null],
        ]);
    }
}
