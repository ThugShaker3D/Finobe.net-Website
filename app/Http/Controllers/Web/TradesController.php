<?php

namespace App\Http\Controllers\Web;

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
use App\Models\Warning;
use App\Models\Forum\Reply;
use App\Models\Notification;
use App\Models\Forum\Rating;
use App\Models\Forum\Thread;
use App\Models\Forum\Subscription;
use App\Http\Controllers\frontEnd;
use App\Http\Controllers\Controller;
use App\Http\Controllers\dataController;

class TradesController extends Controller
{
    protected $request;

    public function __construct(Request $request) {
        $frontend = new frontEnd($request);
        $this->request = $frontend->getData();
    }

    public function trades() {
        $this->request['data']['embeds']['title'] = 'Trades' . $this->request['data']['embeds']['title'];

        if(!$this->request['data']['siteusername']) {
            return redirect('/');
        }

        return view($this->request['data']['user']['version'] . '/Trades', $this->request);
    }
}