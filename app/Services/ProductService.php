<?php

namespace App\Services;

use App\Models\Brand;
use App\Models\Characteristic;
use App\Models\Market;
use App\Models\Product;
use App\Models\ProductCharacteristic;
use App\Models\ProductChange;
use App\Models\ProductPhoto;
use App\Models\Category;
use App\Services\Markets\RozetkaService;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Input;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;

class ProductService
{
    public function getProducts($approved=1, $new=0, $disabled=0, $filters = null, $seller_id = null, $items_per_page = 50, $need_moderation = -1) {
        $query = Product::query();
        $query->with('seller', 'rozetka_category');
        if ($seller_id) $query->where('seller_id', $seller_id);
        if ($approved != -1) {
            $query->where('approved', $approved);
        }
        if ($new != -1) {
            $query->where('new', $new);
        }
        if ($disabled != -1) {
            $query->where('disabled', $disabled);
        }
        if ($need_moderation == 1) {
            $query->where('prom_category_id', null);
        }
        if ($filters) {
            if ($filters['article']) {
                $query->where('article', 'LIKE', '%'.$filters['article'].'%');
            }
            if ($filters['name']) {
                $query->where('name_ru', 'LIKE', '%'.$filters['name'].'%');
            }
            if ($filters['brand']) {
                $query->where('brand', 'LIKE', '%'.$filters['brand'].'%');
            }
            if ($filters['stock'] || $filters['stock'] == '0') {
                $query->where('stock', $filters['stock']);
            }
            if ($filters['price_min'] || $filters['price_min'] == '0') {
                $query->where('price', '>=', $filters['price_min']);
            }
            if ($filters['price_max']) {
                $query->where('price', '<=', $filters['price_max']);
            }
            if ($filters['category']) {
                $category = $filters['category'];
                $query->whereHas('rozetka_category', function($q) use($category) {
                    $q->where('name', 'LIKE', '%'.$category.'%');
                });
            }
            if ($filters['seller']) {
                $seller = $filters['seller'];
                $query->whereHas('seller', function($q) use($seller) {
                    $q->where('name', 'LIKE', '%'.$seller.'%');
                });
            }

            if ($filters['ids']) {
                $query->whereIn('id', str_getcsv($filters['ids']));
            }
        }
        $query->orderBy('created_at', 'desc');
        if ($items_per_page > 0) {
            $newProducts = $query->paginate($items_per_page)->appends(Input::except('page'));
        } else {
            $newProducts = $query->get();
        }
        return $newProducts;
    }

    public function getProduct($product_id) {
        try {
            $product = Product::where('id', $product_id)
                ->with('seller')
                ->with('rozetka_category')
                ->with('characteristics')
                ->with('photos')
                ->first();
            if($product) {
                return $product;
            } else {
                return null;
            }
        } catch (\Exception $e) {
            return null;
        }
    }

    public function createProduct(Request $request) {
        try {
            $product = new Product;

            $rozetka_category_id = $request['rozetka-category'][count($request['rozetka-category'])-1];
            $new_category = Category::where('category_id', $rozetka_category_id)->first();
            if ($new_category) {
                $product->rozetka_category_id = $new_category->id;
            } else if(count($request['rozetka-category']) >= 2) {
                $rozetka_category_id = $request['rozetka-category'][count($request['rozetka-category'])-2];
                $new_category = Category::where('category_id', $rozetka_category_id)->first();
                if($new_category) {
                    $product->rozetka_category_id = $new_category->id;
                }
            }
            if (!$product->rozetka_category_id) {
                return null;
            }

            if ($request['price']) {
                $product->price = (double)$request['price'] > 0 ? round((double)$request['price']) : 0.01;
                $request['price_old'] && (double)$request['price_old'] > 0 && (double)$request['price_old'] > $product->price
                    ? $product->price_old = round((double)$request['price_old'])
                    : $product->price_old = round((double)$request['price']);
            }
            if ($request['stock']) $product->stock = $request['stock'];
            if ($request['status']) $product->status = $request['status'];
            if ($request['article']) $product->article = $request['article'];
            if ($request['brand']) $product->brand = $request['brand'];
            if ($request['warranty']) $product->warranty = $request['warranty'];
            if ($request['country_origin']) $product->country_origin = $request['country_origin'];
            if ($request['country_brand']) $product->country_brand = $request['country_brand'];
            if ($request['name_ru']) $product->name_ru = $request['name_ru'];
            if ($request['name_ua']) $product->name_ua = $request['name_ua'];
            if ($request['description_ru']) $product->description_ru = $request['description_ru'];
            if ($request['description_ua']) $product->description_ua = $request['description_ua'];
            if ($request['state']) $product->state = $request['state'];
            if ($request['keywords']) $product->keywords = $request['keywords'];
            if ($request['delivery_message']) $product->delivery_message = $request['delivery_message'];
            if ($request['price_promo']) $product->price_promo = $request['price_promo'];
            $product->do_not_update_price = $request['do_not_update_price'] ?: false;
            $product->seller_id = Auth::user()->id;
            $product->new = 1;
            $product->disabled = 1;
            $product->save();

            if($request['ch']) {
                foreach ($request['ch'] as $ch) {
                    if ($ch['key']) {
                        $characteristic = new ProductCharacteristic;
                        $characteristic->key = $ch['key'];
                        $characteristic->value = $ch['value'] ? $ch['value'] : '';
                        $characteristic->product_id = $product->id;
                        $characteristic->save();
                    }
                }
            }

            foreach ($request->all() as $key => $value) {
                if(substr( $key, 0, 13 ) === "product-photo" ) {
                    try {
                        if (preg_match('/^data:image\/(\w+);base64,/', $value, $type)) {
                            $value = substr($value, strpos($value, ',') + 1);
                            $type = strtolower($type[1]); // jpg, png, gif
                            $value = base64_decode($value);
                            $path = "img/sellers/{$product->seller_id}/products/{$product->id}/";
                            while(true) {
                                $filename = $this->generateRandomString(6).".".$type;
                                if($product->photos()->where('photo', $path.$filename)->count() == 0) {
                                    break;
                                }
                            }
                            if(!is_dir($path)){
                                mkdir($path, 0755, true);
                            }
                            file_put_contents($path.$filename, $value);
                            $newPhoto = new ProductPhoto;
                            $newPhoto->product_id = $product->id;
                            $newPhoto->photo = $path.$filename;
                            if ($request['main_photo'] && $key == $request['main_photo']) {
                                $newPhoto->main = true;
                            } else {
                                $newPhoto->main = false;
                            }
                            $product->photos()->saveMany([$newPhoto]);
                        }
                    } catch (\Exception $e) {}
                }
            }

            return $product;
        } catch (\Exception $e) {
            return null;
        }
    }

    public function updateProduct($product_id, Request $request) {
        try {
            $product = $this->getProduct($product_id);

            /* if(get_class(Auth::user()) == 'App\Models\Seller') {
                if ($request['price'] && (double)$request['price'] > 0 && $request['price'] != $product->price) {
                    $product->price = round((double)$request['price']);
                    $request['price_old'] && (double)$request['price_old'] > 0 && (double)$request['price_old'] > $product->price
                        ? $product->price_old = round((double)$request['price_old'])
                        : $product->price_old = round((double)$request['price']);
                } else if($request['price_old'] && (double)$request['price_old'] > 0  && $request['price_old'] <= $product->price) {
                    $product->price_old = $product->price;
                } else if($request['price_old'] && (double)$request['price_old'] > 0  && $request['price_old'] < $product->price_old) {
                    $product->price_old = round((double)$request['price_old']);
                } else if($request['price_old'] && (double)$request['price_old'] > 0  && $request['price_old'] != $product->price_old) {
                    return redirect('/seller/products/'.$product->id)->with(array('message_lang_ref'=> 'auth.fake_product_price_message'));
                }
            } else {
                if ($request['price']) $product->price = round((double)$request['price']);
                if ($request['price_old']) $product->price_old = round((double)$request['price_old']);
            } */
            if ($request['price']) $product->price = round((double)$request['price']);
            if ($request['price_old']) $product->price_old = round((double)$request['price_old']);
            $product->price_promo = ($request['price_promo'] && $request['price_promo'] < $product->price) ? $request['price_promo'] : null;
            if ($request['stock'] || $request['stock'] == 0) $product->stock = $request['stock'];
            if ($request['status']) $product->status = $request['status'];
            if ($request['article']) $product->article = $request['article'];
            if ($request['brand']) $product->brand = $request['brand'];
            if ($request['warranty']) $product->warranty = $request['warranty'];
            if ($request['country_origin']) $product->country_origin = $request['country_origin'];
            if ($request['country_brand']) $product->country_brand = $request['country_brand'];
            if ($request['name_ru']) $product->name_ru = $request['name_ru'];
            if ($request['name_ua']) $product->name_ua = $request['name_ua'];
            if ($request['description_ru']) $product->description_ru = $request['description_ru'];
            if ($request['description_ua']) $product->description_ua = $request['description_ua'];
            if ($request['state']) $product->state = $request['state'];
            if ($request['keywords']) $product->keywords = $request['keywords'];
            if ($request['delivery_message']) $product->delivery_message = $request['delivery_message'];
            $product->do_not_update_price = $request['do_not_update_price'] ?: false;
            $product->disabled = $request['disabled'] ?: false;

            $rozetka_category_id = $request['rozetka-category'][count($request['rozetka-category'])-1];
            $new_category = Category::where('category_id', $rozetka_category_id)->first();
            if ($new_category) {
                $product->rozetka_category_id = $new_category->id;
            } else if(count($request['rozetka-category']) >= 2) {
                $rozetka_category_id = $request['rozetka-category'][count($request['rozetka-category'])-2];
                $new_category = Category::where('category_id', $rozetka_category_id)->first();
                if($new_category) {
                    $product->rozetka_category_id = $new_category->id;
                }
            }
            if (!$product->rozetka_category_id) {
                return null;
            }

            // only Admin can approve product :)
            if(get_class(Auth::user()) == 'App\Models\Admin') {
                $product->approved = $request['approved'] ?: false;
            }

            if(!$product->approved && !$product->new) {
                $product->new = true;
                $product->comment = '';
            }

            if($request['ch']) {
                $old_chars = ProductCharacteristic::where('product_id', $product_id)->get()->keyBy('id');
                foreach ($request['ch'] as $ch) {
                    if ($ch['key']) {
                        $characteristic = $old_chars->where('key', $ch['key'])->first();
                        if ($characteristic) {
                            $old_chars->forget($characteristic->id);
                            $new_value = $ch['value'] ? $ch['value'] : '';
                            if ($characteristic->value != $new_value) {
                                $characteristic->value = $new_value;
                                $characteristic->save();
                            }
                        } else {
                            $characteristic = new ProductCharacteristic;
                            $characteristic->key = $ch['key'];
                            $characteristic->value = $ch['value'] ? $ch['value'] : '';
                            $characteristic->product_id = $product->id;
                            $characteristic->save();
                        }
                    }
                }
                foreach ($old_chars as $deleted) {
                    $deleted->delete();
                }
            }
            if ($request['main_photo']) {
                $photo = $product->photos()->where('photo', $request['main_photo'])->first();
                if($photo) {
                    $product->photos()->where('main', 1)->update(['main' => 0]);
                    $photo->main = true;
                    $photo->save();
                }
            }

            $product->save();

            // update prom category
            if ($request['prom-category']) {
                $prom_category_id = $request['prom-category'][count($request['prom-category'])-1];
                $new_prom_category = Category::where('category_id', $prom_category_id)->first();
                if (!$new_prom_category && count($request['prom-category']) >= 2) {
                    $prom_category_id = $request['prom-category'][count($request['prom-category'])-2];
                    $new_prom_category = Category::where('category_id', $prom_category_id)->first();
                }
                if ($new_prom_category) {
                    Product::where('rozetka_category_id', $product->rozetka_category_id)->update([
                        'prom_category_id' => $new_prom_category->id
                    ]);
                }
            }

            return $product;
        } catch (\Exception $e) {
            return null;
        }
    }

    public function copyProduct($product_id) {
        try {
            $product = Product::where('id', $product_id)->first();
            if ($product) {
                while (true) {
                    $new_article = $this->generateRandomString(12);
                    if(Product::where('seller_id', $product->seller_id)
                            ->where('article', $new_article)->count() == 0) break;
                }
                $new_product = $product->replicate();
                $new_product->article = $new_article;
                $new_product->new = true;
                $new_product->approved = false;
                $new_product->disabled = true;
                $new_product->save();

                $characteristics = ProductCharacteristic::where('product_id', $product->id)->get();
                foreach ($characteristics as $ch) {
                    $new_ch = $ch->replicate();
                    $new_ch->product_id = $new_product->id;
                    $new_ch->save();
                }

                // copy photos
                $photos = ProductPhoto::where('product_id', $product->id)->get();
                foreach ($photos as $photo) {
                    try {
                        $new_photo = $photo->replicate();
                        $file = file_get_contents($new_photo->photo);
                        $new_photo->product_id = $new_product->id;
                        $new_photo->photo = str_replace('/'.$product->id.'/', '/'.$new_product->id.'/', $photo->photo);
                        $new_photo->save();

                        $path = substr($new_photo->photo, 0, strpos($new_photo->photo, '/'.$new_product->id.'/')).'/'.$new_product->id.'/';
                        if(!is_dir($path)){
                            mkdir($path, 0755, true);
                        }
                        file_put_contents($new_photo->photo, $file);
                    } catch (\Exception $e) {}
                }

                return $new_product->id;
            } else {
                return null;
            }
        } catch (\Exception $e) {
            return null;
        }
    }

    public function deleteProduct($product_id) {
        try {
            $seller_id = Product::where('id', $product_id)->first()->seller_id;
            Product::where('id', $product_id)->delete();
            ProductCharacteristic::where('product_id',$product_id)->delete();
            foreach (ProductPhoto::where('product_id', $product_id)->get() as $photo) {
                unlink($photo->photo);
                $photo->delete();
            }
            $folder = public_path()."/img/sellers/{$seller_id}/products/{$product_id}";
            $this->deleteDirectoryWithItsContent($folder);
            ProductChange::where('product_id', $product_id)->delete();
            return 1;
        } catch (\Exception $e) {
            return 0;
        }
    }

    public function deleteAllProductsWithSeller($seller_id) {
        foreach (Product::where('seller_id', $seller_id)->get() as $product) {
            $this->deleteProduct($product->id);
        }
    }

    public function uploadProductPhoto($product_id, Request $request) {
        $json = array();
        try {
            $product = $this->getProduct($product_id);
            if(!$product) {
                $json['error'] = 'Product with id '.$product_id.' not exists';
                json_encode($json);
            }
            $extension = pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION);
            $path = "img/sellers/{$product->seller_id}/products/{$product_id}";
            while (true) {
                $filename = $this->generateRandomString(6).".".$extension;
                if($product->photos()->where('photo', $path.'/'.$filename)->count() == 0) {
                    break;
                }
            }
            $request->file('photo')->move($path, $filename);
            $newPhoto = new ProductPhoto;
            $newPhoto->product_id = $product_id;
            $newPhoto->photo = $path.'/'.$filename;
            $newPhoto->main = 0;
            $newPhoto->save(); // $product->photos()->saveMany([$newPhoto]);
            $json['path'] = $path.'/'.$filename;
        } catch (\Exception $e) {
            $json['error'] = 'Caught exception: '.$e->getMessage();
        }
        return json_encode($json);
    }

    public function deleteProductPhoto($product_id, Request $request) {
        $json = array();
        try {
            if ($request->photo) {
                $photo = ProductPhoto::where('product_id', $product_id)->where('photo', $request->photo)->first();
                if ($photo) {
                    $photo->delete();
                    unlink($request->photo);
                    $json['photo'] = $request->photo;
                } else {
                    $json['error'] = 'photo not exists';
                }
            }  else {
                $json['error'] = 'photo field is required';
            }
        } catch (\Exception $e) {
            $json['error'] = 'Caught exception: '.$e->getMessage();
        }
        return json_encode($json);
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

    public function getCategories($market = 'rozetka', $onlyMain = true, $pattern = null, $limit = null) {
        $query = Category::query()->with('children', 'parent');
        if($market) {
            $query->where('market_id', Market::where('market_code', $market)->first()->id);
        }
        if($onlyMain) {
            $query->where('parent_id', 0);
        }
        if($pattern) {
            $query->where('name', 'like', $pattern.'%');
        }
        if($limit) {
            $query->take($limit);
        }
        return $query->get();
    }

    public function findCategory($pattern, $market = 'rozetka') {
        $query = Category::query();
        if($market) {
            $query->where('market_id', Market::where('market_code', $market)->first()->id);
        }
        $query->where('name', 'like', $pattern.'%');
        return $query->first();
    }

    public function getCategoryPath($category_id, $market_id = 1) {
        $arr = array();
        $category = Category::where('id', $category_id)->first();
        if ($category) {
            $market_id = $category->market_id;
            $arr[0][0] = [
                'id' => $category->id,
                'category_id' => $category->category_id,
                'name' => $category->name,
                'selected' => true
            ];
            $parent_id = $category->parent_id;
            $category_id = $category->id;
            $i = 0;
            while (true) {
                $category_sisters = Category::where('parent_id', $parent_id)->where('market_id', $market_id)->get();
                $j = 1;
                foreach ($category_sisters as $sister) {
                    if ($sister->id != $category_id) {
                        $arr[$i][$j++] = [
                            'id' => $sister->id,
                            'category_id' => $sister->category_id,
                            'name' => $sister->name,
                            'selected' => false
                        ];
                    }
                }
                if ($parent_id == 0) {
                    break;
                } else {
                    $parent = Category::where('category_id', $parent_id)->where('market_id', $market_id)->first();
                    if (!$parent) break;
                    $arr[++$i][0] = [
                        'id' => $parent->id,
                        'category_id' => $parent->category_id,
                        'name' => $parent->name,
                        'selected' => true
                    ];
                    $category_id = $parent->id;
                    $parent_id = $parent->parent_id;
                }
            }
            $children = Category::where('parent_id', $category->category_id)->where('market_id', $market_id)->get();
            if (count($children) > 0) {
                $items = array();
                foreach ($children as $child) {
                    array_push($items, [
                        'id' => $child->id,
                        'category_id' => $child->category_id,
                        'name' => $child->name,
                        'selected' => false
                    ]);
                }
                array_unshift($arr, $items);
            }
            return array_reverse($arr);
        } else {
            $j = 0;
            $root_categories = Category::where('parent_id', 0)->where('market_id', $market_id)->get();
            foreach ($root_categories as $root_category) {
                $arr[0][$j++] = [
                    'id' => $root_category->id,
                    'category_id' => $root_category->category_id,
                    'name' => $root_category->name,
                    'selected' => false
                ];
            }
            return $arr;
        }
    }

    public function getCategoryChildren($parent_id, $market_id = 1)
    {
        try {
            $result= array();
            $categories = Category::where('parent_id', $parent_id)->where('market_id', $market_id)->get();
            for($i = 0; $i < count($categories); $i++) {
                $result[$i] = [
                    'category_id' => $categories[$i]->category_id,
                    'name' => $categories[$i]->name
                ];
            }
            return $result;
        } catch (\Exception $e) {
            return array();
        }
    }

    private function deleteDirectoryWithItsContent($dir) {
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
    }

    public function getProductsCommissions($products)
    {
        $result = array();
        foreach ($products as $product) {
            $result[$product->id] = (new OrderService())->getCategoryCommissionSize($product->rozetka_category_id);
        }
        return $result;
    }

    public function getBrands($pattern, $limit = 5)
    {
        if($pattern) {
            return Brand::where('name', 'like', $pattern.'%')->take($limit)->get()->toArray();
        } else return array();
    }

    public function getCharacteristicKeys($pattern, $market = null, $category_id = null, $limit = null)
    {
        $query = Characteristic::query();
        if($market) {
            $m = Market::where('market_code', $market)->first();
            if($m) {
                $query->where('market_id', $m->id);
            }
            if($category_id && ((integer)$category_id > 0)) {
                $c = Category::where('category_id', $category_id)->first();
                if($c) {
                    $query->where('category_id', $c->id);
                }
            }
        }
        if ($pattern && strlen($pattern) > 0) {
            $query->where('name', 'like', $pattern.'%');
        }
        $query->select('name')->groupBy('name');
        if($limit) $query->take($limit);
        return $query->get()->toArray();
    }

    public function getCharacteristicValues($key = null, $market = null, $category_id = null, $pattern = null)
    {
        $query = Characteristic::query();
        if($market) {
            $m = Market::where('market_code', $market)->first();
            if($m) {
                $query->where('market_id', $m->id);
            }
            if($category_id && ((integer)$category_id > 0)) {
                $c = Category::where('category_id', $category_id)->first();
                if($c) {
                    $query->where('category_id', $c->id);
                }
            }
        }
        if($key && strlen($key) > 0) {
            $query->where('name', $key);
        }
        $query->whereNotNull('value_name');
        if ($pattern) {
            $query->where('value_name', 'LIKE', $pattern.'%');
            $query->limit(5);
        } else {
            $query->limit(100);
        }
        return $query->get()->toArray();
    }

    public function getCategoryAttributes($category_id, $market = 'rozetka') {
        if($market == 'rozetka') {
            $mService = new RozetkaService();
        } else {
            return null;
        }
        return $mService->getCategoryAttributes($category_id);
    }

    public function deleteProducts($items) {
        foreach ($items as $item) {
            Product::where('id', $item)->delete();
        }
    }

    public function moderateProducts($items, $is_accepted, $declined_description = null) {
        foreach ($items as $item) {
            $product = Product::where('id', $item)->first();
            if ($product) {
                $product->new = 0;
                $product->approved = $is_accepted;
                if(!$product->approved && $declined_description) {
                    $product->comment = $declined_description;
                }
                $product->save();
            }
        }
    }

    public function editProducts($items, $request) {
        $products_edited = array();
        $products_not_edited = array();
        foreach ($items as $item) {
            try {
                $product = Product::where('id', $item)->first();

                if ($request['update_price']) {
                    $product->do_not_update_price = $request['update_price'] == 'false' ? 1 : 0;
                }

                $price = $request['product_price'];
                $price_type = $request['product_price_type'];
                $price_operation = $request['product_price_operation'];
                if ($price && floatval($price) > 0 && $price_type && $price_operation) {
                    switch ($price_operation) {
                        default:
                        case '+':
                            $product->price += $price_type == 'percent' ? round($product->price * floatval($price)/100 ,2): round(floatval($price),2);
                            break;
                        case '-':
                            $new_price = $product->price - ($price_type == 'percent' ? round($product->price * floatval($price)/100 ,2): round(floatval($price),2));
                            if ($new_price > 0) {
                                $product->price = $new_price;
                            }
                            break;
                        case '=':
                            $product->price = round(floatval($price), 2);
                            break;
                    }
                }

                $stock = $request['product_stock'];
                $stock_operation = $request['product_stock_operation'];
                if (intval($stock) >= 0 && $stock_operation) {
                    switch ($stock_operation) {
                        default:
                        case '+':
                            if ($stock != 0) {
                                $product->stock += $stock;
                            }
                            break;
                        case '-':
                            if ($stock != 0) {
                                $new_stock = $product->stock - $stock;
                                if ($new_stock > 0) {
                                    $product->stock = $new_stock;
                                }
                            }
                            break;
                        case '=':
                            $product->stock = $stock;
                            break;
                    }
                }

                $state = $request['product_state'];
                if ($state && ($state == 'new' || $state == 'used' ||$state == 'refurbished')) {
                    $product->state = $state;
                }

                $category_id = $request['product_rozetka_category_id'];
                if ($category_id) {
                    $category = Category::where('category_id', $category_id)->where('market_id', Market::where('market_code', 'rozetka')->first()->id)->first();
                    if ($category) {
                        $product->rozetka_category_id = $category->id;
                    }
                }

                $brand = $request['product_brand'];
                if ($brand) {
                    $product->brand = $brand;
                }

                $delivery_message = $request['product_delivery_message'];
                if ($delivery_message) {
                    $product->delivery_message = $delivery_message;
                }
                $warranty = $request['product_warranty'];
                if ($warranty) {
                    $product->warranty = $warranty;
                }
                $country_origin = $request['product_country_origin'];
                if ($country_origin) {
                    $product->country_origin = $country_origin;
                }
                $country_brand = $request['product_country_brand'];
                if ($country_brand) {
                    $product->country_brand = $country_brand;
                }

                if ($request['hide_product']) {
                    $product->disabled = $request['hide_product'] == 'true' ? 1 : 0;
                }
                $product->save();

                array_push($products_edited, $item);
            } catch (\Exception $e) {
                array_push($products_not_edited, $item);
            }
        }
        return array(
            'edited' => $products_edited,
            'failed' => $products_not_edited
        );
    }

    public function sendProductsToModeration($items) {
        foreach ($items as $item) {
            $pr = Product::where('id', $item)->first();
            if ($pr) {
                $pr->new = 1;
                $pr->approved = 0;
                $pr->disabled = 0;
                $pr->save();
            }
        }
    }

    public function exportProducts($items, $seller_id, $type = 'excel') {
        if ($type == 'excel') {
            try {
                $public_path = '/seller/storage/export/';
                $filename = 'export.xlsx';
                $storage_path = 'app/sellers/'.$seller_id.'/exports/';
                $reader = new \PhpOffice\PhpSpreadsheet\Reader\Xlsx();
                $spreadsheet = $reader->load(Config::get('app.excel_template_link'));
                $array_data = [
                    [
                        'Категорія',
                        'Артикул',
                        'Назва (рос.)',
                        'Назва (укр.)',
                        'Опис (рос.)',
                        'Опис (укр.)',
                        'Виробник',
                        'Ціна',
                        'Стара ціна',
                        'Наявність',
                        'Гарантія',
                        'Країна-виробник товару',
                        'Країна реєстрації бренду',
                        'Фотографії'
                    ]
                ];
                $first_ch_index = count($array_data[0]);
                for ($i = 0; $i < count($items); $i++) {
                    $product = Product::with('rozetka_category', 'characteristics', 'photos')->where('id', $items[$i])->first();
                    $category = $product->rozetka_category()->first();
                    if (!$product) {
                        continue;
                    }
                    $photos = '';
                    foreach ($product->photos()->get() as $photo) {
                        $photos .= url($photo->photo).', ';
                    }
                    $photos = rtrim($photos, ', ');
                    array_push($array_data, [
                        $category ? $category->name : '',
                        $product->article,
                        $product->name_ru,
                        $product->name_ua,
                        $product->description_ru,
                        $product->description_ua,
                        $product->brand,
                        $product->price,
                        $product->price_old,
                        $product->stock,
                        $product->warranty,
                        $product->country_origin,
                        $product->country_brand,
                        $photos
                    ]);
                    foreach ($product->characteristics()->get() as $ch) {
                        $ch_index = array_search($ch->key, $array_data[0]);
                        if (!$ch_index) {
                            array_push($array_data[0], $ch->key);
                            $ch_index = array_search($ch->key, $array_data[0]);
                        }
                        for ($j = $first_ch_index; $j < $ch_index; $j++) {
                            if (isset($array_data[$i+1])) {
                                if (!array_key_exists($j, $array_data[$i + 1])) {
                                    $array_data[$i + 1][$j] = NULL;
                                }
                            }
                        }
                        $array_data[$i+1][$ch_index] = $ch->value;
                    }
                }
                $spreadsheet->getActiveSheet()->fromArray($array_data, NULL, 'A1');
                $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
                if (!is_dir('../storage/'.$storage_path)){
                    mkdir('../storage/'.$storage_path, 0755, true);
                }
                $writer->save('../storage/'.$storage_path.$filename);
                return $public_path.$filename;
            } catch (\Exception $e) {
                return null;
            }
        } else {
            return null; // not implemented!
        }
    }

    public function findProduct($pattern, $limit = null, $expand = null, $id = null, $approved = 1, $seller_id = null, $only_active = false) {
        $query = Product::query();
        if ($id && Product::where('id', $id)->count() > 0) {
            $query->where('id', $id);
        } else {
            $query->where(function ($q) use($pattern) {
                $q->where('article', 'like', '%'.$pattern.'%');
                $q->orWhere('name_ru', 'like', '%'.$pattern.'%');
                $q->orWhere('name_ua', 'like', '%'.$pattern.'%');
            });
        }
        if ($approved != -1) {
            $query->where('approved', $approved);
        }
        if ($only_active == true) {
            $query->where('is_active_at_rozetka', 1);
        }
        if ($seller_id) {
            $query->where('seller_id', $seller_id);
        }
        if ($limit) {
            $query->take($limit);
        }
        $query->orderBy('name_ru', 'asc');
        if ($expand == 'commission') {
            $products = $query->get();
            foreach ($products as $product) {
                $product->commission = (new OrderService())->getCategoryCommissionSize($product->rozetka_category_id, $product->seller_id);
            }
            return $products;
        } else {
            return $query->get();
        }
    }

}
