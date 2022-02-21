<?php

class WC_Gateway_GoCuotas extends WC_Payment_Gateway
{
    public function __construct()
    {
        $this->id = 'gocuotas';
        $this->has_fields = false;
        $this->method_title = 'Go Cuotas';
        $this->method_description = 'Plugin para integración de Go Cuotas en WooCommerce';

        $this->supports = array(
            'products'
        );

        $this->init_form_fields();

        $this->init_settings();
        $this->title = $this->get_option('title');
        $this->description = $this->get_option('description');
        $this->enabled = $this->get_option('enabled');

        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));

        add_action('wp_enqueue_scripts', array($this, 'payment_scripts'));

        add_action('woocommerce_api_gocuotas', array($this, 'webhook'));

        add_action('woocommerce_thankyou_' . $this->id, array($this, 'thankyou_page'));

        add_action('woocommerce_api_gocuotas_webhook', array($this, 'webhook'));
    }

    public function init_form_fields()
    {
        $this->form_fields = array(
            'enabled' => [
                'title'       => 'Activar/Desactivar',
                'label'       => 'Activar GOcuotas',
                'type'        => 'checkbox',
                'description' => '',
                'default'     => 'no'
            ],
            'title' => [
                'title'       => 'Titulo',
                'type'        => 'text',
                'description' => 'Titulo a mostrar al finalizar compra.',
                'default'     => 'Pagar CUOTAS con DEBITO sin interés',
                'desc_tip'    => true,
            ],
            'description' => [
                'title'       => 'Descripción',
                'type'        => 'textarea',
                'description' => 'Descripcion a mostrar al finalizar compra.',
                'default'     => 'Ahora podes pagar en CUOTAS sin interés con tu tarjeta de DEBITO!',
            ],
            'email_go' => [
                'title'       => 'Email API Comercio',
                'type'        => 'text'
            ],
            'password_go' => [
                'title'       => 'Password API Comercio',
                'type'        => 'password'
            ],
            'show_fees_category' => [
                'title'       => 'Mostrar cuotas en las categoría de productos',
                'label'       => 'Activar',
                'type'        => 'checkbox',
                'description' => '',
                'default'     => 'no'
            ],
            'show_fees_product' => [
                'title'       => 'Mostrar cuotas en la página de producto',
                'label'       => 'Activar',
                'type'        => 'checkbox',
                'description' => '',
                'default'     => 'no'
            ],
            'fees_text' => [
                'title' => 'Texto para mostrar las cuotas en el producto',
                'label' => 'Texto',
                'type' => 'text',
                'default' => 'o en 4 cuotas con tarjeta de DÉBITO SIN interés de: ',
                'description' => 'Ejemplo: o en 4 cuotas con tarjeta de DÉBITO SIN interés de: '
            ],
            'fees_number' => [
                'title' => 'Cuotas a mostrar',
                'label' => 'Cuotas',
                'type' => 'number',
                'default' => 4,
                'description' => 'Si en el texto de arriba se muestran 4 (cuatro) cuotas, este campo debe ser 4. Si se muestran 3 (tres) cuotas, este campo debe ser 3. etc'
            ],
            'logg' => [
                'title'       => 'Activar Log',
                'label'       => 'Activar',
                'type'        => 'checkbox',
                'description' => 'Guarda un log de operaciones en un archivo dentro de la carpeta del plugin.',
                'default'     => 'no'
            ]
        );
    }

    public function admin_options()
    {
        echo '<h3>' . $this->method_title . '</h3>';
        echo '<table class="form-table">';
        $this->generate_settings_html();
        echo '</table>';
    }

    public function payment_fields()
    {
        if ($this->description) {
            echo wpautop(wp_kses_post($this->description));
        }
    }

    public function payment_scripts()
    {

        if (!is_cart() && !is_checkout() && !isset($_GET['pay_for_order'])) {
            return;
        }

        if ('no' === $this->enabled) {
            return;
        }

        if (empty($this->comercio_key) || empty($this->publishable_key)) {
            return;
        }

        wp_localize_script('woocommerce_gocuotas', 'gocuotas_params', array(
            'publishableKey' => $this->publishable_key
        ));

        wp_enqueue_script('woocommerce_gocuotas');
    }

    public function process_payment($order_id)
    {
        global $woocommerce;
        $order = wc_get_order($order_id);
        $godata = get_option('woocommerce_gocuotas_settings', true);


        $authentication = wp_remote_post('https://www.gocuotas.com/api_redirect/v1/authentication', [
            'method' => 'POST',
            'body' => [
                'email' => $godata['email_go'],
                'password' => $godata['password_go']
            ]
        ]);

        if ($godata['logg'] == 'yes') {
            GoCuotas_Helper::go_log('auth-info.txt', json_encode($authentication) . PHP_EOL);
        }

        $auth_response = $authentication['response'];

        if ($auth_response['code'] != 200) {
            wc_add_notice($auth_response['message'] == 'Unauthorized' ? 'Error de autenticación de comercio.' : $auth_response['message'], 'error');
            return;
        }

        $token = $authentication['body'];
        $token = json_decode($token)->token;


        $total = $order->get_total();
        $total = str_replace(".", "", $total);
        $total = str_replace(",", "", $total);

        $order_received_url = wc_get_endpoint_url('order-received', $order->get_id(), wc_get_checkout_url());
        $order_received_url_ok = $order_received_url . "done/";

        $order_received_url_fail =  $order->get_checkout_payment_url($on_checkout = false);

        $order_received_url_okk = add_query_arg('key', $order->get_order_key(), $order_received_url_ok);


        $payment_init = wp_remote_post('https://www.gocuotas.com/api_redirect/v1/checkouts', [
            'method' => 'POST',
            'headers' => [
                'Authorization' => $token
            ],
            'body' => [
                'amount_in_cents' => $total,
                'order_reference_id' => $order_id,
                'url_success' => $order_received_url_okk,
                'url_failure' => $order_received_url_fail,
                'webhook_url' => home_url() . '/wc-api/gocuotas_webhook',
                'email' => $order->get_billing_email(),
                'phone_number' => $order->get_billing_phone(),
            ]
        ]);

        if (is_wp_error($payment_init)) {
            wc_add_notice('Error de pago: Ocurrio un error al conectar con la API.', 'error');
            return;
        };

        $response = $payment_init['response'];

        if ($response['code'] != 201) {
            wc_add_notice('Error de pago: Ocurrio un error al pagar. "' . $response['message'] . '"', 'error');
            return;
        }

        if ($godata['logg'] == 'yes') {
            GoCuotas_Helper::go_log('payment_info.txt', json_encode($payment_init['body']) . PHP_EOL);
        }

        $url_init = $payment_init['body'];
        $url_init = json_decode($url_init)->url_init;

        update_post_meta($order_id, 'gocuotas_response', $payment_init['body']);
        $order->reduce_order_stock();
        WC()->cart->empty_cart();

        return array(
            'result' => 'success',
            'redirect' => $url_init
        );
    }
    public function thankyou_page($order_id)
    {
        if ($this->instructions) {
            echo wpautop(wptexturize($this->instructions));
        }

        $r = $_SERVER['REQUEST_URI'];
        $r = explode('/', $r);

        if (in_array("done", $r)) {
            $order = wc_get_order($order_id);
            $order->payment_complete();
            $order->add_order_note(
                'GO Cuotas: ' .
                    __('Payment APPROVED. IPN', 'gocuotas')
            );
        }
    }

    public function webhook()
    {
        $data = json_decode(file_get_contents('php://input'), true);

        GoCuotas_Helper::go_log(date('Y-m-d') . 'webhook.txt', json_encode($data) . PHP_EOL);

        $order = wc_get_order($data['order_reference_id']);

        if (!$order) return;

        $status = ['completed', 'processing', 'cancelled', 'refunded'];

        if (in_array($order->get_status(), $status)) return;

        if ($data['status'] != 'approved') {
            $order->update_status('failed');
            $order->add_order_note(
                'GO Cuotas: ' .
                    __('ERROR EN PAGO, DENEGADO. IPN', 'gocuotas')
            );

            return;
        }

        $order->payment_complete();
        $order->reduce_order_stock();

        update_option('webhook_debug', $_GET);
    }
}

remove_filter('woocommerce_cancel_unpaid_orders', 'wc_cancel_unpaid_orders');
add_filter('woocommerce_cancel_unpaid_orders', 'override_cancel_unpaid_orders');

function override_cancel_unpaid_orders()
{
    $held_duration = get_option('woocommerce_hold_stock_minutes');

    if ($held_duration < 1 || 'yes' !== get_option('woocommerce_manage_stock')) {
        return;
    }

    $data_store    = WC_Data_Store::load('order');
    $unpaid_orders = $data_store->get_unpaid_orders(strtotime('-' . absint($held_duration) . ' MINUTES', current_time('timestamp')));

    if ($unpaid_orders) {
        foreach ($unpaid_orders as $unpaid_order) {
            $order = wc_get_order($unpaid_order);

            if (apply_filters('woocommerce_cancel_unpaid_order', 'checkout' === $order->get_created_via(), $order)) {
                $order->update_status('cancelled', __('Unpaid order cancelled - time limit reached.', 'woocommerce'));

                foreach ($order->get_items() as $item_id => $item) {
                    $product = $item->get_product();
                    $qty = $item->get_quantity();
                    wc_update_product_stock($product, $qty, 'increase');
                }
            }
        }
    }
    wp_clear_scheduled_hook('woocommerce_cancel_unpaid_orders');
    wp_schedule_single_event(time() + (absint($held_duration) * 60), 'woocommerce_cancel_unpaid_orders');
}