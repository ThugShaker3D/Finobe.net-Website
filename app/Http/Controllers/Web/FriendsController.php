<?php

namespace App\Http\Controllers\Web;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Session;
use App\Models\User;
use App\Http\Controllers\frontEnd;
use App\Http\Controllers\Controller;
use App\Http\Controllers\dataController;

class FriendsController extends Controller
{
    protected $request;

    public function __construct(Request $request) {
        $frontend = new frontEnd($request);
        $this->request = $frontend->getData();
    }

    public function add(Request $request, $id) {
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
        
        if($user->id == $this->request['data']['user']['id']) {
            return redirect('/user/' . $id);
        }

        foreach($user->friends as $friend) {
            if($friend['userid'] == $this->request['data']['user']['id']) {
                return redirect('/user/' . $id);
            }
        }

        $friends = $user->friends;
        $friends[] = [
            'userid' => $this->request['data']['user']['id'],
            'status' => 'pending'
        ];

        $user->friends = json_encode($friends, JSON_FORCE_OBJECT);
        $user->save();

        return redirect('/user/' . $id);
    }

    public function accept(Request $request, $id) {
        $data = $request->all();

        if(!$this->request['data']['siteusername']) {
            return redirect('/');
        }

        if(!User::where('id', $id)->exists()) {
            Session::put('error', 'User does not exist');
            return redirect('/');
        }

        if(empty(array_filter($this->request['data']['user']['friends'], fn($entry) => $entry['userid'] == $id))) {
            if(isset($data['feature'])) {
                return redirect('/friends/incoming');
            }
            
            return redirect('/user/' . $id);
        }

        $user = User::find($id);
        $user->friends = json_decode($user->friends, true);
        
        if($user->id == $this->request['data']['user']['id']) {
            if(isset($data['feature'])) {
                return redirect('/friends/incoming');
            }
            
            return redirect('/user/' . $id);
        }

        foreach($user->friends as $friend) {
            if($friend['userid'] == $this->request['data']['user']['id']) {
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
            'userid' => $this->request['data']['user']['id'],
            'status' => 'friends'
        ];

        $user->friends = json_encode($friends, JSON_FORCE_OBJECT);
        $user->save();

        foreach($this->request['data']['user']['friends'] as $key => $friend) {
            if($friend['userid'] == $user->id) {
                $this->request['data']['user']['friends'][$key]['status'] = 'friends';
                break;
            }
        }

        User::where('id', $this->request['data']['user']['id'])->update([
            'friends' => json_encode($this->request['data']['user']['friends'], JSON_FORCE_OBJECT)
        ]);

        if(isset($data['feature'])) {
            return redirect('/friends/incoming');
        }
        
        return redirect('/user/' . $id);
    }

    public function remove(Request $request, $id) {
        $data = $request->all();

        if(!$this->request['data']['siteusername']) {
            return redirect('/');
        }

        if(!User::where('id', $id)->exists()) {
            Session::put('error', 'User does not exist');
            return redirect('/');
        }

        if(empty(array_filter($this->request['data']['user']['friends'], fn($entry) => $entry['userid'] == $id))) {
            if(isset($data['feature'])) {
                return redirect('/friends/incoming');
            }
            
            return redirect('/user/' . $id);
        }

        $user = User::find($id);
        $user->friends = json_decode($user->friends, true);
        
        if($user->id == $this->request['data']['user']['id']) {
            if(isset($data['feature'])) {
                return redirect('/friends/incoming');
            }
            
            return redirect('/user/' . $id);
        }

        $friends = $user->friends;

        foreach($friends as $key => $friend) {
            if($friend['userid'] == $this->request['data']['user']['id']) {
                unset($friends[$key]);
                break;
            }
        }

        $user->friends = json_encode($friends, JSON_FORCE_OBJECT);
        $user->save();

        foreach($this->request['data']['user']['friends'] as $key => $friend) {
            if($friend['userid'] == $user->id) {
                unset($this->request['data']['user']['friends'][$key]);
                break;
            }
        }

        User::where('id', $this->request['data']['user']['id'])->update([
            'friends' => json_encode($this->request['data']['user']['friends'], JSON_FORCE_OBJECT)
        ]);

        if(isset($data['feature'])) {
            return redirect('/friends/incoming');
        }
        
        return redirect('/user/' . $id);
    }

    public function list(Request $request, $id) {
        $data = $request->all();

        if(!$this->request['data']['siteusername']) {
            return redirect('/');
        }

        if(!User::where('id', $id)->exists()) {
            abort(404);
        }

        $user = User::find($id)->toArray();
        $user['friends'] = array_reverse(array_filter(json_decode($user['friends'], true), fn($friend) => $friend['status'] == 'friends'));

        foreach($user['friends'] as $key => $friend) {
            $user['friends'][$key]['username'] = Cache::remember('username_' . $friend['userid'], 60 * 60, fn() => User::where('id', $friend['userid'])->value('username'));
            $user['friends'][$key]['pfp'] = Cache::remember('pfp_' . $friend['userid'], 60 * 60, fn() => User::where('id', $friend['userid'])->value('pfp'));
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

    public function incoming(Request $request) {
        $this->request['data']['embeds']['title'] = 'Friends Incoming' . $this->request['data']['embeds']['title'];
        $data = $request->all();

        if(!$this->request['data']['siteusername']) {
            return redirect('/');
        }

        $this->request['data']['user']['friends'] = array_reverse(array_filter($this->request['data']['user']['friends'], fn($friend) => $friend['status'] == 'pending'));

        foreach($this->request['data']['user']['friends'] as $key => $friend) {
            $this->request['data']['user']['friends'][$key]['username'] = Cache::remember('username_' . $friend['userid'], 60 * 60, fn() => User::where('id', $friend['userid'])->value('username'));
            $this->request['data']['user']['friends'][$key]['pfp'] = Cache::remember('pfp_' . $friend['userid'], 60 * 60, fn() => User::where('id', $friend['userid'])->value('pfp'));
        }

        return view($this->request['data']['user']['version'] . '/User_friends_incoming', $this->request);
    }
}