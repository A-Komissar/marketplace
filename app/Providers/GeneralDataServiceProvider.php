<?php

namespace App\Providers;

use App\Models\Order;
use App\Models\SellerSettings;
use App\Services\ChatService;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Facades\Auth;

use App\Models\Seller;
use App\Models\Admin;
use App\Models\Message;
use App\Models\Product;

use App\Services\FinanceService;

class GeneralDataServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     *
     * @return void
     */
    public function register()
    {
        //
    }

    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot()
    {
        view()->composer('admin.partials.header', function ($view) {
            $view->registration_requests_num = Seller::where('approved', 0)->where('declined', '<>', 1)->count();
            $view->change_info_requests_num = SellerSettings::count();
            $view->new_items_num = Product::where('new', 1)->where('approved', 0)->where('disabled', 0)->count();
            $view->new_messages_num = Message::where('new', 1)->where('written_by_admin', 0)->count();
            $order_new_statuses = Config::get('market.order_new_statuses') ?: 1;
            $view->new_orders_num = Order::whereIn('status_id', $order_new_statuses)->count();
        });

        view()->composer('admin.partials.sidebar', function ($view) {
            $view->registration_requests_num = Seller::where('approved', 0)->where('declined', '<>', 1)->count();
            $view->change_info_requests_num = SellerSettings::count();
            $view->new_items_num = Product::where('new', 1)->where('approved', 0)->where('disabled', 0)->count();

            $view->products_no_category_num = Product::where('approved', 1)->where('prom_category_id', null)->count();
            $view->new_messages_num = Message::where('new', 1)->where('written_by_admin', 0)->count();

            $order_new_statuses = Config::get('market.order_new_statuses') ?: 1;
            $view->new_orders_num = Order::whereIn('status_id', $order_new_statuses)->count();
            $order_completed_statuses = Config::get('market.order_completed_statuses');
            $view->completed_orders_num = Order::whereIn('status_id', $order_completed_statuses)->count();
            $order_failed_statuses = Config::get('market.order_failed_statuses');
            $view->failed_orders_num = Order::whereIn('status_id', $order_failed_statuses)->count();
            $view->orders_in_progress_num = Order::whereNotIn('status_id', array_merge($order_completed_statuses, $order_failed_statuses, $order_new_statuses))->count();
        });

        view()->composer('seller.partials.header', function ($view) {
            $seller_id = Auth::user()->id;
            $admin = Admin::where(DB::raw('lower(name)'), 'LIKE', "%admin%")
                ->orWhere(DB::raw('lower(name)'), 'LIKE', "%админ%")
                ->orWhere(DB::raw('lower(name)'), 'LIKE', "%адмін%")->first();
            $admin_id = $admin ? $admin->id : (Admin::where('name', '<>', 'auto')->first())->id;
            $view->admin_id = $admin_id;
            $financeService = new FinanceService();
            $view->balance = $financeService->getBalance($seller_id);
            $new_messages_num = Message::where('seller_id', $seller_id)->where('new', 1)
                ->where('written_by_admin', 1)->count();
            $view->new_messages_num = $new_messages_num;

            $order_new_statuses = Config::get('market.order_new_statuses') ?: 1;
            $view->new_orders_num = Order::whereIn('status_id', $order_new_statuses)
                ->whereHas('products', function ($q) use ($seller_id) {
                    $q->where('seller_id', $seller_id);
                })->count();
        });

        view()->composer('seller.partials.sidebar', function ($view) {
            $seller_id = Auth::user()->id;
            $admin = Admin::where(DB::raw('lower(name)'), 'LIKE', "%admin%")
                ->orWhere(DB::raw('lower(name)'), 'LIKE', "%админ%")
                ->orWhere(DB::raw('lower(name)'), 'LIKE', "%адмін%")->first();
            $admin_id = $admin ? $admin->id : (Admin::where('name', '<>', 'auto')->first())->id;
            $view->admin_id = $admin_id;
            $view->new_messages_num = Message::where('seller_id',$seller_id)->where('new', 1)
                ->where('written_by_admin', 1)->count();

            $order_new_statuses = Config::get('market.order_new_statuses') ?: 1;
            $order_completed_statuses = Config::get('market.order_completed_statuses');
            $order_failed_statuses = Config::get('market.order_failed_statuses');
            $view->new_orders_num = Order::whereIn('status_id', $order_new_statuses)
                ->whereHas('products', function ($q) use ($seller_id) {
                    $q->where('seller_id', $seller_id);
                })->count();
            $view->completed_orders_num = Order::whereIn('status_id', $order_completed_statuses)
                ->whereHas('products', function ($q) use ($seller_id) {
                    $q->where('seller_id', $seller_id);
                })->count();
            $view->failed_orders_num = Order::whereIn('status_id', $order_failed_statuses)
                ->whereHas('products', function ($q) use ($seller_id) {
                    $q->where('seller_id', $seller_id);
                })->count();
            $view->orders_in_progress_num = Order::whereNotIn('status_id', array_merge($order_completed_statuses, $order_failed_statuses, $order_new_statuses))
                ->whereHas('products', function ($q) use ($seller_id) {
                    $q->where('seller_id', $seller_id);
                })->count();

            $view->products_approved_num = Product::where('seller_id', $seller_id)
                ->where('approved', true)->where('disabled', false)->count();
            $view->products_not_approved_num = Product::where('seller_id', $seller_id)
                ->where('approved', false)->where('new', true)->where('disabled', false)->count();
            $view->products_declined_num = Product::where('seller_id', $seller_id)
                ->where('approved', false)->where('new', false)->where('disabled', false)->count();
            $view->products_disabled_num = Product::where('seller_id', $seller_id)
                ->where('new', false)->where('disabled', true)->count();
            $view->products_new_num = Product::where('seller_id', $seller_id)
                ->where('new', true)->where('disabled', true)->count();

            try {
                $view->chats_num = count((new ChatService())->getChats($seller_id, false, true)) ?: 0;
            } catch (\Exception $e) {
                $view->chats_num = 0;
            }

            $view->user = Auth::user()->name;
            $view->company = Auth::user()->company_name;
        });

    }
}
