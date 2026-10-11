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

    // Seconds to wait for the gateway before giving up. Answers that use
    // several live data tools can take a while, so keep this generous.
    'timeout' => (int) env('MYORI_AI_TIMEOUT', 60),

    // The read-only tools the assistant may use, as class names extending
    // Myori\AiClient\Tools\Tool. Each one checks the user's permissions itself.
    'tools' => [
        // App\MyoriAi\Tools\StickerSummary::class,
    ],

    // The guard whose user provider finds the user the gateway acts for.
    'guard' => 'web',

    // Set to false to register the package's routes yourself.
    'register_routes' => true,

    'route_prefix' => 'myori-ai',

    // Middleware for the chat endpoint your pages call. It must sign the user in.
    'chat_middleware' => ['web', 'auth', 'throttle:20,1'],

];
