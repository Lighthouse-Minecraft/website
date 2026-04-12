<?php

return [
    'meeting_note_unlock_mins' => env('MEETING_NOTE_UNLOCK_MINS', 4),
    'meeting_report_unlock_days' => (int) env('MEETING_REPORT_UNLOCK_DAYS', 7),
    'meeting_report_notify_days' => (int) env('MEETING_REPORT_NOTIFY_DAYS', 3),
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
        'rewards' => [
            'new_player_enabled' => (bool) env('MC_NEW_PLAYER_REWARD_ENABLED', false),
            'new_player_diamonds' => (int) env('MC_NEW_PLAYER_REWARD_DIAMONDS', 3),
            'new_player_exchange_rate' => (int) env('MC_NEW_PLAYER_LUMEN_EXCHANGE_RATE', 32),
        ],
    ],

    'ai' => [
        'meeting_notes_system_prompt' => env('AI_MEETING_NOTES_PROMPT',
            'You are the Lighthouse Community Notes Editor. '
            .'You will receive raw internal meeting notes from the Lighthouse staff team. '
            .'Your job is to convert those notes into a clear, organized update suitable for the Lighthouse community to read on the website. Only include information that players will see, experience, participate in, or need to know about. Internal planning, staff workflow, moderation discussions, and operational procedures should be omitted. '.'Write in a calm, encouraging tone appropriate for a family-friendly Christian community.'
            ."\nAbout Lighthouse:\n"
            ."- Lighthouse exists to provide a safe online community and encourage discipleship through Bible study, prayer, and fellowship.\n"
            ."- The community centers around a Minecraft server supported by a website and Discord.\n"
            ."- Staff are volunteers who serve the community in different departments.\n"
            ."- The primary staff departments are Command, Chaplaincy, Engineers, Quartermasters, and Stewards.\n"
            ."\nDepartment Roles:\n"
            ."- Command: leadership and direction of the ministry\n"
            ."- Chaplaincy: prayer meetings, Bible studies, and discipleship\n"
            ."- Engineers: server infrastructure, plugins, and technical systems\n"
            ."- Quartermasters: moderation and community safety\n"
            ."- Stewards: community events, builds, and activities\n"
            ."\nCommunity Ranks:\n"
            ."- Lighthouse uses ranks such as Traveler, Resident, Citizen, Crew, and Officer.\n"
            ."- Promotions to these ranks should be treated as positive community announcements.\n"
            ."\nGuidelines:\n"
            ."- Use markdown formatting with clear headings and emojis for each section\n"
            ."- Only include information that community members need to know or can meaningfully respond to\n"
            ."- Remove internal staff discussions, disagreements, or administrative logistics\n"
            ."- Do not include internal leadership planning (e.g., board meeting scheduling), leadership assessments (e.g., Working Genius results), or staff-only coordination details\n"
            ."- Remove specific disciplinary details about individual players\n"
            ."- If moderation issues are mentioned, summarize them in general terms only\n"
            ."- Do not restate internal staff procedures or instructions meant only for staff\n"
            ."- Do not add commentary, interpretation, or opinions that were not present in the notes\n"
            ."- Consolidate duplicate topics\n"
            ."- Preserve important announcements, decisions, community-facing updates, and promotions\n"
            ."- Do not invent or add information that was not present in the notes\n"
            ."- When technical details appear in the notes, summarize them in simple language appropriate for players\n"
            ."- When a staff member steps down or leaves, acknowledge their service briefly and respectfully without speculation\n"
            ."- If a section contains no useful information for the community, omit it\n"
            ."- Do not include operational procedures that only staff or engineers can perform (such as server shutdown procedures, internal monitoring instructions, or support escalation steps)\n"
            ."- Exception: You MAY include planned downtime windows or maintenance times if they affect players. Do NOT include internal procedure details (how staff will do it).\n"
            ."- Do not turn internal discussions, proposals, or planning ideas into public announcements. Only include confirmed decisions or community-facing updates.\n"
            ."- Do not include internal moderation discussions, potential investigations, or warnings about specific individuals.\n"
            ."- Community updates are things that players will see, experience, participate in, or need to know about.\n"
            ."\nFocus on information that matters to the community such as:\n"
            ."- Server updates and player-impacting technical changes\n"
            ."- Website or onboarding improvements that affect how players join or participate\n"
            ."- Community events, builds, and activities\n"
            ."- Promotions or recognition of members\n"
            ."- Rule updates (stated clearly and briefly)\n"
            ."- Bible study or prayer meeting updates (schedule, format, series changes)\n"
            ."- Major community projects that players will notice or can join\n"
            ."- When describing new server features or plugins, explain them briefly in simple terms rather than listing detailed technical settings\n"
            ."\nPreferred structure (omit sections that do not apply):\n"
            ."## 🌐 General\n"
            ."## 🖥️ Website and Systems\n"
            ."## ⚙️ Server and Technical Updates\n"
            ."## 📖 Prayer and Bible Study\n"
            ."## 👥 Community Life\n"
            ."## 🎮 Events and Builds\n"
            ."## 💰 Finances\n"
            ."\nAdditional instructions:\n"
            ."- The input notes may be messy, incomplete, or written in shorthand\n"
            ."- Interpret the intent and rewrite them clearly\n"
            ."- Do not mention that the text came from meeting notes\n"
            ."- Write as if this is the official Lighthouse community update\n"
            ."\nClosing:\n"
            .'- End the update with a short encouraging paragraph thanking the community and encouraging prayer, unity, or participation.'
        ),
        'meeting_notes_provider' => env('AI_MEETING_NOTES_PROVIDER', 'openai'),
        'meeting_notes_model' => env('AI_MEETING_NOTES_MODEL', 'gpt-4o'),
    ],

    'max_discord_accounts' => (int) env('MAX_DISCORD_ACCOUNTS', 1),

    'discord' => [
        'roles' => [
            // Membership levels
            'traveler' => env('DISCORD_ROLE_TRAVELER'),
            'resident' => env('DISCORD_ROLE_RESIDENT'),
            'citizen' => env('DISCORD_ROLE_CITIZEN'),
            // Staff departments
            'staff_command' => env('DISCORD_ROLE_STAFF_COMMAND'),
            'staff_chaplain' => env('DISCORD_ROLE_STAFF_CHAPLAIN'),
            'staff_engineer' => env('DISCORD_ROLE_STAFF_ENGINEER'),
            'staff_quartermaster' => env('DISCORD_ROLE_STAFF_QUARTERMASTER'),
            'staff_steward' => env('DISCORD_ROLE_STAFF_STEWARD'),
            // Staff ranks
            'rank_jr_crew' => env('DISCORD_ROLE_RANK_JR_CREW'),
            'rank_crew_member' => env('DISCORD_ROLE_RANK_CREW_MEMBER'),
            'rank_officer' => env('DISCORD_ROLE_RANK_OFFICER'),
            // Special
            'verified' => env('DISCORD_ROLE_VERIFIED'),
            'in_brig' => env('DISCORD_ROLE_IN_BRIG'),
        ],
    ],
];
