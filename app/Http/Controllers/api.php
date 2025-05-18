<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
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

        $items = $this->db->table('purchases')
            ->join('assets', 'purchases.assetid', '=', 'assets.id')
            ->where('purchases.username', $data['user'])
            ->where('purchases.assetid', '!=', 0)
            ->where('assets.asset_type', $assetTypes[$data['type']])
            ->orderBy('purchases.date', 'desc')
            /*
            ->offset($offset)
            ->limit($itemsPerPage)
            */
            ->paginate($itemsPerPage)
            ->select('assets.id', 'assets.title', 'assets.author', 'assets.asset_type', 'assets.additional')
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
            $this->response['items']['data'][] = $item;
        }
        
        $this->response['data']['items'] = [
            'data' => [],
            'pagination' => [
                'current_page' => $currentPage,
                'number_of_pages' => ceil(count($this->response['data']['items']) / $itemsPerPage)
            ]
        ];

        return response()->json($this->response, 200);
    }
}
