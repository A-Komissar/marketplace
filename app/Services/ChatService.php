<?php

namespace App\Services;

use App\Models\Chat;
use App\Models\ChatMessage;
use App\Models\Market;
use App\Models\OrderProduct;
use App\Models\Product;
use App\Services\Markets\RozetkaService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Input;

class ChatService
{

    public function getChats($user_id, $is_admin = false, $only_new = false)
    {
        $chats = array();
        foreach (Chat::with('client')->get() as $item) {
            try {
                if (!$is_admin) {
                    if ($item->product_id || $item->order_id) {
                        if ($item->order_id) {
                            foreach (OrderProduct::where('order_id', $item->order_id)->get() as $prod) {
                                if ($prod->seller_id == $user_id) {
                                    if ($only_new && $item->is_read) {
                                        continue;
                                    }
                                    array_push($chats, $item);
                                    break;
                                }
                            }
                        } else {
                            $product = Product::where('id', $item->product_id)->first();
                            if ($product && $product->seller_id == $user_id) {
                                if ($only_new && $item->is_read) {
                                    continue;
                                }
                                array_push($chats, $item);
                            }
                        }
                    }
                } else {
                    if ($only_new && $item->is_read) {
                        continue;
                    }
                    array_push($chats, $item);
                }
            } catch (\Exception $e) { }
        }
        return $chats;
    }

    public function getChat($chat_id) {
        try {
            $chat = Chat::with('client', 'product', 'order')->where('id', $chat_id)->first();
            if (get_class(Auth::user()) != 'App\Models\Admin') {
                if ($chat->product_id || $chat->order_id) {
                    $found = false;
                    if ($chat->order_id) {
                        foreach (OrderProduct::where('order_id', $chat->order_id)->get() as $prod) {
                            if ($prod->seller_id == Auth::user()->id) {
                                $found = true;
                                break;
                            }
                        }
                    } else {
                        $product = Product::where('id', $chat->product_id)->first();
                        if ($product && $product->seller_id == Auth::user()->id) {
                            $found = true;
                        }
                    }
                    if (!$found) {
                        return null;
                    } else {
                        $chat->is_read = true;
                        $chat->save();
                        return $chat;
                    }
                } else {
                    return null;
                }
            } else {
                return $chat;
            }
        } catch (\Exception $e) {
            return null;
        }
    }

    public function getChatMessages($chat_id) {
        return ChatMessage::where('chat_id', $chat_id)->orderBy('created_at', 'desc')->get();
    }

    public function sendMessage($chat_id, $message, $send_email = true) {
        try {
            $chat = Chat::where('id', $chat_id)->first();
            if (get_class(Auth::user()) != 'App\Models\Admin') {
                if ($chat->product_id || $chat->order_id) {
                    $found = false;
                    if ($chat->order_id) {
                        foreach (OrderProduct::where('order_id', $chat->order_id)->get() as $prod) {
                            if ($prod->seller_id == Auth::user()->id) {
                                $found = true;
                                break;
                            }
                        }
                    } else {
                        $product = Product::where('id', $chat->product_id)->first();
                        if ($product && $product->seller_id == Auth::user()->id) {
                            $found = true;
                        }
                    }
                    if (!$found) {
                        return null;
                    }
                } else {
                    return null;
                }
            }
            if ($chat->market_id == Market::where('market_code', 'rozetka')->first()->id) {
                return (new RozetkaService())->sendMessage($chat, $message, $send_email);
            } else {
                return null;
            }
        } catch (\Exception $e) {
            return null;
        }
    }
}
