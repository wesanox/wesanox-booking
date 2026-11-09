<?php

declare(strict_types=1);

// Prevent direct WordPress bootstrap for unit tests.
define('ABSPATH', '/tmp/');

require_once dirname(__DIR__) . '/vendor/autoload.php';

// Minimal WordPress function stubs for unit tests.
if (!function_exists('wp_timezone')) {
    function wp_timezone(): DateTimeZone
    {
        return new DateTimeZone('Europe/Berlin');
    }
}

if (!function_exists('wc_get_order')) {
    function wc_get_order(int $order_id): mixed
    {
        return $GLOBALS['wesanox_test_wc_orders'][$order_id] ?? false;
    }
}

if (!class_exists('Wesanox_Test_WC_Order')) {
    final class Wesanox_Test_WC_Order
    {
        /** @param array<string, mixed> $meta */
        public function __construct(
            public array $meta = [],
            public int $saveCount = 0,
        ) {}

        public function get_meta(string $key, bool $single = true): mixed
        {
            return $this->meta[$key] ?? '';
        }

        public function update_meta_data(string $key, mixed $value): void
        {
            $this->meta[$key] = $value;
        }

        public function save(): void
        {
            $this->saveCount++;
        }
    }
}

if (!function_exists('defined')) {
    function defined(string $constant): bool
    {
        return \defined($constant);
    }
}
