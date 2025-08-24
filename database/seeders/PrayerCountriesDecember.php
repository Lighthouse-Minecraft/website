<?php

namespace Database\Seeders;

use DB;
use Illuminate\Database\Seeder;

class PrayerCountriesDecember extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('prayer_countries')->insert([
            ['day' => '12-1',  'name' => 'Ukraine', 'operation_world_url' => 'https://operationworld.org/prayer-calendar/12-01/', 'prayer_cast_url' => 'https://prayercast.com/prayer-topic/ukraine/'], // :contentReference[oaicite:31]{index=31}
            ['day' => '12-2',  'name' => 'Ukraine', 'operation_world_url' => 'https://operationworld.org/prayer-calendar/12-02/', 'prayer_cast_url' => 'https://prayercast.com/prayer-topic/ukraine/'], // :contentReference[oaicite:32]{index=32}
            ['day' => '12-3',  'name' => 'United Arab Emirates', 'operation_world_url' => 'https://operationworld.org/prayer-calendar/12-03/', 'prayer_cast_url' => 'https://prayercast.com/prayer-topic/united-arab-emirates/'], // :contentReference[oaicite:33]{index=33}
            ['day' => '12-4',  'name' => 'United Kingdom', 'operation_world_url' => 'https://operationworld.org/prayer-calendar/12-04/', 'prayer_cast_url' => 'https://prayercast.com/prayer-topic/united-kingdom/'], // :contentReference[oaicite:34]{index=34}
            ['day' => '12-5',  'name' => 'United Kingdom', 'operation_world_url' => 'https://operationworld.org/prayer-calendar/12-05/', 'prayer_cast_url' => 'https://prayercast.com/prayer-topic/united-kingdom/'], //
            ['day' => '12-6',  'name' => 'United Kingdom', 'operation_world_url' => 'https://operationworld.org/prayer-calendar/12-06/', 'prayer_cast_url' => 'https://prayercast.com/prayer-topic/united-kingdom/'], //
            ['day' => '12-7',  'name' => 'United States of America', 'operation_world_url' => 'https://operationworld.org/prayer-calendar/12-07/', 'prayer_cast_url' => 'https://prayercast.com/prayer-topic/usa/'], // :contentReference[oaicite:37]{index=37}
            ['day' => '12-8',  'name' => 'United States of America', 'operation_world_url' => 'https://operationworld.org/prayer-calendar/12-08/', 'prayer_cast_url' => 'https://prayercast.com/prayer-topic/usa/'], //
            ['day' => '12-9',  'name' => 'United States of America', 'operation_world_url' => 'https://operationworld.org/prayer-calendar/12-09/', 'prayer_cast_url' => 'https://prayercast.com/prayer-topic/usa/'], //
            ['day' => '12-10', 'name' => 'United States of America', 'operation_world_url' => 'https://operationworld.org/prayer-calendar/12-10/', 'prayer_cast_url' => 'https://prayercast.com/prayer-topic/usa/'], //
            ['day' => '12-11', 'name' => 'United States of America', 'operation_world_url' => 'https://operationworld.org/prayer-calendar/12-11/', 'prayer_cast_url' => 'https://prayercast.com/prayer-topic/usa/'], //
            ['day' => '12-12', 'name' => 'Uruguay', 'operation_world_url' => 'https://operationworld.org/prayer-calendar/12-12/', 'prayer_cast_url' => 'https://prayercast.com/prayer-topic/uruguay/'], // :contentReference[oaicite:42]{index=42}
            ['day' => '12-13', 'name' => 'Uzbekistan', 'operation_world_url' => 'https://operationworld.org/prayer-calendar/12-13/', 'prayer_cast_url' => 'https://prayercast.com/prayer-topic/uzbekistan/'], // :contentReference[oaicite:43]{index=43}
            ['day' => '12-14', 'name' => 'Uzbekistan', 'operation_world_url' => 'https://operationworld.org/prayer-calendar/12-14/', 'prayer_cast_url' => 'https://prayercast.com/prayer-topic/uzbekistan/'], //
            ['day' => '12-15', 'name' => 'Vanuatu', 'operation_world_url' => 'https://operationworld.org/prayer-calendar/12-15/', 'prayer_cast_url' => 'https://prayercast.com/prayer-topic/vanuatu/'], // :contentReference[oaicite:45]{index=45}
            ['day' => '12-16', 'name' => 'Venezuela', 'operation_world_url' => 'https://operationworld.org/prayer-calendar/12-16/', 'prayer_cast_url' => 'https://prayercast.com/prayer-topic/venezuela/'], // :contentReference[oaicite:46]{index=46}
            ['day' => '12-17', 'name' => 'Venezuela', 'operation_world_url' => 'https://operationworld.org/prayer-calendar/12-17/', 'prayer_cast_url' => 'https://prayercast.com/prayer-topic/venezuela/'], //
            ['day' => '12-18', 'name' => 'Vietnam', 'operation_world_url' => 'https://operationworld.org/prayer-calendar/12-18/', 'prayer_cast_url' => 'https://prayercast.com/prayer-topic/vietnam/'], // :contentReference[oaicite:48]{index=48}
            ['day' => '12-19', 'name' => 'Vietnam', 'operation_world_url' => 'https://operationworld.org/prayer-calendar/12-19/', 'prayer_cast_url' => 'https://prayercast.com/prayer-topic/vietnam/'], //
            ['day' => '12-20', 'name' => 'U.S. Virgin Islands and Wallis & Futuna', 'operation_world_url' => 'https://operationworld.org/prayer-calendar/12-20/', 'prayer_cast_url' => null], // no Prayercast page for either
            ['day' => '12-21', 'name' => 'Yemen', 'operation_world_url' => 'https://operationworld.org/prayer-calendar/12-21/', 'prayer_cast_url' => 'https://prayercast.com/prayer-topic/yemen/'], // :contentReference[oaicite:51]{index=51}
            ['day' => '12-22', 'name' => 'Yemen', 'operation_world_url' => 'https://operationworld.org/prayer-calendar/12-22/', 'prayer_cast_url' => 'https://prayercast.com/prayer-topic/yemen/'], //
            ['day' => '12-23', 'name' => 'Zambia', 'operation_world_url' => 'https://operationworld.org/prayer-calendar/12-23/', 'prayer_cast_url' => 'https://prayercast.com/prayer-topic/zambia/'], // :contentReference[oaicite:53]{index=53}
            ['day' => '12-24', 'name' => 'Zimbabwe', 'operation_world_url' => 'https://operationworld.org/prayer-calendar/12-24/', 'prayer_cast_url' => 'https://prayercast.com/prayer-topic/zimbabwe/'], // :contentReference[oaicite:54]{index=54}
            ['day' => '12-25', 'name' => 'Zimbabwe', 'operation_world_url' => 'https://operationworld.org/prayer-calendar/12-25/', 'prayer_cast_url' => 'https://prayercast.com/prayer-topic/zimbabwe/'], //
            ['day' => '12-26', 'name' => 'Refugees and Internally Displaced People', 'operation_world_url' => 'https://operationworld.org/prayer-calendar/12-26/', 'prayer_cast_url' => 'https://prayercast.com/prayer-topic/refugees/'], // topic day :contentReference[oaicite:56]{index=56}
            ['day' => '12-27', 'name' => 'Human Trafficking', 'operation_world_url' => 'https://operationworld.org/prayer-calendar/12-27/', 'prayer_cast_url' => null], // no dedicated Prayercast topic page found
            ['day' => '12-28', 'name' => 'Bible Translation and Distribution', 'operation_world_url' => 'https://operationworld.org/prayer-calendar/12-28/', 'prayer_cast_url' => null], // no dedicated Prayercast topic page found
            ['day' => '12-29', 'name' => 'Leaders of the World’s Nations', 'operation_world_url' => 'https://operationworld.org/prayer-calendar/12-29/', 'prayer_cast_url' => 'https://prayercast.com/topic-category/love-muslims/leaders-and-influencers/'], // closest topical match :contentReference[oaicite:59]{index=59}
            ['day' => '12-30', 'name' => 'Operation World', 'operation_world_url' => 'https://operationworld.org/prayer-calendar/12-30/', 'prayer_cast_url' => null], // no Prayercast page
            ['day' => '12-31', 'name' => 'The Lord’s Return', 'operation_world_url' => 'https://operationworld.org/prayer-calendar/12-31/', 'prayer_cast_url' => null], // no Prayercast page
        ]);
    }
}
