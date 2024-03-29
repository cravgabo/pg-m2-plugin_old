<?php

namespace Paymentez\PaymentGateway\Gateway\Http\Client;

use Magento\Sales\Model\Order\Payment;
use Paymentez\Paymentez;
use Paymentez\PaymentGateway\Gateway\Config\CardConfig;
use Paymentez\PaymentGateway\Gateway\Config\GatewayConfig;

class RefundClient extends AbstractClient
{
    /**
     * RefundClient constructor.
     * @param Paymentez $adapter
     * @param GatewayConfig $gateway_config
     * @param CardConfig $config
     */
    public function __construct(Paymentez $adapter, GatewayConfig $gateway_config, CardConfig $config)
    {
        parent::__construct($adapter, $gateway_config);
        $this->config = $config;
    }

    /**
     * @inheritDoc
     * @throws \Exception
     */
    protected function process(array $request_body)
    {
        $is_production = $this->config->isProduction();
        $credentials = $this->config->getServerCredentials();

        $this->adapter->init($credentials['application_code'], $credentials['application_key'], $is_production);

        $charge = $this->adapter::charge();
        /** @var Payment $payment */
        $extra_data = $request_body['extra_data'];
        $payment = $request_body['objects']['payment'];
        $order_obj = $request_body['objects']['order'];

        $amount = isset($extra_data['additional_amount']) ? $extra_data['additional_amount'] : $request_body['order']['amount'];

        if ($payment->getAdditionalInformation('status_detail') == '1') {
            $user = [
                'id' => $request_body['user']['id']
            ];
            $this->logger->debug('RefundClient.process Use verify for review transactions...');
            $response = $charge->verify('BY_AMOUNT', (string)$request_body['order']['amount'], $payment->getParentTransactionId(), $user, true);
            $response = json_decode(json_encode($response), true);
            $this->logger->debug('RefundClient.process Verify response => ', $response);
            if (isset($response['transaction']['status']) && $response['transaction']['status'] == 'failure') {
                $response['status'] = 'success';
                return (array)$response;
            }
        }

        $this->logger->debug('RefundClient.process Consuming Refund...');
        $response = $charge->refund($payment->getParentTransactionId(), $amount, true);

        return (array)$response;
    }
}
