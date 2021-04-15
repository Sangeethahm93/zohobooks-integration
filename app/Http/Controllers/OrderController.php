<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use BigCommerce\ApiV3\Client;
use Weble\ZohoClient\OAuthClient;
use Bigcommerce\Api\Client as Bigcommerce;
use Webleit\ZohoBooksApi\Client as ZohoClient;
use Webleit\ZohoBooksApi\ZohoBooks;
use Weble\ZohoClient\Enums\Region;
use App\Models\Order;

class OrderController extends Controller
{
    /**
     * Set the maximum execution time for api call
     */
    public function __construct() {
        ini_set('max_execution_time', 300);
    }

    /**
     * Creating the order data in local database from the bigcommerce api
     * 
     * @param array $request
     * 
     * @return Response
     */
    public function storeOrderData(Request $request){
        $orderId = $request->orderId;
        $orderStatus = $request->status;
        $cartId = $request->cartId;
        try {
            $order = new Order();
            $order->orderId = $orderId;
            $order->cartId = $cartId;
            $order->orderStatus = $orderStatus;
            $order->invoiceStatus = false;
            $order->save();
            return "Order data saved successfully";
        } catch (\Exception $e) { 
            if ($e->getCode() == 23000) {
                return "Order id already generated";
            }
        }
    }

    /**
     * Zoho api integrations
     */
    public static function zohoApiCalls() {
        $oAuthClient = new OAuthClient(env('ZOHO_CLIENT_ID'), env('ZOHO_CLIENT_SECRET'));
        $oAuthClient->setRefreshToken(env('ZOHO_REFRESH_TOKEN'));
        $oAuthClient->setRegion(Region::in());
        $oAuthClient->offlineMode();

        // Access Token
        $accessToken = $oAuthClient->getAccessToken();
        $isExpired = $oAuthClient->accessTokenExpired();

        // setup the zoho books client
        $client = new ZohoClient($oAuthClient);
        $client->setOrganizationId(env('ZOHO_ORG_ID'));

        // Create the main class
        $zohoBooks = new ZohoBooks($client);
        $zohoBooks->getAvailableModules();
        return $zohoBooks;
    }
}
