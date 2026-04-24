<?php

return [
    'name' => 'Email API Bridge',
    'env'  => getenv('APP_ENV') ?: 'production',
    
    // Configurazione Code
    'queue' => [
        'max_attempts' => 3,     // Tentativi prima di segnare come fallita
        'limit'        => 15,    // Quante mail inviare ad ogni ciclo del worker
    ],

    // Logiche di sicurezza
    'security' => [
        'api_key' => getenv('API_KEY'),
    ]
];