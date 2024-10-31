<?php
// Require the default PMPro Gateway Class.
require_once PMPRO_DIR . '/classes/gateways/class.pmprogateway.php';
//load classes init method
add_action('init', array(
    'PMProGateway_takbull',
    'init'
));

class PMProGateway_takbull extends PMProGateway
{
    function __construct($gateway = null)
    {
        $this->gateway = $gateway;
        return $this->gateway;
    }

    /**
     * Run on WP init
     *
     * @since 1.8
     */
    static function init()
    {
        add_filter('pmpro_gateways', array(
            'PMProGateway_takbull',
            'pmpro_gateways'
        ));

        //add fields to payment settings
        add_filter('pmpro_payment_options', array(
            'PMProGateway_takbull',
            'pmpro_payment_options'
        ));
        add_filter('pmpro_payment_option_fields', array(
            'PMProGateway_takbull',
            'pmpro_payment_option_fields'
        ), 10, 2);

        //code to add at checkout
        $gateway = pmpro_getGateway();
        if ($gateway == "takbull") {
            add_filter('pmpro_include_payment_information_fields', '__return_false');
            add_filter('pmpro_required_billing_fields', array(
                'PMProGateway_takbull',
                'pmpro_required_billing_fields'
            ));

            add_action('pmpro_checkout_after_form', array('PMProGateway_takbull', 'pmpro_checkout_preheader'));
            add_action('pmpro_checkout_after_form', array('PMProGateway_takbull', 'pmpro_checkout_after_form'));
        }
        add_filter('pmpro_checkout_before_change_membership_level', array(
            'PMProGateway_takbull',
            'pmpro_checkout_before_change_membership_level'
        ), 11, 2);

        add_action('wp_ajax_nopriv_pmpro_takbull_ipn_handler', array(
            'PMProGateway_takbull',
            'wp_ajax_pmpro_takbull_ipn_handler'
        ));
        add_action('wp_ajax_pmpro_takbull_ipn_handler', array(
            'PMProGateway_takbull',
            'wp_ajax_pmpro_takbull_ipn_handler'
        ));

        add_action('wp_ajax_nopriv_pmpro_takbull_get_redirect', array(
            'PMProGateway_takbull',
            'wp_ajax_pmpro_takbull_get_redirect'
        ));
        add_action('wp_ajax_pmpro_takbull_get_redirect', array(
            'PMProGateway_takbull',
            'wp_ajax_pmpro_takbull_get_redirect'
        ));
        add_filter('pmpro_gateways_with_pending_status', array(
            'PMProGateway_takbull',
            'pmpro_gateways_with_pending_status'
        ));


        add_action('wp_ajax_nopriv_pmpro_takbull_checkout_process', array(
            'PMProGateway_takbull',
            'pmpro_takbull_checkout_process'
        ));
        add_action('wp_ajax_pmpro_takbull_checkout_process', array(
            'PMProGateway_takbull',
            'pmpro_takbull_checkout_process'
        ));
    }



    /**
     * Send traffic to wp-admin/admin-ajax.php?action=pmpro_takbull_itn_handler to the ipn handler
     */
    static function wp_ajax_pmpro_takbull_ipn_handler()
    {

        require_once PMPRO_TAKBULLGATEWAY_DIR . '/includes/takbull_webhook.php';
        exit;
    }


    static function wp_ajax_pmpro_takbull_get_redirect()
    {
        if (!wp_verify_nonce($_POST['nonce'], 'ajax-nonce' . pmpro_getOption("gateway"))) {
            die('Busted!');
        }
        Takbull_API::set_api_secret(pmpro_getOption("apisecret"));
        Takbull_API::set_api_key(pmpro_getOption("apikey"));
        $request_data = $_POST['req'];
        $request_data['Language'] = get_locale();
        $response = Takbull_API::request(json_encode($request_data), "api/ExtranalAPI/GetTakbullPaymentPageRedirectUrl");
        if (!empty($response->error)) {
            throw new Exception(print_r($response, true), $response
                ->error
                ->message);
        }
        $body = wp_remote_retrieve_body($response);
        wp_send_json_success($body);
    }

    /**
     * Filtering orders at checkout.
     *
     * @since 1.8
     */
    static function pmpro_checkout_order($morder)
    {
        return $morder;
    }

    /**
     * Make sure this gateway is in the gateways list
     *
     * @since 1.8
     */
    static function pmpro_gateways($gateways)
    {
        if (empty($gateways['takbull'])) $gateways['takbull'] = __('Takbull', 'paid-memberships-pro');

        return $gateways;
    }

    /**
     * Add Takbull to the list of allowed gateways.
     *
     * @return array
     */
    static function pmpro_gateways_with_pending_status($gateways)
    {
        $gateways[] = 'takbull';
        return $gateways;
    }

    /**
     * Get a list of payment options that the this gateway needs/supports.
     *
     * @since 1.8
     */
    static function getGatewayOptions()
    {
        $options = array(
            // 'sslseal',
            // 'nuclear_HTTPS',
            'apikey',
            'apisecret',
            'create_invoice',
            'is_taxtable',
            'is_sub_date_same_as_charge',
            'delay_to_next_period_after_initial_payment',
            'display_type',
            'currency',
            'takbull_logging'
        );

        return $options;
    }

    /**
     * Set payment options for payment settings page.
     *
     * @since 1.8
     */
    static function pmpro_payment_options($options)
    {
        //get options
        $takbull_options = PMProGateway_takbull::getGatewayOptions();

        //merge with others.
        $options = array_merge($takbull_options, $options);

        return $options;
    }

    /**
     * Display fields for this gateway's options.
     *
     * @since 1.8
     */
    static function pmpro_payment_option_fields($values, $gateway)
    {
?>
        <tr class="pmpro_settings_divider gateway  gateway_takbull " <?php if ($gateway != "takbull") {                                                                        ?>style="display: none;" <?php                                                                                                }                                                                                                    ?>>
            <td colspan="2">
                <hr />
                <h2 class="title"><?php esc_html_e('Takbull Settings', 'paid-memberships-pro'); ?></h2>
            </td>
        </tr>
        <tr class="gateway  gateway_takbull" <?php if ($gateway != "takbull") { ?>style="display: none;" <?php                                                                        }                                                                            ?>>
            <th scope="row" valign="top">
                <label for="apikey"><?php _e('API Key', 'paid-memberships-pro'); ?>:</label>
            </th>
            <td>
                <input type="text" id="apikey" name="apikey" value="<?php echo esc_attr($values['apikey']); ?>" class="regular-text code" />
            </td>
        </tr>
        <tr class="gateway  gateway_takbull" <?php if ($gateway != "takbull") { ?>style="display: none;" <?php } ?>>
            <th scope="row" valign="top">
                <label for="apisecret"><?php _e('API Secret', 'paid-memberships-pro'); ?>:</label>
            </th>
            <td>
                <input type="text" id="apisecret" name="apisecret" value="<?php echo esc_attr($values['apisecret']);                                                                            ?>" class="regular-text code" />
            </td>
        </tr>
        <tr class="gateway gateway_takbull" <?php if ($gateway != "takbull") { ?>style="display: none;" <?php } ?>>
            <th scope="row" valign="top">
                <label for="is_sub_date_same_as_charge"><?php _e('הגדר תאריך הוראה לאותו יום של רכישה', 'paid-memberships-pro');                                                        ?>
                    :</label>
            </th>
            <td>
                <select id="is_sub_date_same_as_charge" name="is_sub_date_same_as_charge">
                    <option value="0" <?php if (empty($values['is_sub_date_same_as_charge'])) { ?>selected="selected" <?php } ?>><?php _e('No', 'paid-memberships-pro'); ?></option>
                    <option value="1" <?php if (!empty($values['is_sub_date_same_as_charge'])) { ?>selected="selected" <?php } ?>><?php _e('Yes', 'paid-memberships-pro'); ?></option>
                </select>
            </td>
        </tr>
        <tr class="gateway gateway_takbull" <?php if ($gateway != "takbull") { ?>style="display: none;" <?php } ?>>
            <th scope="row" valign="top">
                <label for="delay_to_next_period_after_initial_payment"><?php _e('דחה את התשלום למחזור הבא אם מוגדר תשלום ראשוני', 'paid-memberships-pro'); ?>
                    :</label>
            </th>
            <td>
                <select id="delay_to_next_period_after_initial_payment" name="delay_to_next_period_after_initial_payment">
                    <option value="0" <?php if (empty($values['delay_to_next_period_after_initial_payment'])) { ?>selected="selected" <?php } ?>><?php _e('No', 'paid-memberships-pro'); ?></option>
                    <option value="1" <?php if (!empty($values['delay_to_next_period_after_initial_payment'])) { ?>selected="selected" <?php } ?>><?php _e('Yes', 'paid-memberships-pro'); ?></option>
                </select>
            </td>
        </tr>

        <tr class="gateway gateway_takbull" <?php
                                            if ($gateway != "takbull") {
                                            ?>style="display: none;" <?php
                                                                    }
                                                                        ?>>
            <th scope="row" valign="top">
                <label for="display_type"><?php
                                            _e('אופן הצגת שדות לתשלום', 'paid-memberships-pro');
                                            ?>
                    :</label>
            </th>
            <td>
                <select id="display_type" name="display_type">
                    <option value=1 <?php if ($values['display_type'] == 1) { ?>selected="selected" <?php } ?>><?php _e('Popup', 'paid-memberships-pro'); ?></option>
                    <option value=2 <?php if ($values['display_type'] == 2) { ?>selected="selected" <?php } ?>><?php _e('Redirect', 'paid-memberships-pro'); ?></option>
                </select>
            </td>
        </tr>
        <tr class="gateway gateway_takbull" <?php if ($gateway != "takbull") { ?>style="display: none;" <?php }                                                                        ?>>
            <th scope="row" valign="top">
                <label for="create_invoice"><?php _e('Create invoice', 'paid-memberships-pro'); ?>:</label>
            </th>
            <td>
                <select id="create_invoice" name="create_invoice">
                    <option value="0" <?php if (empty($values['create_invoice'])) { ?>selected="selected" <?php } ?>><?php _e('No', 'paid-memberships-pro');                                                                                                                                                                                                                                                                                                                                ?></option>
                    <option value="1" <?php if (!empty($values['create_invoice'])) { ?>selected="selected" <?php } ?>><?php _e('Yes', 'paid-memberships-pro');                                                                    ?></option>
                </select>
            </td>
        </tr>
        <tr class="gateway gateway_takbull" <?php if ($gateway != "takbull") { ?>style="display: none;" <?php } ?>>
            <th scope="row" valign="top">
                <label for="is_taxtable"><?php _e('Order include TAX', 'paid-memberships-pro'); ?> :</label>
            </th>
            <td>
                <select id="is_taxtable" name="is_taxtable">
                    <option value=0 <?php if (empty($values['is_taxtable'])) { ?> selected="selected" <?php } ?>><?php _e('No', 'paid-memberships-pro'); ?></option>
                    <option value=1 <?php if (!empty($values['is_taxtable'])) { ?>selected="selected" <?php } ?>><?php _e('Yes', 'paid-memberships-pro'); ?></option>
                </select>
            </td>
        </tr>
        <tr class="gateway  gateway_takbull" <?php if ($gateway != "takbull") { ?>style="display: none;" <?php } ?>>
            <th scope="row" valign="top">
                <label for="takbull_logging"><?php _e('Enable debuge log', 'paid-memberships-pro'); ?>:</label>
            </th>
            <td>
                <select id="takbull_logging" name="takbull_logging">
                    <option value=0 <?php if (empty($values['takbull_logging'])) { ?> selected="selected" <?php } ?>><?php _e('No', 'paid-memberships-pro'); ?></option>
                    <option value=1 <?php if (!empty($values['takbull_logging'])) { ?> selected="selected" <?php } ?>><?php _e('Yes', 'paid-memberships-pro'); ?></option>
                </select>
            </td>
        </tr>
        <script>
            //trigger the payment gateway dropdown to make sure fields show up correctly
            jQuery(document).ready(function() {
                pmpro_changeGateway(jQuery('#gateway').val());
            });
        </script>
<?php
    }

    static function pmpro_include_billing_address_fields($include)
    {
        return $include;
    }

    static function pmpro_required_billing_fields($fields)
    {
        unset($fields['bstate']);
        unset($fields['bcountry']);
        unset($fields['baddress1']);
        unset($fields['bemail']);
        unset($fields['CardType']);
        unset($fields['AccountNumber']);
        unset($fields['ExpirationMonth']);
        unset($fields['ExpirationYear']);
        unset($fields['CVV']);
        return $fields;
    }

    static function pmpro_checkout_confirmed($pmpro_confirmed)
    {
        Pmpro_Takbull_Logger::log("Takbull: pmpro_checkout_confirmed");
    }

    static function pmpro_checkout_before_change_membership_level($user_id, $morder)
    {
        global $wpdb, $discount_code_id;
        //if no order, no need to pay
        Pmpro_Takbull_Logger::log('Request pmpro_checkout_before_change_membership_level order:' . json_encode($morder));
        if (empty($morder)) return;
        // bail if the current gateway is not set to Takbull.
        if ('takbull' != $morder->gateway) {
            return;
        }
        $morder->user_id = $user_id;
        $morder->saveOrder();
        do_action("pmpro_before_send_to_takbull", $user_id, $morder);
        $display_type = pmpro_getOption("display_type");
        if ($display_type == 1) {
            return;
        }
        $endpoint =  $morder
            ->Gateway
            ->sendToTakbull($morder);
        wp_redirect($endpoint);
        exit;
    }

    function getDataToSend($order)
    {
        global $pmpro_currency;
        global $user;
        $this->create_document = pmpro_getOption("create_invoice") == 1;
        $this->is_taxtable = pmpro_getOption("is_taxtable");
        $is_sub_date_same_as_charge = pmpro_getOption("is_sub_date_same_as_charge") == 1;
        $delay_to_next_period_after_initial_payment = pmpro_getOption("delay_to_next_period_after_initial_payment") == 1;
        $display_type = pmpro_getOption("display_type");

        if (empty($order->code)) $order->code = $order->getRandomCode();

        $order->status = 'pending';
        $order->payment_transaction_id = $order->code;
        $order->subscription_transaction_id = $order->code;
        $order->saveOrder();
        if (!empty($order->FirstName) && !empty($order->LastName)) {
            $name = trim($order->FirstName . " " . $order->LastName);
        } elseif (!empty($order->FirstName)) {
            $name = $order->FirstName;
        } elseif (!empty($order->LastName)) {
            $name = $order->LastName;
        } else {
            $name = "";
        }

        if (!empty($order->Email)) {
            $email = $order->Email;
        } else {
            $email = "";
        }
        if (empty($email) && !empty($user->ID) && !empty($user->user_email)) {
            $email = $user->user_email;
        } elseif (empty($email)) {
            // $email = "No Email";
            if (isset($_REQUEST['bemail']))
                $email = sanitize_email($_REQUEST['bemail']);
            else
                $email = "";
        }
        $amount = $order->PaymentAmount;
        $amount_tax = $order->getTaxForPrice($amount);
        $amount = pmpro_round_price((float)$amount + (float)$amount_tax);
        $customer = array(
            'CustomerFullName' => $name,
            'FirstName' => $order->FirstName,
            'LastName' => $order->LastName,
            'Email' => $email,
            'PhoneNumber' => $order->billing->phone,
        );

        $level3_data = array(
            'order_reference' => $order->code,
            'IPNAddress' => esc_url_raw(add_query_arg('action', 'pmpro_takbull_ipn_handler', admin_url('admin-ajax.php'))),
            'RedirectAddress' => esc_url_raw(add_query_arg('level', $order->membership_level->id, pmpro_url("confirmation"))),
            'CancelReturnAddress' => esc_url_raw(pmpro_url("levels")),
            'Currency' => $pmpro_currency,
            'CustomerFullName' => $name,
            'Customer' => $customer,
            'OrderTotalSum' => $amount,
            'TaxAmount' => $amount_tax,
            'Language' => get_locale(),
            'DealType' => 1,
            'Comments' => $order->membership_level->name
        );

        $level3_data['InitialAmount'] = $order->InitialPayment;
        $level3_data['CreateDocument'] = $this->create_document;
        $level3_data['Taxtable'] = $this->is_taxtable == "1";
        $level3_data['DisplayType'] =  $display_type == 1 ? 'iframe' : 'redirect';
        $level3_data['PostProcessMethod'] =  $display_type == 1 ? 1 : 0;
        $level3_data['NumberOfPayments'] = $order->membership_level->billing_limit;


        $level3_data['Interval']         = $order->BillingFrequency;
        switch ($order->BillingPeriod) {
            case 'Day':
                $frequency = 1;
                $recurring_date = date('d M Y', strtotime('+' . $level3_data['Interval'] . ' day'));
                break;
            case 'Week':
                $frequency = 2;
                $recurring_date = date('d M Y', strtotime('+7 day'));
                break;
            case 'Month':
                if ($is_sub_date_same_as_charge) {
                    $level3_data['Interval']         = date('j');
                }
                $recurring_date = date('d M Y H:i', mktime(null, null, null, date('m'), $level3_data['Interval'], date('Y')));
                $frequency = 3;
                break;
            case 'Year':
                $frequency = 4;
                $recurring_date = date('d M Y', strtotime('+1 year'));
                break;
        }

        if (!empty($frequency)) {
            $regex = '/( [\x00-\x7F] | [\xC0-\xDF][\x80-\xBF] | [\xE0-\xEF][\x80-\xBF]{2} | [\xF0-\xF7][\x80-\xBF]{3} ) | ./x';
            $product_name = substr(strip_tags(preg_replace($regex, '$1', $order->membership_level->name)), 0, 200);
            $takbull_line_items = array(
                array(
                    'SKU' => (string)$order->code,
                    'Description' => $product_name,
                    'ProductName' => $product_name,
                    'Price' => $amount,
                    'Quantity' => 1,
                    'ProductType' => 1
                )
            );

            $level3_data['OrderTotalSum']  = $amount;
            $level3_data['DealType']         = 4;
            $level3_data['RecuringInterval']         = $frequency;

            while (strtotime($recurring_date) < strtotime(date("d M Y"))) {
                $recurring_date = date('d M Y H:i', strtotime($recurring_date . "+ 1 " . $order->BillingPeriod));
            }

            $level3_data['RecuringDueDate']  = $recurring_date;
            if (!empty($order->membership_level->expiration_number) && !empty($order->membership_level->expiration_period)) {
                $level3_data['SubscriptionExpirationDate'] = date("d M Y", strtotime("+ " . $order->membership_level->expiration_number . " " . $order->membership_level->expiration_period, current_time("timestamp")));
                $level3_data['IsExpireSubscription']         = true;
            }
            $already = false;
            $current_user_ID = get_current_user_id();
            if (!empty($current_user_ID) && !empty($order->membership_level->id)) {

                // Check the current user's meta.
                $alreadyUsedLeveByUser = get_user_meta(get_current_user_id(), 'pmpro_trial_level_used', true);
                $already = $alreadyUsedLeveByUser == $order->membership_level->id;
            }

            if (pmpro_isLevelTrial($order->membership_level)) {
                $level3_data['RecuringDueDate'] = date("d M Y H:i", strtotime("+ " . $order->BillingFrequency . " " . $order->BillingPeriod, current_time("timestamp")));
            }

            if (function_exists('pmprosd_getDelay')) {
                // my_function is defined
                if (!empty($order->discount_code)) {
                    global $wpdb;
                    $code_id            = $wpdb->get_var("SELECT id FROM $wpdb->pmpro_discount_codes WHERE code = '" . esc_sql($order->discount_code) . "' LIMIT 1");
                    $subscription_delay = pmprosd_getDelay($order->membership_id, $code_id);
                } else {
                    $subscription_delay = pmprosd_getDelay($order->membership_id);
                }
            }
            if (!empty($subscription_delay) && !$already) {
                $level3_data['RecuringDueDate'] =  date("d M Y H:i", strtotime("+ " . $subscription_delay . ' days', current_time("timestamp")));
                if ($is_sub_date_same_as_charge && $order->BillingPeriod == "Month") {
                    $level3_data['Interval'] =  date("j", strtotime("+ " . $subscription_delay . ' days', current_time("timestamp")));
                }
            } else {
                if ($delay_to_next_period_after_initial_payment && $order->InitialPayment > 0) {
                    $level3_data['RecuringDueDate'] = date("d M Y", strtotime("+ " . $order->BillingFrequency . " " . $order->BillingPeriod, current_time("timestamp")));
                }
            }
        } else {
            $regex = '/( [\x00-\x7F] | [\xC0-\xDF][\x80-\xBF] | [\xE0-\xEF][\x80-\xBF]{2} | [\xF0-\xF7][\x80-\xBF]{3} ) | ./x';
            // $description = substr(strip_tags(preg_replace($regex, '$1', $order->membership_level->description)), 0, 200);
            $product_name = substr(strip_tags(preg_replace($regex, '$1', $order->membership_level->name)), 0, 200);
            $takbull_line_items = array(
                array(
                    'SKU' => (string)$order->code,
                    'Description' => $product_name,
                    'ProductName' => $product_name,
                    'Price' => $order->InitialPayment,
                    'Quantity' => 1,
                )
            );
            $level3_data['OrderTotalSum']  = $order->InitialPayment;
        }
        $level3_data['Products'] = $takbull_line_items;
        return $level3_data;
    }
    function sendToTakbull(&$order)
    {
        try {
            Takbull_API::set_api_secret(pmpro_getOption("apisecret"));
            Takbull_API::set_api_key(pmpro_getOption("apikey"));
            $level3_data = $this->getDataToSend($order);
            $response = Takbull_API::request(json_encode($level3_data), "api/ExtranalAPI/GetTakbullPaymentPageRedirectUrl");
            if (!empty($response->error)) {
                throw new Exception(print_r($response, true), $response
                    ->error
                    ->message);
            }
            $body = wp_remote_retrieve_body($response);
            if ($body->responseCode != 0) {
                throw new Exception(print_r($response, true), __($body->description, 'PMPRO-gateway-takbull'));
            }
            $endpoint = Takbull_API::get_redirecr_order_api() . "?orderUniqId=" . $body->uniqId;
            return $endpoint;
            // wp_redirect($endpoint);
            // exit;
        } catch (\Throwable $th) {
            throw $th;
        }
    }

    static function pmpro_checkout_preheader()
    {
        Pmpro_Takbull_Logger::log('pmpro_checkout_preheader init');
        global $gateway, $pmpro_level, $current_user, $pmpro_requirebilling, $pmpro_pages, $pmpro_currency;
        $default_gateway = pmpro_getOption("gateway");
        $display_type = pmpro_getOption("display_type");
        if ($display_type != 1) {
            return;
        }
        if ($gateway == "takbull" || $default_gateway == "takbull") {
            wp_register_script('takbull_bootstrap', '//maxcdn.bootstrapcdn.com/bootstrap/3.3.7/js/bootstrap.min.js');
            wp_enqueue_script('takbull_bootstrap');

            // CSS
            wp_register_style('takbull_bootstrap', plugin_dir_url(__DIR__) . '/css/modal.css');
            wp_enqueue_style('takbull_bootstrap');
            Pmpro_Takbull_Logger::log('pmpro_checkout_preheader style and script init');
            if (!function_exists('pmpro_takbull_javascript')) {

                $localize_vars = array(
                    'data' => array(
                        'url' => admin_url('admin-ajax.php'),
                        'nonce' => wp_create_nonce('ajax-nonce' . pmpro_getOption("gateway")),
                        'action' => 'pmpro_takbull_get_redirect',
                        'pmpro_require_billing' => $pmpro_requirebilling,
                        'redirect_url' => Takbull_API::get_redirecr_order_api() . "?orderUniqId=",
                        'order_reference' => wp_create_nonce('takbull_order_ref' . $pmpro_level->id)
                    )
                );

                wp_register_script(
                    'pmpro_takbull',
                    plugin_dir_url(__DIR__) . '/js/pmpro-takbull.js',
                    array('jquery'),
                    PMPRO_VERSION
                );
                wp_localize_script('pmpro_takbull', 'pmproTakbullVars', $localize_vars);
                wp_enqueue_script('pmpro_takbull');
            }
        }
    }

    function process(&$order)
    {

        Pmpro_Takbull_Logger::log("process Order takbull hit: " . print_r($order, true));
        Pmpro_Takbull_Logger::log("process Order _REQUEST hit: " . print_r($_REQUEST, true));
        try {
            if (empty($order->code)) $order->code = $order->getRandomCode();
            $order->payment_type = "Takbull";
            $order->status = "pending";

            if (!empty($_REQUEST['takbull_token'])) {
                $takbull_token = $_REQUEST['takbull_token'];
            }
            $order->saveOrder();


            $display_type = pmpro_getOption("display_type");
            if (!empty($takbull_token) && $display_type == 1) {
                Takbull_API::set_api_secret(pmpro_getOption("apisecret"));
                Takbull_API::set_api_key(pmpro_getOption("apikey"));
                //is this a return call or notification
                $level3_data = $this->getDataToSend($order);
                $level3_data['CreditCard']['CardExternalToken'] = $takbull_token;
                $response = Takbull_API::request(json_encode($level3_data), "api/ExtranalAPI/ChargeToken");
                if (!empty($response->error)) {
                    return false;
                }
                $body = wp_remote_retrieve_body($response);
                if ($body->internalCode != 0) {
                    return false;
                } else {
                    Pmpro_Takbull_Logger::log("process Order _REQUEST hit: " . json_encode($body));
                    $transaction = new Pmpro_Takbull_Transaction($body->transactionInternalNumber, $order->code);
                    if (!empty($body->orderUniqId)) {
                        $uniqId = $body->orderUniqId;
                        $order->payment_transaction_id      = $uniqId;
                        $order->subscription_transaction_id = $uniqId;
                        $order->status = 'success'; // We have confirmed that and thats the reason we are here.
                        $order->saveOrder();
                    }
                    return true;
                }
            }

            return true;
        } catch (\Throwable $th) {
            Pmpro_Takbull_Logger::log('Request ex : ' . print_r($th, true));
            throw $th;
        }
    }

    static function pmpro_checkout_after_form()
    {
        $display_type = pmpro_getOption("display_type");
        if ($display_type != 1) {
            return;
        }
        echo '
        <div class="modal fade" id="takbull_payment_popup" tabindex="-1" role="dialog"  data-backdrop="false">
            <div class="modal-dialog modal-lg" role="document">
                <div class="modal-content">                  
                    <div class="modal-body">
                    <iframe id="wc_takbull_iframe" name="wc_takbull_iframe" width="100%" height="620px" style="border: 0;"></iframe>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-default" data-dismiss="modal">' . __('Close', 'takbull-gateway') . '</button>                                    
                    </div>
                </div>
             </div>
        </div>
     ';
    }


    function cancel(&$order)
    {
        global $wpdb, $gateway_environment, $logstr;
        try {
            Pmpro_Takbull_Logger::log("cancel Order takbull hit");
            if (!empty($order->payment_transaction_id)) {
                $uniqId = $wpdb->get_var("SELECT payment_transaction_id FROM $wpdb->pmpro_membership_orders WHERE payment_transaction_id = '" . $order->payment_transaction_id . "' ORDER BY id DESC LIMIT 1");
                do_action("hook_before_subscription_cancel_takbull", $order);
                Pmpro_Takbull_Logger::log("cancel Order takbull process: " . $uniqId);
                if (!empty($_POST['payment_status']) && $_POST['payment_status'] == 'CANCELLED') {
                    Pmpro_Takbull_Logger::log("cancel Order takbull already canceled: ");
                    return true;
                }
                Takbull_API::set_api_secret(pmpro_getOption("apisecret"));
                Takbull_API::set_api_key(pmpro_getOption("apikey"));
                $response = Takbull_API::get_request("api/ExtranalAPI/CancelSubscription?uniqId=" . $uniqId);
                Pmpro_Takbull_Logger::log("cancel Order takbull process: " . json_encode($response));

                if (!empty($response->error)) {
                    throw new WC_Takbull_Exception(print_r($response, true), $response->error->message);
                }
                $body = wp_remote_retrieve_body($response);
                if ($body->internalCode != 0) {
                    Pmpro_Takbull_Logger::log('Response' . print_r($response, true) . ' Description:: ' . __($body->internalDescription, 'takbull-gateway'));
                    return false;
                } else {
                    $order->updateStatus('cancelled');
                    return true;
                }
            } else {
                Pmpro_Takbull_Logger::log("cancel Order takbull no tra: ");
            }
            return false;
        } catch (Exception $e) {
            error_log($e->getMessage());
            Pmpro_Takbull_Logger::log("cancel Order Exception:" . $e->getMessage());
        }
    }

    function cancel_subscription($subscription)
    {
        global $wpdb;
        Pmpro_Takbull_Logger::log("cancel_subscription takbull hit " . print_r($subscription, true));
        $uniqId = $wpdb->get_var(
            "SELECT payment_transaction_id FROM $wpdb->pmpro_membership_orders WHERE subscription_transaction_id = '" .
                $subscription->get_subscription_transaction_id() . "' ORDER BY id DESC LIMIT 1"
        );
        do_action("hook_before_subscription_cancel_takbull", $subscription);
        Pmpro_Takbull_Logger::log("cancel Order takbull process: " . $uniqId);
        Takbull_API::set_api_secret(pmpro_getOption("apisecret"));
        Takbull_API::set_api_key(pmpro_getOption("apikey"));
        $response = Takbull_API::get_request("api/ExtranalAPI/CancelSubscription?uniqId=" . $uniqId);
        Pmpro_Takbull_Logger::log("cancel Order takbull process: " . json_encode($response));

        if (!empty($response->error)) {
            throw new WC_Takbull_Exception(print_r($response, true), $response->error->message);
        }
        $body = wp_remote_retrieve_body($response);
        if ($body->internalCode != 0) {
            Pmpro_Takbull_Logger::log('Response' . print_r($response, true) . ' Description:: ' . __($body->internalDescription, 'takbull-gateway'));
            return false;
        } else {            
            return true;
        }
        return false;
    }

    function getSubscriptionStatus(&$order)
    {
        Pmpro_Takbull_Logger::log("Takbull: getSubscriptionStatus");
    }
}
