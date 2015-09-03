<?php 
$HSmysqli = new mysqli('', '', '', '');
if ($HSmysqli->connect_error) {
	die('Connect Error (' . $HSmysqli->connect_errno . ') ' . $HSmysqli->connect_error);
}

$userArray = array();

function getRSName($id) {
	global $HSmysqli;
	
	$queryFid6 = "SELECT fid6 FROM `mybb_userfields` WHERE `ufid` = '{$id}'";
	if($resultFid = $HSmysqli->query($queryFid6)) {
		$resultFid = $resultFid->fetch_assoc();
		$fidRSname = $resultFid['fid6'];
		return $fidRSname;
	}

}

	
	$queryUsers = "SELECT uid FROM `mybb_users` WHERE `usergroup` IN (40,41,39,44,38)";
	
	if($results = $HSmysqli->query($queryUsers)) {
	
		//Fetch array
		while($row = $results->fetch_assoc()) {
			$id = $row['uid'];
			$rsname = getRSName($id);
			if($rsname != "") {
				$userArray[] = array($id, $rsname);
			}
			}
			$userArray[] = array("REMOVE", "REMOVE");
			$userArray[] = array("GENERATE", "GENERATE");
		}


$HSmysqli->close();

?>

<!DOCTYPE html>
<html>
<head>
<script src="http://ajax.googleapis.com/ajax/libs/jquery/1.11.2/jquery.min.js"></script>
<script>
$(document).ready(function(){
	
	//Make Var
	var memberlist = <?php print json_encode($userArray); ?>;
	var truecounter = 0;
	
	$.each(memberlist, function(index, value) {
		$("body").append("<p id='" + index +"'>" + value[1] +"...</p>").css("text-align", "center");
	});
	
	function updateList(start) {
	
		var removeID = start-20;
	
		$("#" + removeID).hide();
		
		for (i = start; i < start+1; i++) {
		
			if(memberlist.length < i) {
				break;
			}
			
		$("#" + i).append("Attempting...");
		
		$.get("stuff.php", {id: memberlist[i][0], user: memberlist[i][1]}).done(function( stuffreturn ) {
			$("#" + truecounter).append(stuffreturn);
			truecounter++;
				if(i <= start+1) {
					updateList(start+1);
				}
		});
		
		}
	
	}
	
	updateList(0);
	
});
</script>
</head>
<body>

</body>
</html>
