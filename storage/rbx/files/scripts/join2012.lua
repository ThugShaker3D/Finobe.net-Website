-- MultiplayerSharedScript.lua inserted here ------ Prepended to GroupBuild.lua and Join.lua --
pcall(function() game:SetPlaceID({placeId}, true) end)

settings()["Game Options"].CollisionSoundEnabled = true
pcall(function() settings().Rendering.EnableFRM = true end)
pcall(function() settings().Physics.Is30FpsThrottleEnabled = true end)
pcall(function() settings()["Task Scheduler"].PriorityMethod = Enum.PriorityMethod.AccumulatedError end)

-- arguments ---------------------------------------
local threadSleepTime = ...

if threadSleepTime==nil then
	threadSleepTime = 15
end

local test = false

print("! Joining game '{jobId}' place {placeId} at {serverip}")

game:GetService("ChangeHistoryService"):SetEnabled(false)
game:GetService("ContentProvider"):SetThreadPool(16)
game:GetService("InsertService"):SetBaseSetsUrl("http://www.finobe.net/Game/Tools/InsertAsset.ashx?nsets=10&type=base")
game:GetService("InsertService"):SetUserSetsUrl("http://www.finobe.net/Game/Tools/InsertAsset.ashx?nsets=20&type=user&userid=%d")
game:GetService("InsertService"):SetCollectionUrl("http://www.finobe.net/Game/Tools/InsertAsset.ashx?sid=%d")
game:GetService("InsertService"):SetAssetUrl("http://www.finobe.net/Asset/?id=%d")
game:GetService("InsertService"):SetAssetVersionUrl("http://www.finobe.net/Asset/?assetversionid=%d")

pcall(function() game:GetService("SocialService"):SetFriendUrl("http://www.finobe.net/Game/LuaWebService/HandleSocialRequest.ashx?method=IsFriendsWith&playerid=%d&userid=%d") end)
pcall(function() game:GetService("SocialService"):SetBestFriendUrl("http://www.finobe.net/Game/LuaWebService/HandleSocialRequest.ashx?method=IsBestFriendsWith&playerid=%d&userid=%d") end)
pcall(function() game:GetService("SocialService"):SetGroupUrl("http://www.finobe.net/Game/LuaWebService/HandleSocialRequest.ashx?method=IsInGroup&playerid=%d&groupid=%d") end)
pcall(function() game:GetService("SocialService"):SetGroupRankUrl("http://www.finobe.net/Game/LuaWebService/HandleSocialRequest.ashx?method=GetGroupRank&playerid=%d&groupid=%d") end)
pcall(function() game:GetService("SocialService"):SetGroupRoleUrl("http://www.finobe.net/Game/LuaWebService/HandleSocialRequest.ashx?method=GetGroupRole&playerid=%d&groupid=%d") end)
pcall(function() game:SetCreatorID({creatorId}, Enum.CreatorType.User) end)
-- Bubble chat.  This is all-encapsulated to allow us to turn it off with a config setting
pcall(function() game:GetService("Players"):SetChatStyle(Enum.ChatStyle.{chatstyle}) end)

pcall( function()
	if settings().Network.MtuOverride == 0 then
	  settings().Network.MtuOverride = 1400
	end
end)


-- globals -----------------------------------------

client = game:GetService("NetworkClient")
visit = game:GetService("Visit")

-- functions ---------------------------------------
function setMessage(message)
	-- todo: animated "..."
	if not false then
		game:SetMessage(message)
	else
		-- hack, good enought for now
		game:SetMessage("Teleporting ...")
	end
end

function showErrorWindow(message)
	game:SetMessage(message)
end

function reportError(err)
	print("***ERROR*** " .. err)
	if not test then visit:SetUploadUrl("") end
	client:Disconnect()
	wait(4)
	showErrorWindow("Error: " .. err)
end

-- called when the client connection closes
function onDisconnection(peer, lostConnection)
	if lostConnection then
		showErrorWindow("You have lost the connection to the game")
	else
		showErrorWindow("This game has shut down")
	end
end

function requestCharacter(replicator)
	
	-- prepare code for when the Character appears
	local connection
	connection = player.Changed:connect(function (property)
		if property=="Character" then
			game:ClearMessage()
			
			connection:disconnect()
		end
	end)
	
	setMessage("Requesting character")

	local success, err = pcall(function()	
		replicator:RequestCharacter()
		setMessage("Waiting for character")
	end)
	if not success then
		reportError(err)
		return
	end
end

-- called when the client connection is established
function onConnectionAccepted(url, replicator)

	local waitingForMarker = true
	
	local success, err = pcall(function()	
		if not test then 
		    visit:SetPing("", 300) 
		end
		
		if not false then
			game:SetMessageBrickCount()
		else
			setMessage("Teleporting ...")
		end

		replicator.Disconnection:connect(onDisconnection)
		
		-- Wait for a marker to return before creating the Player
		local marker = replicator:SendMarker()
		
		marker.Received:connect(function()
			waitingForMarker = false
			requestCharacter(replicator)
		end)
	end)
	
	if not success then
		reportError(err)
		return
	end
	
	-- TODO: report marker progress
	
	while waitingForMarker do
		workspace:ZoomToExtents()
		wait(0.5)
	end
end

-- called when the client connection fails
function onConnectionFailed(_, error)
	showErrorWindow("Failed to connect to the Game. (ID=" .. error .. ")")
end

-- called when the client connection is rejected
function onConnectionRejected()
	connectionFailed:disconnect()
	showErrorWindow("This game is not available. Please try another")
end

idled = false
function onPlayerIdled(time)
	if time > 20*60 then
		showErrorWindow(string.format("You were disconnected for being idle %d minutes", time/60))
		client:Disconnect()	
		if not idled then
			idled = true
		end
	end
end


-- main ------------------------------------------------------------

pcall(function() settings().Diagnostics:LegacyScriptMode() end)
local success, err = pcall(function()	

	game:SetRemoteBuildMode(true)
	
	setMessage("Connecting to Server")
	client.ConnectionAccepted:connect(onConnectionAccepted)
	client.ConnectionRejected:connect(onConnectionRejected)
	connectionFailed = client.ConnectionFailed:connect(onConnectionFailed)
	client.Ticket = ""
	
	playerConnectSucces, player = pcall(function() return client:PlayerConnect({userid}, "{serverip}", {serverport}, 0, threadSleepTime) end)
	if not playerConnectSucces then
		--Old player connection scheme
		player = game:GetService("Players"):CreateLocalPlayer({userid})
		client:Connect("{serverip}", {serverport}, 0, threadSleepTime)
	end

	player:SetSuperSafeChat(false)
	pcall(function() player:SetMembershipType(Enum.MembershipType.None) end)
	pcall(function() player:SetAccountAge(365) end)
	player.Idled:connect(onPlayerIdled)
	
	pcall(function() player.Name = [========[{username}]========] end)
	player.CharacterAppearance = "{charapp}"	
	if not test then visit:SetUploadUrl("")end
end)

if not success then
	reportError(err)
end

pcall(function() game:SetScreenshotInfo("") end)
pcall(function() game:SetVideoInfo('<?xml version="1.0"?><entry xmlns="http://www.w3.org/2005/Atom" xmlns:media="http://search.yahoo.com/mrss/" xmlns:yt="http://gdata.youtube.com/schemas/2007"><media:group><media:title type="plain"><![CDATA[ROBLOX Place]]></media:title><media:description type="plain"><![CDATA[ For more games visit http://www.finobe.net]]></media:description><media:category scheme="http://gdata.youtube.com/schemas/2007/categories.cat">Games</media:category><media:keywords>ROBLOX, video, free game, online virtual world</media:keywords></media:group></entry>') end)
-- use single quotes here because the video info string may have unescaped double quotes
