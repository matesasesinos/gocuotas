<?php

/**
 * Plugin Name: Go Cuotas
 * Version: 1.1.7
 * Author: Juan Iriart
 * Text Domain: gocuotas
 * Description: Plugin para integración de Go Cuotas en WooCommerce
 */

if (!defined('ABSPATH')) {
    exit;
}

define('GC_VERSION', '1.1.7');

require_once plugin_dir_path(__FILE__) . 'class-helper.php';

add_action('plugins_loaded', 'init_gocuotas_class');

function init_gocuotas_class()
{
    require_once plugin_dir_path(__FILE__) . 'class-gocuotas.php';
}

add_action('wp_enqueue_scripts', function () {
    wp_enqueue_style('gocuotas', plugin_dir_url(__FILE__) . 'cgocuotas.css', array(), GC_VERSION, 'all');
});

add_filter('woocommerce_payment_gateways', 'gocuotas_add_class');
function gocuotas_add_class($gateways)
{
    $gateways[] = 'WC_Gateway_GoCuotas';
    return $gateways;
}

function go_deactivate()
{
    delete_option('woocommerce_gocuotas_settings');
}

register_deactivation_hook(__FILE__, 'go_deactivate');