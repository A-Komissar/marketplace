<?php

namespace App\Services;

use App\Models\Kit;
use App\Models\KitItem;
use App\Models\Market;
use App\Models\Product;
use App\Models\ProductChange;
use App\Models\ProductCharacteristic;
use App\Services\Markets\RozetkaService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Input;

class LogService
{

    public function getProductsLog($seller_id = null, $filters = null, $per_page = 50) {
        $query = ProductChange::query();
        if ($seller_id) {
            $query->whereHas('product', function($q) use($seller_id) {
                $q->where('seller_id', $seller_id);
            });
        } else if ($filters['seller']) {
            $seller = $filters['seller'];
            $query->whereHas('product', function($q) use($seller) {
                $q->whereHas('seller', function($q2) use($seller) {
                    $q2->where('name', 'LIKE', '%'.$seller.'%');
                });
            });
        }
        if ($filters['product']) {
            $product = $filters['product'];
            $query->whereHas('product', function($q) use($product) {
                $q->where(function ($q) use($product) {
                    $q->where('article', 'like', '%'.$product.'%');
                    $q->orWhere('name_ru', 'like', '%'.$product.'%');
                    $q->orWhere('name_ua', 'like', '%'.$product.'%');
                });
            });
        }
        if ($filters['category']) {
            $category = $filters['category'];
            $query->whereHas('product', function($q) use($category) {
                $q->whereHas('rozetka_category', function($q2) use($category) {
                    $q2->where('name', 'LIKE', '%'.$category.'%');
                });
            });
        }

        $items = $query->orderBy('changed_at', 'DESC')->orderBy('product_id', 'ASC')
            ->paginate($per_page)->appends(Input::except('page'));
        return $items;
    }

    public function rollbackProducts($items) {
        foreach ($items as $item) {
            try {
                $change = ProductChange::where('id', $item)->first();
                if ($change && ($change->type == 'property' || $change->type == 'characteristic')) {
                    if (get_class(Auth::user()) == 'App\Models\Admin') {
                        $product = Product::where('id', $change->product_id)->first();
                    } else {
                        $product = Product::where('seller_id', Auth::user()->id)->where('id', $change->product_id)->first();
                    }
                    if ($product) {
                        if ($change->type == 'property') {
                            $property = $change->key;
                            switch ($property) {
                                default:
                                case 'rozetka_category_id':
                                    $product->rozetka_category_id = intval($change->before);
                                    break;
                                case 'price':
                                    $product->price = doubleval($change->before);
                                    break;
                                case 'price_promo':
                                    $product->price_promo = doubleval($change->before);
                                    break;
                                case 'stock':
                                    $product->stock = intval($change->before);
                                    break;
                                case 'brand':
                                    $product->brand = $change->before;
                                    break;
                                case 'name_ru':
                                    $product->name_ru = $change->before;
                                    break;
                                case 'name_ua':
                                    $product->name_ua = $change->before;
                                    break;
                                case 'description_ru':
                                    $product->description_ru = $change->before;
                                    break;
                                case 'description_ua':
                                    $product->description_ua = $change->before;
                                    break;
                                case 'state':
                                    $product->state = $change->before;
                                    break;
                                case 'keywords':
                                    $product->keywords = $change->before;
                                    break;
                                case 'warranty':
                                    $product->warranty = $change->before;
                                    break;
                                case 'country_origin':
                                    $product->country_origin = $change->before;
                                    break;
                                case 'country_brand':
                                    $product->country_brand = $change->before;
                                    break;
                                case 'delivery_message':
                                    $product->delivery_message = $change->before;
                                    break;
                            }
                            $product->default_save();
                            $change->delete();
                        } else if ($change->type == 'characteristic') {
                            $ch = ProductCharacteristic::where('product_id', $product->id)->where('key', $change->key)->first();
                            if (!$ch) {
                                $ch = new ProductCharacteristic();
                                $ch->product_id = $product->id;
                                $ch->key = $change->key;
                            }
                            if (!$change->before) {
                                $ch->default_delete();
                            } else {
                                $ch->value = $change->before;
                                $ch->default_save();
                            }
                            $change->delete();
                        }
                    }
                }
            } catch (\Exception $e) { }
        }
    }

    public function deleteOldData($type = 'month') {
        if ($type == 'week') {
            $date = Carbon::now()->subWeek()->toDateTimeString();
        } else {
            $date = Carbon::now()->subMonth()->toDateTimeString();
        }
        ProductChange::where('changed_at', '<', $date)->delete();
        return true;
    }

}
