<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Http\Request;
use BigCommerce\ApiV3\Client;
use Weble\ZohoClient\OAuthClient;
use Bigcommerce\Api\Client as Bigcommerce;
use Webleit\ZohoBooksApi\Client as ZohoClient;
use Webleit\ZohoBooksApi\ZohoBooks;
use Weble\ZohoClient\Enums\Region;
use App\Models\Order;
use App\Http\Controllers\OrderController;

class GenerateCustomerInvoice extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'generate:customerInvoice';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Creating the contacts if there is no contact present in zoho and creating the invoice of the product';

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
         * Connecting the Zoho books
         */
        $zohoBooksData = OrderController::zohoApiCalls();

        /**
         * Getting the order data from the Order table
         */
        $orderData = Order::where('invoiceStatus', false)->get(['orderId']);

        /**
         * Checking whether order invoice generated or not
         */
        if(count($orderData) > 0) {
            if (is_array($orderData) || is_object($orderData)) {
                foreach($orderData as $order) {
                    $orderId = $order->orderId;

                    /**
                     * Checking order invoice is already present or not
                     */
                    $zohobooksInvoice = $zohoBooksData->invoices->getList(['reference_number' => $orderId]);
                    $zohoBooksInvoiceArray = json_decode($zohobooksInvoice, true);
                    
                    if(count($zohoBooksInvoiceArray) > 0) {
                        //Updating the inoice status
                        $invoicesData = array('invoiceStatus'=>true, 'orderStatus'=>'Completed');
                        Order::updateData($orderId, $invoicesData);
                        echo "Invoice is already generated";
                    } else {
                        $order = Bigcommerce::getOrder($orderId);
                        $orderBillingAddress = $order->billing_address;
                        $email = $orderBillingAddress->email;
                        $orderStatus = $order->status;
                        if($orderStatus == 'Completed') {
                            $zohobooksContact = $zohoBooksData->contacts->getList(['email' => $email]);
                            $zohoBooksContactArray = json_decode($zohobooksContact, true);
    
                            $contactId = [];
                            if(count($zohoBooksContactArray) > 0) {
                                foreach($zohoBooksContactArray as $key=>$value) {
                                    if($value['contact_type'] == 'customer') {
                                        array_push($contactId, $value['contact_id']);
                                    }
                                }
                            } else {
                                //Creating the customer
                                $formdata = [
                                    "contact_name" => $orderBillingAddress->first_name." ".$orderBillingAddress->last_name,
                                    "company_name" => $orderBillingAddress->company,
                                    "billing_address" => [
                                        "address" => $orderBillingAddress->street_1,
                                        "street2" => $orderBillingAddress->street_2,
                                        "city" => $orderBillingAddress->city,
                                        "state" => $orderBillingAddress->state,
                                        "zip" => $orderBillingAddress->zip,
                                        "country" => $orderBillingAddress->country,
                                        "phone"=> $orderBillingAddress->phone
                                    ],
                                    "shipping_address" => [
                                        "address" => $orderBillingAddress->street_1,
                                        "street2" => $orderBillingAddress->street_2,
                                        "city" => $orderBillingAddress->city,
                                        "state" => $orderBillingAddress->state,
                                        "zip" => $orderBillingAddress->zip,
                                        "country" => $orderBillingAddress->country,
                                        "phone"=> $orderBillingAddress->phone
                                    ],
                                    "contact_persons" => [
                                        [
                                            "email" => $orderBillingAddress->email
                                        ]
                                    ],
                                    "custom_fields" => [
                                        [
                                            "value" => $orderBillingAddress->form_fields[0]->value,
                                            "label" => "INFLUENCER CODE"
                                        ]
                                    ],
                                ];
    
                                //Customer creating
                                $createCustomer = $zohoBooksData->contacts->create($formdata);
                                array_push($contactId, $createCustomer->contact_id);
                                echo 'Contact created successfully';
                            }
    
                            $orderProducts = Bigcommerce::getOrderProducts($orderId);
                            $zohoProductdetails = [];
    
                            foreach($orderProducts as $orderProduct) {
                                $productId = $orderProduct->product_id;
                                $productVariantId = $orderProduct->variant_id;
                                
                                $productModifiersData = $bigApi->catalog()->product($productId)->modifiers()->getAll();
                                $productModifiers = $productModifiersData->getProductModifiers();
    
                                if(count($productModifiers) == 0) {
                                    $productVariantData = $bigApi->catalog()->product($productId)->variant($productVariantId)->get()->getProductVariant();
                                    $productVariantPrice = $productVariantData->price;
                                    $productVariantSku = $productVariantData->sku;
    
                                    //Getting the tax name of the product
                                    $productTax = Bigcommerce::getProduct($productId)->tax_class();
                                    $taxName = $productTax->name;
    
                                    //Getting the product id from the zoho books
                                    $zohobooksItem = $zohoBooksData->items->getList(['sku' => $productVariantSku]);
                                    $zohoBooksItemArray = json_decode($zohobooksItem, true);
                                    $itemId = [];
                                    foreach($zohoBooksItemArray as $key=>$value) {
                                        array_push($itemId, $value['item_id']);
                                    }
    
                                    /**
                                     * Zoho books tax classes compare with bigcommerce tax classes
                                     */
                                    $zohobooksTaxes = $zohoBooksData->settings->taxes->getList();
                                    $zohoBooksTaxArray = json_decode($zohobooksTaxes, true);
                                    $taxValue = [];
                                    foreach($zohoBooksTaxArray as $key=>$value) {
                                        if($taxName == '12' || $taxName == '12%') {
                                            if($value['tax_name'] == 'GST12') {
                                                array_push($taxValue, $value['tax_id']);
                                            }
                                        } elseif($taxName == '5' || $taxName == '5%' ||  $taxName == '5.00') {
                                            if($value['tax_name'] == 'GST5') {
                                                array_push($taxValue, $value['tax_id']);
                                            }
                                        } elseif($taxName == '18' || $taxName == '18%') {
                                            if($value['tax_name'] == 'GST18') {
                                                array_push($taxValue, $value['tax_id']);
                                            }
                                        } elseif($taxName == '28' || $taxName == '28%') {
                                            if($value['tax_name'] == 'GST28') {
                                                array_push($taxValue, $value['tax_id']);
                                            }
                                        } else {
                                            if($value['tax_name'] == 'GST0') {
                                                array_push($taxValue, $value['tax_id']);
                                            }
                                        }
                                    }
    
                                    foreach($itemId as $id) {
                                        array_push($zohoProductdetails, array('item_id'=>$id, 'rate'=> $productVariantPrice, 'quantity' => $orderProduct->quantity, 'tax_id'=> $taxValue[0]));
                                    }
                                }
                            }
    
                            if($orderBillingAddress->form_fields[0]->value !== '') {
                                $salepersonDetails = $orderBillingAddress->form_fields[0]->value;
                            } else {
                                $salepersonDetails = 'INF';
                            }
                    
                           //Creating the invoice in zoho books
                            $invoiceData = [
                                "customer_id" => $contactId[0],
                                "salesperson_name" => $salepersonDetails,
                                "line_items" => $zohoProductdetails,
                                "reference_number" => $orderId,
                                "shipping_charge" => $order->shipping_cost_inc_tax,
                                "custom_fields" => [
                                    [
                                        "value" => $order->payment_method,
                                        "label" => "Payment Method"
                                    ],
                                    [
                                        "value" => $order->payment_status,
                                        "label" => "Payment Status"
                                    ]
                                ]
                            ];
                            $createInvoices = $zohoBooksData->invoices->create($invoiceData);
    
                            $invoiceId = $createInvoices->invoice_id;
                            
                            /**
                             * Mark the invoice as approve
                             */
                            $zohoBooksData->invoices->approveInvoice($invoiceId);
    
                            /**
                             * Mark the invoice as sent
                             */
                            $zohoBooksData->invoices->markAsSent($invoiceId);
                            
                            //Updating the database
                            $invoicesData = array('invoiceStatus'=>true, 'orderStatus'=>'Completed');
                            Order::updateData($orderId, $invoicesData);
                            echo "Invoice generated successfully";
                        } else {
                            echo "Order status is not changed to completed";
                        }
                    }
                }
            } 
        } else {
            echo "There is no order id to generate invoice";
        }
    }
}
