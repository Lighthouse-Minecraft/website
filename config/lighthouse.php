<?php

return [
    'meeting_note_unlock_mins' => env('MEETING_NOTE_UNLOCK_MINS', 4),
    'prayer_cache_ttl' => env('PRAYER_CACHE_TTL', 60 * 60 * 24), // in seconds, default to 24 hours
    'prayer_list_url' => env('PRAYER_LIST_URL', 'https://app.echoprayer.com/user/feeds/4068'),

    'donation_goal' => env('DONATION_GOAL', 60),
    'donation_current_month_amount' => env('DONATION_CURRENT_MONTH_AMOUNT', 0),
    'donation_current_month_name' => env('DONATION_CURRENT_MONTH_NAME', ''),
    'donation_last_month_amount' => env('DONATION_LAST_MONTH_AMOUNT', 0),
    'donation_last_month_name' => env('DONATION_LAST_MONTH_NAME', ''),
];
