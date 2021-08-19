<?php

require_once('api/Simpla.php');
require_once('payment/Oplati/OplatiPayment.php');

class Oplati extends Simpla
{
    public function checkout_form($order_id, $button_text = null)
    {
        $order    = $this->orders->get_order((int)$order_id);
        $settings = $this->payment->get_payment_settings($order->payment_method_id);
        $payment = new OplatiPayment($this, $order, $settings);
        try {
            $payment->create();
        } catch (Exception $e) {
            return $e->getMessage();
        }
        $paymentDetails = $payment->getDetails();
        $this->updatePaymentDetails($paymentDetails);

        return $this->getHtml($paymentDetails['dynamicQR'], $settings).PHP_EOL.$this->getJs($order_id);
    }

    public function getPaymentDetails($id)
    {
        $query = $this->db->placehold("SELECT payment_details FROM __orders WHERE id=? LIMIT 1", intval($id));
        $this->db->query($query);
        $payment_details = $this->db->result('payment_details');

        return unserialize($payment_details);
    }

    public function updatePaymentDetails($paymentDetails)
    {
        $this->orders->update_order(intval($paymentDetails['orderNumber']), array('payment_details' => serialize($paymentDetails)));
    }

    public function getHtml($dynamicQR, $settings)
    {
        $html = <<<HTML

<div id="oplati" style="text-align: center;" data-timeout="{$settings['check_status_timeout']}">
	<div class="page-title">
		<h3>Для оплаты отсканируйте QR код или нажмите на ссылку</h3>
	</div>
	<p>
		<img src="https://chart.googleapis.com/chart?cht=qr&chl={$dynamicQR}&chs={$settings['qrsize']}x{$settings['qrsize']}&choe=UTF-8&chld=L|2" alt="Оплатить через «Оплати»" style="display: inline;">
	</p>
	<p>
		<a class="btn btn-default" href="https://getapp.o-plati.by/map/?app_link={$dynamicQR}" target="_blank">Оплатить через «Оплати»</a>
	</p>
</div>

HTML;

        return $html;
    }

    protected function getJs($order_id)
    {
        $js = <<<JS
<script>
    $(document).ready(function () {
    
        let olatiObj = $('#oplati'),
            checkStatusTimeout = parseInt(olatiObj.data('timeout')) * 1000;
    
        let checkStatus = function () {
            $.ajax({
                url: '//' + location.host + '/payment/Oplati/callback.php?action=checkStatus&order_id={$order_id}',
                dataType: 'json',
                cache: false,
                success: function (resp) {
    
                    console.log(resp);
    
                    if (resp.success) {
                        if (resp.data.status === 0) {
                            setTimeout(checkStatus, checkStatusTimeout);
                        } else if (resp.data.status === 1) {
                            olatiObj.html('<p>' + resp.message + '</p>');
                            setTimeout(function () {
                                location.href = '/payment/Oplati/callback.php?action=success&order_id={$order_id}';
                            }, 3000);
                        } else {
                            olatiObj.html('<p>' + resp.message + '</p><p><button id="rePayment" class="btn btn-default">Повторить платеж</button> <a class="button2" href="/payment/Oplati/callback.php?action=cancel&order_id={$order_id}">Отменить платеж</a></p>');
                        }
                    } else {
                        console.log(resp.message);
                    }
                },
                error: function () {
                    olatiObj.html('System error');
                }
            });
        };
    
        setTimeout(checkStatus, checkStatusTimeout);
    
        $('body').on('click', 'button#rePayment', function (e) {
    
            $('body').trigger('processStart');
    
            $.ajax({
                url: '//' + location.host + '/payment/Oplati/callback.php?action=rePayment&order_id={$order_id}',
                dataType: 'json',
                cache: false,
                success: function (resp) {
    
                    $('body').trigger('processStop');
    
                    if (resp.success) {
                        olatiObj.html($(resp.data).html());
                        setTimeout(checkStatus, checkStatusTimeout);
                    } else {
                        alert('Error');
                        console.log(resp.message);
                    }
                },
                error: function () {
                    $('body').trigger('processStop');
                    olatiObj.html('System error');
                }
            });
    
        });
        
    });
</script>
JS;

        return $js;
    }

}