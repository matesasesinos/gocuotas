<?php 

class GoCuotasStockManager
{

    public function __construct()
    {
        add_action('woocommerce_order_status_pending_to_cancelled', array($this, 'restore_stock_item'), 10, 1);
        add_action('woocommerce_order_status_pending_to_failed', array($this, 'restore_stock_item'), 10, 1);

        add_action('woocommerce_order_status_processing_to_refunded', array($this, 'restore_stock_item'), 10, 1);
        add_action('woocommerce_order_status_on-hold_to_refunded', array($this, 'restore_stock_item'), 10, 1);
    }

    public function restore_stock_item($order_id)
    {
        $order = wc_get_order($order_id);

        if (!$order || 'yes' !== get_option('woocommerce_manage_stock') || !apply_filters('woocommerce_can_reduce_order_stock', true, $order) || get_option('woocommerce_hold_stock_minutes') < 1) {
            return;
        }      
        $orderMessage = [];
        foreach ($order->get_items() as $item) {
            if ($item['product_id'] > 0) {
                $_product = wc_get_product($item['product_id']);
                $productName = $_product->get_name();

                if ($_product && $_product->exists() && $_product->managing_stock()) {
                    $qty = apply_filters('woocommerce_order_item_quantity', $item['qty'], $order, $item);
                    wc_update_product_stock($_product, $qty, 'increase');
                    array_push($orderMessage, "{$productName}: stock restaurado correctamente.");
                    do_action('woocommerce_auto_stock_restored', $_product, $item);
                }
            }
        }
        if(sizeof($orderMessage) > 0) {
            $order->add_order_note(implode('<br />', $orderMessage));
        }
    }
}

new GoCuotasStockManager();