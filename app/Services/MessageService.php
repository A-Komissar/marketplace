<?php

namespace App\Services;

use App\Models\Admin;
use App\Models\Message;
use App\Models\Seller;
use DateTime;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Input;

class MessageService
{

    public function getAllConversations($user_id, $is_admin = false, $items_per_page = 50)
    {
        $query = Message::query();
        if($is_admin) {
            $query->where('admin_id', $user_id);
        } else {
            $query->where('seller_id', $user_id);
        }
        $query->whereIn('id', function($query) {
            $query->select(DB::raw('max(id)'))
                ->from(with(new Message)->getTable())
                ->groupBy('seller_id');
        });
        $query->orderBy('created_at', 'desc');
        $conversations = $query->paginate($items_per_page)->appends(Input::except('page'));
        return $conversations;
    }

    public function getConversation($seller_id, $admin_id = null, $items_per_page = 20) {
        $query = Message::query();
        $query->where('seller_id', $seller_id);
        if($admin_id) {
            $query->where('admin_id', $admin_id);
        }
        $query->with('seller')->with('admin');
        $query->orderBy('created_at', 'desc');
        $conversation = $query->paginate($items_per_page)->appends(Input::except('page'));
        return $conversation;
    }

    public function readAllMessages($seller_id, $admin_id, $is_admin = false) {
        $query = Message::query();
        $query->where('seller_id', $seller_id);
        $query->where('admin_id', $admin_id);
        $query->where('new', 1);
        if($is_admin) {
            $query->where('written_by_admin', 0);
        } else {
            $query->where('written_by_admin', 1);
        }
        $messages = $query->get();
        foreach ($messages as $message) {
            $this->readMessage($message->id);
        }
        return 1;
    }

    public function readMessage($message_id){
        $message = Message::where('id', $message_id)->first();
        $message->new = 0;
        $message->save();
        return 1;
    }

    public function sendMessage($seller_id, $admin_id, $message, $written_by_admin = false) {
        if(!Seller::find($seller_id) || !Admin::find($admin_id)) return 0;
        $message = Message::create([
            'message' => $message,
            'seller_id' => $seller_id,
            'admin_id' => $admin_id,
            'written_by_admin' => $written_by_admin
        ]);
        return $message;
    }

    public function deleteAllMessagesWithSeller($seller_id) {
        $messages = $this->getConversation($seller_id);
        foreach ($messages as $message) {
            $message->delete();
        }
    }

    public function deleteMessage($message_id) {
        try{
            $message = Message::where('id', $message_id)->first();
            $message->delete();
            return 1;
        } catch (Exception $e) {
            return 0;
        }
    }

}
