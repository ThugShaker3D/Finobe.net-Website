<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Http\Controllers\dataController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Validator;

class frontEnd extends Controller
{
    protected $db;
    protected $dataService;
    protected $request;

    public function __construct(dataController $dataService, Request $request) {
        $this->db = DB::connection('finobe');
        $this->dataService = $dataService;
        $this->request = [
            'data' => [
                'embeds' => [
                    'title' => ' - ',
                    'description' => 'Finobe, it is website. He is for old brick-builder. Good for use.',
                    'url' => env('APP_URL'),
                    'image' => env('APP_URL') . '/s/img/'
                ],
                'csrf_token' => View::share('csrf_token', csrf_token()),
                'siteusername' => Auth::check(),
                'user' => [
                    'version' => 'v2',
                    'branding' => 'aesthetiful' // default branding
                ],
                'page' => strtok($_SERVER['REQUEST_URI'], '?'),
				'dir' => str_replace('\\', '', '/' . explode('/', trim($_SERVER['REQUEST_URI'], '/'))[0] . '/'),
                'alerts' => [
					'success' => Session::get('success', false),
					'error' => Session::get('error', false),
					'announcements' => []
				],
                'lucky_number' => rand(0, User::count()) . '/' . User::count()
            ]
        ];

        if($this->request['data']['alerts']['success']) {
			Session::forget('success');
		}
		
		if($this->request['data']['alerts']['error']) {
			Session::forget('error');
		}

        if($this->request['data']['siteusername']) {
            $this->request['data']['user'] = Auth::user()->toArray();
            $this->request['data']['user']['formattedDius'] = $this->dataService->formatNumber($this->request['data']['user']['Dius']);
            $this->request['data']['embeds']['title'] .= ($this->request['data']['user']['branding'] == 'finobe') ? 'Finobe' : 'Aesthetiful';
            
            if($this->request['data']['user']['branding'] == 'finobe') {
                if($this->request['data']['user']['logo'] == 'v1') {
                    $this->request['data']['embeds']['image'] .= 'BUSY.png';
                } elseif($this->request['data']['user']['logo'] == 'v2') {
                    $this->request['data']['embeds']['image'] .= 'finnobe3.png';
                } else {
                    $this->request['data']['embeds']['image'] .= 'finnobe3logo.png';
                }
            } else {
                $this->request['data']['embeds']['image'] .= 'logo.png';
            }

            $this->request['data']['user']['friends'] = json_decode($this->request['data']['user']['friends'], true);
            $this->request['data']['user']['avatar'] = json_decode($this->request['data']['user']['avatar'], true);
            $this->request['data']['user']['places'] = $this->db->table('assets')
                ->where('author', $this->request['data']['user']['id'])
                ->where('asset_type', 9)
                ->count();
            
            $this->request['data']['notifications'] = [
                'data' => [],
                'ads' => env('FINOBE_ADS', true),
                'info' => [
                    'number' => $this->db->table('pms')->where('touser', $this->request['data']['user']['username'])->where('readed', 'n')->count(),
                    'inbox' => $this->db->table('messages')->where('touser', $this->request['data']['user']['username'])->where('readed', 'n')->count(),
                    'incomingFriends' => 0
                ]
            ];

            $notifications = $this->db->table('pms')
                ->where('touser', $this->request['data']['user']['username'])
                ->orderBy('date', 'DESC')
                ->get()
                ->map(function ($item) {
                    return (array) $item;
                })->toArray();
            
            foreach($notifications as $notification) {
                $this->request['data']['notifications']['data'][] = [
                    'id' => $notification['id'],
                    'message' => $notification['message'],
                    'date' => date('M j Y g:i:s A', strtotime($notification['date']))
                ];
            }

            foreach($this->request['data']['user']['friends'] as $friend) {
                if($friend['status'] == 'pending') {
                    $this->request['data']['notifications']['info']['incomingFriends']++;
                }
            }
        } else {
            $this->request['data']['embeds']['title'] .= 'Aesthetiful';
            $this->request['data']['embeds']['image'] .= 'logo.png';
        }

        $this->request['data']['announcements'] = $this->db->table('announcements')
            ->select('message', 'expire', 'author', 'color')
            ->where('expire', '>', 'now()')
            ->orderBy('id', 'DESC')
            ->get()
            ->map(function ($item) {
                return (array) $item;
            })->toArray();
    }

    public function index(Request $request) {
        $this->request['data']['embeds']['title'] = 'Home' . $this->request['data']['embeds']['title'];

        if($this->request['data']['siteusername']) {
            $this->request['data']['games'] = [];

            $games = Cache::remember('latest_places', 60 * 10, function() {
                return $this->db->table('assets')
                    ->select('assets.*', DB::raw('SUM(servers.players) AS total_players'))
                    ->leftJoin('servers', 'assets.id', '=', 'servers.placeid')
                    ->where('asset_type', 9)
                    ->groupBy('assets.id')
                    ->orderByDesc('total_players')
                    ->limit(6)
                    ->get()
                    ->map(function ($item) {
                        return (array) $item;
                    })->toArray();
            });
            
            foreach($games as $key => $game) {
                $game['additional'] = json_decode($game['additional'], true);
                $players = 0;

                $servers = $this->db->table('servers')
                    ->select('players')
                    ->where('placeid', $game['id'])
                    ->get()
                    ->map(function ($item) {
                        return (array) $item;
                    })->toArray();
                
                foreach($servers as $server) {
                    $players += count(json_decode($server['players']));
                }

                $thumbnail = $this->db->table('assets')
                    ->select('file')
                    ->where('id', $game['additional']['media']['imageAssetId'])
                    ->value('file');
                
                $this->request['data']['games'][] = [
                    'id' => $game['id'],
                    'title' => $game['title'],
                    'author' => User::find($game['author'])->value('username'),
                    'thumbnail' => $thumbnail,
                    'visits' => number_format($game['additional']['visits']),
                    'version' => $game['additional']['version'],
                    'players' => $players
                ];
            }
        }

        return view($this->request['data']['user']['version'] . '/Landing', $this->request);
    }

    public function user(Request $request, $id) {
        if(!$this->request['data']['siteusername']) {
            return redirect('/');
        }

        if(!User::where('id', $id)->exists()) {
            return view('404', [], 404);
        }

        $user = User::find($id)->toArray();

        $this->request['data']['embeds']['title'] = htmlspecialchars($user['username']) . $this->request['data']['embeds']['title'];

        $user['places'] = [];
        $user['created'] = date('m/d/Y h:i:s A', strtotime($user['created']));
        $user['blurb'] = nl2br(str_replace('${myDius}', '<span class="n-money-text text-nowrap"><img src="/s/img/diu_16.png" alt="Diu" title="Diu" class="img-responsive align-middle "> [' . number_format($user['Dius']) . ']</span>', preg_replace('/\b((?:https?|ftp):\/\/\S+)/i', '<a href="$1">$1</a>', strip_tags(htmlspecialchars($user['blurb'])))));
        $user['badges'] = json_decode($user['badges'], true);
        $user['friends'] = json_decode($user['friends'], true);
        $user['CurrentFriends'] = array_reverse(array_filter($user['friends'], function ($friend) {
            return $friend['status'] == 'friends';
        }));

        $user['friends'] = array_reverse($user['friends']);

        foreach($user['friends'] as $key => $friend) {
            $user['friends'][$key]['id'] = User::where('username', $friend['username'])->value('id');
            $user['friends'][$key]['pfp'] = User::where('username', $friend['username'])->value('pfp');
        }

        foreach($user['CurrentFriends'] as $key => $friend) {
            $user['CurrentFriends'][$key]['id'] = Cache::remember('id_' . $friend['username'], 60 * 60 * 24 * 7, function() { return User::where('username', $friend['username'])->value('id'); });
            $user['CurrentFriends'][$key]['pfp'] = Cache::remember('pfp_' . $friend['username'], 60 * 60, function() { return User::where('username', $friend['username'])->value('pfp'); });
        }

        $places = $this->db->table('assets')
            ->where('author', $user['id'])
            ->where('asset_type', 9)
            ->get()
            ->map(function ($item) {
                return (array) $item;
            })->toArray();

        foreach($places as $index => $place) {
            $place['additional'] = json_decode($place['additional'], true);
            $place['count'] = $index + 1;
            $place['thumbnail'] = $this->db->table('assets')->select('file')->where('id', $place['additional']['media']['imageAssetId'])->value('file');
            $user['places'][] = $place;
        }

        $this->request['data']['profile'] = $user;
        return view($this->request['data']['user']['version'] . '/User', $this->request);
    }

    public function login(Request $request) {
        $this->request['data']['embeds']['title'] = 'Login' . $this->request['data']['embeds']['title'];
        $this->request['data']['errorlogin'] = Session::has('errorlogin');
        $data = $request->all();

        Session::forget('errorlogin');

        if($this->request['data']['siteusername']) {
            return redirect('/');
        }

        if($request->isMethod('post')) {
            $validator = Validator::make($data, [
                'email' => 'required|email',
                'password' => 'required|string'
            ]);

            if(!User::where('email', $data['email'])->exists()) {
                Session::put('errorlogin', true);
                return redirect('/auth/login');
            }

            $user = User::where('email', $data['email'])->first();

            if(!Hash::check($data['password'], $user->toArray()['password'])) {
                Session::put('errorlogin', true);
                return redirect('/auth/login');
            }

            Auth::login($user);
            Session::put('success', 'Successfully logged in.');
            return redirect('/');
        }

        return view($this->request['data']['user']['version'] . '/Login', $this->request);
    }

    public function logout(Request $request) {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }
}
