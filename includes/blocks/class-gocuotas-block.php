<?php

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;

class WC_GoCuotas_Block_Support extends AbstractPaymentMethodType
{
	private $gateway;

	protected $name = 'gocuotas';

	public function initialize()
	{
		$this->settings = get_option('woocommerce_gocuotas_settings', []);
		$gateways       = WC()->payment_gateways->payment_gateways();
		$this->gateway  = $gateways[$this->name];
	}

	public function is_active()
	{
		return $this->gateway->is_available();
	}

	public function get_payment_method_script_handles()
	{
		$script_path       = '/assets/js/frontend/blocks.js';
		$script_asset_path = WC_GoCuotas::plugin_abspath() . 'assets/js/frontend/blocks.asset.php';
		$script_asset      = file_exists($script_asset_path)
			? require($script_asset_path)
			: array(
				'dependencies' => array(),
				'version'      => '1.2.0'
			);

		$script_url = WC_GoCuotas::plugin_url() . $script_path;

		wp_register_script(
			'wc-gocuotas-blocks',
			$script_url,
			$script_asset['dependencies'],
			$script_asset['version'],
			true
		);

		if (function_exists('wp_set_script_translations')) {
			wp_set_script_translations('wc-gocuotas-blocks', 'woocommerce-gateway-gocuotas', WC_GoCuotas::plugin_abspath() . 'languages/');
		}

		return ['wc-gocuotas-blocks'];
	}

	public function get_payment_method_data()
	{
		return [
			'title'       => $this->get_setting('title'),
			'description' => $this->get_setting('description'),
			'supports'    => array_filter($this->gateway->supports, [$this->gateway, 'supports'])
		];
	}
}
