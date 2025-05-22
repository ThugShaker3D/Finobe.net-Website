<?php

namespace App\Http\Controllers;

use App\Models\User;
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

        $this->request['data']['alerts']['announcements'] = $this->db->table('announcements')
            ->select('message', 'expire', 'author', 'color')
            ->where('expire', '>', now())
            ->orderBy('id', 'DESC')
            ->get()
            ->map(function ($item) {
                return (array) $item;
            })->toArray();
    }

    public function index(Request $request) {
        $this->request['data']['embeds']['title'] = 'Admin Panel' . $this->request['data']['embeds']['title'];

        if(!$this->request['data']['siteusername'] || $this->request['data']['user']['status'] != 'admin') {
            return redirect('/');
        }

        return view($this->request['data']['user']['version'] . '/Admin/Index', $this->request);
    }

    public function assets(Request $request) {
        $this->request['data']['embeds']['title'] = 'Asset Moderation' . $this->request['data']['embeds']['title'];

        if(!$this->request['data']['siteusername'] || $this->request['data']['user']['status'] != 'admin') {
            return redirect('/');
        }

        $assets = [];
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
            $assets[] = $result;
        }

        $this->request['data']['assets'] = $assets;

        return view($this->request['data']['user']['version'] . '/Admin/Assets', $this->request);
    }

    public function accept(Request $request) {
        $data = $request->all();

        if(!$this->request['data']['siteusername'] || $this->request['data']['user']['status'] != 'admin') {
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
        
        $this->db->table('purchases')->insert([
            'username' => User::where('id', $data['author'])->value('username'),
            'assetid' => $data['id'],
            'author' => $asset['author'],
            'amount' => 0
        ]);
        
        Session::put('success', 'Item accepted.');
        return redirect('/admin/assets');
    }

    public function deny(Request $request) {
        $data = $request->all();

        if(!$this->request['data']['siteusername'] || $this->request['data']['user']['status'] != 'admin') {
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

        if(!$this->request['data']['siteusername'] || $this->request['data']['user']['status'] != 'admin') {
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
                'time' => 'required|date_format:H:i'
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

    public function decider(Request $request) {
        $this->request['data']['embeds']['title'] = 'Decider' . $this->request['data']['embeds']['title'];

        if(!$this->request['data']['siteusername'] || $this->request['data']['user']['status'] != 'admin') {
            return redirect('/');
        }

        return view($this->request['data']['user']['version'] . '/Admin/Decider', $this->request);
    }

    public function prune_posts(Request $request) {
        $this->request['data']['embeds']['title'] = 'Prune Forum Posts' . $this->request['data']['embeds']['title'];
        $data = $request->all();

        if(!$this->request['data']['siteusername'] || $this->request['data']['user']['status'] != 'admin') {
            return redirect('/');
        }

        if($request->isMethod('post')) {
            $id = !empty($data['id']) && empty($data['replyid']) ? $data['id'] : $data['replyid'];
            $table = !empty($data['id']) && empty($data['replyid']) ? 'forum_threads' : 'forum_replies';

            if(!$this->db->table($table)->where('id', $id)->exists()) {
                Session::put('error', 'Forum post or reply doesn\'t exist.');
                return redirect('/admin/prune-posts');
            }

            if($table == 'forum_threads') {
                $results = $this->db->table('forum_replies')
                    ->select('id')
                    ->where('toid', $id)
                    ->get()
                    ->map(function ($item) {
                        return (array) $item;
                    })->toArray();
                
                foreach($results as $result) {
                    $this->db->table('forum_replies')
                        ->where('id', $result['id'])
                        ->delete();
                }

                $this->db->table('forum_threads')
                    ->where('id', $id)
                    ->delete();
            } else {
                $this->db->table('forum_replies')
                    ->where('id', $id)
                    ->delete();
            }

            Session::put('success', 'Successfully deleted.');
            return redirect('/admin/prune-posts');
        }

        return view($this->request['data']['user']['version'] . '/Admin/Prune_posts', $this->request);
    }

    public function announcements(Request $request) {
        $this->request['data']['embeds']['title'] = 'Announcements' . $this->request['data']['embeds']['title'];
        $data = $request->all();

        if(!$this->request['data']['siteusername'] || $this->request['data']['user']['status'] != 'admin') {
            return redirect('/');
        }

        if($request->isMethod('post')) {
            $validator = Validator::make($data, [
                'message' => 'required|string|min:3|max:255',
                'date' => 'required|date',
                'time' => 'required|date_format:H:i',
                'color' => 'required|string|in:success,primary,danger,info,warning'
            ]);

            if($validator->fails()) {
                Session::put('error', $validator->errors()->first());
                return redirect('/admin/announcements');
            }

            $expire = $data['date'] . ' ' . $data['time'];
            $timezone = new \DateTimeZone('America/Los_Angeles');
            $dateTime = new \DateTime($expire, $timezone);
            $expire = $dateTime->format('Y-m-d H:i:s');

            $this->db->table('announcements')->insert([
                'author' => $this->request['data']['user']['username'],
                'message' => $data['message'],
                'expire' => $expire,
                'color' => $data['color']
            ]);

            Session::put('success', 'Successfully created.');
            return redirect('/admin/announcements');
        }

        $html = [
            'time' => 'Time: ' . date('Y-m-d H:i:s'),
            'data' => ''
        ];

        $page = isset($data['page']) ? max(1, intval($data['page'])) : 1;
        $perPage = 10;

        $paginator = $this->db->table('announcements')
            ->orderByDesc('id')
            ->paginate($perPage, ['*'], 'page', $page);

        $results = $paginator->items();

        if (count($results)) {
            $html['data'] = "<table border='1' style=\"width:100%;\"><tr>";

            foreach (array_keys((array) $results[0]) as $key) {
                $html['data'] .= "<th>" . htmlspecialchars($key) . "</th>";
            }

            $html['data'] .= "</tr>";

            foreach ($results as $row) {
                $html['data'] .= "<tr>";
                foreach ((array) $row as $key => $value) {
                    if ($key === 'username') {
                        $html['data'] .= "<td><a href=\"/user/" . htmlspecialchars($value) . "\" target=\"_blank\">" . htmlspecialchars($value) . "</a></td>";
                    } else {
                        $html['data'] .= "<td>" . htmlspecialchars($value) . "</td>";
                    }
                }
                $html['data'] .= "</tr>";
            }

            $html['data'] .= "</table><div>";

            for ($pageNum = 1; $pageNum <= $paginator->lastPage(); $pageNum++) {
                $html['data'] .= "<a href='?page={$pageNum}'" . ($pageNum == $page ? " style='font-weight: bold'" : "") . ">{$pageNum}</a> ";
            }

            $html['data'] .= "</div>";
        } else {
            $html['data'] = "0 results";
        }

        $this->request['data']['announcements'] = $html;

        return view($this->request['data']['user']['version'] . '/Admin/Announcements', $this->request);
    }

    public function lock(Request $request) {
        $data = $request->all();

        if(!$this->request['data']['siteusername'] || $this->request['data']['user']['status'] != 'admin') {
            return redirect('/');
        }

        if(!isset($data['id'])) {
            return redirect('/forum/home');
        }

        if(!$this->db->table('forum_threads')->where('id', $data['id'])->exists()) {
            Session::put('error', 'This thread doesn\'t exist');
            return redirect('/forum/home');
        }

        $this->db->table('forum_threads')
            ->where('id', $data['id'])
            ->update([
                'locked' => 'y'
            ]);
        
        Session::put('success', 'Successfully locked');
        return redirect('/forum/post?id=' . $data['id']);
    }

    public function unlock(Request $request) {
        $data = $request->all();

        if(!$this->request['data']['siteusername'] || $this->request['data']['user']['status'] != 'admin') {
            return redirect('/');
        }

        if(!isset($data['id'])) {
            return redirect('/forum/home');
        }

        if(!$this->db->table('forum_threads')->where('id', $data['id'])->exists()) {
            Session::put('error', 'This thread doesn\'t exist');
            return redirect('/forum/home');
        }

        $this->db->table('forum_threads')
            ->where('id', $data['id'])
            ->update([
                'locked' => 'n'
            ]);
        
        Session::put('success', 'Successfully unlocked');
        return redirect('/forum/post?id=' . $data['id']);
    }

    public function pin(Request $request) {
        $data = $request->all();

        if(!$this->request['data']['siteusername'] || $this->request['data']['user']['status'] != 'admin') {
            return redirect('/');
        }

        if(!isset($data['id'])) {
            return redirect('/forum/home');
        }

        if(!$this->db->table('forum_threads')->where('id', $data['id'])->exists()) {
            Session::put('error', 'This thread doesn\'t exist');
            return redirect('/forum/home');
        }

        $this->db->table('forum_threads')
            ->where('id', $data['id'])
            ->update([
                'pinned' => 'y'
            ]);
        
        Session::put('success', 'Successfully pinned');
        return redirect('/forum/post?id=' . $data['id']);
    }

    public function unpin(Request $request) {
        $data = $request->all();

        if(!$this->request['data']['siteusername'] || $this->request['data']['user']['status'] != 'admin') {
            return redirect('/');
        }

        if(!isset($data['id'])) {
            return redirect('/forum/home');
        }

        if(!$this->db->table('forum_threads')->where('id', $data['id'])->exists()) {
            Session::put('error', 'This thread doesn\'t exist');
            return redirect('/forum/home');
        }

        $this->db->table('forum_threads')
            ->where('id', $data['id'])
            ->update([
                'pinned' => 'n'
            ]);
        
        Session::put('success', 'Successfully unpinned');
        return redirect('/forum/post?id=' . $data['id']);
    }

    public function stick(Request $request) {
        $data = $request->all();

        if(!$this->request['data']['siteusername'] || $this->request['data']['user']['status'] != 'admin') {
            return redirect('/');
        }

        if(!isset($data['id'])) {
            return redirect('/forum/home');
        }

        if(!$this->db->table('forum_replies')->where('id', $data['id'])->exists()) {
            Session::put('error', 'This thread doesn\'t exist');
            return redirect('/forum/home');
        }

        $reply = (array) $this->db->table('forum_replies')
            ->where('id', $data['id'])
            ->first();

        if($this->db->table('forum_replies')->where('toid', $reply['toid'])->where('sticked', 'y')->exists()) {
            Session::put('error', 'A sticked reply already exists');
            return redirect('/forum/home');
        }

        $this->db->table('forum_replies')
            ->where('id', $data['id'])
            ->update([
                'sticked' => 'y'
            ]);
        
        Session::put('success', 'Successfully sticked');
        return redirect('/forum/post?id=' . $reply['toid']);
    }

    public function unstick(Request $request) {
        $data = $request->all();

        if(!$this->request['data']['siteusername'] || $this->request['data']['user']['status'] != 'admin') {
            return redirect('/');
        }

        if(!isset($data['id'])) {
            return redirect('/forum/home');
        }

        if(!$this->db->table('forum_replies')->where('id', $data['id'])->exists()) {
            Session::put('error', 'This thread doesn\'t exist');
            return redirect('/forum/home');
        }

        $reply = (array) $this->db->table('forum_replies')
            ->where('id', $data['id'])
            ->first();

        $this->db->table('forum_replies')
            ->where('id', $data['id'])
            ->update([
                'sticked' => 'n'
            ]);
        
        Session::put('success', 'Successfully sticked');
        return redirect('/forum/post?id=' . $reply['toid']);
    }

    public function createxml(Request $request) {
        $this->request['data']['embeds']['title'] = 'Announcements' . $this->request['data']['embeds']['title'];
        $data = $request->all();

        if(!$this->request['data']['siteusername'] || $this->request['data']['user']['status'] != 'admin') {
            return redirect('/');
        }

        if($request->isMethod('post')) {
            $validator = Validator::make($data, [
                'title' => 'required|string|min:3|max:255',
                'description' => 'nullable|string|max:8192',
                'price' => 'required|integer|min:0',
                'onsale' => 'nullable|in:on,1,true,0,false,off',
                'mesh' => 'required|file',
                'xml' => 'required|file|mimetypes:text/plain',
                'texture' => 'required|file|mimetypes:image/png'
            ]);

            if($validator->fails()) {
                Session::put('error', $validator->errors()->first());
                return redirect('/admin/createxml');
            }

            $id = Asset::createHat(
                $data['title'],
                ['tmp_name' => $request->file('texture')->getPathname()],
                ['tmp_name' => $request->file('mesh')->getPathname()],
                ['tmp_name' => $request->file('xml')->getPathname()],
                $this->request['data']['user']['id'],
                $data['description'] ?? '',
                intval($data['price']),
                isset($data['onsale'])
            );

            Session::put('success', 'Success');
            return redirect('/item/' . $id);
        }

        return view($this->request['data']['user']['version'] . '/Admin/CreateXML', $this->request);
    }

    public function give_dius(Request $request) {
        $this->request['data']['embeds']['title'] = 'Reward Dius' . $this->request['data']['embeds']['title'];
        $data = $request->all();

        if(!$this->request['data']['siteusername'] || $this->request['data']['user']['status'] != 'admin') {
            return redirect('/');
        }

        if($request->isMethod('post')) {
            $validator = Validator::make($data, [
                'name' => 'required|string|max:255',
                'amount' => 'required|integer|min:0',
                'toggler' => 'nullable'
            ]);

            if($validator->fails()) {
                Session::put('error', $validator->errors()->fails());
                return redirect('/admin/give_dius');
            }

            if(isset($data['toggler'])) {
                if(!User::where('username', $data['name'])->exists()) {
                    Session::put('error', 'User does not exist');
                    return redirect('/admin/give_dius');
                }

                User::where('username', $data['name'])
                    ->update([
                        'diubanned' => DB::raw("CASE WHEN diubanned = 'n' THEN 'y' ELSE 'n' END")
                    ]);
                
                $user = User::where('username', $this->request['data']['user']['username'])->select('diubanned')->first();

                if($user->diubanned == 'y') {
                    Session::put('success', 'Successfully diu banned.');
                } else {
                    Session::put('success', 'Successfully diu unbanned.');
                }

                return redirect('/admin/give_dius');
            }

            if($data['name'] == '*') {
                $users = User::all();

                foreach($users as $user) {
                    $user->Dius += intval($data['Dius']);
                    $user->save();
                }
            } else {
                if(!User::where('username', $data['name'])->exists()) {
                    Session::put('error', 'User does not exist');
                    return redirect('/admin/give_dius');
                }

                $user = User::where('username', $data['name'])->first();
                $user->Dius += intval($data['amount']);
                $user->save();
            }

            Session::put('success', 'Successfully given dius.');
            return redirect('/admin/give_dius');
        }

        return view($this->request['data']['user']['version'] . '/Admin/Dius', $this->request);
    }

    public function warn(Request $request) {
        $this->request['data']['embeds']['title'] = 'Warn Users' . $this->request['data']['embeds']['title'];
        $data = $request->all();

        if(!$this->request['data']['siteusername'] || $this->request['data']['user']['status'] != 'admin') {
            return redirect('/');
        }

        if($request->isMethod('post')) {
            $validator = Validator::make($data, [
                'name' => 'required|string|min:3|max:255',
                'reason' => 'required|string|min:3|max:255'
            ]);

            if($validator->fails()) {
                Session::put('error', $validator->errors()->fails());
                return redirect('/admin/warn');
            }

            if(!User::where('username', $data['name'])->exists()) {
                Session::put('error', 'User does not exist');
                return redirect('/admin/warn');
            }

            $this->db->table('warning')->insert([
                'username' => $data['name'],
                'reason' => $data['reason'],
                'moderator' => $this->request['data']['user']['username']
            ]);

            Session::put('success', 'Successfully created.');
            return redirect('/admin/warn');
        }

        return view($this->request['data']['user']['version'] . '/Admin/Warn', $this->request);
    }

    public function give_badges(Request $request) {
        $this->request['data']['embeds']['title'] = 'Give Badges' . $this->request['data']['embeds']['title'];
        $data = $request->all();

        if(!$this->request['data']['siteusername'] || $this->request['data']['user']['status'] != 'admin') {
            return redirect('/');
        }

        if($request->isMethod('post')) {
            $validator = Validator::make($data, [
                'username' => 'required|string|min:3|max:255',
                'message' => 'required|string|min:3|max:255',
                'color' => 'required|string|in:success,primary,danger,info,warning'
            ]);

            if($validator->fails()) {
                Session::put('error', $validator->errors()->fails());
                return redirect('/admin/give_badges');
            }

            if(!User::where('username', $data['username'])->exists()) {
                Session::put('error', 'User does not exist');
                return redirect('/admin/give_badges');
            }

            $user = User::where('username', $data['username'])->first();
            $badges = json_decode($user->badges, true);
            $badges['data']['custom_badges'][] = [
                'message' => $data['message'],
                'color' => $data['color']
            ];

            $user->badges = json_encode($badges);
            $user->save();

            Session::put('success', 'Successfully created.');
            return redirect('/admin/give_badges');
        }

        return view($this->request['data']['user']['version'] . '/Admin/Badges', $this->request);
    }

    public function elections(Request $request) {
        $this->request['data']['embeds']['title'] = 'Elections' . $this->request['data']['embeds']['title'];
        $data = $request->all();

        if(!$this->request['data']['siteusername'] || $this->request['data']['user']['status'] != 'admin') {
            return redirect('/');
        }

        if($request->isMethod('post')) {
            $validator = Validator::make($data, [
                'title' => 'required|string|min:3|max:255',
                'css' => 'required|string|max:8192',
                'expire' => 'required|date',
                'time' => 'required|date',
                'options' => 'required|string'
            ]);

            if($validator->fails()) {
                Session::put('error', $validator->errors()->fails());
                return redirect('/admin/elections');
            }

            if($this->db->table('elections')->where('expire', '>', DB::raw('CURDATE()'))) {
                Session::put('error', 'There is an election already active');
                return redirect('/admin/elections');
            }

            $id = 1;
            $options = json_decode($data['options'], true);

            foreach($options as $key => $option) {
                $options[$key]['id'] = $id;
                $id++;
            }

            $timezone = new \DateTimeZone('America/Los_Angeles');
            $dateTime = new \DateTime($data['expire'] . ' ' . $data['time'], $timezone);
            $expire = $dateTime->format('Y-m-d H:i:s');

            $this->db->table('elections')->insert([
                'title' => $data['title'],
                'author' => $this->request['data']['user']['username'],
                'css' => $data['css'],
                'options' => json_encode($options),
                'expire' => $expire
            ]);

            Session::put('success', 'Successfully created.');
            return redirect('/admin/elections');
        }

        return view($this->request['data']['user']['version'] . '/Admin/Elections', $this->request);
    }

    public function servers(Request $request) {
        $data = $request->all();

        if($request->isMethod('post')) {
            $validator = Validator::make($data, [
                'type' => 'required|string'
            ]);

            if($validator->fails()) {
                return redirect('/admin/servers');
            }
        }

        $this->request['data']['servers'] = $this->db->table('servers')
            ->get()
            ->map(function ($item) {
                return (array) $item;
            })->toArray();

        return view($this->request['data']['user']['version'] . '/Admin/Servers', $this->request);
    }
}
