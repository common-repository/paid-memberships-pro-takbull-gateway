<?php

/**
 * Plugin Name: Paid Memberships Pro - Takbull Gateway
 * Description: Take credit card payments on your store using Takbull.
 * Author: S.P Takbull
 * Author URI: https://takbull.co.il/
 * Version: 1.2.0.1
 * Requires at least: 4.4
 * Tested up to: 5.7
 * WC requires at least: 2.6
 * WC tested up to: 4.0
 * Text Domain: pmpro-takbull
 * Domain Path: /languages
 *
 */
define("PMPRO_TAKBULLGATEWAY_DIR", plugin_dir_path(__FILE__));
define("PMPRO_TAKBULL_META_KEY", "_pmpro_takbull");

//load payment gateway class


function pmpro_takbull_plugins_loaded()
{
	if (!defined('PMPRO_DIR')) {
		return;
	}
	require(PMPRO_TAKBULLGATEWAY_DIR . '/includes/takbull_api.php');
	require(PMPRO_TAKBULLGATEWAY_DIR . '/includes/takbull_logger.php');
	require(PMPRO_TAKBULLGATEWAY_DIR . '/includes/takbull_order.php');
	require_once(PMPRO_TAKBULLGATEWAY_DIR . "/classes/class.pmprogateway_takbull.php");
	require_once(PMPRO_TAKBULLGATEWAY_DIR . "/classes/takbull_transaction.php");
}
add_action('plugins_loaded', 'pmpro_takbull_plugins_loaded');

function pmpro_currencies_ruble($currencies)
{
	$currencies['ILS'] = __('שקל ישראל (₪)', 'pmpro');
	return $currencies;
}

add_filter('pmpro_currencies', 'pmpro_currencies_ruble');

// Record when users gain the trial level.
function one_time_trial_save_trial_level_used($level_id, $user_id)
{
	update_user_meta($user_id, 'pmpro_trial_level_used', $level_id);
}
add_action('pmpro_after_change_membership_level', 'one_time_trial_save_trial_level_used', 10, 2);

add_action('before_woocommerce_init', function () {
    if (class_exists(\Automattic\WooCommerce\Utilities\FeaturesUtil::class)) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
    }
});