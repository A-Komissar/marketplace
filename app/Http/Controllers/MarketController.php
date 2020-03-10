<?php

namespace App\Http\Controllers;

use App\Services\Markets\DefaultService;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Services\Markets\RozetkaService;
use App\Services\Markets\PromService;
use Illuminate\Support\Facades\Storage;

class MarketController extends Controller
{
    private $market;
    private $market_code = 'default';

    public function __construct(Request $request)
    {
        if ($request['market'] == 'rozetka') {
            $this->market = new RozetkaService();
            $this->market_code = 'rozetka';
        } else if ($request['market'] == 'prom') {
            $this->market = new PromService();
            $this->market_code = 'prom';
        } else {
            $this->market = new DefaultService();
        }
    }

    public function feed()
    {
        try {
            $type = $this->market->getFeedContentType();
            if ($type == 'text/xml') {
                $filepath = '/public/market/'.$this->market_code.'/feed.xml';
                $feed = Storage::get($filepath);
                return response($feed)->header('Content-Type', $type);
            } else {
                return '';
            }
        } catch (\Exception $e) {
            return $this->generateFeed();
        }
    }

    public function generateFeed()
    {
        $type = $this->market->getFeedContentType();
        $feed = $this->market->getFeed();
        return response($feed)->header('Content-Type', $type);
    }

    public function getCategories()
    {
        $this->market->getCategories();
    }

    public function getProducts()
    {
        $this->market->getProducts();
    }

    public function getOrders()
    {
        $this->market->getOrders();
    }

    public function getBrands()
    {
        $this->market->getBrands();
    }

    public function getCharacteristics()
    {
        $this->market->getCharacteristics();
    }

    public function getChats()
    {
        $this->market->getChats();
    }

    public function getKits()
    {
        $this->market->getKits();
    }

    public function sendEmailWithProductsChanges()
    {
        $this->market->sendEmailWithProductsChanges();
    }

}
