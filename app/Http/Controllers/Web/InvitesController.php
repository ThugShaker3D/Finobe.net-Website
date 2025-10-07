<?php

namespace App\Http\Controllers\Web;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Validator;
use App\Models\Keys;
use App\Models\User;
use App\Http\Controllers\frontEnd;
use App\Http\Controllers\Controller;

class InvitesController extends Controller
{
    protected $request;

    public function __construct(Request $request) {
        $frontend = new frontEnd($request);
        $this->request = $frontend->getData();
    }

    public function index(Request $request) {
        $this->request['data']['embeds']['title'] = 'Invites' . $this->request['data']['embeds']['title'];

        if(!$this->request['data']['siteusername']) {
            return redirect('/');
        }

        $keys = [
            'data' => [],
            'info' => [
                'created' => Keys::where('author', $this->request['data']['user']['username'])->where(DB::raw('MONTH(creation)'), DB::raw('MONTH(NOW())'))->where(DB::raw('YEAR(creation)'), DB::raw('YEAR(NOW())'))->count()
            ]
        ];

        $inviteKeys = Keys::where('author', $this->request['data']['user']['username'])
            ->orderBy('creation', 'DESC')
            ->get()
            ->map(fn($item) => $item->toArray());
        
        foreach($inviteKeys as $key) {
            $key['creation'] = date('m/d/Y', strtotime($key['creation']));

            if($key['used'] == 'y') {
                $key['uuid'] = User::where('username', $key['usedBy'])->value('id');
                $key['dateUsed'] = date('m/d/Y', strtotime($key['dateUsed']));
            }

            $keys['data'][] = $key;
        }

        $this->request['data']['keys'] = $keys;

        return view($this->request['data']['user']['version'] . '/Invitations/Invites', $this->request);
    }

    public function new(Request $request) {
        $this->request['data']['embeds']['title'] = 'Invites' . $this->request['data']['embeds']['title'];
        $data = $request->all();

        if(!$this->request['data']['siteusername']) {
            return redirect('/');
        }

        if($request->isMethod('post')) {
            if(!(bool)env('FINOBE_INVITE_KEYS')) {
                Session::put('error', 'Invite keys are disabled');
                return redirect('/invites');
            }

            if(!(bool)env('FINOBE_CREATE_INVITE_KEYS')) {
                Session::put('error', 'Invite key creation is disabled');
                return redirect('/invites');
            }

            if(Keys::where('author', $this->request['data']['user']['username'])->where(DB::raw('MONTH(creation)'), DB::raw('MONTH(NOW())'))->where(DB::raw('YEAR(creation)'), DB::raw('YEAR(NOW())'))->count() - 2 >= 0) {
                Session::put('error', 'You cannot create any more invites this month');
                return redirect('/invites');
            }

            if(Keys::where('author', $this->request['data']['user']['username'])->where('used', 'n')->count() >= 2) {
                Session::put('error', 'You have too many unused keys');
                return redirect('/invites');
            }

            function inviteKey($length) {
                $characters = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
                $randomString = '';
            
                $maxIndex = strlen($characters) - 1;
            
                for($i = 0; $i < $length; $i++) {
                    $randomString .= $characters[random_int(0, $maxIndex)];
                }
            
                return $randomString;
            }

            $key = inviteKey(32);

            Keys::create([
                'author' => $this->request['data']['user']['username'],
                'IID' => $key
            ]);

            Session::put('successv2', 'Invite key created: ' . $key);
            return redirect('/invites');
        }

        return view($this->request['data']['user']['version'] . '/Invitations/Confirmation', $this->request);
    }
}