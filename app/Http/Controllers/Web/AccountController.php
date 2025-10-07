<?php

namespace App\Http\Controllers\Web;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Validator;
use App\Models\User;
use App\Models\Purchases;
use App\Models\EmailVerify;
use App\Models\ResetPassword;
use App\Http\Controllers\frontEnd;
use App\Http\Controllers\Controller;

class AccountController extends Controller
{
    protected $request;

    public function __construct(Request $request) {
        $frontend = new frontEnd($request);
        $this->request = $frontend->getData();
    }

    public function verify_email(Request $request) {
        if(!$this->request['data']['siteusername']) {
            return redirect('/');
        }

        if(EmailVerify::where('username', $this->request['data']['user']['username'])->where('used', 'n')->exists()) {
            return redirect('/');
        }

        $verifyid = hash_hmac('sha256', rand(0, 10000), 'privatekey');

        $html = file_get_contents(storage_path('verify_email_template.php'));
        $keywords = ['UUID', 'SIGNATURE', 'RESETID'];
	    $replacementValues = [$this->request['data']['user']['id'], hash_hmac('sha256', 'testingthis', 'privatekey'), $verifyid];
        $html = str_replace($keywords, $replacementValues, $html);

        if((int)env('FINOBE_MAIL_MODE') == 1) {
            $response = Http::withHeaders([
                'accept' => 'application/json',
                'authorization' => 'Zoho-enczapikey ' . env('FINOBE_ZOHO_API_KEY'),
                'cache-control' => 'no-cache',
                'content-type' => 'application/json',
            ])->post('https://api.zeptomail.com/v1.1/email', [
                "from" => [
                    "address" => "noreply@aesthetiful.com"
                ],
                "to" => [
                    [
                        "email_address" => [
                            "address" => $this->request['data']['user']['email'],
                            "name" => $this->request['data']['user']['username']
                        ]
                    ]
                ],
                "subject" => "Verify Email Address",
                "htmlbody" => $html
            ]);

            if(!$response->successful()) {
                Session::put('error', 'There was an error while sending the email, please try again. (this is most likely a issue with our backend system)');
                return redirect('/');
            }
        } elseif((int)env('FINOBE_MAIL_MODE') == 2) {
            $response = Http::withHeaders([
                'accept' => 'application/json',
                'X-Smtp2go-Api-Key' => env('FINOBE_SMTP2GO_API_KEY'),
                'content-type' => 'application/json',
            ])->post('https://us-api.smtp2go.com/v3/email/send', [
                "sender" => "Finobe <noreply@aesthetiful.com>",
                "to" => $this->request['data']['user']['username'] . " <" . $this->request['data']['user']['email'] . ">",
                "subject" => "Verify Email Address",
                "html_body" => $html
            ]);

            if(!$response->successful() || !$response->json()['data']['succeeded']) {
                Session::put('error', 'There was an error while sending the email, please try again.');
                return redirect('/');
            }
        }

        EmailVerify::create([
            'username' => $this->request['data']['user']['username'],
            'uid' => $verifyid
        ]);

        Session::put('success', 'Sent.');
        return redirect('/');
    }

    public function email_verify(Request $request, $id, $verifyid) {
        if(!User::find($id)) {
            Session::put('error', 'Unknown error');
            return redirect('/');
        }

        $user = User::find($id);

        if(!EmailVerify::where('username', $user->username)->where('uid', $verifyid)->where('used', 'n')->exists()) {
            Session::put('error', 'Session not found');
            return redirect('/');
        }

        $email = EmailVerify::where('username', $user->username)
            ->where('uid', $verifyid)
            ->where('used', 'n');
        $email->used = 'y';
        $email->save();
        
        $user->verified = 'y';
        $user->save();

        Session::put('success', 'Verified email');
        return redirect('/');
    }

    public function password_reset() {
        $this->request['data']['embeds']['title'] = 'Reset Password' . $this->request['data']['embeds']['title'];

        if($this->request['data']['siteusername']) {
            return redirect('/');
        }

        return view($this->request['data']['user']['version'] . '/Reset', $this->request);
    }

    public function password_email(Request $request) {
        $data = $request->all();

        if($this->request['data']['siteusername']) {
            return redirect('/');
        }

        $validator = Validator::make($data, [
            'email' => 'required|email'
        ]);

        if($validator->fails() || !User::where('email', $data['email'])->exists()) {
            return redirect('/password/reset');
        }

        $user = User::where('email', $data['email'])->first();

        if(ResetPassword::where('username', $user->username)->where('used', 'n')->exists()) {
            return redirect('/password/reset');
        }

        $session = ResetPassword::create([
            'username' => $user->username,
            'uid' => ''
        ]);

        $resetid = hash_hmac('sha256', $session->id, 'privatekey');
        $session->uid = $resetid;
        $session->save();

        $html = file_get_contents(storage_path('reset_password_template.php'));
        $keywords = ['UUID', 'SIGNATURE', 'RESETID'];
	    $replacementValues = [$user->id, hash_hmac('sha256', 'testingthis', 'privatekey'), $resetid];
        $html = str_replace($keywords, $replacementValues, $html);

        if((int)env('FINOBE_MAIL_MODE') == 1) {
            $response = Http::withHeaders([
                'accept' => 'application/json',
                'authorization' => 'Zoho-enczapikey ' . env('FINOBE_ZOHO_API_KEY'),
                'cache-control' => 'no-cache',
                'content-type' => 'application/json',
            ])->post('https://api.zeptomail.com/v1.1/email', [
                "from" => [
                    "address" => "noreply@aesthetiful.com"
                ],
                "to" => [
                    [
                        "email_address" => [
                            "address" => $data['email'],
                            "name" => $user->username
                        ]
                    ]
                ],
                "subject" => "Finobe Password Reset",
                "htmlbody" => $html
            ]);

            if(!$response->successful()) {
                $session->delete();
                
                Session::put('error', 'There was an error while sending the email, please try again.');
                return redirect('/');
            }
        } elseif((int)env('FINOBE_MAIL_MODE') == 2) {
            $response = Http::withHeaders([
                'accept' => 'application/json',
                'X-Smtp2go-Api-Key' => env('FINOBE_SMTP2GO_API_KEY'),
                'content-type' => 'application/json',
            ])->post('https://api.smtp2go.com/v3/email/send', [
                "sender" => "Finobe <noreply@aesthetiful.com>",
                "to" => $user->username . " <" . $data['email'] . ">",
                "subject" => "Finobe Password Reset",
                "html_body" => $html
            ]);

            if(!$response->successful()) {
                $session->delete();
                
                Session::put('error', 'There was an error while sending the email, please try again.');
                return redirect('/');
            }
        }

        return redirect('/password/reset');
    }

    public function password_verify(Request $request, $id, $resetid) {
        $this->request['data']['embeds']['title'] = 'Reset Password' . $this->request['data']['embeds']['title'];
        $data = $request->all();

        if($this->request['data']['siteusername']) {
            return redirect('/');
        }

        if(!User::find($id)) {
            Session::put('error', 'User not found');
            return redirect('/password/reset');
        }

        $user = User::find($id);

        if(!ResetPassword::where('username', $user->username)->where('uid', $resetid)->where('used', 'n')->exists()) {
            Session::put('error', 'Session not found');
            return redirect('/password/reset');
        }

        $session = ResetPassword::where('username', $user->username)
            ->where('uid', $resetid)
            ->where('used', 'n')
            ->first();

        if(strcasecmp(hash_hmac('sha256', $session->id, 'privatekey'), $resetid) !== 0) {
            Session::put('error', 'Incorrect hash');
            return redirect('/');
        }

        if($request->isMethod('post')) {
            if(!isset($data['password'])) {
                return redirect('/password/reset');
            }

            $session->used = 'y';
            $session->save();
            
            $user->password = password_hash($data['password'], PASSWORD_BCRYPT);
            $user->save();

            Session::put('success', 'Successfully reset');
            return redirect('/');
        }

        $this->request['data']['reset'] = true;

        return view($this->request['data']['user']['version'] . '/Reset', $this->request);
    }

    public function settings(Request $request) {
        $this->request['data']['embeds']['title'] = 'Settings' . $this->request['data']['embeds']['title'];
        $data = $request->all();

        if(!$this->request['data']['siteusername']) {
            return redirect('/');
        }

        if($request->isMethod('post')) {
            if(isset($data['blurb']) && !$request->hasFile('file')) {
                $validator = Validator::make($data, [
                    'blurb' => 'required|string|max:8192'
                ]);

                if($validator->fails()) {
                    Session::put('error', $validator->errors()->first());
                    return redirect('/app/settings');
                }

                $user = User::find($this->request['data']['user']['id']);
                $user->blurb = $data['blurb'];
                $user->save();

                Session::put('success', 'Successfully updated.');
                return redirect('/app/settings');
            } elseif(isset($data['password']) && !$request->hasFile('file')) {
                $validator = Validator::make($data, [
                    'email' => 'required|email',
                    'password' => 'required|string|alpha_dash|unique:finobe.users,email'
                ]);

                if($validator->fails()) {
                    Session::put('error', $validator->errors()->first());
                    return redirect('/app/settings');
                }

                if(!Hash::check($data['password'], $this->request['data']['user']['password'])) {
                    Session::put('errorlogin', true);
                    return redirect('/auth/login');
                }

                $user = Auth::user();
                $user->email = $data['email'];
                $user->verified = 'n';
                $user->save();

                return redirect('/app/settings');
            } elseif($this->request['data']['user']['status'] == 'admin' && $request->hasFile('file')) {
                $validator = Validator::make($data, [
                    'file' => 'required|file|minetypes:image/png,image/jpg|max:10240'
                ]);

                if($validator->fails()) {
                    Session::put('error', $validator->errors()->first());
                    return redirect('/app/settings');
                }

                $file = $request->file('file');
                list($width, $height) = getimagesize($file->getPathname());
                $filename = uniqid() . '.' . $file->extension();

                if($width != $height) {
                    Session::put('error', 'Image needs to be 1:1 ratio');
                    return redirect('/app/settings');
                }

                $file->move('/var/www/cdn.finobe.net/avatar/', $filename);

                $user = User::find($this->request['data']['user']['id']);
                $user->pfp = $filename;
                $user->save();

                Session::put('success', 'Successfully updated.');
                return redirect('/app/settings');
            }

            return redirect('/app/settings');
        }

        return view($this->request['data']['user']['version'] . '/Settings/Index', $this->request);
    }

    public function theme(Request $request) {
        $this->request['data']['embeds']['title'] = 'Theme' . $this->request['data']['embeds']['title'];
        $data = $request->all();

        if(!$this->request['data']['siteusername']) {
            return redirect('/');
        }

        if($request->isMethod('post')) {
            if(isset($data['branding'])) {
                $validator = Validator::make($data, [
                    'branding' => 'required|string|in:aesthetiful,finobe'
                ]);

                if($validator->fails()) {
                    Session::put('error', $validator->errors()->first());
                    return redirect('/app/theme');
                }

                $user = User::find($this->request['data']['user']['id']);
                $user->branding = $data['branding'];
                $user->save();

                Session::put('successv2', 'Successfully updated.');
                return redirect('/app/' . ($user->version == 'v1' ? 'settings' : 'theme'));
            } elseif(isset($data['logo'])) {
                $validator = Validator::make($data, [
                    'logo' => 'required|string|in:v1,v2,v3'
                ]);

                if($validator->fails()) {
                    Session::put('error', $validator->errors()->first());
                    return redirect('/app/theme');
                }

                $user = User::find($this->request['data']['user']['id']);
                $user->logo = $data['logo'];
                $user->save();

                Session::put('successv2', 'Successfully updated.');
                return redirect('/app/' . ($user->version == 'v1' ? 'settings' : 'theme'));
            } elseif(isset($data['dark'])) {
                User::where('id', $this->request['data']['user']['id'])
                    ->update([
                        'theme' => DB::raw('CASE WHEN theme = 0 THEN 1 ELSE 0 END')
                    ]);
                
                Session::put('successv2', 'Successfully updated.');
                return redirect('/app/' . ($this->request['data']['user']['version'] == 'v1' ? 'settings' : 'theme'));
            } elseif(isset($data['gary'])) {
                User::where('id', $this->request['data']['user']['id'])
                    ->update([
                        'gary' => DB::raw('CASE WHEN gary = 0 THEN 1 ELSE 0 END')
                    ]);
                
                Session::put('successv2', 'Successfully updated.');
                return redirect('/app/' . ($this->request['data']['user']['version'] == 'v1' ? 'settings' : 'theme'));
            } elseif(isset($data['upsidedown'])) {
                User::where('id', $this->request['data']['user']['id'])
                    ->update([
                        'upsidedown' => DB::raw('CASE WHEN upsidedown = 0 THEN 1 ELSE 0 END')
                    ]);
                
                Session::put('successv2', 'Successfully updated.');
                return redirect('/app/' . ($this->request['data']['user']['version'] == 'v1' ? 'settings' : 'theme'));
            } elseif(isset($data['version'])) {
                $validator = Validator::make($data, [
                    'version' => 'required|string|in:v1,v2'
                ]);

                if($validator->fails()) {
                    Session::put('error', $validator->errors()->first());
                    return redirect('/app/theme');
                }

                User::where('id', $this->request['data']['user']['id'])
                    ->update([
                        'gary' => 0,
                        'upsidedown' => 0,
                        'theme' => 0,
                        'logo' => 'v1',
                        'version' => $data['version']
                    ]);
                
                Session::put('success', 'Successfully changed');
                return redirect('/app/' . ($this->request['data']['user']['version'] == 'v1' ? 'settings' : 'theme'));
            }

            return redirect('/app/theme');
        }

        return view($this->request['data']['user']['version'] . '/Settings/Theme', $this->request);
    }

    public function games(Request $request) {
        $this->request['data']['embeds']['title'] = 'Places' . $this->request['data']['embeds']['title'];
        $data = $request->all();

        if(!$this->request['data']['siteusername']) {
            return redirect('/');
        }

        if($request->isMethod('post')) {
            if($this->request['data']['user']['Dius'] < 625) {
                Session::put('error', 'You do not have enough Dius to purchase a place slot');
                return redirect('/app/games');
            }

            $user = User::find($this->request['data']['user']['id']);
            $user->Dius -= 625;
            $user->slots += 1;
            $user->save();

            Purchases::create([
                'username' => $user->username,
                'assetid' => 0,
                'author' => 1,
                'amount' => -625,
                'type' => 3
            ]);

            Session::put('successv2', 'Successfully purchased.');
            return redirect('/app/games');
        }

        return view($this->request['data']['user']['version'] . '/Settings/Places', $this->request);
    }

    public function connect(Request $request) {
        $this->request['data']['embeds']['title'] = 'Connecting eracast' . $this->request['data']['embeds']['title'];
        $data = $request->all();

        if($request->isMethod('post')) {
            $validator = Validator::make($data, [
                'userid' => 'required|integer',
                'username' => 'required|string'
            ]);

            if($validator->fails()) {
                Session::put('error', $validator->errors()->first());
                return redirect('/app/connect');
            }

            Http::withHeaders([
                'User-Agent' => 'finobe.net/Server 1.0'
            ])->get('https://api.eracast.cc/v1/update_aesthetifulplus_link', [
                'eracast_fiur3ui3uigu3itjuirjifs',
                'user' => $data['username'],
                'userid' => $this->request['data']['user']['id']
            ]);

            $user = User::find($this->request['data']['user']['id']);
            $user->eracast_link = $data['userid'];
            $user->save();

            Session::put('success', 'Successfully linked.');
            return redirect('/app/connect');
        }

        if(!isset($data['data'])) {
            return redirect('https://www.eracast.cc/signin?context=connect&next=&feature=aesthetifulplus');
        } else {
            function decryptData($data, $key) {
                $data = base64_decode(urldecode($data));
                $iv = substr($data, 0, 16);
                $encrypted = substr($data, 16);
                $decrypted = openssl_decrypt($encrypted, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
                parse_str($decrypted, $dataArray);
                return $dataArray;
            }

            $validator = Validator::make($data, [
                'data' => 'required|string'
            ]);

            if($validator->fails()) {
                Session::put('error', $validator->errors()->first());
                return redirect('/app/connect');
            }

            $decoded = decryptData($data['data'], 'connect_jfkhfbfbch');

            if(!isset($decoded['e_username'])) {
                Session::put('error', 'There was an error, please try again.');
                return redirect('/app/connect');
            }

            $response = Http::withHeaders([
                'User-Agent' => 'finobe.net/Server 1.0'
            ])->get('https://api.eracast.cc/v1/get_user_pfp', [
                'user' => $decoded['e_username']
            ]);

            $this->request['data']['pfp'] = $response->body();
            $this->request['data']['e_username'] = $decoded['e_username'];
            $this->request['data']['e_id'] = $decoded['e_id'];
        }

        return view($this->request['data']['user']['version'] . '/Settings/ConnectAPI', $this->request);
    }
}