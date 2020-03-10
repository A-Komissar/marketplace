<?php

namespace App\Http\Controllers\Admin;

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
        $items = $this->kitService->getKits(null, $request);
        return view('admin.products.kits.index', compact('items'));
    }

    public function edit($kit_id)
    {
        $item = $this->kitService->getKit($kit_id);
        return view('admin.products.kits.edit', compact('item'));
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
