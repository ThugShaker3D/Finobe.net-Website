<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;

class frontEnd extends Controller
{
    protected $db;
    protected $request;

    public function __construct(Request $request) {
        $this->db = DB::connection('finobe');
        $this->request = [
            'data' => [
                'embeds' => [
                    'title' => 'Finobe - ',
                    'description' => 'Finobe, it is website. He is for old brick-builder. Good for use.',
                    'url' => env('APP_URL'),
                    'image' => env('APP_URL') . '/s/img/'
                ],
                'csrf_token' => View::share('csrf_token', csrf_token()),
                'siteusername' => Session::get('siteusername', false),
                'user' => [],
                'page' => strtok($_SERVER['REQUEST_URI'], '?'),
				'dir' => str_replace('\\', '', '/' . explode('/', trim($_SERVER['REQUEST_URI'], '/'))[0] . '/'),
                'alerts' => [
					'success' => Session::get('success', false),
					'error' => Session::get('error', false),
					'announcements' => []
				]
            ]
        ];
    }
}
