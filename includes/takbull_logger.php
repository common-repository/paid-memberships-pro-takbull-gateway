<?php
if (!defined('ABSPATH')) {
	exit; // Exit if accessed directly
}


class PmPro_Takbull_Logger
{

	public static $logger;
	const LOG_FILENAME = 'pmpro-gateway-takbull';

	public static function log($message, $end_time = null)
	{
		$takbull_logging = pmpro_getOption("takbull_logging");
		if ($takbull_logging == 0) {
			return;
		}
		if (apply_filters('takbull_logging', true, $message)) {
			$formatted_start_time = date_i18n(get_option('date_format') . ' g:ia', date("d/m/Y H:i:s"));
			$log_entry = '====Start Log ' . $formatted_start_time . '====' . "\n" . $message . "\n";
			// $log_entry = '====Start Log====' . "\n" . $message . "\n" . '====End Log====' . "\n\n";
		}
		try {
			$loghandle = fopen(PMPRO_TAKBULLGATEWAY_DIR . "/logs/pmpro_takbull.log", "a+");
			fwrite($loghandle, $log_entry);
			fclose($loghandle);
		} catch (\Exception $e) {
			error_log($log_entry);
		}
	}
}
