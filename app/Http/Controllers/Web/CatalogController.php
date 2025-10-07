<?php

namespace App\Http\Controllers\Web;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Validator;
use App\Models\User;
use App\Models\Asset;
use App\Models\Purchases;
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
            $result['additional'] = json_decode($result['additional'], true);
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

        $item['additional'] = json_decode($item['additional'], true);
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

        $item['additional'] = json_decode($item['additional'], true);
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
            $item2->additional = json_encode($item['additional']);
            $item2->save();

            Session::put('successv2', 'Item settings saved.');
            return redirect('/item/' . $id . '/settings');
        }

        $this->request['data']['embeds']['title'] = $item['title'] . $this->request['data']['embeds']['title'];
        $this->request['data']['item'] = $item;

        return view($this->request['data']['user']['version'] . '/Catalog/Item_settings', $this->request);
    }
}