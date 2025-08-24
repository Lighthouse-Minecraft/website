<?php

namespace Database\Seeders;

use DB;
use Illuminate\Database\Seeder;

class PrayerCountriesMarch extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('prayer_countries')->insert([
            ['day' => '3-1',  'name' => 'Bangladesh',                          'operation_world_url' => 'https://operationworld.org/prayer-calendar/03-01/', 'prayer_cast_url' => 'https://prayercast.com/prayer-topic/bangladesh/'],
            ['day' => '3-2',  'name' => 'Bangladesh',                          'operation_world_url' => 'https://operationworld.org/prayer-calendar/03-02/', 'prayer_cast_url' => 'https://prayercast.com/prayer-topic/bangladesh/'],
            ['day' => '3-3',  'name' => 'Bangladesh',                          'operation_world_url' => 'https://operationworld.org/prayer-calendar/03-03/', 'prayer_cast_url' => 'https://prayercast.com/prayer-topic/bangladesh/'],
            ['day' => '3-4',  'name' => 'Barbados',                            'operation_world_url' => 'https://operationworld.org/prayer-calendar/03-04/', 'prayer_cast_url' => 'https://prayercast.com/prayer-topic/barbados/'],
            ['day' => '3-5',  'name' => 'Belarus',                             'operation_world_url' => 'https://operationworld.org/prayer-calendar/03-05/', 'prayer_cast_url' => 'https://prayercast.com/prayer-topic/belarus/'],
            ['day' => '3-6',  'name' => 'Belgium',                             'operation_world_url' => 'https://operationworld.org/prayer-calendar/03-06/', 'prayer_cast_url' => 'https://prayercast.com/prayer-topic/belgium/'],
            ['day' => '3-7',  'name' => 'Belgium',                             'operation_world_url' => 'https://operationworld.org/prayer-calendar/03-07/', 'prayer_cast_url' => 'https://prayercast.com/prayer-topic/belgium/'],
            ['day' => '3-8',  'name' => 'Belize and Bermuda',                  'operation_world_url' => 'https://operationworld.org/prayer-calendar/03-08/', 'prayer_cast_url' => 'https://prayercast.com/prayer-topic/belize/'],
            ['day' => '3-9',  'name' => 'Benin',                               'operation_world_url' => 'https://operationworld.org/prayer-calendar/03-09/', 'prayer_cast_url' => 'https://prayercast.com/prayer-topic/benin/'],
            ['day' => '3-10', 'name' => 'Bhutan',                              'operation_world_url' => 'https://operationworld.org/prayer-calendar/03-10/', 'prayer_cast_url' => 'https://prayercast.com/prayer-topic/bhutan/'],
            ['day' => '3-11', 'name' => 'Bolivia',                             'operation_world_url' => 'https://operationworld.org/prayer-calendar/03-11/', 'prayer_cast_url' => 'https://prayercast.com/prayer-topic/bolivia/'],
            ['day' => '3-12', 'name' => 'Bolivia',                             'operation_world_url' => 'https://operationworld.org/prayer-calendar/03-12/', 'prayer_cast_url' => 'https://prayercast.com/prayer-topic/bolivia/'],
            ['day' => '3-13', 'name' => 'Bosnia and Herzegovina',              'operation_world_url' => 'https://operationworld.org/prayer-calendar/03-13/', 'prayer_cast_url' => 'https://prayercast.com/prayer-topic/bosnia-and-herzegovina/'],
            ['day' => '3-14', 'name' => 'Botswana',                            'operation_world_url' => 'https://operationworld.org/prayer-calendar/03-14/', 'prayer_cast_url' => 'https://prayercast.com/prayer-topic/botswana/'],
            ['day' => '3-15', 'name' => 'Brazil',                              'operation_world_url' => 'https://operationworld.org/prayer-calendar/03-15/', 'prayer_cast_url' => 'https://prayercast.com/prayer-topic/brazil/'],
            ['day' => '3-16', 'name' => 'Brazil',                              'operation_world_url' => 'https://operationworld.org/prayer-calendar/03-16/', 'prayer_cast_url' => 'https://prayercast.com/prayer-topic/brazil/'],
            ['day' => '3-17', 'name' => 'Brazil',                              'operation_world_url' => 'https://operationworld.org/prayer-calendar/03-17/', 'prayer_cast_url' => 'https://prayercast.com/prayer-topic/brazil/'],
            ['day' => '3-18', 'name' => 'Brazil',                              'operation_world_url' => 'https://operationworld.org/prayer-calendar/03-18/', 'prayer_cast_url' => 'https://prayercast.com/prayer-topic/brazil/'],
            ['day' => '3-19', 'name' => 'British Virgin Islands and Brunei',   'operation_world_url' => 'https://operationworld.org/prayer-calendar/03-19/', 'prayer_cast_url' => 'https://prayercast.com/prayer-topic/brunei/'],
            ['day' => '3-20', 'name' => 'Bulgaria',                            'operation_world_url' => 'https://operationworld.org/prayer-calendar/03-20/', 'prayer_cast_url' => 'https://prayercast.com/prayer-topic/bulgaria/'],
            ['day' => '3-21', 'name' => 'Burkina Faso',                        'operation_world_url' => 'https://operationworld.org/prayer-calendar/03-21/', 'prayer_cast_url' => 'https://prayercast.com/prayer-topic/burkina-faso/'],
            ['day' => '3-22', 'name' => 'Burkina Faso',                        'operation_world_url' => 'https://operationworld.org/prayer-calendar/03-22/', 'prayer_cast_url' => 'https://prayercast.com/prayer-topic/burkina-faso/'],
            ['day' => '3-23', 'name' => 'Burundi',                             'operation_world_url' => 'https://operationworld.org/prayer-calendar/03-23/', 'prayer_cast_url' => 'https://prayercast.com/prayer-topic/burundi/'],
            ['day' => '3-24', 'name' => 'Cambodia',                            'operation_world_url' => 'https://operationworld.org/prayer-calendar/03-24/', 'prayer_cast_url' => 'https://prayercast.com/prayer-topic/cambodia/'],
            ['day' => '3-25', 'name' => 'Cambodia',                            'operation_world_url' => 'https://operationworld.org/prayer-calendar/03-25/', 'prayer_cast_url' => 'https://prayercast.com/prayer-topic/cambodia/'],
            ['day' => '3-26', 'name' => 'Cameroon',                            'operation_world_url' => 'https://operationworld.org/prayer-calendar/03-26/', 'prayer_cast_url' => 'https://prayercast.com/prayer-topic/cameroon/'],
            ['day' => '3-27', 'name' => 'Cameroon',                            'operation_world_url' => 'https://operationworld.org/prayer-calendar/03-27/', 'prayer_cast_url' => 'https://prayercast.com/prayer-topic/cameroon/'],
            ['day' => '3-28', 'name' => 'Canada',                              'operation_world_url' => 'https://operationworld.org/prayer-calendar/03-28/', 'prayer_cast_url' => 'https://prayercast.com/prayer-topic/canada/'],
            ['day' => '3-29', 'name' => 'Canada',                              'operation_world_url' => 'https://operationworld.org/prayer-calendar/03-29/', 'prayer_cast_url' => 'https://prayercast.com/prayer-topic/canada/'],
            ['day' => '3-30', 'name' => 'Cabo Verde and Cayman Islands',       'operation_world_url' => 'https://operationworld.org/prayer-calendar/03-30/', 'prayer_cast_url' => 'https://prayercast.com/prayer-topic/cape-verde/'],
            ['day' => '3-31', 'name' => 'Central African Republic',            'operation_world_url' => 'https://operationworld.org/prayer-calendar/03-31/', 'prayer_cast_url' => 'https://prayercast.com/prayer-topic/central-african-republic/'],
        ]);
    }
}
