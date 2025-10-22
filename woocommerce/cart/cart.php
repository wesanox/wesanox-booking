<?php
/**
 * Cart Page (Delegation to class renderer)
 * @package WooCommerce\Templates
 * @version 7.9.0
 */
defined('ABSPATH') || exit;

$renderer = new \Wesanox\Booking\Woocommerce\View\Cart\Cart();
$renderer->wesanox_render_cart();