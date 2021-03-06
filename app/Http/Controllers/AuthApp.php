<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Service\ShopifyApiService;
use App\MerchantSettings;

class AuthApp extends Controller
{
    private $shopifyAppService;

    public function __construct(ShopifyApiService $service) {
        $this->shopifyAppService = $service;
    }

    /**
     * Handle the incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function __invoke(Request $request)
    {
          if($result = $this->shopifyAppService->auth($request)) {
                $result_array = json_decode($result, true);
                session(['access_token' => $result_array['access_token']]);
                session(['shop_url' => $request->get('shop')]);
                $res = $this->shopifyAppService->getShopInfo($result_array['access_token'], $request->get('shop'));
                $shopInfo = json_decode($res, true);
                $params = ['shop_url' => $request->get('shop'),
                           'access_token' => $result_array['access_token'],
                           'gateway_password' => crypt($request->get('shop'),
                           env('SHOPIFY_API_SECRET')),
                           'shop_id' => $shopInfo['shop']['id'],
                           'shop_name' =>  urlencode($shopInfo['shop']['name'])];
                $this->shopifyAppService->registerOrederCreatedHook($result_array['access_token'], $request->get('shop'));
                MerchantSettings::addOrUpdateShop($params);
                return redirect('https://' . $request->get('shop') . '/admin/apps/' . env('SHOPIFY_API_KEY') .  '/form');
          }
    }
}
