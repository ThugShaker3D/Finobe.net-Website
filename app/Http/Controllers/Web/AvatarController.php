<?php

namespace App\Http\Controllers\Web;

use App\Models\Purchases;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Validator;
use League\CommonMark\MarkdownConverter;
use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use App\Models\Ban;
use App\Models\User;
use App\Models\Asset;
use App\Models\Forum\Reply;
use App\Models\Notification;
use App\Models\Forum\Rating;
use App\Models\Forum\Thread;
use App\Models\Forum\Subscription;
use App\Http\Controllers\frontEnd;
use App\Http\Controllers\Controller;
use App\Http\Controllers\dataController;

class AvatarController extends Controller
{
    protected $request;

    public function __construct(Request $request) {
        $frontend = new frontEnd($request);
        $this->request = $frontend->getData();
    }

    public function index() {
        $this->request['data']['embeds']['title'] = 'Character' . $this->request['data']['embeds']['title'];

        if(!$this->request['data']['siteusername']) {
            return redirect('/');
        }

        $purchases = [];
        $results = Purchases::where('username', $this->request['data']['user']['username'])
            ->where('type', 1)
            ->orderBy('id', 'DESC')
            ->get()
            ->map(fn($item) => $item->toArray());
        
        foreach($results as $result) {
            if(!Asset::find($result['assetid'])) {
                continue;
            }
            
            $item = Asset::find($result['assetid'])->toArray();

            if(!in_array($item['asset_type'], [2, 8, 11, 12, 18, 19])) {
                continue;
            }

            $item['additional'] = json_decode($item['additional'], true);
            $result['asset_type'] = $item['asset_type'];
            $result['visibility'] = $item['visibility'];

            if($item['visibility'] == 'd') {
                $item['title'] = '[Not Approved]';
            }

            $result['title'] = htmlspecialchars($item['title']);
            $result['uuid'] = $item['author'];
            $result['author'] = User::where('id', $item['author'])->value('username');
            $result['equipped'] = in_array($result['assetid'], $this->request['data']['user']['avatar'][0]['equippedGearVersionIds']);

            if(in_array($item['asset_type'], [2, 8, 11, 12, 18, 19])) {
                $result['thumbnail'] = $item['additional']['media']['thumbnail'];
            }

            $purchases[] = $result;
        }

        $this->request['data']['purchases'] = $purchases;
        
        return view($this->request['data']['user']['version'] . '/Character', $this->request);
    }
}