<?php

namespace App\Http\Controllers;

use FFMpeg\FFMpeg;
use FFMpeg\Format\Audio\Mp3;
use FFMpeg\Format\Video\X264;
use FFMpeg\Coordinate\TimeCode;
use Carbon\Carbon;
use App\Mail\DynamicContentEmail;
use App\Models\User;
use App\Jobs\ProcessVideo;
use App\Http\Controllers\dataController as DataController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Process\Exception\ProcessFailedException;
use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\MarkdownConverter;
use League\CommonMark\Extension\DisallowedRawHtml\DisallowedRawHtmlExtension;
use League\CommonMark\Renderer\Block\ParagraphRenderer;
use League\CommonMark\Node\Block\Paragraph;
use League\CommonMark\Util\HtmlElement;
use Parsedown;

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
                'lucky_number' => rand(0, Cache::remember('user_count', 3600, fn() => User::count())) . '/' . Cache::remember('user_count', 3600, fn() => User::count()),
                'string_replacements' => [
                    'phrasesToReplace' => [
                        'fuck',
                        'fucking',
                        'roblox',
                        'rob lox',
                        'robux',
                        'ass',
                        'asshole',
                        'shit',
                        'r*blox'
                    ],
                    'replacements' => [
                        'OBAMA BALL',
                        'sonic 06',
                        'blockland.us'
                    ]
                ]
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
            $this->request['data']['user'] = Auth::user();

            if(!$this->request['data']['user']) {
                Auth::logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                return redirect('/');
            }

            $this->request['data']['user'] = $this->request['data']['user']->toArray();
            $this->request['data']['user']['formattedDius'] = DataController::formatNumber($this->request['data']['user']['Dius']);
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
            
            $this->request['data']['user']['places'] = $this->db->table('assets')
                ->where('author', $this->request['data']['user']['id'])
                ->where('asset_type', 9)
                ->count();
            
            $this->request['data']['notifications'] = [
                'data' => [],
                'ads' => (bool)env('FINOBE_ADS'),
                'info' => [
                    'number' => $this->db->table('pms')->where('touser', $this->request['data']['user']['id'])->where('readed', 'n')->count(),
                    'inbox' => $this->db->table('messages')->where('touser', $this->request['data']['user']['id'])->where('readed', 'n')->count(),
                    'incomingFriends' => 0
                ]
            ];

            $notifications = $this->db->table('pms')
                ->where('touser', $this->request['data']['user']['id'])
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
            
            if(strtotime($this->request['data']['user']['lastdiu']) <= time() && $this->request['data']['user']['diubanned'] == 'n') {
                /*
                $this->db->table('users')
                    ->where('username', $this->request['data']['user']['username'])
                    ->update([
                        'Dius' => $this->request['data']['user']['Dius'] + 25,
                        'lastdiu' => DB::raw('DATE_ADD(CURRENT_TIMESTAMP(), INTERVAL 1 DAY)')
                    ]);
                */
                
                $user = Auth::user();
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

    public function getData() {
        return $this->request;
    }

    public function place_new(Request $request) {
        $this->request['data']['embeds']['title'] = 'New Place' . $this->request['data']['embeds']['title'];
        $data = $request->all();

        if(!$this->request['data']['siteusername']) {
            return redirect('/');
        }

        if($request->isMethod('post')) {
            $validator = Validator::make($data, [
                'title' => 'required|string|min:3|max:255',
                'description' => 'nullable|string|max:8192'
            ]);

            $games = $this->db->table('assets')
                ->where('author', $this->request['data']['user']['id'])
                ->where('asset_type', 9)
                ->count();
            
            if(!(bool)env('FINOBE_CREATE_PLACES')) {
                Session::put('error', 'Creating assets is currently disabled');
                return redirect('/app/place/new');
            }

            if($games >= $this->request['data']['user']['slots']) {
                Session::put('error', 'You have used all of your place slots');
                return redirect('/app/place/new');
            }

            if($this->request['data']['user']['status'] != 'admin') {
                Session::put('error', 'Admin status is required');
                return redirect('/app/place/new');
            }
            /*
            $id = $this->db->table('assets')->insertGetId([
                'asset_type' => 9,
                'title' => trim($data['title']),
                'description' => trim($data['description'] ?? ''),
                'additional' => json_encode([
                    'visits' => 0,
                    'version' => '2012',
                    'maxplayers' => 15,
                    'category' => 'original',
                    'featured' => false,
                    'gears' => [
                        'combat' => true,
                        'social' => true,
                        'building' => true,
                        'musical' => true
                    ],
                    'uncopylocked' => false,
                    'allowplaying' => true,
                    'chat_type' => 'classic',
                    'media' => [
                        'imageAssetId' => 1
                    ],
                    'hidden' => false
                ])
            ]);
            */
            $defaultPlace = file_get_contents("/var/www/cdn.finobe.net/default.rbxl");
            $id = Asset::createAsset(trim($data['title']), 9, $this->request['data']['user']['id'], $defaultPlace, trim($data['description'] ?? ''), "n", [
                    'visits' => 0,
                    'version' => '2012',
                    'maxplayers' => 15,
                    'category' => 'original',
                    'featured' => false,
                    'gears' => [
                        'combat' => true,
                        'social' => true,
                        'building' => true,
                        'musical' => true
                    ],
                    'uncopylocked' => false,
                    'allowplaying' => true,
                    'chat_type' => 'classic',
                    'media' => [
                        'imageAssetId' => 1
                    ],
                    'hidden' => false
            ]);
            return redirect('/place/' . $id);
        }

        return view($this->request['data']['user']['version'] . '/Places/New', $this->request);
    }

    public function catalog_new(Request $request) {
        $this->request['data']['embeds']['title'] = 'New Asset' . $this->request['data']['embeds']['title'];
        $data = $request->all();

        if(!$this->request['data']['siteusername']) {
            return redirect('/');
        }
        
        if($request->isMethod('post')) {
            if(!(bool)env('FINOBE_CREATE_ASSETS')) {
                Session::put('Creating assets is currently disabled');
                return redirect('/catalog/new');
            }

            $assetTypes = [
                'hats' => 8,
                't-shirts' => 2,
                'shirt' => 11,
                'pants' => 12,
                'gears' => 19,
                'faces' => 18,
                'heads' => 17,
                'packages' => 32,
                'audio' => 3,
                'model' => 10
            ];

            $validator = Validator::make($data, [
                'title' => 'required|string|min:3|max:255',
                'description' => 'nullable|string|max:8192',
                'media-type' => 'required|string',
                'price' => 'required|integer|min:0'
            ]);

            if($validator->fails()) {
                Session::put('error', $validator->errors()->first());
                return redirect('/catalog/new');
            }

            if($this->request['data']['user']['Dius'] - 5 < 0) {
                Session::put('error', 'Not enough dius');
                return redirect('/catalog/new');
            }

            if($data['media-type'] == 'video') {
                if($this->request['data']['user']['status'] != 'admin') {
                    return redirect('/catalog/new');
                }

                $validator = Validator::make($data, [
                    'file' => 'required|file|mimetypes:video/mp4,video/x-msvideo,video/x-ms-wmv,video/x-ms-asf,video/quicktime,video/webm|max:102400'
                ]);

                if($validator->fails()) {
                    Session::put('error', $validator->errors()->first());
                    return redirect('/catalog/new');
                }

                $filename = uniqid();
                $thumbnail = $filename . '.jpg';
                $filename .= '.webm';

                $file = $request->file('file')->store('videos');

                /*
                $ffmpeg = FFmpeg::create();
                $video = $ffmpeg->open(storage_path('app/private/' . $file));
                $format = new X264('aac', 'libx264');
                //$format->setAdditionalParameters(['-movflags', '+faststart']);
                $video->save($format, '/var/www/cdn.finobe.net/videos/data/' . $filename);
                $video->frame(TimeCode::fromSeconds(1))
                    ->save('/var/www/cdn.finobe.net/videos/thumbs/' . $thumbnail);
                */

                Redis::set("video_processing:{$filename}", true);
                ProcessVideo::dispatch($file, $filename, $thumbnail);

                /*
                if(empty(trim(shell_exec("ps aux | grep 'php artisan queue:work' | grep -v grep")))) {
                    exec('cd /var/www/Finobe && php artisan queue:work --timeout=21600 --sleep=3 --tries=3 > /dev/null 2>&1 &');
                }
                */
                
                $id = $this->db->table('videos')->insertGetId([
                    'title' => $data['title'],
                    'author' => $this->request['data']['user']['username'],
                    'filename' => $filename,
                    'thumbnail' => $thumbnail,
                    'description' => $data['description'] ?? ''
                ]);

                $user = User::find($this->request['data']['user']['id']);
                $user->Dius -= 5;
                $user->save();

                $this->db->table('purchases')->insert([
                    'username' => $user->username,
                    'assetid' => 0,
                    'author' => 0,
                    'amount' => -5,
                    'type' => 5
                ]);

                Session::put('success', 'Video is processing, the video will automatically publish when finished processing.');
                return redirect('/videos');
            } elseif($data['media-type'] == 'audio') {
                $validator = Validator::make($data, [
                    'file' => 'required|file|mimetypes:audio/mpeg,audio/ogg,audio/midi,audio/x-midi,audio/wav,audio/x-wav|max:10240'
                ]);

                if($validator->fails()) {
                    Session::put('error', $validator->errors()->first());
                    return redirect('/catalog/new');
                }

                if($this->request['data']['user']['Dius'] - 5 < 0) {
                    Session::put('error', 'Not enough dius');
                    return redirect('/catalog/new');
                }

                $filename = uniqid();
                $ffmpeg = FFmpeg::create();

                try {
                    $file = $request->file('file');
                    if(in_array($file->getMimeType(), ['audio/midi', 'audio/x-midi'])) {
                        $file->move(public_path('dynamic/temp/'), $filename);
                        $input = public_path('dynamic/temp/' . $filename);
                        $output = public_path('dynamic/temp/' . $filename . '.mp3');
                        exec("timidity $input -Ow -o - | ffmpeg -i - -codec:a libmp3lame -b:a 96k $output");
                        $audio = $ffmpeg->open($output);
                        $duration = $audio->getFormat()->get('duration');
                    } else {
                        $file->move(public_path('dynamic/temp/'), $filename);
                        $audio = $ffmpeg->open(public_path('dynamic/temp/' . $filename));
                        $duration = $audio->getFormat()->get('duration');
                        $format = new Mp3();
                        $format->setAudioKiloBitrate(96);
                        $audio->save($format, public_path('dynamic/temp/' . $filename . '.mp3'));
                    }
                    
                    rename(public_path('dynamic/temp/' . $filename . '.mp3'), public_path('dynamic/reviewing/' . $filename)); //ffmpeg is fucking me in the ass without the .mp3 extention
                } catch(ProcessFailedException $e) {
                    Session::put('error', $e->getProcess()->getErrorOutput());
                    return redirect('/catalog/new');
                }

                $id = $this->db->table('assets')->insertGetId([
                    'asset_type' => $assetTypes[$data['media-type']],
                    'title' => $data['title'],
                    'author' => $this->request['data']['user']['id'],
                    'file' => $filename,
                    'description' => $data['description'] ?? '',
                    'visibility' => 'r',
                    'additional' => json_encode([
                        'duration' => $duration,
                        'price' => intval($data['price']),
                        'media' => [
                            'imageAssetId' => 2
                        ],
                        'oldUser' => ''
                    ])
                ]);

                $user = User::find($this->request['data']['user']['id']);
                $user->Dius -= 5;
                $user->save();

                $this->db->table('purchases')->insert([
                    'username' => $user->username,
                    'assetid' => 0,
                    'author' => 0,
                    'amount' => -5,
                    'type' => 5
                ]);

                DataController::send_discord_message('<@541523977475194880>, ' . $this->request['data']['user']['username'] . ' uploaded an item, moderate it! [ https://finobe.net/admin/assets ]');

                return redirect('/item/' . $id);
            } elseif($data['media-type'] == 'shirt') {
                $validator = Validator::make($data, [
                    'file' => 'required|file|mimetypes:image/png,image/jpeg|max:10240'
                ]);

                if($validator->fails()) {
                    Session::put('error', $validator->errors()->first());
                    return redirect('/catalog/new');
                }

                if(intval($data['price']) < 5) {
                    Session::put('error', 'Price must be at least 5 Diu');
                    return redirect('/catalog/new');
                }

                if($this->request['data']['user']['Dius'] - 5 < 0) {
                    Session::put('error', 'Not enough dius');
                    return redirect('/catalog/new');
                }

                $image = getimagesize($request->file('file')->getPathname());

                if(abs(($image[0] / $image[1]) - (585 / 559)) > 0.01) {
                    Session::put('error', 'Image is not correct ratio (585x559)');
                    return redirect('/catalog/new');
                }

                $user = User::find($this->request['data']['user']['id']);

                try {
                    $id = Asset::createAccessory(
                        $data['title'],
                        ['tmp_name' => $request->file('file')->getPathname()],
                        $user->id,
                        $data['description'] ?? '',
                        intval($data['price']),
                        true,
                        false,
                        'shirt'
                    );
                } catch(\Exception $e) {
                    Session::put('error', $e->getMessage());
                    return redirect('/catalog/new');
                }

                $user->Dius -= 5;
                $user->save();

                $this->db->table('purchases')->insert([
                    'username' => $user->username,
                    'assetid' => 0,
                    'author' => 0,
                    'amount' => -5,
                    'type' => 5
                ]);

                DataController::send_discord_message('<@541523977475194880>, ' . $this->request['data']['user']['username'] . ' uploaded an item, moderate it! [ https://finobe.net/admin/assets ]');

                Session::put('success', 'Success');
                return redirect('/item/' . $id);
            } elseif($data['media-type'] == 'pants') {
                $validator = Validator::make($data, [
                    'file' => 'required|file|mimetypes:image/png,image/jpeg|max:10240'
                ]);

                if($validator->fails()) {
                    Session::put('error', $validator->errors()->first());
                    return redirect('/catalog/new');
                }

                if(intval($data['price']) < 5) {
                    Session::put('error', 'Price must be at least 5 Diu');
                    return redirect('/catalog/new');
                }

                if($this->request['data']['user']['Dius'] - 5 < 0) {
                    Session::put('error', 'Not enough dius');
                    return redirect('/catalog/new');
                }

                $image = getimagesize($request->file('file')->getPathname());

                if(abs(($image[0] / $image[1]) - (585 / 559)) > 0.01) {
                    Session::put('error', 'Image is not correct ratio (585x559)');
                    return redirect('/catalog/new');
                }

                $user = User::find($this->request['data']['user']['id']);

                try {
                    $id = Asset::createAccessory(
                        $data['title'],
                        ['tmp_name' => $request->file('file')->getPathname()],
                        $user->id,
                        $data['description'] ?? '',
                        intval($data['price']),
                        true,
                        false,
                        'pants'
                    );
                } catch(\Exception $e) {
                    Session::put('error', $e->getMessage());
                    return redirect('/catalog/new');
                }

                $user->Dius -= 5;
                $user->save();

                $this->db->table('purchases')->insert([
                    'username' => $user->username,
                    'assetid' => 0,
                    'author' => 0,
                    'amount' => -5,
                    'type' => 5
                ]);

                DataController::send_discord_message('<@541523977475194880>, ' . $this->request['data']['user']['username'] . ' uploaded an item, moderate it! [ https://finobe.net/admin/assets ]');

                Session::put('success', 'Success');
                return redirect('/item/' . $id);
            } elseif($data['media-type'] == 'faces') {
                if($this->request['data']['user']['status'] != 'admin') {
                    Session::put('error', 'Admin required');
                    return redirect('/catalog/new');
                }

                $validator = Validator::make($data, [
                    'file' => 'required|file|mimetypes:image/png,image/jpeg|max:10240'
                ]);

                if($validator->fails()) {
                    Session::put('error', $validator->errors()->first());
                    return redirect('/catalog/new');
                }

                if($this->request['data']['user']['Dius'] - 5 < 0) {
                    Session::put('error', 'Not enough dius');
                    return redirect('/catalog/new');
                }

                $image = getimagesize($request->file('file')->getPathname());

                if(abs(($image[0] / $image[1]) - (256 / 256)) > 0.01) {
                    Session::put('error', 'Image is not correct ratio (256x256)');
                    return redirect('/catalog/new');
                }

                $user = User::find($this->request['data']['user']['id']);

                try {
                    $id = Asset::createAccessory(
                        $data['title'],
                        ['tmp_name' => $request->file('file')->getPathname()],
                        $user->id,
                        $data['description'] ?? '',
                        intval($data['price']),
                        true,
                        false,
                        'face'
                    );
                } catch(\Exception $e) {
                    Session::put('error', $e->getMessage());
                    return redirect('/catalog/new');
                }

                $user->Dius -= 5;
                $user->save();

                $this->db->table('purchases')->insert([
                    'username' => $user->username,
                    'assetid' => 0,
                    'author' => 0,
                    'amount' => -5,
                    'type' => 5
                ]);

                DataController::send_discord_message('<@541523977475194880>, ' . $this->request['data']['user']['username'] . ' uploaded an item, moderate it! [ https://finobe.net/admin/assets ]');

                Session::put('success', 'Success');
                return redirect('/item/' . $id);
            } elseif($data['media-type'] == 't-shirts') {
                $validator = Validator::make($data, [
                    'file' => 'required|file|mimetypes:image/png,image/jpeg|max:10240'
                ]);

                if($validator->fails()) {
                    Session::put('error', $validator->errors()->first());
                    return redirect('/catalog/new');
                }

                if(intval($data['price']) < 2) {
                    Session::put('error', 'Price must be at least 2 Diu');
                    return redirect('/catalog/new');
                }

                if($this->request['data']['user']['Dius'] - 5 < 0) {
                    Session::put('error', 'Not enough dius');
                    return redirect('/catalog/new');
                }

                $user = User::find($this->request['data']['user']['id']);

                try {
                    $id = Asset::createAccessory(
                        $data['title'],
                        ['tmp_name' => $request->file('file')->getPathname()],
                        $user->id,
                        $data['description'] ?? '',
                        intval($data['price']),
                        true,
                        false,
                        'tshirt'
                    );
                } catch(\Exception $e) {
                    Session::put('error', $e->getMessage());
                    return redirect('/catalog/new');
                }

                $user->Dius -= 5;
                $user->save();

                $this->db->table('purchases')->insert([
                    'username' => $user->username,
                    'assetid' => 0,
                    'author' => 0,
                    'amount' => -5,
                    'type' => 5
                ]);

                DataController::send_discord_message('<@541523977475194880>, ' . $this->request['data']['user']['username'] . ' uploaded an item, moderate it! [ https://finobe.net/admin/assets ]');

                Session::put('success', 'Success');
                return redirect('/item/' . $id);
            }

            return redirect('/catalog/new');
        }

        return view($this->request['data']['user']['version'] . '/Catalog/New', $this->request);
    }

    public function app_settings(Request $request) {
        $this->request['data']['embeds']['title'] = 'Settings' . $this->request['data']['embeds']['title'];
        $data = $request->all();

        if(!$this->request['data']['siteusername']) {
            return redirect('/');
        }

        if($request->isMethod('post')) {
            if(isset($data['blurb']) && !$request->hasFile('file')) {
                $validator = Validator::make($data, [
                    'blurb' => 'required|string|max:8192'
                ]);

                if($validator->fails()) {
                    Session::put('error', $validator->errors()->first());
                    return redirect('/app/settings');
                }

                $user = User::find($this->request['data']['user']['id']);
                $user->blurb = $data['blurb'];
                $user->save();

                Session::put('success', 'Successfully updated.');
                return redirect('/app/settings');
            } elseif(isset($data['password']) && !$request->hasFile('file')) {
                $validator = Validator::make($data, [
                    'email' => 'required|email',
                    'password' => 'required|string|alpha_dash|unique:finobe.users,email'
                ]);

                if($validator->fails()) {
                    Session::put('error', $validator->errors()->first());
                    return redirect('/app/settings');
                }

                if(!Hash::check($data['password'], $this->request['data']['user']['password'])) {
                    Session::put('errorlogin', true);
                    return redirect('/auth/login');
                }

                $user = Auth::user();
                $user->email = $data['email'];
                $user->verified = 'n';
                $user->save();

                return redirect('/app/settings');
            } elseif($this->request['data']['user']['status'] == 'admin' && $request->hasFile('file')) {
                $validator = Validator::make($data, [
                    'file' => 'required|file|minetypes:image/png,image/jpg|max:10240'
                ]);

                if($validator->fails()) {
                    Session::put('error', $validator->errors()->first());
                    return redirect('/app/settings');
                }

                $file = $request->file('file');
                list($width, $height) = getimagesize($file->getPathname());
                $filename = uniqid() . '.' . $file->extension();

                if($width != $height) {
                    Session::put('error', 'Image needs to be 1:1 ratio');
                    return redirect('/app/settings');
                }

                $file->move('/var/www/cdn.finobe.net/avatar/', $filename);

                $user = User::find($this->request['data']['user']['id']);
                $user->pfp = $filename;
                $user->save();

                Session::put('success', 'Successfully updated.');
                return redirect('/app/settings');
            }

            return redirect('/app/settings');
        }

        return view($this->request['data']['user']['version'] . '/Settings/Index', $this->request);
    }

    public function app_theme(Request $request) {
        $this->request['data']['embeds']['title'] = 'Theme' . $this->request['data']['embeds']['title'];
        $data = $request->all();

        if(!$this->request['data']['siteusername']) {
            return redirect('/');
        }

        if($request->isMethod('post')) {
            if(isset($data['branding'])) {
                $validator = Validator::make($data, [
                    'branding' => 'required|string|in:aesthetiful,finobe'
                ]);

                if($validator->fails()) {
                    Session::put('error', $validator->errors()->first());
                    return redirect('/app/theme');
                }

                $user = User::find($this->request['data']['user']['id']);
                $user->branding = $data['branding'];
                $user->save();

                Session::put('successv2', 'Successfully updated.');
                return redirect('/app/' . ($user->version == 'v1' ? 'settings' : 'theme'));
            } elseif(isset($data['logo'])) {
                $validator = Validator::make($data, [
                    'logo' => 'required|string|in:v1,v2,v3'
                ]);

                if($validator->fails()) {
                    Session::put('error', $validator->errors()->first());
                    return redirect('/app/theme');
                }

                $user = User::find($this->request['data']['user']['id']);
                $user->logo = $data['logo'];
                $user->save();

                Session::put('successv2', 'Successfully updated.');
                return redirect('/app/' . ($user->version == 'v1' ? 'settings' : 'theme'));
            } elseif(isset($data['dark'])) {
                User::where('id', $this->request['data']['user']['id'])
                    ->update([
                        'theme' => DB::raw('CASE WHEN theme = 0 THEN 1 ELSE 0 END')
                    ]);
                
                Session::put('successv2', 'Successfully updated.');
                return redirect('/app/' . ($this->request['data']['user']['version'] == 'v1' ? 'settings' : 'theme'));
            } elseif(isset($data['gary'])) {
                User::where('id', $this->request['data']['user']['id'])
                    ->update([
                        'gary' => DB::raw('CASE WHEN gary = 0 THEN 1 ELSE 0 END')
                    ]);
                
                Session::put('successv2', 'Successfully updated.');
                return redirect('/app/' . ($this->request['data']['user']['version'] == 'v1' ? 'settings' : 'theme'));
            } elseif(isset($data['upsidedown'])) {
                User::where('id', $this->request['data']['user']['id'])
                    ->update([
                        'upsidedown' => DB::raw('CASE WHEN upsidedown = 0 THEN 1 ELSE 0 END')
                    ]);
                
                Session::put('successv2', 'Successfully updated.');
                return redirect('/app/' . ($this->request['data']['user']['version'] == 'v1' ? 'settings' : 'theme'));
            } elseif(isset($data['version'])) {
                $validator = Validator::make($data, [
                    'version' => 'required|string|in:v1,v2'
                ]);

                if($validator->fails()) {
                    Session::put('error', $validator->errors()->first());
                    return redirect('/app/theme');
                }

                User::where('id', $this->request['data']['user']['id'])
                    ->update([
                        'gary' => 0,
                        'upsidedown' => 0,
                        'theme' => 0,
                        'logo' => 'v1',
                        'version' => $data['version']
                    ]);
                
                Session::put('success', 'Successfully changed');
                return redirect('/app/' . ($this->request['data']['user']['version'] == 'v1' ? 'settings' : 'theme'));
            }

            return redirect('/app/theme');
        }

        return view($this->request['data']['user']['version'] . '/Settings/Theme', $this->request);
    }

    public function app_games(Request $request) {
        $this->request['data']['embeds']['title'] = 'Places' . $this->request['data']['embeds']['title'];
        $data = $request->all();

        if(!$this->request['data']['siteusername']) {
            return redirect('/');
        }

        if($request->isMethod('post')) {
            if($this->request['data']['user']['Dius'] < 625) {
                Session::put('error', 'You do not have enough Dius to purchase a place slot');
                return redirect('/app/games');
            }

            $user = User::find($this->request['data']['user']['id']);
            $user->Dius -= 625;
            $user->slots += 1;
            $user->save();

            $this->db->table('purchases')->insert([
                'username' => $user->username,
                'assetid' => 0,
                'author' => 1,
                'amount' => -625,
                'type' => 3
            ]);

            Session::put('successv2', 'Successfully purchased.');
            return redirect('/app/games');
        }

        return view($this->request['data']['user']['version'] . '/Settings/Places', $this->request);
    }

    public function app_connect(Request $request) {
        $this->request['data']['embeds']['title'] = 'Connect' . $this->request['data']['embeds']['title'];
        $data = $request->all();

        if(!$this->request['data']['siteusername']) {
            return redirect('/');
        }

        if($request->isMethod('post')) {
            $validator = Validator::make($data, [
                'username' => 'required|string'
            ]);

            if($validator->fails()) {
                Session::put('error', $validator->errors()->first());
                return redirect('/app/connect');
            }

            $user = User::find($this->request['data']['user']['id']);
            $user->eracast_link = 'None';
            $user->save();

            Http::withHeaders([
                'User-Agent' => 'finobe.net/Server 1.0'
            ])->get('https://api.eracast.cc/v1/update_aesthetifulplus_link', [
                'user' => $data['username'],
                'userid' => 'None'
            ]);

            Session::put('successv2', 'Successfully disconnected.');
            return redirect('/app/connect');
        }

        if($this->request['data']['user']['eracast_link'] != 'None') {
            $response = Http::withHeaders([
                'User-Agent' => 'finobe.net/Server 1.0'
            ])->get('https://api.eracast.cc/v1/get_user_username', [
                'userid' => $this->request['data']['user']['eracast_link']
            ]);

            $this->request['data']['user']['eracast_username'] = $response->body();
        }

        return view($this->request['data']['user']['version'] . '/Settings/Connect', $this->request);
    }

    public function api_connect(Request $request) {
        $this->request['data']['embeds']['title'] = 'Connecting eracast' . $this->request['data']['embeds']['title'];
        $data = $request->all();

        if($request->isMethod('post')) {
            $validator = Validator::make($data, [
                'userid' => 'required|integer',
                'username' => 'required|string'
            ]);

            if($validator->fails()) {
                Session::put('error', $validator->errors()->first());
                return redirect('/app/connect');
            }

            Http::withHeaders([
                'User-Agent' => 'finobe.net/Server 1.0'
            ])->get('https://api.eracast.cc/v1/update_aesthetifulplus_link', [
                'eracast_fiur3ui3uigu3itjuirjifs',
                'user' => $data['username'],
                'userid' => $this->request['data']['user']['id']
            ]);

            $user = User::find($this->request['data']['user']['id']);
            $user->eracast_link = $data['userid'];
            $user->save();

            Session::put('success', 'Successfully linked.');
            return redirect('/app/connect');
        }

        if(!isset($data['data'])) {
            return redirect('https://www.eracast.cc/signin?context=connect&next=&feature=aesthetifulplus');
        } else {
            function decryptData($data, $key) {
                $data = base64_decode(urldecode($data));
                $iv = substr($data, 0, 16);
                $encrypted = substr($data, 16);
                $decrypted = openssl_decrypt($encrypted, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
                parse_str($decrypted, $dataArray);
                return $dataArray;
            }

            $validator = Validator::make($data, [
                'data' => 'required|string'
            ]);

            if($validator->fails()) {
                Session::put('error', $validator->errors()->first());
                return redirect('/app/connect');
            }

            $decoded = decryptData($data['data'], 'connect');

            if(!isset($decoded['e_username'])) {
                Session::put('error', 'There was an error, please try again.');
                return redirect('/app/connect');
            }

            $response = Http::withHeaders([
                'User-Agent' => 'finobe.net/Server 1.0'
            ])->get('https://api.eracast.cc/v1/get_user_pfp', [
                'user' => $decoded['e_username']
            ]);

            $this->request['data']['pfp'] = $response->body();
            $this->request['data']['e_username'] = $decoded['e_username'];
            $this->request['data']['e_id'] = $decoded['e_id'];
        }

        return view($this->request['data']['user']['version'] . '/Settings/ConnectAPI', $this->request);
    }

    public function legal_about_us(Request $request) {
        $this->request['data']['embeds']['title'] = 'About us' . $this->request['data']['embeds']['title'];

        return view($this->request['data']['user']['version'] . '/About-us', $this->request);
    }

    public function legal_welcome(Request $request) {
        $this->request['data']['embeds']['title'] = 'Welcome' . $this->request['data']['embeds']['title'];

        if(!$this->request['data']['siteusername']) {
            return redirect('/');
        }

        return view($this->request['data']['user']['version'] . '/Welcome', $this->request);
    }

    public function legal_rules(Request $request) {
        $this->request['data']['embeds']['title'] = 'Rules' . $this->request['data']['embeds']['title'];

        return view($this->request['data']['user']['version'] . '/Rules', $this->request);
    }

    public function legal_terms(Request $request) {
        $this->request['data']['embeds']['title'] = 'Terms of Service' . $this->request['data']['embeds']['title'];

        return view($this->request['data']['user']['version'] . '/Terms', $this->request);
    }

    public function transparency_bans(Request $request) {
        $this->request['data']['embeds']['title'] = 'Public Ban List' . $this->request['data']['embeds']['title'];
        $data = $request->all();

        $bans = [];
        $results = $this->db->table('bans')
            ->whereIn('username', function ($subquery) {
                $subquery->select('username')->from('users');
            })
            ->orderByDesc('id')
            ->limit(50);

        if(isset($data['q'])) {
            $search = '%' . $data['q'] . '%';
            $results->where('username', 'like', $search);
        }

        $results = $results->get()
            ->map(function ($item) {
                return (array) $item;
            })->toArray();
        
        foreach($results as $result) {
            if(!User::where('username', $result['username'])->exists()) {
                continue;
            }

            $result['username'] = htmlspecialchars($result['username']);
            $result['date'] = date('Y-m-d', strtotime($result['date']));
            $result['expire'] = date('Y-m-d', strtotime($result['expire']));
            $bans[] = $result;
        }

        $results = $this->db->table('warning')
            ->whereIn('username', function ($subquery) {
                $subquery->select('username')->from('users');
            })
            ->orderByDesc('id')
            ->limit(50);

        if(isset($data['q'])) {
            $search = '%' . $data['q'] . '%';
            $results->where('username', 'like', $search);
        }

        $results = $results->get()
            ->map(function ($item) {
                return (array) $item;
            })->toArray();

        foreach($results as $result) {
            if(!User::where('username', $result['username'])->exists()) {
                continue;
            }

            $result['username'] = htmlspecialchars($result['username']);
            $result['date'] = date('Y-m-d', strtotime($result['date']));
            $bans[] = $result;
        }

        usort($bans, function($a, $b) {
			return strtotime($b['date']) - strtotime($a['date']);
		});
        $this->request['data']['bans'] = $bans;

        return view($this->request['data']['user']['version'] . '/Bans', $this->request);
    }

    public function gettoken(Request $request) {
        if(!$this->request['data']['siteusername']) {
            return response('', 204);
        }

        return response($this->request['data']['user']['token'], 200);
    }

    public function videos(Request $request) {
        $this->request['data']['embeds']['title'] = 'Videos' . $this->request['data']['embeds']['title'];
        $data = $request->all();

        $videos = $this->db->table('videos')->count();
        
        $pages_to_show = 10;
        $results_per_page = 20;
        $number_of_pages = ceil($videos / $results_per_page);
        $currentPage = isset($data['page']) ? max(1, intval($data['page'])) : 1;
        $offset = ($currentPage - 1) * $results_per_page;
        $start_page = max(1, min($currentPage - floor($pages_to_show / 2), $number_of_pages - $pages_to_show + 1));
        $end_page = min($number_of_pages, $start_page + $pages_to_show - 1);
        $videos = $this->db->table('videos')
            ->orderBy('id', 'DESC')
            ->offset($offset)
            ->limit($results_per_page)
            ->get()
            ->map(function ($item) {
                return (array) $item;
            })->toArray();
        
        foreach($videos as $key => $video) {
            if(Redis::exists("video_processing:{$video['filename']}")) {
                unset($videos[$key]);
            }
        }
        
        $this->request['data']['videos'] = [
            'data' => $videos,
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
            $this->request['data']['videos']['pages']['data'][] = ['page' => $page];
        }

        if(!$videos) {
            $this->request['data']['videos']['pages']['data'][] = [
                'page' => 1
            ];
        }

        return view($this->request['data']['user']['version'] . '/Videos/Index', $this->request);
    }

    public function video(Request $request, $id) {
        if(!$this->db->table('videos')->where('id', $id)->exists()) {
            Session::put('error', 'Video not found');
            return redirect('/videos');
        }

        $video = (array) $this->db->table('videos')
            ->where('id', $id)
            ->first();
        
        $video['title'] = strip_tags(htmlspecialchars($video['title']));
        $video['description'] = nl2br(strip_tags(htmlspecialchars($video['description'])));
        $video['uuid'] = User::where('username', $video['author'])->value('id');
        $video['author'] = htmlspecialchars($video['author']);
        $video['rating'] = $this->db->table('video_ratings')->where('toid', $video['id'])->where('rate_type', 'l')->count();
        $video['upvotes'] = $video['rating'];
        $video['rating'] = $video['rating'] - $this->db->table('video_ratings')->where('toid', $video['id'])->where('rate_type', 'd')->count();
        $video['downvotes'] = $this->db->table('video_ratings')->where('toid', $video['id'])->where('rate_type', 'd')->count();

        if($this->request['data']['siteusername']) {
            if($this->db->table('video_ratings')->where('toid', $id)->where('sender', $this->request['data']['user']['username'])->count()) {
                $video['userRating'] = $this->db->table('video_ratings')
                    ->select('rate_type')
                    ->where('toid', $id)
                    ->where('sender', $this->request['data']['user']['username'])
                    ->value('rate_type');
            }
        }

        $this->request['data']['embeds']['title'] = $video['title'] . $this->request['data']['embeds']['title'];
        $this->request['data']['video'] = $video;

        return view($this->request['data']['user']['version'] . '/Videos/Video', $this->request);
    }

    public function video_thumb(Request $request, $id) {
        if(!$this->db->table('videos')->where('id', $id)->exists()) {
            return response()->view($this->request['data']['user']['version'] . '/404', $this->request, 404);
        }

        return redirect('https://cdn.finobe.net/videos/thumbs/' . $this->db->table('videos')->select('thumbnail')->where('id', $id)->value('thumbnail'));
    }

    public function video_data(Request $request, $id) {
        if(!$this->db->table('videos')->where('id', $id)->exists()) {
            return response()->view($this->request['data']['user']['version'] . '/404', $this->request, 404);
        }

        //return redirect('https://cdn.finobe.net/videos/data/' . $this->db->table('videos')->select('filename')->where('id', $id)->value('filename'));
        $video = (array) $this->db->table('videos')
            ->where('id', $id)
            ->first();
        
        if(!file_exists('/var/www/cdn.finobe.net/videos/data/' . $video['filename'])) {
            abort(404, 'Video not found');
        }

        $filePath = '/var/www/cdn.finobe.net/videos/data/' . $video['filename'];
        $size = filesize($filePath);
        $start = 0;
        $end = $size - 1;

        $headers = [
            'Content-Type' => mime_content_type($filePath),
            'Accept-Ranges' => 'bytes',
        ];

        if($request->headers->has('Range')) {
            preg_match('/bytes=(\d+)-(\d*)/', $request->header('Range'), $matches);

            $start = intval($matches[1]);
            $end = isset($matches[2]) && is_numeric($matches[2]) ? intval($matches[2]) : $end;

            $length = $end - $start + 1;

            $headers['Content-Range'] = "bytes $start-$end/$size";
            $headers['Content-Length'] = $length;

            return response()->stream(function () use ($filePath, $start, $length) {
                $handle = fopen($filePath, 'rb');
                fseek($handle, $start);

                $buffer = 1024 * 8;
                $bytesSent = 0;

                while(!feof($handle) && $bytesSent < $length) {
                    $readLength = min($buffer, $length - $bytesSent);
                    echo fread($handle, $readLength);
                    $bytesSent += $readLength;
                    ob_flush();
                    flush();
                }

                fclose($handle);
            }, 206, $headers);
        }

        $headers['Content-Length'] = $size;

        return response()->stream(function () use ($filePath) {
            readfile($filePath);
        }, 200, $headers);
    }

    public function auth_form(Request $request) {
        $this->request['data']['embeds']['title'] = 'Form' . $this->request['data']['embeds']['title'];
        $this->request['data']['invitekeys'] = (bool) env('FINOBE_INVITE_KEYS');
        $data = $request->all();

        if($this->request['data']['siteusername']) {
            return redirect('/');
        }

        return view($this->request['data']['user']['version'] . '/Form', $this->request);
    }

    public function auth_register(Request $request) {
        $this->request['data']['embeds']['title'] = 'Register' . $this->request['data']['embeds']['title'];
        $this->request['data']['invitekeys'] = (bool) env('FINOBE_INVITE_KEYS');
        $data = $request->all();

        if($this->request['data']['siteusername']) {
            return redirect('/');
        }

        if($request->isMethod('post')) {
            $forbiddenPhrases = [
                'raped', 'dick', 'aesthetiful', 'instance', 'fuck', 'shit', 'fag', 'f@g', 'd1ck', 'pussy',
                'jew', 'tranny', 'tr@nny', 'goon', 'g@@n', 'g00n', 'gyat', 'gy@t', 'r@ped', 'tities', 't1t1es',
                'nigg', 'n!gger', 'nigga', 'n1gga', 'n1gger', 'pedo', 'fag', 'faggot'
            ];

            $validator = Validator::make($data, [
                'username' => 'required|string|regex:/^(?!_)(?!.*_$)(?!.*_.*_)[A-Za-z0-9_]+$/|unique:finobe.users,username|min:3|max:20',
                'password' => 'required|string|confirmed|alpha_dash|min:8|max:255',
                'email' => 'required|email|confirmed|unique:finobe.users,email',
                'invite_key' => ($this->request['data']['invitekeys'] ? 'required' : 'nullable') . '|string',
                'g-recaptcha-response' => 'required'
            ]);

            if($validator->fails()) {
                Session::put('error', $validator->errors()->first());
                return redirect('/auth/form');
            }

            if(!$this->request['data']['invitekeys']) {
                Session::put('error', 'Account creation is currently disabled');
                return redirect('/auth/form');
            }

            $username = strtolower($data['username']);
            foreach ($forbiddenPhrases as $phrase) {
                if (str_contains($username, strtolower($phrase))) {
                    Session::put('error', 'The username field must not be greater than 20 characters.');
                    return redirect('/auth/form');
                }
            }

            $response = Http::asForm()->post('https://www.google.com/recaptcha/api/siteverify', [
				'secret' => env('GOOGLE_RECAPTCHA_SECRET'),
				'response' => $data['g-recaptcha-response']
			])->json();

            if(!$response['success']) {
                Session::put('error', 'reCAPTCHA failed.');
                return redirect('/auth/form');
            }

            if($this->request['data']['invitekeys'] && isset($data['invite_key']) && !$this->db->table('invitekeys')->where('IID', $data['invite_key'])->where('used', 'n')->exists()) {
                Session::put('error', 'Invalid invite key.');
                return redirect('/auth/form');
            }

            $user = User::create([
                'username' => trim($data['username']),
                'email' => trim($data['email']),
                'password' => password_hash($data['password'], PASSWORD_BCRYPT),
                'friends' => '[]',
                'inventory' => '[]',
                'badges' => '[]',
                'avatar' => '[{"resolvedAvatarType":"R6","equippedGearVersionIds":[],"backpackGearVersionIds":[],"assetAndAssetTypeIds":[],"bodyColors":{"headColorId":24,"torsoColorId":"23","rightArmColorId":24,"leftArmColorId":24,"rightLegColorId":"119","leftLegColorId":"119"},"scales":{"height":1,"width":1,"head":1,"depth":1,"proportion":0,"bodyType":0}}]',
                'token' => bin2hex(random_bytes(30))
            ]);

            DataController::send_discord_message('<@541523977475194880>, ' . $data['username'] . ' has sign up');

            if($this->request['data']['invitekeys'] && isset($data['invite_key'])) {
                $this->db->table('invitekeys')
                    ->where('IID', $data['invite_key'])
                    ->update([
                        'used' => 'y',
                        'dateUsed' => now(),
                        'usedBy' => trim($data['username'])
                    ]);
            }

            Auth::login($user);
            return redirect('/legal/welcome');
        }

        return view($this->request['data']['user']['version'] . '/Register', $this->request['data']);
    }

    public function auth_login(Request $request) {
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

            if($validator->fails()) {
                Session::put('errorlogin', true);
                return redirect('/auth/login');
            }

            if(!User::where('email', $data['email'])->exists()) {
                Session::put('errorlogin', true);
                return redirect('/auth/login');
            }

            $user = User::where('email', $data['email'])->first();

            if(!Hash::check($data['password'], $user->password)) {
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
