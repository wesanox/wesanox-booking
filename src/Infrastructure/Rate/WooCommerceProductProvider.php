<?php

declare(strict_types=1);

namespace Wesanox\Booking\Infrastructure\Rate;

defined('ABSPATH') || exit;

use Wesanox\Booking\Application\Rate\WooCommerceProductProviderInterface;

/**
 * WooCommerce product data adapter.
 * Requires WooCommerce to be active.
 */
final class WooCommerceProductProvider implements WooCommerceProductProviderInterface
{
    /**
     * @return array<int, string>  [product_id => product_name]
     */
    public function getProducts(): array
    {
        if (!function_exists('wc_get_products')) {
            return [];
        }

        $products = wc_get_products([
            'status'     => 'publish',
            'type'       => ['simple', 'variable'],
            'limit'      => 200,
            'orderby'    => 'title',
            'order'      => 'ASC',
        ]);

        $result = [];
        foreach ($products as $product) {
            if ($product instanceof \WC_Product) {
                $result[$product->get_id()] = $product->get_name();
            }
        }

        return $result;
    }

    /**
     * @return array<int, string>  [variation_id => variation_label]
     */
    public function getVariations(int $product_id): array
    {
        if (!function_exists('wc_get_product')) {
            return [];
        }

        $product = wc_get_product($product_id);

        if (!$product instanceof \WC_Product_Variable) {
            return [];
        }

        $result = [];
        foreach ($product->get_available_variations() as $variation) {
            $id    = (int) $variation['variation_id'];
            $attrs = $variation['attributes'] ?? [];
            $label = implode(' / ', array_filter(array_values($attrs)));

            if ($label === '') {
                $label = '#' . $id;
            }

            $result[$id] = $label;
        }

        return $result;
    }

    public function productExists(int $product_id): bool
    {
        if (!function_exists('wc_get_product')) {
            return false;
        }

        $product = wc_get_product($product_id);

        return $product instanceof \WC_Product;
    }

    public function variationBelongsToProduct(int $variation_id, int $product_id): bool
    {
        if (!function_exists('wc_get_product')) {
            return false;
        }

        $variation = wc_get_product($variation_id);

        if (!$variation instanceof \WC_Product_Variation) {
            return false;
        }

        return $variation->get_parent_id() === $product_id;
    }
}
