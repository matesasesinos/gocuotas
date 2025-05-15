<?php

/**
 * Plugin Name: Go Cuotas
 * Version: 1.3.1
 * Author: Juan Iriart
 * Text Domain: gocuotas
 * Description: Plugin para integración de Go Cuotas en WooCommerce
 */

if (!defined('ABSPATH')) {
    exit;
}

define('GC_VERSION', '1.3.1');

require_once plugin_dir_path(__FILE__) . 'includes/helpers/class-helper.php';

class WC_GoCuotas
{
    public static function init()
    {
        add_action('plugins_loaded', array(__CLASS__, 'includes'), 0);
        add_filter('woocommerce_payment_gateways', array(__CLASS__, 'add_gateway'));
        add_action('woocommerce_blocks_loaded', array(__CLASS__, 'woocommerce_gateway_dummy_woocommerce_block_support'));
        add_action('wp_enqueue_scripts', [__CLASS__, 'styles']);
    }

    public static function styles()
    {
        wp_enqueue_style('gocuotas-styles', plugin_dir_url(__FILE__) . 'assets/css/gocuotas.css', array(), GC_VERSION, 'all');
    }

    public static function includes()
    {
        if (!class_exists('WC_Gateway_GoCuotas')) {
            require_once 'includes/class-gocuotas.php';
        }
    }

    public static function add_gateway($gateways)
    {
        $gateways[] = 'WC_Gateway_GoCuotas';
        return $gateways;
    }

    public static function plugin_url()
    {
        return untrailingslashit(plugins_url('/', __FILE__));
    }

    public static function plugin_abspath()
    {
        return trailingslashit(plugin_dir_path(__FILE__));
    }

    public static function woocommerce_gateway_dummy_woocommerce_block_support()
    {
        if (class_exists('Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType')) {
            require_once 'includes/blocks/class-gocuotas-block.php';
            add_action(
                'woocommerce_blocks_payment_method_type_registration',
                function (Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry $payment_method_registry) {
                    $payment_method_registry->register(new WC_GoCuotas_Block_Support());
                }
            );
        }
    }
}

WC_GoCuotas::init();