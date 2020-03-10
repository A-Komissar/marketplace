<?php

namespace App\Http\Controllers\Seller;

use App\Models\Market;
use App\Services\KitService;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;

class KitController extends Controller
{

    private $kitService;

    public function __construct()
    {
        $this->kitService = new KitService();
    }

    public function index(Request $request)
    {
        $items = $this->kitService->getKits(Auth::user()->id, $request);
        return view('seller.products.kits.index', compact('items'));
    }

    public function create()
    {
        $markets = Market::all();
        return view('seller.products.kits.create', compact('markets'));
    }

    public function store(Request $request)
    {
        $this->validate($request, [
            'title' => 'required',
            'start_date' => 'required',
        ]);
        $item = $this->kitService->createKit(Auth::user()->id, $request, $request['market'] ?: 1);
        if ($item) {
            return redirect()->route('seller.kit.edit', ['kit_id' => $item->id])
                ->with(array('message_lang_ref'=> 'common.model_updated'));
        } else {
            return redirect()->back()->with(array('message_lang_ref'=> 'common.model_not_updated', 'type' => 'error'));
        }
    }

    public function edit($kit_id)
    {
        $item = $this->kitService->getKit($kit_id);
        return view('seller.products.kits.edit', compact('item'));
    }

    public function update($kit_id, Request $request)
    {
        $item = $this->kitService->updateKit($kit_id, $request);
        if ($item) {
            return redirect()->back()
                ->with(array('message_lang_ref'=> 'common.model_updated'));
        } else {
            return redirect()->back()
                ->with(array('message_lang_ref'=> 'common.model_not_updated', 'type' => 'error'));
        }
    }

}
