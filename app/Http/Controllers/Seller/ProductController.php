<?php

namespace App\Http\Controllers\Seller;

use App\Models\Category;
use App\Models\Commission;
use App\Models\ImportProducts;
use App\Models\Market;
use App\Models\Product;
use App\Services\ImportService;
use App\Services\OrderService;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Services\ProductService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Input;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Pagination\LengthAwarePaginator;

class ProductController extends Controller
{
    private $productService;

    public function __construct()
    {
        $this->productService = new ProductService();
    }

    public function getProducts(Request $request)
    {
        $products = $this->productService->getProducts(1,-1, 0, $request, Auth::user()->id);
        $commissions = $this->productService->getProductsCommissions($products);
        $categories =  Category::where('parent_id', 0)->get();
        return view('seller.products.index', compact('products', 'commissions', 'categories'));
    }

    public function getNotApprovedProducts(Request $request)
    {
        $products = $this->productService->getProducts(0,1, 0, $request, Auth::user()->id);
        $commissions = $this->productService->getProductsCommissions($products);
        $categories =  Category::where('parent_id', 0)->get();
        return view('seller.products.index', compact('products', 'commissions', 'categories'));
    }

    public function getDeclinedProducts(Request $request)
    {
        $products = $this->productService->getProducts(0,0, 0, $request, Auth::user()->id);
        $commissions = $this->productService->getProductsCommissions($products);
        $categories =  Category::where('parent_id', 0)->get();
        return view('seller.products.index', compact('products', 'commissions', 'categories'));
    }

    public function getDisabledProducts(Request $request)
    {
        $products = $this->productService->getProducts(-1,0,1, $request, Auth::user()->id);
        $commissions = $this->productService->getProductsCommissions($products);
        $categories =  Category::where('parent_id', 0)->get();
        return view('seller.products.index', compact('products', 'commissions', 'categories'));
    }

    public function getNewProducts(Request $request)
    {
        $products = $this->productService->getProducts(-1,1,1, $request, Auth::user()->id);
        $commissions = $this->productService->getProductsCommissions($products);
        $categories =  Category::where('parent_id', 0)->get();
        return view('seller.products.index', compact('products', 'commissions', 'categories'));
    }

    public function editProduct($product_id)
    {
        $product = $this->productService->getProduct($product_id);
        if($product && $product->seller_id != Auth::user()->id) $product = null;
        if($product) {
            $commission = (new OrderService())->getCategoryCommissionSize($product->rozetka_category_id, Auth::user()->id);
            $categories =  $this->productService->getCategoryPath($product->rozetka_category_id);
            return view('seller.products.edit', compact('product', 'categories', 'commission'));
        } else {
            return redirect('seller/products/approved');
        }
    }

    public function updateProduct(Request $request, $product_id)
    {
        $res = $this->productService->updateProduct($product_id, $request);
        if ($res && get_class($res) == 'Illuminate\Http\RedirectResponse') {
            return $res;
        } else if ($res) {
            return redirect('seller/products/'.$product_id)->with(array('message_lang_ref'=> 'common.product_updated'));
        } else {
            return Redirect::back()->with(array('message_lang_ref'=> 'common.product_not_updated'));
        }
    }

    public function deleteProduct($product_id)
    {
        $product = $this->productService->getProduct($product_id);
        $type = ($product->approved) ? 'approved' : 'not-approved';
        $this->productService->deleteProduct($product_id);
        return redirect('seller/products/'.$type);
    }

    public function showNewProduct()
    {
        if(Auth::user()->big) {
            return redirect('seller/products/new/import');
        } else {
            return redirect('seller/products/new/manual');
        }
    }

    public function showNewManualProduct()
    {
        $categories =  Category::where('parent_id', 0)->get();
        return view('seller.products.new', compact( 'categories'));
    }

    public function showNewImportProduct()
    {
        $template = ImportProducts::where('seller_id', Auth::user()->id)->first();
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
        return view('seller.products.import', compact('has_template', 'template', 'additional'));
    }

    public function createProduct(Request $request)
    {
        $this->validate($request, [
            'brand'    => 'required',
            'name_ru'    => 'required',
            'name_ua'    => 'required',
            'article'    => 'regex:/([A-Za-z0-9])+/|required',
            'price'    => 'numeric|required',
            'stock'    => 'numeric',
            'warranty'    => 'required'
        ]);
        $product = $this->productService->createProduct($request);
        if($product) {
            return redirect('seller/products/'.$product->id)->with(array('message_lang_ref'=> 'common.product_updated'));
        } else {
            return Redirect::back()->with(array('message_lang_ref'=> 'common.product_not_created'));
        }
    }

    public function importProducts(Request $request)
    {
        if(Auth::user()->big) {
            $this->validate($request, [
                'category'    => 'required',
                'article'    => 'required',
                'name_ru'    => 'required',
                'price'    => 'required',
            ]);
            if($request['import_type'] != 'excel' || !$request['import_file']) {
                $this->validate($request, [
                    'import_link'    => 'url|required',
                ]);
            }
        }
        $result = (new ImportService())->importProducts(Auth::user()->id, $request);
        if ($result) {
            if ($request['redirect_to']) {
                return redirect()->route($request['redirect_to']);
            } else {
                return redirect('seller/products/not-moderated');
            }
        } else {
            if ($request['redirect_to']) {
                return redirect()->route($request['redirect_to'])->with(array('message_lang_ref'=> 'seller.cant_import'));
            } else {
                return redirect('seller/products/new/import')->with(array('message_lang_ref'=> 'seller.cant_import'));
            }
        }
    }

    public function copyProduct($product_id) {
        $new_product_id = $this->productService->copyProduct($product_id);
        return redirect('seller/products/'.$new_product_id);
    }

    public function getCategoryChildren($parent_id)
    {
        return $this->productService->getCategoryChildren($parent_id);
    }

    public function getCategoryCommission($category_id)
    {
        $category = Category::where('category_id', $category_id)->first();
        if ($category) {
            return (new OrderService())->getCategoryCommissionSize($category->id, Auth::user()->id);
        } else {
            return Config::get('market.default_commission');
        }
    }

    public function deleteProductPhoto($product_id, Request $request)
    {
        return $this->productService->deleteProductPhoto($product_id, $request);
    }

    public function uploadProductPhoto($product_id, Request $request)
    {
        return $this->productService->uploadProductPhoto($product_id, $request);
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
        $limit = (int) $request['limit'] > 0 ? (int) $request['limit'] : 5;
        return $this->productService->getCharacteristicKeys($request['pattern'], $request['market'], $request['category_id'], $limit);
    }

    public function getCharacteristicValues(Request $request)
    {
        return $this->productService->getCharacteristicValues($request['key'], $request['market'], $request['category_id'], $request['pattern']);
    }

    public function getCommissions(Request $request) {
        $query = Commission::query();
        $query->with('market', 'category');
        if($request['category']) {
            $category = $request['category'];
            $query->whereHas('category', function($q) use($category) {
                $q->where('name', 'LIKE', $category.'%');
            });
        }
        $query->where('seller_id', 0);
        $query->orderBy('value', 'asc');

        // $commissions = $query->paginate(50)->appends(Input::except('page')); // common commissions

        // personalize commissions
        $commissions = $query->get();
        if(get_class(Auth::user()) == 'App\Models\Seller') {
            // replace common commissions to personal if found
            foreach (Commission::with('market', 'category')->where('seller_id', Auth::user()->id)->get() as $commission) {
                $old = $commissions->where('category_id', $commission->category_id)
                    ->where('market_id', $commission->market_id)
                    ->where('seller_id', 0)->first();
                if ($old) {
                    // search current item index in collection
                    $index = $commissions->search(function($item) use ($old) {
                        return $item->id === $old->getKey();
                    });
                    // replace founded item
                    $commissions = $commissions->replace([$index => $commission]);
                }
            }
        }
        $currentPage = LengthAwarePaginator::resolveCurrentPage();
        $itemCollection = collect($commissions->sortBy('value'));
        $perPage = 50;
        $currentPageItems = $itemCollection->slice(($currentPage * $perPage) - $perPage, $perPage)->all();
        $commissions = new LengthAwarePaginator($currentPageItems, count($itemCollection), $perPage);
        $commissions->setPath($request->url());

        return view('seller.products.commissions.index', compact('commissions'));
    }

    public function getCategoryAttributes($category_id, Request $request) {
        return $this->productService->getCategoryAttributes($category_id, $request['market'] ? $request['market'] : 'rozetka');
    }

    public function deleteImportTemplate() {
        $template = ImportProducts::where('seller_id', Auth::user()->id);
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
                case 'seller.products.new.index':
                    $new = 1;
                    $disabled = 1;
                    break;
                case 'seller.products.index':
                    $approved = 1;
                    $new = 0;
                    $disabled = 0;
                    break;
                case 'seller.products.not-approved.index':
                    $approved = 0;
                    $new = 1;
                    $disabled = 0;
                    break;
                case 'seller.products.declined.index':
                    $approved = 0;
                    $new = 0;
                    $disabled = 0;
                    break;
                case 'seller.products.disabled.index':
                    $new = 0;
                    $disabled = 1;
                    break;
                default:
                    break;
            }
            $items = array();
            foreach ($this->productService->getProducts($approved, $new, $disabled, $request, Auth::user()->id, -1) as $product) {
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

    public function sendProductsToModeration(Request $request) {
        if ($request['items'] == 'all') {
            $items = array();
            $approved = -1;
            $new = -1;
            $disabled = -1;
            $route = app('router')->getRoutes()->match(app('request')->create(url()->previous()))->getName();
            switch ($route) {
                case 'seller.products.new.index':
                    $new = 1;
                    $disabled = 1;
                    break;
                case 'seller.products.index':
                    $approved = 1;
                    $new = 0;
                    $disabled = 0;
                    break;
                case 'seller.products.not-approved.index':
                    $approved = 0;
                    $new = 1;
                    $disabled = 0;
                    break;
                case 'seller.products.declined.index':
                    $approved = 0;
                    $new = 0;
                    $disabled = 0;
                    break;
                case 'seller.products.disabled.index':
                    $new = 0;
                    $disabled = 1;
                    break;
                default:
                    break;
            }
            foreach ($this->productService->getProducts($approved, $new, $disabled, $request, Auth::user()->id, -1) as $product) {
                array_push($items, $product->id);
            }
            $this->productService->sendProductsToModeration($items);
        } else {
            $this->productService->sendProductsToModeration(json_decode($request['items']));
        }
        $json = array();
        $json['status'] = 'sent';
        return json_encode($json);
    }

    public function editProducts(Request $request) {
        if ($request['items'] == 'all') {
            $approved = -1;
            $new = -1;
            $disabled = -1;
            $route = app('router')->getRoutes()->match(app('request')->create(url()->previous()))->getName();
            switch ($route) {
                case 'seller.products.new.index':
                    $new = 1;
                    $disabled = 1;
                    break;
                case 'seller.products.index':
                    $approved = 1;
                    $new = 0;
                    $disabled = 0;
                    break;
                case 'seller.products.not-approved.index':
                    $approved = 0;
                    $new = 1;
                    $disabled = 0;
                    break;
                case 'seller.products.declined.index':
                    $approved = 0;
                    $new = 0;
                    $disabled = 0;
                    break;
                case 'seller.products.disabled.index':
                    $new = 0;
                    $disabled = 1;
                    break;
                default:
                    break;
            }
            $items = array();
            foreach ($this->productService->getProducts($approved, $new, $disabled, $request, Auth::user()->id, -1) as $product) {
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
        $log_link = "<a href='/seller/log/products' target='_blank'>".__('common.see_changelog') . "</a>";
        $alert_html .= "<div><strong>{$log_link}.</strong></div>";
        $json = array();
        $json['data'] = $alert_html;
        return json_encode($json);
    }

    public function exportProducts(Request $request) {
        if ($request['items'] == 'all') {
            $approved = -1;
            $new = -1;
            $disabled = -1;
            $route = app('router')->getRoutes()->match(app('request')->create(url()->previous()))->getName();
            switch ($route) {
                case 'seller.products.new.index':
                    $new = 1;
                    $disabled = 1;
                    break;
                case 'seller.products.index':
                    $approved = 1;
                    $new = 0;
                    $disabled = 0;
                    break;
                case 'seller.products.not-approved.index':
                    $approved = 0;
                    $new = 1;
                    $disabled = 0;
                    break;
                case 'seller.products.declined.index':
                    $approved = 0;
                    $new = 0;
                    $disabled = 0;
                    break;
                case 'seller.products.disabled.index':
                    $new = 0;
                    $disabled = 1;
                    break;
                default:
                    break;
            }
            $items = array();
            foreach ($this->productService->getProducts($approved, $new, $disabled, $request, Auth::user()->id, -1) as $product) {
                array_push($items, $product->id);
            }
            $filepath = $this->productService->exportProducts($items, Auth::user()->id, $request['type']);
        } else {
            $filepath = $this->productService->exportProducts(json_decode($request['items']), Auth::user()->id, $request['type']);
        }
        $json = array();
        $json['file'] = $filepath;
        return json_encode($json);
    }

    public function findProduct(Request $request) {
        $limit = $request['limit'] == 'all' ? null : ((int) $request['limit'] > 0 ? (int) $request['limit'] : 5);
        $only_active = $request['only_active'] == 'true' ? true : false;
        if ($request['id']) {
            return $this->productService->findProduct($request['pattern'], $limit, $request['expand'], $request['id'], 1, Auth::user()->id, $only_active);
        } else {
            return $this->productService->findProduct($request['pattern'], $limit, $request['expand'], null, 1, Auth::user()->id, $only_active);
        }
    }

}
