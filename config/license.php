<?php

return [
    // Enforcement is off by default so a wrong date can never lock users out.
    'enforce' => env('LICENSE_ENFORCE', false),
    'key' => env('LICENSE_KEY'),
    'expires_at' => env('LICENSE_EXPIRES_AT', '2099-12-31'),
    'warn_days' => 30,
];
