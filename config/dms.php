<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Company email domains
    |--------------------------------------------------------------------------
    |
    | Microsoft sign-in and document sharing accept these work addresses only.
    | Include both Tanseeq LLC and Tanseeq Projects (and Investment if used).
    |
    */

    'allowed_email_domains' => array_values(array_filter(array_map(
        static fn (string $domain): string => strtolower(trim($domain)),
        explode(',', (string) env(
            'DMS_ALLOWED_EMAIL_DOMAINS',
            'tanseeqllc.com,tanseeqprojects.com,tanseeqinvestment.com'
        ))
    ))),

];
