<?php
/*

https://github.com/KiboOst/php-simpleNetatmoPresence
v 2017.1.0

*/

header('charset=utf-8');
require("splNetatmoConf.php");

function NP_connection()
	{
		global $Netatmo_user, $Netatmo_pass, $Netatmo_app_id, $Netatmo_app_secret;
		$token_url = "https://api.netatmo.com/oauth2/token";
		$postdata = http_build_query(
									array(
										'grant_type' => "password",
										'client_id' => $Netatmo_app_id,
										'client_secret' => $Netatmo_app_secret,
										'username' => $Netatmo_user,
										'password' => $Netatmo_pass,
										'scope' => 'read_presence access_presence write_presence'
								)
			);
		$opts = array('http' =>
							array(
								'method'  => 'POST',
								'header'  => "Content-type: application/x-www-form-urlencoded\r\n".
											"User-Agent: netatmoclient",
								'content' => $postdata
				)
			);
		$context  = stream_context_create($opts);
		$response = @file_get_contents($token_url, false, $context);
		if ($response === false) {
			$response = @file_get_contents($token_url, false, $context);
			if ($response === false) {
				die("ERROR, can't connect to Netatmo Server<br>");
			}
		}
		$params = null;
		$params = json_decode($response, true);

		return $params;
	}

function NP_setWebhook($NPconnection, $endpoint)
	{
		$api_url = "https://api.netatmo.net/api/addwebhook?access_token=" . $NPconnection['access_token']."&url=".$endpoint."&app_type=app_security";
		$requete = @file_get_contents($api_url);
		$jsonDatas = json_decode($requete,true);
		return $jsonDatas;
	}

function NP_dropWebhook($NPconnection)
	{
		$api_url = "https://api.netatmo.net/api/dropwebhook?access_token=" . $NPconnection['access_token']."&app_type=app_security";
		$requete = @file_get_contents($api_url);
		$jsonDatas = json_decode($requete,true);
		return $jsonDatas;
	}

function NP_getDatas($NPconnection, $eventNum=10)
	{
		$api_url = "https://api.netatmo.net/api/gethomedata?access_token=" . $NPconnection['access_token']."&size=".$eventNum;
		$requete = @file_get_contents($api_url);
		$jsonDatas = json_decode($requete,true);
		return $jsonDatas;
	}

function NP_getCameras($jsonDatas)
	{
		$cameraList = $jsonDatas['body']['homes'][0]['cameras']; //may support several homes ?
		$CamerasArray = array();
		for ($i=0; $i < count($cameraList) ;$i++)
		{
			$camera = $cameraList[$i];
			$cameraVPN = $camera["vpn_url"];
			$CamerasArray[$i]['snapshot'] = $cameraVPN.'/live/snapshot_720.jpg';

			if ($camera['is_local'] == false)
			{
				$cameraVPN = $cameraVPN."/live/index.m3u8";
			}
			else
			{
				$cameraVPN = $cameraVPN."/live/index_local.m3u8";
			}

			$CamerasArray[$i]['name'] = $camera["name"];
			$CamerasArray[$i]['id'] = $camera["id"];
			$CamerasArray[$i]['vpn'] = $cameraVPN;
			$CamerasArray[$i]['status'] = $camera["status"];
			$CamerasArray[$i]['sd_status'] = $camera["sd_status"];
			$CamerasArray[$i]['alim_status'] = $camera["alim_status"];
			$CamerasArray[$i]['light_mode_status'] = $camera["light_mode_status"];
			$CamerasArray[$i]['is_local'] = $camera["is_local"];

		}
		return $CamerasArray;
	}

function NP_getEvents($jsonDatas,$requestType="All",$num=1) //human, animal, vehicle
	{
		//will return the last event of defined type as array of [title, snapshotURL, vignetteURL]
		$returnEvents = array();

		$cameras = NP_getCameras($jsonDatas);
		$cameraEvents = $jsonDatas['body']['homes'][0]['events'];

		$numEvents = count($cameraEvents);
		$counts = $num;
		if ($numEvents < $counts) $counts == $numEvents;

		for ($i=0; $i < $counts ;$i++)
		{
			$thisEvent = $cameraEvents[$i];

			$id = $thisEvent["id"];
			$time = $thisEvent["time"];
			$camId = $thisEvent["camera_id"];
			foreach ($cameras as $cam)
				{
					if ($cam['id'] == $camId)
					{
						$camName = $cam['name'];
						$camVPN = $cam['vpn'];
						break;
					}
				}

			$eventList = $thisEvent["event_list"];
			$isAvailable = $thisEvent["video_status"];
			if ($isAvailable == "available")
				{
					for ($j=0; $j < count($eventList) ;$j++)
					{
						$thisSubEvent = $thisEvent["event_list"][$j];
						$subType = $thisSubEvent["message"];
						if (strpos($subType, $requestType) !== false OR $requestType=="All")
							{
								$subTime = $thisSubEvent["time"];
								$subTime = date("d-m-Y H:i:s", $subTime);

								if (isset($thisSubEvent["snapshot"]["filename"]))  //other vignette of same event!
								{
									$snapshotURL = $camVPN."/".$thisSubEvent["snapshot"]["filename"];
									$vignetteURL = $camVPN."/".$thisSubEvent["vignette"]["filename"];
								}else{
									$snapshotID = $thisSubEvent["snapshot"]["id"];
									$snapshotKEY = $thisSubEvent["snapshot"]["key"];
									$snapshotURL = "https://api.netatmo.com/api/getcamerapicture?image_id=".$snapshotID."&key=".$snapshotKEY;

									$vignetteID = $thisSubEvent["vignette"]["id"];
									$vignetteKEY = $thisSubEvent["vignette"]["key"];
									$vignetteURL = "https://api.netatmo.com/api/getcamerapicture?image_id=".$vignetteID."&key=".$vignetteKEY;
								}
								//echo '<img src="'.$snapshotURL.'" height="219" width="350" </img>'.'<br>';
								//echo '<img src="'.$vignetteURL.'" height="166" width="166" </img>'.'<br>';

								$returnThis = array();
								$returnThis['title'] = $subType . " | ".$subTime." | ".$camName;
								$returnThis['snapshotURL'] = $snapshotURL;
								$returnThis['vignetteURL'] = $vignetteURL;
								array_push($returnEvents, $returnThis);
							}
					}
				}
		}

		return $returnEvents;
	}

?>