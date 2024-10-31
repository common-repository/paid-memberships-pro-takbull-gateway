<?php
defined('ABSPATH') || exit; // Exit if accessed directly
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