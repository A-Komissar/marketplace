<?php 

namespace App\Services\Markets;

interface MarketServiceInterface {
	public function getOrders();
    public function getAvailableStatuses($old_status_key);
    public function updateOrder($order_id, $status, $comment = '', $ttn = '');
    public function getFeedContentType();
    public function getFeed();
    public function getProducts();
    public function getCategories();
    public function getCharacteristics();
    public function getBrands();
    public function getCategoryAttributes($category_id);
    public function getChats();
    public function sendMessage($chat, $message, $send_email = true);
    public function getKits();
    public function sendEmailWithProductsChanges();
}
