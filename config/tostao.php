<?php

return [
    /*
    | Currency shown across the app (ISO 4217). Amounts are always stored as
    | integer cents, so there are no floating point surprises.
    */
    'currency' => env('TOSTAO_CURRENCY', 'EUR'),

    /*
    | Locales offered in the language switcher. Add a lang/<code>.json file
    | to add a new one.
    */
    'locales' => ['pt' => 'Português', 'en' => 'English'],

    /*
    | Default palette used for the members and the starter categories.
    */
    'member_colors' => ['#2f6fb5', '#c2548a', '#2a9078', '#c98a1b', '#7a5cc2', '#d4553f'],
];
