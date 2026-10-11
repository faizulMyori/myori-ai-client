<?php

return [

    // Base URL of the MyORI AI gateway, for example https://ai.myori.my
    'gateway_url' => env('MYORI_AI_GATEWAY_URL'),

    // This system's gateway token, printed once by `php artisan system:create` on the gateway.
    'token' => env('MYORI_AI_TOKEN'),

    // Secret the gateway signs its calls into this system with, printed alongside the token.
    'callback_secret' => env('MYORI_AI_CALLBACK_SECRET'),

    // How old (in seconds) a signed call from the gateway may be before it is refused.
    'signature_tolerance' => (int) env('MYORI_AI_SIGNATURE_TOLERANCE', 300),

    // Seconds to wait for the gateway before giving up.
    'timeout' => (int) env('MYORI_AI_TIMEOUT', 30),

];
