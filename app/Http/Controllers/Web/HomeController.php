<?php

namespace App\Http\Controllers\Web;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Session;
use App\Models\Ban;
use App\Models\User;
use App\Models\Asset;
use App\Models\Server;
use App\Http\Controllers\frontEnd;
use App\Http\Controllers\Controller;
use App\Http\Controllers\dataController;

class HomeController extends Controller
{
    protected $request;

    public function __construct(dataController $dataService, Request $request) {
        $frontend = new frontEnd($dataService, $request);
        $this->request = $frontend->getData();
    }

    public function index(Request $request) {
        $this->request['data']['embeds']['title'] = 'Home' . $this->request['data']['embeds']['title'];

        if($request->isMethod('post')) {
            if(!$this->request['data']['siteusername']) {
                return redirect('/');
            }

            if(Ban::where('username', $this->request['data']['user']['username'])->where('expire', '<', DB::raw('now()'))->where('reactivated', 'n')->exists()) {
                $ban = (array) Ban::where('username', $this->request['data']['user']['username'])
                    ->where('expire', '<', DB::raw('now()'))
                    ->where('reactivated', 'n')
                    ->first();
                
                if($ban['perm'] == 'y') {
                    return redirect('/');
                }

                if(Carbon::parse($ban['expire'])->lt(now())) {
                    Ban::where('username', $this->request['data']['user']['username'])
                        ->where('reactivated', 'n')
                        ->update([
                            'reactivated' => 'y'
                        ]);
                } else {
                    Session::put('error', 'This activity has been logged and your ban may be extended');
                    return redirect('/');
                }
            }

            if(Ban::where('username', $this->request['data']['user']['username'])->where('reactivated', 'n')->exists()) {
                Ban::where('username', $this->request['data']['user']['username'])
                    ->where('reactivated', 'n')
                    ->update([
                        'reactivated' => 'y'
                    ]);
            }

            Session::put('successv2', 'Your moderation action has been lifted.');
            return redirect('/');
        }

        if($this->request['data']['siteusername']) {
            $this->request['data']['games'] = [];

            $games = Cache::remember('latest_places', 60 * 10, function() {
                return Asset::select('assets.*', DB::raw('SUM(servers.players) AS total_players'))
                    ->leftJoin('servers', 'assets.id', '=', 'servers.placeid')
                    ->where('asset_type', 9)
                    ->groupBy('assets.id')
                    ->orderByDesc('total_players')
                    ->limit(6)
                    ->get()
                    ->map(fn($item) => $item->toArray())->toArray();
            });
            
            foreach($games as $key => $game) {
                $game['additional'] = json_decode($game['additional'], true);
                $players = 0;

                $servers = Server::select('players')
                    ->where('placeid', $game['id'])
                    ->get()
                    ->map(fn($item) => $item->toArray())->toArray();
                
                foreach($servers as $server) {
                    $players += count(json_decode($server['players']));
                }

                $thumbnail = Asset::select('file')
                    ->where('id', $game['additional']['media']['imageAssetId'])
                    ->value('file');
                
                $this->request['data']['games'][] = [
                    'id' => $game['id'],
                    'title' => $game['title'],
                    'author' => User::find($game['author'])->value('username'),
                    'thumbnail' => $thumbnail,
                    'visits' => number_format($game['additional']['visits']),
                    'version' => $game['additional']['version'],
                    'players' => $players
                ];
            }
        }

        return view($this->request['data']['user']['version'] . '/Landing', $this->request);
    }
}