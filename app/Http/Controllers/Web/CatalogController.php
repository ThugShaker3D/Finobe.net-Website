<?php

namespace App\Http\Controllers\Web;

use Carbon\Carbon;
use FFMpeg\Format\Audio\Mp3;
use FFMpeg\FFMpeg;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\Process\Exception\ProcessFailedException;
use App\Models\User;
use App\Models\Asset;
use App\Models\Video;
use App\Models\Purchases;
use App\Jobs\ProcessVideo;
use App\Http\Controllers\Asset as AssetHelper;
use App\Http\Controllers\frontEnd;
use App\Http\Controllers\Controller;
use App\Http\Controllers\dataController as DataController;

class CatalogController extends Controller
{
    protected $request;

    public function __construct(Request $request) {
        $frontend = new frontEnd($request);
        $this->request = $frontend->getData();
    }

    public function index(Request $request, $section) {
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
            "models" => 10
        ];

        if(!isset($sections[$section])) {
            return redirect('/catalog/hats');
        }

        $items = [];
        $pages_to_show = 10;
        $results_per_page = 12;

        if(isset($data['q'])) {
            $search = htmlspecialchars($data['q']);
            $results = Asset::whereRaw('LOWER(title) LIKE LOWER(?)', ["%{$search}%"])
                ->where('asset_type', $sections[$section])
                ->where('visibility', 'n')
                ->orderBy('id', 'DESC')
                ->count();
        } else {
            $results = Asset::where('asset_type', $sections[$section])
                ->where('visibility', 'n')
                ->orderBy('id', 'DESC')
                ->count();
        }

        $number_of_pages = ceil($results / $results_per_page);
        $currentPage = isset($data['page']) ? max(1, intval($data['page'])) : 1;
        $offset = ($currentPage - 1) * $results_per_page;
        $start_page = max(1, min($currentPage - floor($pages_to_show / 2), $number_of_pages - $pages_to_show + 1));
        $end_page = min($number_of_pages, $start_page + $pages_to_show - 1);
        $phrasesToReplace = $this->request['data']['string_replacements']['phrasesToReplace'];
        $replacements = $this->request['data']['string_replacements']['replacements'];

        if(isset($data['q'])) {
            $results = Asset::whereRaw('LOWER(title) LIKE LOWER(?)', ["%{$search}%"])
                ->where('asset_type', $sections[$section])
                ->where('visibility', 'n')
                ->orderBy('id', 'DESC')
                ->offset($offset)
                ->limit($results_per_page)
                ->get()
                ->map(fn($item) => $item->toArray());
        } else {
            $results = Asset::where('asset_type', $sections[$section])
                ->where('visibility', 'n')
                ->orderBy('id', 'DESC')
                ->offset($offset)
                ->limit($results_per_page)
                ->get()
                ->map(fn($item) => $item->toArray());
        }

        foreach($results as $result) {
            $result['title'] = htmlspecialchars(preg_replace_callback('/\b(' . implode('|', array_map('preg_quote', $phrasesToReplace)) . ')\b/i', fn() => $replacements[array_rand($replacements)], $result['title']));

            if($result['asset_type'] == 3) {
                $result['duration'] = DataController::timestamp($result['additional']['duration']);
            }

            $user = User::find($result['author']);
            
            $result['uuid'] = $user ? $user->toArray()['id'] : false;
            $result['author'] = htmlspecialchars($user['username'] ?? $result['additional']['oldUser']);
            $items[] = $result;
        }

        if($sections[$section] != 3 && count($results) && count($items) < 12) {
            $missing = $results_per_page - count($items);
            for ($i = 0; $i < $missing; $i++) {
                $items[] = [];
            }
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

        if(!count($items)) {
            $this->request['data']['items']['pages']['data'][] = [
                'page' => 1
            ];
        }

        $this->request['data']['items']['data'] = $items;
        $this->request['data']['section'] = $section;
        $this->request['data']['search'] = isset($data['q']) ? $data['q'] : false;

        return view($this->request['data']['user']['version'] . '/Catalog/Index', $this->request);
    }

    public function item(Request $request, $id) {
        $data = $request->all();
        $phrasesToReplace = $this->request['data']['string_replacements']['phrasesToReplace'];
        $replacements = $this->request['data']['string_replacements']['replacements'];

        if(!$this->request['data']['siteusername']) {
            return redirect('/');
        }

        if(!Asset::find($id)) {
            abort(404);
        }

        $item = Asset::find($id)->toArray();
        
        if($item['asset_type'] == 9) {
            return redirect('/place/' . $id);
        }

        if($item['asset_type'] == 1) {
            abort(404);
        }

        $item['title'] = htmlspecialchars(preg_replace_callback('/\b(' . implode('|', array_map('preg_quote', $phrasesToReplace)) . ')\b/i', fn() => $replacements[array_rand($replacements)], $item['title']));
        
        if(User::find($item['author'])) {
            $item['uuid'] = $item['author'];
        }

        $item['author'] = htmlspecialchars(User::where('id', $item['author'])->value('username') ?? $item['additional']['oldUser']);
        $item['description'] = nl2br(preg_replace('/\b((?:https?|ftp):\/\/\S+)/i', '<a href="$1">$1</a>', strip_tags(htmlspecialchars($item['description']))));
        $item['publish'] = date('m/d/Y', strtotime($item['created']));
	    $item['updated'] = date('m/d/Y', strtotime($item['updated']));

        if($item['asset_type'] == 3) {
            $item['thumbnail'] = Cache::remember('thumbnail_' . $item['additional']['media']['imageAssetId'], 60 * 60, fn() => Asset::where('id', $item['additional']['media']['imageAssetId'])->value('file'));
        }

        $item['isOwned'] = Purchases::where('username', $this->request['data']['user']['username'])->where('assetid', $id)->where('type', 1)->exists();
        $item['sales'] = number_format(Purchases::where('assetid', $id)->count());

        if($item['visibility'] == 'd') {
            $item['title'] = "[Not Approved]";
            $item['description'] = "[Not Approved]";
        }

        $this->request['data']['embeds']['title'] = $item['title'] . $this->request['data']['embeds']['title'];
        $this->request['data']['item'] = $item;

        return view($this->request['data']['user']['version'] . '/Catalog/Item', $this->request);
    }

    public function settings(Request $request, $id) {
        $data = $request->all();

        if(!$this->request['data']['siteusername']) {
            return redirect('/');
        }

        if(!Asset::find($id)) {
            abort(404);
        }

        $item = Asset::find($id)->toArray();
        
        if($item['author'] != $this->request['data']['user']['id']) {
            Session::put('error', 'You do not own this item');
            return redirect('/item/' . $id);
        }

        if(!in_array($item['asset_type'], [2, 3, 8, 11, 12, 18, 19])) {
            abort(404);
        }

        if($item['visibility'] != 'n') {
            Session::put('error', 'This item is currently unavailable to changes');
            return redirect('/item/' . $id);
        }

        $item['description'] = strip_tags(htmlspecialchars($item['description']));

        if($request->isMethod('post')) {
            $validator = Validator::make($data, [
                'title'            => 'required|string|min:3|max:255',
                'description'      => 'nullable|string|max:8192',
                'onsale'           => 'nullable|in:on,1,true,0,false,off',
                'price'            => 'required|integer|min:0'
            ]);

            if($validator->fails()) {
                Session::put('error', $validator->errors()->first());
                return redirect('/item/' . $id . '/settings');
            }

            if(in_array($item['asset_type'], [11, 12])) {
                $validator = Validator::make($data, [
                    'price' => 'required|integer|min:5'
                ]);

                if($validator->fails()) {
                    Session::put('error', $validator->errors()->first());
                    return redirect('/item/' . $id . '/settings');
                }
            } elseif(in_array($item['asset_type'], [2])) {
                $validator = Validator::make($data, [
                    'price' => 'required|integer|min:2'
                ]);

                if($validator->fails()) {
                    Session::put('error', $validator->errors()->first());
                    return redirect('/item/' . $id . '/settings');
                }
            }

            $item['additional']['onSale'] = isset($data['onsale']);
            $item['additional']['price'] = intval($data['price']);

            $item2 = Asset::find($id);
            $item2->title = $data['title'];
            $item2->description = $data['description'] ?? '';
            $item2->additional = $item['additional'];
            $item2->save();

            Session::put('successv2', 'Item settings saved.');
            return redirect('/item/' . $id . '/settings');
        }

        $this->request['data']['embeds']['title'] = $item['title'] . $this->request['data']['embeds']['title'];
        $this->request['data']['item'] = $item;

        return view($this->request['data']['user']['version'] . '/Catalog/Item_settings', $this->request);
    }

    public function new(Request $request) {
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
                
                Video::create([
                    'title' => $data['title'],
                    'author' => $this->request['data']['user']['username'],
                    'filename' => $filename,
                    'thumbnail' => $thumbnail,
                    'description' => $data['description'] ?? ''
                ]);

                $user = User::find($this->request['data']['user']['id']);
                $user->Dius -= 5;
                $user->save();

                Purchases::create([
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

                $audio = Asset::create([
                    'asset_type' => $assetTypes[$data['media-type']],
                    'title' => $data['title'],
                    'author' => $this->request['data']['user']['id'],
                    'file' => $filename,
                    'description' => $data['description'] ?? '',
                    'visibility' => 'r',
                    'additional' => [
                        'duration' => $duration,
                        'price' => intval($data['price']),
                        'media' => [
                            'imageAssetId' => 2
                        ],
                        'oldUser' => ''
                    ]
                ]);

                $user = User::find($this->request['data']['user']['id']);
                $user->Dius -= 5;
                $user->save();

                Purchases::create([
                    'username' => $user->username,
                    'assetid' => 0,
                    'author' => 0,
                    'amount' => -5,
                    'type' => 5
                ]);

                DataController::send_discord_message('<@541523977475194880>, ' . $this->request['data']['user']['username'] . ' uploaded an item, moderate it! [ https://finobe.net/admin/assets ]');

                return redirect('/item/' . $audio->id);
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
                    $id = AssetHelper::createAccessory(
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

                Purchases::create([
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
                    $id = AssetHelper::createAccessory(
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

                Purchases::create([
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
                    $id = AssetHelper::createAccessory(
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

                Purchases::create([
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
                    $id = AssetHelper::createAccessory(
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

                Purchases::create([
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
}