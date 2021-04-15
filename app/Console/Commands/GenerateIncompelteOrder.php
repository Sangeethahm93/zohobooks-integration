<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use BigCommerce\ApiV3\Client;
use Bigcommerce\Api\Client as Bigcommerce;
use App\Models\Order;
use App\Http\Controllers\OrderController;

class GenerateIncompelteOrder extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'generate:incompleteOrder';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generating a incomplete orders from bogcommerce to local database';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        /**
        * Connecting the Bicommerce through client id, auth token, and store hash
        */
        $bigApi = new Client(env('BIG_STORE_HASH'), env('BIG_X_AUTH_CLIENT'), env('BIG_X_AUTH_TOKEN'));
        Bigcommerce::configure(array(
            'client_id' => env('BIG_X_AUTH_CLIENT'),
            'auth_token' => env('BIG_X_AUTH_TOKEN'),
            'store_hash' => env('BIG_STORE_HASH')
        ));

        /**
         * Get the bigommerce incomplete orders
         */
        $orders = Bigcommerce::getOrders(array('status_id' => 0, 'is_deleted' => 'false'));
        
        foreach ($orders as $orderNew) {
            try {
                $order = new Order();
                $order->orderId = $orderNew->id;
                $order->cartId = $orderNew->cart_id;
                $order->orderStatus = $orderNew->status;
                $order->invoiceStatus = false;
                $order->save();
            	echo "Order data saved successfully";
            } catch (\Exception $e) { 
                if ($e->getCode() == 23000) {
                    echo "Order id already generated";
                }
            }
        }
    }
}
