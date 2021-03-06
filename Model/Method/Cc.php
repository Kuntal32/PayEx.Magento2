<?php

namespace PayEx\Payments\Model\Method;

use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Model\Order;

/**
 * Class Cc
 * @package PayEx\Payments\Model\Method
 */
class Cc extends \PayEx\Payments\Model\Method\AbstractMethod
{

    const METHOD_CODE = 'payex_cc';

    /**
     * Payment code
     *
     * @var string
     */
    protected $_code = self::METHOD_CODE;

    /**
     * @var string
     */
    //protected $_formBlockType = 'PayEx\Payments\Block\Form\Cc';
    protected $_infoBlockType = 'PayEx\Payments\Block\Info\Cc';

    /**
     * Payment Method feature
     *
     * @var bool
     */
    protected $_canAuthorize = true;
    protected $_canCapture = true;
    protected $_canCapturePartial = true;
    protected $_canCaptureOnce = true;
    protected $_canRefund = true;
    protected $_canRefundInvoicePartial = true;
    protected $_isGateway = true;
    protected $_isInitializeNeeded = true;
    protected $_canVoid = true;
    protected $_canUseInternal = false;
    protected $_canFetchTransactionInfo = true;

    /**
     * Method that will be executed instead of authorize or capture
     * if flag isInitializeNeeded set to true
     *
     * @param string $paymentAction
     * @param object $stateObject
     *
     * @return $this
     * @throws LocalizedException
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     * @api
     */
    public function initialize($paymentAction, $stateObject)
    {
        $this->payexLogger->info('initialize');

        /** @var \Magento\Quote\Model\Quote\Payment $info */
        $info = $this->getInfoInstance();

        /** @var \Magento\Sales\Model\Order $order */
        $order = $info->getOrder();

        $order_id = $order->getIncrementId();

        // Get Currency code
        $currency_code = $order->getOrderCurrency()->getCurrencyCode();

        // Get Operation Type (AUTHORIZATION / SALE)
        $operation = $this->getConfigData('transactiontype');

        // Get Payment Type (PX, CREDITCARD etc)
        $payment_type = $this->getConfigData('payment_type');

        // Get Additional Values
        $additional = ($payment_type === 'PX' ? 'PAYMENTMENU=TRUE' : '');

        // Direct Debit uses 'SALE' only
        if ($payment_type === 'DIRECTDEBIT') {
            $operation = 'SALE';
        }

        // Responsive Skinning
        if ($this->getConfigData('responsive') === '1') {
            $separator = (!empty($additional) && mb_substr($additional, -1) !== '&') ? '&' : '';

            // PayEx Payment Page 2.0  works only for View 'Credit Card' and 'Direct Debit' at the moment
            if (in_array($payment_type, ['CREDITCARD', 'DIRECTDEBIT'])) {
                $additional .= $separator . 'RESPONSIVE=1';
            } else {
                $additional .= $separator . 'USECSS=RESPONSIVEDESIGN';
            }
        }

        // Language
        $language = $this->getConfigData('language');
        if (empty($language)) {
            $language = $this->payexHelper->getLanguage();
        }

        // Get Amount
        //$amount = $order->getGrandTotal();
        $items = $this->payexHelper->getOrderItems($order);
        $amount = array_sum(array_column($items, 'price_with_tax'));

        // Call PxOrder.Initialize8
        $params = [
            'accountNumber' => '',
            'purchaseOperation' => $operation,
            'price' => round($amount * 100),
            'priceArgList' => '',
            'currency' => $currency_code,
            'vat' => 0,
            'orderID' => $order_id,
            'productNumber' => $order_id,
            'description' => $this->payexHelper->getStore()->getName(),
            'clientIPAddress' => $this->payexHelper->getRemoteAddr(),
            'clientIdentifier' => 'USERAGENT=' . $this->request->getServer('HTTP_USER_AGENT'),
            'additionalValues' => $additional,
            'externalID' => '',
            'returnUrl' => $this->urlBuilder->getUrl('payex/cc/success', [
                '_secure' => $this->request->isSecure()
            ]),
            'view' => $payment_type,
            'agreementRef' => '',
            'cancelUrl' => $this->urlBuilder->getUrl('payex/cc/cancel', [
                '_secure' => $this->request->isSecure()
            ]),
            'clientLanguage' => $language
        ];
        $result = $this->payexHelper->getPx()->Initialize8($params);
        $this->payexLogger->info('PxOrder.Initialize8', $result);

        // Check Errors
        if ($result['code'] !== 'OK' || $result['description'] !== 'OK' || $result['errorCode'] !== 'OK') {
            $message = $this->payexHelper->getVerboseErrorMessage($result);
            throw new LocalizedException(__($message));
        }

        $order_ref = $result['orderRef'];
        $redirectUrl = $result['redirectUrl'];

        // Add Order Info
        if ($this->getConfigData('checkoutinfo')) {
            // Add Order Items
            foreach ($items as $index => $item) {
                // Call PxOrder.AddSingleOrderLine2
                $params = [
                    'accountNumber' => '',
                    'orderRef' => $order_ref,
                    'itemNumber' => ($index + 1),
                    'itemDescription1' => $item['name'],
                    'itemDescription2' => '',
                    'itemDescription3' => '',
                    'itemDescription4' => '',
                    'itemDescription5' => '',
                    'quantity' => $item['qty'],
                    'amount' => (int)(100 * $item['price_with_tax']), //must include tax
                    'vatPrice' => (int)(100 * $item['tax_price']),
                    'vatPercent' => (int)(100 * $item['tax_percent'])
                ];

                $result = $this->payexHelper->getPx()->AddSingleOrderLine2($params);
                $this->payexLogger->info('PxOrder.AddSingleOrderLine2', $result);
            }

            // Add Order Address Info
            $params = array_merge([
                'accountNumber' => '',
                'orderRef' => $order_ref
            ], $this->payexHelper->getAddressInfo($order));

            $result = $this->payexHelper->getPx()->AddOrderAddress2($params);
            $this->payexLogger->info('PxOrder.AddOrderAddress2', $result);
        }

        // Set Pending Payment status
        $order->setCanSendNewEmailFlag(false);
        $order->addStatusHistoryComment(__('The customer was redirected to PayEx.'), Order::STATE_PENDING_PAYMENT);
        $order->save();

        // Set state object
        /** @var \Magento\Sales\Model\Order\Status $status */
        $status = $this->payexHelper->getAssignedState(Order::STATE_PENDING_PAYMENT);
        $stateObject->setState($status->getState());
        $stateObject->setStatus($status->getStatus());
        $stateObject->setIsNotified(false);

        // Save Redirect URL in Session
        $this->checkoutHelper->getCheckout()->setPayexRedirectUrl($redirectUrl);

        return $this;
    }
}
