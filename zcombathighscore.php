<?php
/*
 *	Clan Highscore Version 4
 *  Created By Zam
*/
set_time_limit(600);
$HSmysqli = new mysqli('', '', '', '');
if ($HSmysqli->connect_error) {
	die('Connect Error (' . $HSmysqli->connect_errno . ') ' . $HSmysqli->connect_error);
}

$skills = array(
	"overall","attack","defence","strength","hitpoints","range","prayer","magic","cooking",
	"woodcutting","fletching","fishing","firemaking","crafting","smithing","mining","herblore",
	"agility","thieving","slayer","farming","runecrafting","hunter","construction"
);

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
	
	if (stripos(strtolower($data), 'page not found') == true)
	return 0;
	
	$dataBreak = explode("\n", $data);
	$userData = getUserStats($id);
	
	//[0] => rank [1] => lvl [2] =>  exp
	foreach($skills as $skill) {
		$dataLine = explode(',', $dataBreak[$i]);
		$newlvl = $dataLine[1];
		$newexp = $dataLine[2];
		
		$oldlvl = $userData[$skill . '_lvl'];
		$oldexp = $userData[$skill . '_exp'];
		
		if($newlvl > $oldlvl) {
			print "{$skill} new lvl {$newlvl} <br />";
			addAnnouncement($id, "achieved lvl {$newlvl} in {$skill}.");
			updateStats($id, $skill, 'lvl', $newlvl);
		}
		
		if($newexp > $oldexp) {
		
		//See if its an even mill.
		$subStringNewExp = substr($newexp, 0, -6);
		$subStringOldExp = substr($oldexp, 0, -6);
		
		if($subStringNewExp > $subStringOldExp) {
		
			addAnnouncement($id, "achieved {$subStringNewExp}M in {$skill}.");
		}
			print "{$skill} new exp {$newexp} <br />";
			updateStats($id, $skill, 'exp', $newexp);
		}
		$i++;
	}
	
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
					if($HSmysqli->affected_rows <= 0) {
						$queryInsertPlayer = "INSERT INTO `hsranks` (`uid`, `{$skill}_{$type}_rank`) VALUES ('{$uid}', '{$rank}')";

						if($HSmysqli->query($queryInsertPlayer)) {
							return true;
						} 
					}
				}
				$rank++;
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

function addAnnouncement($userid, $msg) {
	global $HSmysqli;
	
	$query = "INSERT INTO `achievements` (`id`, `userid`, `message`, `date`) VALUES (NULL, '{$userid}', '{$msg}', CURRENT_DATE())";
	
	if($HSmysqli->query($query)) {
		return true;
	} 
}

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

function runHighscoreUpdater() {
	global $HSmysqli;
	
	$queryUsers = "SELECT uid FROM `mybb_users` WHERE `usergroup` IN (40,41,39,44,38)";
	
	if($results = $HSmysqli->query($queryUsers)) {
	
		//Fetch array
		while($row = $results->fetch_assoc()) {
			$id = $row['uid'];
			updateHighscore($id, getRSName($id));
			}
		}

}

function getRSName($id) {
	global $HSmysqli;
	
	$queryFid6 = "SELECT fid6 FROM `mybb_userfields` WHERE `ufid` = '{$id}'";
	if($resultFid = $HSmysqli->query($queryFid6)) {
		$resultFid = $resultFid->fetch_assoc();
		$fidRSname = $resultFid['fid6'];
		return $fidRSname;
	}

}

function getStyleByUserID($id) {
	global $HSmysqli;
	
	$queryUser = "SELECT usergroup FROM `mybb_users` WHERE `uid` = '{$id}'";
	if($result = $HSmysqli->query($queryUser)) {
		$resultAssoc = $result->fetch_assoc();
		$usergroup = $resultAssoc['usergroup'];
		
		$queryNameStyle = "SELECT namestyle FROM `mybb_usergroups` WHERE `gid` = '{$usergroup}'";
		if($resultStyle = $HSmysqli->query($queryNameStyle)) {
			$resultAssoc = $resultStyle->fetch_assoc();
			$resultAssocStyle = $resultAssoc['namestyle'];
			return $resultAssocStyle;
		}
	}
}


//Updater Bypasser.

error_reporting(E_ALL);
ini_set('display_errors', '1');
define('IN_MYBB', 1);
require dirname(__FILE__) . "/global.php";
add_breadcrumb("zCombat Clan Highscore", "zcombathighscore.php");
set_time_limit(600);

if(isset($_GET['update'])) {
	if($_GET['update'] == 'updatenow') {
		runHighscoreUpdater();
	}
}
elseif(isset($_GET['player'])) {
	$uid = $_GET['player'];
	$user	=	getRSName($uid);
	$style	=	getStyleByUserID($uid);
	$userStyled = str_replace('{username}', $user, $style);

	
		$display = "<table border='0' width='100%'><tr><td valign='top'><table border='0' cellspacing='0' cellpadding='5' class='tborder'><thead><tr><td class='thead' colspan='4'><div><strong>Ranks - {$userStyled}</strong></div></td></tr></thead><tbody><tr>
<td class='tcat' align='center' valign='top'><strong>Skill</strong></td>
<td class='tcat' align='center' valign='top'><strong>Level Rank</strong></td>
<td class='tcat' align='center' valign='top'><strong>Experience Rank</strong></td>
</tr>
";

	foreach($skills as $skill) {
	
		$query = "SELECT * FROM hsranks WHERE uid = {$uid}";
		if($results = $HSmysqli->query($query)) {

		//Fetch array
		while($row = $results->fetch_assoc()) {
			$display .= "<tr><td class='trow1' align='center' valign='top'><strong><img src='skills/{$skill}.gif'>" . ucfirst($skill) . "</strong></td>
<td class='trow1' align='center' valign='top'><strong>{$row[$skill . '_lvl_rank']}</strong></td>
<td class='trow1' align='center' valign='top'><strong>{$row[$skill . '_exp_rank']}</strong></td></tr>";
		}

		}
	}


$display .= "</tbody>
         </table></td>";
		 
		 //Get Logs
		 
		$display .= "<td valign='top'><table border='0' cellspacing='0' cellpadding='5' class='tborder'><thead><tr><td class='thead' colspan='4'><div><strong>Recent Achievements</strong></div></td></tr></thead><tbody><tr>
	<td class='tcat' align='center' valign='top'><strong>Achievement</strong></td>
	<td class='tcat' align='center' valign='top'><strong>Date</strong></td></tr>
	";

		$query = "SELECT * FROM achievements WHERE userid = {$uid} ORDER BY id DESC LIMIT 30";
		if($results = $HSmysqli->query($query)) {

		//Fetch array
		while($row = $results->fetch_assoc()) {
			$display .= "<tr><td class='trow1' align='center' valign='top'>{$userStyled} {$row['message']}</td><td class='trow1' align='center' valign='top'>{$row['date']}</td></tr>";
		}

		}

	$display .= "
         </table></td></tr></table>";
}
elseif(isset($_GET['skill'])) {

	/*
	$defaultArray = array(
	'skill' => 'overall',
	'amount' => 0,
	'order' => 'DESC',
	'type' => 'exp'
	);
	*/

	$highscoreSettings = array('skill' => 'overall');
	$skill = 'Overall';
	
	if(isset($_GET['skill'])) {
		$highscoreSettings['skill'] = $_GET['skill'];
		$skill = ucfirst($_GET['skill']);
	}
	
	if(isset($_GET['order'])) {
		if($_GET['order'] == 'DESC' || $_GET['order'] == 'ASC') {
			$highscoreSettings['order'] = $_GET['order'];
		}
		else {
			die("Possible Hacking attempted... Information Recorded.");
		}
	}

	if(isset($_GET['type'])) {
	if($_GET['type'] == 'lvl' || $_GET['type'] == 'exp') {
		$highscoreSettings['type'] = $_GET['type'];
	}
	else {
		die("Possible Hacking attempted... Information Recorded.");
	}
}
	$defaultHS = getHighscore($highscoreSettings);
	$rank = 1;
	
		$display = "<table border='0' cellspacing='0' cellpadding='5' class='tborder'><thead><tr><td class='thead' colspan='4'><div><strong>{$skill}</strong></div></td></tr></thead><tbody><tr>
<td class='tcat' align='center' valign='top'><strong>Rank</strong></td>
<td class='tcat' align='center' valign='top'><strong>Username</strong></td>
<td class='tcat' align='center' valign='top'><strong><a href='zcombathighscore.php?skill={$skill}&type=lvl'>[Level]</a></strong></td>
<td class='tcat' align='center' valign='top'><strong><a href='zcombathighscore.php?skill={$skill}&type=exp'>[Experience]</a></strong></td></tr>
";
	
	foreach($defaultHS as $highscore) {
		$user	=	getRSName($highscore['userid']);
		$lvl	=	number_format($highscore[$highscoreSettings['skill'] . '_lvl']);
		$exp	=	number_format($highscore[$highscoreSettings['skill'] . '_exp']);
		$style	=	getStyleByUserID($highscore['userid']);
		$userStyled = str_replace('{username}', $user, $style);
		
		$tdClass = "trow1";
		
		if($mybb->user['uid'] == $highscore['userid'])
			$tdClass = "thead";
		
		$display .= "<tr><td class='{$tdClass}' align='center' valign='top'>{$rank}</td>
		<td class='{$tdClass}' align='center' valign='top'><a href='zcombathighscore.php?player={$highscore['userid']}'>{$userStyled}</a></td>
		<td class='{$tdClass}' align='center' valign='top'>{$lvl}</td>
		<td class='{$tdClass}' align='center' valign='top'>{$exp}</td></tr>";
		$rank++;
	}
	
		$display .= "</tbody>
         </table>";

}
else {
	
	/*
	$defaultArray = array(
	'skill' => 'overall',
	'amount' => 0,
	'order' => 'DESC',
	'type' => 'exp'
	);
	*/
	
	//Start table
	
	//Loops Each Skill
	
	$rank = 1;
	$display = '';
	$onoff = 1;
	
	foreach($skills as $skill) {
	
		//Make Default Array
			$defaultArray = array(
				'skill' => $skill,
				'amount' => 5,
				'order' => 'DESC',
				'type' => 'lvl'
				);
			
			$hss = getHighscore($defaultArray);
			
			if($onoff == 1) {
			$display .= '<tr>';
			}
			
			$display .= "<td><table border='0' cellspacing='0' cellpadding='5' class='tborder'><thead><tr><td class='thead' colspan='4'><div><strong><img src='skills/{$skill}.gif'><a href='zcombathighscore.php?skill={$skill}'>" . ucfirst($skill) . "<a/></strong></div></td></tr></thead><tbody><tr>
			<td class='tcat' align='center' valign='top'><strong>Rank</strong></td>
			<td class='tcat' align='center' valign='top'><strong>Username</strong></td>
			<td class='tcat' align='center' valign='top'><strong><a href='zcombathighscore.php?skill={$skill}&type=lvl'>[Level]</a></strong></td>
			<td class='tcat' align='center' valign='top'><strong><a href='zcombathighscore.php?skill={$skill}&type=exp'>[Experience]</a></strong></td></tr>
			";
			
			foreach($hss as $highscore) {
				$user	=	getRSName($highscore['userid']);
				$lvl	=	number_format($highscore[$skill . '_lvl']);
				$exp	=	number_format($highscore[$skill . '_exp']);
				$style	=	getStyleByUserID($highscore['userid']);
				$userStyled = str_replace('{username}', $user, $style);
			
				$tdClass = "trow1";
		
				if($mybb->user['uid'] == $highscore['userid'])
					$tdClass = "thead";
				
				$display .= "<tr><td class='{$tdClass}' align='center' valign='top'>{$rank}</td>
				<td class='{$tdClass}' align='center' valign='top'><a href='zcombathighscore.php?player={$highscore['userid']}'>{$userStyled}</a></td>
				<td class='{$tdClass}' align='center' valign='top'>{$lvl}</td>
				<td class='{$tdClass}' align='center' valign='top'>{$exp}</td></tr>";
				$rank++;
			}
			
			$rank = 1;
			
		$display .= "</tbody>
         </table></td>";
		 
		 if($onoff >= 2) {
			$display .= '</tr>';
			$onoff = 0;
			}
			
			$onoff++;
	
	}

	//End Table
	
	$display = "<table border='0' width='100%' cellspacing='0' cellpadding='5'>{$display}</table>";
    
}

    eval("\$html = \"" . $templates->get("chs") . "\";");
    
    output_page($html);

$HSmysqli->close();

















