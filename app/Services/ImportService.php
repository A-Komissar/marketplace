<?php

namespace App\Services;

use App\Models\Market;
use App\Models\Product;
use App\Models\ProductCharacteristic;
use App\Models\ProductPhoto;
use App\Models\Category;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Input;
use App\Models\ImportProducts;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Maatwebsite\Excel\Facades\Excel;

class ImportService
{

    public function importAllProducts() {
        $models = ImportProducts::all();
        foreach ($models as $model) {
            try {
                $request = new Request();
                $request['is_default'] = false;
                $request['import_type'] = $model->import_type;
                $request['import_link'] = $model->import_url;
                $request['category'] = $model->category;
                $request['article'] = $model->article;
                $request['name_ru'] = $model->name_ru;
                $request['name_ua'] = $model->name_ua;
                $request['description_ru'] = $model->description_ru;
                $request['description_ua'] = $model->description_ua;
                $request['price'] = $model->price;
                $request['price_old'] = $model->price_old;
                $request['price_rate'] = $model->price_rate;
                $request['stock'] = $model->stock;
                $request['brand'] = $model->brand;
                $request['country_origin'] = $model->country_origin;
                $request['country_brand'] = $model->country_brand;
                $request['warranty'] = $model->warranty;
                $request['photo'] = $model->photo;
                $request['update_price'] = $model->update_price;
                $request['nullify_stock_if_not_found'] = $model->nullify_stock_if_not_found;
                $json = json_decode($model->additional_JSON);
                if ($model->import_type == 'xml') {
                    $request['product'] = $json->product;
                    $request['product_category_id'] = $json->product_category_id;
                    $request['category_category_id_attribute'] = $json->category_category_id_attribute;
                    $request['param'] = $json->param;
                    $request['param_key_attribute'] = $json->param_key_attribute;
                } else if ($model->import_type == 'excel') {
                    $request['photos_delimiter'] = $json->photos_delimiter;
                } else if ($model->import_type == 'csv') {
                    $request['main_delimiter'] = $json->main_delimiter;
                    $request['photos_delimiter'] = $json->photos_delimiter;
                }
                $this->importProducts($model->seller_id, $request);
            } catch (\Exception $e) {
                continue;
            }
        }
    }

    public function importProducts($seller_id, Request $request) {
        try {
            if($request['is_default']) {
                $this->createImportProductsTemplate($seller_id, $request);
            }
            switch ($request['import_type']) {
                default:
                case 'xml':
                    $result = $this->importProductsFromXml($seller_id, $request);
                    break;
                case 'excel':
                    $result = $this->importProductsFromExcel($seller_id, $request);
                    break;
                case 'csv':
                    $result = $this->importProductsFromCsv($seller_id, $request);
                    break;
            }
            return $result;
        } catch (\Exception $e) {
            return false;
        }
    }

    public function createImportProductsTemplate($seller_id, Request $request) {
        try {
            $template = ImportProducts::where('seller_id', $seller_id)->first();
            if (!$template) {
                $template = new ImportProducts;
            }
            $template->seller_id = $seller_id;
            $template->import_url = $request['import_link'];
            $template->import_type = $request['import_type'];
            $template->category = $request['category'];
            $template->article = $request['article'];
            $template->name_ru = $request['name_ru'];
            $template->name_ua = $request['name_ua'];
            $template->description_ru = $request['description_ru'];
            $template->description_ua = $request['description_ua'];
            $template->price = $request['price'];
            $template->price_old = $request['price_old'];
            $template->price_rate = $request['price_rate'];
            $template->stock = $request['stock'];
            $template->brand = $request['brand'];
            $template->warranty = $request['warranty'];
            $template->country_origin = $request['country_origin'];
            $template->country_brand = $request['country_brand'];
            $template->photo = $request['photo'];
            $template->update_price = $request['update_price'] ?: false;
            $template->nullify_stock_if_not_found = $request['nullify_stock_if_not_found'] ?: false;
            if ($template->import_type == 'xml') {
                $json = [
                    'product' => $request['product'],
                    'product_category_id' => $request['product_category_id'],
                    'category_category_id_attribute' => $request['category_category_id_attribute'],
                    'param' => $request['param'],
                    'param_key_attribute' => $request['param_key_attribute']
                ];
                $template->additional_JSON = json_encode($json);
            } else if ($template->import_type == 'excel') {
                $json = [
                    'photos_delimiter' => $request['photos_delimiter']
                ];
                $template->additional_JSON = json_encode($json);
            } else if ($template->import_type == 'csv') {
                $json = [
                    'main_delimiter' => $request['main_delimiter'],
                    'photos_delimiter' => $request['photos_delimiter']
                ];
                $template->additional_JSON = json_encode($json);
            }
            $template->save();
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    public function importProductsFromXml($seller_id, Request $request) {
        DB::beginTransaction();
        try {
            $result = true;
            try {
                $xml = simplexml_load_file($request['import_link']);
            } catch (\Exception $e) {
                try {
                    $arrContextOptions = array("ssl"=>array("verify_peer"=>false, "verify_peer_name"=>false));
                    $assertion = file_get_contents($request['import_link'], false, stream_context_create($arrContextOptions));
                    $xml = simplexml_load_string($assertion);
                } catch (\Exception $e) {
                    return false;
                }
            }
            $categories = array();
            foreach ($xml->xpath('//'.$request['category']) as $category)
            {
                $attributes = $category->attributes();
                $id = ((array) $attributes[$request['category_category_id_attribute']])[0];
                $categories[$id] = ((array) $category)[0];
            }

            $currencies = array();
            try {
                foreach ($xml->xpath('//currency') as $currency)
                {
                    $attributes = $currency->attributes();
                    $id = ((array) $attributes['id'])[0];
                    $currencies[$id] = ((array) $attributes['rate'])[0];
                }
            } catch (\Exception $e) { }

            foreach ($xml->xpath('//'.$request['product']) as $prod)
            {
                try {
                    $prodArr = (array) $prod;
                    $product_article = $request['article'] && array_key_exists($request['article'], $prodArr)
                        ? $prodArr[$request['article']] : null;
                    $product_warranty = $request['warranty'] && array_key_exists($request['warranty'], $prodArr)
                        ? $prodArr[$request['warranty']] : null;
                    $product_country_origin = $request['country_origin'] && array_key_exists($request['country_origin'], $prodArr)
                        ? $prodArr[$request['country_origin']] : null;
                    $product_country_brand = $request['country_brand'] && array_key_exists($request['country_brand'], $prodArr)
                        ? $prodArr[$request['country_brand']] : null;

                    // fix warranty for seller №60
                    if (is_array($product_warranty)) {
                        $product_warranty = $product_warranty[0];
                    }

                    foreach ($prod->xpath($request['param']) as $p)
                    {
                        $attributes = $p->attributes();
                        $key = ((array) $attributes[$request['param_key_attribute']])[0];

                        if (!$product_article && ($key == $request['article'] || mb_strtolower($key, 'UTF-8') == 'артикул')) {
                            $product_article = isset(((array) $p)[0]) ? (string) ((array) $p)[0] : (string) $p;
                        }
                        if (!$product_warranty && ($key == $request['warranty']
                            || mb_strtolower($key, 'UTF-8') == 'гарантия' || mb_strtolower($key, 'UTF-8') == 'гарантия, мес')) {
                            $product_warranty = isset(((array) $p)[0]) ? (string) ((array) $p)[0] : (string) $p;
                            if (mb_strtolower($key, 'UTF-8') == 'гарантия, мес') {
                                $product_warranty .= 'мес.';
                            }
                        }
                        if (!$product_country_origin && ($key == $request['country_origin']
                            || mb_strtolower($key, 'UTF-8') == 'страна-производитель' || mb_strtolower($key, 'UTF-8') == 'страна производитель')) {
                            $product_country_origin = isset(((array) $p)[0]) ? (string) ((array) $p)[0] : (string) $p;
                        }
                        if (!$product_country_brand && $key == $request['country_brand']) {
                            $product_country_brand = isset(((array) $p)[0]) ? (string) ((array) $p)[0] : (string) $p;
                        }
                    }

                    // if there are no article use product id as article
                    if(!$product_article) {
                        $attributes = $prod->attributes();
                        $product_article = ((array) $attributes['id'])[0];
                    }

                    $product_article = trim($product_article);

                    $price_rate = (double)$request['price_rate'] > 0 ? (double)$request['price_rate'] : 1;
                    try {
                        $xml_price_rate = (double) $currencies[$prodArr['currencyId']];
                        if ($xml_price_rate > 0) {
                            $price_rate *= $xml_price_rate;
                        }
                    } catch (\Exception $e) { }

                    $product = Product::where('seller_id', $seller_id)
                        ->where('article', $product_article)->first();
                    if ($product) {
                        if ($request['update_price'] && !$product->do_not_update_price) {
                            $product->price =  round((double)$prodArr[$request['price']] * $price_rate);
                            if($request['price_old'] && array_key_exists($request['price_old'], $prodArr)) {
                                $product->price_old = round((double)$prodArr[$request['price_old']] * $price_rate);
                            } else {
                                $product->price_old = $product->price;
                            }
                        }
                        if ($request['stock'] && array_key_exists($request['stock'], $prodArr)) {
                            $product->stock = $prodArr[$request['stock']];
                        } else {
                            $attributes = $prod->attributes();
                            $product->stock = ((array) $attributes['available'])[0] == "true" ? 3: 0;
                        }

                        if ($request['reload_name']) {
                            if(array_key_exists($request['name_ru'], $prodArr)) {
                                $product->name_ru = $prodArr[$request['name_ru']];
                            }
                            if(array_key_exists($request['name_ua'], $prodArr)) {
                                $product->name_ua = $prodArr[$request['name_ua']];
                            }
                        }
                        if ($request['reload_description']) {
                            if(array_key_exists($request['description_ru'], $prodArr)) {
                                $product->description_ru = (string) $prodArr[$request['description_ru']];
                            }
                            if(array_key_exists($request['description_ua'], $prodArr)) {
                                $product->description_ua = (string) $prodArr[$request['description_ua']];
                            }
                        }
                        if ($request['reload_vendor'] && $request['brand'] && array_key_exists($request['brand'], $prodArr)) {
                            $product->brand = $prodArr[$request['brand']];
                        }
                        if ($request['reload_warranty'] && $product_warranty) {
                            $product->warranty = $product_warranty;
                        }
                        if ($request['reload_country_origin'] && $product_country_origin) {
                            $product->country_origin = $product_country_origin;
                        }
                        if ($request['reload_country_brand'] && $product_country_brand) {
                            $product->country_brand = $product_country_brand;
                        }
                        if($request['reload_category'] || $request['reload_all']) {
                            try {
                                $rozetka_category = Category::where('name', 'LIKE', trim($categories[$prodArr[$request['product_category_id']]]))
                                    ->where('market_id', Market::where('market_code', 'rozetka')->first()->id)->first();
                                if ($rozetka_category) {
                                    $product->rozetka_category_id = $rozetka_category->id;
                                }
                            } catch(\Exception $e) { }
                        }
                        $product->is_updated = true;
                        $product->save();

                        // reload all photos
                        if ($request['reload_photos'] && array_key_exists($request['photo'], $prodArr)) {
                            try {
                                $old_photos = ProductPhoto::where('product_id', $product->id)->get();
                                foreach ($old_photos as $old_photo) {
                                    try {
                                        unlink($old_photo->photo);
                                        $old_photo->delete();
                                    } catch (\Exception $e) { }
                                }
                                if (is_array($prodArr[$request['photo']])) {
                                    foreach($prodArr[$request['photo']] as $photo_url) {
                                        try {
                                            $url =  $this->uploadProductPhoto(trim($photo_url), $seller_id, $product->id);
                                            $photo = new ProductPhoto;
                                            $photo->product_id = $product->id;
                                            $photo->photo = $url;
                                            $photo->save();
                                        } catch (\Exception $e) { }
                                    }
                                } else {
                                    $url = $this->uploadProductPhoto(trim($prodArr[$request['photo']]), $seller_id, $product->id);
                                    $photo = new ProductPhoto;
                                    $photo->product_id = $product->id;
                                    $photo->photo = $url;
                                    $photo->save();
                                }
                                $photo = ProductPhoto::where('product_id', $product->id)->first();
                                if($photo) {
                                    ProductPhoto::where('product_id', $product->id)->where('main', 1)->update(['main' => 0]);
                                    $photo->main = true;
                                    $photo->save();
                                }
                            } catch (\Exception $e) {
                                // can't upload photo
                            }
                        }

                        // reload_characteristics
                        if ($request['reload_characteristics']) {
                            $old_chars = ProductCharacteristic::where('product_id', $product->id)->get()->keyBy('id');
                            foreach ($prod->xpath($request['param']) as $p)
                            {
                                try {
                                    $attributes = $p->attributes();
                                    $key = ((array) $attributes[$request['param_key_attribute']])[0];
                                    if ($key != $request['article'] && mb_strtolower($key, 'UTF-8') != 'артикул'
                                        && $key != $request['warranty'] && mb_strtolower($key, 'UTF-8') != 'гарантия'
                                        && mb_strtolower($key, 'UTF-8') != 'гарантия, мес'
                                        && $key != $request['country_origin'] && mb_strtolower($key, 'UTF-8') != 'страна-производитель'
                                        && mb_strtolower($key, 'UTF-8') != 'страна производитель' && $key != $request['country_brand']
                                    ) {
                                        $characteristic = $old_chars->where('key', $key)->first();
                                        if ($characteristic) {
                                            $old_chars->forget($characteristic->id);
                                            $new_value = isset(((array) $p)[0]) ? (string) ((array) $p)[0] : (string) $p;
                                            if ($characteristic->value != $new_value) {
                                                $characteristic->value = $new_value;
                                                $characteristic->save();
                                            }
                                        } else {
                                            $characteristic = new ProductCharacteristic;
                                            $characteristic->product_id = $product->id;
                                            $characteristic->key = $key;
                                            $characteristic->value = isset(((array) $p)[0]) ? (string) ((array) $p)[0] : (string) $p;
                                            $characteristic->save();
                                        }
                                    }
                                } catch (\Exception $e) { }
                            }
                            foreach ($old_chars as $deleted) {
                                $deleted->delete();
                            }
                        }
                        continue;
                    }
                    $product = new Product;
                    $product->seller_id = $seller_id;
                    $product->new = 1;
                    $product->disabled = 1;
                    try {
                        $rozetka_category = Category::where('name', 'LIKE', trim($categories[$prodArr[$request['product_category_id']]]))
                            ->where('market_id', Market::where('market_code', 'rozetka')->first()->id)->first();
                        $product->rozetka_category_id = $rozetka_category ? $rozetka_category->id : 0;
                    } catch(\Exception $e) {
                        $product->rozetka_category_id = 0;
                    }
                    $product->price = round((double) $prodArr[$request['price']] * $price_rate);
                    if($request['price_old'] && array_key_exists($request['price_old'], $prodArr)) {
                        $product->price_old = round((double) $prodArr[$request['price_old']] * $price_rate);
                    } else {
                        $product->price_old = $product->price;
                    }
                    if($request['stock'] && array_key_exists($request['stock'], $prodArr)) {
                        $product->stock = $prodArr[$request['stock']];
                    } else {
                        $attributes = $prod->attributes();
                        $product->stock = ((array) $attributes['available'])[0] == "true" ? 3: 0;
                    }
                    if($request['brand'] && array_key_exists($request['brand'], $prodArr)) {
                        $product->brand = $prodArr[$request['brand']];
                    }
                    if(array_key_exists('keywords', $prodArr)) {
                        $product->keywords = $prodArr['keywords'];
                    }
                    $product->name_ru = $prodArr[$request['name_ru']];
                    if(array_key_exists($request['name_ua'], $prodArr)) {
                        $product->name_ua = $prodArr[$request['name_ua']];
                    } else {
                        $product->name_ua = $product->name_ru;
                    }
                    if(array_key_exists($request['description_ru'], $prodArr))
                        $product->description_ru = (string) $prodArr[$request['description_ru']];
                    if(array_key_exists($request['description_ua'], $prodArr))
                        $product->description_ua = (string) $prodArr[$request['description_ua']];
                    if($product_warranty) $product->warranty = $product_warranty;
                    if($product_country_origin) $product->country_origin = $product_country_origin;
                    if($product_country_brand) $product->country_brand = $product_country_brand;
                    $product->article = $product_article;
                    $product->is_updated = true;
                    $product->save();
                    foreach ($prod->xpath($request['param']) as $p)
                    {
                        try {
                            $attributes = $p->attributes();
                            $key = ((array) $attributes[$request['param_key_attribute']])[0];
                            if ($key != $request['article'] && mb_strtolower($key, 'UTF-8') != 'артикул'
                                && $key != $request['warranty'] && mb_strtolower($key, 'UTF-8') != 'гарантия'
                                && mb_strtolower($key, 'UTF-8') != 'гарантия, мес'
                                && $key != $request['country_origin'] && mb_strtolower($key, 'UTF-8') != 'страна-производитель'
                                && mb_strtolower($key, 'UTF-8') != 'страна производитель' && $key != $request['country_brand']
                            ) {
                                $characteristic = new ProductCharacteristic;
                                $characteristic->product_id = $product->id;
                                $characteristic->key = $key;
                                $characteristic->value = isset(((array) $p)[0]) ? (string) ((array) $p)[0] : (string) $p;
                                $characteristic->save();
                            }
                        } catch (\Exception $e) {
                            //
                        }
                    }
                    if (array_key_exists($request['photo'], $prodArr)) {
                        try {
                            if(is_array($prodArr[$request['photo']])) {
                                foreach($prodArr[$request['photo']] as $photo_url) {
                                    try {
                                        $url = $this->uploadProductPhoto(trim($photo_url), $seller_id, $product->id);
                                        $photo = new ProductPhoto;
                                        $photo->product_id = $product->id;
                                        $photo->photo = $url;
                                        $photo->save();
                                    } catch (\Exception $e) { }
                                }
                            } else {
                                $url =  $this->uploadProductPhoto(trim($prodArr[$request['photo']]), $seller_id, $product->id);
                                $photo = new ProductPhoto;
                                $photo->product_id = $product->id;
                                $photo->photo = $url;
                                $photo->save();
                            }
                            $photo = ProductPhoto::where('product_id', $product->id)->first();
                            if($photo) {
                                ProductPhoto::where('product_id', $product->id)->where('main', 1)->update(['main' => 0]);
                                $photo->main = true;
                                $photo->save();
                            }
                        } catch (\Exception $e) {
                            // can't upload photo
                        }
                    }
                } catch (\Exception $e) {
                    DB::rollBack();
                    return false;
                }
            }
            if($result) {
                DB::commit();
            } else {
                DB::rollBack();
                $this->deleteUselessProductsPhotosDirectories($seller_id);
            }
            $this->checkProductsAvailability($seller_id, $request['nullify_stock_if_not_found'] ?: 0);
            return $result;
        } catch (\Exception $e) {
            DB::rollBack();
            $this->deleteUselessProductsPhotosDirectories($seller_id);
            return false;
        }
    }

    public function importProductsFromExcel($seller_id, Request $request) {
        try {
            if($request['import_file']) {
                $extension = pathinfo($_FILES['import_file']['name'], PATHINFO_EXTENSION);
                $data = file_get_contents($request['import_file']);
            } else if($request['import_link'] && $request['import_link'] !== '') {
                $parts = explode(".", $request['import_link']);
                $extension = end($parts);
                $data = file_get_contents($request['import_link']);
            }
            $path = storage_path().'/imports/'.$seller_id.'/';
            if(!is_dir($path)){
                mkdir($path, 0755, true);
            }
            $f = fopen($path.'import.'.$extension, 'w');
            fwrite($f, $data);
            fclose($f);
            $data = Excel::toArray(null, $path.'import.'.$extension);
            unlink($path.'import.'.$extension);
            return $this->importProductsFromArray($seller_id, $data[0], $request);
        } catch (\Exception $e) {
            return false;
        }
    }

    public function importProductsFromCsv($seller_id, Request $request) {
        try {
            $array = array();
            if (($handle = fopen($request['import_link'], "r")) !== FALSE ) {
                while (($data = fgetcsv($handle, 100000, $request['main_delimiter'])) !== FALSE) {
                    array_push($array, $data);
                }
            }
            return $this->importProductsFromArray($seller_id, $array, $request);
        } catch (\Exception $e) {
            return false;
        }
    }

    public function importProductsFromArray($seller_id, $array, Request $request) {
        DB::beginTransaction();
        try {
            $result = true;
            $headers = $array[0];
            unset($array[0]);
            $params = $headers;
            unset($params[array_search($request['category'], $params)]);
            unset($params[array_search($request['article'], $params)]);
            unset($params[array_search($request['price'], $params)]);
            if (array_search($request['price_old'], $params) >= 0)
                unset($params[array_search($request['price_old'], $params)]);
            if (array_search($request['stock'], $params) >= 0)
                unset($params[array_search($request['stock'], $params)]);
            if (array_search($request['brand'], $params) >= 0)
                unset($params[array_search($request['brand'], $params)]);
            if (array_search($request['warranty'], $params) >= 0)
                unset($params[array_search($request['warranty'], $params)]);
            if (array_search($request['country_origin'], $params) >= 0)
                unset($params[array_search($request['country_origin'], $params)]);
            if (array_search($request['country_brand'], $params) >= 0)
                unset($params[array_search($request['country_brand'], $params)]);
            unset($params[array_search($request['name_ru'], $params)]);
            unset($params[array_search($request['name_ua'], $params)]);
            if (array_search($request['description_ru'], $params) >= 0)
                unset($params[array_search($request['description_ru'], $params)]);
            if (array_search($request['description_ua'], $params) >= 0)
                unset($params[array_search($request['description_ua'], $params)]);
            if (array_search($request['photo'], $params) >= 0)
                unset($params[array_search($request['photo'], $params)]);
            foreach($array as $item) {
                try {
                    $product_article = trim($item[array_search($request['article'], $headers)]);
                    $price_rate = (double)$request['price_rate'] > 0 ? (double)$request['price_rate'] : 1;
                    $product = Product::where('seller_id', $seller_id)->where('article', $product_article)->first();
                    if ($product) {
                        if (($request['update_price'] || $request['reload_all']) && !$product->do_not_update_price) {
                            $product->price = round((double) $item[array_search($request['price'], $headers)] * $price_rate);
                            if (array_search($request['price_old'], $headers) >= 0) {
                                $product->price_old = round((double) $item[array_search($request['price_old'], $headers)] * $price_rate);
                            } else {
                                $product->price_old = $product->price;
                            }
                        }
                        if (array_search($request['stock'], $headers) >= 0) {
                            $product->stock = $item[array_search($request['stock'], $headers)] ?: 0;
                        }
                        if ($request['reload_name'] || $request['reload_all']) {
                            if (array_search($request['name_ua'], $headers) >= 0) {
                                $product->name_ua = $item[array_search($request['name_ua'], $headers)];
                            }
                            if (array_search($request['name_ru'], $headers) >= 0) {
                                $product->name_ru = $item[array_search($request['name_ru'], $headers)];
                            }
                        }
                        if ($request['reload_description'] || $request['reload_all']) {
                            if (array_search($request['description_ua'], $headers) >= 0) {
                                $product->description_ua = $item[array_search($request['description_ua'], $headers)];
                            }
                            if (array_search($request['description_ru'], $headers) >= 0) {
                                $product->description_ru = $item[array_search($request['description_ru'], $headers)];
                            }
                        }
                        if (($request['reload_vendor'] || $request['reload_all']) && array_search($request['brand'], $headers) >= 0) {
                            $product->brand = $item[array_search($request['brand'], $headers)];
                        }
                        if (($request['reload_warranty'] || $request['reload_all']) && array_search($request['warranty'], $headers) >= 0) {
                            $product->warranty = $item[array_search($request['warranty'], $headers)];
                        }
                        if (($request['reload_country_origin'] || $request['reload_all']) && array_search($request['country_origin'], $headers) >= 0) {
                            $product->country_origin = $item[array_search($request['country_origin'], $headers)];
                        }
                        if (($request['reload_country_brand'] || $request['reload_all']) && array_search($request['country_brand'], $headers) >= 0) {
                            $product->country_brand = $item[array_search($request['country_brand'], $headers)];
                        }
                        if (($request['reload_category'] || $request['reload_all']) && array_search($request['category'], $headers) >= 0) {
                            $rozetka_category = Category::where('name', 'LIKE', trim($item[array_search($request['category'], $headers)]))
                                ->where('market_id', Market::where('market_code', 'rozetka')->first()->id)->first();
                            if ($rozetka_category) {
                                $product->rozetka_category_id = $rozetka_category->id;
                            }
                        }

                        $product->is_updated = true;
                        $product->save();

                        // reload all photos
                        if ($request['reload_photos'] || $request['reload_all']) {
                            try {
                                $old_photos = ProductPhoto::where('product_id', $product->id)->get()->keyBy('id');
                                $photosArr = explode($request['photos_delimiter'], $item[array_search($request['photo'], $headers)]);
                                foreach($photosArr as $photoStr) {
                                    try {
                                        $photo_url = trim($photoStr);
                                        $base_url = URL::to('/');
                                        if (substr($photo_url, 0, strlen($base_url)) === $base_url) {
                                            $photo = ProductPhoto::where('product_id', $product->id)
                                                ->where('photo', substr($photo_url, strlen($base_url)+1))->first();
                                            if ($photo) {
                                                $old_photos->forget($photo->id);
                                                continue;
                                            }
                                        }
                                        $url = $this->uploadProductPhoto($photo_url, $seller_id, $product->id);
                                        $photo = new ProductPhoto;
                                        $photo->product_id = $product->id;
                                        $photo->photo = $url;
                                        $photo->save();
                                    } catch (\Exception $e) {}
                                }
                                foreach ($old_photos as $old_photo) {
                                    try {
                                        unlink($old_photo->photo);
                                    } catch (\Exception $e) { }
                                    $old_photo->delete();
                                }
                                $photo = ProductPhoto::where('product_id', $product->id)->first();
                                if($photo) {
                                    ProductPhoto::where('product_id', $product->id)->where('main', 1)->update(['main' => 0]);
                                    $photo->main = true;
                                    $photo->save();
                                }
                            } catch (\Exception $e) {
                                // can't upload photos
                            }
                        }
                        // reload_characteristics
                        if ($request['reload_characteristics'] || $request['reload_all']) {
                            $old_chars = ProductCharacteristic::where('product_id', $product->id)->get()->keyBy('id');
                            foreach ($params as $param) {
                                $value = $item[array_search($param, $headers)];
                                if ($value) {
                                    $characteristic = $old_chars->where('key', $param)->first();
                                    if ($characteristic) {
                                        $old_chars->forget($characteristic->id);
                                        if ($characteristic->value != $value) {
                                            $characteristic->value = $value;
                                            $characteristic->save();
                                        }
                                    } else {
                                        $characteristic = new ProductCharacteristic;
                                        $characteristic->product_id = $product->id;
                                        $characteristic->key = $param;
                                        $characteristic->value = $value;
                                        $characteristic->save();
                                    }
                                }
                            }
                            foreach ($old_chars as $deleted) {
                                $deleted->delete();
                            }
                        }
                        continue;
                    }
                    $product = new Product;
                    $product->seller_id = $seller_id;
                    $product->new = 1;
                    $product->disabled = 1;
                    $rozetka_category = Category::where('name', 'LIKE', $item[array_search($request['category'], $headers)])
                        ->where('market_id', Market::where('market_code', 'rozetka')->first()->id)->first();
                    $product->rozetka_category_id = $rozetka_category ? $rozetka_category->id : 0;
                    $product->article = $product_article;
                    $product->price = round((double) $item[array_search($request['price'], $headers)] * $price_rate);
                    if (array_search($request['price_old'], $headers) >= 0) {
                        $product->price_old = round((double) $item[array_search($request['price_old'], $headers)] * $price_rate);
                    } else {
                        $product->price_old = $product->price;
                    }
                    if (array_search($request['stock'], $headers) >= 0) {
                        $product->stock = $item[array_search($request['stock'], $headers)] ?: 0;
                    }
                    if (array_search($request['brand'], $headers) >= 0) {
                        $product->brand = $item[array_search($request['brand'], $headers)];
                    }
                    if (array_search($request['warranty'], $headers) >= 0)
                        $product->warranty = $item[array_search($request['warranty'], $headers)];
                    if (array_search($request['country_origin'], $headers) >= 0)
                        $product->country_origin = $item[array_search($request['country_origin'], $headers)];
                    if (array_search($request['country_brand'], $headers) >= 0)
                        $product->country_brand = $item[array_search($request['country_brand'], $headers)];
                    $product->name_ru = $item[array_search($request['name_ru'], $headers)];
                    if (array_search($request['name_ua'], $headers) >= 0) {
                        $product->name_ua = $item[array_search($request['name_ua'], $headers)];
                    } else {
                        $product->name_ua = $product->name_ru;
                    }
                    if (array_search($request['description_ru'], $headers) >= 0)
                        $product->description_ru = $item[array_search($request['description_ru'], $headers)];
                    if (array_search($request['description_ua'], $headers) >= 0)
                        $product->description_ua = $item[array_search($request['description_ua'], $headers)];
                    $product->is_updated = true;
                    $product->save();
                    foreach ($params as $param) {
                        $value = $item[array_search($param, $headers)];
                        if($value) {
                            $characteristic = new ProductCharacteristic;
                            $characteristic->product_id = $product->id;
                            $characteristic->key = $param;
                            $characteristic->value = $value;
                            $characteristic->save();
                        }
                    }
                    try {
                        $photosArr = explode($request['photos_delimiter'], $item[array_search($request['photo'], $headers)]);
                        foreach($photosArr as $photoStr) {
                            $photo_url = trim($photoStr);
                            $url = $this->uploadProductPhoto($photo_url, $seller_id, $product->id);
                            $photo = new ProductPhoto;
                            $photo->product_id = $product->id;
                            $photo->photo = $url;
                            $photo->save();
                        }
                        $photo = ProductPhoto::where('product_id', $product->id)->first();
                        if($photo) {
                            ProductPhoto::where('product_id', $product->id)->where('main', 1)->update(['main' => 0]);
                            $photo->main = true;
                            $photo->save();
                        }
                    } catch (\Exception $e) {
                        // can't upload photos
                    }
                } catch (\Exception $e) {
                    $result = false;
                }
            }
            if($result) {
                DB::commit();
            } else {
                DB::rollBack();
                $this->deleteUselessProductsPhotosDirectories($seller_id);
            }
            $this->checkProductsAvailability($seller_id, $request['nullify_stock_if_not_found'] ?: 0);
            return $result;
        } catch (\Exception $e) {
            DB::rollBack();
            $this->deleteUselessProductsPhotosDirectories($seller_id);
            return false;
        }
    }

    private function uploadProductPhoto($url, $seller_id, $product_id) {
        try {
            $content = file_get_contents($url);
            $path = "img/sellers/{$seller_id}/products/{$product_id}";
            $parts = explode(".", $url);
            $extension = end($parts);
            while(true) {
                $filename = $this->generateRandomString(6).".".$extension;
                if(ProductPhoto::where('product_id', $product_id)->where('photo', $path.'/'.$filename)->count() == 0) {
                    break;
                }
            }
            if(!is_dir($path)){
                mkdir($path, 0755, true);
            }
            file_put_contents($path.'/'.$filename, $content);
            return $path.'/'.$filename;
        } catch (\Exception $e) {
            return null;
        }
    }

    private function generateRandomString($length = 6) {
        $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $charactersLength = strlen($characters);
        $randomString = '';
        for ($i = 0; $i < $length; $i++) {
            $randomString .= $characters[rand(0, $charactersLength - 1)];
        }
        return $randomString;
    }

    private function deleteUselessProductsPhotosDirectories($seller_id) {
        try {
            $folder = public_path()."/img/sellers/{$seller_id}/products/";
            $last_product_id = Product::where('seller_id', $seller_id)->orderBy('id', 'desc')->first()->id;
            $objects = scandir($folder);
            foreach ($objects as $object) {
                if ($object != "." && $object != "..") {
                    if (is_dir($folder.$object))
                        if($object > $last_product_id) {
                            $this->deleteDirectoryWithItsContent($folder.$object);
                        }
                }
            }
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    private function deleteDirectoryWithItsContent($dir) {
        try {
            if (is_dir($dir)) {
                $objects = scandir($dir);
                foreach ($objects as $object) {
                    if ($object != "." && $object != "..") {
                        if (is_dir($dir."/".$object))
                            rmdir($dir."/".$object);
                        else
                            unlink($dir."/".$object);
                    }
                }
                rmdir($dir);
            }
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    private function checkProductsAvailability($seller_id, $nullify_stock_if_not_found = -1) {
        $nullify_stock = false;
        $import = ImportProducts::where('seller_id', $seller_id)->first();
        if ($nullify_stock_if_not_found == 1 || ($nullify_stock_if_not_found != 0 && $import && $import->nullify_stock_if_not_found)) {
            $nullify_stock = true;
        }
        if ($nullify_stock) {
            try {
                Product::where('seller_id', $seller_id)->where('is_updated', 0)->update(['stock' => 0]);
                Product::where('seller_id', $seller_id)->where('is_updated', 1)->update(['is_updated' => 0]);
                return true;
            } catch (\Exception $e) {
                return false;
            }
        } else {
            Product::where('seller_id', $seller_id)->update(['is_updated' => 0]);
            return true;
        }
    }

}
