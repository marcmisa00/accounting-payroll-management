<?php

return [
    // Copy the values of your old BANK_ENCRYPTION_METHOD / BANK_ENCRYPTION_KEY constants into .env
    'bank_encryption_method' => env('BANK_ENCRYPTION_METHOD'),
    'bank_encryption_key'    => env('BANK_ENCRYPTION_KEY'),

    // Signatories selectable when exporting a payslip PDF (key => printed name/title)
    'payslip_signatories' => [
        'mulit'     => ['name' => 'McANGELO MULIT',           'title' => 'ACCOUNTING SPECIALIST'],
        'salvador'  => ['name' => 'JACQUELINE SALVADOR-IGAY', 'title' => 'ACCOUNTING SPECIALIST'],
        'astrologo' => ['name' => 'MARY GRACE ASTROLOGO',     'title' => 'CHIEF EXECUTIVE OFFICER'],
    ],

    // Pre-selected signatory per company (legacy defaults)
    'payslip_default_signatory' => [
        'NESI1'  => 'salvador',
        'NESI2'  => 'mulit',
        'NEWIND' => 'mulit',
    ],
];