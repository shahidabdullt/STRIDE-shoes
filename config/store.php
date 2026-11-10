<?php

return [

    /*
    | Display currency (ISO 4217). Prices are stored as integer minor units, so changing this
    | only changes formatting, not amounts.
    */
    'currency' => env('STORE_CURRENCY', 'USD'),

    /*
    | Flat shipping rule. The seeded FAQs quote these figures, so change them together.
    */
    'shipping_flat_cents' => 600,
    'free_shipping_threshold_cents' => 10000,

    /* Most units of one size a customer can hold in a cart. */
    'max_quantity_per_item' => 5,

    /* A stock level at or below this is shown as "low" instead of "available". */
    'low_stock_threshold' => 3,

    /* How long a confirmation link stays valid, in hours. */
    'confirmation_link_hours' => 24,

];
