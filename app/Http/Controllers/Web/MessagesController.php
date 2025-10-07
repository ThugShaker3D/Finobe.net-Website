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
use App\Models\Message;
use App\Models\Forum\Reply;
use App\Models\Notification;
use App\Models\Forum\Rating;
use App\Models\Forum\Thread;
use App\Models\Forum\Subscription;
use App\Http\Controllers\frontEnd;
use App\Http\Controllers\Controller;
use App\Http\Controllers\dataController;

class MessagesController extends Controller
{
    protected $request;

    public function __construct(Request $request) {
        $frontend = new frontEnd($request);
        $this->request = $frontend->getData();
    }

    public function inbox(Request $request) {
        $this->request['data']['embeds']['title'] = 'Inbox' . $this->request['data']['embeds']['title'];
        $this->request['data']['section'] = 'inbox';
        $data = $request->all();

        if(!$this->request['data']['siteusername']) {
            return redirect('/');
        }

        $this->request['data']['messages'] = [
            'data' => [],
            'page' => 1,
            'number_of_pages' => 1
        ];

        $messages = Message::where('touser', $this->request['data']['user']['id'])
            ->where('archived', 'n')
            ->count();

        $results_per_page = 12;
        $number_of_pages = ceil($messages / $results_per_page);
        $currentPage = isset($data['page']) ? max(1, intval($data['page'])) : 1;
        $offset = ($currentPage - 1) * $results_per_page;

        $messages = Message::where('touser', $this->request['data']['user']['id'])
            ->where('archived', 'n')
            ->orderBy('date', 'DESC')
            ->offset($offset)
            ->limit($results_per_page)
            ->get()
            ->map(fn($item) => $item->toArray());
        
        foreach($messages as $message) {
            $message['message'] = strip_tags(htmlspecialchars($message['message']));
            $message['subject'] = strip_tags(htmlspecialchars($message['subject']));
            $message['author'] = htmlspecialchars(User::where('id', $message['author'])->value('username'));
            $message['date'] = date('M j, Y | g:i A', strtotime($message['date']));
            $this->request['data']['messages']['data'][] = $message;
        }

        $this->request['data']['messages']['page'] = $currentPage;
        $this->request['data']['messages']['number_of_pages'] = $number_of_pages;

        return view($this->request['data']['user']['version'] . '/Inbox/Index', $this->request);
    }

    public function sent(Request $request) {
        $this->request['data']['embeds']['title'] = 'Sent Messages' . $this->request['data']['embeds']['title'];
        $this->request['data']['section'] = 'sent';
        $data = $request->all();

        if(!$this->request['data']['siteusername']) {
            return redirect('/');
        }

        $this->request['data']['messages'] = [
            'data' => [],
            'page' => 1,
            'number_of_pages' => 1
        ];

        $messages = Message::where('author', $this->request['data']['user']['id'])
            ->where('archived', 'n')
            ->count();

        $results_per_page = 12;
        $number_of_pages = ceil($messages / $results_per_page);
        $currentPage = isset($data['page']) ? max(1, intval($data['page'])) : 1;
        $offset = ($currentPage - 1) * $results_per_page;

        $messages = Message::where('author', $this->request['data']['user']['id'])
            ->where('archived', 'n')
            ->orderBy('date', 'DESC')
            ->offset($offset)
            ->limit($results_per_page)
            ->get()
            ->map(function ($item) {
                return (array) $item;
            })->toArray();
        
        foreach($messages as $message) {
            $message['message'] = strip_tags(htmlspecialchars($message['message']));
            $message['subject'] = strip_tags(htmlspecialchars($message['subject']));
            $message['author'] = htmlspecialchars(User::where('id', $message['author'])->value('username'));
            $message['date'] = date('M j, Y | g:i A', strtotime($message['date']));
            $this->request['data']['messages']['data'][] = $message;
        }
        
        $this->request['data']['messages']['page'] = $currentPage;
        $this->request['data']['messages']['number_of_pages'] = $number_of_pages;

        return view($this->request['data']['user']['version'] . '/Inbox/Index', $this->request);
    }

    public function archive(Request $request) {
        $this->request['data']['embeds']['title'] = 'Archived Messages' . $this->request['data']['embeds']['title'];
        $this->request['data']['section'] = 'archive';
        $data = $request->all();

        if(!$this->request['data']['siteusername']) {
            return redirect('/');
        }

        $this->request['data']['messages'] = [
            'data' => [],
            'page' => 1,
            'number_of_pages' => 1
        ];

        $messages = Message::where('touser', $this->request['data']['user']['id'])
            ->where('archived', 'y')
            ->count();

        $results_per_page = 12;
        $number_of_pages = ceil($messages / $results_per_page);
        $currentPage = isset($data['page']) ? max(1, intval($data['page'])) : 1;
        $offset = ($currentPage - 1) * $results_per_page;

        $messages = Message::where('touser', $this->request['data']['user']['id'])
            ->where('archived', 'y')
            ->orderBy('date', 'DESC')
            ->offset($offset)
            ->limit($results_per_page)
            ->get()
            ->map(fn($item) => $item->toArray());
        
        foreach($messages as $message) {
            $message['message'] = strip_tags(htmlspecialchars($message['message']));
            $message['subject'] = strip_tags(htmlspecialchars($message['subject']));
            $message['author'] = htmlspecialchars(User::where('id', $message['author'])->value('username'));
            $message['date'] = date('M j, Y | g:i A', strtotime($message['date']));
            $this->request['data']['messages']['data'][] = $message;
        }
        
        $this->request['data']['messages']['page'] = $currentPage;
        $this->request['data']['messages']['number_of_pages'] = $number_of_pages;

        return view($this->request['data']['user']['version'] . '/Inbox/Index', $this->request);
    }

    public function message(Request $request) {
        $data = $request->all();

        if(!$this->request['data']['siteusername']) {
            return redirect('/');
        }

        if(!isset($data['id'])) {
            return redirect('/app/inbox');
        }

        if(!Message::find($data['id'])) {
            Session::put('error', 'Message not found');
            return redirect('/app/inbox');
        }

        $message = Message::find($data['id'])->toArray();

        if($this->request['data']['user']['id'] != $message['author'] && $this->request['data']['user']['id'] != $message['touser']) {
            Session::put('error', 'You are not mentioned in this message');
            return redirect('/app/inbox');
        }

        if($request->isMethod('post')) {
            if($message['archived'] == 'n') {
                $message2 = Message::find($data['id']);
                $message2->archived = 'y';
                $message2->save();
                
                Session::put('success', 'Successfully archived');
            } else {
                $message2 = Message::find($data['id']);
                $message2->archived = 'n';
                $message2->save();
                
                Session::put('success', 'Successfully unarchived');
            }

            return redirect('/app/inbox/message?id=' . $data['id']);
        }

        $message['uid'] = $message['author'];
        $message['message'] = nl2br(preg_replace('/\b((?:https?|ftp):\/\/\S+)/i', '<a href="$1" target="_blank">$1</a>', strip_tags(htmlspecialchars($message['message']))));
        $message['subject'] = strip_tags(htmlspecialchars($message['subject']));
        $message['author'] = htmlspecialchars(User::where('id', $message['author'])->value('username'));
        $message['date'] = date('M j, g:ia', strtotime($message['date']));

        if($message['readed'] == 'n' && $this->request['data']['user']['id'] != $message['uid']) {
            $message2 = Message::find($message['id']);
            $message2->readed = 'y';
            $message2->save();
        }

        $this->request['data']['embeds']['title'] = $message['subject'] . $this->request['data']['embeds']['title'];
        $this->request['data']['message'] = $message;

        return view($this->request['data']['user']['version'] . '/Inbox/Message', $this->request);
    }

    public function compose(Request $request) {
        $this->request['data']['embeds']['title'] = 'Compose Messages' . $this->request['data']['embeds']['title'];
        $data = $request->all();

        if(!$this->request['data']['siteusername']) {
            return redirect('/');
        }

        if($request->isMethod('post')) {
            $validator = Validator::make($data, [
                'username' => 'required|string|exists:finobe.users,username',
                'subject' => 'required|string|min:3|max:255',
                'message' => 'required|string|min:3|max:8192'
            ]);

            if($validator->fails()) {
                Session::put('error', $validator->errors()->first());
                return redirect('/app/inbox/compose');
            }

            if(Message::where('author', $this->request['data']['user']['id'])->where('date', '>=', DB::raw('NOW() - INTERVAL 5 MINUTE'))->exists()) {
                Session::put('error', 'Wait 5 minutes before sending another message');
                return redirect('/app/inbox/compose');
            }

            if($this->request['data']['user']['username'] == $data['username']) {
                Session::put('error', 'You cannot send a message to yourself');
                return redirect('/app/inbox/compose');
            }

            Message::create([
                'author' => $this->request['data']['user']['id'],
                'touser' => User::where('username', $data['username'])->value('id'),
                'subject' => $data['subject'],
                'message' => $data['message']
            ]);

            Session::put('success', 'Successfully send');
            return redirect('/app/inbox');
        }

        if(isset($data['user'])) {
            if(!User::where('username', $data['user'])->exists()) {
                Session::put('error', 'User not found');
                return redirect('/app/inbox/compose');
            }

            $this->request['data']['sendto'] = [
                'id' => User::where('username', $data['user'])->value('id'),
                'username' => $data['user']
            ];
        }

        if(isset($data['subject'])) {
            $this->request['data']['subject'] = $data['subject'];
        }

        return view($this->request['data']['user']['version'] . '/Inbox/Compose', $this->request);
    }
}