<?php

chdir('../../');

require_once('payment/Oplati/Oplati.php');

$oplati = new Oplati();

$action   = $oplati->request->get('action');
$order_id = $oplati->request->get('order_id', 'integer');

$return = array(
    'success' => true,
    'data'    => array(),
    'message' => ''
);

try {

    if (empty($action)) {
        throw new Exception('Empty action!');
    }

    if (empty($order_id)) {
        throw new Exception('Empty order Id!');
    }

    if ( ! ($order = $oplati->orders->get_order(intval($order_id)))) {
        throw new Exception('Order not found!');
    }

    if ($action === 'success') {
        $oplati->orders->update_order(intval($order->id), array('paid' => 1));
        $oplati->notify->email_order_user(intval($order->id));
        $oplati->notify->email_order_admin(intval($order->id));
        $oplati->orders->close(intval($order->id));
        header("Location: ".$oplati->config->root_url);
    }

    if ($action === 'cancel') {
        $oplati->orders->update_order(intval($order->id), array('paid' => 0));
        header("Location: ".$oplati->config->root_url);
    }

    $settings = $oplati->payment->get_payment_settings($order->payment_method_id);

    require_once('payment/Oplati/OplatiPayment.php');

    $payment = new OplatiPayment($oplati, $order, $settings);

    if ($action === 'checkStatus') {
        $paymentDetails           = $oplati->getPaymentDetails($order->id);
        $return['data']['status'] = $payment->getStatus($paymentDetails['paymentId']);
        $return['message']        = $payment->getStatusMessage($return['data']['status']);
        $oplati->updatePaymentDetails($payment->getDetails());
    }

    if ($action === 'rePayment') {
        $payment->create();
        $paymentDetails = $payment->getDetails();
        $return['data'] = $oplati->getHtml($paymentDetails['dynamicQR'], $settings);
        $oplati->updatePaymentDetails($paymentDetails);

    }

} catch (Exception $e) {
    $return['success'] = false;
    $return['message'] = $e->getMessage();
}

echo json_encode($return);

exit();