<?php

namespace App\Http\Controllers;

use App\Models\User;
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
                    'title' => ' - ',
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

        if($this->request['data']['alerts']['success']) {
			Session::forget('success');
		}
		
		if($this->request['data']['alerts']['error']) {
			Session::forget('error');
		}

        if($this->request['data']['siteusername']) {
            $this->request['data']['user'] = (array) User::where('username', $this->request['data']['siteusername']);
            $this->request['data']['embeds']['title'] .= ($this->request['data']['user']['branding'] == 'finobe') ? 'Finobe' : 'Aesthetiful';
        } else {
            $this->request['data']['embeds']['title'] .= 'Aesthetiful';
        }
    }

    public function index(Request $request) {
        $this->request['data']['embeds']['title'] = '' . $this->request['data']['embeds']['title'];
    }
}
