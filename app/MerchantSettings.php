<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use App\Service\ShopifyApiService;

class MerchantSettings extends Model
{
    protected $table = 'merchants_settings';
    protected $fillable = ['shop_name', 'shop_url', 'access_token', 'gateway_password', 'shop_id']; 
    protected $shopifyAppService;
    
    public function __construct(array $attributes = array()) {
        //$this->shopifyAppService = $service;
        parent::__construct($attributes);
    }

    /**
     * 
     * @param string $shopUrl
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public static function getSettingsByShopUrl($shopUrl)
    {
        return self::query()->where('shop_url', $shopUrl)->get();
    }
    
    public static function getSettingsByShopName($shopname) 
    {
         return self::query()->where('shop_name', $shopname)->get();
    }

    /**
     * 
     * @param \Illuminate\Http\Request $request
     */
    public static function saveShopSettings(\Illuminate\Http\Request $request)
    {
       $params = $request->all();
       $params['easy_secret_key'] = ShopifyApiService::encryptKey($params['easy_secret_key']);
       $params['easy_test_secret_key'] = ShopifyApiService::encryptKey($params['easy_test_secret_key']);
       self::query()->where('shop_url', $request->get('shop_url'))->update($params);
    }

    public static function addOrUpdateShop($params)
    {
        error_log($params['shop_id']);
        self::query()->updateOrCreate(['shop_id' => $params['shop_id']], $params);
    }

}
