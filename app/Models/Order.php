<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class Order extends Model
{
    use HasFactory;
    
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'orderId',
        'cartId',
        'orderStatus',
        'invoiceStatus'
    ];

    /**
     * Getting the order table data where invoice status is false
     * 
     */
    public static function getOrderData(){
        DB::table('orders')->where('id', 1)->get(['orderId']);
    }

    /**
     * Updating the invoice status in order table
     * 
     * @param int $orderId
     * @param array $data
     */
    public static function updateData($orderId,$data){
        DB::table('orders')
          ->where('orderId', $orderId)
          ->update($data);
    }
}
