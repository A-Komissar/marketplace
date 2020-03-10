<?php

namespace App\Http\Controllers\Seller;

use App\Events\AdminNewMessage;
use App\Events\MessageSent;
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

    /**
     * Instantiate a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->messageService = new MessageService();
    }


    /**
     * Display a listing of all conversations with sellers.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $conversations = $this->messageService->getAllConversations(Auth::user()->id, false);
        return view('seller.conversations.index', compact('conversations'));

    }

    public function getConversation($admin_id) {
        $this->messageService->readAllMessages(Auth::user()->id, $admin_id, false);
        $messages = $this->messageService->getConversation(Auth::user()->id, $admin_id);
        return view('seller.conversations.show', compact('messages'));
    }

    public function sendMessage(Request $request, $admin_id) {
        if($request['message']) {
            $message = $this->messageService->sendMessage(Auth::user()->id, $admin_id, $request['message']);
            try {
                $seller = Seller::where('id', Auth::user()->id)->first();
                $admin = Admin::where('id', $admin_id)->first();
                event(new MessageSent($message, $admin, $seller));
                $new_messages_num = Message::where('new', 1)->where('written_by_admin', 0)->count();
                event(new AdminNewMessage($admin_id, $new_messages_num, $seller->name, $message->message));
            } catch (\Exception $e) {
                // websockets doesn't work!
            }
            return $message;
        } else {
            return json_encode((object) null);
        }
    }

    public function readMessages($admin_id) {
        return $this->messageService->readAllMessages(Auth::user()->id, $admin_id, false);
    }

}
