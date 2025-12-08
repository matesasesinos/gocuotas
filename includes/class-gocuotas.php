<?php

if (! defined('ABSPATH')) {
    exit;
}

class WC_Gateway_GoCuotas extends WC_Payment_Gateway
{
    public $id = 'gocuotas';
    public $sandbox;
    public $utmParams;

    public function __construct()
    {
        $this->icon = isset(get_option('woocommerce_gocuotas_settings', true)['show_icons']) === 'yes' ? get_option('go_cuotas_icon', WC_GoCuotas::plugin_url() . '/logo.png') : '';
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
        $this->sandbox = $this->get_option('sandbox');
        $this->utmParams = $this->get_option('utm_params');

        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));

        add_action('wp_enqueue_scripts', array($this, 'payment_scripts'));
        add_action('wp_enqueue_scripts', array($this, 'utmScript'));

        add_action('woocommerce_api_gocuotas', array($this, 'webhook'));

        add_action('woocommerce_thankyou_' . $this->id, array($this, 'thankyou_page'));

        add_action('woocommerce_api_gocuotas_webhook', array($this, 'webhook'));

        add_filter('woocommerce_available_payment_gateways', array($this, 'restrict_payment_option'));
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
            'sandbox' => [
                'title'       => 'Modo Sandbox',
                'label'       => 'Activar',
                'type'        => 'checkbox',
                'description' => 'Habilitar el modo sandbox para pruebas.',
                'default'     => 'no'
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
            'max_total' => [
                'title' => 'Mostrar solo si el total es menor a: ',
                'lable' => 'Máximo',
                'type' => 'number',
                'description' => 'Configurar el costo máximo de la orden para utilizar el plugin, este total también muestra o no las cuotas en el producto, deje el campo vacio para desactivar'
            ],
            'iconfile' => [
                'title'       => 'Icono',
                'label'       => 'Icono a mostrar',
                'type'        => 'file',
                'description' => 'Icono que se mostrara en el producto y al finalizar la compra. <br />Actual<br /> <img src="' . get_option('go_cuotas_icon', WC_GoCuotas::plugin_url() . '/logo.png') . '" style="max-width:100px" />',
                'default'     => get_option('go_cuotas_icon', WC_GoCuotas::plugin_url() . '/logo.png'),
            ],
            'show_icons' => [
                'title'       => 'Mostrar iconos (logos)',
                'label'       => 'Activar',
                'type'        => 'checkbox',
                'description' => 'Mostrar o no los logos del plugin, si lo desactiva, no se ven en ninguna parte de la página.',
                'default'     => 'yes'
            ],
            'logg' => [
                'title'       => 'Activar Log',
                'label'       => 'Activar',
                'type'        => 'checkbox',
                'description' => 'Guarda un log de operaciones, se puede encontrar en WooCommerce > Estado > Registros (gocuotas).',
                'default'     => 'no'
            ],
            'utm_params' => [
                'title'       => 'Incluir parámetros UTM',
                'label'       => 'Activar',
                'type'        => 'checkbox',
                'description' => 'Incluir los parámetros UTM en las URLs de éxito y fracaso para el seguimiento de campañas.',
                'default'     => 'no'
            ],
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

    public function restrict_payment_option($available_gateways)
    {
        if (is_admin()) {
            return $available_gateways;
        }

        if (is_wc_endpoint_url('order_pay')) {
            $order_id = wc_get_order_id_by_order_key($_GET['key']);
            $order = wc_get_order($order_id);
            $order_total = $order->get_total();
        } else {
            $order_total = WC()->cart != null ? WC()->cart->total : [];
        }

        if (get_option('woocommerce_gocuotas_settings', true)['max_total'] < $order_total && get_option('woocommerce_gocuotas_settings', true)['max_total'] != '') {
            unset($available_gateways['gocuotas']);
        }

        return $available_gateways;
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

    public function utmScript()
    {
        if ($this->utmParams !== 'yes') {
            return;
        }

        wp_enqueue_script('gocuotas_utm', plugin_dir_url(__DIR__) . '/../assets/js/gocuotas-utm.js', array(), '1.0.0', true);
    }

    public function process_payment($order_id)
    {
        try {
            $order = wc_get_order($order_id);

            if (!$order) {
                return [
                    'result'   => 'failure',
                    'messages' => __('GoCuotas: Orden no encontrada', 'gocuotas'),
                    'reload'   => false,
                ];
            }

            $godata = get_option('woocommerce_gocuotas_settings', true);

            $endpoint = $this->endpointURL();
            $token = $this->setToken($endpoint, $godata['email_go'], $godata['password_go']);

            if (!$token) {
                return [
                    'result'   => 'failure',
                    'messages' => __('GoCuotas: Error al obtener el token de autenticación', 'gocuotas'),
                    'reload'   => false,
                ];
            }

            $total = $order->get_total();
            $total = str_replace(".", "", $total);
            $total = str_replace(",", "", $total);

            if (intval(get_option('woocommerce_price_num_decimals')) == 0) {
                $total = number_format($total, 2, '', '');
            }

            //UTM
            $utmParams = $this->setUTMParams();

            //URLS
            $orderReceivedUrl = wc_get_endpoint_url('order-received', $order->get_id(), wc_get_checkout_url());
            $orderReceivedUrlFail =  $order->get_checkout_payment_url($on_checkout = false);
            $orderReceivedUrlSuccess = add_query_arg('key', $order->get_order_key(), $orderReceivedUrl);

            if (!empty($utmParams)) {
                $orderReceivedUrlFail = add_query_arg($utmParams, $orderReceivedUrlFail);
                $orderReceivedUrlSuccess = add_query_arg($utmParams, $orderReceivedUrlSuccess);
            }

            $body = [
                'amount_in_cents' => $total,
                'order_reference_id' => $order_id,
                'url_success' => $orderReceivedUrlSuccess,
                'url_failure' => $orderReceivedUrlFail,
                'webhook_url' => home_url() . '/wc-api/gocuotas_webhook',
                'email' => $order->get_billing_email(),
                'phone_number' => $order->get_billing_phone(),
            ];

            $paymentInit = $this->setPaymentProcess($endpoint, $token, $body);

            if (!$paymentInit) {
                return [
                    'result'   => 'failure',
                    'messages' => __('GoCuotas: Error en la respuesta API', 'gocuotas'),
                    'reload'   => false,
                ];
            };

            if ($godata['logg'] == 'yes') {
                wc_get_logger()->info('GoCuotas: Respuesta API - ' . print_r([
                    'success_url' => $orderReceivedUrlSuccess,
                    'failure_url' => $orderReceivedUrlFail,
                    'utm_params' => $utmParams,
                    'body' => $body
                ], true), array('source' => 'gocuotas'));
                wc_get_logger()->info('GoCuotas: Respuesta API - ' . print_r($paymentInit, true), array('source' => 'gocuotas'));
            }

            $urlInit = json_decode($paymentInit)->url_init;

            update_post_meta($order_id, 'gocuotas_response', $paymentInit);

            return array(
                'result' => 'success',
                'redirect' => $urlInit
            );
        } catch (Exception $e) {
            wc_get_logger()->critical('GoCuotas: Excepción en process_payment - ' . $e->getMessage(), array('source' => 'gocuotas'));
            return [
                'result'   => 'failure',
                'messages' => $e->getMessage(),
                'reload'   => false,
            ];
        }
    }

    public function thankyou_page($order_id)
    {
        // delete utm cookies if exist
        if (!empty($this->setUTMParams())) {
            $keys = ['utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content'];

            foreach ($keys as $key) {
                if (isset($_COOKIE[$key])) {
                    unset($_COOKIE[$key]);
                    setcookie($key, '', time() - 3600, '/');
                }
            }
        }

        $order = wc_get_order($order_id);

        if (!$order)
            return;

        if ($order->get_payment_method() !== $this->id) {
            return;
        }

        $skip_status = array('processing', 'completed');
        if (in_array($order->get_status(), $skip_status, true)) {
            return;
        }

        $order->add_order_note(__('GO Cuotas: esperando confirmación de pago.', 'gocuotas'));

        if (class_exists('WC_Cart') && WC()->cart) {
            WC()->cart->empty_cart();
        }
    }

    public function webhook()
    {
        $data = json_decode(file_get_contents('php://input'), true);

        // Logger
        if (get_option('woocommerce_gocuotas_settings', true)['logg'] == 'yes') {
            wc_get_logger()->info('GoCuotas: Webhook recibido - ' . print_r($data, true), array('source' => 'gocuotas'));
        }

        $order = wc_get_order($data['order_reference_id']);

        if (!$order) return;

        if ($order->get_payment_method() != 'gocuotas')
            return;

        $status = ['completed', 'processing', 'cancelled', 'refunded'];

        if (in_array($order->get_status(), $status)) return;

        if ($data['status'] != 'approved') {
            $message = "Go Cuotas: ERROR EN PAGO, DENEGADO. IPN<br /> Ver log de WooCommerce para más detalles.";
            $order->update_status('failed');
            $order->add_order_note($message);

            return;
        }

        $order->payment_complete();
        wc_reduce_stock_levels($data['order_reference_id']);

        update_option('webhook_debug', $_GET);
    }

    private function endpointURL()
    {
        return $this->sandbox == 'yes' ? 'https://sandbox.gocuotas.com' : 'https://www.gocuotas.com';
    }

    private function setToken($endpoint, $email, $password)
    {
        $authentication = wp_remote_post($endpoint . '/api_redirect/v1/authentication/?email=' . $email . '&password=' . $password, [
            'method' => 'POST',
            'timeout' => 20,
            'redirection' => 5,
        ]);

        if (is_wp_error($authentication)) {
            wc_get_logger()->error('GoCuotas: Error en la petición de autenticación - ' . $authentication->get_error_message(), array('source' => 'gocuotas'));
            return false;
        }

        if (!$authentication['response'] || $authentication['response']['code'] != 200) {
            wc_get_logger()->error('GoCuotas: Error en la respuesta de autenticación ' . json_encode($authentication), array('source' => 'gocuotas'));
            return false;
        }

        $token = wp_remote_retrieve_body($authentication);
        $token = json_decode($token)->token;
        return $token;
    }

    private function setPaymentProcess($endpoint, $token, $body)
    {
        $headers = [
            'Authorization' => 'Bearer ' . $token,
            'Content-Type' => 'application/json',
        ];

        $paymentInit = wp_remote_post($endpoint . '/api_redirect/v1/checkouts', [
            'headers' => $headers,
            'body' => json_encode($body),
        ]);

        if (is_wp_error($paymentInit)) {
            wc_get_logger()->error('GoCuotas: Error en la respuesta API ' . json_encode($paymentInit), array('source' => 'gocuotas'));
            return false;
        }

        if ($paymentInit['response']['code'] != 201) {
            wc_get_logger()->error('GoCuotas: Error en la respuesta API ' . json_encode($paymentInit), array('source' => 'gocuotas'));
            return false;
        }

        return $paymentInit['body'];
    }

    private function setUTMParams()
    {
        if ($this->utmParams !== 'yes') {
            return [];
        }

        $utmParams = [];
        $keys = ['utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content'];

        foreach ($keys as $key) {
            if (isset($_COOKIE[$key])) {
                $utmParams[$key] = sanitize_text_field($_COOKIE[$key]);
            }
        }

        return $utmParams;
    }
}
