<?php

namespace App\Http\Controllers\Admin;

use App\Models\Market;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Input;

class MarketController extends Controller
{

    public function index(Request $request)
    {
        $query = Market::query();
        $items = $query->paginate(50)->appends(Input::except('page'));
        return view('admin.settings.market.index', compact('items'));
    }

    public function create()
    {
        return view('admin.settings.market.create');
    }

    public function store(Request $request)
    {
        $this->validate($request, [
            'market_code'  => 'required|unique:markets',
            'market_name'     => 'required',
        ]);
        try {
            $item = new Market();
            $item->market_code = $request['market_code'];
            $item->market_name = $request['market_name'];
            $item->save();
            return redirect('admin/settings/market/'.$item->id)
                ->with(array('message_lang_ref'=> 'common.model_updated'));
        } catch (\Exception $e) {
            return redirect('admin/settings/market/new')->with(array('message_lang_ref'=> 'common.model_not_updated', 'type' => 'error'));
        }
    }

    public function edit($market_id)
    {
        $item = Market::where('id', $market_id)->first();
        if($item) {
            return view('admin.settings.market.edit', compact('item'));
        } else {
            return abort(404);
        }
    }

    public function update($market_id, Request $request)
    {
        $this->validate($request, [
            'market_name'     => 'required',
        ]);
        try {
            $item = Market::where('id', $market_id)->first();
            $item->market_name = $request['market_name'];
            $item->save();
            return redirect()->back()
                ->with(array('message_lang_ref'=> 'common.model_updated'));
        } catch (\Exception $e) {
            return redirect()->back()
                ->with(array('message_lang_ref'=> 'common.model_not_updated', 'type' => 'error'));
        }
    }

    public function destroy($market_id)
    {
        if (!in_array($market_id, Config::get('market.not_deletable_markets'))) {
            Market::where('id', $market_id)->delete();
            return redirect('admin/settings/market')->with(array('message_lang_ref'=> 'common.model_deleted'));
        } else {
            return redirect('admin/settings/market')->with(array('message_lang_ref'=> 'common.model_not_deleted', 'type' => 'error'));
        }

    }

}
