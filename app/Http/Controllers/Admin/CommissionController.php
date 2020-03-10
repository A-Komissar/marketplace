<?php

namespace App\Http\Controllers\Admin;

use App\Models\Category;
use App\Models\Commission;
use App\Models\Market;
use App\Models\Seller;
use App\Services\ProductService;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Input;

class CommissionController extends Controller
{

    public function index(Request $request)
    {
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
        $commissions = $query->paginate(50)->appends(Input::except('page'));
        return view('admin.settings.commission.index', compact('commissions'));
    }

    public function create()
    {
        $categories =  Category::where('parent_id', 0)->get();
        $markets = Market::all();
        return view('admin.settings.commission.create', compact('categories', 'markets'));
    }

    public function store(Request $request)
    {
        $this->validate($request, [
            'category'  => 'required',
            'value'     => 'required|numeric|min:0',
        ]);
        try {
            $market = Market::where('id', $request['market'])->first();
            $commission = new Commission;
            $commission->market_id = $market ? $market->id : Market::first()->id;
            $commission->value = $request['value']/100;

            $category_id = $request['category'][count($request['category'])-1];
            $new_category = Category::where('category_id', $category_id)->first();
            if($new_category) {
                $commission->category_id = $new_category->id;
            } else if(count($request['category']) >= 2) {
                $category_id = $request['category'][count($request['category'])-2];
                $new_category = Category::where('category_id', $category_id)->first();
                if($new_category) {
                    $commission->category_id = $new_category->id;
                }
            }
            if ($request['seller_id'] && $request['seller_id'] > 0 && Seller::where('id', $request['seller_id'])->first()) {
                $commission->seller_id = $request['seller_id'];
                $commission->save();
                return redirect('admin/moderation/sellers/'.$commission->seller_id.'?tab=commission')
                    ->with(array('message_lang_ref'=> 'common.model_updated'));
            } else {
                $commission->save();
                return redirect('admin/settings/commission/'.$commission->id)
                    ->with(array('message_lang_ref'=> 'common.model_updated'));
            }
        } catch (\Exception $e) {
            return redirect('admin/settings/commission/new');
        }
    }

    public function edit($commission_id)
    {
        $commission = Commission::with('market', 'category')->where('id', $commission_id)->first();
        if($commission) {
            $productService = new ProductService();
            $categories = $productService->getCategoryPath($commission->category_id);
            $markets = Market::all();
            return view('admin.settings.commission.edit', compact('commission', 'categories', 'markets'));
        } else {
            return abort(404);
        }
    }

    public function update($commission_id, Request $request)
    {
        $this->validate($request, [
            'category'  => 'required',
            'value'     => 'required|numeric|min:0',
        ]);
        try {
            $market = Market::where('id', $request['market'])->first();
            $commission = Commission::where('id', $commission_id)->first();
            $commission->market_id = $market ? $market->id : Market::first()->id;
            $commission->value = $request['value']/100;

            $category_id = $request['category'][count($request['category'])-1];
            $new_category = Category::where('category_id', $category_id)->first();
            if($new_category) {
                $commission->category_id = $new_category->id;
            } else if(count($request['category']) >= 2) {
                $category_id = $request['category'][count($request['category'])-2];
                $new_category = Category::where('category_id', $category_id)->first();
                if($new_category) {
                    $commission->category_id = $new_category->id;
                }
            }
            $commission->save();
            if ($request['seller_id'] && $request['seller_id'] > 0 && Seller::where('id', $request['seller_id'])->first()) {
                return redirect('admin/moderation/sellers/'.$commission->seller_id.'?tab=commission')
                    ->with(array('message_lang_ref'=> 'common.model_updated'));
            } else {
                return redirect('admin/settings/commission/'.$commission_id)
                    ->with(array('message_lang_ref'=> 'common.model_updated'));
            }
        } catch (\Exception $e) {
            return abort(500);
        }
    }

    public function destroy($commission_id)
    {
        $commission = Commission::where('id', $commission_id)->first();
        if ($commission->seller_id > 0 && Seller::where('id', $commission->seller_id)->first()) {
            $seller_id = $commission->seller_id;
            Commission::where('id', $commission_id)->delete();
            return redirect('admin/moderation/sellers/'.$seller_id.'?tab=commission'); //->with(array('message_lang_ref'=> 'common.commission_deleted'));
        } else {
            Commission::where('id', $commission_id)->delete();
            return redirect('admin/settings/commission');
        }

    }

}
