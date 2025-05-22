<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class Asset extends Controller
{
    protected $db;

    public function __construct() {
        $this->db = DB::connection('finobe');
    }

    public static function createAsset(string $name, int $assetType, int $author, $file, string $description, string $visibility, array $additional): int
    {
        $instance = new self();
        $fileHash = uniqid();
        $additional = json_encode($additional);
        $asset = $instance->db->table('assets')->insertGetId([
            'asset_type' => $assetType,
            'title' => $name,
            'author' => $author,
            'file' => $fileHash,
            'description' => $description,
            'visibility' => $visibility,
            'additional' => $additional
        ]);
        file_put_contents("/var/www/cdn.finobe.net/assets/{$fileHash}", $file);
        return $asset;
    }

    public static function render(int $id, int $assetType): string
    {
        if (self::isAssetExist($id))
        {
            switch ($assetType) {
                case 2:
                    return self::renderTShirt($id);
                case 8:
                    return self::renderHat($id);
                case 11:
                    return self::renderShirt($id);
                case 12:
                    return self::renderPants($id);
                default:
                    return "";
            }
        }

        return "";
    }

    public static function renderHat(int $id) : string
    {
        if (self::isAssetExist($id)) {
            $arbiter = new RobloxArbiterUtilities("45.131.65.123", 64989);
            $constructedJob = $arbiter->ConstructJob(
                RobloxUtilities::GenerateGUID(),
                '
                    local assetid, asseturl, url, fileExtension, x, y = ...
                    
                    print("Render Hat " .. assetid)
                    
                    pcall(function() game:GetService("ContentProvider"):SetBaseUrl(url) end)
                    game:GetService("ThumbnailGenerator").GraphicsMode = 4
                    game:GetService("ScriptContext").ScriptsDisabled = true
                    game:GetObjects(asseturl)[1].Parent = workspace
                    t = game:GetService("ThumbnailGenerator")
                    return t:Click(fileExtension, x, y, true, true)
                ',
                60,
                0,
                2,
                "ScriptExecution",
                [$id, "https://www.finobe.net/asset/?id={$id}", "https://www.finobe.net", "PNG", 768, 768]
            );

            $jobEx = $arbiter->OpenJobEx($constructedJob);

            $filename = uniqid() . ".png";
            file_put_contents("/var/www/cdn.finobe.net/thumbnails/{$filename}", base64_decode($jobEx));

            $instance = new self();
            $hatData = self::getAssetData($id);
            $additional = json_decode($hatData->additional);
            $additional->media->thumbnail = "https://cdn.finobe.net/thumbnails/{$filename}";
            $additional = json_encode($additional);
            $instance->db->table('assets')
                ->where('id', $id)
                ->update([
                    'additional' => $additional
                ]);

            return "https://cdn.finobe.net/thumbnails/{$filename}";
        }
        return "";
    }

    public static function createHat(string $name, array $texture, array $mesh, array $xml, int $author, string $description, int $price, bool $onSale = false, bool $isLimited = false, array $historicalPrice = []): int
    {
        $textureId = self::createAsset("{$name} Texture", 1, $author, file_get_contents($texture['tmp_name']), "", "n", []);
        $meshId = self::createAsset("{$name} Mesh", 4, $author, file_get_contents($mesh['tmp_name']), "", "n", []);

        $xmltemplate = file_get_contents($xml['tmp_name']);

        // finds and replaces roblox links with MESHURLPLACEHOLDER or TEXTUREURLPLACEHOLDER automatically EDIT: also check rbxassetid format
        $xmltemplate = preg_replace_callback(
            '/<Content name="MeshId">\s*<url>(https?:\/\/www\.roblox\.com\/asset\/\?id=\d+|rbxassetid:\/\/\d+)<\/url>\s*<\/Content>/i',
            function ($matches) {
                return str_replace($matches[1], 'MESHURLPLACEHOLDER', $matches[0]);
            },
            $xmltemplate
        );

        $xmltemplate = preg_replace_callback(
            '/<Content name="TextureId">\s*<url>(https?:\/\/www\.roblox\.com\/asset\/\?id=\d+|rbxassetid:\/\/\d+)<\/url>\s*<\/Content>/i',
            function ($matches) {
                return str_replace($matches[1], 'TEXTUREURLPLACEHOLDER', $matches[0]);
            },
            $xmltemplate
        );

        $xmltemplate =str_replace("MESHURLPLACEHOLDER",  "http://www.finobe.net/asset/?id=" . $meshId, $xmltemplate);
        $xmltemplate =str_replace("TEXTUREURLPLACEHOLDER", "http://www.finobe.net/asset/?id=" . $textureId, $xmltemplate);

        $hatId = self::createAsset($name, 8, $author, $xmltemplate, $description, "n", [
            "price" => $price,
            "onSale" => $onSale,
            "isLimited" => $isLimited,
            "historicPrice" => $historicalPrice,
            "media" => [
                "thumbnail" => ""
            ]
        ]);

        self::renderHat($hatId);
        return $hatId;
    }

    public static function createAccessory(string $name, array $texture, int $author, string $description, int $price, bool $onSale = false, bool $isLimited = false, string $type = "face"): int //type: shirt/pants/tshirt/face
    {
        $textureId = self::createAsset("{$name} Texture", 1, $author, file_get_contents($texture['tmp_name']), '', "r", []);
        $assetType = 18;
        $xmlTemplate = "";
        if($type == "face") {
            $assetType = 18;
            $xmlTemplate = '<roblox xmlns:xmime="http://www.w3.org/2005/05/xmlmime" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xsi:noNamespaceSchemaLocation="http://www.roblox.com/roblox.xsd" version="4">
                <External>null</External>
                <External>nil</External>
                <Item class="Decal" referent="RBX0">
                    <Properties>
                        <token name="Face">5</token>
                        <string name="Name">face</string>
                        <float name="Shiny">20</float>
                        <float name="Specular">0</float>
                        <Content name="Texture"><url>TEXTUREURLPLACEHOLDER</url></Content>
                        <bool name="archivable">true</bool>
                    </Properties>
                </Item>
            </roblox>';
        } elseif($type == "tshirt") {
            $assetType = 2;
            $xmlTemplate = '<roblox xmlns:xmime="http://www.w3.org/2005/05/xmlmime" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xsi:noNamespaceSchemaLocation="http://www.roblox.com/roblox.xsd" version="4">
                <External>null</External>
                <External>nil</External>
                <Item class="Decal" referent="RBX0">
                    <Properties>
                        <token name="Face">5</token>
                        <string name="Name">face</string>
                        <float name="Shiny">20</float>
                        <float name="Specular">0</float>
                        <Content name="Texture"><url>TEXTUREURLPLACEHOLDER</url></Content>
                        <bool name="archivable">true</bool>
                    </Properties>
                </Item>
            </roblox>';
        } elseif($type == "shirt") {
            $assetType = 11;
            $xmlTemplate = '<roblox xmlns:xmime="http://www.w3.org/2005/05/xmlmime" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xsi:noNamespaceSchemaLocation="http://www.roblox.com/roblox.xsd" version="4">
                <External>null</External>
                <External>nil</External>
                <Item class="Shirt" referent="RBX0">
                    <Properties>
                    <Content name="ShirtTemplate">
                        <url>TEXTUREURLPLACEHOLDER</url>
                    </Content>
                    <string name="Name">Shirt</string>
                    <bool name="archivable">true</bool>
                    </Properties>
                </Item>
            </roblox>';
        } elseif($type == "pants") {
            $assetType = 12;
            $xmlTemplate = '<roblox xmlns:xmime="http://www.w3.org/2005/05/xmlmime" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xsi:noNamespaceSchemaLocation="http://www.roblox.com/roblox.xsd" version="4">
                <External>null</External>
                <External>nil</External>
                <Item class="Pants" referent="RBX0">
                    <Properties>
                    <Content name="PantsTemplate">
                        <url>TEXTUREURLPLACEHOLDER</url>
                    </Content>
                    <string name="Name">Pants</string>
                    <bool name="archivable">true</bool>
                    </Properties>
                </Item>
            </roblox>';
        }
        $xmlTemplate =str_replace("TEXTUREURLPLACEHOLDER", "http://www.finobe.net/asset/?id=" . $textureId, $xmlTemplate);
        $accessoryId = self::createAsset($name, $assetType, $author, $xmlTemplate, $description, "r", [
            "price" => $price,
            "onSale" => $onSale,
            "media" => [
                "thumbnail" => "",
                "textureAssetId" => $textureId
            ]
        ]);
        if ($assetType == 18)
        {
            if($assetType == 18) {
                $thumb = self::getImage($accessoryId, $textureId);
            } else {
                $thumb = self::getImage($accessoryId);
            }
            
            $instance = new self();
            $hatData = self::getAssetData($accessoryId);
            $additional = json_decode($hatData->additional);
            $additional->media->thumbnail = $thumb;
            $additional = json_encode($additional);
            $instance->db->table('assets')
                ->where('id', $accessoryId)
                ->update([
                    'additional' => $additional
                ]);
        }
        if ($assetType != 18) {
            self::render($accessoryId, $assetType);
        }
        return $accessoryId;
    }

    public static function getImage(int $id, int $textureId = 0): string
    {
        if (self::isAssetExist($id))
        {
            $imageData = self::getAssetData($id);
            $additional = json_decode($imageData->additional, true);

            if($imageData->asset_type == 18) {
                $textureData = self::getAssetData($textureId);
                return "https://cdn.finobe.net/assets/{$textureData->file}";
            }
            
            return $additional->media->thumbnail ?? "";
        }

        return "";
    }

    public static function isAssetExist(int $assetId) : bool
    {
        $instance = new self();
        return $instance->db->table('assets')->where('id', $assetId)->exists();
    }

    public static function getAssetData(int $assetId) : object
    {
        $instance = new self();
        if ($instance->db->table('assets')->where('id', $assetId)->exists()) {
            return $instance->db->table('assets')->where('id', $assetId)->first();
        }
        return (object)[];
    }

    public static function getCdnLink(int $assetId): string
    {
        If (self::isAssetExist($assetId)) {
            $assetinfo = (object)self::getAssetData($assetId);
            return "http://cdn.finobe.net/assets/{$assetinfo->file}";
        }
        return "http://cdn.finobe.net/";
    }

    public static function isAllowedToDownload(int $assetId): bool
    {
        $user = Auth::user();

        If (self::isAssetExist($assetId)) {
            $assetinfo = (object)self::getAssetData($assetId);
            $additional = json_decode($assetinfo->additional);

            if (RobloxUtilities::IsFinobeCloudAuthorized())
            {
                return true;
            }

            if ($assetinfo->asset_type != 9 || (($assetinfo == 9 && $additional->uncopylocked) || $additional->onSale)) {
                return true;
            } else {
                if ($assetinfo->author == ($user->id ?? 0)) {
                    return true;
                }
            }
        }

        return false;
    }

    public static function renderPants(int $id)
    {
        if (self::isAssetExist($id)) {
            $arbiter = new RobloxArbiterUtilities("45.131.65.123", 64989);
            $constructedJob = $arbiter->ConstructJob(
                RobloxUtilities::GenerateGUID(),
                '
                    local assetid, asseturl, url, fileExtension, x, y = ...
                    
                    print("Render Pants " .. assetid)
                    
                    pcall(function() game:GetService("ContentProvider"):SetBaseUrl(url) end)
                    game:GetService("ThumbnailGenerator").GraphicsMode = 4
                    game:GetService("ScriptContext").ScriptsDisabled = true
                    player = game:GetService("Players"):CreateLocalPlayer(0)
                    player:LoadCharacter(false)
                    c = Instance.new("Pants")
                    c.PantsTemplate = game:GetObjects(asseturl)[1].PantsTemplate
                    c.Parent = player.Character

                    t = game:GetService("ThumbnailGenerator")
                    return t:Click(fileExtension, x, y, true, true)
                ',
                60,
                0,
                2,
                "ScriptExecution",
                [$id, "https://www.finobe.net/asset/?id={$id}", "https://www.finobe.net", "PNG", 768, 768]
            );

            $jobEx = $arbiter->OpenJobEx($constructedJob);

            $filename = uniqid() . ".png";
            file_put_contents("/var/www/cdn.finobe.net/thumbnails/{$filename}", base64_decode($jobEx));

            $instance = new self();
            $hatData = self::getAssetData($id);
            $additional = json_decode($hatData->additional);
            $additional->media->thumbnail = "https://cdn.finobe.net/thumbnails/{$filename}";
            $additional = json_encode($additional);
            $instance->db->table('assets')
                ->where('id', $id)
                ->update([
                    'additional' => $additional
                ]);

            return "https://cdn.finobe.net/thumbnails/{$filename}";
        }
        return "";
    }

    public static function renderShirt(int $id)
    {
        if (self::isAssetExist($id)) {
            $arbiter = new RobloxArbiterUtilities("45.131.65.123", 64989);
            $constructedJob = $arbiter->ConstructJob(
                RobloxUtilities::GenerateGUID(),
                '
                    local assetid, asseturl, url, fileExtension, x, y = ...
                    
                    print("Render Shirt " .. assetid)
                    
                    pcall(function() game:GetService("ContentProvider"):SetBaseUrl(url) end)
                    game:GetService("ThumbnailGenerator").GraphicsMode = 4
                    game:GetService("ScriptContext").ScriptsDisabled = true
                    player = game:GetService("Players"):CreateLocalPlayer(0)
                     player:LoadCharacter(false)
                    c = Instance.new("Shirt")
                    c.ShirtTemplate = game:GetObjects(asseturl)[1].ShirtTemplate
                    c.Parent = player.Character

                    t = game:GetService("ThumbnailGenerator")
                    return t:Click(fileExtension, x, y, true, true)
                ',
                60,
                0,
                2,
                "ScriptExecution",
                [$id, "https://www.finobe.net/asset/?id={$id}", "https://www.finobe.net", "PNG", 768, 768]
            );

            $jobEx = $arbiter->OpenJobEx($constructedJob);

            $filename = uniqid() . ".png";
            file_put_contents("/var/www/cdn.finobe.net/thumbnails/{$filename}", base64_decode($jobEx));

            $instance = new self();
            $hatData = self::getAssetData($id);
            $additional = json_decode($hatData->additional);
            $additional->media->thumbnail = "https://cdn.finobe.net/thumbnails/{$filename}";
            $additional = json_encode($additional);
            $instance->db->table('assets')
                ->where('id', $id)
                ->update([
                    'additional' => $additional
                ]);

            return "https://cdn.finobe.net/thumbnails/{$filename}";
        }
        return "";
    }

    public static function renderTShirt(int $id)
    {
        if (self::isAssetExist($id)) {
            $arbiter = new RobloxArbiterUtilities("45.131.65.123", 64989);
            $constructedJob = $arbiter->ConstructJob(
                RobloxUtilities::GenerateGUID(),
                '
                    local assetid, asseturl, url, fileExtension, x, y = ...
                    
                    print("Render T-Shirt " .. assetid)
                    
                    pcall(function() game:GetService("ContentProvider"):SetBaseUrl(url) end)
                    game:GetService("ThumbnailGenerator").GraphicsMode = 4
                    game:GetService("ScriptContext").ScriptsDisabled = true
                    player = game:GetService("Players"):CreateLocalPlayer(0)
                     player:LoadCharacter(false)
                    c = Instance.new("ShirtGraphic")
                    c.Graphic = asseturl
                    c.Parent = player.Character

                    t = game:GetService("ThumbnailGenerator")
                    return t:Click(fileExtension, x, y, true, true)
                ',
                60,
                0,
                2,
                "ScriptExecution",
                [$id, "https://www.finobe.net/asset/?id={$id}", "https://www.finobe.net", "PNG", 768, 768]
            );

            $jobEx = $arbiter->OpenJobEx($constructedJob);

            $filename = uniqid() . ".png";
            file_put_contents("/var/www/cdn.finobe.net/thumbnails/{$filename}", base64_decode($jobEx));

            $instance = new self();
            $hatData = self::getAssetData($id);
            $additional = json_decode($hatData->additional);
            $additional->media->thumbnail = "https://cdn.finobe.net/thumbnails/{$filename}";
            $additional = json_encode($additional);
            $instance->db->table('assets')
                ->where('id', $id)
                ->update([
                    'additional' => $additional
                ]);

            return "https://cdn.finobe.net/thumbnails/{$filename}";
        }
        return "";
    }
}
