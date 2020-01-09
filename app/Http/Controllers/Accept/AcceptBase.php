<?php

namespace App\Http\Controllers\Accept;

use Illuminate\Http\Request;
use App\Service\ShopifyApiService;
use App\MerchantSettings;
use App\Service\EasyApiService;
use App\PaymentDetails;

/**
 * Description of AcceptBase
 *
 * @author mabe
 */
class AcceptBase extends \App\Http\Controllers\Controller {

    /**
     * @var Request
     */
    private $request;

    /**
     * @var \App\Exceptions\EasyApiExceptionHandler
     */
    private $eh;

    /**
     * @var \App\ShopifyReturnParams
     */
    private $shopifyReturnParams;

    protected $easyApiService;

    protected $shopifyApiService;

    private $exHandler;

    private $logger;

    public function __construct(EasyApiService $easyApiService, 
                                ShopifyApiService $shopifyApiService, 
                                Request $request,
                                \App\Exceptions\EasyApiExceptionHandler $eh,
                                \App\Exceptions\Handler $exHandler,
                                \Illuminate\Log\Logger $logger,
                                \App\ShopifyReturnParams $shopifyReturnParams) {
        $this->easyApiService = $easyApiService;
        $this->shopifyApiService = $shopifyApiService;
        $this->shopifyReturnParams = $shopifyReturnParams;
        $this->eh = $eh;
        $this->request = $request;
        $this->exHandler = $exHandler;
        $this->logger = $logger;
    }

    protected function handle() {
        try{
            $keyField = static::KEY;
            $settingsCollection = MerchantSettings::getSettingsByShopOrigin($this->request->get('origin'));
            $this->easyApiService->setAuthorizationKey(ShopifyApiService::decryptKey($settingsCollection->first()->$keyField));
            $this->easyApiService->setEnv(static::ENV);
            $collectionPaymentDetail = PaymentDetails::getDetailsByCheckouId($this->request->get('checkout_id'));
            $payment = $this->easyApiService->getPayment($collectionPaymentDetail->first()->dibs_paymentid);
            if(!empty($payment->getPaymentType())) {
                $this->shopifyReturnParams->setX_Amount($collectionPaymentDetail->first()->amount);
                $this->shopifyReturnParams->setX_Currency( $collectionPaymentDetail->first()->currency);
                $this->shopifyReturnParams->setX_GatewayReference($collectionPaymentDetail->first()->dibs_paymentid);
                $this->shopifyReturnParams->setX_Reference($collectionPaymentDetail->first()->checkout_id);
                $this->shopifyReturnParams->setX_Result('completed');
                $this->shopifyReturnParams->setX_Timestamp(date("Y-m-d\TH:i:s\Z"));
                $this->shopifyReturnParams->setX_TransactionType('authorization');
                $this->shopifyReturnParams->setX_AccountId($settingsCollection->first()->easy_merchantid);
                if($payment->getPaymentType() == 'CARD') {
                    $cardDetails = $payment->getCardDetails();
                    $this->shopifyReturnParams->setX_CardType($payment->getPaymentMethod());
                    $this->shopifyReturnParams->setX_CardMaskedPan($cardDetails['maskedPan']);
                }
                $this->shopifyReturnParams->setX_PaymentType($payment->getPaymentType());
                if($collectionPaymentDetail->first()->test == 1) {
                    $this->shopifyReturnParams->setX_Test();
                }
                $signature = $this->shopifyApiService->calculateSignature($this->shopifyReturnParams->getParams(), $settingsCollection->first()->gateway_password);
                $this->shopifyReturnParams->setX_Signature($signature);
                $params['params'] = $this->shopifyReturnParams->getParams();
                $params['url'] = $this->request->get('x_url_complete');
                return view('easy-accept', $params);
            } else {
                return redirect($this->request->get('x_url_cancel'));
            }
        } catch (\App\Exceptions\EasyException $e) {
              $this->eh->handle($e, $this->request->all());
              return response('HTTP/1.0 500 Internal Server Error', 500);
        } catch(\Exception $e) {
              $this->exHandler->report($e);
              $this->logger->debug($this->request);
              $this->logger->debug($collectionPaymentDetail);
              return response('HTTP/1.0 500 Internal Server Error', 500);
        }
    }
}
