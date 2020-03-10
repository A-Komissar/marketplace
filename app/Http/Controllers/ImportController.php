<?php

namespace App\Http\Controllers;

use App\Services\ImportService;
use Illuminate\Http\Request;

class ImportController extends Controller
{
    private $importService;

    public function __construct()
    {
        $this->importService = new ImportService();
    }


    public function importProducts()
    {
        $this->importService->importAllProducts();
    }
}
