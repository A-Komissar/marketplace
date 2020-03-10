<?php

use App\Models\Admin;
use App\Models\Order;
use App\Models\OrderProduct;
use App\Models\Product;
use App\Models\Seller;
use App\Models\TransactionHistory;
use App\Services\FinanceService;
use App\Services\OrderService;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class RechargeAllOrdersCommissions extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        TransactionHistory::truncate();
        foreach (Seller::all() as $seller) {
            $seller->balance = 0;
            $seller->save();
        }

        Schema::table('sellers', function (Blueprint $table) {
            $table->decimal('balance', 10, 2)->default(0)->change();
        });

        Schema::table('transaction_history', function (Blueprint $table) {
            $table->decimal('transaction_value', 10, 2)->default(0)->change();
            $table->decimal('balance_before', 10, 2)->default(0)->change();
            $table->decimal('balance_after', 10, 2)->default(0)->change();
        });

        foreach (OrderProduct::all() as $product) {
            $product->commission_value = (new OrderService)->getCategoryCommissionSize(Product::where('id', $product->product_id)
                    ->first()->rozetka_category_id) * $product->price * $product->quantity;
            $product->is_commission_charged = 0;
            $product->save();

            $order = Order::where('id', $product->order_id)->first();
            if (!in_array($order->status_id, [10,11,14,17,18,19,23,25,26,27,28,29,30,31,32,33,34,35,38,39])) {
                $transaction_description = 'Зняття комісії за товар <a href="../products/' .$product->product_id. '" target="_blank">№'.$product->product_id.'</a> (замовлення <a href="../orders/'.$product->order_id. '" target="_blank">№'.$product->order_id.'</a>)';
                $admin = Admin::where('name', 'auto')->first();
                if(!$admin) {
                    $admin = Admin::first();
                }
                $res = (new FinanceService())->updateBalance($product->seller_id, $admin->id, 0-$product->commission_value, $transaction_description);
                if ($res) {
                    $product->is_commission_charged = 1;
                    $product->save();
                }
            }
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        //
    }
}
