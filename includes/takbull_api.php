<?php
if (!defined('ABSPATH')) {
	exit;
}

/**
 * Communicates with Takbull API.
 */
class Takbull_API
{

	/**
	 * Takbull API Endpoint
	 */
	const ENDPOINT           = 'https://api.takbull.co.il/';
	// const ENDPOINT           = 'http://192.168.1.16:8001/';
	const TAKBULL_API_VERSION = '1.0.0.1';

	/**
	 * Secret API Key.
	 * @var string
	 */
	private static $api_secret = '';

	/**
	 * Set secret API Key.
	 * @param string $key
	 */
	public static function set_api_secret($api_secret)
	{
		self::$api_secret = $api_secret;
	}

	/**
	 * Secret API Key.
	 * @var string
	 */
	private static $api_key = '';

	/**
	 * Set secret API Key.
	 * @param string $key
	 */
	public static function set_api_key($api_key)
	{
		self::$api_key = $api_key;
	}

	/**
	 * Get secret key.
	 * @return string
	 */
	public static function get_api_secret()
	{
		return self::$api_secret;
	}

	/**
	 * Get secret key.
	 * @return string
	 */
	public static function get_api_key()
	{
		return self::$api_key;
	}

	public static function	get_redirecr_order_api()
	{
		return self::ENDPOINT . "PaymentGateway";
	}

	/**
	 * Generates the user agent we use to pass to API request so
	 * Takbull can identify our application.
	 *
	 * @since 4.0.0
	 * @version 4.0.0
	 */
	public static function get_user_agent()
	{
		$app_info = array(
			'name'    => 'WooCommerce Takbull Gateway',
			'version' => self::TAKBULL_API_VERSION,
		);

		return array(
			'lang'         => 'php',
			'lang_version' => phpversion(),
			'publisher'    => 'woocommerce',
			'uname'        => php_uname(),
			'application'  => $app_info,
		);
	}

	/**
	 * Generates the headers to pass to API request.
	 *
	 * @since 4.0.0
	 * @version 4.0.0
	 */
	public static function get_headers()
	{
		$user_agent = self::get_user_agent();
		$app_info   = $user_agent['application'];

		return apply_filters(
			'woocommerce_takbull_request_headers',
			array(
				'API_Secret'              => self::get_api_secret(),
				'API_Key'              => self::get_api_key(),
				'Takbull-Version'             => self::TAKBULL_API_VERSION,
				'User-Agent'                 => $app_info['name'] . '/' . $app_info['version'],
				'X-Takbull-Client-User-Agent' => json_encode($user_agent),
				'Content-Type' => 'application/json'
			)
		);
	}

	/**
	 * Send the request to Takbull's API
	 *
	 * @since 3.1.0
	 * @version 4.0.6
	 * @param array $request
	 * @param string $api
	 * @param string $method
	 * @param bool $with_headers To get the response with headers.
	 * @return stdClass|array
	 * @throws Exception
	 */
	public static function request($request, $api = 'charges', $method = 'POST', $with_headers = false)
	{
		$headers         = self::get_headers();
		$response = wp_remote_post(
			self::ENDPOINT . $api,
			array(
				'method'  => $method,
				'headers' => $headers,
				'body'    => apply_filters('woocommerce_takbull_request_body', $request, $api),
				'timeout' => 70,
			)
		);

		if (is_wp_error($response) || empty($response['body'])) {
			Pmpro_Takbull_Logger::log(
				'Error Response: ' . print_r($response, true) . PHP_EOL . PHP_EOL . 'Failed request: ' . print_r(
					array(
						'api'             => $api,
						'request'         => $request,
					),
					true
				)
			);
			throw new Exception(print_r($response, true), __('There was a problem connecting to the Takbull API endpoint.', 'takbull-gateway'));
		}
		return array(
			'headers' => wp_remote_retrieve_headers($response),
			'body'    => json_decode($response['body']),
		);
	}

	public static function get_request($request)
	{
		$headers         = self::get_headers();
		$response = wp_remote_get(
			self::ENDPOINT . $request,
			array(
				'headers' => $headers,
				'timeout' => 70,
			)
		);

		if (is_wp_error($response) || empty($response['body'])) {
			PmPro_Takbull_Logger::log(
				'Error Response: ' . print_r($response, true) . PHP_EOL . PHP_EOL . 'Failed request: ' . print_r(
					array(
						'request'         => $request,
					),
					true
				)
			);
			throw new WC_Takbull_Exception(print_r($response, true), __('There was a problem connecting to the Takbull API endpoint.', 'takbull-gateway'));
		}
		return array(
			'headers' => wp_remote_retrieve_headers($response),
			'body'    => json_decode($response['body']),
		);
	}
}
