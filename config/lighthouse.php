<?php

return [
    'meeting_note_unlock_mins' => env('MEETING_NOTE_UNLOCK_MINS', 4),
    'prayer_cache_ttl' => env('PRAYER_CACHE_TTL', 60 * 60 * 24), // in seconds, default to 24 hours
    'prayer_list_url' => env('PRAYER_LIST_URL', 'https://app.echoprayer.com/user/feeds/4068'),
];
