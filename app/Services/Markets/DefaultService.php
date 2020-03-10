<?php

namespace App\Services\Markets;

use App\Models\Category;
use App\Models\Market;
use App\Models\Order;
use App\Models\Product;
use App\Models\Seller;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Storage;

class DefaultService implements MarketServiceInterface
{
    private $feedContentType;
    private $market;
    private $category;
    private $order;
    private $order_completed_statuses;
    private $order_failed_statuses;
    private $order_new_statuses;

    public function __construct() {
        $this->order_completed_statuses = Config::get('market.order_completed_statuses');
        $this->order_failed_statuses = Config::get('market.order_failed_statuses');
        $this->order_new_statuses = Config::get('market.order_new_statuses');
        $this->feedContentType = Config::get('market.prom.feed_content_type');
        $this->market = new Market();
        $this->category = new Category();
        $this->order = new Order();
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
                $offer['available'] =  'false';
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
            Storage::put('/public/market/default/feed.xml', $xml->asXML());
        } catch (\Exception $e) {
            return null;
        }

        return $xml->asXML();
    }


    public function getProducts() {
        return null;  // not implemented
    }

    public function getOrders()
    {
        // not implemented
        return null;
    }

    public function getAvailableStatuses($old_status_key)
    {
        // not implemented
        return null;
    }

    public function getCategories()
    {
        // not implemented
        return null;
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

    public function getChats() {
        return null; // not implemented
    }

    public function getKits() {
        // not implemented
        return null;
    }

    public function updateOrder($order_id, $status, $comment = '', $ttn = '')
    {
        // not implemented
        return null;
    }

    public function getCategoryAttributes($category_id)
    {
        // not implemented
        return null;
    }

    public function sendMessage($chat, $message, $send_email = true)
    {
        // not implemented
        return null;
    }

    public function sendEmailWithProductsChanges() {
        // not implemented
        return null;
    }
}
