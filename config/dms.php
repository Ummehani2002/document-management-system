<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Company email domains
    |--------------------------------------------------------------------------
    |
    | Microsoft sign-in and document sharing accept these work addresses only.
    | Group company domains (Tanseeq, Proscape, Water in Motion, and others).
    |
    */

    'allowed_email_domains' => array_values(array_filter(array_map(
        static fn (string $domain): string => strtolower(trim($domain)),
        explode(',', (string) env(
            'DMS_ALLOWED_EMAIL_DOMAINS',
            'acacia-ae.com,acaciagardencenter.com,bioscape.ae,metalinellc.com,proscapefm.com,proscapeksa.com,proscapeuae.com,stonesandslates.com,tanseeqinvestment.com,tanseeqllc.com,tanseeqprojects.com,tanseeqrealty.com,timbertechllc.com,transmechllc.com,ventanallc.com,waterinmotionllc.com'
        ))
    ))),
];
