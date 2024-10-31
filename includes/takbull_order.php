<?php



class Pmpro_Order_Takbull
{

    private static $instance = [];
    public static function getInstance()
    {
        $cls = static::class;
        if (!isset(self::$instance[$cls])) {
            self::$instance[$cls] = new static();
        }

        return self::$instance[$cls];
    }
    /**
     * Order constructor.
     */
    private function __construct()
    {
        // add_action('woocommerce_order_item_add_action_buttons', array($this,  'render_charge_button'));
        add_action('pmpro_after_order_settings_table', array($this, 'add_meta_boxes'), 10, 2);
    }

    public function add_meta_boxes($order)
    {
        Pmpro_Takbull_Logger::log("add_meta_boxes");
        $transactions_json_array = get_pmpro_membership_order_meta($order->id, PMPRO_TAKBULL_META_KEY);
        foreach ($transactions_json_array as $meta) {
            Pmpro_Takbull_Logger::log("add_meta_boxes tranlinr::".$meta);
            $transactions[] = (new Pmpro_Takbull_Transaction())->set_json_data($meta);
        }
        Pmpro_Takbull_Logger::log("add_meta_boxes trans:: " . print_r($transactions_json_array, true));

        if (empty($transactions)) {
            return;
        }
?>

        <table id="takbull-transactions" style="width: 100%;border:10px solid #456789;padding:4px;">
            <tbody>
                <th style="text-align: left;">Transaction Id</th>
                <th style="text-align: left;">Date</th>
                <th style="text-align: left;">Status</th>
                <th style="text-align: left;">Card last 4 dig</th>
                <th style="text-align: left;">Invoice</th>
                <?php foreach ($transactions as $transaction) : ?>
                    <tr>
                        <td>
                            <?php
                            echo $transaction->get_id();
                            ?>
                        </td>
                        <td>
                            <?php
                            echo $transaction->get_transactionDate();
                            ?>
                        </td>
                        <td>
                            <?php
                            echo $transaction->get_status();
                            ?>
                        </td>
                        <td>
                            <?php
                            echo $transaction->get_last4DigitsCardNumber();
                            ?>
                        </td>
                        <td>
                            <?php
                            $invoice = $transaction->get_invoiceLink();
                            if (!empty($invoice)) {
                                printf(
                                    '<a href="https://api.takbull.co.il/PublicInvoice/Invoice?InvUniqId=%2$s"  target="_blank">%1$s</a>',
                                    __('Get Invoice', 'takbull-gateway'),
                                    $invoice
                                );
                            }
                            ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
<?php
    }
    public function get_transactions($order)
    {
        $transactions = [];

        return $transactions;
    }
}


Pmpro_Order_Takbull::getInstance();
