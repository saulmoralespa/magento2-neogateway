<?php
/**
 * Created by PhpStorm.
 * User: smp
 * Date: 4/08/18
 * Time: 06:34 PM
 */

namespace Smp\Neogateway\Model;

use MetropagoGateway\MetropagoGateway;

class Neogateway extends \Magento\Payment\Model\Method\Cc
{
    const CODE = 'neogateway';
    protected $_code = self::CODE;

    protected $_isGateway = true;
    protected $_canCapture = true;
    protected $_canCapturePartial = true;
    protected $_canRefund = true;
    protected $_canRefundInvoicePartial = true;
    protected $_countryFactory;

    protected $_minAmount = null;
    protected $_maxAmount = null;
    protected $_supportedCurrencyCodes = array('USD');

    protected $_debugReplacePrivateDataKeys = ['number', 'exp_month', 'exp_year', 'cvc'];


    protected $_neoLogger;


    public function __construct(
        \Smp\Neogateway\Logger\Logger $neoLogger,
        \Magento\Framework\Model\Context $context,
        \Magento\Framework\Registry $registry,
        \Magento\Framework\Api\ExtensionAttributesFactory $extensionFactory,
        \Magento\Framework\Api\AttributeValueFactory $customAttributeFactory,
        \Magento\Payment\Helper\Data $paymentData,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Payment\Model\Method\Logger $logger,
        \Magento\Framework\Module\ModuleListInterface $moduleList,
        \Magento\Framework\Stdlib\DateTime\TimezoneInterface $localeDate,
        \Magento\Directory\Model\CountryFactory $countryFactory,
        array $data = array()
    ) {
        parent::__construct(
            $context,
            $registry,
            $extensionFactory,
            $customAttributeFactory,
            $paymentData,
            $scopeConfig,
            $logger,
            $moduleList,
            $localeDate,
            null,
            null,
            $data
        );

        $this->_neoLogger = $neoLogger;

        $this->_countryFactory = $countryFactory;

        $this->_minAmount = $this->getConfigData('min_order_total');
        $this->_maxAmount = $this->getConfigData('max_order_total');
    }


    /**
     * Assign corresponding data
     *
     * @param \Magento\Framework\DataObject|mixed $data
     * @return $this
     * @throws \Exception
     */
    public function assignData(\Magento\Framework\DataObject $data) {

        parent::assignData($data);

        $content = (array)$data->getData();

        $info = $this->getInfoInstance();

        if (key_exists('additional_data', $content)) {

            if (key_exists('card_holder_name',$content['additional_data'])) {
                $additionalData = $content['additional_data'];

                $info->setAdditionalInformation(
                    'card_holder_name', $additionalData['card_holder_name']
                );

                $info->setCcType($additionalData['cc_type'])
                    ->setCcExpYear($additionalData['cc_exp_year'])
                    ->setCcExpMonth($additionalData['cc_exp_month'])
                    ->setCcNumber($additionalData['card_number'])
                    ->setCcCid($additionalData['cvc']);

                $info->setAdditionalInformation(
                    'cc_bin',
                    $additionalData['cc_bin']
                );
                $info->setAdditionalInformation(
                    'cc_last_4',
                    $additionalData['cc_last_4']
                );

            }else {
                $this->_logger->error(__('[Neogateway]: Card holder name not found.'));
                $this->_neoLogger->debug(__('[Neogateway]: Card holder name not found.'));
                throw new \Magento\Framework\Validator\Exception(
                    __("Payment capturing error.")
                );
            }

            return $this;
        }

        throw new \Magento\Framework\Validator\Exception(
            __("Payment capturing error.")
        );
    }

    /**
     * Payment capturing
     *
     * @param \Magento\Payment\Model\InfoInterface $payment
     * @param float $amount
     * @return $this
     * @throws \Magento\Framework\Validator\Exception
     */
    public function capture(\Magento\Payment\Model\InfoInterface $payment, $amount)
    {
            /** @var \Magento\Sales\Model\Order $order */
            $order = $payment->getOrder();

            $info = $this->getInfoInstance();

            $card_holder_name = $info->getAdditionalInformation(
                'card_holder_name'
            );

            $card_first_six_numbers = $info->getAdditionalInformation(
                'cc_bin'
            );

            $card_last_four_numbers = $info->getAdditionalInformation(
                'cc_last_4'
            );

            $month = sprintf('%02d',$payment->getCcExpMonth());
            $year =  substr($payment->getCcExpYear(), -2);
            $expireDate = "$month$year";

            $data_card_check = ['first_six_numbers' => $card_first_six_numbers, 'last_four_numbers' => $card_last_four_numbers];

            try {

                $CustManager  = new \MetropagoGateway\CustomerManager($this->instancePayment());
                $TrxManager  = new \MetropagoGateway\TransactionManager($this->instancePayment());


                $customerFilters = new \MetropagoGateway\CustomerSearch();
                $customerSearchOptions = new \MetropagoGateway\CustomerSearchOption();

                $customerFilters->Email = new \MetropagoGateway\TextFilter();
                $customerFilters->Email->Is($order->getCustomerEmail());
                $customerSearchOptions->IncludeCardInstruments=true;
                $customerFilters->SearchOption = $customerSearchOptions;

                $response_customer = $CustManager->SearchCustomer($customerFilters);


                $data_card = ['expire' => $expireDate, 'order_id' => $order->getIncrementId()];
                $info_user = ['card_holder_name' => $card_holder_name, 'expire' => $expireDate];


                if(!empty($response_customer)){

                    $data_card['id'] = $response_customer[0]->CustomerId;
                    $uniqueIdentifier = $response_customer[0]->UniqueIdentifier;
                    $info_user['id'] = $uniqueIdentifier;

                    //search ig register card
                    $cards = (array)$response_customer[0]->CreditCards;
                    $token = $this->checkIfCardRegister($cards, $data_card_check);

                    if (!is_null($token)) {
                        $data_card['token'] = $token;
                        $transRequest = $this->saleRequest($data_card, $amount);
                    }else{

                        $customerAdd = $this->addingCardCustomerModel($info_user, $order, $payment);

                        $cardResponse = $CustManager->UpdateCustomer($customerAdd);

                        $data_card['token'] = $cardResponse[0]->Token;

                        $transRequest = $this->saleRequest($data_card, $amount);

                    }

                }else{
                    $customer = $this->createCustomer($order);
                    $customerRe = $CustManager->AddCustomer($customer);

                    // $customerRe->ResponseDetails->IsSuccess === true

                    $info_user['id'] = $customerRe->UniqueIdentifier;
                    $data_card['id'] = $customerRe->ResponseDetails->CustomerId;

                    $customerAdd = $this->addingCardCustomerModel($info_user, $order, $payment);

                    $cardResponse = $CustManager->UpdateCustomer($customerAdd);

                    $data_card['token'] = $cardResponse->CreditCards[0]->Token;

                    $transRequest = $this->saleRequest($data_card, $amount);
                }

                //execute payment Perform Sale
                $sale_response = $TrxManager->Sale($transRequest);

                $payment->setTransactionId($sale_response->ResponseDetails->TransactionId)
                    ->setIsTransactionClosed(0);

            } catch (\Exception $e) {
                $this->debugData(['request' => 'capture paymente neogateway', 'exception' => $e->getMessage()]);
                $this->_neoLogger->debug($e->getMessage());
                $this->_logger->error(__('Payment capturing error.'));
                throw new \Magento\Framework\Validator\Exception(__('Payment capturing error.'));
            }

            return $this;
    }


    public function instancePayment()
    {
        $merchant_id = $this->getConfigData('merchant_id');
        $terminal_id = $this->getConfigData('terminal_id');
        $test = (bool)$this->getConfigData('test');

        $Gateway =  new MetropagoGateway($test,$merchant_id,$terminal_id,"","");

        return $Gateway;


    }

    public function checkIfCardRegister($cards, $data_card)
    {
        if (empty($cards))
            return null;

        $card_token = null;

        foreach ($cards as $card) {
            if (strpos($card->Number, $data_card['first_six_numbers']) !== false  && strpos($card->Number, $data_card['last_four_numbers']) !== false) {
                $card_token = $card->Token;
                break;
            }
        }

        return $card_token;
    }


    public function createCustomer($order)
    {

        $address = $this->getAddress($order);
        $addresLine1 = $address->getData("street");
        $addresLine2 = empty($address->getStreetLine(2)) ? $addresLine1 : $address->getStreetLine(2);
        $city = $address->getCity();
        $country = $address->getCountryId();
        $state = $address->getRegionCode();
        $zipCode = $address->getPostcode();

        $customer = new \MetropagoGateway\Customer();

        $customer->Company = empty($address->getCompany()) ? $address->getName() : $address->getCompany();
        $customer->Email = $address->getCustomerEmail();
        $customer->FirstName = $address->getName();
        $customer->LastName = $address->getLastname();
        $customer->Phone = $address->getTelephone();
        $customer->UniqueIdentifier = mt_rand() . $address->getCustomerId();

        $customer->ShippingAddress = array();
        $ShippingAddress = new \MetropagoGateway\Address();
        $ShippingAddress->AddressLine1 = $addresLine1;
        $ShippingAddress->AddressLine2 = $addresLine2;
        $ShippingAddress->City = $city;
        $ShippingAddress->CountryName = $country;
        $ShippingAddress->State = $state;
        $ShippingAddress->ZipCode = $zipCode;
        $customer->ShippingAddress[] =$ShippingAddress;

        $customer->BillingAddress = array();
        $BillingAddress = new \MetropagoGateway\Address();
        $BillingAddress->AddressLine1 = $addresLine1;
        $BillingAddress->AddressLine2 = $addresLine2;
        $BillingAddress->City = $address->getCity();
        $BillingAddress->CountryName = $address->getCountryId();
        $BillingAddress->State = $address->getRegionCode();
        $BillingAddress->ZipCode = $address->getPostcode();
        $customer->BillingAddress[] = $BillingAddress;
        return $customer;

    }

    public function addingCardCustomerModel( $data_user, $order, $payment)
    {

        $customer  = new \MetropagoGateway\Customer();
        $customer->CreditCards = array();

        $customer->UniqueIdentifier = $data_user['id'];

        $card = new \MetropagoGateway\CreditCard();

        $card->CardholderName = $data_user['card_holder_name'];
        $card->Status = "Active";
        $card->ExpirationDate = $data_user['expire'];
        $card->Number = $payment->getCcNumber();
        $customer->CreditCards[] = $card;

        return $customer;
    }


    public function saleRequest($data, $amount)
    {
        $transRequest = new \MetropagoGateway\Transaction();
        $transRequest->CustomerData = new \MetropagoGateway\Customer();
        $transRequest->CustomerData->CustomerId = $data['id'];
        $transRequest->CustomerData->CreditCards =array();
        $card = new \MetropagoGateway\CreditCard();
        $card->ExpirationDate = $data['expire'];
        $card->Token = $data['token'];
        $transRequest->CustomerData->CreditCards[] = $card;
        $transRequest->Amount = "$amount";
        $transRequest->OrderTrackingNumber = $data['order_id'];

        return $transRequest;
    }

    /**
     * Payment refund
     *
     * @param \Magento\Payment\Model\InfoInterface $payment
     * @param float $amount
     * @return $this
     * @throws \Magento\Framework\Validator\Exception
     */
    public function refund(\Magento\Payment\Model\InfoInterface $payment, $amount)
    {
        $transactionId = $payment->getParentTransactionId();
        try {

            $rtransRequest = new \MetropagoGateway\Transaction();
            $rtransRequest->TransactionId = $transactionId;
            $rtransRequest->Amount = "$amount";
            $TrxManager  = new \MetropagoGateway\TransactionManager($this->instancePayment());
            $refund_response = $TrxManager->Refund($rtransRequest);

        } catch (\Exception $e) {
            $this->debugData(['transaction_id' => $transactionId, 'exception' => $e->getMessage()]);
            $this->_logger->error(__('Payment refunding error.'));
            throw new \Magento\Framework\Validator\Exception(__('Payment refunding error.'));
        }
        $payment
            ->setTransactionId($transactionId . '-' . \Magento\Sales\Model\Order\Payment\Transaction::TYPE_REFUND)
            ->setParentTransactionId($transactionId)
            ->setIsTransactionClosed(1)
            ->setShouldCloseParentTransaction(1);
        return $this;
    }


    /**
     * Determine method availability based on quote amount and config data
     *
     * @param \Magento\Quote\Api\Data\CartInterface|null $quote
     * @return bool
     */
    public function isAvailable(\Magento\Quote\Api\Data\CartInterface $quote = null)
    {
        if ($quote && (
                $quote->getBaseGrandTotal() < $this->_minAmount
                || ($this->_maxAmount && $quote->getBaseGrandTotal() > $this->_maxAmount))
        ) {
            return false;
        }
        if (!$this->getConfigData('merchant_id') || !$this->getConfigData('terminal_id')) {
            return false;
        }
        return parent::isAvailable($quote);
    }


    public function canUseForCurrency($currencyCode)
    {
        if (!in_array($currencyCode, $this->_supportedCurrencyCodes)) {
            return false;
        }
        return true;
    }

    public function getAddress($order)
    {
        $billingAddress = $order->getBillingAddress();
        $shippingAddress = $order->getShippingAddress();
        if ($billingAddress){
            return $billingAddress;
        }
        return $shippingAddress;
    }
}
