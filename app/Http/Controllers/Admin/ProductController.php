<?php

namespace App\Http\Controllers\Admin;

use App\Models\Category;
use App\Models\ImportProducts;
use App\Models\Market;
use App\Models\Product;
use App\Services\ImportService;
use App\Services\MessageService;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Services\ProductService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Route;

class ProductController extends Controller
{
    private $productService;

    public function __construct()
    {
        $this->productService = new ProductService();
    }

    public function getNewProducts(Request $request)
    {
        $products = $this->productService->getProducts(0,1,0, $request);
        $categories = Category::where('parent_id', 0)->get();
        return view('admin.products.new_product.index', compact('products', 'categories'));
    }

    public function editNewProduct($product_id)
    {
        $product = $this->productService->getProduct($product_id);
        if($product) {
            $categories =  $this->productService->getCategoryPath($product->rozetka_category_id);
            return view('admin.products.new_product.edit', compact('product', 'categories'));
        } else {
            return abort(404);
        }
    }

    public function verificationNewProduct(Request $request)
    {
        $this->validate($request, [
            'id' => 'required',
            'declined' => 'required',
        ]);
        $request->request->add(['approved' => !$request['declined']]);
        $product = $this->productService->getProduct($request['id']);
        if ($product) {
			$updated_product = $this->productService->updateProduct($product->id, $request);
			$product->new = 0;
            if ($request['declined']) {
                $product->approved = 0;
                if ($request['declined_description']) {
                    $product->comment = $request['declined_description'];
                }
                $product->save();
                return redirect('admin/products/new'); 
            } else if ($updated_product) {
                $product->approved = 1;
                $product->save();
                return redirect('admin/products/new'); 
            } 
			if (!$updated_product) {
				if (!$product->rozetka_category_id) {
	            	return Redirect::back()->with(array('message_lang_ref' => 'common.error_product_without_category'));
		        } else {
		            return Redirect::back()->with(array('message_lang_ref' => 'common.product_not_updated'));
		        }
			}
        } else {
        	return abort(404);
        }
    }

    public function getProducts(Request $request)
    {
        $products = $this->productService->getProducts(1,-1, 0, $request);
        $categories =  Category::where('parent_id', 0)->get();
        return view('admin.products.index', compact('products', 'categories'));
    }

    public function getProductsWithoutCategory(Request $request)
    {
        $products = $this->productService->getProducts(1,0, 0, $request, null, 50, 1);
        $categories = Category::where('parent_id', 0)->get();
        return view('admin.products.index', compact('products', 'categories'));
    }

    public function getDeclinedProducts(Request $request)
    {
        $products = $this->productService->getProducts(0,0, 0, $request);
        $categories = Category::where('parent_id', 0)->get();
        return view('admin.products.index', compact('products', 'categories'));
    }

    public function getDisabledProducts(Request $request)
    {
        $products = $this->productService->getProducts(-1,0, 1, $request);
        $categories = Category::where('parent_id', 0)->get();
        return view('admin.products.index', compact('products', 'categories'));
    }

    public function getNotModeratedProducts(Request $request)
    {
        $products = $this->productService->getProducts(-1,1, 1, $request);
        $categories = Category::where('parent_id', 0)->get();
        return view('admin.products.index', compact('products', 'categories'));
    }

    public function editProduct($product_id)
    {
        $product = $this->productService->getProduct($product_id);
        if($product) {
        	$categories = $this->productService->getCategoryPath($product->rozetka_category_id);
            $prom_categories = $this->productService->getCategoryPath($product->prom_category_id, Market::where('market_code', 'prom')->first()->id);
        	return view('admin.products.edit', compact('product', 'categories', 'prom_categories'));
        } else {
        	return abort(404);
        }
    }

    public function updateProduct(Request $request, $product_id)
    {
        $product = $this->productService->updateProduct($product_id, $request);
        if ($product) {
            return Redirect::back()->with(array('message_lang_ref'=> 'common.model_updated'));
        }
        else {
            return Redirect::back()->with(array('message_lang_ref' => 'common.product_not_updated', 'type' => 'error'));
        }
    }

    public function deleteProduct($product_id)
    {
        $product = $this->productService->getProduct($product_id);
        $type = ($product->approved) ? 'approved' : 'declined';
        $this->productService->deleteProduct($product_id);
        return redirect('admin/products/'.$type);
    }

    public function getCategoryChildren(Request $request, $parent_id)
    {
        if ($request['market']) {
            $market = Market::where('market_code', $request['market'])->first();
            if ($market) {
                return $this->productService->getCategoryChildren($parent_id, $market->id);
            }
        }
        return $this->productService->getCategoryChildren($parent_id);
    }

    public function deleteProductPhoto($product_id, Request $request)
    {
        return $this->productService->deleteProductPhoto($product_id, $request);
    }

    public function uploadProductPhoto($product_id, Request $request)
    {
        return $this->productService->uploadProductPhoto($product_id, $request);
    }

    public function editImportTemplate($seller_id)
    {
        $template = ImportProducts::where('seller_id', $seller_id)->first();
        if(!$template) {
            $has_template = false;
            $template = new ImportProducts;
            $template->import_url = 'http://your.link/file.xml';
            $template->import_type = 'xml';
            $template->category = 'category';
            $template->article = 'Артикул';
            $template->name_ru = 'name';
            $template->name_ua = 'name';
            $template->description_ru = 'description';
            $template->description_ua = 'description';
            $template->price = 'price';
            $template->price_old = 'price';
            $template->price_rate = 1;
            $template->stock = 'stock_quantity';
            $template->brand = 'vendor';
            $template->photo = 'picture';
            $template->warranty = 'Гарантия';
            $template->country_origin = 'Страна-производитель товара';
            $template->country_brand = 'Страна регистрации бренда';
            $template->update_price = true;
            $template->additional_JSON = '{
                    "product": "offer",
                    "product_category_id": "categoryId",
                    "category_category_id_attribute": "id",
                    "param": "param",
                    "param_key_attribute": "name"
                }';
        } else {
            $has_template = true;
        }
        if($template->additional_JSON == '') $template->additional_JSON = '{}';
        $additional = json_decode($template->additional_JSON);
        return view('admin.products.import', compact('has_template','template', 'additional', 'seller_id'));
    }

    public function importProducts($seller_id, Request $request)
    {
        $this->validate($request, [
            'category'    => 'required',
            'article'    => 'required',
            'name_ru'    => 'required',
            'price'    => 'required',
            'brand'    => 'required',
        ]);
        if($request['import_type'] != 'excel' || !$request['import_file']) {
            $this->validate($request, [
                'import_link'    => 'url|required',
            ]);
        }
        $result = (new ImportService())->importProducts($seller_id, $request);
        if ($result) {
            return redirect('admin/products/not-moderated');
        } else {
            return redirect('admin/products/import/'.$seller_id)->with(array('message_lang_ref'=> 'seller.cant_import'));
        }
    }

    public function getBrands($pattern)
    {
        return $this->productService->getBrands($pattern);
    }

    public function getCategories(Request $request) {
        $limit = (int) $request['limit'] > 0 ? (int) $request['limit'] : 5;
        return $this->productService->getCategories($request['market'], false, $request['pattern'], $limit)->toArray();
    }

    public function getCategoryPath(Request $request) {
        $category = $this->productService->findCategory($request['pattern'], $request['market']);
        if($category) {
            return $this->productService->getCategoryPath($category->id);
        } else {
            return array();
        }
    }

    public function getCharacteristicKeys(Request $request)
    {
        $limit = $request['limit'] == 'all' ? null : ((int) $request['limit'] > 0 ? (int) $request['limit'] : 5);
        return $this->productService->getCharacteristicKeys($request['pattern'], $request['market'], $request['category_id'], $limit);
    }

    public function getCharacteristicValues(Request $request)
    {
        return $this->productService->getCharacteristicValues($request['key'], $request['market'], $request['category_id'], $request['pattern']);
    }

    public function getCategoryAttributes($category_id, Request $request) {
        return $this->productService->getCategoryAttributes($category_id, $request['market'] ? $request['market'] : 'rozetka');
    }

    public function deleteImportTemplate($seller_id) {
        $template = ImportProducts::where('seller_id', $seller_id);
        $json = array();
        if($template) {
            $template->delete();
            $json['status'] = 'deleted';
        } else {
            $json['status'] = 'Not Found';
        }
        return json_encode($json);
    }

    public function deleteProducts(Request $request) {
        if ($request['items'] == 'all') {
            $approved = -1;
            $new = -1;
            $disabled = -1;
            $route = app('router')->getRoutes()->match(app('request')->create(url()->previous()))->getName();
            switch ($route) {
                case 'admin.products.new.index':
                    $approved = 0;
                    $new = 1;
                    $disabled = 0;
                    break;
                case 'admin.products.index':
                    $approved = 1;
                    $new = 0;
                    $disabled = 0;
                    break;
                case 'admin.products.declined.index':
                    $approved = 0;
                    $new = 0;
                    $disabled = 0;
                    break;
                case 'admin.products.disabled.index':
                    $new = 0;
                    $disabled = 1;
                    break;
                case 'admin.products.not-moderated.index':
                    $new = 1;
                    $disabled = 1;
                    break;
                default:
                    break;
            }
            $items = array();
            foreach ($this->productService->getProducts($approved, $new, $disabled, $request, null, -1) as $product) {
                array_push($items, $product->id);
            }
            $this->productService->deleteProducts($items);
        } else {
            $this->productService->deleteProducts(json_decode($request['items']));
        }
        $json = array();
        $json['status'] = 'deleted';
        return json_encode($json);
    }

    public function editProducts(Request $request) {
        if ($request['items'] == 'all') {
            $approved = -1;
            $new = -1;
            $disabled = -1;
            $route = app('router')->getRoutes()->match(app('request')->create(url()->previous()))->getName();
            switch ($route) {
                case 'admin.products.new.index':
                    $approved = 0;
                    $new = 1;
                    $disabled = 0;
                    break;
                case 'admin.products.index':
                    $approved = 1;
                    $new = 0;
                    $disabled = 0;
                    break;
                case 'admin.products.declined.index':
                    $approved = 0;
                    $new = 0;
                    $disabled = 0;
                    break;
                case 'admin.products.disabled.index':
                    $new = 0;
                    $disabled = 1;
                    break;
                case 'admin.products.not-moderated.index':
                    $new = 1;
                    $disabled = 1;
                    break;
                default:
                    break;
            }
            $items = array();
            foreach ($this->productService->getProducts($approved, $new, $disabled, $request, null, -1) as $product) {
                array_push($items, $product->id);
            }
            $res = $this->productService->editProducts($items, $request);
        } else {
            $res = $this->productService->editProducts(json_decode($request['items']), $request);
        }
        $edited_count = count($res['edited']);
        $route = url(app('router')->getRoutes()->match(app('request')->create(url()->previous()))->uri());
        $edited_route = strpos($route, '?') == false ? $route.'?ids='.implode(',', $res['edited']) : $route.'&ids='.implode(',', $res['edited']);
        $edited_count_link = "<a href='{$edited_route}' target='_blank'>{$edited_count}</a>";
        $edited_count_message = __('common.products_updated_message', ['num' => $edited_count_link]);
        $alert_html = "<span><strong>{$edited_count_message}</strong></span>";
        $failed_count = count($res['failed']);
        if ($failed_count) {
            $failed_route = strpos($route, '?') == false ? $route.'?ids='.implode(',', $res['failed']) : $route.'&ids='.implode(',', $res['failed']);
            $failed_count_link = "<a href='{$failed_route}' target='_blank'>{$failed_count}</a>";
            $failed_count_message = __('common.products_not_updated_message', ['num' => $failed_count_link]);
            $alert_html .= "<span class='ml-2' style='color: darkred;'><strong>{$failed_count_message}</strong></span>";
        }

        $log_link = "<a href='/admin/log/products' target='_blank'>".__('common.see_changelog') . "</a>";
        $alert_html .= "<div><strong>{$log_link}.</strong></div>";

        $json = array();
        $json['data'] = $alert_html;
        return json_encode($json);
    }

    public function moderateProducts(Request $request) {
        if ($request['items'] == 'all') {
            $approved = -1;
            $new = -1;
            $disabled = -1;
            $route = app('router')->getRoutes()->match(app('request')->create(url()->previous()))->getName();
            switch ($route) {
                case 'admin.products.new.index':
                    $approved = 0;
                    $new = 1;
                    $disabled = 0;
                    break;
                case 'admin.products.index':
                    $approved = 1;
                    $new = 0;
                    $disabled = 0;
                    break;
                case 'admin.products.declined.index':
                    $approved = 0;
                    $new = 0;
                    $disabled = 0;
                    break;
                case 'admin.products.disabled.index':
                    $new = 0;
                    $disabled = 1;
                    break;
                case 'admin.products.not-moderated.index':
                    $new = 1;
                    $disabled = 1;
                    break;
                default:
                    break;
            }
            $items = array();
            foreach ($this->productService->getProducts($approved, $new, $disabled, $request, null, -1) as $product) {
                array_push($items, $product->id);
            }
            $this->productService->moderateProducts($items, $request['is_accepted'] == 'true' ? true : false, $request['declined_description']);
        } else {
            $this->productService->moderateProducts(json_decode($request['items']), $request['is_accepted'] == 'true' ? true : false, $request['declined_description']);
        }
        $json = array();
        $json['status'] = 'moderated';
        return json_encode($json);
    }

    public function findProduct(Request $request) {
        $limit = $request['limit'] == 'all' ? null : ((int) $request['limit'] > 0 ? (int) $request['limit'] : 5);
        if ($request['id']) {
            return $this->productService->findProduct($request['pattern'], $limit, $request['expand'], $request['id']);
        } else {
            if ($request['seller_id'] && $request['only_active']) {
                return $this->productService->findProduct($request['pattern'], $limit, $request['expand'], null, 1, intval($request['seller_id']), true);
            } else {
                return $this->productService->findProduct($request['pattern'], $limit, $request['expand']);
            }
        }
    }

}
