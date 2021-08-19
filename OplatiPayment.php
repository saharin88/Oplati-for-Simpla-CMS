<?php

class OplatiPayment
{
    /**
     * @var stdClass
     */
    protected $order;

    /**
     * @var array
     */
    protected $settings;

    /**
     * @var Simpla
     */
    protected $simpla;

    /**
     * @var array
     */
    protected $details;

    public function __construct(Simpla $simpla, stdClass $order, array $settings)
    {
        $this->simpla   = $simpla;
        $this->order    = $order;
        $this->settings = $settings;
    }

    public function create()
    {
        $this->details = $this->request('pos/webPayments', $this->prepareData());
        if (empty($this->details['paymentId'])) {
            throw new Exception((empty($this->details['devMessage']) ? 'Error create payment' : $this->details['devMessage']));
        }
    }

    public function getStatus($paymentId)
    {
        $this->details = $this->request('pos/payments/'.$paymentId);
        if (isset($this->details['status']) === false) {
            throw new Exception((empty($this->details['devMessage']) ? 'Error get payment status' : $this->details['devMessage']));
        }

        return $this->details['status'];
    }

    public function getDetails()
    {
        return $this->details;
    }

    public function getStatusMessage($status)
    {
        if ($status === 0) {
            $message = 'Платеж ожидает подтверждения.';
        } else {
            if ($status === 1) {
                $message = 'Платеж совершен.';
            } else {
                if ($status === 2) {
                    $message = 'Отказ от платежа.';
                } else {
                    if ($status === 3) {
                        $message = 'Недостаточно средств.';
                    } else {
                        if ($status === 4) {
                            $message = 'Время для осуществления платежа вышло.';
                        } else {
                            if ($status === 5) {
                                $message = 'Техническая отмена.';
                            } else {
                                $message = 'Код статуса не поддержываэтся.';
                            }
                        }
                    }
                }
            }
        }

        return $message;
    }

    protected function prepareData()
    {
        $data = array(
            'sum'         => $this->order->total_price,
            'shift'       => 'smena 1',
            'orderNumber' => $this->order->id,
            'regNum'      => $this->settings['regnum'],
            'details'     => [
                'amountTotal' => $this->order->total_price,
                'items'       => []
            ],
            'successUrl'  => '',
            'failureUrl'  => ''
        );

        $items = $this->simpla->orders->get_purchases(array('order_id' => $this->order->id));

        foreach ($items as $item) {
            $data['details']['items'][] = [
                'type'     => 1,
                'name'     => $item->product_name,
                'price'    => (float)$item->price,
                'quantity' => (int)$item->amount,
                'cost'     => ((float)$item->price * (int)$item->amount),
            ];
        }

        $delivery = $this->simpla->delivery->get_delivery($this->order->delivery_id);

        if (empty($delivery->separate_payment)) {

            $data['details']['items'][] = [
                'type' => 2,
                'name' => $delivery->name,
                'cost' => (float)$this->order->delivery_price,
            ];
        }

        return $data;
    }


    protected function request($url, $data = array(), $requestMethod = 'GET')
    {

        $headers = [
            'Content-Type: application/json; charset=UTF-8',
            'regNum: '.$this->settings['regnum'],
            'password: '.$this->settings['password'],
        ];

        $ch = curl_init($this->getServer().$url);
        if ( ! empty($data) || $requestMethod === 'POST') {
            $data = json_encode($data);
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
            $headers[] = 'Content-Length: '.strlen($data);
        }

        if (in_array($requestMethod, ['HEAD', 'PUT', 'DELETE', 'PATCH', 'TRACE', 'CONNECT', 'OPTIONS'])) {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $requestMethod);
        }

        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        curl_setopt($ch, CURLOPT_HEADER, 1);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt(
            $ch, CURLOPT_HTTPHEADER, $headers
        );

        $response = curl_exec($ch);

        $time        = curl_getinfo($ch, CURLINFO_TOTAL_TIME);
        $httpcode    = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $header      = substr($response, 0, $header_size);
        $body        = substr($response, $header_size);

        if (curl_errno($ch)) {
            $body = json_encode([curl_error($ch)]);
        }

        curl_close($ch);


        if (isset($httpcode) && $httpcode >= 200 && $httpcode < 300) {
            return json_decode($body, true);
        } else {
            //TODO log error message;
            throw new Exception('Bad response.');
        }
    }


    protected function getServer()
    {
        if ($this->settings['test'] === '1') {
            return 'https://bpay-testcashdesk.lwo.by/ms-pay/';
        } else {
            return 'https://cashboxapi.o-plati.by/ms-pay/';
        }
    }

}