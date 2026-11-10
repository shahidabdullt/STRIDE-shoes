<?php

/**
 * Stub for the check_size_availability tool, shared by S3 (live/tool.php) and S4.
 */
function spike_check_size(array $args): array
{
    // String keys: PHP would truncate a float key like 9.5 to 9.
    $stock = ['trailblazer' => ['8' => 4, '9' => 0, '9.5' => 2, '10' => 7], 'cloudrunner' => ['9' => 3, '10' => 1]];
    $slug = strtolower((string) ($args['slug'] ?? ''));
    $size = (float) ($args['size'] ?? 0);

    if (! isset($stock[$slug])) {
        return ['error' => 'product_not_found', 'known_products' => array_keys($stock)];
    }

    $qty = $stock[$slug][(string) $size] ?? 0;

    return [
        'product' => $slug,
        'size' => $size,
        'in_stock' => $qty > 0,
        'quantity_band' => $qty === 0 ? 'none' : ($qty <= 2 ? 'low' : 'available'),
        'alternatives' => array_values(array_map('floatval', array_keys(array_filter($stock[$slug])))),
    ];
}
