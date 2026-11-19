<?php

return [
    /*
    |--------------------------------------------------------------------------
    | FoodPick Business Configuration (F&B / Restaurants)
    |--------------------------------------------------------------------------
    |
    | Enables the FoodPick culinary multi-resto ecosystem in Bizmate.
    | When disabled (false), the application runs as standard retail e-commerce.
    |
    */

    'is_foodpick' => filter_var(env('IS_FOODPICK', false), FILTER_VALIDATE_BOOLEAN),

    'allow_store_courier' => filter_var(env('FOODPICK_ALLOW_STORE_COURIER', true), FILTER_VALIDATE_BOOLEAN),

    'allow_self_pickup' => filter_var(env('FOODPICK_ALLOW_SELF_PICKUP', true), FILTER_VALIDATE_BOOLEAN),

    'hide_expedition' => filter_var(env('FOODPICK_HIDE_EXPEDITION', true), FILTER_VALIDATE_BOOLEAN),

    'default_prep_minutes' => (int) env('FOODPICK_DEFAULT_PREP_MINUTES', 20),

    'slot_interval_minutes' => (int) env('FOODPICK_SLOT_INTERVAL_MINUTES', 15),
];
