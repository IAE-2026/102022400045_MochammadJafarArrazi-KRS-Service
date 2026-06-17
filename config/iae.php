<?php

return [
    'service_name' => env('SERVICE_NAME', 'KRS-Service'),
    'api_version' => env('API_VERSION', 'v1'),
    'api_key' => env('IAE_API_KEY', '102022400045'),
    'minimum_ips' => (float) env('MINIMUM_IPS_KRS', 2.00),
    'external_validation_enabled' => filter_var(env('EXTERNAL_VALIDATION_ENABLED', true), FILTER_VALIDATE_BOOLEAN),

    'services' => [
        'mahasiswa' => [
            'url' => rtrim(env('MAHASISWA_SERVICE_URL', 'http://mahasiswa-service:8000'), '/'),
            'key' => env('MAHASISWA_SERVICE_KEY', env('IAE_API_KEY', '102022400045')),
        ],
        'kurikulum_nilai' => [
            'url' => rtrim(env('KURIKULUM_NILAI_SERVICE_URL', 'http://kurikulum-nilai-service:8000'), '/'),
            'key' => env('KURIKULUM_NILAI_SERVICE_KEY', env('IAE_API_KEY', '102022400045')),
        ],
    ],

    'sso' => [
        'url' => rtrim(env('SSO_URL', 'https://iae-sso.virtualfri.id'), '/'),
        'team_id' => env('IAE_TEAM_ID', 'TEAM-09'),
        'integration_enabled' => filter_var(env('SSO_INTEGRATION_ENABLED', true), FILTER_VALIDATE_BOOLEAN),
    ],
];
