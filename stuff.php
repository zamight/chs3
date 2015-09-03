<?php 
$HSmysqli = new mysqli('', '', '', '');
if ($HSmysqli->connect_error) {
	die('Connect Error (' . $HSmysqli->connect_errno . ') ' . $HSmysqli->connect_error);
}

$skills = array(
	"overall","attack","defence","strength","hitpoints","range","prayer","magic","cooking",
	"woodcutting","fletching","fishing","firemaking","crafting","smithing","mining","herblore",
	"agility","thieving","slayer","farming","runecrafting","hunter","construction"
);

$id = $_GET['id'];
$user = $_GET['user'];

function updateStats($userid, $skill, $type, $value) {
	global $HSmysqli;
	
	$query = "UPDATE `highscores` SET `{$skill}_{$type}` = '{$value}' WHERE `userid` = {$userid}";

	if($HSmysqli->query($query)) {
		if($HSmysqli->affected_rows <= 0) {
			$queryInsertPlayer = "INSERT INTO `highscores` (`userid`, `{$skill}_{$type}`) VALUES ('{$userid}', '{$value}')";

			if($HSmysqli->query($queryInsertPlayer)) {
				return true;
			} 
		}
	} 
}

function getUserStats($id) {
	global $HSmysqli, $skills;
	
	$query = "SELECT * FROM highscores WHERE userid = {$id} LIMIT 1";
	$return = array();
	$return['userid'] = $id;
	
	foreach($skills as $skill) {
		$return[$skill . '_lvl'] = 0;
		$return[$skill . '_exp'] = 0;
	}
	
	if($results = $HSmysqli->query($query)) {
	
		//Fetch array
		while($row = $results->fetch_assoc()) {
			$return = null;
			$return = $row;
		}
	
	}
	
	return $return;
}

function updateHighscore($id, $user) {

	global $HSmysqli, $skills;
	
	$i = 0;
		
	$ch      = curl_init();
	$timeout = 5;
	curl_setopt($ch, CURLOPT_URL, "http://services.runescape.com/m=hiscore_oldschool/index_lite.ws?player=" . $user);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
	curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout);
	$data = curl_exec($ch);
	curl_close($ch);
	
	if (stripos(strtolower($data), 'page not found') == true) {
		$query1 = "DELETE FROM `highscores` WHERE `userid` = {$id}";
			$HSmysqli->query($query1);
			$HSmysqli->close();
	die("Not Found");
	}
	
	$dataBreaks = explode("\n", $data);
	$userData = getUserStats($id);
	
	foreach($dataBreaks as $dataBreak) {
		$dataLine[] = explode(',', $dataBreak);
	}
	
						//##########COMBAT ALGORITHM###############
					/*
						"overall","attack","defence","strength","hitpoints","range","prayer","magic","cooking",
	"woodcutting","fletching","fishing","firemaking","crafting","smithing","mining","herblore",
	"agility","thieving","slayer","farming","runecrafting","hunter","construction"
					
					*/
			
			$Defence		=	$dataLine[2][1];
			$Hitpoints		=	$dataLine['4'][1];
			$Prayer			=	$dataLine['6'][1];
			$Attack			=	$dataLine['1'][1];
			$Strength		=	$dataLine['3'][1];
			$Range			=	$dataLine['5'][1];
			$Magic			=	$dataLine['7'][1];
			
			$Melee			=	floor(0.25 * ($Defence + $Hitpoints + floor($Prayer/2)) + 0.325 * ($Attack + $Strength));
			
			$Range			=	floor(0.25 * ($Defence + $Hitpoints + floor($Prayer/2)) + 0.325 * (floor($Range/2) + $Range));
			
			$Magic			=	floor(0.25 * ($Defence + $Hitpoints + floor($prayer/2)) + 0.325 * (floor($Magic/2) + $Magic));
			
			$Finalcmb = $Magic;
			
			if($Melee > $Range AND $Melee > $Magic) {
				$Finalcmb = $Melee;
			}
			elseif($Range > $Magic) {
				$Finalcmb = $Range;
			}
			
			updateCmb($id, $Finalcmb);
	
	//[0] => rank [1] => lvl [2] =>  exp
	foreach($skills as $skill) {
		$newlvl = $dataLine[$i][1];
		$newexp = $dataLine[$i][2];
		
		/*
		if($i == 4) {
			if($newlvl  > 10) {
				$query = "UPDATE `mybb_users` SET `usergroup` = '2', `displaygroup` = '2' WHERE `uid` = {$id}";
				$HSmysqli->query($query);
				$query1 = "DELETE FROM `highscores` WHERE `userid` = {$id}";
				$HSmysqli->query($query1);
				return 0;
				}
		}
		*/
		
		$oldlvl = $userData[$skill . '_lvl'];
		$oldexp = $userData[$skill . '_exp'];
		
		if($newlvl > $oldlvl) {
			if($oldlvl != 0) {
			addAnnouncement($id, "achieved lvl {$newlvl} in {$skill}.");
			}
			updateStats($id, $skill, 'lvl', $newlvl);
		}
		
		if($newexp > $oldexp) {
		
		if($oldexp != 0) {
		//See if its an even mill.
		$subStringNewExp = substr($newexp, 0, -6);
		$subStringOldExp = substr($oldexp, 0, -6);
		
		if($subStringNewExp > $subStringOldExp) {
		
			addAnnouncement($id, "achieved {$subStringNewExp}M in {$skill}.");
		}
		}
			updateStats($id, $skill, 'exp', $newexp);
		}
		$i++;
	}
	
	print "Updated";
}

function addAnnouncement($userid, $msg) {
	global $HSmysqli;
	
	$query = "INSERT INTO `achievements` (`id`, `userid`, `message`, `date`) VALUES (NULL, '{$userid}', '{$msg}', CURRENT_DATE())";
	
	if($HSmysqli->query($query)) {
		return true;
	} 
}

function updateCmb($userid, $value) {
	global $HSmysqli;
	
	$query = "UPDATE `highscores` SET `cmb` = '{$value}' WHERE `userid` = {$userid}";

	if($HSmysqli->query($query)) {
		if($HSmysqli->affected_rows <= 0) {
			$queryInsertPlayer = "INSERT INTO `highscores` (`userid`, `cmb`) VALUES ('{$userid}', '{$value}')";

			if($HSmysqli->query($queryInsertPlayer)) {
				return true;
			} 
		}
	} 
}

function getHighscore($array = array()) {

	global $HSmysqli;

	$defaultArray = array(
	'skill' => 'attack',
	'amount' => 0,
	'order' => 'DESC',
	'type' => 'exp'
	);
	
	$return = array();
	
	foreach($defaultArray as $key => $value) {
		$$key = $value;
		if(array_key_exists($key, $array)) {
			$$key = $array[$key];
		}
	}
	
	//Create Query
	$query = "SELECT userid, {$skill}_lvl, {$skill}_exp FROM highscores ORDER BY {$skill}_{$type} {$order}";
	if($type == 'lvl') {
		$query .= ", {$skill}_exp {$order}";
	}
	if($amount > 0) {
		$query .= " LIMIT {$amount}";
	}
	
	if($results = $HSmysqli->query($query)) {
	
		//Fetch array
		while($row = $results->fetch_assoc()) {
			$return[] = $row;
		}
	
	}
	
	$results->free();
	
	return $return;
}

function getRidOfNonMembers() {
	global $HSmysqli;
	
	$ranks = array(40,41,39,44,38);
	
	$getUsers = "SELECT `userid` FROM `highscores`";
	
		if($results = $HSmysqli->query($getUsers)) {

		//Fetch array
		while($row = $results->fetch_assoc()) {
		$remove = true;
			$getUsers1 = "SELECT `usergroup` FROM `mybb_users` WHERE `uid` = '{$row['userid']}'";
				$resultsGet = $HSmysqli->query($getUsers1);
				$row1 = $resultsGet->fetch_assoc();
				
				foreach($ranks as $rank) {
					if($rank == $row1['usergroup']) {
						$remove = false;
					}
				}

				if($remove) {
					$query1 = "DELETE FROM `highscores` WHERE `userid` = '{$row['userid']}'";
					$HSmysqli->query($query1);
				}
		}
	
	}
	print "Updated";

}

function generateHSRanks() {
	global $HSmysqli, $skills;

	//Automatic Rank System
	
	
	//Loop Skills.
	
	$types = array('exp', 'lvl');
	
	foreach($skills as $skill) {
		
		//Setup HSmysqli
				/*
			$defaultArray = array(
			'skill' => 'overall',
			'amount' => 0,
			'order' => 'DESC',
			'type' => 'exp'
			);
			*/
			
			
		foreach($types as $type) {
		$rank = 1;
			//HS Settings
			$hsSettings = array(
				'skill' => $skill,
				'amount' => 0,
				'order' => 'DESC',
				'type' => $type
				);
				
			$highscores = getHighscore($hsSettings);
				
			foreach($highscores as $highscore) {
				$uid = $highscore['userid'];
			
				$query = "UPDATE `hsranks` SET `{$skill}_{$type}_rank` = '{$rank}' WHERE `uid` = {$uid}";

				if($HSmysqli->query($query)) {
					if($HSmysqli->affected_rows == 0) {
						$queryInsertPlayer = "INSERT INTO `hsranks` (`uid`, `{$skill}_{$type}_rank`) VALUES ('{$uid}', '{$rank}')";

						if($HSmysqli->query($queryInsertPlayer)) {
							//
						} 
					}
				}
				$rank++;
			}
		}
	}
print "Updated";
}



if($id == "REMOVE" AND $user == "REMOVE") {
	getRidOfNonMembers();
}
elseif($id == "GENERATE" AND $user == "GENERATE") {

generateHSRanks();

}
else {
updateHighscore($id, $user);
}

$HSmysqli->close();
