<?php

namespace App\Http\Controllers\Web;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Validator;
use League\CommonMark\MarkdownConverter;
use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use App\Models\Ban;
use App\Models\User;
use App\Models\Asset;
use App\Models\Server;
use App\Models\Notification;
use App\Models\Forum\Rating;
use App\Models\Forum\Thread;
use App\Models\Forum\Subscription;
use App\Http\Controllers\frontEnd;
use App\Http\Controllers\Controller;
use App\Http\Controllers\dataController;

class GamesController extends Controller
{
    protected $request;

    public function __construct(Request $request) {
        $frontend = new frontEnd($request);
        $this->request = $frontend->getData();
    }

    public function index() {
        $this->request['data']['embeds']['title'] = 'Places' . $this->request['data']['embeds']['title'];

        if(!$this->request['data']['siteusername']) {
            return redirect('/');
        }

        return view($this->request['data']['user']['version'] . '/Places/Index', $this->request);
    }

    public function place(Request $request, $id) {
        if(!$this->request['data']['siteusername']) {
            return redirect('/');
        }

        if(!Asset::where('id', $id)->where('asset_type', 9)->exists()) {
            abort(404);
        }

        $place = Asset::find($id)->toArray();
        
        $place['additional'] = json_decode($place['additional'], true);
        $this->request['data']['embeds']['title'] = strip_tags(htmlspecialchars($place['title'])) . $this->request['data']['embeds']['title'];

        $place = [
            'id' => $place['id'],
            'additional' => $place['additional'],
            'title' => strip_tags(htmlspecialchars($place['title'])),
            'username' => User::where('id', $place['author'])->value('username'),
            'author' => $place['author'],
            'description' => nl2br(preg_replace('/\b((?:https?|ftp):\/\/\S+)/i', '<a href="$1">$1</a>', strip_tags(htmlspecialchars($place['description'])))),
            'created' => date('m/d/Y', strtotime($place['created'])),
            'updated' => date('m/d/Y', strtotime($place['updated'])),
            'visits' => number_format($place['additional']['visits']),
            'thumbnail' => Asset::where('id', $place['additional']['media']['imageAssetId'])->value('file'),
            'servers' => []
        ];

        $servers = [];
        $results = Server::where('placeid', $place)->get()->map(fn($item) => $item->toArray());

        foreach($results as $result) {
            $players = json_decode($result['players'], true);
            $result['players'] = [];

            foreach($players as $playerId) {
                if(User::find($playerId)) {
                    $result['players'][] = [
                        'userid' => $playerId,
                        'username' => User::where('id', $playerId)->value('username'),
                        'avatar' => Cache::remember('pfp_' . User::where('id', $playerId)->value('username'), 60 * 60, fn() => User::where('username', User::where('id', $playerId)->value('username'))->value('pfp'))
                    ];
                }
            }

            $place['servers'][] = $result;
        }

        $this->request['data']['place'] = $place;

        return view($this->request['data']['user']['version'] . '/Places/Place', $this->request);
    }

    public function place_settings(Request $request, $id) {
        $data = $request->all();

        if(!$this->request['data']['siteusername']) {
            return redirect('/');
        }

        if(!Asset::where('id', $id)->where('asset_type', 9)->exists()) {
            abort(404);
        }

        $place = Asset::find($id)->toArray();
        $place['username'] = User::where('id', $place['author'])->value('username');
        $place['additional'] = json_decode($place['additional'], true);

        if($place['username'] != $this->request['data']['user']['username']) {
            Session::put('error', 'You do not own this place');
            return redirect('/app/places');
        }

        if($request->isMethod('post')) {
            $validator = Validator::make($data, [
                'title'            => 'required|string|min:3|max:255',
                'description'      => 'nullable|string|max:8192',
                'allowplaying'     => 'nullable|in:on,1,true,0,false,off',
                'downloadable'     => 'nullable|in:on,1,true,0,false,off',
                'hideRecent'       => 'nullable|in:on,1,true,0,false,off',
                'combat'           => 'nullable|in:on,1,true,0,false,off',
                'social'           => 'nullable|in:on,1,true,0,false,off',
                'building'         => 'nullable|in:on,1,true,0,false,off',
                'musical'          => 'nullable|in:on,1,true,0,false,off',
                'game-version'     => 'required|in:2012,2016',
                'category'         => 'required|in:original,copy',
                'chat-type'        => 'required|in:classic,bubble_chat,both',
                'max-players'      => 'required|integer|between:5,100',
            ]);

            if($validator->fails()) {
                Session::put('error', $validator->errors()->first());
                return redirect('/place/' . $id . '/settings');
            }

            $place['additional']['allowplaying'] = isset($data['allowplaying']);
            $place['additional']['uncopylocked'] = isset($data['downloadable']);
            $place['additional']['hidden'] = isset($data['hideRecent']);
            $place['additional']['gears']['combat'] = isset($data['combat']);
            $place['additional']['gears']['social'] = isset($data['social']);
            $place['additional']['gears']['building'] = isset($data['building']);
            $place['additional']['gears']['musical'] = isset($data['musical']);
            $place['additional']['version'] = $data['game-version'];
            $place['additional']['category'] = $data['category'];
            $place['additional']['chat_type'] = $data['chat-type'];
            $place['additional']['maxplayers'] = (int) $data['max-players'];

            $asset = Asset::find($id);
            $asset->title = $data['title'];
            $asset->description = $data['description'] ?? '';
            $asset->additional = json_encode($place['additional']);
            $asset->save();
            
            Session::put('successv2', 'Place settings saved.');
            return redirect('/place/' . $id . '/settings');
        }

        $place['title'] = strip_tags(htmlspecialchars($place['title']));
        //$place['description'] = nl2br(preg_replace('/\b((?:https?|ftp):\/\/\S+)/i', '<a href="$1" target="_blank">$1</a>', strip_tags(htmlspecialchars($place['description']))));

        $this->request['data']['place'] = $place;
        $this->request['data']['embeds']['title'] = $place['title'] . $this->request['data']['embeds']['title'];

        return view($this->request['data']['user']['version'] . '/Places/Place_settings', $this->request);
    }
}