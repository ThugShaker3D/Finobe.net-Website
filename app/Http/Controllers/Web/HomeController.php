<?php

namespace App\Http\Controllers\Web;

use Illuminate\Http\Request;
use App\Http\Controllers\frontEnd;
use App\Http\Controllers\Controller;
use App\Http\Controllers\dataController;

class HomeController extends Controller
{
    protected $request;

    public function __construct(dataController $dataService, Request $request) {
        $frontend = new frontEnd($dataService, $request);
        $this->request = $frontend->getData();
    }

    public function index() {
        //
    }
}