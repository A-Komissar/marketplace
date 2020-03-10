<?php

namespace App\Services\Markets;

use App\Events\SellerNewChat;
use App\Mail\NewChatMessageEmail;
use App\Mail\ProductsChangesEmail;
use App\Models\Brand;
use App\Models\Characteristic;
use App\Models\Chat;
use App\Models\ChatMessage;
use App\Models\Kit;
use App\Models\KitItem;
use App\Models\Order;
use App\Models\OrderProduct;
use App\Models\OrderStatus;
use App\Models\Product;
use App\Models\ProductChange;
use App\Models\Seller;
use App\Models\SellerExtraEmail;
use App\Services\ChatService;
use App\Services\OrderService;
use Carbon\Carbon;
use GuzzleHttp\Client;
use App\Models\Market;
use App\Models\Category;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;

class RozetkaService implements MarketServiceInterface
{
    private $feedContentType;
    private $endpoint;
    private $username;
    private $password;
    private $email;
    private $headers = [];
    private $access_token;
    private $market_id;
    private $client;
    private $market;
    private $category;
    private $order;
    private $order_completed_statuses;
    private $order_failed_statuses;
    private $order_new_statuses;
    private $products_log_template_link;
    private $products_log_email;
    private $orders_date_from = '2020-01-01';

    public function __construct() {
        $this->order_completed_statuses = Config::get('market.order_completed_statuses');
        $this->order_failed_statuses = Config::get('market.order_failed_statuses');
        $this->order_new_statuses = Config::get('market.order_new_statuses');
        $this->username = Config::get('market.rozetka.username');
        $this->password = Config::get('market.rozetka.password');
        $this->email = Config::get('market.rozetka.email');
        $this->endpoint = Config::get('market.rozetka.endpoint');
        $this->feedContentType = Config::get('market.rozetka.feed_content_type');
        $this->client = new Client(['base_uri' => $this->endpoint]);
        $this->market = new Market();
        $this->category = new Category();
        $this->order = new Order();
        $this->market_id = $this->market->getIdByCode('rozetka');
        $this->products_log_template_link = Config::get('market.rozetka.products_log_template_link');
        $this->products_log_email = Config::get('market.rozetka.products_log_email');
        $days = Config::get('market.orders_for_last_days') ?: 30;
        $this->orders_date_from = Carbon::now()->subDays($days)->toDateString();
    }

    private function initHeaders() {
        if ($this->access_token == '' && session('rozetka_token')) {
            $this->access_token = session('rozetka_token');
            if (!$this->checkAccessToken()) {
                $this->login();
            }
        } else if ($this->access_token == '') {
            $this->login();
        } else if (!$this->checkAccessToken()) {
            $this->login();
        }
        $this->headers = [
            'Authorization' => 'Bearer '.$this->access_token,
            'Accept' => '*/*',
            'Content-Type' => 'application/json'
        ];
    }

    private function login() {
        $sub_query = '/sites';
        $response = $this->client->post($sub_query, [
            'form_params' => [
                'username' => $this->username,
                'password' => base64_encode($this->password)
            ]
        ]);
        $decoded_response = json_decode($response->getBody());
        if ($response->getStatusCode() == 200 && $decoded_response->success == 1) {
            $this->access_token = $decoded_response->content->access_token;
            session(['rozetka_token' => $this->access_token]);
            return true;
        } else {
            return false;
        }
    }

    private function checkAccessToken() {
        try {
            $sub_query = '/markets/business-types';
            $response = $this->client->get($sub_query, [
                'headers' => $this->headers
            ]);
            $decoded_response = json_decode($response->getBody());
            if ($response->getStatusCode() == 200 && $decoded_response->success == 1) {
                return true;
            } else {
                session()->forget('rozetka_token');
                return false;
            }
        } catch (\Exception $e) {
            return false;
        }
    }

    private function getMeta($sub_query) {
        $response = $this->client->get($sub_query, [
            'headers' => $this->headers
        ]);
        $decoded_response = json_decode($response->getBody());
        if ($response->getStatusCode() == 200 && $decoded_response->success == 1) {
            return $decoded_response->content->_meta;
        } else {
            return false;
        }
    }

    public function getFeedContentType() {
        return $this->feedContentType;
    }

    public function getFeed() {
        if($this->feedContentType == 'text/xml') {
            return $this->getFeedXml();
        } else {
            throw new \Exception('Not implemented');
        }
    }

    private function getFeedXml() {
        $query = Product::with('photos', 'seller', 'characteristics');
        $query->where('approved', 1);
        $query->whereHas('seller', function ($q) {
            $q->where('approved', 1);
        });
        $products = $query->get();
        $categoriesArr = array();
        foreach ($query->select('rozetka_category_id')->distinct()->get() as $product) {
            $category = Category::where('id', $product->rozetka_category_id)->first();
            if (!$category) continue;
            $categoriesArr[$category->category_id] = [
                'name' => $category->name,
                'parent_id' => $category->parent_id
            ];
            $parent_id = $category->parent_id;
            while (true) {
                if ($parent_id != 0) {
                    $parent = Category::where('category_id', $parent_id)->first();
                    if (!$parent || array_key_exists($parent->category_id, $categoriesArr)) {
                        break;
                    }
                    $categoriesArr[$parent->category_id] = [
                        'name' => $parent->name,
                        'parent_id' => $parent->parent_id
                    ];
                    $parent_id = $parent->parent_id;
                } else {
                    break;
                }
            }
        }
        $date = date("Y-m-d H:i");
        $xml = new SimpleXMLExtended('<?xml version="1.0" encoding="UTF-8"?><!DOCTYPE yml_catalog SYSTEM "shops.dtd"><yml_catalog date="'
            .$date.'"></yml_catalog>');
        $shop = $xml->addChild('shop');
        $shop->addChild('name', Config::get('market.rozetka.title'));
        $shop->addChild('company', Config::get('market.rozetka.company'));
        $shop->addChild('url', url('/'));
        $currencies = $shop->addChild('currencies');
        $currency = $currencies->addChild('currency');
        $currency['id'] = 'UAH';
        $currency['rate'] = '1';
        $categories = $shop->addChild('categories');
        foreach ($categoriesArr as $key => $value) {
            $category = $categories->addChild('category', $value['name']);
            $category['id'] = $key;
            if($value['parent_id'] > 0) $category['parentId'] = $value['parent_id'];
        }
        $offers = $shop->addChild('offers');
        foreach ($products as $product) {
            $category = Category::where('id', $product->rozetka_category_id)->first();
            if (!$category) continue;
            $offer = $offers->addChild('offer');
            $article_text = ($product->seller->use_prefix) ? $product->seller->prefix.$product->article : $product->article;
            $offer['id'] = $article_text;
            if ($product->disabled) {
                if ($product->is_active_at_rozetka) {
                    $offer['available'] =  'false';
                } else {
                    continue;
                }
            } else {
                $offer['available'] =  $product->stock > 0 && !$product->seller()->first()->is_hidden ? 'true' : 'false';
            }
            $offer->addChild('price', $product->price);
            if ($product->price_old > $product->price) $offer->addChild('price_old', $product->price_old);
            if ($product->price_promo && $product->price_promo < $product->price) $offer->addChild('price_promo', $product->price_promo);
            $offer->addChild('currencyId', 'UAH');
            $offer->addChild('categoryId', $category->category_id);
            $photos = array();
            $main_photo = $product->photos()->where('main', 1)->first();
            if ($main_photo) {
                array_push($photos, url('/').'/'.$main_photo->photo);
            }
            foreach ($product->photos()->get() as $photo) {
                if (!$photo->main) array_push($photos, url('/').'/'.$photo->photo);
            }
            foreach ($photos as $photo) {
                $offer->addChild('picture', $photo);
            }
            $product_brand = htmlspecialchars($product->brand);
            $offer->addChild('vendor', $product_brand);
            $offer->addChild('stock_quantity', $product->stock);
            $name = htmlspecialchars($product->name_ru);
            if (Seller::where('id', $product->seller_id)->first()->add_article_to_name) {
                $name = htmlspecialchars($product->name_ru.' ('.$article_text.')');
            }
            $name_ua = htmlspecialchars($product->name_ua);
            if (Seller::where('id', $product->seller_id)->first()->add_article_to_name) {
                $name_ua = htmlspecialchars($product->name_ua.' ('.$article_text.')');
            }
            if ($product->state == 'used') {
                $name .= '- Б/У';
                $name_ua .= '- Б/У';
                $offer->addChild('state', 'used');
            } else if ($product->state == 'refurbished') {
                $name .= '- Refurbished';
                $name_ua .= '- Refurbished';
                $offer->addChild('state', 'refurbished');
            }
            $name_item = $offer->addChild('name');
            $name_item->addCData($name);
            $name_ua_item = $offer->addChild('name_ua');
            $name_ua_item->addCData($name_ua);
            $description = $offer->addChild('description');
            $description->addCData($product->description_ru);
            $description_ua = $offer->addChild('description_ua');
            $description_ua->addCData($product->description_ua);
            foreach ($product->characteristics()->get() as $ch) {
                if ($ch->value && $ch->value != "") {
                    $characteristic = $offer->addChild('param', htmlspecialchars($ch->value));
                    $characteristic['name'] = $ch->key;
                }
            }
            if ($product->warranty) {
                $warranty = $offer->addChild('param', $product->warranty);
                $warranty['name'] = 'Гарантия';
            }
            if ($product->country_origin) {
                $country_origin = $offer->addChild('param', $product->country_origin);
                $country_origin['name'] = 'Страна-производитель товара';
            }
            if ($product->country_brand) {
                $country_brand = $offer->addChild('param', $product->country_brand);
                $country_brand['name'] = 'Страна регистрации бренда';
            }
            if ($product->delivery_message) {
                $delivery_message = $offer->addChild('param', $product->delivery_message);
                $delivery_message['name'] = 'Доставка/Оплата';
            }
            $article = $offer->addChild('param', $article_text);
            $article['name'] = 'Артикул';
        }
        try {
            Storage::put('/public/market/rozetka/feed.xml', $xml->asXML());
        } catch (\Exception $e) { }
        return $xml->asXML();
    }

    public function sendEmailWithProductsChanges() {
        try {
            $query = Product::where('approved', 1)->where('disabled', 0);
            $query->whereHas('seller', function ($q) {
                $q->where('approved', 1);
            });
            $products = $query->where('rozetka_product_id', '<>', null)->get();
            $this->sendProductsChanges($products);
        } catch (\Exception $e) { }
    }

    private function sendProductsChanges($products) {
        if (Config::get('app.env') == 'production') {
            try {
                $prod_ids = $products->pluck('id')->toArray();
                $changes = ProductChange::where('is_rozetka_notified', false)->whereIn('product_id', $prod_ids)->get();
                if (count($changes) < 1) {
                    return true;
                }
                $file = $this->generateChangesTable($changes);
                if ($file) {
                    Mail::to($this->products_log_email)->send(new ProductsChangesEmail($file, $this->email));
                    ProductChange::whereIn('id', $changes->pluck('id'))->update(['is_rozetka_notified' => true]);
                    return true;
                } else {
                    return false;
                }
            } catch (\Exception $e) {
                return false;
            }
        }
        return false;
    }

    private function generateChangesTable($changes) {
        try {
            $filepath = "app/logs/products_changelog.xlsx";
            $reader = new \PhpOffice\PhpSpreadsheet\Reader\Xlsx();
            $spreadsheet = $reader->load($this->products_log_template_link);
            $array_data = array();
            $changes_count = 0;
            $photos = array();
            foreach ($changes as $change) {
                $product = Product::with('seller')->where('id', $change->product_id)->first();
                if (!$product || !($change->type == 'characteristic' || $change->type == 'photo' || $change->key == 'name_ru'
                    || $change->key == 'warranty' || $change->key == 'country_origin' || $change->key == 'country_brand' )) {
                    continue;
                }
                if ($change->type == 'photo') {
                    if (in_array($change->product_id, $photos)) {
                        continue;
                    } else {
                        array_push($photos, $change->product_id);
                    }
                }
                $price_id = ($product->seller->use_prefix) ? $product->seller->prefix.$product->article : $product->article;
                $rozetka_id = $product->rozetka_product_id;
                $name = $product->name_ru;
                $changed = Lang::get("common.{$change->key}",[],"ru");
                if ($change->key == 'rozetka_category_id') {
                    $changed = Lang::get('common.product_category',[],'ru');
                } else if ($change->type == 'characteristic') {
                    $changed = Lang::get('common.product_characteristic',[],'ru').' "' . $change->key . '"';
                }
                array_push($array_data, [$price_id, $rozetka_id, $name, $changed]);
                $changes_count++;
            }
            if ($changes_count < 1) {
                return null;
            }
            $spreadsheet->getActiveSheet()->fromArray($array_data, NULL, 'A2');
            $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);

            // create folder if not exists
            if (!is_dir('../storage/app/logs/')){
                mkdir('../storage/app/logs/', 0755, true);
            }
            
            $writer->save('../storage/'.$filepath);
            return $filepath;
        } catch (\Exception $e) {
            return null;
        }
    }

    public function getOrders() {
        $this->initHeaders();
        try {
            for ($i = 1; $i <= 3; $i++) {
                $response = $this->client->get('/orders/search', [
                    'headers' => $this->headers,
                    'query' => [
                        'type' => $i,
                        'created_from' => $this->orders_date_from // skip very old orders
                    ]
                ]);
                $decoded_response = json_decode($response->getBody());
                if ($response->getStatusCode() == 200 && $decoded_response->success == 1) {
                    $meta = $decoded_response->content->_meta;
                } else {
                    $meta = null;
                }
                if ($meta) {
                    for ($page = 1; $page <= $meta->pageCount; $page++) {
                        $orders = $this->getOrdersByPage($page, $i);
                        foreach ($orders as $order) {
                            DB::beginTransaction();
                            try {
                                if ($this->order->where('order_id', $order->id)
                                        ->where('market_id', $this->market_id)->count() > 0) {
                                    $new_order = $this->order->where('order_id', $order->id)
                                        ->where('market_id', $this->market_id)->first();
                                } else {
                                    // if ($i == '2' || $i == '3') continue; // skip completed and failed
                                    $new_order = new Order();
                                    $new_order->market_id = $this->market_id;
                                    $new_order->order_id = $order->id;
                                    $new_order->comment = $order->comment;
                                    $new_order->user_phone = $order->user_phone;
                                    $new_order->user_name = $order->user->contact_fio;
                                    $new_order->user_email = $order->user->email;
                                    $new_order->total_price = $order->cost_with_discount;
                                    $new_order->created_at = $order->created;
                                    $new_order->delivery_service = $order->delivery->delivery_service_name;
                                    $new_order->delivery_office = $order->delivery->place_number;
                                    $new_order->delivery_recipient = $order->delivery->recipient_title;
                                    $delivery_address = "{$order->delivery->place_street} {$order->delivery->place_house}";
                                    if ($order->delivery->place_flat) {
                                        $delivery_address .= " кв. {$order->delivery->place_flat}";
                                    }
                                    $new_order->delivery_address = $delivery_address;
                                    $new_order->delivery_city = $order->delivery->city->name;
                                    $new_order->delivery_region = $order->delivery->city->region_title;
                                    $new_order->delivery_cost = $order->delivery->cost;
                                }
                                $new_order->status_id = (new OrderStatus())::where('key', $order->status)->where('market_id', $this->market_id)->first()->id;
                                $new_order->updated_at = isset($order->changed) ? $order->changed : $order->created;
                                if (in_array($new_order->status_id, $this->order_completed_statuses) && !$new_order->completed_at) {
                                    $new_order->completed_at = $new_order->updated_at;
                                }
                                $new_order->save();
                                if (OrderProduct::where('order_id', $new_order->id)->count() == 0) {
                                    foreach ($order->purchases as $product) {
                                        $article = $product->item->uploader_offer_id;
                                        $pr = new OrderProduct();
                                        $pr->order_id = $new_order->id;
                                        $pr->product_name = $product->item_name;
                                        $product_id = $product->item->id;
                                        $found_prod = false;
                                        if ($product_id) {
                                            $prod = Product::where('rozetka_product_id', $product_id)->first();
                                            if ($prod) {
                                                $pr->product_id = $prod->id;
                                                $pr->seller_id = $prod->seller_id;
                                                $found_prod = true;
                                            }
                                        }
                                        if (!$found_prod) {
                                            if ($article) {
                                                $s = Seller::where('prefix', substr($article, 0, 4))->first();
                                                $p = null;
                                                if ($s) {
                                                    $p = Product::where('article', substr($article, 4))->where('seller_id', $s->id)->first();
                                                }
                                                if ($s && $s->id > 0 && $p && $p->id > 0) {
                                                    $pr->product_id = $p->id;
                                                    $pr->seller_id = $s->id;
                                                } else {
                                                    $p = Product::where('article', $article)->first();
                                                    if ($p && $p->id > 0) {
                                                        $pr->product_id = $p->id;
                                                        $pr->seller_id = $p->seller_id;
                                                    } else {
                                                        $pr->product_id = $pr->seller_id = 0;
                                                    }
                                                }
                                            } else {
                                                $pr->product_id = $pr->seller_id = 0;
                                            }
                                            $prod = Product::where('id', $pr->product_id)->first();
                                            if (!$prod) {
                                                continue;
                                            }
                                        }
                                        $pr->quantity = $product->quantity;
                                        $pr->price = $product->price_with_discount;
                                        $orderService = new OrderService();
                                        $transaction_value = $orderService->getCategoryCommissionSize($prod->rozetka_category_id, $prod->seller_id) * $pr->price * $pr->quantity;
                                        $pr->commission_value = $transaction_value;
                                        if ($pr->product_id > 0 && $pr->seller_id > 0) {
                                            $pr->save();
                                            $orderService->sendOrderMail($pr);
                                        }
                                    }
                                } else {
                                    $email_resend_time = config('market.order_email_resend_time');
                                    $max_emails_count = config('market.max_order_emails_count') > 0 ? config('market.max_order_emails_count') : 3;
                                    foreach (OrderProduct::where('order_id', $new_order->id)->get() as $pr) {
                                        if (in_array($new_order->status_id, $this->order_new_statuses)
                                            && $pr->emails_sent_count <= $max_emails_count && $email_resend_time > 0
                                            && strtotime($pr->last_order_email_time) < (time() - (60 * 60 * $email_resend_time))) {
                                            $orderService = new OrderService();
                                            $orderService->sendOrderMail($pr);
                                        }
                                    }
                                }
                                (new OrderService())->updateCommission($new_order); // update commission from products
                                DB::commit();
                            } catch (\Exception $e) {
                                DB::rollback();
                            }
                        }
                    }
                }
            }
            foreach (Order::whereIn('status_id', $this->order_new_statuses)->get() as $order) {
                (new OrderService())->createNewOrderEvent($order);
            }
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    private function getOrdersByPage($page, $type = '1') {
        $sub_query = '/orders/search';
        $response = $this->client->get($sub_query, [
            'headers' => $this->headers,
            'query' => [
                'page' => $page,
                'type' => $type,
                'expand' => 'user,delivery,purchases'
            ]
        ]);
        $decoded_response = json_decode($response->getBody());
        if ($response->getStatusCode() == 200 && $decoded_response->success == 1) {
            return $decoded_response->content->orders;
        } else {
            return false;
        }
    }

    public function getAvailableStatuses($old_status_key) {
        try {
            $this->initHeaders();
            $keys = array($old_status_key);
            $sub_query = '/order-statuses/search?id='.$old_status_key.'&expand=status_available';
            $response = $this->client->get($sub_query, ['headers' => $this->headers]);
            $decoded_response = json_decode($response->getBody());
            if ($response->getStatusCode() == 200 && $decoded_response->success == 1) {
                $available_statuses = $decoded_response->content->orderStatuses[0]->status_available;
                foreach ($available_statuses as $available_status) {
                    array_push($keys, $available_status->child_id);
                }
                return OrderStatus::where('market_id', $this->market_id)->whereIn('key', $keys)->get();
            } else {
                return OrderStatus::where('market_id', $this->market_id)->whereIn('key', $keys)->get();
            }
        } catch (\Exception $e) {
            return OrderStatus::where('market_id', $this->market_id)->get();
        }
    }

    public function updateOrder($order_id, $status, $comment = '', $ttn = '') {
        if (Config::get('app.env') == 'production') {
            $this->initHeaders();
            try {
                $sub_query = '/orders/'.$order_id;
                if ($status == '3') {
                    $response = $this->client->put($sub_query, [
                        'headers' => $this->headers,
                        'body' => json_encode(array(
                            'status' => $status,
                            'seller_comment' => $comment,
                            'ttn' => $ttn
                        ))
                    ]);
                } else {
                    $response = $this->client->put($sub_query, [
                        'headers' => $this->headers,
                        'body' => json_encode(array(
                            'status' => $status,
                            'seller_comment' => $comment
                        ))
                    ]);
                }
                $decoded_response = json_decode($response->getBody());
                if($response->getStatusCode() == 200 && !isset($decoded_response->errors)) {
                    return true;
                } else {
                    return false;
                }
            } catch (\Exception $e) {
                return false;
            }
        } else {
            return false;
        }
    }

    public function getProducts() {
        $this->initHeaders();
        $res = $this->getProductsByPage(1, 1, 1);
        $pages_count = $res->_meta->pageCount;
        $active_products = $res->items;
        for ($i = 1; $i < $pages_count; $i++) {
            $active_products = array_merge($active_products, $this->getProductsByPage($i + 1, 1));
        }
        /* Product::where('is_active_at_rozetka', true)->update([
            'is_active_at_rozetka' => false
        ]); */
        $old_active_products = Product::where('is_active_at_rozetka', true)->get()->keyBy('id');
        foreach (Product::with('seller')->where('approved', true)->get() as $product) {
            $article_text = ($product->seller->use_prefix) ? $product->seller->prefix . $product->article : $product->article;
            $prod = $this->searchProductByArticleInArray($article_text, $active_products);
            if ($prod) {
                $product->is_active_at_rozetka = true;
                $product->rozetka_product_id = $prod->id;
                $product->rozetka_product_url = $prod->url;
                $product->save();
                $old_active_products->forget($product->id);
            }
        }
        foreach ($old_active_products as $not_active_product) {
            $not_active_product->is_active_at_rozetka = false;
            $not_active_product->save();
        }
    }

    private function searchProductByArticleInArray($article, $products) {
        foreach ($products as $product) {
            if ($product->article == $article) {
                return $product;
            }
        }
        return null;
    }

    private function getProductsByPage($page, $active = 1, $return_meta = 0) {
        $sub_query = '/items/search';
        $response = $this->client->get($sub_query, [
            'headers' => $this->headers,
            'query' => [
                'page' => $page,
                'item_active' => $active,
            ]
        ]);
        $decoded_response = json_decode($response->getBody());
        if ($response->getStatusCode() == 200 && $decoded_response->success == 1) {
            if ($return_meta) {
                return $decoded_response->content;
            } else {
                return $decoded_response->content->items;
            }
        } else {
            return null;
        }
    }

    private function getProductByArticle($article, $active = 1) {
        $sub_query = '/items/search';
        $response = $this->client->get($sub_query, [
            'headers' => $this->headers,
            'query' => [
                'article' => $article,
                'item_active' => $active,
            ]
        ]);
        $decoded_response = json_decode($response->getBody());
        if ($response->getStatusCode() == 200 && $decoded_response->success == 1) {
            $items = $decoded_response->content->items;
            if (count($items) == 0) {
                return null;
            } else {
                return $items[0];
            }
        } else {
            return null;
        }
    }

    public function getChats() {
        $this->initHeaders();
        $sub_query = '/messages/search';
        $pages_count = $this->getMeta($sub_query)->pageCount;
        for ($i = 0; $i < $pages_count; $i++) {
            $response = $this->client->get($sub_query, [
                'headers' => $this->headers,
                'query' => [
                    'page' => $i + 1,
                    'expand' => 'messages,item,user_fio',
                ]
            ]);
            $decoded_response = json_decode($response->getBody());
            if ($response->getStatusCode() == 200 && $decoded_response->success == 1) {
                foreach ($decoded_response->content->chats as $item) {
                    $chat = Chat::where('chat_id', $item->id)->first();
                    if (!$chat) {
                        $chat = new Chat();
                        $chat->chat_id = $item->id;
                        $chat->market_id = $this->market_id;
                        $client = \App\Models\Client::where('user_id', $item->user_id)->first();
                        if (!$client) {
                            $client = new \App\Models\Client();
                            $client->user_id = $item->user->id;
                            $client->name = $item->user->contact_fio;
                            $client->email = $item->user->email;
                            $client->save();
                        } else {
                        	if ($item->user->contact_fio && $item->user->contact_fio != $client->name) {
                        		$client->name = $item->user->contact_fio;
                        		$client->save();
                        	}
                        	if ($item->user->email && $item->user->email != $client->email) {
                        		$client->email = $item->user->email;
                        		$client->save();
                        	}
                        }
                        $chat->client_id = $client->id;
                        $chat->subject = $item->subject;
                        $chat->type = $item->type;
                        $chat->created_at = $item->created;
                    }
                    if (!($chat->product_id || $chat->order_id)) {
                    	if ($item->order_id && !$chat->order_id) {
							$order = Order::where('order_id', $item->order_id)->first();
                        	$chat->order_id = $order ? $order->id : null;
                    	}
                    	if ($item->item_id && !$chat->product_id) {
							try {
	                        	$item_id = $item->item_id;
	                        	if ($item_id) {
	                        		$p = Product::where('rozetka_product_id', $item_id)->first();
	                        		$chat->product_id = $p ? $p->id : null;
	                        	}
	                        	if (!$chat->product_id) {
									$article = $item->item->article;
		                            $s = Seller::where('prefix', substr($article, 0, 4))->first();
		                            if ($s) {
		                                $p = Product::where('article', substr($article, 4))->where('seller_id', $s->id)->first();
		                                $chat->product_id = $p ? $p->id : null;
		                            } else {
		                                $p = Product::where('article', $article)->first();
		                                $chat->product_id = $p ? $p->id : null;
		                            }
	                        	}
	                        } catch (\Exception $e) { }
                    	}
                    }
                    $chat->updated_at = $item->updated;
                    $chat->save();
                    foreach ($item->messages as $mes) {
                        // sender = 2 - Сообщение от менеджера магазина
                        // sender = 3 - Сообщение от пользователя (покупателя)
                        if ($mes->sender != 2 && $mes->sender != 3) continue;
                        $message = ChatMessage::where('chat_id', $chat->id)->where('created_at', $mes->created)->first();
                        if (!$message) {
                            $message = new ChatMessage();
                            $message->chat_id = $chat->id;
                            $message->message = $mes->body;
                            $message->is_sent_by_seller = $mes->sender == 2 ? true : false;
                            $message->created_at = $mes->created;
                            $message->save();
                            if (!$message->is_sent_by_seller && ($chat->product_id || $chat->order_id)) {
                                try {
                                    $chat->is_read = false;
                                    $chat->save();
                                    $sellers = array();
                                    if ($chat->order_id) {
                                        foreach (OrderProduct::where('order_id', $chat->order_id)->get() as $prod) {
                                            array_push($sellers, $prod->seller_id);
                                        }
                                        $sellers = array_unique($sellers);
                                    } else {
                                        array_push($sellers, Product::where('id', $chat->product_id)->first()->seller_id);
                                    }
                                    foreach ($sellers as $seller_id) {
                                        $seller = Seller::where('id', $seller_id)->first();
                                        if ($seller) {
                                            if (Config::get('app.env') == 'production') {
                                                Mail::to($seller->email)->send(new NewChatMessageEmail($seller->name, $message));
                                                $extra_emails = SellerExtraEmail::where('seller_id', $seller_id)->get();
								                foreach ($extra_emails as $extra_email) {
								                    Mail::to($extra_email->email)->send(new NewChatMessageEmail($extra_email->name, $message));
								                }
                                            }
                                            try {
                                                $chats_num = count((new ChatService())->getChats($seller_id, false, true)) ?: 0;
                                                event(new SellerNewChat($seller_id, $chats_num));
                                            } catch (\Exception $e) { }
                                        }
                                    }
                                } catch (\Exception $e) {
                                }
                            }
                        }
                    }
                }
            }
        }
    }

    public function sendMessage($chat, $message, $send_email = true) {
        if (Config::get('app.env') == 'production') {
            $this->initHeaders();
            try {
                $sub_query = '/messages/create';
                $order = Order::where('id', $chat->order_id)->where('market_id', $this->market_id)->first();
                $client = \App\Models\Client::where('id', $chat->client_id)->first();
                $response = $this->client->post($sub_query, [
                    'headers' => $this->headers,
                    'body' => json_encode(array(
                        'body' => $message,
                        'chat_id' => $chat->chat_id,
                        'order_id' => $order ? $order->order_id : null,
                        'sendEmailUser' => $send_email,
                        'receiver_id' => $client->user_id,
                    ))
                ]);
                $decoded_response = json_decode($response->getBody());
                if ($response->getStatusCode() == 200 && !isset($decoded_response->errors)) {
                    $content = $decoded_response->content;
                    $new_message = new ChatMessage();
                    $new_message->chat_id = $chat->id;
                    $new_message->message = $content->body;
                    $new_message->is_sent_by_seller = true;
                    $new_message->created_at = $content->created;
                    $new_message->save();
                    return $new_message;
                } else {
                    return null;
                }
            } catch (\Exception $e) {
                return null;
            }
        } else {
            return null;
        }
    }

    public function getCategories() {
        $this->initHeaders();
        $meta = $this->getMeta('/market-categories/search');
        if ($meta) {
            for ($page = 1; $page <= $meta->pageCount; $page++) {
                $categories = $this->getCategoriesByPage($page);
                foreach ($categories as $category) {
                    if ($this->category->where('category_id', $category->category_id)
                            ->where('market_id', $this->market_id)->count() == 0) {
                        $new_category = new Category();
                        $new_category->category_id = $category->category_id;
                    } else {
                        $new_category = $this->category->where('category_id', $category->category_id)
                            ->where('market_id', $this->market_id)->first();
                    }
                    $new_category->parent_id = $category->parent_id;
                    $new_category->market_id = $this->market_id;
                    $new_category->name = trim(str_replace("(".$category->category_id.")", "", $category->name));
                    $new_category->save();
                }
            }
        }
    }

    private function getCategoriesByPage($page) {
        $sub_query = '/market-categories/search';
        $response = $this->client->get($sub_query, [
            'headers' => $this->headers,
            'query' => [
                'page' => $page
            ]
        ]);
        $decoded_response = json_decode($response->getBody());
        if ($response->getStatusCode() == 200 && $decoded_response->success == 1) {
            return $decoded_response->content->marketCategorys;
        } else {
            return false;
        }
    }

    public function getCharacteristics() {
        $this->initHeaders();
        Characteristic::truncate();
        $categories = Category::where('market_id', $this->market_id)->get();
        foreach ($categories as $category) {
            $this->updateCharacteristics($category->id);
        }
    }

    private function updateCharacteristics($category_id) {
        $this->initHeaders();
        $category = Category::where('id', $category_id)->first();
        if ($category) {
            $sub_query = '/market-categories/category-options';
            $response = $this->client->get($sub_query, [
                'headers' => $this->headers,
                'query' => [
                    'category_id' => $category->category_id
                ]
            ]);
            $decoded_response = json_decode($response->getBody());
            if ($response->getStatusCode() == 200 && $decoded_response->success == 1) {
                $content = json_decode($decoded_response->content);
                foreach ($content as $ch) {
                    $new_ch = new Characteristic();
                    $new_ch->market_id = $this->market_id;
                    $new_ch->category_id = $category_id;
                    $new_ch->characteristic_id = $ch->id;
                    $new_ch->name = $ch->name;
                    $new_ch->attr_type = $ch->attr_type;
                    $new_ch->filter_type = $ch->filter_type;
                    $new_ch->value_id = $ch->value_id;
                    $new_ch->value_name = $ch->value_name;
                    $new_ch->save();
                }
                return true;
            }
        }
        return false;
    }

    public function getBrands() {
        if(($this->access_token != '' && $this->checkAccessToken()) || $this->login()) {
            $sub_query = '/market-categories/export-producers';
            $headers = array(
                'Authorization: Bearer '.$this->access_token,
                'Accept: */*'
            );
            try {
                $ch = curl_init($this->endpoint.$sub_query);
                curl_setopt($ch, CURLOPT_POST, 0);
                curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                $result = curl_exec($ch);
                $path = storage_path().'/rozetka/';
                if(!is_dir($path)){
                    mkdir($path, 0755, true);
                }
                $f = fopen($path.'brand.xls', 'w');
                fwrite($f, $result);
                fclose($f);
                curl_close($ch);
                $brands_array = Excel::toArray(null, $path.'brand.xls');
                unset($brands_array[0][0]);
                foreach ($brands_array[0] as $brand_array) {
                    if (Brand::where('market_id', $this->market_id)
                            ->where('code', $brand_array[0])
                            ->where('name', $brand_array[1])->count() == 0) {
                        $brand = new Brand;
                        $brand->market_id = $this->market_id;
                        $brand->code = $brand_array[0];
                        $brand->name = $brand_array[1];
                        $brand->save();
                    }
                }
                unlink($path.'brand.xls');
                return true;
            } catch (\Exception $e) {
                return false;
            }
        } else {
            return null;
        }
    }

    public function getCategoryAttributes($category_id) {
        $this->initHeaders();
        $sub_query = '/market-categories/options-xls?category_id='.$category_id;
        $response = $this->client->get($sub_query, [ 'headers' => $this->headers]);
        $decoded_response = json_decode($response->getBody());
        if ($response->getStatusCode() == 200 && $decoded_response->success == 1) {
            $params = $decoded_response->content->url;
        } else {
            $params = null;
        }
        return $params;
    }

    public function createKit($kit) {
        $this->initHeaders();
        if (Config::get('app.env') == 'production') {
            try {
                $secondItems = array();
                foreach (KitItem::where('kit_id', $kit->id)->get() as $item) {
                    array_push($secondItems, (object) [
                        'item_id' => Product::where('id', $item->product_id)->first()->rozetka_product_id,
                        'relative_discount' => $item->relative_discount ? intval($item->relative_discount) : '',
                        'fixed_discount' => $item->fixed_discount ? intval($item->fixed_discount) : '',
                        'fixed_amount' => $item->fixed_amount ? intval($item->fixed_amount) : '',
                        'price_amount' => $item->price_amount ? intval($item->price_amount) : '',
                    ]);
                }
                $sub_query = '/kits/create';
                $response = $this->client->post($sub_query, [
                    'headers' => $this->headers,
                    'body' => json_encode(array(
                        'title' => $kit->title,
                        'item_id' => Product::where('id', $kit->product_id)->first()->rozetka_product_id,
                        'start_date' => $kit->start_date,
                        'end_date' => $kit->end_date,
                        'secondItems' => $secondItems
                    ))
                ]);
                $decoded_response = json_decode($response->getBody());
                if ($response->getStatusCode() == 200 && !isset($decoded_response->errors)) {
                    $kit->kit_id = intval($decoded_response->content->id);
                    $kit->is_active = $decoded_response->content->status == 'active' ? true : false;
                    $kit->save();
                    return true;
                }
            } catch (\Exception $e) {}
        }
        return false;
    }

    public function updateKit($kit, $is_active) {
        $this->initHeaders();
        if (!$kit->kit_id) {
            $updated_kit = $this->createKit($kit);
        } else {
            if (Config::get('app.env') == 'production') {
                try {
                    $secondItems = array();
                    foreach (KitItem::where('kit_id', $kit->id)->get() as $item) {
                        array_push($secondItems, (object) [
                            'item_id' => Product::where('id', $item->product_id)->first()->rozetka_product_id,
                            'relative_discount' => $item->relative_discount ? intval($item->relative_discount) : '',
                            'fixed_discount' => $item->fixed_discount ? intval($item->fixed_discount) : '',
                            'fixed_amount' => $item->fixed_amount ? intval($item->fixed_amount) : '',
                            'price_amount' => $item->price_amount ? intval($item->price_amount) : '',
                        ]);
                    }
                    $sub_query = '/kits/'.$kit->kit_id;
                    $response = $this->client->put($sub_query, [
                        'headers' => $this->headers,
                        'body' => json_encode(array(
                            'title' => $kit->title,
                            'item_id' => Product::where('id', $kit->product_id)->first()->rozetka_product_id,
                            'start_date' => $kit->start_date,
                            'end_date' => $kit->end_date,
                            'secondItems' => $secondItems
                        ))
                    ]);
                    $decoded_response = json_decode($response->getBody());
                    if ($response->getStatusCode() == 200 && !isset($decoded_response->errors)) {
                        $kit->kit_id = intval($decoded_response->content->id);
                        $kit->is_active = $decoded_response->content->status == 'active' ? true : false;
                        $kit->save();
                        $updated_kit = true;
                    } else {
                        $updated_kit = false;
                    }
                } catch (\Exception $e) {
                    $updated_kit = false;
                }
            } else {
                $updated_kit = false;
            }
        }
        if ($updated_kit && $is_active != $kit->is_active) {
            $status = $is_active == '0' ? 'locked' : 'active';
            return $this->updateKitStatus($kit, $status);
        }
        return $updated_kit;
    }

    public function updateKitStatus($kit, $status) {
        $this->initHeaders();
        try {
            $sub_query = '/kits/'.$kit->kit_id.'/block';
            $response = $this->client->put($sub_query, [
                'headers' => $this->headers,
                'body' => json_encode(array(
                    'id' => $kit->kit_id,
                    'status' => $status
                ))
            ]);
            $decoded_response = json_decode($response->getBody());
            if ($response->getStatusCode() == 200 && !isset($decoded_response->errors)) {
                $kit->is_active = $decoded_response->content->status == 'active' ? true : false;
                $kit->save();
                return true;
            } else {
                return false;
            }
        } catch (\Exception $e) {
            return false;
        }
    }

    public function getKits() {
        $this->initHeaders();
        try {
            $pages = $this->getMeta('/kits/search')->pageCount;
            for ($page = 1; $page <= $pages; $page++) {
                $sub_query = '/kits/search';
                $response = $this->client->get($sub_query, [
                    'headers' => $this->headers,
                    'query' => [
                        'page' => $page,
                        // 'expand' => 'item, item_main, second_items'
                    ]
                ]);
                $decoded_response = json_decode($response->getBody());
                if ($response->getStatusCode() == 200 && !isset($decoded_response->errors)) {
                    foreach ($decoded_response->content->kits as $kit_res) {
                        $kit = Kit::where('kit_id', $kit_res->id)->first();
                        if ($kit) {
                            $kit->is_active = $kit_res->status == 'active' ? true : false;
                            $kit->start_date = $kit_res->start_date;
                            $kit->end_date = $kit_res->end_date;
                            $kit->title = $kit_res->title;
                            $kit->save();
                        }
                    }
                }
            }
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

}
