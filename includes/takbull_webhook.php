<?php

if (!defined('PMPRO_DIR') || !defined('PMPRO_TAKBULLGATEWAY_DIR')) {
	error_log(__('Paid Memberships Pro and the PMPro Takbull Add On must be activated for the PMPro Takbull IPN handler to function.', 'pmpro-takbull'));
	exit;
}

//some globals
global $wpdb, $gateway_environment, $logstr;
$logstr = "";

Pmpro_Takbull_Logger::log("pmpro_takbull_webhook_hit:: " . print_r($_GET, true));
$statusCode               = wp_unslash($_GET['statusCode']); //pmpro_getParam("statusCode", "GET");
$statusDescription              = pmpro_getParam("statusDescription", "GET");
$uniqId                 = pmpro_getParam("uniqId", "GET");
$token              = pmpro_getParam("token", "GET");
$order_reference         = $orderId = wp_unslash($_GET['order_reference']); // WPCS: CSRF ok, input var ok.
$transactionInternalNumber = pmpro_getParam("transactionInternalNumber", "GET");
$gateway = new PMProGateway_takbull();
Takbull_API::set_api_secret(pmpro_getOption("apisecret"));
Takbull_API::set_api_key(pmpro_getOption("apikey"));
if (!empty($order_reference)) {
	$transaction = new Pmpro_Takbull_Transaction($transactionInternalNumber, $order_reference);
	$morder = new MemberOrder($order_reference);
	$user = get_userdata($morder->user_id);
	if (empty($morder)) {
		Pmpro_Takbull_Logger::log("ERROR: order wasnt fount :: " . $morder->code);
		exit;
	} else {
		do_action("takbull_ipn_hit", $transaction, $morder);

		if ($statusCode  != 0) {
			try {
				Pmpro_Takbull_Logger::log("ERROR: status code :: " . $statusCode);
				$pmproemail = new PMProEmail();
				if ($user) {
					$pmproemail->sendBillingFailureEmail($user, $morder);
				}

				// Email admin so they are aware of the failure
				$pmproemail = new PMProEmail();
				$pmproemail->sendBillingFailureAdminEmail(get_bloginfo("admin_email"), $morder);
				Pmpro_Takbull_Logger::log("ERROR: order failed statusDescription :: " . $statusDescription);
				exit;
			} catch (Exception $e) {
				Pmpro_Takbull_Logger::log("Exception : order failed statusDescription :: " . print_r($e, true));
			}
			// pmpro_cancelMembershipLevel($morder->membership_id, $morder->user_id, 'error');
		} else {
			//is this a first payment?
			// $last_subscr_order = new MemberOrder();
			// $last_subscr_order->getLastMemberOrderBySubscriptionTransactionID($uniqId);
			Pmpro_Takbull_Logger::log("update morder uniq id: " . $uniqId);
			$id = $wpdb->get_var("SELECT id FROM $wpdb->pmpro_membership_orders WHERE payment_transaction_id = '" . $uniqId . "' ORDER BY id DESC LIMIT 1");
			Pmpro_Takbull_Logger::log("update morder id: " . $id);
			if (!$id) {
				Pmpro_Takbull_Logger::log("new order Reccuring payment was succesfull");
				$display_type = pmpro_getOption("display_type");
				if ($display_type == 2) {
					Pmpro_Takbull_Logger::log("update morder after redirect");
					pmpro_ipnChangeMembershipLevel($uniqId, $morder);
				} else {
					if($morder->status != 'success'){
						$morder->payment_transaction_id      = $uniqId;
						$morder->subscription_transaction_id = $uniqId;
						$morder->status = 'success'; // We have confirmed that and thats the reason we are here.
					}
				}
				// do_action("pmpro_subscription_payment_completed", $morder);
			} else {
				$morder->status = 'success'; // We have confirmed that and thats the reason we are here.
				Pmpro_Takbull_Logger::log("Reccuring payment was succesfull");
				// $amount = $transaction->get_amount();
				// if ($amount)
				// 	$morder->total  += $amount;
			}
			$morder->timestamp = strtotime(date("Y-m-d H:i:s"));
			$morder->saveOrder();
		}
	}
}

function pmpro_takbull_Validate($uniqId)
{
	if ($_GET['statusCode'] != 0) {
		return false;
	}
	$validateReq = array(
		'uniqId' => $uniqId
	);
	$response = Takbull_API::request(json_encode($validateReq), "api/ExtranalAPI/ValidateNotification");
	Pmpro_Takbull_Logger::log('Checking PMPRO TAkbULL IPN response is valid response::: ' . print_r($response, true));
	if (!empty($response->error)) {
		throw new Exception(print_r($response, true), $response
			->error
			->message);
	}
	$body = wp_remote_retrieve_body($response);
	if ($body->internalCode != 0) {
		return false;
	}
	return true;
}

function pmpro_ipnExit()
{
	exit;
}

function pmpro_ipnChangeMembershipLevel($txn_id, &$morder)
{

	global $wpdb;
	$morder->getMembershipLevel();
	$morder->getUser();
	//filter for level
	$morder->membership_level = apply_filters("pmpro_ipnhandler_level", $morder->membership_level, $morder->user_id);

	//set the start date to current_time('timestamp') but allow filters  (documented in preheaders/checkout.php)
	$startdate = apply_filters("pmpro_checkout_start_date", "'" . current_time('mysql') . "'", $morder->user_id, $morder->membership_level);

	//fix expiration date
	if (!empty($morder->membership_level->expiration_number)) {
		$enddate = "'" . date_i18n("Y-m-d", strtotime("+ " . $morder->membership_level->expiration_number . " " . $morder->membership_level->expiration_period, current_time("timestamp"))) . "'";
	} else {
		$enddate = "NULL";
	}

	//filter the enddate (documented in preheaders/checkout.php)
	$enddate = apply_filters("pmpro_checkout_end_date", $enddate, $morder->user_id, $morder->membership_level, $startdate);

	//get discount code
	$morder->getDiscountCode();
	if (!empty($morder->discount_code)) {
		//update membership level
		$morder->getMembershipLevel(true);
		$discount_code_id = $morder->discount_code->id;
	} else {
		$discount_code_id = "";
	}


	//custom level to change user to
	$custom_level = array(
		'user_id'         => $morder->user_id,
		'membership_id'   => $morder->membership_level->id,
		'code_id'         => $discount_code_id,
		'initial_payment' => $morder->membership_level->initial_payment,
		'billing_amount'  => $morder->membership_level->billing_amount,
		'cycle_number'    => $morder->membership_level->cycle_number,
		'cycle_period'    => $morder->membership_level->cycle_period,
		'billing_limit'   => $morder->membership_level->billing_limit,
		'trial_amount'    => $morder->membership_level->trial_amount,
		'trial_limit'     => $morder->membership_level->trial_limit,
		'startdate'       => $startdate,
		'enddate'         => $enddate
	);

	global $pmpro_error;
	if (!empty($pmpro_error)) {
		echo esc_html($pmpro_error);
		ipnlog($pmpro_error);
	}
	Pmpro_Takbull_Logger::log("before pmpro_changeMembershipLevel");
	//change level and continue "checkout"
	if (pmpro_changeMembershipLevel($custom_level, $morder->user_id, 'changed') !== false) {
		//update order status and transaction ids
		Pmpro_Takbull_Logger::log("in pmpro_changeMembershipLevel");
		$morder->status                 = "success";
		$morder->payment_transaction_id = $txn_id;
		$morder->subscription_transaction_id = $txn_id;
		$morder->saveOrder();

		//add discount code use
		if (!empty($discount_code) && !empty($use_discount_code)) {

			$wpdb->query(
				$wpdb->prepare(
					"INSERT INTO {$wpdb->pmpro_discount_codes_uses} 
						( code_id, user_id, order_id, timestamp ) 
						VALUES( %d, %d, %s, %s )",
					$discount_code_id
				),
				$morder->user_id,
				$morder->id,
				current_time('mysql')
			);
		}

		//hook
		do_action("pmpro_after_checkout", $morder->user_id, $morder);

		//setup some values for the emails
		if (!empty($morder)) {
			$invoice = new MemberOrder($morder->id);
		} else {
			$invoice = null;
		}

		$user                   = get_userdata($morder->user_id);
		$user->membership_level = $morder->membership_level;        //make sure they have the right level info

		//send email to member
		$pmproemail = new PMProEmail();
		$pmproemail->sendCheckoutEmail($user, $invoice);

		//send email to admin
		$pmproemail = new PMProEmail();
		$pmproemail->sendCheckoutAdminEmail($user, $invoice);

		return true;
	} else {
		return false;
	}
}
