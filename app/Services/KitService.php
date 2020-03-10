<?php

namespace App\Services;

use App\Models\Kit;
use App\Models\KitItem;
use App\Models\Market;
use App\Models\Product;
use App\Services\Markets\RozetkaService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Input;

class KitService
{

    private $market_id;
    private $market_code;

    public function __construct($market_code = 'rozetka') {
        $this->market_id = Market::where('market_code', $market_code)->first()->id;
        $this->market_code = $market_code;
    }

    public function getKits($seller_id = null, $filters = null, $per_page = 50) {
        $query = Kit::query();
        if ($seller_id) {
            $query->where('seller_id', $seller_id);
        }
        $query->orderBy('is_active', 'DESC')->orderBy('start_date', 'DESC')->orderBy('end_date', 'DESC');
        $items = $query->paginate($per_page)->appends(Input::except('page'));
        return $items;
    }

    public function getKit($kit_id) {
        $query = Kit::query()->with('seller', 'product', 'items')->where('id', $kit_id);
        if (get_class(Auth::user()) != 'App\Models\Admin') {
            $query->where('seller_id', Auth::user()->id);
        }
        return $query->first() ?: null;
    }

    public function createKit($seller_id, $data, $market_id = 1) {
        try {
            $kit = new Kit();
            $kit->market_id = $market_id;
            $kit->seller_id = $seller_id;
            $kit->product_id = intval($data['main_item']);
            $kit->title = $data['title'];
            $kit->start_date = $data['start_date'];
            $kit->end_date = $data['end_date'];
            if (!$kit->seller_id || !$kit->product_id) {
                return null;
            }
            $kit->save();
            foreach ($data['product'] as $item) {
                if ($item['id'] == $kit->product_id) {
                    continue;
                }
                $product = Product::where('id', intval($item['id']))->first();
                if (!$product) {
                    continue;
                }
                $kit_item = new KitItem();
                $kit_item->kit_id = $kit->id;
                $kit_item->product_id = $product->id;
                $kit_item->relative_discount = $item['relative_discount'] ? intval($item['relative_discount']) : null;
                /* $kit_item->fixed_discount = $item['fixed_discount'] ? intval($item['fixed_discount']) : null;
                $kit_item->fixed_amount = $item['fixed_amount'] ? intval($item['fixed_amount']) : null;
                $kit_item->price_amount = $item['price_amount'] ? intval($item['price_amount']) : null; */
                $kit_item->save();
            }
            if ($this->market_code == 'rozetka') {
                $updated_kit = (new RozetkaService())->createKit($kit);
                if ($updated_kit) {
                    return $kit;
                }
            }
            return null;
        } catch (\Exception $e) {
            return null;
        }
    }

    public function updateKit($kit_id, $data) {
        try {
            $kit = Kit::where('id', $kit_id)->first();
            if (!$kit) {
                return null;
            }
            if ($data['main_item']) {
                $kit->product_id = intval($data['main_item']);
            }
            if ($data['title']) {
                $kit->title = $data['title'];
            }
            if ($data['start_date']) {
                $kit->start_date = $data['start_date'];
            }
            $kit->end_date = $data['end_date'];
            $kit->save();
            $old_items = KitItem::where('kit_id', $kit->id)->get()->keyBy('id');
            foreach ($data['product'] as $item) {
                if ($item['id'] == $kit->product_id) {
                    KitItem::where('product_id', $item['id'])->delete();
                    continue;
                }
                $kit_item = KitItem::where('kit_id', $kit->id)->where('product_id', $item['id'])->first();
                if (!$kit_item) {
                    $product = Product::where('id', intval($item['id']))->first();
                    if (!$product) {
                        continue;
                    }
                    $kit_item = new KitItem();
                    $kit_item->kit_id = $kit->id;
                    $kit_item->product_id = $product->id;
                }
                $kit_item->relative_discount = $item['relative_discount'] ? intval($item['relative_discount']) : null;
                $kit_item->fixed_discount = null; // $item['fixed_discount'] ? intval($item['fixed_discount']) : null;
                $kit_item->fixed_amount = null; // $item['fixed_amount'] ? intval($item['fixed_amount']) : null;
                $kit_item->price_amount = null; // $item['price_amount'] ? intval($item['price_amount']) : null;
                $kit_item->save();
                $old_items->forget($kit_item->id);
            }
            foreach ($old_items as $deleted) {
                $deleted->delete();
            }
            if ($this->market_code == 'rozetka') {
                $updated_kit = (new RozetkaService())->updateKit($kit, $data['is_active']);
                if ($updated_kit) {
                    return $kit;
                }
            }
            return null;
        } catch (\Exception $e) {
            return null;
        }
    }


}
