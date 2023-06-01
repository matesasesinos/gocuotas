<?php

class GoCuotas_Helper
{

    private static $instance;

    private function __construct()
    {
        add_action('admin_init', [$this, 'uploadIcon']);
        add_filter('woocommerce_get_price_html', [$this, 'show_fees_product'], 10, 2);
        add_filter('woocommerce_available_variation', [$this, 'show_fees_product_variations'], 10, 3);
    }

    public static function getInstance()
    {
        if (!isset(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    //logger
    public static function go_log($log, $save)
    {
        file_put_contents(__DIR__ . '/info/'.date('Y-m-d').'_'.$log.'.txt', json_encode($save) . "\n", FILE_APPEND);
    }

    public function uploadIcon()
    {
        if (isset($_FILES['woocommerce_gocuotas_iconfile']) && $_FILES['woocommerce_gocuotas_iconfile']['size'] > 0) {
            if ($_FILES['woocommerce_gocuotas_iconfile']['size'] > 100000) {
                add_action('admin_notices', function () {
                    echo '<div class="notice notice-error is-dismissible"><p>' . __('El icono no puede pesar mas de 100KB.', 'gocuotas') . '</p></div>';
                });
                return;
            }

            $tmp_file = $_FILES['woocommerce_gocuotas_iconfile']['tmp_name'];

            $type = mime_content_type($tmp_file);


            if ($type != 'image/png') {
                add_action('admin_notices', function () {
                    echo '<div class="notice notice-error is-dismissible"><p>' . __('El icono debe ser PNG.', 'gocuotas') . '</p></div>';
                });
                return;
            }

            list($width) = getimagesize($tmp_file);

            if ($width > 100) {
                add_action('admin_notices', function () {
                    echo '<div class="notice notice-error is-dismissible"><p>' . __('El icono debe tener un ancho hasta a 100px.', 'gocuotas') . '</p></div>';
                });
                return;
            }

            $upload = wp_upload_bits($_FILES['woocommerce_gocuotas_iconfile']['name'], null, file_get_contents($tmp_file));
            update_option('go_cuotas_icon', $upload['url'], true);

            add_action('admin_notices', function () {
                echo '<div class="notice notice-success is-dismissible"><p>' . __('El icono se cambio correctamente.', 'gocuotas') . '</p></div>';
            });
        }
    }

    private function show_logo()
    {
        if(get_option('woocommerce_gocuotas_settings', true)['show_icons'] === 'yes' && (is_product() || is_checkout())) {
            return ' <a id="fee" href="https://www.gocuotas.com" target="_blank"><img style="max-height: 35px;" src="' . get_option('go_cuotas_icon', plugin_dir_url(__FILE__) . 'logo.svg') . '"> </a>';
        }

        return;
    }


    public function fees($price, $product_id)
    {

        $product = wc_get_product($product_id);
        $sale_price = $product->get_sale_price();
        $regular_price = $product->get_regular_price();

        if ($product->is_type('variable')) {
            $precio = $product->get_price();
            $cuota = $precio / get_option('woocommerce_gocuotas_settings', true)['fees_number'];
            $cuota = number_format($cuota, 2, '.', ',');
            $new_price =  $price;
            return $new_price;
        } 

        if ($product->is_type('simple')) {
            $cuota = $sale_price ? $sale_price / get_option('woocommerce_gocuotas_settings', true)['fees_number'] : $regular_price / get_option('woocommerce_gocuotas_settings', true)['fees_number'];
            $cuota = number_format($cuota, 2, '.', ',');
            $new_price = $price . '<span class="custom-price-prefix singlefee">' . get_option('woocommerce_gocuotas_settings', true)['fees_text'] . ' $' . $cuota  . $this->show_logo() . '</span>';
            return $new_price;
        } 
    }

    public function show_fees_product($price, $product)
    { 
        if (is_admin()) return $price;

        if(get_option('woocommerce_gocuotas_settings', true)['enabled'] == 'no') return $price;  

        $p = wc_get_product($product->get_id());

        if(get_option('woocommerce_gocuotas_settings', true)['max_total'] < $p->get_price() && get_option('woocommerce_gocuotas_settings', true)['max_total']!= '') return $price;
        
        if (get_option('woocommerce_gocuotas_settings', true)['show_fees_product'] == 'yes' && is_product()) {
            
            return $this->fees($price, $product->get_id());
        }

        if (get_option('woocommerce_gocuotas_settings', true)['show_fees_category'] == 'yes' && !is_product()) {
            return $this->fees($price, $product->get_id());
        }

        return $price;
    }

    public function show_fees_product_variations($variation_data, $product, $variation)
    {
        if (get_option('woocommerce_gocuotas_settings', true)['show_fees_product'] == 'yes' && is_product()) {
            $cuota = $variation_data['display_price'] / get_option('woocommerce_gocuotas_settings', true)['fees_number'];
            $cuota = number_format($cuota, 2, '.', ',');
            $variation_data['price_html'] .= get_woocommerce_currency_symbol() . $variation_data['display_price'] . ' <span class="custom-price-prefix">' . get_option('woocommerce_gocuotas_settings', true)['fees_text'] . ' $' . $cuota . $this->show_logo() . '</span>';

            return $variation_data;
        }

        return $variation_data;
    }
}

GoCuotas_Helper::getInstance();