<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

use App\Models\User;

class CharacterAppearanceController extends Controller
{
    public function bodyColors(Request $request) {
        $validator = Validator::make($request->all(), [
			'userId' => [
				'required',
				Rule::exists('App\Models\User', 'id')
			]
		]);

        if($validator->fails()) {
            return response()->json(['code' => 0, 'error' => 'Not found'], 404);
		}

		$valid = $validator->valid();
		$user = User::where('id', $valid['userId'])->first();

        $colors = json_decode($user->avatar, false)[0]->bodyColors;

        return '<roblox xmlns:xmime="http://www.w3.org/2005/05/xmlmime" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xsi:noNamespaceSchemaLocation="http://www.finobe.net/roblox.xsd" version="4">
            <External>null</External>
            <External>nil</External>
            <Item class="BodyColors">
                <Properties>
                    <int name="HeadColor">'.$colors->headColorId.'</int>
                    <int name="LeftArmColor">'.$colors->leftArmColorId.'</int>
                    <int name="LeftLegColor">'.$colors->leftLegColorId.'</int>
                    <string name="Name">Body Colors</string>
                    <int name="RightArmColor">'.$colors->rightArmColorId.'</int>
                    <int name="RightLegColor">'.$colors->rightLegColorId.'</int>
                    <int name="TorsoColor">'.$colors->torsoColorId.'</int>
                    <bool name="archivable">true</bool>
                </Properties>
            </Item>
        </roblox>';
    }

    public function characterFetch(Request $request) {
        $validator = Validator::make($request->all(), [
			'userId' => [
				'required',
				Rule::exists('App\Models\User', 'id')
			]
		]);

        if($validator->fails()) {
            return response()->json(['code' => 0, 'error' => 'Not found'], 404);
		}

		$valid = $validator->valid();
        $user = User::where('id', $valid['userId'])->first();

        //TODO(Karma): Database structure actually should be revamped, as current is hell, too much json which is not supposed to be used?
        $avatar = json_decode($user->avatar, false)[0];

        $charApp = route('asset-game.body-colors', ['userId' => $user->id]).';';

        $ids = array_merge(
            $avatar->equippedGearVersionIds ?? [],
            $avatar->backpackGearVersionIds ?? []
        );

        $charApp .= $ids ? implode(";", array_map(
            fn($id) => route('asset-game.asset', ['id' => $id]),
            $ids
        )) : "";

        return response($charApp, 200);
    }
}
