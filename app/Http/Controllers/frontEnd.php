<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
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
                    'successv2' => Session::get('successv2', false),
					'success' => Session::get('success', false),
					'error' => Session::get('error', false),
					'announcements' => []
				],
                'lucky_number' => rand(0, User::count()) . '/' . User::count()
            ]
        ];

        if($this->request['data']['alerts']['successv2']) {
			Session::forget('successv2');
		}

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
                'ads' => filter_var(env('FINOBE_ADS'), FILTER_VALIDATE_BOOLEAN),
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

            $user = User::find($this->request['data']['user']['id']);
            $user->ip = hash_hmac('sha256', request()->header('CF-Connecting-IP'), 'ip');
            $user->lastlogin = now();
            $user->save();
            
            if(strtotime($this->request['data']['user']['lastdiu']) <= time() && $this->request['data']['user']['diubanned'] == 'n') {
                /*
                $this->db->table('users')
                    ->where('username', $this->request['data']['user']['username'])
                    ->update([
                        'Dius' => $this->request['data']['user']['Dius'] + 25,
                        'lastdiu' => DB::raw('DATE_ADD(CURRENT_TIMESTAMP(), INTERVAL 1 DAY)')
                    ]);
                */
                
                $user->Dius = $this->request['data']['user']['Dius'] + 25;
                $user->lastdiu = now()->addDay();
                $user->save();
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

        if($request->isMethod('post')) {
            if(!$this->request['data']['siteusername']) {
                return redirect('/');
            }

            if($this->db->table('bans')->where('username', $this->request['data']['user']['username'])->where('expire', '<', DB::raw('now()'))->where('reactivated', 'n')->exists()) {
                $this->db->table('bans')
                    ->where('username', $this->request['data']['user']['username'])
                    ->where('reactivated', 'n')
                    ->update([
                        'reactivated' => 'y'
                    ]);
            }

            if($this->db->table('warning')->where('username', $this->request['data']['user']['username'])->where('reactivated', 'n')->exists()) {
                $this->db->table('warning')
                    ->where('username', $this->request['data']['user']['username'])
                    ->where('reactivated', 'n')
                    ->update([
                        'reactivated' => 'y'
                    ]);
            }

            Session::put('successv2', 'Your moderation action has been lifted.');
            return redirect('/');
        }

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
            return response()->view($this->request['data']['user']['version'] . '/404', [], 404);
        }

        $user = User::find($id)->toArray();

        $this->request['data']['embeds']['title'] = htmlspecialchars($user['username']) . $this->request['data']['embeds']['title'];

        $user['places'] = [];
        $user['created'] = date('m/d/Y h:i:s A', strtotime($user['created']));
        $user['blurb'] = nl2br(str_replace('${myDius}', '<span class="n-money-text text-nowrap"><img src="/s/img/diu_16.png" alt="Diu" title="Diu" class="img-responsive align-middle "> [' . number_format($user['Dius']) . ']</span>', preg_replace('/\b((?:https?|ftp):\/\/\S+)/i', '<a href="$1">$1</a>', strip_tags(htmlspecialchars($user['blurb'])))));
        $user['badges'] = json_decode($user['badges'], true)['data']['custom_badges'] ?? [];
        $user['friends'] = json_decode($user['friends'], true);
        $user['CurrentFriends'] = array_reverse(array_filter($user['friends'], function ($friend) {
            return $friend['status'] == 'friends';
        }));

        $user['friends'] = array_reverse($user['friends']);

        foreach($user['friends'] as $key => $friend) {
            $user['friends'][$key]['id'] = Cache::remember('id_' . $friend['username'], 60 * 60 * 24 * 7, function() use ($friend) { return User::where('username', $friend['username'])->value('id'); });
            $user['friends'][$key]['pfp'] = Cache::remember('pfp_' . $friend['username'], 60 * 60, function() use ($friend) { return User::where('username', $friend['username'])->value('pfp'); });
        }

        foreach($user['CurrentFriends'] as $key => $friend) {
            $user['CurrentFriends'][$key]['id'] = Cache::remember('id_' . $friend['username'], 60 * 60 * 24 * 7, function() use ($friend) { return User::where('username', $friend['username'])->value('id'); });
            $user['CurrentFriends'][$key]['pfp'] = Cache::remember('pfp_' . $friend['username'], 60 * 60, function() use ($friend) { return User::where('username', $friend['username'])->value('pfp'); });
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
            $place['thumbnail'] = Cache::remember('thumbnail_' . $place['id'], 60 * 60, function() use ($place) { return $this->db->table('assets')->select('file')->where('id', $place['additional']['media']['imageAssetId'])->value('file'); });
            $user['places'][] = $place;
        }

        if($this->db->table('bans')->where('username', $user['username'])->where('perm', 'y')->exists()) {
            $user['ban'] = [
                'IsBanned' => true,
                'data' => [
                    'reason' => $this->db->table('bans')->select('reason')->where('username', $user['username'])->where('perm', 'y')->value('reason')
                ]
            ];
        }

        $this->request['data']['profile'] = $user;
        return view($this->request['data']['user']['version'] . '/User', $this->request);
    }

    public function user_add(Request $request, $id) {
        $data = $request->all();

        if(!$this->request['data']['siteusername']) {
            return redirect('/');
        }

        if(!User::where('id', $id)->exists()) {
            Session::put('error', 'User does not exist');
            return redirect('/');
        }

        $user = User::find($id);
        $user->friends = json_decode($user->friends, true);
        
        if($user->username == $this->request['data']['user']['username']) {
            return redirect('/user/' . $id);
        }

        foreach($user->friends as $friend) {
            if($friend['username'] == $this->request['data']['user']['username']) {
                return redirect('/user/' . $id);
            }
        }

        $friends = $user->friends;
        $friends[] = [
            'username' => $this->request['data']['user']['username'],
            'status' => 'pending'
        ];

        $user->friends = json_encode($friends, JSON_FORCE_OBJECT);
        $user->save();

        return redirect('/user/' . $id);
    }

    public function user_accept(Request $request, $id) {
        $data = $request->all();

        if(!$this->request['data']['siteusername']) {
            return redirect('/');
        }

        if(!User::where('id', $id)->exists()) {
            Session::put('error', 'User does not exist');
            return redirect('/');
        }

        $user = User::find($id);
        $user->friends = json_decode($user->friends, true);
        
        if($user->username == $this->request['data']['user']['username']) {
            if(isset($data['feature'])) {
                return redirect('/friends/incoming');
            } else {
                return redirect('/user/' . $id);
            }
        }

        foreach($user->friends as $friend) {
            if($friend['username'] == $this->request['data']['user']['username']) {
                Session::put('error', 'You already added this user');
                if(isset($data['feature'])) {
                    return redirect('/friends/incoming');
                } else {
                    return redirect('/user/' . $id);
                }
            }
        }

        $friends = $user->friends;
        $friends[] = [
            'username' => $this->request['data']['user']['username'],
            'status' => 'friends'
        ];

        $user->friends = json_encode($friends, JSON_FORCE_OBJECT);
        $user->save();

        foreach($this->request['data']['user']['friends'] as $key => $friend) {
            if($friend['username'] == $user->username) {
                $this->request['data']['user']['friends'][$key]['status'] = 'friends';
                break;
            }
        }

        User::where('id', $this->request['data']['user']['id'])->update([
            'friends' => json_encode($this->request['data']['user']['friends'], JSON_FORCE_OBJECT)
        ]);

        if(isset($data['feature'])) {
            return redirect('/friends/incoming');
        } else {
            return redirect('/user/' . $id);
        }
    }

    public function user_remove(Request $request, $id) {
        $data = $request->all();

        if(!$this->request['data']['siteusername']) {
            return redirect('/');
        }

        if(!User::where('id', $id)->exists()) {
            Session::put('error', 'User does not exist');
            return redirect('/');
        }

        $user = User::find($id);
        $user->friends = json_decode($user->friends, true);
        
        if($user->username == $this->request['data']['user']['username']) {
            if(isset($data['feature'])) {
                return redirect('/friends/incoming');
            } else {
                return redirect('/user/' . $id);
            }
        }

        $friends = $user->friends;

        foreach($friends as $key => $friend) {
            if($friend['username'] == $this->request['data']['user']['username']) {
                unset($friends[$key]);
                break;
            }
        }

        $user->friends = json_encode($friends, JSON_FORCE_OBJECT);
        $user->save();

        foreach($this->request['data']['user']['friends'] as $key => $friend) {
            if($friend['username'] == $user->username) {
                unset($this->request['data']['user']['friends'][$key]);
                break;
            }
        }

        User::where('id', $this->request['data']['user']['id'])->update([
            'friends' => json_encode($this->request['data']['user']['friends'], JSON_FORCE_OBJECT)
        ]);

        if(isset($data['feature'])) {
            return redirect('/friends/incoming');
        } else {
            return redirect('/user/' . $id);
        }
    }

    public function user_friends(Request $request, $id) {
        $data = $request->all();

        if(!$this->request['data']['siteusername']) {
            return redirect('/');
        }

        if(!User::where('id', $id)->exists()) {
            return view($this->request['data']['user']['version'] . '/404', [], 404);
        }

        $user = User::find($id)->toArray();
        $user['friends'] = array_reverse(array_filter(json_decode($user['friends'], true), function ($friend) {
            return $friend['status'] == 'friends';
        }));

        foreach($user['friends'] as $key => $friend) {
            $user['friends'][$key]['id'] = Cache::remember('id_' . $friend['username'], 60 * 60, function() use ($friend) { return User::where('username', $friend['username'])->value('id'); });
            $user['friends'][$key]['pfp'] = Cache::remember('pfp_' . $friend['username'], 60 * 60, function() use ($friend) { return User::where('username', $friend['username'])->value('pfp'); });
        }

        $pages_to_show = 10;
        $results_per_page = 12;
        $number_of_pages = ceil(count($user['friends']) / $results_per_page);
        $currentPage = isset($data['page']) ? max(1, intval($data['page'])) : 1;
        $offset = ($currentPage - 1) * $results_per_page;
        $start_page = max(1, min($currentPage - floor($pages_to_show / 2), $number_of_pages - $pages_to_show + 1));
        $end_page = min($number_of_pages, $start_page + $pages_to_show - 1);
        $user['friends'] = array_slice($user['friends'], $offset, $results_per_page);
        
        $this->request['data']['pagination'] = [
            'data' => [],
            'pages' => [
                'info' => [
                    'current_page' => $currentPage,
                    'previous_page' => max(1, $currentPage - 1),
                    'next_page' => min($number_of_pages, $currentPage + 1),
                    'start_page' => $start_page,
                    'end_page' => $end_page,
                    'number_of_pages' => $number_of_pages
                ],
                'data' =>[]
            ]
        ];

        for ($page = $start_page; $page <= $end_page; $page++) {
            $this->request['data']['pagination']['pages']['data'][] = ['page' => $page];
        }

        if(!count($user['friends'])) {
            $this->request['data']['pagination']['pages']['data'][] = [
                'page' => 1
            ];
        }

        $this->request['data']['profile'] = $user;
        $this->request['data']['embeds']['title'] = htmlspecialchars($user['username']) . '\'s Friends' . $this->request['data']['embeds']['title'];

        return view($this->request['data']['user']['version'] . '/User_friends', $this->request);
    }

    public function friends_incoming(Request $request) {
        $this->request['data']['embeds']['title'] = 'Friends Incoming' . $this->request['data']['embeds']['title'];
        $data = $request->all();

        if(!$this->request['data']['siteusername']) {
            return redirect('/');
        }

        $this->request['data']['user']['friends'] = array_reverse(array_filter($this->request['data']['user']['friends'], function ($friend) {
            return $friend['status'] == 'pending';
        }));

        foreach($this->request['data']['user']['friends'] as $key => $friend) {
            $this->request['data']['user']['friends'][$key]['id'] = Cache::remember('id_' . $friend['username'], 60 * 60, function() use ($friend) { return User::where('username', $friend['username'])->value('id'); });
            $this->request['data']['user']['friends'][$key]['pfp'] = Cache::remember('pfp_' . $friend['username'], 60 * 60, function() use ($friend) { return User::where('username', $friend['username'])->value('pfp'); });
        }

        return view($this->request['data']['user']['version'] . '/User_friends_incoming', $this->request);
    }

    public function users(Request $request) {
        $this->request['data']['embeds']['title'] = 'Users' . $this->request['data']['embeds']['title'];
        $data = $request->all();

        if(!$this->request['data']['siteusername']) {
            return redirect('/');
        }

        $users = [];
        $pages_to_show = 10;
        $results_per_page = 16;

        if(isset($data['search'])) {
            $search = '%' . htmlspecialchars($data['search']) . '%';
            $results = User::whereRaw('LOWER(username) LIKE LOWER(?)', ["%{$search}%"])
                ->orderBy('lastlogin', 'desc')
                ->count();
        } else {
            $results = User::orderBy('lastlogin', 'desc')
                ->count();
        }

        $number_of_pages = ceil($results / $results_per_page);
        $currentPage = isset($data['page']) ? max(1, intval($data['page'])) : 1;
        $offset = ($currentPage - 1) * $results_per_page;
        $start_page = max(1, min($currentPage - floor($pages_to_show / 2), $number_of_pages - $pages_to_show + 1));
        $end_page = min($number_of_pages, $start_page + $pages_to_show - 1);

        if(isset($data['search'])) {
            $results = User::whereRaw('LOWER(username) LIKE LOWER(?)', ["%{$search}%"])
                ->orderBy('lastlogin', 'desc')
                ->offset($offset)
                ->limit($results_per_page)
                ->get()
                ->toArray();
        } else {
            $results = User::orderBy('lastlogin', 'desc')
                ->offset($offset)
                ->limit($results_per_page)
                ->get()
                ->toArray();
        }

        foreach($results as $result) {
            $users[] = [
                'id' => $result['id'],
                'username' => $result['username'],
                'pfp' => $result['pfp'],
                'lastlogin' => date('m/d/Y h:i A', strtotime($result['lastlogin'])),
                'IsOnline' => Carbon::parse($result['lastlogin'])->gt(Carbon::now()->subMinutes(2))
            ];
        }

        $this->request['data']['pagination'] = [
            'data' => [],
            'pages' => [
                'info' => [
                    'current_page' => $currentPage,
                    'previous_page' => max(1, $currentPage - 1),
                    'next_page' => min($number_of_pages, $currentPage + 1),
                    'start_page' => $start_page,
                    'end_page' => $end_page,
                    'number_of_pages' => $number_of_pages
                ],
                'data' =>[]
            ]
        ];

        for ($page = $start_page; $page <= $end_page; $page++) {
            $this->request['data']['pagination']['pages']['data'][] = ['page' => $page];
        }

        if(!count($users)) {
            $this->request['data']['pagination']['pages']['data'][] = [
                'page' => 1
            ];
        }

        $this->request['data']['users'] = $users;
        $this->request['data']['search'] = isset($data['search']) ? $data['search'] : false;

        return view($this->request['data']['user']['version'] . '/Users', $this->request);
    }

    public function forum_home(Request $request) {
        $data = $request->all();
        $this->request['data']['embeds']['title'] = 'Forum' . $this->request['data']['embeds']['title'];
        $this->request['data']['section'] = false;

        $posts = $this->db->table('forum_threads')->count();
        
        $pages_to_show = 10;
        $results_per_page = 10;
        $number_of_pages = ceil($posts / $results_per_page);
        $currentPage = isset($data['page']) ? max(1, intval($data['page'])) : 1;
        $offset = ($currentPage - 1) * $results_per_page;
        $start_page = max(1, min($currentPage - floor($pages_to_show / 2), $number_of_pages - $pages_to_show + 1));
        $end_page = min($number_of_pages, $start_page + $pages_to_show - 1);

        $posts = $this->db->table('forum_threads')
            ->orderBy('pinned', 'DESC')
            ->orderBy('lastreplied', 'DESC')
            ->offset($offset)
            ->limit($results_per_page)
            ->get()
            ->map(function ($item) {
                return (array) $item;
            })->toArray();
        
        $this->request['data']['threads'] = [
            'data' => [],
            'pages' => [
                'info' => [
                    'current_page' => $currentPage,
                    'previous_page' => max(1, $currentPage - 1),
                    'next_page' => min($number_of_pages, $currentPage + 1),
                    'start_page' => $start_page,
                    'end_page' => $end_page,
                    'number_of_pages' => $number_of_pages
                ],
                'data' =>[]
            ]
        ];

        $phrasesToReplace = [
            'fuck',
            'fucking',
            'roblox',
            'robux',
            'ass',
            'asshole',
            'shit'
        ];

        $replacements = [
            'OBAMA BALL',
            'sonic 06',
            'blockland.us'
        ];

        foreach($posts as $post) {
            $post['status'] = User::where('username', $post)->value('status');
            $post['replies'] = $this->db->table('forum_replies')->where('toid', $post['id'])->count();
            $post['title'] = htmlspecialchars($post['title']);
            $post['title'] = preg_replace_callback('/\b(' . implode('|', array_map('preg_quote', $phrasesToReplace)) . ')\b/i', function ($matches) use ($replacements) {
                return $replacements[array_rand($replacements)];
            }, $post['title']);
            $post['author'] = htmlspecialchars($post['author']);
            $post['ago'] = $this->dataService->time_elapsed_string($post['date']);
            $post['date'] = date('F d, Y g:i a', strtotime($post['date']));
            $post['rating'] = $this->db->table('forum_ratings')->where('type', '1')->where('toid', $post['id'])->where('rate_type', 'l')->count();
            $post['rating'] = $post['rating'] - $this->db->table('forum_ratings')->where('type', '1')->where('toid', $post['id'])->where('rate_type', 'd')->count();
            $this->request['data']['threads']['data'][] = $post;
        }

        for ($page = $start_page; $page <= $end_page; $page++) {
            $this->request['data']['threads']['pages']['data'][] = ['page' => $page];
        }

        if(!count($posts)) {
            $this->request['data']['threads']['pages']['data'][] = [
                'page' => 1
            ];
        }

        return view($this->request['data']['user']['version'] . '/Forum/Index', $this->request);
    }

    public function forum_section(Request $request, $section) {
        $data = $request->all();
        $this->request['data']['embeds']['title'] = 'Forum' . $this->request['data']['embeds']['title'];
        $this->request['data']['section'] = $section;

        $posts = $this->db->table('forum_threads')
            ->where('category', $section)
            ->count();
        
        $pages_to_show = 10;
        $results_per_page = 10;
        $number_of_pages = ceil($posts / $results_per_page);
        $currentPage = isset($data['page']) ? max(1, intval($data['page'])) : 1;
        $offset = ($currentPage - 1) * $results_per_page;
        $start_page = max(1, min($currentPage - floor($pages_to_show / 2), $number_of_pages - $pages_to_show + 1));
        $end_page = min($number_of_pages, $start_page + $pages_to_show - 1);

        $posts = $this->db->table('forum_threads')
            ->where('category', $section)
            ->orderBy('pinned', 'DESC')
            ->orderBy('lastreplied', 'DESC')
            ->offset($offset)
            ->limit($results_per_page)
            ->get()
            ->map(function ($item) {
                return (array) $item;
            })->toArray();
        
        $this->request['data']['threads'] = [
            'data' => [],
            'pages' => [
                'info' => [
                    'current_page' => $currentPage,
                    'previous_page' => max(1, $currentPage - 1),
                    'next_page' => min($number_of_pages, $currentPage + 1),
                    'start_page' => $start_page,
                    'end_page' => $end_page,
                    'number_of_pages' => $number_of_pages
                ],
                'data' =>[]
            ]
        ];

        foreach($posts as $post) {
            $post['status'] = User::where('username', $post)->value('status');
            $post['replies'] = $this->db->table('forum_replies')->where('toid', $post['id'])->count();
            $post['title'] = htmlspecialchars($post['title']);
            $post['author'] = htmlspecialchars($post['author']);
            $post['ago'] = $this->dataService->time_elapsed_string($post['date']);
            $post['date'] = date('F d, Y g:i a', strtotime($post['date']));
            $post['rating'] = $this->db->table('forum_ratings')->where('type', '1')->where('toid', $post['id'])->where('rate_type', 'l')->count();
            $post['rating'] = $post['rating'] - $this->db->table('forum_ratings')->where('type', '1')->where('toid', $post['id'])->where('rate_type', 'd')->count();
            $this->request['data']['threads']['data'][] = $post;
        }

        for ($page = $start_page; $page <= $end_page; $page++) {
            $this->request['data']['threads']['pages']['data'][] = ['page' => $page];
        }

        if(!count($posts)) {
            $this->request['data']['threads']['pages']['data'][] = [
                'page' => 1
            ];
        }

        return view($this->request['data']['user']['version'] . '/Forum/Index', $this->request);
    }

    public function forum_search(Request $request) {
        $data = $request->all();
        $this->request['data']['embeds']['title'] = 'Forum' . $this->request['data']['embeds']['title'];

        if(!isset($data['q']) || empty($data['q'])) {
            return redirect('/forum/home');
        }

        $this->request['data']['search'] = htmlspecialchars($data['q']);
        $search = '%' . htmlspecialchars($data['q']) . '%';

        $posts = $this->db->table('forum_threads')
            ->whereRaw('LOWER(title) LIKE ?', [$search])
            ->count();
        
        $pages_to_show = 10;
        $results_per_page = 10;
        $number_of_pages = ceil($posts / $results_per_page);
        $currentPage = isset($data['page']) ? max(1, intval($data['page'])) : 1;
        $offset = ($currentPage - 1) * $results_per_page;
        $start_page = max(1, min($currentPage - floor($pages_to_show / 2), $number_of_pages - $pages_to_show + 1));
        $end_page = min($number_of_pages, $start_page + $pages_to_show - 1);

        $posts = $this->db->table('forum_threads')
            ->whereRaw('LOWER(title) LIKE ?', [$search])
            ->orderBy('pinned', 'DESC')
            ->orderBy('lastreplied', 'DESC')
            ->offset($offset)
            ->limit($results_per_page)
            ->get()
            ->map(function ($item) {
                return (array) $item;
            })->toArray();
        
        $this->request['data']['threads'] = [
            'data' => [],
            'pages' => [
                'info' => [
                    'current_page' => $currentPage,
                    'previous_page' => max(1, $currentPage - 1),
                    'next_page' => min($number_of_pages, $currentPage + 1),
                    'start_page' => $start_page,
                    'end_page' => $end_page,
                    'number_of_pages' => $number_of_pages
                ],
                'data' =>[]
            ]
        ];

        $phrasesToReplace = [
            'fuck',
            'fucking',
            'roblox',
            'robux',
            'ass',
            'asshole',
            'shit'
        ];

        $replacements = [
            'OBAMA BALL',
            'sonic 06',
            'blockland.us'
        ];

        foreach($posts as $post) {
            $post['status'] = User::where('username', $post)->value('status');
            $post['replies'] = $this->db->table('forum_replies')->where('toid', $post['id'])->count();
            $post['title'] = htmlspecialchars($post['title']);
            $post['title'] = preg_replace_callback('/\b(' . implode('|', array_map('preg_quote', $phrasesToReplace)) . ')\b/i', function ($matches) use ($replacements) {
                return $replacements[array_rand($replacements)];
            }, $post['title']);
            $post['author'] = htmlspecialchars($post['author']);
            $post['ago'] = $this->dataService->time_elapsed_string($post['date']);
            $post['date'] = date('F d, Y g:i a', strtotime($post['date']));
            $post['rating'] = $this->db->table('forum_ratings')->where('type', '1')->where('toid', $post['id'])->where('rate_type', 'l')->count();
            $post['rating'] = $post['rating'] - $this->db->table('forum_ratings')->where('type', '1')->where('toid', $post['id'])->where('rate_type', 'd')->count();
            $this->request['data']['threads']['data'][] = $post;
        }

        for ($page = $start_page; $page <= $end_page; $page++) {
            $this->request['data']['threads']['pages']['data'][] = ['page' => $page];
        }

        if(!count($posts)) {
            $this->request['data']['threads']['pages']['data'][] = [
                'page' => 1
            ];
        }

        return view($this->request['data']['user']['version'] . '/Forum/Index', $this->request);
    }

    public function forum_post(Request $request) {
        $data = $request->all();

        if($request->isMethod('post')) {
            if(!$this->request['data']['siteusername']) {
                return redirect('/');
            }

            $validator = Validator::make($data, [
                'id' => 'required|integer',
                'content' => 'required|string|min:3|max:16384'
            ]);

            if(!filter_var(env('FINOBE_FORUM_POST'), FILTER_VALIDATE_BOOLEAN)) {
                Session::put('error', 'Posting on the forums have been disabled');
                return redirect('/forum/post?id=' . $data['id']);
            }

            if($validator->fails()) {
                Session::put('error', $validator->errors()->first());
                return redirect('/forum/home');
            }

            if(!$this->db->table('forum_threads')->where('id', $data['id'])->exists()) {
                Session::put('error', 'This post doesn\'t exist');
                return redirect('/forum/home');
            }

            $post = (array) $this->db->table('forum_threads')
                ->where('id', $data['id'])
                ->first();
            
            if($post['author'] != $this->request['data']['user']['username']) {
                Session::put('error', 'You do not own this post');
                return redirect('/forum/post?id=' . $data['id']);
            }

            if($post['locked'] == 'y') {
                Session::put('error', 'This post is locked');
                return redirect('/forum/post?id=' . $data['id']);
            }

            $this->db->table('forum_threads')
                ->where('id', $data['id'])
                ->update([
                    'comment' => trim($data['content'])
                ]);
            
            /*
            $this->db->table('users')
                ->where('username', $this->request['data']['user']['username'])
                ->update([
                    'post_cooldown' => DB::raw('CURRENT_TIMESTAMP()')
                ]);

            */
            $user = User::find($this->request['data']['user']['id']);
            $user->post_cooldown = now();
            $user->save();
            
            Session::put('success', 'Successfully edited.');
            return redirect('/forum/post?id=' . $data['id']);
        }

        if(!isset($data['id']) || empty($data['id'])) {
            return redirect('/forum/home');
        }

        if(!$this->db->table('forum_threads')->where('id', $data['id'])->exists()) {
            Session::put('error', 'This thread doesn\'t exist or was deleted.');
            return redirect('/forum/home');
        }

        $post = (array) $this->db->table('forum_threads')
            ->where('id', $data['id'])
            ->first();

        if(isset($data['edit']) && (!$this->request['data']['siteusername'] || $post['author'] != $this->request['data']['user']['username'])) {
            return redirect('/forum/post?id=' . $data['id']);
        }
        
        $this->request['data']['embeds']['title'] = htmlspecialchars($post['title']) . $this->request['data']['embeds']['title'];
        $this->request['data']['editing'] = isset($data['edit']);
        $post['title'] = htmlspecialchars($post['title']);
        $post['author'] = htmlspecialchars($post['author']);
        $post['format_date'] = date('M d Y h:i:s A', strtotime($post['date']));
        $post['date'] = date('m/d/Y h:i A', strtotime($post['date']));
        $post['edited_date'] = date('m/d/Y h:i A', strtotime($post['edited_date']));

        $user = User::where('username', $post['author'])->select('id', 'status', 'pfp', 'lastlogin', 'badges')->first()?->toArray();
        $post['uuid'] = $user['id'];
        $post['status'] = $user['status'];
        $post['pfp'] = $user['pfp'];
        $post['posts'] = $this->db->table('forum_threads')->where('author', $post['author'])->count() + $this->db->table('forum_replies')->where('author', $post['author'])->count();
        $post['badges'] = json_decode($user['badges'], true)['data']['custom_badges'] ?? [];

        $phrasesToReplace = [
            'fuck',
            'fucking',
            'roblox',
            'robux',
            'ass',
            'asshole',
            'shit'
        ];

        $replacements = [
            'OBAMA BALL',
            'sonic 06',
            'blockland.us'
        ];

        $post['comment'] = preg_replace_callback('/\b(' . implode('|', array_map('preg_quote', $phrasesToReplace)) . ')\b/i', function ($matches) use ($replacements) {
            return $replacements[array_rand($replacements)];
        }, $post['comment']);

        if($post['status'] == "admin") {
            $post['comment'] = nl2br(preg_replace_callback('/(<img[^>]*>|\b(?:https?|ftp):\/\/\S+)/i', function ($matches) {
                if (strpos($matches[0], '<img') === 0) {
                    return $matches[0];
                } else {
                    return '<a href="' . $matches[0] . '" target="_blank">' . $matches[0] . '</a>';
                }
            }, $post['comment']));
        } else {
            $post['comment'] = nl2br(preg_replace('/\b((?:https?|ftp):\/\/\S+)/i', '<a href="$1" target="_blank">$1</a>', strip_tags(htmlspecialchars($post['comment']))));
        }

        $post['rating'] = $this->db->table('forum_ratings')->where('type', '1')->where('toid', $post['id'])->where('rate_type', 'l')->count();
        $post['upvotes'] = $post['rating'];
        $post['rating'] -= $this->db->table('forum_ratings')->where('type', '1')->where('toid', $post['id'])->where('rate_type', 'd')->count();
        $post['downvotes'] = $this->db->table('forum_ratings')->where('type', '1')->where('toid', $post['id'])->where('rate_type', 'd')->count();

        if($this->request['data']['siteusername']) {
            if($this->db->table('forum_ratings')->where('type', '1')->where('toid', $post['id'])->where('sender', $this->request['data']['user']['username'])->exists()) {
                $post['userRating'] = $this->db->table('forum_ratings')->select('rate_type')->where('type', '1')->where('toid', $post['id'])->where('sender', $this->request['data']['user']['username'])->value('rate_type');
            }

            $post['subscription'] = $this->db->table('subscriptions')->where('username', $this->request['data']['user']['username'])->where('forumId', $post['id'])->exists();
        }

        $post['online'] = Carbon::parse($user['lastlogin'])->gt(Carbon::now()->subMinutes(2));
        $this->request['data']['post'] = $post;

        if($this->db->table('forum_replies')->where('toid', $post['id'])->where('sticked', 'y')->exists()) {
            $sticked = (array) $this->db->table('forum_replies')
                ->where('toid', $post['id'])
                ->where('sticked', 'y')
                ->limit(1)
                ->first();
            
            $sticked['author'] = htmlspecialchars($sticked['author']);
            $sticked['date'] = date('m/d/Y h:i A', strtotime($sticked['date']));
            $sticked['edited_date'] = date('m/d/Y h:i A', strtotime($sticked['edited_date']));

            $user = User::where('username', $sticked['author'])->select('id', 'status', 'pfp', 'lastlogin', 'badges')->first()?->toArray();
            $sticked['uuid'] = $user['id'];
            $sticked['status'] = $user['status'];
            $sticked['pfp'] = $user['pfp'];
            $sticked['posts'] = $this->db->table('forum_threads')->where('author', $sticked['author'])->count() + $this->db->table('forum_replies')->where('author', $sticked['author'])->count();
            $sticked['badges'] = json_decode($user['badges'], true)['data']['custom_badges'] ?? [];
            $sticked['comment'] = preg_replace_callback('/\b(' . implode('|', array_map('preg_quote', $phrasesToReplace)) . ')\b/i', function ($matches) use ($replacements) {
                return $replacements[array_rand($replacements)];
            }, $sticked['comment']);

            if($sticked['status'] == "admin") {
                $sticked['comment'] = nl2br(preg_replace_callback('/(<img[^>]*>|\b(?:https?|ftp):\/\/\S+)/i', function ($matches) {
                    if (strpos($matches[0], '<img') === 0) {
                        return $matches[0];
                    } else {
                        return '<a href="' . $matches[0] . '" target="_blank">' . $matches[0] . '</a>';
                    }
                }, $sticked['comment']));
            } else {
                $sticked['comment'] = nl2br(preg_replace('/\b((?:https?|ftp):\/\/\S+)/i', '<a href="$1" target="_blank">$1</a>', strip_tags(htmlspecialchars($sticked['comment']))));
            }

            $sticked['rating'] = $this->db->table('forum_ratings')->where('type', '2')->where('toid', $sticked['id'])->where('rate_type', 'l')->count();
            $sticked['upvotes'] = $sticked['rating'];
            $sticked['rating'] -= $this->db->table('forum_ratings')->where('type', '2')->where('toid', $sticked['id'])->where('rate_type', 'd')->count();
            $sticked['downvotes'] = $this->db->table('forum_ratings')->where('type', '2')->where('toid', $sticked['id'])->where('rate_type', 'd')->count();

            if($sticked['replyTo']) {
                $sticked['replyComment'] = $this->db->table('forum_replies')->select('comment')->where('id', $sticked['replyTo'])->value('comment');
            }

            if($this->request['data']['siteusername']) {
                if($this->db->table('forum_ratings')->where('type', '2')->where('toid', $sticked['id'])->where('sender', $this->request['data']['user']['username'])->exists()) {
                    $sticked['userRating'] = $this->db->table('forum_ratings')->select('rate_type')->where('type', '2')->where('toid', $sticked['id'])->where('sender', $this->request['data']['user']['username'])->value('rate_type');
                }

                $sticked['subscription'] = $this->db->table('subscriptions')->where('username', $this->request['data']['user']['username'])->where('forumId', $sticked['id'])->exists();
            }

            $sticked['online'] = Carbon::parse($user['lastlogin'])->gt(Carbon::now()->subMinutes(2));
            $this->request['data']['sticked'] = $sticked;
        }

        $replies = $this->db->table('forum_replies')
            ->where('toid', $post['id'])
            ->count();
        
        $pages_to_show = 10;
        $results_per_page = 10;
        $number_of_pages = ceil($replies / $results_per_page);
        $currentPage = isset($data['page']) ? max(1, intval($data['page'])) : 1;
        $offset = ($currentPage - 1) * $results_per_page;
        $start_page = max(1, min($currentPage - floor($pages_to_show / 2), $number_of_pages - $pages_to_show + 1));
        $end_page = min($number_of_pages, $start_page + $pages_to_show - 1);

        $replies = $this->db->table('forum_replies')
            ->where('toid', $post['id'])
            ->offset($offset)
            ->limit($results_per_page)
            ->get()
            ->map(function ($item) {
                return (array) $item;
            })->toArray();
        
        $this->request['data']['replies'] = [
            'data' => [],
            'pages' => [
                'info' => [
                    'current_page' => $currentPage,
                    'previous_page' => max(1, $currentPage - 1),
                    'next_page' => min($number_of_pages, $currentPage + 1),
                    'start_page' => $start_page,
                    'end_page' => $end_page,
                    'number_of_pages' => $number_of_pages
                ],
                'data' =>[]
            ]
        ];

        foreach($replies as $reply) {
            $reply['author'] = htmlspecialchars($reply['author']);
		    $reply['format_date'] = date('M d Y h:i:s A', strtotime($reply['date']));
            $reply['date'] = date('m/d/Y h:i A', strtotime($reply['date']));
            $reply['edited_date'] = date('m/d/Y h:i A', strtotime($reply['edited_date']));

            $user = User::where('username', $reply['author'])->select('id', 'status', 'pfp', 'lastlogin', 'badges')->first()?->toArray();
            $reply['uuid'] = $user['id'];
            $reply['status'] = $user['status'];
            $reply['pfp'] = $user['pfp'];
            $reply['posts'] = $this->db->table('forum_threads')->where('author', $reply['author'])->count() + $this->db->table('forum_replies')->where('author', $reply['author'])->count();
            $reply['badges'] = json_decode($user['badges'], true)['data']['custom_badges'] ?? [];
            $reply['comment'] = preg_replace_callback('/\b(' . implode('|', array_map('preg_quote', $phrasesToReplace)) . ')\b/i', function ($matches) use ($replacements) {
                return $replacements[array_rand($replacements)];
            }, $reply['comment']);

            if($reply['status'] == "admin") {
                $reply['comment'] = nl2br(preg_replace_callback('/(<img[^>]*>|\b(?:https?|ftp):\/\/\S+)/i', function ($matches) {
                    if (strpos($matches[0], '<img') === 0) {
                        return $matches[0];
                    } else {
                        return '<a href="' . $matches[0] . '" target="_blank">' . $matches[0] . '</a>';
                    }
                }, $reply['comment']));
            } else {
                $reply['comment'] = nl2br(preg_replace('/\b((?:https?|ftp):\/\/\S+)/i', '<a href="$1" target="_blank">$1</a>', strip_tags(htmlspecialchars($reply['comment']))));
            }

            $reply['rating'] = $this->db->table('forum_ratings')->where('type', '2')->where('toid', $reply['id'])->where('rate_type', 'l')->count();
            $reply['upvotes'] = $reply['rating'];
            $reply['rating'] -= $this->db->table('forum_ratings')->where('type', '2')->where('toid', $reply['id'])->where('rate_type', 'd')->count();
            $reply['downvotes'] = $this->db->table('forum_ratings')->where('type', '2')->where('toid', $reply['id'])->where('rate_type', 'd')->count();

            if($reply['replyTo']) {
                $reply['replyComment'] = $this->db->table('forum_replies')->select('comment')->where('id', $reply['replyTo'])->value('comment');
            }

            if($this->request['data']['siteusername']) {
                if($this->db->table('forum_ratings')->where('type', '2')->where('toid', $reply['id'])->where('sender', $this->request['data']['user']['username'])->exists()) {
                    $reply['userRating'] = $this->db->table('forum_ratings')->select('rate_type')->where('type', '2')->where('toid', $reply['id'])->where('sender', $this->request['data']['user']['username'])->value('rate_type');
                }

                $reply['subscription'] = $this->db->table('subscriptions')->where('username', $this->request['data']['user']['username'])->where('forumId', $reply['id'])->exists();
            }

            $reply['online'] = Carbon::parse($user['lastlogin'])->gt(Carbon::now()->subMinutes(2));
            $this->request['data']['replies']['data'][] = $reply;
        }

        for ($page = $start_page; $page <= $end_page; $page++) {
            $this->request['data']['replies']['pages']['data'][] = ['page' => $page];
        }

        if(!count($replies)) {
            $this->request['data']['replies']['pages']['data'][] = [
                'page' => 1
            ];
        }

        return view($this->request['data']['user']['version'] . '/Forum/Post', $this->request);
    }

    public function forum_reply(Request $request) {
        $data = $request->all();

        if(!$this->request['data']['siteusername']) {
            return redirect('/');
        }

        if(!isset($data['id'])) {
            return redirect('/forum/home');
        }

        if(!$this->db->table('forum_threads')->where('id', $data['id'])->exists()) {
            Session::put('error', 'This post does not exist');
            return redirect('/forum/home');
        }

        if($request->isMethod('post')) {
            $validator = Validator::make($data, [
                'id' => 'required|integer',
                'content' => 'required|string|min:3|max:16384'
            ]);

            if(!filter_var(env('FINOBE_FORUM_POST'), FILTER_VALIDATE_BOOLEAN)) {
                Session::put('error', 'Posting on the forums have been disabled');
                return redirect('/forum/post?id=' . $data['id']);
            }

            if($validator->fails()) {
                Session::put('error', $validator->errors()->first());
                return redirect('/forum/home');
            }

            if(User::where('username', $this->request['data']['user']['username'])->where('post_cooldown', '>=', DB::raw('NOW() - INTERVAL 5 MINUTE'))->exists()) {
                Session::put('error', 'You cannot make another post within 5 minutes of your last one.');
                return redirect('/forum/post?id=' . $data['id']);
            }

            if(!$this->db->table('forum_threads')->where('id', $data['id'])->exists()) {
                Session::put('error', 'This post doesn\'t exist');
                return redirect('/forum/home');
            }

            $post = (array) $this->db->table('forum_threads')
                ->where('id', $data['id'])
                ->first();
            
            if($post['locked'] == 'y') {
                Session::put('error', 'This post is locked');
                return redirect('/forum/home');
            }
            
            if($this->db->table('forum_threads')->where('id', $data['id'])->where(DB::raw('DATE(lastreplied)'), '<=', DB::raw('DATE_SUB(NOW(), INTERVAL 3 WEEK)'))->exists() && $this->request['data']['user']['status'] != 'admin') {
                if($this->db->table('warning')->where('username', $this->request['data']['user']['username'])->where('date', '>=', DB::raw('DATE_SUB(NOW(), INTERVAL 1 MONTH)'))->count() >= 2) {
                    $this->db->table('bans')->insert([
                        'username' => $this->request['data']['user']['username'],
                        'reason' => 'You are not allowed to necrobump threads that have been inactive for 3 weeks.',
                        'expire' => date('Y-m-d H:i:s', strtotime('+1 week')),
                        'moderator' => 'Auto'
                    ]);
                } else {
                    $this->db->table('warning')->insert([
                        'username' => $this->request['data']['user']['username'],
                        'reason' => 'You are not allowed to necrobump threads that have been inactive for 3 weeks.',
                        'moderator' => 'Auto'
                    ]);
                }
            }

            $id = $this->db->table('forum_replies')->insertGetId([
                'toid' => $data['id'],
                'author' => $this->request['data']['user']['username'],
                'comment' => $data['content']
            ]);

            if($post['author'] != $this->request['data']['user']['username']) {
                $this->db->table('pms')->insert([
                    'owner' => $this->request['data']['user']['username'],
                    'subject' => '',
                    'touser' => $post['author'],
                    'message' => $this->request['data']['user']['username'] . ' replied to ' . $post['title'],
                    'forum_id' => $data['id'],
                    'reply_id' => $id
                ]);
            }

            $subscriptions = $this->db->table('subscriptions')
                ->where('forumId', $data['id'])
                ->get()
                ->map(function ($item) {
                    return (array) $item;
                })->toArray();
            
            foreach($subscriptions as $subscription) {
                $this->db->table('pms')->insert([
                    'owner' => $this->request['data']['user']['username'],
                    'subject' => '',
                    'touser' => $subscription['username'],
                    'message' => $this->request['data']['user']['username'] . ' replied to ' . $post['title'],
                    'forum_id' => $data['id'],
                    'reply_id' => $id
                ]);
            }

            if(isset($data['reply']) && $this->db->table('forum_replies')->where('id', $data['reply'])->exists()) {
                $this->db->table('pms')->insert([
                    'owner' => $this->request['data']['user']['username'],
                    'subject' => '',
                    'touser' => $this->db->table('forum_replies')->select('author')->where('id', $data['reply'])->value('author'),
                    'message' => $this->request['data']['user']['username'] . ' replied to your reply on ' . $post['title'],
                    'forum_id' => $data['id']
                ]);

                $this->db->table('forum_replies')
                    ->where('id', $id)
                    ->update([
                        'replyTo' => $data['reply']
                    ]);
            }
            
            $user = User::find($this->request['data']['user']['id']);
            $user->post_cooldown = now();
            $user->save();
            
            $this->db->table('forum_threads')
                ->where('id', $data['id'])
                ->update([
                    'lastreplied' => DB::raw('CURRENT_TIMESTAMP()')
                ]);
            
            $results_per_page = 12;
            $position_in_list = $this->db->table('forum_replies')->where('id', '<=', $id)->where('toid', $data['id'])->count();
            $page_of_reply = ceil($position_in_list / $results_per_page);

            Session::put('success', 'Successfully created.');
            return redirect('/forum/post?id=' . $data['id'] . '&page=' . $page_of_reply);
        }

        $post = (array) $this->db->table('forum_threads')
            ->where('id', $data['id'])
            ->first();
        
        if($post['locked'] == 'y') {
            Session::put('error', 'This post is locked');
            return redirect('/forum/post?id=' . $data['id']);
        }

        $this->request['data']['embeds']['title'] = htmlspecialchars($post['title']) . $this->request['data']['embeds']['title'];
        $this->request['data']['replying'] = isset($data['reply']) ? $data['reply'] : false;
        $post['title'] = htmlspecialchars($post['title']);
        $this->request['data']['post'] = $post;

        return view($this->request['data']['user']['version'] . '/Forum/Reply', $this->request);
    }

    public function forum_edit_reply(Request $request) {
        $this->request['data']['embeds']['title'] = 'Edit Reply' . $this->request['data']['embeds']['title'];
        $data = $request->all();

        if(!$this->request['data']['siteusername']) {
            return redirect('/');
        }

        if($request->isMethod('post')) {
            $validator = Validator::make($data, [
                'id' => 'required|integer',
                'postId' => 'required|integer',
                'content' => 'required|string|min:3|max:8192'
            ]);

            if($validator->fails()) {
                Session::put('error', $validator->errors()->first());
                return redirect('/forum/edit?id=' . $data['id']);
            }

            if(!$this->db->table('forum_threads')->where('id', $data['postId'])->exists() || !$this->db->table('forum_replies')->where('id', $data['id'])->exists()) {
                Session::put('error', 'This post or reply does not exist');
                return redirect('/forum/home');
            }

            $reply = (array) $this->db->table('forum_replies')
            ->where('id', $data['id'])
            ->first();
        
            if($reply['author'] != $this->request['data']['user']['username']) {
                Session::put('error', 'You do not own this reply');
                return redirect('/forum/home');
            }

            $post = (array) $this->db->table('forum_threads')
                ->where('id', $reply['toid'])
                ->first();
            
            if($post['locked'] == 'y') {
                Session::put('error', 'This post is locked');
                return redirect('/forum/edit?id=' . $data['id']);
            }

            $this->db->table('forum_replies')
                ->where('id', $data['id'])
                ->update([
                    'comment' => $data['content'],
                    'edited' => 'y',
                    'edited_date' => DB::raw('CURRENT_TIMESTAMP()')
                ]);
            
            $results_per_page = 12;
            $position_in_list = $this->db->table('forum_replies')->where('id', '<=', $data['id'])->where('toid', $data['postId'])->count();
            $page_of_reply = ceil($position_in_list / $results_per_page);

            Session::put('success', 'Successfully edited.');
            return redirect('/forum/post?id=' . $data['postId'] . '&page=' . $page_of_reply);
        }

        if(!isset($data['id'])) {
            return redirect('/forum/home');
        }

        if(!$this->db->table('forum_replies')->where('id', $data['id'])->exists()) {
            Session::put('error', 'This reply doesn\'t exist or was deleted');
            return redirect('/forum/home');
        }

        $reply = (array) $this->db->table('forum_replies')
            ->where('id', $data['id'])
            ->first();
        
        if($reply['author'] != $this->request['data']['user']['username']) {
            Session::put('error', 'You do not own this reply');
            return redirect('/forum/home');
        }

        $post = (array) $this->db->table('forum_threads')
            ->where('id', $reply['toid'])
            ->first();

        $post['title'] = htmlspecialchars($post['title']);
        $this->request['data']['post'] = $post;
        $this->request['data']['reply'] = $reply;

        return view($this->request['data']['user']['version'] . '/Forum/Edit', $this->request);
    }

    public function forum_new_post(Request $request) {
        $this->request['data']['embeds']['title'] = 'New Post' . $this->request['data']['embeds']['title'];
        $data = $request->all();

        if(!$this->request['data']['siteusername']) {
            return redirect('/');
        }

        if($request->isMethod('post')) {
            if($request->hasFile('file')) {
                if($this->request['data']['user']['status'] == 'admin') {
                    return redirect('/app/forum/new/post');
                }

                $validator = Validator::make($data, [
                    'file' => 'required|file|mimes:jpg,jpeg,png,pdf,doc,docx,mp4,mov,gif,exe,ttf,webm,webp'
                ]);

                if($validator->fails()) {
                    Session::put('error', $validator->errors()->first());
                    return redirect('/app/forum/new/post');
                }

                $file = $request->file('file');
                $fileUrl = 'https://cdn.finobe.net/forum/media/' . $file->getClientOriginalName();

                if(file_exists('/var/www/cdn.finobe.net/forum/media/' . $file->getClientOriginalName())) {
                    Session::put('error', 'File already exists. Here is the link: ' . $fileUrl);
                    return redirect('/app/forum/new/post');
                }

                try {
                    $file->move('/var/www/cdn.finobe.net/forum/media', $file->getClientOriginalName());
                    Session::put('success', 'File uploaded successfully. Here is the link: ' . $fileUrl);
                    return redirect('/app/forum/new/post');
                } catch(\Exception $e) {
                    Session::put('error', 'Error uploading file. Check your server configurations.');
                    return redirect('/app/forum/new/post');
                }
            } else {
                $validator = Validator::make($data, [
                    'title' => 'required|string|min:3',
                    'content' => 'required|string|min:3|max:8192',
                    'section' => 'required|integer|size:1'
                ]);

                if($validator->fails()) {
                    Session::put('error', $validator->errors()->first());
                    return redirect('/app/forum/new/post');
                }

                if($data['section'] == '1' && $this->request['data']['user']['status'] != 'admin') {
                    Session::put('error', 'Not enough permissions');
                    return redirect('/app/forum/new/post');
                }

                $id = $this->db->table('forum_threads')->insertGetId([
                    'category' => $data['section'],
                    'author' => $this->request['data']['user']['username'],
                    'title' => $data['title'],
                    'comment' => $data['content']
                ]);

                $user = User::find($this->request['data']['user']['id']);
                $user->post_cooldown = now();
                $user->save();

                Session::put('success', 'Successfully created.');
                return redirect('/forum/post?id=' . $id);
            }
        }

        if($this->request['data']['user']['status'] == 'admin') {
            function displayDirectory($dir) {
                $files = scandir($dir);
                $html = '<ul>';
                foreach($files as $file) {
                    if($file != '.' && $file != '..') {
                        $path = $dir . '/' . $file;
                        $path2 = 'https://cdn.finobe.net/forum/media/' . $file;
                        $html .= '<li>';
                        if(is_dir($path)) {
                            $html .= '<strong>' . $file . '</strong>';
                            displayDirectory($path);
                        } else {
                            $html .= '<a href="' . $path2 . '" target="_blank">' . $file . '</a>';
                        }

                        $html .= '</li>';
                    }
                }

                $html .= '</ul>';
                return $html;
            }

            $this->request['data']['list'] = displayDirectory('/var/www/cdn.finobe.net/forum/media');
        }

        return view($this->request['data']['user']['version'] . '/Forum/New/Post', $this->request);
    }

    public function catalog_index(Request $request, $section) {
        $this->request['data']['embeds']['title'] = 'Catalog' . $this->request['data']['embeds']['title'];
        $data = $request->all();

        if(!$this->request['data']['siteusername']) {
            return redirect('/');
        }

        $sections = [
            "hats" => 8,
            "t-shirts" => 2,
            "shirts" => 11,
            "pants" => 12,
            "gears" => 19,
            "faces" => 18,
            "heads" => 17,
            "packages" => 32,
            "audio" => 3,
            "model" => 10
        ];

        if(!isset($sections[$section])) {
            return redirect('/catalog/hats');
        }

        $items = [];
        $pages_to_show = 10;
        $results_per_page = 16;

        if(isset($data['q'])) {
            $search = '%' . htmlspecialchars($data['search']) . '%';
            $results = User::whereRaw('LOWER(username) LIKE LOWER(?)', ["%{$search}%"])
                ->where('asset_type', $sections[$section])
                ->where('visibility', 'n')
                ->orderBy('lastlogin', 'desc')
                ->count();
        } else {
            $results = User::where('asset_type', $sections[$section])
                ->where('visibility', 'n')
                ->orderBy('lastlogin', 'desc')
                ->count();
        }

        $number_of_pages = ceil($results / $results_per_page);
        $currentPage = isset($data['page']) ? max(1, intval($data['page'])) : 1;
        $offset = ($currentPage - 1) * $results_per_page;
        $start_page = max(1, min($currentPage - floor($pages_to_show / 2), $number_of_pages - $pages_to_show + 1));
        $end_page = min($number_of_pages, $start_page + $pages_to_show - 1);

        if(isset($data['q'])) {
            $results = User::whereRaw('LOWER(username) LIKE LOWER(?)', ["%{$search}%"])
                ->where('asset_type', $sections[$section])
                ->where('visibility', 'n')
                ->orderBy('lastlogin', 'desc')
                ->offset($offset)
                ->limit($results_per_page)
                ->get()
                ->toArray();
        } else {
            $results = User::where('asset_type', $sections[$section])
                ->where('visibility', 'n')
                ->orderBy('lastlogin', 'desc')
                ->offset($offset)
                ->limit($results_per_page)
                ->get()
                ->toArray();
        }

        foreach($results as $result) {
            $result['additional'] = json_decode($result['additional'], true);
            $result['title'] = htmlspecialchars($result['title']);

            if($result['asset_type'] == 3) {
                $result['duration'] = $this->dataService->timestamp($row['additional']['duration']);
            }

            $user = User::where('id', $result['author']);
            $result['uuid'] = $user ? $user['id'] : false;
            $result['author'] = htmlspecialchars($user['username'] ?? $result['additional']['oldUser']);
            $items[] = $result;
        }

        $this->request['data']['items'] = [
            'data' => [],
            'pages' => [
                'info' => [
                    'current_page' => $currentPage,
                    'previous_page' => max(1, $currentPage - 1),
                    'next_page' => min($number_of_pages, $currentPage + 1),
                    'start_page' => $start_page,
                    'end_page' => $end_page,
                    'number_of_pages' => $number_of_pages
                ],
                'data' =>[]
            ]
        ];

        for ($page = $start_page; $page <= $end_page; $page++) {
            $this->request['data']['items']['pages']['data'][] = ['page' => $page];
        }

        if(!count($users)) {
            $this->request['data']['items']['pages']['data'][] = [
                'page' => 1
            ];
        }

        $this->request['data']['items']['data'] = $items;
        $this->request['data']['section'] = $section;
        $this->request['data']['search'] = isset($data['q']) ? $data['q'] : false;

        return view($this->request['data']['user']['version'] . '/Catalog/Index', $this->request);
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

            Auth::login($user, isset($data['remember']));
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
