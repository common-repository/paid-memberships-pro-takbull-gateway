<?php

/**
 * Class Transaction
 */
class Pmpro_Takbull_Transaction
{

    protected $data = array(
        'order_id' => 0,
        'transactionInternalNumber' => '',
        'status'     => '',
        'statusCode'     => 0,
        'statusDescription'     => '',
        'last4DigitsCardNumber'     => '',
        'invoiceId'     => 0,
        'numberOfPayments'     => 0,
        'cardtype'     => '',
        'cardCompanyTtype'     => '',
        'clearer'     => '',
        'dealType'     => 0,
        'invoiceLink'     => '',
        'isDocumentCreated'     => false,
        'transactionDate'     => '',
        'transactionType' => 0,
        'amount' => 0
    );

    /**
     * This is false until the object is read from the DB.
     *
     * @since 3.0.0
     * @var bool
     */
    protected $object_read = false;

    /**
     * Transaction constructor.
     *
     * @param string $transaction_id
     */
    public function __construct($transactionInternalNumber = '', $order_id = 0)
    {
        $this->set_order_id($order_id);
        if (!empty($transactionInternalNumber)) {
            $this->set_id($transactionInternalNumber);
            $this->get_transaction();
        }
    }

    public function set_id($id)
    {
        $this->id =  $id;
    }

    public function get_transaction()
    {
        $response = Takbull_API::get_request("api/ExtranalAPI/GetTransaction?transactionInternalNumber=" . $this->get_id());
        if (is_wp_error($response) || empty($response['body'])) {
            // Pmpro_Takbull_Logger::log(print_r($response, true), __('There was a problem connecting to the Takbull API endpoint.', 'takbull-gateway'));
            //add order not that transaction get failed
        }
        $body = $response['body'];
        // Pmpro_Takbull_Logger::log('GetTransaction Response: ' . json_encode($body));
        $this->set_data($body);
        $this->save();
    }
    /**
     * Returns the unique ID for this object.
     *
     * @since  2.6.0
     * @return int
     */
    public function get_id()
    {
        return $this->id;
    }
    public function save()
    {
        global  $wpdb;
        $order = $this->get_order();
        if (!$order) {
            return;
        }
        $transactionNumber = $this->get_id();
        $updated = false;
        $transactions = get_pmpro_membership_order_meta($order->id, PMPRO_TAKBULL_META_KEY);
        if ($transactions) {
            foreach ($transactions as $meta) {
                if ($updated) {
                    break;
                }
                if (json_decode($meta)->id ==  $transactionNumber) {
                    Pmpro_Takbull_Logger::log('transactionNumber update meta::: ' . $transactionNumber);
                    // update_pmpro_membership_order_meta($order->id, PMPRO_TAKBULL_META_KEY, (string)$this);
                    $updated = true;
                }
            }
        }
        if ($updated == false) {
            Pmpro_Takbull_Logger::log('add_pmpro_membership_order_meta update meta::: ' . $transactionNumber);
            do_action("takbull_new_transaction", $this, $order);
            add_pmpro_membership_order_meta($order->id, PMPRO_TAKBULL_META_KEY, (string)$this);
        }
    }

    /**
     * Change data to JSON format.
     *
     * @since  2.6.0
     * @return string Data in JSON format.
     */
    public function __toString()
    {
        return wp_json_encode($this->get_data());
    }
    /**
     * Returns all data for this object.
     *
     * @since  2.6.0
     * @return array
     */
    public function get_data()
    {
        return array_merge(array('id' => $this->get_id()), $this->data);
    }

    public function set_data($data)
    {
        if ($data->statusCode != 0) {
            $this->set_props([
                'status'     => $data->status,
                'statusCode'     => $data->statusCode,
                'statusDescription'     => $data->statusCode == 0 ? '' : $data->statusDescription,
                'dealType'     => $data->dealType,
                'amount'     => $data->amount,
                'transactionDate' => $data->transactionDate,
            ]);
        } else {
            $this->set_props([
                'status'     => $data->status,
                'statusCode'     => $data->statusCode,
                'statusDescription'     => $data->statusCode == 0 ? '' : $data->statusDescription,
                'last4DigitsCardNumber'     => $data->last4DigitsCardNumber,
                'numberOfPayments'     => $data->numberOfPayments,
                'cardtype'     => $data->cardtype,
                // 'clearer'     => $data->clearer,
                'cardCompanyTtype'     => $data->cardCompanyTtype,
                'dealType'     => $data->dealType,
                'amount'     => $data->amount,
                'transactionDate' => $data->transactionDate,
                'invoiceLink' => !empty($data->invoiceUniqId) ? $data->invoiceUniqId : ''
            ]);
        }
        return $this;
    }


    public function set_props($props, $context = 'set')
    {
        $errors = false;
        foreach ($props as $prop => $value) {
            try {
                /**
                 * Checks if the prop being set is allowed, and the value is not null.
                 */
                if (is_null($value) || in_array($prop, array('prop', 'date_prop', 'meta_data'), true)) {
                    continue;
                }
                $setter = "set_$prop";

                if (is_callable(array($this, $setter))) {
                    $this->{$setter}($value);
                }
            } catch (Exception $e) {
                if (!$errors) {
                    $errors = new WP_Error();
                }
                $errors->add(99900101, $e->getMessage());
            }
        }
        return $errors && count($errors->get_error_codes()) ? $errors : true;
    }

    protected function get_prop($prop, $context = 'view')
    {
        $value = null;

        if (array_key_exists($prop, $this->data)) {
            $value = $this->data[$prop];
        }

        return $value;
    }

    protected function set_prop($prop, $value)
    {
        if (array_key_exists($prop, $this->data)) {

            $this->data[$prop] = $value;
        }
    }


    /*
	|--------------------------------------------------------------------------
	| Getters
	|--------------------------------------------------------------------------
	*/
    public function get_isDocumentCreated($context = 'view')
    {
        return $this->get_prop('isDocumentCreated', $context);
    }
    public function get_invoiceLink($context = 'view')
    {
        return $this->get_prop('invoiceLink', $context);
    }
    public function get_last4DigitsCardNumber($context = 'view')
    {
        return $this->get_prop('last4DigitsCardNumber', $context);
    }
    public function get_transactionDate($context = 'view')
    {
        return $this->get_prop('transactionDate', $context);
    }
    public function get_transactionType($context = 'view')
    {
        return $this->get_prop('transactionType', $context);
    }

    public function get_order_id($context = 'view')
    {
        return $this->get_prop('order_id', $context);
    }
    public function get_amount($context = 'view')
    {
        return $this->get_prop('amount', $context);
    }

    public function get_status($context = 'view')
    {
        $status = intval($this->get_prop('status', $context));
        switch ($status) {
            case 0:
                return "לא ידוע";
            case 1:
                return "מאושר";
            case 2:
                return "סירוב";
            case 3:
                return "זיכוי חלקי";
            case 4:
                return "ממתין";
            case 5:
                return "זיכוי";
            case 6:
                return "נכשל";
            default:
                return '????';
        }
    }
    public function get_statusCode($context = 'view')
    {
        return $this->get_prop('statusCode', $context);
    }
    public function get_description($context = 'view')
    {
        return $this->get_prop('description', $context);
    }

    public function get_order()
    {
        $order_id = $this->get_order_id();

        return new MemberOrder($order_id);
    }

    /*
	|--------------------------------------------------------------------------
	| Setters
	|--------------------------------------------------------------------------
	*/
    public function set_invoiceLink($invoiceLink)
    {
        if (!empty($invoiceLink))
            $this->set_prop('invoiceLink', $invoiceLink);
        return $this;
    }

    public function set_order_id($order_id)
    {
        return $this->set_prop('order_id', $order_id);
    }

    public function set_transactionDate($transactionDate)
    {
        return $this->set_prop('transactionDate', $transactionDate);
    }

    public function set_status($status)
    {
        return $this->set_prop('status', $status);
    }

    public function set_statusCode($status)
    {
        return $this->set_prop('statusCode', $status);
    }

    public function set_transactionType($transactionType)
    {
        return $this->set_prop('transactionType', $transactionType);
    }

    public function set_statusDescription($statusDescription)
    {
        return $this->set_prop('statusDescription', $statusDescription);
    }

    public function set_last4DigitsCardNumber($last4DigitsCardNumber)
    {
        return $this->set_prop('last4DigitsCardNumber', $last4DigitsCardNumber);
    }

    public function set_numberOfPayments($numberOfPayments)
    {
        return $this->set_prop('numberOfPayments', $numberOfPayments);
    }

    public function set_cardtype($cardtype)
    {
        return $this->set_prop('cardtype', $cardtype);
    }


    public function set_cardCompanyTtype($cardCompanyTtype)
    {
        return $this->set_prop('cardCompanyTtype', $cardCompanyTtype);
    }


    public function set_clearer($clearer)
    {
        return $this->set_prop('clearer', $clearer);
    }


    public function set_dealType($dealType)
    {
        return $this->set_prop('dealType', $dealType);
    }


    public function set_isDocumentCreated($isDocumentCreated)
    {
        return $this->set_prop('isDocumentCreated', $isDocumentCreated);
    }
    public function set_amount($amount)
    {
        return $this->set_prop('amount', $amount);
    }

    public function set_json_data($data)
    {
        if (is_string($data)) {
            $data = json_decode($data, true);
        }
        $this->set_props($data);
        return $this;
    }
}
