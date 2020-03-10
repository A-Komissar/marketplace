<?php

namespace App\Http\Controllers\Admin;

use App\Models\Admin;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;

class AdminController extends Controller
{

    public function edit()
    {
        $profile = Admin::where('id', Auth::user()->id)->first();
        return view('admin.settings.profile.edit', compact('profile'));
    }

    public function update(Request $request)
    {
        $profile = Admin::where('id', Auth::user()->id)->first();
        if($request['name']) $profile->name = $request['name'];
        if($request['email']) $profile->email = $request['email'];
        if($request['password']) {
            $this->validate($request, [
                'password'    => 'min:6|confirmed',
            ]);
            $profile->password = bcrypt($request['password']);
        }
        $profile->save();
        return redirect('admin/settings/profile')
            ->with(array('message_lang_ref'=> 'common.model_updated'));
    }

    public function restartWebSocketsServer() {
        Artisan::call('websockets:serve');
        return true;
    }

}
