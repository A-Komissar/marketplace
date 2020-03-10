<?php

namespace App\Services;

use App\Events\AdminNewOrder;
use App\Events\SellerNewOrder;
use App\Mail\NewOrderEmail;
use App\Models\Admin;
use App\Models\Category;
use App\Models\Commission;
use App\Models\Market;
use App\Models\Order;
use App\Models\OrderProduct;
use App\Models\OrderStatus;
use App\Models\Product;
use App\Models\Seller;
use App\Models\SellerExtraEmail;
use Carbon\Carbon;
use DateTime;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Input;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\Mail;

class OrderService
{

    private $order_completed_statuses;
    private $order_failed_statuses;
    private $order_new_statuses;
    private $orders_export_template_link = '../storage/app/templates/orders_export_template.xlsx';

    public function __construct() {
        $this->order_completed_statuses = Config::get('market.order_completed_statuses');
        $this->order_failed_statuses = Config::get('market.order_failed_statuses');
        $this->order_new_statuses = Config::get('market.order_new_statuses') ?: [1];
    }

    public function createOrder(Request $request) {
        try {
            $new_order = new Order();
            $new_order->market_id = $request['market'];
            $new_order->order_id = $request['order_id'];
            $new_order->status_id = 1;
            $new_order->total_price = $request['total_price'] ?: 0;
            $new_order->user_phone = $request['user_phone'];
            $new_order->user_name = $request['user_name'];
            $new_order->user_email = $request['user_email'];
            $new_order->comment = $request['comment'];
            $new_order->payment = $request['payment'];
            $new_order->delivery_service = $request['delivery_service'];
            $new_order->delivery_office = $request['delivery_office'];
            $new_order->delivery_recipient = $request['delivery_recipient'];
            $new_order->delivery_address = $request['delivery_address'];
            $new_order->delivery_city = $request['delivery_city'];
            $new_order->delivery_region = $request['delivery_region'];
            $new_order->delivery_cost = $request['delivery_cost'];
            $new_order->status_id = 1;
            $new_order->created_at = $new_order->updated_at = (Carbon::now())->toDateTimeString();
            $new_order->save();
            foreach ($request['product'] as $product) {
                $pr = new OrderProduct();
                $pr->order_id = $new_order->id;
                $pr->product_id = $product['id'];
                $pr->seller_id = Product::where('id', $product['id'])->first()->seller_id;
                $pr->quantity = intval($product['quantity']);
                $pr->price = floatval($product['price']);
                $pr->commission_value = round(floatval($product['commission'])/100 * $pr->price * $pr->quantity, 2);
                if ($pr->product_id > 0 && $pr->seller_id > 0) {
                    $pr->save();
                    $this->sendOrderMail($pr);
                }
            }
            if ($new_order->total_price <= 0) {
                $total_price = 0;
                foreach (OrderProduct::where('order_id', $new_order->id)->get() as $pr) {
                    $total_price += $pr->price * $pr->quantity;
                }
                $new_order->total_price = round($total_price, 2);
                $new_order->save();
            }
            $this->updateCommission($new_order);
            $this->createNewOrderEvent($new_order);
            return $new_order;
        } catch (\Exception $e) {
            return null;
        }
    }

    public function getOrders($new=0, $seller_id = null, $is_in_progress = false, $is_completed = false, $is_failed = false, $filters = null, $items_per_page = 50) {
        $query = Order::query();
        $query->with('market', 'status', 'products');
        if ($new) {
            $query->whereIn('status_id', $this->order_new_statuses);
        }
        if ($is_completed) {
            $query->whereIn('status_id', $this->order_completed_statuses);
        }
        if ($is_failed) {
            $query->whereIn('status_id', $this->order_failed_statuses);
        }
        if ($is_in_progress) {
            $query->whereNotIn('status_id', array_merge($this->order_completed_statuses, $this->order_failed_statuses, $this->order_new_statuses));
        }
        if($seller_id) {
            $query->whereHas('products', function ($q) use ($seller_id) {
                $q->where('seller_id', $seller_id);
            });
        }
        if ($filters) {
            if ($filters['order_no']) {
                $query->where('order_id', $filters['order_no']);
            }
        }
        $query->orderBy('created_at', 'desc');
        $orders = $query->paginate($items_per_page)->appends(Input::except('page'));
        return $orders;
    }

    public function getOrder($order_id) {
        $query = Order::query();
        $query->with('market', 'status', 'products');
        $query->where('id', $order_id);
        if(get_class(Auth::user()) == 'App\Models\Seller') {
            $seller_id = Auth::user()->id;
            $query->whereHas('products', function ($q) use ($seller_id) {
                $q->where('seller_id', $seller_id);
            });
        }
        $query->orderBy('created_at', 'desc');
        return $query->first();
    }

    public function updateStatus($order_id, $status_id, $comment = '', $ttn = '', $cancel_reason = null) {
        try {
            $order = Order::where('id', $order_id)->first();
            $rozetka_id = Market::where('market_code', 'rozetka')->first()->id;
            $prom_id = Market::where('market_code', 'prom')->first()->id;
            if ($order->market_id == $rozetka_id) {
                $status_key = OrderStatus::where('id', $status_id)->first()->key;
                $success = (new Markets\RozetkaService())->updateOrder($order->order_id, $status_key, $comment, $ttn);
            } else if ($order->market_id == $prom_id) {
                $status = OrderStatus::where('id', $status_id)->first()->value;
                if ($status == 'in_progress' || $status == 'dispatched') {
                    $success = true;
                } else {
                    $success = (new Markets\PromService())->updateOrder($order->order_id, $status, $comment, $ttn, $cancel_reason);
                }
                if ($success) {
                    $order->updated_at = (Carbon::now())->toDateTimeString();
                }
            } else {
                $order->updated_at = (Carbon::now())->toDateTimeString();
                $success = true;
            }
            if ($success || $order->market_id != $rozetka_id) {
                if ($ttn) {
                    $order->status_id = $status_id;
                    $order->ttn = $ttn;
                    $order->save();
                } else {
                    $order->status_id = $status_id;
                    $order->save();
                }
                if (in_array($status_id, $this->order_completed_statuses)) {
                    $order = Order::where('id', $order_id)->first();
                    if (!$order->completed_at) {
                        $order->completed_at = (Carbon::now())->toDateTimeString();
                        $order->save();
                    }
                }
                $this->updateCommission(Order::where('id', $order_id)->first());
            }
            return $success;
        } catch (\Exception $e) {
            return false;
        }
    }

    public function getAvailableStatuses($old_status_id = null, $market = 'rozetka') {
        try {
            if (in_array($old_status_id, $this->order_completed_statuses) || in_array($old_status_id, $this->order_failed_statuses)) {
                return null;
            }
            if ($old_status_id && $market == 'rozetka') {
                $old_status_key = OrderStatus::where('id', $old_status_id)->first()->key;
                return (new Markets\RozetkaService())->getAvailableStatuses($old_status_key);
            } else if ($market == 'prom') {
                $old_status = OrderStatus::where('id', $old_status_id)->first();
                $statuses = OrderStatus::where('market_id', Market::where('market_code', 'prom')->first()->id)
                    ->where('key', '>=', $old_status && $old_status->key ? $old_status->key : 0)->orderBy('key', 'ASC')->get();
                $statuses = $statuses->keyBy('value');
                $statuses = $statuses->forget('paid');
                $statuses = $statuses->forget('pending');
                return $statuses;
            }
            else {
                 return OrderStatus::where('market_id', Market::where('market_code', 'rozetka')->first()->id)->orderBy('id', 'ASC')->get();
            }
        } catch(\Exception $e) {
            return null;
        }
    }

    public function getProductCommissionSize($market_id, $product_id) {
        try {
            $product = Product::where('id', $product_id)->first();
            $market = Market::where('id', $market_id)->first();
            if($market && $market->market_code == 'rozetka') {
                $commission_size = Config::get('market.rozetka.default_commission');
                $category_id = $product->rozetka_category_id;
            } else {
                throw new \Exception('Not implemented');
            }
            $category = Category::where('id', $category_id)->first();
            $commission = $this->getCommission($category->id, $product->seller_id);
            if($commission) {
                $commission_size = $commission->value;
            } else {
                $parent_id = $category->parent_id;
                while (true) {
                    if($parent_id != 0) {
                        $parent = Category::where('category_id', $parent_id)->first();
                        $commission = $this->getCommission($parent->id, $product->seller_id);
                        if ($commission) {
                            $commission_size = $commission->value;
                            break;
                        } else {
                            $parent_id = $parent->parent_id;
                        }
                    } else {
                        break;
                    }
                }
            }
            return $commission_size * $product->price;
        } catch (\Exception $e) {
            return 0;
        }
    }

    public function getCategoryCommissionSize($category_id, $seller_id = 0) {
        try {
            $category = Category::where('id', $category_id)->first();
            $market = Market::where('id', $category->market_id)->first();
            if ($market && $market->market_code == 'rozetka') {
                $commission_size = Config::get('market.rozetka.default_commission');
            } else {
                $commission_size = Config::get('market.default_commission');
            }
            $commission = $this->getCommission($category_id, $seller_id);
            if ($commission) {
                $commission_size = $commission->value;
            } else {
                $parent_id = $category->parent_id;
                while(true) {
                    if ($parent_id != 0) {
                        $parent = Category::where('category_id', $parent_id)->first();
                        $commission = $this->getCommission($parent->id, $seller_id);
                        if ($commission) {
                            $commission_size = $commission->value;
                            break;
                        } else {
                            $parent_id = $parent->parent_id;
                        }
                    } else {
                        break;
                    }
                }
            }
            return $commission_size;
        } catch (\Exception $e) {
            return Config::get('market.default_commission');
        }
    }

    public function sendOrderMail($order_product) {
        try {
            $seller = Seller::where('id', $order_product->seller_id)->first();
            $email = $seller->email;
            $receiver = $seller->name;
            $product = Product::where('id', $order_product->product_id)->first();
            $order = Order::where('id', $order_product->order_id)->first();
            $market = Market::where('id', $order->market_id)->first()->market_name;
            $obj = (object) [
                'product_id' =>$product->id,
                'product' => $product->name_ua != '' ? $product->name_ua : $product->name_ru,
                'quantity' => $order_product->quantity,
                'price' => $order_product->price,
                'user_name' => $order->user_name,
                'user_phone' => $order->user_phone,
                'user_email' => $order->user_email,
                'market' => $market
            ];
            if (Config::get('app.env') == 'production') {
                Mail::to($email)->send(new NewOrderEmail($receiver, $obj));
                $extra_emails = SellerExtraEmail::where('seller_id', $order_product->seller_id)->get();
                foreach ($extra_emails as $extra_email) {
                    Mail::to($extra_email->email)->send(new NewOrderEmail($extra_email->name, $obj));
                }
            }
            $order_product->emails_sent_count++;
            $order_product->last_order_email_time = \Carbon\Carbon::now();
            $order_product->save();
        } catch (\Exception $e) {}
    }

    public function updateCommission($new_order) {
        foreach (OrderProduct::where('order_id', $new_order->id)->get() as $commission_prod) {
            try {
                if (in_array($new_order->status_id, $this->order_failed_statuses) && $commission_prod->is_commission_charged) {
                    $transaction_description = 'Повернення комісії за непроданий товар <a href="../products/' . $commission_prod->product_id . '" target="_blank">№' . $commission_prod->product_id . '</a> (замовлення <a href="../orders/' . $commission_prod->order_id . '" target="_blank">№' . $commission_prod->order_id . '</a>)';
                    $admin = Admin::where('name', 'auto')->first();
                    if (!$admin) {
                        $admin = Admin::first();
                    }
                    $res = (new FinanceService())->updateBalance($commission_prod->seller_id, $admin->id, $commission_prod->commission_value, $transaction_description);
                    if ($res) {
                        $commission_prod->is_commission_charged = 0;
                        $commission_prod->save();
                    }
                } else if (!$commission_prod->is_commission_charged && !in_array($new_order->status_id, $this->order_failed_statuses)) {
                    $transaction_description = 'Зняття комісії за товар <a href="../products/' . $commission_prod->product_id . '" target="_blank">№' . $commission_prod->product_id . '</a> (замовлення <a href="../orders/' . $commission_prod->order_id . '" target="_blank">№' . $commission_prod->order_id . '</a>)';
                    $admin = Admin::where('name', 'auto')->first();
                    if (!$admin) {
                        $admin = Admin::first();
                    }
                    $res = (new FinanceService())->updateBalance($commission_prod->seller_id, $admin->id, 0 - $commission_prod->commission_value, $transaction_description);
                    if ($res) {
                        $commission_prod->is_commission_charged = 1;
                        $commission_prod->save();
                    }
                }
            } catch (\Exception $e) { }
        }
    }

    private function getCommission($category_id, $seller_id = 0) {
        $category = Category::where('id', $category_id)->first();
        $market = Market::where('id', $category->market_id)->first();
        if (Seller::where('id', $seller_id)->first()) {
            $commission = Commission::where('market_id', $market->id)->where('category_id', $category->id)->where('seller_id', $seller_id)->first();
            if ($commission) {
                return $commission;
            } else {
                return Commission::where('market_id', $market->id)
                    ->where('category_id', $category->id)->where('seller_id', 0)->first();
            }
        } else {
            return Commission::where('market_id', $market->id)
                ->where('category_id', $category->id)->where('seller_id', 0)->first();
        }
    }

    public function deleteOrder($order_id) {
        try {
            $order = Order::where('id', $order_id)->first();
            if (!$order) {
                return false;
            }
            $products = OrderProduct::where('order_id', $order_id)->get();
            foreach ($products as $product) {
                try {
                    if ($product->is_commission_charged) {
                        $transaction_description = 'Повернення комісії за непроданий товар <a href="../products/' . $product->product_id . '" target="_blank">№' . $product->product_id . '</a> (замовлення <a href="../orders/' . $product->order_id . '" target="_blank">№' . $product->order_id . '</a>)';
                        $admin = Admin::where('name', 'auto')->first();
                        if (!$admin) {
                            $admin = Admin::first();
                        }
                        $res = (new FinanceService())->updateBalance($product->seller_id, $admin->id, $product->commission_value, $transaction_description);
                        if (!$res) {
                            return false;
                        }
                        $product->delete();
                    }
                } catch (\Exception $e) {
                    return false;
                }
            }
            $order->delete();
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    public function createNewOrderEvent($new_order) {
        $sellers = array();
        foreach (OrderProduct::where('order_id', $new_order->id)->get() as $pr) {
            if (!in_array($pr->seller_id, $sellers)) {
                array_push($sellers, $pr->seller_id);
            }
        }
        foreach ($sellers as $seller_id) {
            try {
                $new_orders_num = Order::whereIn('status_id', $this->order_new_statuses)
                    ->whereHas('products', function ($q) use ($seller_id) {
                        $q->where('seller_id', $seller_id);
                    })->count();
                event(new SellerNewOrder($seller_id, $new_orders_num));
            } catch (\Exception $e) {
                // websockets doesn't work!
            }
        }
        foreach (Admin::where('name', '<>', 'auto')->get() as $admin) {
            try {
                $new_orders_num = Order::whereIn('status_id', $this->order_new_statuses)->count();
                event(new AdminNewOrder($admin->id, $new_orders_num));
            } catch (\Exception $e) {
                // websockets doesn't work!
            }
        }
    }

    public function deleteOrderNote($order_id) {
        Order::where('id', $order_id)->update(['note' => null]);
    }

    public function editOrderNote($order_id, $note) {
        Order::where('id', $order_id)->update(['note' => $note]);
    }

    public function exportToExcel($start_date = null, $end_date = null) {
        try {
            $filepath = "app/export/orders.xlsx";
            $reader = new \PhpOffice\PhpSpreadsheet\Reader\Xlsx();
            $spreadsheet = $reader->load($this->orders_export_template_link);
            $array_data = array();
            $orders = Order::query()->with('market', 'status');
            if ($start_date) {
                $orders->where('created_at', '>=', $start_date);
            }
            if ($end_date) {
                $orders->where('created_at', '<=', $end_date);
            }
            foreach ($orders->get() as $order) {
                try {
                    $status = $order->market->market_code == 'prom' ? Lang::get('common.prom_status_'.$order->status->value,[],'ru') : $order->status->value;
                    $order_data = [
                        $order->order_id,
                        $order->market->market_name,
                        $status,
                        $order->user_name ?: '',
                        $order->user_phone ? strval($order->user_phone) : '',
                        $order->user_email ?: '',
                        (new DateTime($order->created_at))->format('d.m.Y'),
                        $order->completed_at ? (new DateTime($order->completed_at))->format('d.m.Y') : '',
                        $order->comment ?: '',
                        $order->ttn ? strval($order->ttn) : '',
                        $order->payment ?: '',
                        $order->delivery_service ?: '',
                        $order->delivery_city ?: '',
                        $order->delivery_address ?: ''
                    ];
                    foreach ($order->products()->get() as $product) {
                        try {
                            $product_data = [
                                $product->seller->name.' ('.$product->seller->company_name.')',
                                ($product->product && $product->product->rozetka_category) ? $product->product->rozetka_category->name : '',
                                $product->product ? strval($product->product->article) : '',
                                $product->product ? $product->product->name_ru : ($product->product_name ?: $product->product_id),
                                $product->quantity,
                                $product->price,
                                $product->price * $product->quantity,
                                $product->commission_value,
                                round(($product->commission_value/($product->price * $product->quantity))*100),
                                $product->is_commission_charged ? 'Средства списаны' : 'Средства не списаны'
                            ];
                            array_push($array_data, array_merge($order_data, $product_data));
                        } catch (\Exception $e) { }
                    }
                } catch (\Exception $e) { }
            }
            $spreadsheet->getActiveSheet()->fromArray($array_data, NULL, 'A2');
            $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
            // create folder if not exists
            if (!is_dir('../storage/app/export/')){
                mkdir('../storage/app/export/', 0755, true);
            }
            $writer->save('../storage/'.$filepath);
            return $filepath;
        } catch (\Exception $e) {
            return null;
        }
    }

}
