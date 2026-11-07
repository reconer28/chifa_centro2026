<?php

return [
    'duracion_token_horas' => (int) env('CHIFA_DURACION_TOKEN_HORAS', 16),
    'edicion_minutos' => 5,
    'advertencia_minutos' => 12,
    'urgencia_minutos' => 15,
    'websocket_host' => env('REVERB_PUBLIC_HOST'),
    'websocket_port' => (int) env('REVERB_PUBLIC_PORT', env('REVERB_PORT', 8080)),
    'websocket_scheme' => env('REVERB_PUBLIC_SCHEME', env('REVERB_SCHEME', 'http')),
];
