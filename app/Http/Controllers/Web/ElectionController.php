<?php

namespace App\Http\Controllers\Web;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Validator;
use App\Models\User;
use App\Models\Election;
use App\Http\Controllers\frontEnd;
use App\Http\Controllers\Controller;

class ElectionController extends Controller
{
    protected $request;

    public function __construct(Request $request) {
        $frontend = new frontEnd($request);
        $this->request = $frontend->getData();
    }

    public function election(Request $request) {
        $this->request['data']['embeds']['title'] = 'Invites' . $this->request['data']['embeds']['title'];
        $data = $request->all();

        if(!$this->request['data']['siteusername']) {
            return redirect('/');
        }

        if(!Election::where('expire', '>', now())->exists()) {
            Session::put('error', 'There is currently no active elections');
            return redirect('/');
        }

        $election = Election::where('expire', '>', now())->first()->toArray();
        
        $election['title'] = strip_tags(htmlspecialchars($election['title']));

        if($request->isMethod('post')) {
            if(!isset($data['index'])) {
                return redirect('/election');
            }

            foreach($election['votes'] as $key => $vote) {
                if($vote['id'] != $data['index'] && $vote['username'] == $this->request['data']['user']['username']) {
                    Session::put('error', 'You can only vote on one option');
                    return redirect('/election');
                }
            }

            foreach($election['votes'] as $key => $vote) {
                if($vote['id'] == $data['index'] && $vote['username'] == $this->request['data']['user']['username']) {
                    unset($election['votes'][$key]);

                    $election2 = Election::find($election['id']);
                    $election2->votes = $election['votes'];
                    $election2->save();
                    
                    return redirect('/election');
                }
            }

            $election['votes'][] = [
                'id' => $data['index'],
                'username' => $this->request['data']['user']['username']
            ];

            $election2 = Election::find($election['id']);
            $election2->votes = $election['votes'];
            $election2->save();
                    
            return redirect('/election');
        }

        $this->request['data']['election'] = $election;

        return view($this->request['data']['user']['version'] . '/Election', $this->request);
    }
}