<?php

namespace App\Http\Controllers\Seller;

use App\Services\ChatService;

use DateTime;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;

class ChatController extends Controller
{
    private $chatService;
    private $items_per_page;

    public function __construct()
    {
        $this->chatService = new ChatService();
        $this->items_per_page = 50;
    }

    public function index(Request $request)
    {
        $items = $this->chatService->getChats(Auth::user()->id);
        $currentPage = LengthAwarePaginator::resolveCurrentPage();
        $itemCollection = collect($items);
        $perPage = $this->items_per_page;
        $currentPageItems = $itemCollection->slice(($currentPage * $perPage) - $perPage, $perPage)->all();
        $items = new LengthAwarePaginator($currentPageItems, count($itemCollection), $perPage);
        $items->setPath($request->url());
        return view('seller.chats.index', compact('items'));

    }

    public function get($chat_id) {
        $chat = $this->chatService->getChat($chat_id);
        if ($chat) {
            $messages = $this->chatService->getChatMessages($chat_id);
            return view('seller.chats.show', compact('chat', 'messages'));
        } else {
            return redirect()->route('seller.chat.index')->with(array('message_lang_ref'=> 'common.model_not_found', 'type' => 'error'));
        }

    }

    public function sendMessage(Request $request, $chat_id) {
        if($request['message']) {
            $message = $this->chatService->sendMessage($chat_id, $request['message']);
            if ($message) {
                $message->created_date = (new DateTime($message->created_at))->format('H:i d.m.Y');
                return $message;
            } else {
                return json_encode((object) null);
            }
        } else {
            return json_encode((object) null);
        }
    }

}
