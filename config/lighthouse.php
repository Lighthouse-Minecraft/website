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

    'stripe' => [
        'donation_pricing_table_id' => env('STRIPE_DONATION_PRICING_TABLE_ID', ''),
        'donation_pricing_table_key' => env('STRIPE_DONATION_PRICING_TABLE_KEY', ''),
        'customer_portal_url' => env('STRIPE_CUSTOMER_PORTAL_URL', ''),
        'one_time_donation_url' => env('STRIPE_ONE_TIME_DONATION_URL', ''),
    ],

    'max_minecraft_accounts' => (int) env('MAX_MINECRAFT_ACCOUNTS', 2),
    'minecraft_verification_grace_period_minutes' => (int) env('MINECRAFT_VERIFICATION_GRACE_PERIOD_MINUTES', 30),
    'minecraft_verification_rate_limit_per_hour' => (int) env('MINECRAFT_VERIFICATION_RATE_LIMIT_PER_HOUR', 10),
    'minecraft' => [
        'server_name' => env('MINECRAFT_SERVER_NAME', 'Lighthouse MC'),
        'server_host' => env('MINECRAFT_SERVER_HOST', 'play.lighthousemc.net'),
        'server_port_java' => (int) env('MINECRAFT_SERVER_PORT_JAVA', 25565),
        'server_port_bedrock' => (int) env('MINECRAFT_SERVER_PORT_BEDROCK', 19132),
    ],
];
