<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Vault Encryption Key
    |--------------------------------------------------------------------------
    |
    | A dedicated encryption key for the staff credential vault, separate from
    | APP_KEY. This means a database dump alone is not sufficient to read stored
    | credentials — the VAULT_KEY is also required.
    |
    | Generate a value with:
    |   php -r "echo base64_encode(random_bytes(32)).PHP_EOL;"
    |
    */
    'key' => env('VAULT_KEY'),

    /*
    |--------------------------------------------------------------------------
    | Vault Session TTL
    |--------------------------------------------------------------------------
    |
    | How long (in minutes) a re-authentication session remains valid after
    | the user verifies their password. After this window expires, the user
    | must re-authenticate to view encrypted credential fields.
    |
    */
    'session_ttl_minutes' => (int) env('VAULT_SESSION_TTL_MINUTES', 30),
];
