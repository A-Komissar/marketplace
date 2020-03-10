<?php

namespace App\Services\Markets;

use App\Models\Category;
use App\Models\Market;
use App\Models\Order;
use App\Models\OrderProduct;
use App\Models\OrderStatus;
use App\Models\Product;
use App\Models\Seller;
use App\Services\Markets\SimpleXMLExtended;
use App\Services\OrderService;
use Carbon\Carbon;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use mysql_xdevapi\Exception;

class PromService implements MarketServiceInterface
{
    private $feedContentType;
    private $endpoint;
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
    private $orders_date_from = '2020-01-01T00:00:00';

    public function __construct() {
        $this->order_completed_statuses = Config::get('market.order_completed_statuses');
        $this->order_failed_statuses = Config::get('market.order_failed_statuses');
        $this->order_new_statuses = Config::get('market.order_new_statuses');
        $this->endpoint = Config::get('market.prom.endpoint');
        $this->feedContentType = Config::get('market.prom.feed_content_type');
        $this->market = new Market();
        $this->category = new Category();
        $this->order = new Order();
        $this->market_id = $this->market->getIdByCode('prom');
        $this->access_token = Config::get('market.prom.token');
        $this->initHeaders();
        $this->client = new Client([
            'base_uri' => $this->endpoint,
            'headers' => $this->headers
        ]);
        $days = Config::get('market.orders_for_last_days') ?: 30;
        $this->orders_date_from = Carbon::now()->subDays($days)->toDateString().'T00:00:00';
    }

    private function initHeaders() {
        $this->headers = [
            'Authorization' => 'Bearer '.$this->access_token,
            'Accept' => '*/*',
            'Content-Type' => 'application/json'
        ];
    }

    public function getFeedContentType() {
        return $this->feedContentType;
    }

    public function getFeed() {
        if($this->feedContentType == 'text/xml') {
            return $this->getFeedXml();
        } else {
            // throw new \Exception('Not implemented');
            return null;
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
            if(!$category) continue;
            if ($product->prom_category_id) {
                $category_prom = Category::where('id', $product->prom_category_id)->first();
                if ($category_prom) {
                    $categoriesArr[$category->category_id] = [
                        'name' => $category->name,
                        'parent_id' => $category->parent_id,
                        'portal_id' => $category_prom->category_id,
                    ];
                }
            } else {
                $categoriesArr[$category->category_id] = [
                    'name' => $category->name,
                    'parent_id' => $category->parent_id
                ];
            }
            $parent_id = $category->parent_id;
            while (true) {
                if($parent_id != 0) {
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
        $xml = new SimpleXMLExtended('<?xml version="1.0" encoding="UTF-8"?><!DOCTYPE yml_catalog SYSTEM "shops.dtd"><yml_catalog date="'
            . date("Y-m-d H:i").'"></yml_catalog>');
        $shop = $xml->addChild('shop');
        $shop->addChild('name', Config::get('market.prom.title'));
        $shop->addChild('company', Config::get('market.prom.company'));
        $shop->addChild('url', url('/'));
        $currencies = $shop->addChild('currencies');
        $currency = $currencies->addChild('currency');
        $currency['id'] = 'UAH';
        $currency['rate'] = '1';
        $categories = $shop->addChild('categories');
        foreach ($categoriesArr as $key => $value) {
            $category = $categories->addChild('category', $value['name']);
            $category['id'] = $key;
            if ($value['parent_id'] > 0) $category['parentId'] = $value['parent_id'];
            if (array_key_exists('portal_id', $value) && $value['portal_id'] > 0) $category['portal_id'] = $value['portal_id'];
        }
        $offers = $shop->addChild('offers');
        foreach ($products as $product) {
            $category = Category::where('id', $product->rozetka_category_id)->first();
            if(!$category) continue;
            $offer = $offers->addChild('offer');
            $article_text = ($product->seller->use_prefix) ? $product->seller->prefix.$product->article : $product->article;
            $offer['id'] = $article_text; // $product->id;
            if ($product->disabled) {
                $available =  '';
            } else {
                $available = $product->stock > 0 && !$product->seller()->first()->is_hidden ? 'true' : '';
            }
            $offer['available'] =  $available; // true - В наличии, false - Под заказ, empty - Нет в наличии.
            $offer->addChild('price', $product->price);
            if ($product->price_old > $product->price) {
                $offer->addChild('oldprice', $product->price_old);
            }
            if ($product->price_promo && $product->price_promo < $product->price) {
                $discount_percent = floor(100 - ($product->price_promo/$product->price)*100);
                if ($discount_percent >= 1) {
                    $offer->addChild('discount', $discount_percent.'%');
                }
            }
            $offer->addChild('currencyId', 'UAH');
            $offer->addChild('categoryId', $category->category_id);
            if ($product->prom_category_id) {
                $prom_category = Category::where('id', $product->prom_category_id)->first();
                if ($prom_category) {
                    $offer->addChild('portal_category_id', $prom_category->category_id);
                }
            }
            $photos = array();
            $main_photo = $product->photos()->where('main', 1)->first();
            if($main_photo) {
                array_push($photos, url('/').'/'.$main_photo->photo);
            }
            foreach ($product->photos()->get() as $photo) {
                if(!$photo->main) array_push($photos, url('/').'/'.$photo->photo);
            }
            foreach ($photos as $photo) {
                $offer->addChild('picture', $photo);
            }
            $product_brand = htmlspecialchars($product->brand);
            $offer->addChild('vendor', $product_brand);
            $offer->addChild('quantity_in_stock', $product->stock);
            $name = htmlspecialchars($product->name_ru);
            if (Seller::where('id', $product->seller_id)->first()->add_article_to_name) {
                $name = htmlspecialchars($product->name_ru.' ('.$article_text.')');
            }
            $name_item = $offer->addChild('name');
            $name_item->addCData($name);
            $description = $offer->addChild('description');
            $description->addCData($product->description_ru);
            $offer->addChild('country', $product->country_origin);
            $offer->addChild('available', $available);
            $offer->addChild('keywords', $product->keywords);
            foreach ($product->characteristics()->get() as $ch) {
                if($ch->value && $ch->value != "") {
                	$characteristic = $offer->addChild('param', htmlspecialchars($ch->value));
                    $characteristic['name'] = $ch->key;
                }
            }
            if($product->warranty) {
                $warranty = $offer->addChild('param', $product->warranty);
                $warranty['name'] = 'Гарантия';
            }
            if($product->country_origin) {
                $country_origin = $offer->addChild('param', $product->country_origin);
                $country_origin['name'] = 'Страна-производитель товара';
            }
            if($product->country_brand) {
                $country_brand = $offer->addChild('param', $product->country_brand);
                $country_brand['name'] = 'Страна регистрации бренда';
            }
            $article = $offer->addChild('param', $article_text);
            $article['name'] = 'Артикул';
        }

        try {
            Storage::put('/public/market/prom/feed.xml', $xml->asXML());
        } catch (\Exception $e) {
            return null;
        }

        return $xml->asXML();
    }

    public function getProducts() {
        /* $sub_query = '/products/list';
        $response = $this->client->get($this->endpoint.$sub_query, [
            'headers' => $this->headers,
            'query' => [
                'group_id' => 0,
                'limit' => 999999
            ]
        ]);
        $decoded_response = json_decode($response->getBody()); */
        return null;  // not implemented
    }

    public function getOrders()
    {
        try {
            $sub_query = '/orders/list';
            $response = $this->client->get($this->endpoint.$sub_query, [
                'headers' => $this->headers,
                'query' => [
                    'limit' => 999999,
                    'date_from' => $this->orders_date_from // skip very old orders
                ]
            ]);
            $decoded_response = json_decode($response->getBody());
            foreach ($decoded_response->orders as $order) {
                DB::beginTransaction();
                try {
                    $new_order = $this->order->where('order_id', $order->id)
                        ->where('market_id', $this->market_id)->first();
                    if ($new_order) {
                        $in_progress = OrderStatus::where('market_id', $this->market_id)->where('value', 'in_progress')->first();
                        $dispatched = OrderStatus::where('market_id', $this->market_id)->where('value', 'dispatched')->first();
                        if (in_array($new_order->status_id, $this->order_completed_statuses)) {
                            if (!$new_order->completed_at) {
                                $new_order->completed_at = Carbon::now()->toDateString();
                                $new_order->save();
                            }
                            continue;
                        } else if ((($in_progress && $new_order->status_id == $in_progress->id)
                                || ($dispatched && $new_order->status_id == $dispatched->id))
                                && ($order->status == 'pending' || $order->status == 'received')) {
                            continue;
                        }
                    } else {
                        $new_order = new Order();
                        $new_order->market_id = $this->market_id;
                        $new_order->order_id = $order->id;
                        $new_order->comment = $order->client_notes;
                        $new_order->user_phone = $order->phone;
                        $new_order->user_name = $order->client_last_name.' '.$order->client_first_name.' '.$order->client_second_name;
                        $new_order->user_email = $order->email;
                        $special_price = doubleval(str_replace(array(' ', '&nbsp;', 'грн.'), array('', '', ''), htmlentities($order->price_with_special_offer)));
                        $new_order->total_price = $special_price ?: doubleval(str_replace(array(' ', '&nbsp;', 'грн.'), array('', '', ''), htmlentities($order->price)));
                        $date_created = str_replace("T"," ", substr($order->date_created,0, 16));
                        $new_order->created_at = $date_created;
                        try {
                            $new_order->delivery_service = $order->delivery_option->name;
                        } catch (\Exception $e) { }
                        $new_order->delivery_address = $order->delivery_address;
                        try {
                            $new_order->payment = $order->payment_option->name;
                        } catch (\Exception $e) { }
                        /*
                        $new_order->delivery_recipient = $new_order->user_name;
                        $new_order->delivery_office = null;
                        $new_order->delivery_city = null;
                        $new_order->delivery_region = null;
                        $new_order->delivery_cost = null;
                        */
                    }
                    $status = (new OrderStatus())::where('value', $order->status)->where('market_id', $this->market_id)->first();
                    $new_order->status_id = $status ? $status->id : 1;
                    $new_order->updated_at = Carbon::now()->toDateTimeString();
                    if (in_array($new_order->status_id, $this->order_completed_statuses) && !$new_order->completed_at) {
                        $new_order->completed_at = Carbon::now()->toDateString();
                    }
                    $new_order->save();

                    if ($order->status == 'pending') {
                        $this->updateOrder($order->id, 'received');
                    }

                    if (OrderProduct::where('order_id', $new_order->id)->count() == 0) {
                        foreach ($order->products as $product) {
                            $article = $product->external_id;
                            $pr = new OrderProduct();
                            $pr->order_id = $new_order->id;
                            $pr->product_name = $product->name;
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
                                $p = Product::where('name_ru', 'LIKE', '%'.$product->name.'%')->first();
                                if ($p) {
                                    $pr->product_id = $p->id;
                                    $pr->seller_id = $p->seller_id;
                                } else {
                                    $pr->product_id = $pr->seller_id = 0;
                                }
                            }
                            $prod = Product::where('id', $pr->product_id)->first();
                            if (!$prod) {
                                continue;
                            }
                            $pr->quantity = $product->measure_unit == 'шт.' ? $product->quantity : 1;
                            $pr->price = doubleval(str_replace(array(' ', '&nbsp;', 'грн.'), array('', '', ''), htmlentities($product->price)));
                            $total_price = doubleval(str_replace(array(' ', '&nbsp;', 'грн.'), array('', '', ''), htmlentities($product->total_price)));
                            $orderService = new OrderService();
                            if ($total_price > 0) {
                                $transaction_value = $orderService->getCategoryCommissionSize($prod->rozetka_category_id, $prod->seller_id) * $total_price;
                            } else {
                                $transaction_value = $orderService->getCategoryCommissionSize($prod->rozetka_category_id, $prod->seller_id) * $pr->price * $pr->quantity;
                            }
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
            foreach (Order::whereIn('status_id', $this->order_new_statuses)->get() as $order) {
                (new OrderService())->createNewOrderEvent($order);
            }
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    public function getAvailableStatuses($old_status_key)
    {
        // not implemented
        return null;
    }

    public function updateOrder($order_id, $status, $comment = '', $ttn = '', $cancel_reason = null)
    {
        if (Config::get('app.env') == 'production') {
            try {
                $sub_query = '/orders/set_status';
                if ($status == 'canceled') {
                    $response = $this->client->post($this->endpoint.$sub_query, [
                        'headers' => $this->headers,
                        'body' => json_encode(array(
                            'status' => $status,
                            'cancellation_reason' => $cancel_reason ?: 'another',
                            'cancellation_text' => $comment,
                            'ids' => [
                                $order_id
                            ]
                        ))
                    ]);
                } else {
                    $response = $this->client->post($this->endpoint.$sub_query, [
                        'headers' => $this->headers,
                        'body' => json_encode(array(
                            'status' => $status,
                            'ids' => [
                                $order_id
                            ]
                        ))
                    ]);
                }
                $decoded_response = json_decode($response->getBody());
                if ($response->getStatusCode() == 200 && $decoded_response->processed_ids) {
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

    public function getCategories()
    {
        try {
            $file_url = 'https://my.prom.ua/cabinet/export_categories/xml';
            $file = file_get_contents($file_url, false,
                stream_context_create(array('https' => array('header' => 'Accept: application/xml'))));
            $xml = simplexml_load_string($file);
            $this->getCategoryChildren($xml->children()[0]);
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    private function getCategoryChildren($parent_category) {
        $parent_id = intval(((array) ($parent_category->attributes())['id'])[0]);
        $categories = $parent_category->children();
        if (empty($categories)) {
            return;
        }
        foreach ($categories as $category) {
            try {
                $attributes = $category->attributes();
                $category_id = intval(((array) $attributes['id'])[0]);
                if (Category::where('market_id', $this->market_id)->where('category_id', $category_id)->count() == 0) {
                    $new_category = new Category();
                    $new_category->market_id = $this->market_id;
                    $new_category->parent_id = $parent_id;
                    $new_category->category_id = $category_id;
                    $name = ((array) $attributes['caption'])[0];
                    $new_category->name = $this->mb_ucfirst(trim($name), 'utf-8');
                    $new_category->save();
                }
                $this->getCategoryChildren($category);
            } catch (\Exception $e) { }
        }
    }

    public function getCharacteristics()
    {
        // not implemented
        return null;
    }

    public function getBrands()
    {
        // not implemented
        return null;
    }

    public function getCategoryAttributes($category_id)
    {
        // not implemented
        return null;
    }

    public function getChats() {
        /* $sub_query = '/messages/list';
        $response = $this->client->get($this->endpoint.$sub_query, [
            'headers' => $this->headers,
            'query' => [
                'limit' => 999999
            ]
        ]);
        $decoded_response = json_decode($response->getBody());
        var_dump($decoded_response);exit(); */
        return null; // not implemented
    }

    public function sendMessage($chat, $message, $send_email = true) {
        // not implemented
        return null;
    }

    public function getKits() {
        // not implemented
        return null;
    }

    public function sendEmailWithProductsChanges() {
        // not implemented
        return null;
    }

    private function mb_ucfirst($str, $encoding = NULL) {
        if($encoding === NULL) {
            $encoding = mb_internal_encoding();
        }
        return mb_substr(mb_strtoupper($str, $encoding), 0, 1, $encoding) . mb_substr($str, 1, mb_strlen($str)-1, $encoding);
    }
}
