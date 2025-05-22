<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Validator;

class admin extends Controller
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
                'ads' => (bool)env('FINOBE_ADS'),
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

            $user = User::find($this->request['data']['user']['id'])->first();
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
        $this->request['data']['embeds']['title'] = 'Admin Panel' . $this->request['data']['embeds']['title'];

        if(!$this->request['data']['siteusername'] || $this->request['data']['user']['admin'] != 'admin') {
            return redirect('/');
        }

        return view($this->request['data']['user']['version'] . '/Admin/Index', $this->request);
    }

    public function assets(Request $request) {
        $this->request['data']['embeds']['title'] = 'Asset Moderation' . $this->request['data']['embeds']['title'];

        if(!$this->request['data']['siteusername'] || $this->request['data']['user']['admin'] != 'admin') {
            return redirect('/');
        }

        $items = [];
        $results = $this->db->table('assets')
            ->where('visibility', 'r')
            ->where('asset_type', '!=', 1)
            ->orderBy('id', 'DESC')
            ->get()
            ->map(function ($item) {
                return (array) $item;
            })->toArray();
        
        foreach($results as $result) {
            $result['title'] = strip_tags(htmlspecialchars($result['title']));
            $result['author'] = htmlspecialchars(User::where('id', $result['author'])->value('username'));
            $result['publish'] = date('m/d/Y', strtotime($result['created']));
            $result['additional'] = json_decode($result['additional'], true);
            $items[] = $result;
        }

        return view($this->request['data']['user']['version'] . '/Admin/Assets', $this->request);
    }

    public function accept(Request $request) {
        $data = $request->all();

        if(!$this->request['data']['siteusername'] || $this->request['data']['user']['admin'] != 'admin') {
            return redirect('/');
        }

        if(!isset($data['id']) || !$this->db->table('assets')->where('id', $data['id'])->exists()) {
            Session::put('error', 'error');
            return redirect('/admin/assets');
        }

        $asset = (array) $this->db->table('assets')
            ->where('id', $data['id'])
            ->first();
        
        $asset['additional'] = json_decode($asset['additional'], true);

        if($asset['asset_type'] == 3) {
            if(!rename(public_path('dynamic/reviewing/' . $asset['file']), '/var/www/cdn.finobe.net/audios/' . $asset['file'])) {
                Session::put('error', error_get_last()['message']);
                return redirect('/admin/assets');
            }
        } elseif($asset['asset_type'] == 11 || $asset['asset_type'] == 12 || $asset['asset_type'] == 18) {
            $this->db->table('assets')
                ->where('id', $asset['additional']['media']['textureAssetId'])
                ->update([
                    'visibility' => 'n'
                ]);
        }

        $this->db->table('assets')
            ->where('id', $data['id'])
            ->update([
                'visibility' => 'n'
            ]);
        
        Session::put('success', 'Item accepted.');
        return redirect('/admin/assets');
    }

    public function deny(Request $request) {
        $data = $request->all();

        if(!$this->request['data']['siteusername'] || $this->request['data']['user']['admin'] != 'admin') {
            return redirect('/');
        }

        if(!isset($data['id']) || !$this->db->table('assets')->where('id', $data['id'])->exists()) {
            Session::put('error', 'error');
            return redirect('/admin/assets');
        }

        $asset = (array) $this->db->table('assets')
            ->where('id', $data['id'])
            ->first();
        
        $asset['additional'] = json_decode($asset['additional'], true);

        if($asset['asset_type'] == 3) {
            if(!rename(public_path('dynamic/reviewing/' . $asset['file']), public_path('dynamic/denied/' . $asset['file']))) {
                Session::put('error', error_get_last()['message']);
                return redirect('/admin/assets');
            }
        } elseif($asset['asset_type'] == 11 || $asset['asset_type'] == 12 || $asset['asset_type'] == 18) {
            if(!rename('/var/www/cdn.finobe.net/assets/' . $asset['file'], public_path('dynamic/denied/' . $asset['file']))) {
                Session::put('error', error_get_last()['message']);
                return redirect('/admin/assets');
            }

            $this->db->table('assets')
                ->where('id', $asset['additional']['media']['textureAssetId'])
                ->update([
                    'visibility' => 'd'
                ]);
        }

        $this->db->table('assets')
            ->where('id', $data['id'])
            ->update([
                'visibility' => 'd'
            ]);
        
        Session::put('success', 'Item denied.');
        return redirect('/admin/assets');
    }

    public function bans(Request $request) {
        $this->request['data']['embeds']['title'] = 'User Moderation' . $this->request['data']['embeds']['title'];
        $data = $request->all();

        if(!$this->request['data']['siteusername'] || $this->request['data']['user']['admin'] != 'admin') {
            return redirect('/');
        }

        if($request->isMethod('post')) {
            $validator = Validator::make($data, [
                'name' => [
                    'required',
                    function ($attribute, $value, $fail) {
                        $isIp = filter_var($value, FILTER_VALIDATE_IP);
                        $isUsername = is_string($value) && strlen($value) >= 3 && strlen($value) <= 255;

                        if (!$isIp && !$isUsername) {
                            $fail('The ' . $attribute . ' must be a valid IP address or a username between 3 and 255 characters.');
                        }
                    }
                ],
                'reason' => 'required|string|min:3|max:255',
                'type' => 'required|string|in:n,y|size:1',
                'date' => 'required|date',
                'time' => 'required|time'
            ]);

            if($validator->fails()) {
                Session::put('error', $validator->errors()->first());
                return redirect('/admin/bans');
            }

            if(!filter_var($data['name'], FILTER_VALIDATE_IP) && !User::where('username', $data['name'])->exists()) {
                Session::put('error', 'User doesn\' exist.');
                return redirect('/admin/bans');
            }

            if(filter_var($data['name'], FILTER_VALIDATE_IP)) {
                $data['name'] = hash_hmac('sha256', $data['name'], 'ip');
            }

            if($this->db->table('bans')
                ->where('username', $request->name)
                ->where(function ($query) {
                    $query->where('expire', '>', now())
                        ->orWhere('perm', 'y');
                })->exists()) {
                Session::put('error', 'This user already has an active ban');
                return redirect('/admin/bans');
            }

            if($data['type'] == 'n') {
                $expire = $data['date'] . ' ' . $data['time'];
                $timezone = new \DateTimeZone('America/Los_Angeles');
                $dateTime = new \DateTime($expire, $timezone);
                $expire = $dateTime->format('Y-m-d H:i:s');

                $this->db->table('bans')->insert([
                    'username' => $data['name'],
                    'reason' => $data['reason'],
                    'expire' => $expire,
                    'moderator' => $this->request['data']['user']['username'],
                    'perm' => 'n'
                ]);
            } else {
                $this->db->table('bans')->insert([
                    'username' => $data['name'],
                    'reason' => $data['reason'],
                    'moderator' => $this->request['data']['user']['username'],
                    'perm' => 'y'
                ]);
            }

            Session::put('success', 'Successfully created.');
            return redirect('/admin/bans');
        }

        return view($this->request['data']['user']['version'] . '/Admin/Ban', $this->request);
    }
}
