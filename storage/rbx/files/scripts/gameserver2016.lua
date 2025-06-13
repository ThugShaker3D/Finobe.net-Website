local placeId, port, url, creatorid = ...
------------------- UTILITY FUNCTIONS --------------------------

local Players = game:GetService("Players")
local HttpService = game:GetService("HttpService")

function waitForChild(parent, childName)
	while true do
		local child = parent:findFirstChild(childName)
		if child then
			return child
		end
		parent.ChildAdded:wait()
	end
end

function reportPlayers()
    local success, message = pcall(function()
        local PlayerList = {}
        for _, player in pairs(Players:GetChildren()) do
            if player:IsA("Player") then
                table.insert(PlayerList, player.UserId)
            end
        end
        local MessagePayload = HttpService:JSONEncode({
            ["jobId"] = game.JobId,
            ["players"] = PlayerList
        })
        local ResponseData = game:HttpPost(url.."/api/gameserver/update", MessagePayload, true, "application/json")
    end)

    if not success then
        warn("Error reporting players: ".. tostring(message))
    end
end	

-----------------------------------END UTILITY FUNCTIONS -------------------------

-----------------------------------"CUSTOM" SHARED CODE----------------------------------

pcall(function() settings().Network.UseInstancePacketCache = true end)
pcall(function() settings().Network.UsePhysicsPacketCache = true end)
pcall(function() settings()["Task Scheduler"].PriorityMethod = Enum.PriorityMethod.AccumulatedError end)

settings().Network.PhysicsSend = Enum.PhysicsSendMethod.TopNErrors
settings().Network.ExperimentalPhysicsEnabled = true
settings().Network.WaitingForCharacterLogRate = 100
pcall(function() settings().Diagnostics:LegacyScriptMode() end)

-----------------------------------START GAME SHARED SCRIPT------------------------------

local assetId = placeId -- might be able to remove this now

local scriptContext = game:GetService('ScriptContext')
pcall(function() scriptContext:AddStarterScript(37801172) end)
scriptContext.ScriptsDisabled = true

game:SetPlaceID(assetId, false)
game:SetCreatorID(creatorid, Enum.CreatorType.User)
game:GetService("ChangeHistoryService"):SetEnabled(false)

-- establish this peer as the Server
local ns = game:GetService("NetworkServer")

if url~=nil then
	pcall(function() game:GetService("Players"):SetAbuseReportUrl(url .. "/AbuseReport/InGameChatHandler.ashx") end)
	pcall(function() game:GetService("ScriptInformationProvider"):SetAssetUrl(url .. "/Asset/") end)
	pcall(function() game:GetService("ContentProvider"):SetBaseUrl(url .. "/") end)
	-- pcall(function() game:GetService("Players"):SetChatFilterUrl(url .. "/Game/ChatFilter.ashx") end)

	game:GetService("BadgeService"):SetPlaceId(placeId)

	game:GetService("BadgeService"):SetIsBadgeLegalUrl("")
	game:GetService("InsertService"):SetBaseSetsUrl(url .. "/Game/Tools/InsertAsset.ashx?nsets=10&type=base")
	game:GetService("InsertService"):SetUserSetsUrl(url .. "/Game/Tools/InsertAsset.ashx?nsets=20&type=user&userid=%d")
	game:GetService("InsertService"):SetCollectionUrl(url .. "/Game/Tools/InsertAsset.ashx?sid=%d")
	game:GetService("InsertService"):SetAssetUrl(url .. "/Asset/?id=%d")
	game:GetService("InsertService"):SetAssetVersionUrl(url .. "/Asset/?assetversionid=%d")
	
	pcall(function() loadfile(url .. "/Game/LoadPlaceInfo.ashx?PlaceId=" .. placeId)() end)
end

pcall(function() game:GetService("NetworkServer"):SetIsPlayerAuthenticationRequired(true) end)
settings().Diagnostics.LuaRamLimit = 0


game:GetService("Players").PlayerAdded:connect(function(player)
	print("Player " .. player.userId .. " added")
    game:HttpGet(url .. "/api/gameserver/visit/" .. game.JobId)
	reportPlayers()
	player.Chatted:connect(
		function(msg)
			if player.Character ~= nil then
				if msg == ";ec" or msg == ";energycell" or msg == ";bleach" or msg == ";suicide" or msg == ";cut" or msg == ";finobe" or msg == ";kms" or msg == ";raymonf" or msg == ";kyle" then
				local aeaea = Instance.new("Sound")
				aeaea.Parent = player.Character.Torso
				local higuw = math.random(1, 2)
				if higuw == 1 then
					aeaea.SoundId = url .. "/assets/?id=15"
				elseif higuw == 2 then
					aeaea.SoundId = url .. "/assets/?id=16"
				end
				aeaea.Volume = 0.7
				aeaea:Play()
				wait(0.1)
				player.Character.Humanoid.Health = 0
			end
		end
	end)
end)

game:GetService("Players").PlayerRemoving:connect(function(player)
	print("Player " .. player.userId .. " leaving")
	reportPlayers()
end)

if placeId~=nil and url~=nil then
	wait()
	
	game:Load(url .. "/asset/?id=" .. placeId)
end

ns:Start(port) 

scriptContext:SetTimeout(10)
scriptContext.ScriptsDisabled = false

------------------------------END START GAME SHARED SCRIPT--------------------------

-- StartGame -- 
game:GetService("RunService"):Run()

game:HttpGet(url .. "/api/gameserver/register/" .. game.JobId)

while wait(30) do
    game:HttpGet(url .. "/api/gameserver/alive/" .. game.JobId)
	if(game:GetService('Players').NumPlayers == 0) then
		game:HttpGet(url .. "/api/gameserver/shutdown/" .. game.JobId)
	end
end
