<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class api extends Controller
{
    protected $db;
    protected $response;

    public function __construct(Request $request) {
        $this->db = DB::connection('finobe');
        $this->response = [
            'code' => 200,
            'message' => ''
        ];
    }

    public function inventory(Request $request) {
        $data = $request->all();

        if(!Auth::check() || !isset($data['type']) || !isset($data['user']) || !User::where('username', $data['user'])->exists()) {
            $this->response['code'] = 403;
            $this->response['message'] = 'Access denied';

            return response()->json($this->response, 403);
        }

        $assetTypes = [
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

        $itemsPerPage = 12;
        $currentPage = isset($data['page']) ? max(1, intval($data['page'])) : 1;
        $offset = ($currentPage - 1) * $itemsPerPage;

        $this->request['data'] = [
            'items' => [
                'data' => [] // WHY UNDEFINED WHEN NO ITEMS??
            ]
        ];

        $items = $this->db->table('purchases')
            ->join('assets', 'purchases.assetid', '=', 'assets.id')
            ->where('purchases.username', $data['user'])
            ->where('purchases.assetid', '!=', 0)
            ->where('assets.asset_type', $assetTypes[$data['type']])
            ->orderBy('purchases.date', 'desc')
            ->select('assets.id', 'assets.title', 'assets.author', 'assets.asset_type', 'assets.additional')
            ->offset($offset)
            ->limit($itemsPerPage)
            ->get()
            ->map(function ($item) {
                return (array) $item;
            })->toArray();

        foreach($items as $item) {
            $item['additional'] = json_decode($item['additional'], true);
            $item['thumbnail'] = $item['asset_type'] == 3 ? 'https://finobe.net/s/img/speaker.png' : $item['additional']['media']['thumbnail'];

            $user = User::find($item['author']);

            $item['details'] = [
                'uid' => $user['id'] ?? false
            ];

            $item['title'] = htmlspecialchars($item['title']);
            $item['author'] = htmlspecialchars($user['username'] ?? $item['additional']['oldUser']);
            $this->response['data']['items']['data'][] = $item;
        }
        
        $this->response['data']['items']['pagination'] = [
            'current_page' => $currentPage,
            'number_of_pages' => ceil(count($this->response['data']['items']['data'] ?? []) / $itemsPerPage)
        ];

        return response()->json($this->response, 200);
    }

    public function rate(Request $request) {
        if(!Auth::check() || !isset($data['type']) || !isset($data['postId']) || !isset($data['rating'])) {
            $this->response['code'] = 400;
            $this->response['message'] = 'Bad request';

            return response()->json($this->response, 400);
        }

        $user = Auth::user()->toArray();
        $data['type'] = ($data['type'] == '1') ? '1' : '2';
        $data['rating'] = ($data['rating'] == 'l') ? 'l' : 'd';

        if($this->db->table('forum_ratings')->where('sender', $user['username'])->where('type', $data['type'])->where('toid', $data['postId'])->count()) {
            $ratingData = $this->db->table('forum_ratings')
                ->where('sender', $user['username'])
                ->where('type', $data['type'])
                ->where('toid', $data['postId'])
                ->get()
                ->map(function ($item) {
                    return (array) $item;
                })->toArray();
            
            if($ratingData['rate_type'] != $data['rating']) {
                $this->db->table('forum_ratings')
                    ->where('id', $ratingData['id'])
                    ->update([
                        'rate_type' => $data['rating']
                    ]);
            } else {
                $this->db->table('forum_ratings')
                    ->where('sender', $user['username'])
                    ->where('toid', $data['postId'])
                    ->where('type', $data['type'])
                    ->delete();
            }
        } else {
            $this->db->table('forum_ratings')->insert([
                'sender' => $user['username'],
                'type' => $data['type'],
                'toid' => $data['postId'],
                'rate_type' => $data['rating']
            ]);
        }

        return response()->json($this->response, 200);
    }
}
