<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use App\Models\User;
use App\Models\Asset;
use App\Models\Server;
use App\Models\Replies;
use App\Models\Message;
use App\Models\Purchases;
use App\Models\Notification;
use App\Models\Forum\Rating;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Validator;

class api extends Controller
{
    protected $response;

    public function __construct(Request $request) {
        $this->response = [
            'code' => 200,
            'message' => 'Success'
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
            "models" => 10
        ];

        $itemsPerPage = 12;
        $currentPage = isset($data['page']) ? max(1, intval($data['page'])) : 1;
        $offset = ($currentPage - 1) * $itemsPerPage;

        $this->request['data'] = [
            'items' => [
                'data' => [] // WHY UNDEFINED WHEN NO ITEMS??
            ]
        ];

        if(!isset($assetTypes[$data['type']])) {
            $this->response['code'] = 400;
            $this->response['message'] = 'Bad request';

            return response()->json($this->response, 400);
        }

        $itemcount = Purchases::join('assets', 'purchases.assetid', '=', 'assets.id')
            ->where('purchases.username', $data['user'])
            ->where('purchases.assetid', '!=', 0)
            ->where('purchases.type', 1)
            ->where('assets.asset_type', $assetTypes[$data['type']])
            ->orderBy('purchases.date', 'desc')
            ->select('assets.id', 'assets.title', 'assets.author', 'assets.asset_type', 'assets.additional')
            ->count();
        
        $items = Purchases::join('assets', 'purchases.assetid', '=', 'assets.id')
            ->where('purchases.username', $data['user'])
            ->where('purchases.assetid', '!=', 0)
            ->where('purchases.type', 1)
            ->where('assets.asset_type', $assetTypes[$data['type']])
            ->where('assets.visibility', 'n')
            ->orderBy('purchases.date', 'desc')
            ->select('assets.id', 'assets.title', 'assets.author', 'assets.asset_type', 'assets.additional')
            ->offset($offset)
            ->limit($itemsPerPage)
            ->get()
            ->map(fn($item) => $item->toArray());

        foreach($items as $item) {
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
            'number_of_pages' => ceil($itemcount / $itemsPerPage),
            'number_of_items' => $itemcount
        ];

        return response()->json($this->response, 200);
    }

    public function rate(Request $request) {
        $data = $request->all();

        if(!Auth::check()) {
            $this->response['code'] = 400;
            $this->response['message'] = 'Bad request';

            return response()->json($this->response, 400);
        }

        $validator = Validator::make($data, [
            'type'   => 'required|string|in:1,2|size:1',
            'postId' => 'required|integer',
            'rating' => 'required|string|in:l,d|size:1',
        ]);

        if($validator->fails()) {
            $this->response['code'] = 400;
            $this->response['message'] = 'Bad request';
            $this->response['errors'] = $validator->errors();

            return response()->json($this->response, 400);
        }

        $user = Auth::user()->toArray();
        $data['postId'] = intval($data['postId']);

        if(Rating::where('sender', $user['username'])->where('type', $data['type'])->where('toid', $data['postId'])->count()) {
            $ratingData = Rating::where('sender', $user['username'])
                ->where('type', $data['type'])
                ->where('toid', $data['postId'])
                ->first()
                ->toArray();
            
            if($ratingData['rate_type'] != $data['rating']) {
                $rating = Rating::find($ratingData['id']);
                $rating->rate_type = $data['rating'];
                $rating->save();
            } else {
                Rating::where('sender', $user['username'])
                    ->where('toid', $data['postId'])
                    ->where('type', $data['type'])
                    ->delete();
            }
        } else {
            Rating::create([
                'sender' => $user['username'],
                'type' => $data['type'],
                'toid' => $data['postId'],
                'rate_type' => $data['rating']
            ]);
        }

        return response()->json($this->response, 200);
    }

    public function rating_number(Request $request) {
        $data = $request->all();

        if(!Auth::check()) {
            $this->response['code'] = 400;
            $this->response['message'] = 'Bad request';

            return response()->json($this->response, 400);
        }

        $validator = Validator::make($data, [
            'type' => 'required|string|size:1',
            'postId' => 'required|integer',
        ]);

        if($validator->fails()) {
            $this->response['code'] = 400;
            $this->response['message'] = 'Bad request';
            $this->response['errors'] = $validator->errors();

            return response()->json($this->response, 400);
        }

        $data['postId'] = intval($data['postId']);

        $rating = Rating::where('type', $data['type'])
            ->where('toid', $data['postId'])
            ->where('rate_type', 'l')
            ->count();
        
        $rating -= Rating::where('type', $data['type'])
            ->where('toid', $data['postId'])
            ->where('rate_type', 'd')
            ->count();
        
        $this->response['rating'] = $rating;
        return response()->json($this->response, 200);
    }

    public function mark(Request $request) {
        $data = $request->all();

        if(!Auth::check()) {
            return redirect('/');
        }

        if(!isset($data['id'])) {
            Session::put('error', 'Bad request!');
            return redirect('/');
        }

        $user = Auth::user()->toArray();

        if($data['id'] == 'all') {
            $message = Message::where('touser', $user['id']);
            $message->readed = 'y';
            $message->save();
            
            return redirect('/');
        }

        if(!Notification::find($data['id'])) {
            return redirect('/');
        }

        $notification = Notification::find($data['id']);
        $notification->readed = 'y';
        $notification->save();
        
        $results_per_page = 10;
        $total_replies_before = Replies::where('toid', $notification->forum_id)->where('id', $notification->reply_id)->count();
        $page_number = ceil($total_replies_before / $results_per_page);
        $page_number = max(1, $page_number);

        return redirect('/forum/post?id=' . $notification->forum_id . ($page_number < 1 ? '&page=' . $page_number : ''));
    }

    public function purchase(Request $request) {
        $data = $request->all();

        if(!Auth::check()) {
            $this->response['code'] = 400;
            $this->response['message'] = 'Bad request';

            return response()->json($this->response, 400);
        }

        if(!isset($data['assetid'])) {
            $this->response['code'] = 400;
            $this->response['message'] = 'Bad request';

            return response()->json($this->response, 400);
        }

        if(!Asset::find($data['assetid'])) {
            $this->response['code'] = 400;
            $this->response['message'] = 'Asset does not exist';

            return response()->json($this->response, 400);
        }

        $user = Auth::user();
        $item = Asset::find($data['assetid']);

        if(!in_array($item->asset_type, [2, 3, 8, 11, 12, 18, 19])) {
            $this->response['code'] = 400;
            $this->response['message'] = 'Invalid item type';

            return response()->json($this->response, 400);
        }
        
        if($user['Dius'] - $item->additional['price'] < 0) {
            $this->response['code'] = 400;
            $this->response['message'] = 'Not enough Dius';

            return response()->json($this->response, 400);
        }

        if(Purchases::where('username', $user['username'])->where('assetid', $data['assetid'])->where('serial', isset($data['serial']) ? $data['serial'] : 0)->where('type', 1)->exists()) {
            $this->response['code'] = 400;
            $this->response['message'] = 'This item has already been purchased';

            return response()->json($this->response, 400);
        }

        if(!$item->additional['onSale']) {
            $this->response['code'] = 400;
            $this->response['message'] = 'This item not on sale';

            return response()->json($this->response, 400);
        }

        Purchases::create([
            'username' => $user['username'],
            'assetid' => $data['assetid'],
            'serial' => isset($data['serial']) ? $data['serial'] : 0,
            'author' => $item->author,
            'amount' => -1 * $item->additional['price']
        ]);

        $user->Dius -= $item->additional['price'];
        $user->save();

        if(User::where('id', $item->author)->exists() && $user->id != $item->author) {
            $user = User::find($item['author']);
            $user->Dius += $item->additional['price'];
            $user->save();

            Purchases::create([
                'username' => $user['username'],
                'assetid' => $data['assetid'],
                'serial' => isset($data['serial']) ? $data['serial'] : 0,
                'author' => $item->author,
                'amount' => $item->additional['price'],
                'type' => 2
            ]);
        }
        
        return response()->json($this->response, 200);
    }

    public function character(Request $request) {
        $data = $request->all();

        if(!Auth::check() || !isset($data['type'])) {
            $this->response['code'] = 400;
            $this->response['message'] = 'Bad request';

            return response()->json($this->response, 400);
        }

        $idNumbers = [
            1, 2, 3, 5, 6, 9, 11, 12, 18, 21, 22, 23, 24, 25, 26, 27, 28, 29, 36, 37, 38, 39, 40, 41, 42,
            43, 44, 45, 47, 48, 49, 50, 100, 101, 102, 103, 104, 105, 106, 107, 108, 110, 111, 112, 113,
            115, 116, 118, 119, 120, 121, 123, 124, 125, 126, 127, 128, 131, 133, 134, 135, 136, 137, 138,
            140, 141, 143, 145, 146, 147, 148, 149, 150, 151, 153, 154, 157, 158, 168, 176, 178, 179, 180,
            190, 191, 192, 193, 194, 195, 196, 198, 199, 200, 208, 209, 210, 211, 212, 213, 216, 217, 218,
            219, 220, 221, 222, 223, 224, 225, 226, 232, 268, 301, 302, 303, 304, 305, 306, 307, 308, 309,
            310, 311, 312, 313, 314, 315, 316, 317, 318, 319, 320, 321, 322, 323, 324, 325, 327, 328, 329,
            330, 331, 332, 333, 334, 335, 336, 337, 338, 339, 340, 341, 342, 343, 344, 345, 346, 347, 348,
            349, 350, 351, 352, 353, 354, 355, 356, 357, 358, 359, 360, 361, 362, 363, 364, 365, 1001, 1002,
            1003, 1004, 1005, 1006, 1007, 1008, 1009, 1010, 1011, 1012, 1013, 1014, 1015, 1016, 1017, 1018,
            1019, 1020, 1021, 1022, 1023, 1024, 1025, 1026, 1027, 1028, 1029, 1030, 1031, 1032
        ];

        if($data['type'] == 'head') {
            if(!isset($data['color']) || !in_array($data['color'], $idNumbers)) {
                $this->response['code'] = 400;
                $this->response['message'] = 'Bad request';

                return response()->json($this->response, 400);
            }

            $user = Auth::user();
            $avatar = $user->avatar;
            $avatar[0]['bodyColors']['headColorId'] = $data['color'];
            $user->avatar = $avatar;
            $user->save();
        } elseif($data['type'] == 'torso') {
            if(!isset($data['color']) || !in_array($data['color'], $idNumbers)) {
                $this->response['code'] = 400;
                $this->response['message'] = 'Bad request';

                return response()->json($this->response, 400);
            }

            $user = Auth::user();
            $avatar = $user->avatar;
            $avatar[0]['bodyColors']['torsoColorId'] = $data['color'];
            $user->avatar = $avatar;
            $user->save();
        } elseif($data['type'] == 'r-arm') {
            if(!isset($data['color']) || !in_array($data['color'], $idNumbers)) {
                $this->response['code'] = 400;
                $this->response['message'] = 'Bad request';

                return response()->json($this->response, 400);
            }

            $user = Auth::user();
            $avatar = $user->avatar;
            $avatar[0]['bodyColors']['rightArmColorId'] = $data['color'];
            $user->avatar = $avatar;
            $user->save();
        } elseif($data['type'] == 'l-arm') {
            if(!isset($data['color']) || !in_array($data['color'], $idNumbers)) {
                $this->response['code'] = 400;
                $this->response['message'] = 'Bad request';

                return response()->json($this->response, 400);
            }

            $user = Auth::user();
            $avatar = $user->avatar;
            $avatar[0]['bodyColors']['leftArmColorId'] = $data['color'];
            $user->avatar = $avatar;
            $user->save();
        } elseif($data['type'] == 'r-leg') {
            if(!isset($data['color']) || !in_array($data['color'], $idNumbers)) {
                $this->response['code'] = 400;
                $this->response['message'] = 'Bad request';

                return response()->json($this->response, 400);
            }

            $user = Auth::user();
            $avatar = $user->avatar;
            $avatar[0]['bodyColors']['rightLegColorId'] = $data['color'];
            $user->avatar = $avatar;
            $user->save();
        } elseif($data['type'] == 'l-leg') {
            if(!isset($data['color']) || !in_array($data['color'], $idNumbers)) {
                $this->response['code'] = 400;
                $this->response['message'] = 'Bad request';

                return response()->json($this->response, 400);
            }

            $user = Auth::user();
            $avatar = $user->avatar;
            $avatar[0]['bodyColors']['leftLegColorId'] = $data['color'];
            $user->avatar = $avatar;
            $user->save();
        } elseif($data['type'] == 'hat') {
            if(!isset($data['assetid'])) {
                $this->response['code'] = 400;
                $this->response['message'] = 'Bad request';

                return response()->json($this->response, 400);
            }

            $user = Auth::user();
            $avatar = $user->avatar;

            $data['assetid'] = intval($data['assetid']);
            $itemcount = 0;
            $visibility = Asset::find($data['assetid'])->visibility;

            if($visibility != 'n' && !in_array($data['assetid'], $avatar[0]['equippedGearVersionIds'])) {
                $this->response['code'] = 400;
                $this->response['message'] = 'Item has not been approved or is under review';

                return response()->json($this->response, 400);
            }

            if(!Purchases::where('username', $user->username)->where('assetid', $data['assetid'])->where('type', 1)->exists()) {
                $this->response['code'] = 400;
                $this->response['message'] = 'You do not own this item';

                return response()->json($this->response, 400);
            }

            foreach($avatar[0]['equippedGearVersionIds'] as $key => $value) {
                if(Asset::where('id', $value)->where('asset_type', 8)->exists()) {
                    $itemcount++;
                }
            }

            if($itemcount < 5 || in_array($data['assetid'], $avatar[0]['equippedGearVersionIds'])) {
                if(in_array($data['assetid'], $avatar[0]['equippedGearVersionIds'])) {
                    $avatar[0]['equippedGearVersionIds'] = array_values(
                        array_diff($avatar[0]['equippedGearVersionIds'], [$data['assetid']])
                    );
                } else {
                    $avatar[0]['equippedGearVersionIds'][] = $data['assetid'];
                }
            } else {
                $this->response['code'] = 400;
                $this->response['message'] = 'Too many hats';

                return response()->json($this->response, 400);
            }

            $user->avatar = $avatar;
            $user->save();
        } elseif($data['type'] == 'shirt') {
            if(!isset($data['assetid'])) {
                $this->response['code'] = 400;
                $this->response['message'] = 'Bad request';

                return response()->json($this->response, 400);
            }

            $user = Auth::user();
            $avatar = $user->avatar;

            $data['assetid'] = intval($data['assetid']);
            $itemcount = 0;
            $visibility = Asset::find($data['assetid'])->visibility;

            if($visibility != 'n' && !in_array($data['assetid'], $avatar[0]['equippedGearVersionIds'])) {
                $this->response['code'] = 400;
                $this->response['message'] = 'Item has not been approved or is under review';

                return response()->json($this->response, 400);
            }

            if(!Purchases::where('username', $user->username)->where('assetid', $data['assetid'])->where('type', 1)->exists()) {
                $this->response['code'] = 400;
                $this->response['message'] = 'You do not own this item';

                return response()->json($this->response, 400);
            }

            foreach($avatar[0]['equippedGearVersionIds'] as $key => $value) {
                if(Asset::where('id', $value)->where('asset_type', 11)->exists()) {
                    $itemcount++;
                }
            }

            if($itemcount < 1 || in_array($data['assetid'], $avatar[0]['equippedGearVersionIds'])) {
                if(in_array($data['assetid'], $avatar[0]['equippedGearVersionIds'])) {
                    $avatar[0]['equippedGearVersionIds'] = array_values(
                        array_diff($avatar[0]['equippedGearVersionIds'], [$data['assetid']])
                    );
                } else {
                    $avatar[0]['equippedGearVersionIds'][] = $data['assetid'];
                }
            } else {
                $this->response['code'] = 400;
                $this->response['message'] = 'You are wearing a shirt already';

                return response()->json($this->response, 400);
            }

            $user->avatar = $avatar;
            $user->save();
        } elseif($data['type'] == 'pants') {
            if(!isset($data['assetid'])) {
                $this->response['code'] = 400;
                $this->response['message'] = 'Bad request';

                return response()->json($this->response, 400);
            }

            $user = Auth::user();
            $avatar = $user->avatar;

            $data['assetid'] = intval($data['assetid']);
            $itemcount = 0;
            $visibility = Asset::find($data['assetid'])->visibility;

            if($visibility != 'n' && !in_array($data['assetid'], $avatar[0]['equippedGearVersionIds'])) {
                $this->response['code'] = 400;
                $this->response['message'] = 'Item has not been approved or is under review';

                return response()->json($this->response, 400);
            }

            if(!Purchases::where('username', $user->username)->where('assetid', $data['assetid'])->where('type', 1)->exists()) {
                $this->response['code'] = 400;
                $this->response['message'] = 'You do not own this item';

                return response()->json($this->response, 400);
            }

            foreach($avatar[0]['equippedGearVersionIds'] as $key => $value) {
                if(Asset::where('id', $value)->where('asset_type', 12)->exists()) {
                    $itemcount++;
                }
            }

            if($itemcount < 1 || in_array($data['assetid'], $avatar[0]['equippedGearVersionIds'])) {
                if(in_array($data['assetid'], $avatar[0]['equippedGearVersionIds'])) {
                    $avatar[0]['equippedGearVersionIds'] = array_values(
                        array_diff($avatar[0]['equippedGearVersionIds'], [$data['assetid']])
                    );
                } else {
                    $avatar[0]['equippedGearVersionIds'][] = $data['assetid'];
                }
            } else {
                $this->response['code'] = 400;
                $this->response['message'] = 'You are wearing pants already';

                return response()->json($this->response, 400);
            }

            $user->avatar = $avatar;
            $user->save();
        } elseif($data['type'] == 'face') {
            if(!isset($data['assetid'])) {
                $this->response['code'] = 400;
                $this->response['message'] = 'Bad request';

                return response()->json($this->response, 400);
            }

            $user = Auth::user();
            $avatar = $user->avatar;

            $data['assetid'] = intval($data['assetid']);
            $itemcount = 0;
            $visibility = Asset::find($data['assetid'])->visibility;

            if($visibility != 'n' && !in_array($data['assetid'], $avatar[0]['equippedGearVersionIds'])) {
                $this->response['code'] = 400;
                $this->response['message'] = 'Item has not been approved or is under review';

                return response()->json($this->response, 400);
            }

            if(!Purchases::where('username', $user->username)->where('assetid', $data['assetid'])->where('type', 1)->exists()) {
                $this->response['code'] = 400;
                $this->response['message'] = 'You do not own this item';

                return response()->json($this->response, 400);
            }

            foreach($avatar[0]['equippedGearVersionIds'] as $key => $value) {
                if(Asset::where('id', $value)->where('asset_type', 18)->exists()) {
                    $itemcount++;
                }
            }

            if($itemcount < 1 || in_array($data['assetid'], $avatar[0]['equippedGearVersionIds'])) {
                if(in_array($data['assetid'], $avatar[0]['equippedGearVersionIds'])) {
                    $avatar[0]['equippedGearVersionIds'] = array_values(
                        array_diff($avatar[0]['equippedGearVersionIds'], [$data['assetid']])
                    );
                } else {
                    $avatar[0]['equippedGearVersionIds'][] = $data['assetid'];
                }
            } else {
                $this->response['code'] = 400;
                $this->response['message'] = 'You are wearing a face already';

                return response()->json($this->response, 400);
            }

            $user->avatar = $avatar;
            $user->save();
        } elseif($data['type'] == 't-shirt') {
            if(!isset($data['assetid'])) {
                $this->response['code'] = 400;
                $this->response['message'] = 'Bad request';

                return response()->json($this->response, 400);
            }

            $user = Auth::user();
            $avatar = $user->avatar;

            $data['assetid'] = intval($data['assetid']);
            $itemcount = 0;
            $visibility = Asset::where('id', $data['assetid'])->value('visibility');

            if($visibility != 'n' && !in_array($data['assetid'], $avatar[0]['equippedGearVersionIds'])) {
                $this->response['code'] = 400;
                $this->response['message'] = 'Item has not been approved or is under review';

                return response()->json($this->response, 400);
            }

            if(!Purchases::where('username', $user->username)->where('assetid', $data['assetid'])->where('type', 1)->exists()) {
                $this->response['code'] = 400;
                $this->response['message'] = 'You do not own this item';

                return response()->json($this->response, 400);
            }

            foreach($avatar[0]['equippedGearVersionIds'] as $key => $value) {
                if(Asset::where('id', $value)->where('asset_type', 2)->exists()) {
                    $itemcount++;
                }
            }

            if($itemcount < 1 || in_array($data['assetid'], $avatar[0]['equippedGearVersionIds'])) {
                if(in_array($data['assetid'], $avatar[0]['equippedGearVersionIds'])) {
                    $avatar[0]['equippedGearVersionIds'] = array_values(
                        array_diff($avatar[0]['equippedGearVersionIds'], [$data['assetid']])
                    );
                } else {
                    $avatar[0]['equippedGearVersionIds'][] = $data['assetid'];
                }
            } else {
                $this->response['code'] = 400;
                $this->response['message'] = 'You are wearing a t-shirt already';

                return response()->json($this->response, 400);
            }

            $user->avatar = $avatar;
            $user->save();
        } elseif($data['type'] == 'gear') {
            if(!isset($data['assetid'])) {
                $this->response['code'] = 400;
                $this->response['message'] = 'Bad request';

                return response()->json($this->response, 400);
            }

            $user = Auth::user();
            $avatar = $user->avatar;

            $data['assetid'] = intval($data['assetid']);
            $itemcount = 0;
            $visibility = Asset::find($data['assetid'])->visibility;

            if($visibility != 'n' && !in_array($data['assetid'], $avatar[0]['equippedGearVersionIds'])) {
                $this->response['code'] = 400;
                $this->response['message'] = 'Item has not been approved or is under review';

                return response()->json($this->response, 400);
            }

            if(!Purchases::where('username', $user->username)->where('assetid', $data['assetid'])->where('type', 1)->exists()) {
                $this->response['code'] = 400;
                $this->response['message'] = 'You do not own this item';

                return response()->json($this->response, 400);
            }

            foreach($avatar[0]['equippedGearVersionIds'] as $key => $value) {
                if(Asset::where('id', $value)->where('asset_type', 19)->exists()) {
                    $itemcount++;
                }
            }

            if($itemcount < 5 || in_array($data['assetid'], $avatar[0]['equippedGearVersionIds'])) {
                if(in_array($data['assetid'], $avatar[0]['equippedGearVersionIds'])) {
                    $avatar[0]['equippedGearVersionIds'] = array_values(
                        array_diff($avatar[0]['equippedGearVersionIds'], [$data['assetid']])
                    );
                } else {
                    $avatar[0]['equippedGearVersionIds'][] = $data['assetid'];
                }
            } else {
                $this->response['code'] = 400;
                $this->response['message'] = 'Too many gears';

                return response()->json($this->response, 400);
            }

            $user->avatar = $avatar;
            $user->save();
        }

        return response()->json($this->response, 200);
    }

    public function render(Request $request) {
        if(!Auth::check()) {
            $this->response['code'] = 400;
            $this->response['message'] = 'Bad request';

            return response()->json($this->response, 400);
        }

        $user = Auth::user();
        $avatar = $user->avatar;

        if($user->status != 'admin' && abs(Carbon::now()->diffInSeconds($user->render_cooldown)) <= 30) { // literally have no fucking clue why it goes into this (value is always negative)
            $this->response['code'] = 400;
            $this->response['message'] = 'Whoa, cool down with the regeneration requests there!';

            return response()->json($this->response, 400);
        }

        $arbiter = new RobloxArbiterUtilities(DataController::arbiter_pool(), 64989);
        $constructedJob = $arbiter->ConstructJob(
            RobloxUtilities::GenerateGUID(),
            '
                local asseturl, url, fileExtension, x, y = ...
                
                settings()["Task Scheduler"].ThreadPoolConfig = Enum.ThreadPoolConfig.PerCore4;
                game:GetService("ContentProvider"):SetThreadPool(16)
                game:GetService("ScriptContext").ScriptsDisabled=true 
                pcall(function() game:GetService("ContentProvider"):SetBaseUrl(url) end)
                player = game:GetService("Players"):CreateLocalPlayer(0)
                player.CharacterAppearance = asseturl

                print("Grabbing stuff..")
                print(player.CharacterAppearance)
                for _, child in ipairs(player.StarterGear:GetChildren()) do
                    print(child.Name .. " (" .. child.ClassName .. ")")
                end

                --player.StarterPack:GetObjectByClassName("").Parent = player.Character
                player:LoadCharacter(false)
                
                if player.Character then
                    for _, child in pairs(player.Character:GetChildren()) do
                        if child:IsA("Tool") then
                            player.Character.Torso["Right Shoulder"].CurrentAngle = math.rad(90)
                            break
                        end
                    end
                end
                
                game:GetService("ThumbnailGenerator").GraphicsMode = 4
                
                return game:GetService("ThumbnailGenerator"):Click(fileExtension, x, y, true)
            ',
            60,
            0,
            2,
            "ScriptExecution",
            [route('asset-game.character-fetch', ['userId' => $user->id]), "https://www.finobe.net", "PNG", 768, 768]
        );
    
        $jobEx = $arbiter->OpenJobEx($constructedJob);
        $filename = uniqid() . ".png";

        if(empty(base64_decode($jobEx))) {
            $this->response['code'] = 400;
            $this->response['message'] = 'There was an error while rendering.';

            return response()->json($this->response, 400);
        }

        file_put_contents("/var/www/cdn.finobe.net/avatar/" . $filename, base64_decode($jobEx));

        if($user->pfp != 'avatar/682b8bd6cbc48.png') {
            File::delete('/var/www/cdn.finobe.net/' . $user->pfp);
        }

        $user->render_cooldown = now();
        $user->pfp = "avatar/{$filename}";
        $user->save();

        $this->response['image'] = "https://cdn.finobe.net/avatar/{$filename}";

        return response()->json($this->response, 200);
    }

    public function places(Request $request) {
        $data = $request->all();

        if(!Auth::check()) {
            $this->response['code'] = 400;
            $this->response['message'] = 'Bad request';

            return response()->json($this->response, 400);
        }

        if(!isset($data['version'])) {
            $data['version'] = 'all';
        }

        if(!isset($data['type'])) {
            $data['type'] = 'all';
        }

        if(!isset($data['search'])) {
            $data['search'] = '';
        }

        $this->response['data'] = [];
        $this->response['info'] = [
            'pages' => 1,
            'current_page' => 1
        ];

        $results_per_page = 20;
        $currentPage = isset($data['page']) ? max(1, intval($data['page'])) : 1;
        $offset = ($currentPage - 1) * $results_per_page;
        if($data['type'] == 'all' && $data['version'] == 'all' && $data['search'] == '') {
            $results = Asset::leftJoin('servers', 'assets.id', '=', 'servers.placeid')
                ->select('assets.*', DB::raw('SUM(servers.players) as total_players'))
                ->where('asset_type', 9)
                ->groupBy('assets.id')
                ->orderByDesc('total_players')
                ->limit($results_per_page)
                ->offset($offset);
        } elseif($data['type'] == 'featured' && $data['version'] == 'all' && $data['search'] == '') {
            $results = Asset::leftJoin('servers', 'assets.id', '=', 'servers.placeid')
                ->select('assets.*', DB::raw('SUM(servers.players) as total_players'))
                ->whereRaw("additional->>'$.featured' = true")
                ->where('asset_type', 9)
                ->groupBy('assets.id')
                ->orderByDesc('total_players')
                ->limit($results_per_page)
                ->offset($offset);
        } elseif($data['type'] == 'featured' && $data['version'] == '2012' && $data['search'] == '') {
            $results = Asset::leftJoin('servers', 'assets.id', '=', 'servers.placeid')
                ->select('assets.*', DB::raw('SUM(servers.players) as total_players'))
                ->whereRaw("additional->>'$.featured' = true")
                ->whereRaw("additional->>'$.version' = '2012'")
                ->where('asset_type', 9)
                ->groupBy('assets.id')
                ->orderByDesc('total_players')
                ->limit($results_per_page)
                ->offset($offset);
        } elseif($data['type'] == 'featured' && $data['version'] == '2016' && $data['search'] == '') {
            $results = Asset::leftJoin('servers', 'assets.id', '=', 'servers.placeid')
                ->select('assets.*', DB::raw('SUM(servers.players) as total_players'))
                ->whereRaw("additional->>'$.featured' = true")
                ->whereRaw("additional->>'$.version' = '2016'")
                ->where('asset_type', 9)
                ->groupBy('assets.id')
                ->orderByDesc('total_players')
                ->limit($results_per_page)
                ->offset($offset);
        } elseif($data['type'] == 'all' && $data['version'] == '2012' && $data['search'] == '') {
            $results = Asset::leftJoin('servers', 'assets.id', '=', 'servers.placeid')
                ->select('assets.*', DB::raw('SUM(servers.players) as total_players'))
                ->whereRaw("additional->>'$.version' = '2012'")
                ->where('asset_type', 9)
                ->groupBy('assets.id')
                ->orderByDesc('total_players')
                ->limit($results_per_page)
                ->offset($offset);
        } elseif($data['type'] == 'all' && $data['version'] == '2016' && $data['search'] == '') {
            $results = Asset::leftJoin('servers', 'assets.id', '=', 'servers.placeid')
                ->select('assets.*', DB::raw('SUM(servers.players) as total_players'))
                ->whereRaw("additional->>'$.version' = '2016'")
                ->where('asset_type', 9)
                ->groupBy('assets.id')
                ->orderByDesc('total_players')
                ->limit($results_per_page)
                ->offset($offset);
        } elseif($data['type'] == 'featured' && $data['version'] == 'all' && $data['search'] != '') {
            $results = Asset::leftJoin('servers', 'assets.id', '=', 'servers.placeid')
                ->select('assets.*', DB::raw('SUM(servers.players) as total_players'))
                ->whereRaw('title LIKE ?', ['%' . htmlspecialchars($data['search']) . '%'])
                ->whereRaw("additional->>'$.featured' = true")
                ->where('asset_type', 9)
                ->groupBy('assets.id')
                ->orderByDesc('total_players')
                ->limit($results_per_page)
                ->offset($offset);
        } elseif($data['type'] == 'featured' && $data['version'] == '2012' && $data['search'] != '') {
            $results = Asset::leftJoin('servers', 'assets.id', '=', 'servers.placeid')
                ->select('assets.*', DB::raw('SUM(servers.players) as total_players'))
                ->whereRaw('title LIKE ?', ['%' . htmlspecialchars($data['search']) . '%'])
                ->whereRaw("additional->>'$.featured' = true")
                ->whereRaw("additional->>'$.version' = '2012'")
                ->where('asset_type', 9)
                ->groupBy('assets.id')
                ->orderByDesc('total_players')
                ->limit($results_per_page)
                ->offset($offset);
        } elseif($data['type'] == 'featured' && $data['version'] == '2016' && $data['search'] != '') {
            $results = Asset::leftJoin('servers', 'assets.id', '=', 'servers.placeid')
                ->select('assets.*', DB::raw('SUM(servers.players) as total_players'))
                ->whereRaw('title LIKE ?', ['%' . htmlspecialchars($data['search']) . '%'])
                ->whereRaw("additional->>'$.featured' = true")
                ->whereRaw("additional->>'$.version' = '2016'")
                ->where('asset_type', 9)
                ->groupBy('assets.id')
                ->orderByDesc('total_players')
                ->limit($results_per_page)
                ->offset($offset);
        } elseif($data['type'] == 'all' && $data['version'] == 'all' && $data['search'] != '') {
            $results = Asset::leftJoin('servers', 'assets.id', '=', 'servers.placeid')
                ->select('assets.*', DB::raw('SUM(servers.players) as total_players'))
                ->whereRaw('title LIKE ?', ['%' . htmlspecialchars($data['search']) . '%'])
                ->where('asset_type', 9)
                ->groupBy('assets.id')
                ->orderByDesc('total_players')
                ->limit($results_per_page)
                ->offset($offset);
        } elseif($data['type'] == 'all' && $data['version'] == '2012' && $data['search'] != '') {
            $results = Asset::leftJoin('servers', 'assets.id', '=', 'servers.placeid')
                ->select('assets.*', DB::raw('SUM(servers.players) as total_players'))
                ->whereRaw('title LIKE ?', ['%' . htmlspecialchars($data['search']) . '%'])
                ->whereRaw("additional->>'$.version' = '2012'")
                ->where('asset_type', 9)
                ->groupBy('assets.id')
                ->orderByDesc('total_players')
                ->limit($results_per_page)
                ->offset($offset);
        } elseif($data['type'] == 'all' && $data['version'] == '2016' && $data['search'] != '') {
            $results = Asset::leftJoin('servers', 'assets.id', '=', 'servers.placeid')
                ->select('assets.*', DB::raw('SUM(servers.players) as total_players'))
                ->whereRaw('title LIKE ?', ['%' . htmlspecialchars($data['search']) . '%'])
                ->whereRaw("additional->>'$.version' = '2016'")
                ->where('asset_type', 9)
                ->groupBy('assets.id')
                ->orderByDesc('total_players')
                ->limit($results_per_page)
                ->offset($offset);
        }

        if(!isset($results)) {
            $this->response['code'] = 400;
            $this->response['message'] = 'Bad request';

            return response()->json($this->response, 400);
        }

        $results = $results->get()->map(fn($item) => $item->toArray());
        
        foreach($results as $result) {
            $players = 0;

            $servers = Server::select('players')
                ->where('placeid', $result['id'])
                ->get()
                ->map(fn($item) => $item->toArray());
            
            foreach($servers as $server) {
                $players += count($server['players']);
            }

            $result['author'] = htmlspecialchars(User::where('id', $result['author'])->value('username'));
            $result['thumbnail'] = Cache::remember('thumbnail_' . $result['additional']['media']['imageAssetId'], 60 * 60, fn() => Asset::find($result['additional']['media']['imageAssetId'])->value('file'));

            $html = '
                <div data-v-5ad0ed22="" title="' . htmlspecialchars($result['title']) . '" class="game-card-div">
                    <a data-v-5ad0ed22="" href="/place/' . $result['id'] . '" class="game-card-link">
                        <span data-v-5ad0ed22="" class="game-card d-flex flex-column" ' . (Auth::user()->toArray()['theme'] == 1 ? 'style="background-color: #35383c;' : '') . '">
                            ' . ($result['additional']['version'] == "2016" ? '<span data-v-5ad0ed22="" class="badge badge-danger position-absolute">2016</span>' : '') . '
                            <span data-v-5ad0ed22="" class="thumbnail">
                                <div data-v-5ad0ed22="" class="vue-load-image"><img data-v-5ad0ed22="" src="https://cdn.finobe.net/' . $result['thumbnail'] . '" class="card-img-top"></div>
                            </span>
                            <span data-v-5ad0ed22="" class="data">
                                <p data-v-5ad0ed22="" class="catalog-no-overflow-plz">' . htmlspecialchars($result['title']) . '</p>
                                <p data-v-5ad0ed22="" class="catalog-no-overflow-plz author">by ' . htmlspecialchars($result['author']) . '</p>
                                <p data-v-5ad0ed22="" class="catalog-no-overflow-plz text-muted">' . $players . ' online</p>
                            </span>
                            <p data-v-5ad0ed22="" class="catalog-no-overflow-plz text-muted mb-0 visits"><small data-v-5ad0ed22="">' . number_format($result['additional']['visits']) . ' visits</small></p>
                        </span>
                    </a>
                </div>';
            
            $this->response['data'][] = $html;
        }

        if(!count($results)) {
            $html = '
                <div data-v-4350f98c="">
                  <div data-v-4350f98c="">
                      <div data-v-28f94fdd="" data-v-4350f98c="" class="finobe__vloader">
                        <img src="/s/img/pensive.svg"></span> 
                        <h3 data-v-28f94fdd="" class="mt-2 text-center">No places found.</h3>
                      </div>
                  </div>
                </div>';
            $this->response['data'][] = $html;
        }

        $total_items = Asset::where('asset_type', 9)->count();
        $total_pages = ceil($total_items / $results_per_page);

        $this->response['info']['pages'] = $total_pages;
        $this->response['info']['current_page'] = $currentPage;
        
        return response()->json($this->response, 200);
    }
}
