<?php

namespace App\Http\Controllers\Admin;

use App\Events\MessageSent;
use App\Events\SellerNewMessage;
use App\Models\Admin;
use App\Models\Message;
use App\Models\Seller;
use App\Services\MessageService;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;

class MessageController extends Controller
{
    private $messageService;

    public function __construct()
    {
        $this->messageService = new MessageService();
    }

    public function index()
    {
        $conversations = $this->messageService->getAllConversations(Auth::user()->id, true);
        return view('admin.conversations.index', compact('conversations'));
    }

    public function getConversation($seller_id) {
        $this->messageService->readAllMessages($seller_id, Auth::user()->id, true);
        $messages = $this->messageService->getConversation($seller_id, Auth::user()->id);
        return view('admin.conversations.show', compact('messages'));
    }

    public function sendMessage(Request $request, $seller_id) {
        if($request['message']) {
            $message = $this->messageService->sendMessage($seller_id, Auth::user()->id, $request['message'], true);
            try {
                $seller = Seller::where('id', $seller_id)->first();
                $admin = Admin::where('id', Auth::user()->id)->first();
                event(new MessageSent($message, $admin, $seller));
                $new_messages_num = Message::where('seller_id',$seller_id)->where('new', 1)
                    ->where('written_by_admin', 1)->count();
                event(new SellerNewMessage($seller_id, $new_messages_num, $admin->name, $message->message));
            } catch (\Exception $e) {
                // websockets doesn't work!
            }
            return $message;
        } else {
            return json_encode((object) null);
        }
    }

    public function sendMessageToSellers(Request $request) {
        $sellers = null;
        if(!$request['seller'] && !$request['for_all']) return redirect()->back();
        if ($request['for_all']) {
            $sellers = Seller::where('approved', 1)->get();
        } else {
            $ids = array_map(function ($item) { return intval($item['id']); }, $request['seller']);
            $sellers = Seller::whereIn('id', $ids)->get();
        }
        foreach ($sellers as $seller) {
            $message = $this->messageService->sendMessage($seller->id, Auth::user()->id, $request['message'], true);
            try {
                $admin = Admin::where('id', Auth::user()->id)->first();
                event(new MessageSent($message, $admin, $seller));
                $new_messages_num = Message::where('seller_id', $seller->id)->where('new', 1)
                    ->where('written_by_admin', 1)->count();
                event(new SellerNewMessage($seller->id, $new_messages_num, $admin->name, $message->message));
            } catch (\Exception $e) {
                // websockets doesn't work!
            }
        }
        return redirect()->back()->with(array('message_lang_ref'=> 'common.model_updated'));
    }

    public function readMessages($seller_id) {
        return $this->messageService->readAllMessages($seller_id, Auth::user()->id, true);
    }

}
