<?php

declare(strict_types=1);

namespace Wesanox\Booking\Application\Rate;

defined('ABSPATH') || exit;

/**
 * Port for WooCommerce product data access.
 * Implemented in Infrastructure to keep Domain/Application WP-free.
 */
interface WooCommerceProductProviderInterface
{
    /**
     * All bookable products (simple + variable).
     *
     * @return array<int, string>  [product_id => product_name]
     */
    public function getProducts(): array;

    /**
     * Variations for a variable product.
     *
     * @return array<int, string>  [variation_id => variation_label]
     */
    public function getVariations(int $product_id): array;

    public function productExists(int $product_id): bool;

    public function variationBelongsToProduct(int $variation_id, int $product_id): bool;
}
