<?php

return [

    'mailbox' => [
        'from_address' => env('CREDENTIALING_MAIL_FROM', 'credentialing@revantagehbs.com'),
        'from_name' => env('CREDENTIALING_MAIL_FROM_NAME', 'Revantage Credentialing'),
    ],

    'business_days' => [
        'exclude_weekends' => true,
        'holidays' => [],
    ],

];
