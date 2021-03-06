<?php

date_default_timezone_set('America/New_York');

// these values are used to determine if a tablet IN or OUT time is within a reasonable range
// the time is in 12h format with no leading zeros and no punctuation
$centerOpenTime = 545;
$centerCloseTime = 615;

// dates and times when the center is closed
$aHolidays = array("2015-5-25 00:00","2015-7-3 14:00","2015-9-4 00:00","2015-9-7 00:00","2015-11-26 00:00","2015-11-27 00:00","2015-12-24 14:00","2015-12-25 00:00","2015-12-31 14:00","2016-1-1 00:00");	

// meal and snack times
$aMealTimes["breakfast"] = array("start"=>"0730","end"=>"0900");
$aMealTimes["am_snack"] = array("start"=>"0930","end"=>"0930");
$aMealTimes["lunch"] = array("start"=>"1100","end"=>"1300");
$aMealTimes["pm_snack"] = array("start"=>"1430","end"=>"1630");


// how many days to look back when generating the executive summary
$daysToGoBack = 28;

// pWeb credentials
$aPwebCredentials = getExternalSiteCredentials("pweb");

// CCIDS credentials
$aCCIDScredentials = getExternalSiteCredentials("ccids");

/**    Returns the offset from the origin timezone to the remote timezone, in seconds.
*    @param $remote_tz;
*    @param $origin_tz; If null the servers current timezone is used as the origin.
*    @return int;
*/

function get_timezone_offset($remote_tz, $origin_tz = null) {
    if($origin_tz === null) {
        if(!is_string($origin_tz = date_default_timezone_get())) {
            return false; // A UTC timestamp was returned -- bail out!
        }
    }
    $origin_dtz = new DateTimeZone($origin_tz);
    $remote_dtz = new DateTimeZone($remote_tz);
    $origin_dt = new DateTime("now", $origin_dtz);
    $remote_dt = new DateTime("now", $remote_dtz);
    $offset = $origin_dtz->getOffset($origin_dt) - $remote_dtz->getOffset($remote_dt);
    return $offset;
}
// calculate the offset between the server time and EST
$tzoffset = get_timezone_offset('America/New_York',date_default_timezone_get());


function generateRandomString($length = 10) {
    $characters = str_shuffle('0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ');
    $randomString = '';
    for ($i = 0; $i < $length; $i++) {
        $randomString .= $characters[rand(0, strlen($characters) - 1)];
    }
    return $randomString;
}

function generateRandomStringNoUpper($length = 10) {
    $characters = str_shuffle('0123456789abcdefghijklmnopqrstuvwxyz');
    $randomString = '';
    for ($i = 0; $i < $length; $i++) {
        $randomString .= $characters[rand(0, strlen($characters) - 1)];
    }
    return $randomString;
}

function checkLoginToken($login_token){
	GLOBAL $dbconn;

	$goBack = (1 * 60 * 60);
	// hours * minutes * seconds	
	
	$sql = "SELECT `last_login` FROM `user` WHERE `login_token` = '".mysql_real_escape_string($login_token)."';";
	$result = mysql_query($sql);
	if($row = mysql_fetch_assoc($result)){

		//echo date("h:i a")."<br>";
		//echo $row["last_login"]."<br>";
		//echo strtotime($row["last_login"]) ."<br>";
		//echo (time() - $goBack)."<br>";		
		
		if(strtotime($row["last_login"]) >= (time() - $goBack)){
			return true;
		} else {
			return false;		
		}	
	} else {
		return false;	
	}
	mysql_free_result($result);
}

function displayOpenHTML($title){
	
	echo "<html>\n";
	echo "<head>\n";
	echo "<title>".$title."</title>\n";
	echo "<style type=\"text/css\">\n";
	include_once($_SERVER["DOCUMENT_ROOT"]."/emarc/css/font-awesome.css");
	include_once($_SERVER["DOCUMENT_ROOT"]."/emarc/css/main.css");
	echo "</style>\n";
	echo "<!--[if gte IE 9]>\n";
	echo "  <style type=\"text/css\">\n";
	echo "    .gradient {\n";
	echo "       filter: none;\n";
	echo "    }\n";
	echo "  </style>\n";
	echo "<![endif]-->\n";
	echo "<script type=\"text/javascript\">\n";
	echo "function showHide(divId){\n";
	echo "	myDiv = document.getElementById(divId);\n";
	echo "	if(document.getElementById(divId).style.display == 'block'){\n";
	echo "		document.getElementById(divId).style.display = 'none';\n"; 	
	echo "	} else {\n";
	echo "		document.getElementById(divId).style.display = 'block';\n";
	echo "	}";	
	echo "}\n";
	echo "</script>";
	echo "</head>\n";
	echo "<body>\n";
}

function displayCloseHTML(){
	echo "</body>";
	echo "</html>";	
}	
function displayLeftNavigation(){

	echo "<div id=\"navigation\">\n";
	echo "<ul>";
	if(strpos($_SERVER["REQUEST_URI"],"emarc.php") === false){
		echo "<li><span class=\"fa fa-home fa-lg fa-fw\"></span><a href=\"/emarc/\">EMARC Home</a></li>";
	}
	if($_SESSION["permissions_levelFK"] > 2){
		echo "<li><span class=\"fa fa-user fa-lg fa-fw\"></span>";
		echo "<a href=\"manage.users.php\">Manage Account</a>";
		echo "</li>\n";
	} else {
//		echo "<li><span class=\"fa fa-graduation-cap fa-lg fa-fw\"></span><a href=\"manage.teachers.php\">Manage Teachers</a></li>\n";
		echo "<li><span class=\"fa fa-users fa-lg fa-fw\"></span>";
		echo "<a href=\"manage.users.php\">EMARC Users</a>";
		echo "</li>\n";
		
		echo "<li><span class=\"fa fa-certificate fa-lg fa-fw\"></span>";
		echo "<a href=\"manage.credentials.php\">Import Credentials</a>";
		echo "</li>\n";	
			
	}
	echo "<li class=\"align_right\"><a href=\"logout.php\" class=\"button\"><span>Logout</span></a></li>";
	echo "</ul>\n";
	echo "<br class=\"clear\">\n";
	echo "</div>\n";

}

function getFreeReduced($childId, $centerId){
	// if we have found a Title20 child, check to see if they pay a reduced rate, or are free

	GLOBAL $dbconn;	
	
	$sql  = "SELECT ";
	$sql .= "`CFIRSTNAME` ";
	$sql .= ", `CLASTNAME` ";
	$sql .= "FROM `ez2_import-tcChild` ";
	$sql .= "WHERE `ICHILD_ID` = '".mysql_real_escape_string($childId)."' ";
	$sql .= "AND `ICAB_ID` = '".mysql_real_escape_string($centerId)."' ";
	$sql .= ";"; 	
	
	$result = mysql_query($sql,$dbconn);
	if($row = mysql_fetch_assoc($result)){
			
		$sql1  = "SELECT ";
		$sql1 .= "`fam_fee` ";
		$sql1 .= "FROM `ccids_import-transactions` ";
		$sql1 .= "WHERE `cfname` = '".$row["CFIRSTNAME"]."' ";
		$sql1 .= "AND `clname` = '".$row["CLASTNAME"]."' ";
		$sql1 .= "AND `ICAB_ID` = '".mysql_real_escape_string($centerId)."' ";
		$sql1 .= ";";

		$result1 = mysql_query($sql1,$dbconn);
		if($result1 && $row1 = mysql_fetch_assoc($result1)){
			return ($row1["fam_fee"] > 0?"free":"reduced");
			mysql_free_result($result1);
		}
	}	
	mysql_free_result($result);
}	
function getTitle20($childId, $centerId){

	GLOBAL $dbconn;	
	
	// lookup to see if child is title 20 or not. 
	// different centers store the title 20 data in different places... why? 'cause ez2 is gross.
	
	/* ==================================  
	Title 20 Identification (Bool) per family:

	Center - DataSource - Column

	215 State - DBCHILD.DBF (ez2_import-dbChild) - U311729
	216 Canal - ARMASTER.DBF (ez2_import-arMaster) - U323326
	217 Dempsey - ARMASTER.DBF (ez2_import-arMaster) - U328161
	218 Hempwood - DBCHILD.DBF (ez2_import-dbChild) - U311729
	==================================	*/
	
	$aTableCol[215] = array("table"=>"ez2_import-dbChild","column"=>"U311729");
	$aTableCol[216] = array("table"=>"ez2_import-arMaster","column"=>"U323326");
	$aTableCol[217] = array("table"=>"ez2_import-arMaster","column"=>"U328161");
	$aTableCol[218] = array("table"=>"ez2_import-dbChild","column"=>"U311729");

	if($centerId == 216 || $centerId == 217){

		$sql  = "SELECT `".$aTableCol[$centerId]["column"]."` AS 'title20' ";
		$sql .= "FROM `".$aTableCol[$centerId]["table"]."` ";
		$sql .= "WHERE `ILEDGER_ID` = ";
		$sql .= "( SELECT DISTINCT(`ILEDGER_ID`) ";
		$sql .= "FROM `ez2_import-tcLedger` ";
		$sql .= "WHERE `ICHILD_ID` = '".mysql_real_escape_string($childId)."' ";
		$sql .= "AND `ICAB_ID` = '".mysql_real_escape_string($centerId)."' )";
//		$sql .= "AND `ICAB_ID` = '".mysql_real_escape_string($centerId)."' ";
		$sql .= ";";

	} else if($centerId == 215 || $centerId == 218){

		$sql  = "SELECT `".$aTableCol[$centerId]["column"]."` AS 'title20' ";
		$sql .= "FROM `".$aTableCol[$centerId]["table"]."` ";
		$sql .= "WHERE `ICHILD_ID` = '".mysql_real_escape_string($childId)."' ";
		$sql .= "AND `ICAB_ID` = '".mysql_real_escape_string($centerId)."' ";
		$sql .= ";";
	
	} 

	// debug
	//echo $sql."<br>";

	$result = mysql_query($sql,$dbconn);
	if($row = mysql_fetch_assoc($result)){
		if(!empty($row["title20"])){
			return true;		
		}	
	}	
	
	return false;
	mysql_free_result($result);
}

function getTitle20SwipeForDate($childId, $classId, $centerId, $date){
	
	GLOBAL $dbconn;	

	$isTitle20 = getTitle20($childId, $centerId);	

	$sRet = null;	
	
	if($isTitle20 === true ){
		
		$sql  = "SELECT ";
		$sql .= "DISTINCT(`ICHILD_ID`) AS 'ICHILD_ID' ";
	//	$sql .= ", `ICLASS_ID` ";
	//	$sql .= ", `ICAB_ID` ";
		$sql .= " ,`source` ";
	//	$sql .= ", `in_datetime` ";
	//	$sql .= ", `in_status` ";
	//	$sql .= ", `out_datetime` ";
	//	$sql .= ", `out_status` ";
		$sql .= "FROM `student-attendance` ";
		$sql .= "WHERE 1 = 1 ";
		$sql .= "AND (`source` = 'tablet' OR `source` = 'pweb_import-transaction') ";
		$sql .= "AND `ICHILD_ID` = '".mysql_real_escape_string($childId)."' ";
		$sql .= "AND `ICLASS_ID` = '".mysql_real_escape_string($classId)."' ";
		$sql .= "AND `ICAB_ID` = '".mysql_real_escape_string($centerId)."' ";
		$sql .= "AND YEAR(`in_datetime`) = '".date("Y",strtotime($date))."' ";
		$sql .= "AND MONTH(`in_datetime`) = '".date("m",strtotime($date))."' ";
		$sql .= "AND DAY(`in_datetime`) = '".date("d",strtotime($date))."' ";
		$sql .= "AND `out_datetime` IS NOT NULL ";
		$sql .= "ORDER BY `source` DESC ";
		$sql .= ";";
		
		$result = mysql_query($sql, $dbconn);

		if($row = mysql_fetch_assoc($result)){
			do{
				if($row["source"] != 'tablet'){
					$sRet = true;			
				} else {
					$sRet = false;				
				}
			} while($row = mysql_fetch_assoc($result));			
		}
		
		mysql_free_result($result);
	}
	
	return $sRet;

}

function daysMinusWeekends($daysToGoBack){

	GLOBAL $aHolidays; 
	
	foreach($aHolidays as $k => $v){
		$aHolidayCompare[] = date("Ymd",strtotime($v));
	}
	
	$weekendDays = 0;	
	$holidayDays = 0;	
	for($i = 0;$i < $daysToGoBack;$i++){
		if( in_array( date("Ymd",mktime(0, 0, 0, date("m")  , date("d")-$i, date("Y"))),$aHolidayCompare) ){
			$holidayDays++;		
		} else if( date("N",mktime(0, 0, 0, date("m")  , date("d")-$i, date("Y"))) > 5){
			$weekendDays++;		
		}
	}
	return $daysToGoBack - ($weekendDays + $holidayDays);
}

function scheduledDaysInRange($daysToGoBack, $aSchedule){
	GLOBAL $aHolidays; 

	// DEBUG
	//echo "IN: ".date("h:i:s",time())."<br>";	
	
	foreach($aHolidays as $k => $v){
		$aHolidayCompare[] = date("Ymd",strtotime($v));
	}	
	
	$scheduledDays = 0;
	$holidayDays = 0;
	
	// if an empty schedule is passed, assume attendance for all days
	if(empty($aSchedule)){ 
		$aSchedule[1] = "06:00A-06:00P";
		$aSchedule[2] = "06:00A-06:00P";
		$aSchedule[3] = "06:00A-06:00P";
		$aSchedule[4] = "06:00A-06:00P";
		$aSchedule[5] = "06:00A-06:00P";
	}
	
	for($i = 0;$i < $daysToGoBack;$i++){
		if( !empty($aSchedule[date("N",mktime(0, 0, 0, date("m")  , date("d")-$i, date("Y")))]) && !in_array( date("Ymd",mktime(0, 0, 0, date("m")  , date("d")-$i, date("Y"))),$aHolidayCompare) ){
			$scheduledDays++;		
		}
	}
	
	// DEBUG
	//echo "OUT: ".date("h:i:s",time())."<br>";	
	
	return $scheduledDays;
}

function getCenterCapacity($ICAB_ID){
	
	switch($ICAB_ID){
		case 215: return 140;
			break;
		case 216: return 94;
			break;
		case 217: return 114;
			break;
		case 218: return 97;
			break;
	}
	
}

function getCenterCurrentAttendance($ICAB_ID){
	GLOBAL $dbconn;

	$studentCount = 0;
	
	$sql  = "SELECT COUNT(DISTINCT(`ICHILD_ID`)) as 'cnt' ";
	$sql .= "FROM `student-attendance` ";
	$sql .= "WHERE `ICAB_ID` = '".mysql_real_escape_string($ICAB_ID)."' ";
	$sql .= "AND (YEAR(`in_datetime`) = '".date("Y")."' ";
	$sql .= "AND MONTH(`in_datetime`) = '".date("m")."' ";
	$sql .= "AND DAY(`in_datetime`) = '".date("d")."') ";
	$sql .= "AND `out_datetime` IS NULL ";
	$sql .= ";";

	$result = mysql_query($sql,$dbconn);
	while($row = mysql_fetch_assoc($result)){
		$studentCount = $row["cnt"]; 	
	}
	mysql_free_result($result);
	return $studentCount;
}

function getCenterAttendanceForDate($ICAB_ID,$epochDate){
	GLOBAL $dbconn;

	$studentCount = 0;
	
	$sql  = "SELECT COUNT(DISTINCT(`ICHILD_ID`)) as 'cnt' ";
	$sql .= "FROM `student-attendance` ";
	$sql .= "WHERE `ICAB_ID` = '".mysql_real_escape_string($ICAB_ID)."' ";
	$sql .= "AND YEAR(`in_datetime`) = '".date("Y",$epochDate)."' ";
	$sql .= "AND MONTH(`in_datetime`) = '".date("m",$epochDate)."' ";
	$sql .= "AND DAY(`in_datetime`) = '".date("d",$epochDate)."' ";
	$sql .= "AND `in_datetime` IS NOT NULL ";
	$sql .= ";";

	$result = mysql_query($sql,$dbconn);
	while($row = mysql_fetch_assoc($result)){
		$studentCount = $row["cnt"]; 	
	}
	mysql_free_result($result);
	return $studentCount;
}

function getCenterClassCount($ICAB_ID){
	GLOBAL $dbconn;
	
	$classCount = 0;
	
	$sql  = "SELECT DISTINCT(`ICLASS_ID`) ";
	$sql .= "FROM `ez2_import-tcClass` ";
	$sql .= "WHERE `ICAB_ID` = '".mysql_real_escape_string($ICAB_ID)."' ";
	$sql .= "AND `CCLASS_NAM` NOT IN ('Office','Director') ";
	$sql .= ";";

	// DEBUG
	//echo '<br>'.$sql.'<br>';

	$result = mysql_query($sql,$dbconn);
	while($row = mysql_fetch_assoc($result)){
		$aStudents = getCenterClassStudentIds($ICAB_ID,$row["ICLASS_ID"]);
		if(!empty($aStudents)){
			$classCount++;
		} 	
	}
	mysql_free_result($result);
	return $classCount;
}

function getCenterClassAttendanceReporting($ICAB_ID){
	GLOBAL $dbconn;
	
	$classCount = 0;
	
	//$sql  = "SELECT COUNT(DISTINCT(`ICLASS_ID`)) as 'cnt' ";
	$sql  = "SELECT DISTINCT(`ICLASS_ID`) ";
	$sql .= "FROM `student-attendance` ";
	$sql .= "WHERE `ICAB_ID` = '".mysql_real_escape_string($ICAB_ID)."' ";
	$sql .= "AND YEAR(`in_datetime`) = '".date("Y")."' ";
	$sql .= "AND MONTH(`in_datetime`) = '".date("m")."' ";
	$sql .= "AND DAY(`in_datetime`) = '".date("d")."' ";
	$sql .= ";";

	// DEBUG
	//echo '<br>'.$sql.'<br>';

	$result = mysql_query($sql,$dbconn);
	while($row = mysql_fetch_assoc($result)){
		$aStudents = getCenterClassStudentIds($ICAB_ID,$row["ICLASS_ID"]);
		if(!empty($aStudents)){		
//			$classCount = $row["cnt"]; 	
			$classCount++;
		} 	
	}
	mysql_free_result($result);
	return $classCount;
}

function getAttendanceOldestNewest($cid){
	GLOBAL $dbconn;

	$aRet = null;	
	
	$sql = "SELECT MIN(DISTINCT(`in_datetime`)) AS 'min_date',  MAX(DISTINCT(`in_datetime`)) AS 'max_date' FROM `student-attendance` WHERE `in_datetime` IS NOT NULL AND `in_datetime` > 0 AND  `ICAB_ID` = '".mysql_real_escape_string($cid)."';";
	$result = mysql_query($sql, $dbconn);

	$firstAvailableWeek = null;
	$lastAvailableWeek = null;	
	
	while($row = mysql_fetch_assoc($result)){
		$firstAvailableWeek = strtotime($row["min_date"]);
		if(date("N",$firstAvailableWeek) > 1){
			$firstAvailableWeek = $firstAvailableWeek - ((3600 * 24) * (date("N",$firstAvailableWeek) - 1));
		}
		
		$lastAvailableWeek = strtotime($row["max_date"]);
		if(date("N",$lastAvailableWeek) < 7){
			$lastAvailableWeek = $lastAvailableWeek - ((3600 * 24) * (date("N",$lastAvailableWeek) - 1));
		}
			
	}
	mysql_free_result($result);

	$aRet = array("first"=>$firstAvailableWeek,"last"=>$lastAvailableWeek);

	return $aRet;
}

function getExternalSiteCredentials($whichSite){
	// acceptable values for whichSite are "pweb" and "ccids"
	$whichSite = !empty($whichSite)?$whichSite:"pweb";
	
	GLOBAL $dbconn;

	$aRet = null;	
	
	$sql = "SELECT * FROM `import_credentials` WHERE `external_site` = '".mysql_real_escape_string($whichSite)."';";

	$result = mysql_query($sql, $dbconn);
	while($row = mysql_fetch_assoc($result)){
		$aRet[$row["site_name"]] = array("login"=>$row["username"],"password"=>$row["password"],"ICAB_ID"=>$row["ICAB_ID"]);
	}
	mysql_free_result($result);
	return $aRet;
}

function getCenterClassStudentIds($cid, $class_id){
	GLOBAL $dbconn;
	
	$studentIds = null;
	
	$sql  = "SELECT ";
	$sql .= "DISTINCT(`ez2_import-tcChild`.`ICHILD_ID`) ";
	$sql .= ", `ez2_import-tcChild`.`CFIRSTNAME` ";
	$sql .= ", `ez2_import-tcChild`.`CLASTNAME` ";
	$sql .= ", `ez2_import-tcChild`.`CBIRTHDATE` ";
	$sql .= ", `ez2_import-tcChild`.`CADMITDATE` ";
	$sql .= "FROM `ez2_import-tcChild`, `ez2_import-dbChiSch` ";
	$sql .= "WHERE `ez2_import-tcChild`.`ICHILD_ID` = `ez2_import-dbChiSch`.`ICHILD_ID` ";
	$sql .= "AND `ez2_import-tcChild`.`ICAB_ID` = '".$cid."' ";	
	$sql .= "AND `ez2_import-tcChild`.`ICAB_ID` = `ez2_import-dbChiSch`.`ICAB_ID` ";	
	$sql .= "AND `ez2_import-dbChiSch`.`ICLASS_ID` = '".$class_id."' ";
	$sql .= "AND `ez2_import-tcChild`.`ISTATUS` = 1 ";
	$sql .= "AND `ez2_import-tcChild`.`ICHILD_ID` IS NOT NULL ";
	$sql .= "AND `ez2_import-tcChild`.`CFIRSTNAME` IS NOT NULL ";
	$sql .= "AND `ez2_import-tcChild`.`CLASTNAME` IS NOT NULL ";
	$sql .= "ORDER BY `CLASTNAME` ASC ";
	$sql .= ";";
	
	//echo $sql."<br>";
	
	$result = mysql_query($sql,$dbconn);
	while($row = mysql_fetch_assoc($result)){
		if(!empty($row["ICHILD_ID"]) && !empty($row["CFIRSTNAME"]) && !empty($row["CLASTNAME"]) ){
			$studentIds[$row["ICHILD_ID"]] = array("first_name"=>$row["CFIRSTNAME"],"last_name"=>$row["CLASTNAME"],"birthday"=>$row["CBIRTHDATE"],"admit_date"=>$row["CADMITDATE"]);
		} 	
	}
	mysql_free_result($result);
	return $studentIds;
}

function getStudentEnrollDate($student_id){
	GLOBAL $dbconn;
	
	$sql = "SELECT `CADMITDATE` FROM `ez2_import-tcChild` WHERE `ICHILD_ID` = '".mysql_real_escape_string($student_id)."';";
	$result = mysql_query($sql,$dbconn);
	if($row = mysql_fetch_assoc($result)){
		return (!empty($row["CADMITDATE"])?$row["CADMITDATE"]:false);
	} else {
		return false;	
	}
	mysql_free_result($result);
}

function getStudentWithdrawlDate($student_id){
	GLOBAL $dbconn;
	
	$sql = "SELECT `CLEAVEDATE` FROM `ez2_import-tcChild` WHERE `ICHILD_ID` = '".mysql_real_escape_string($student_id)."';";
	$result = mysql_query($sql,$dbconn);
	if($row = mysql_fetch_assoc($result)){
		return (!empty($row["CLEAVEDATE"])?$row["CLEAVEDATE"]:false);
	} else {
		return false;	
	}
	mysql_free_result($result);
}

function getStudentAttendanceForRange($student_id, $center_id, $startDate, $endDate){
	GLOBAL $dbconn;	
	GLOBAL $centerOpenTime;
	GLOBAL $centerCloseTime;
	
	$startDate = (!empty($_GET["start_date"])?strtotime($_GET["start_date"]):$startDate);
	$endDate = (!empty($_GET["end_date"])?strtotime($_GET["end_date"]):$endDate);
	
	$sql  = "SELECT ";
	$sql .= "DISTINCT(`in_datetime`) ";
	//$sql .= "`in_datetime` ";
	$sql .= ", `out_datetime` ";
	$sql .= ", `in1_datetime` ";
	$sql .= ", `out1_datetime` ";
	$sql .= ", `in2_datetime` ";
	$sql .= ", `out2_datetime` ";
	$sql .= ", `source` ";
	$sql .= ", `in_ISTAFF_ID` ";
	$sql .= ", `out_ISTAFF_ID` ";
	$sql .= ", `in1_ISTAFF_ID` ";
	$sql .= ", `out1_ISTAFF_ID` ";
	$sql .= ", `in2_ISTAFF_ID` ";
	$sql .= ", `out2_ISTAFF_ID` ";
	$sql .= "FROM `student-attendance` ";
	$sql .= "WHERE `ICHILD_ID` = '".mysql_real_escape_string($student_id)."' ";
	$sql .= "AND `ICAB_ID` = '".mysql_real_escape_string($center_id)."' ";
	$sql .= "AND UNIX_TIMESTAMP(`in_datetime`) >= '".strtotime(date("Ymd",$startDate))."' ";
	$sql .= "AND UNIX_TIMESTAMP(`in_datetime`) < '".(strtotime(date("Ymd",$endDate)) + (3600*24) )."' ";
//	$sql .= "AND `out_datetime` IS NOT NULL ";
	$sql .= "ORDER BY `in_datetime` ";
	$sql .= ", `source` DESC ";
	$sql .= ";";


	$result = mysql_query($sql,$dbconn);
	while($row = mysql_fetch_assoc($result)){
		
		$sql2  = "SELECT ";
		$sql2 .= "`NTOTAL`, ";
		$sql2 .= "`CMON_TIMES`, ";
		$sql2 .= "`CTUE_TIMES`, ";
		$sql2 .= "`CWED_TIMES`, ";
		$sql2 .= "`CTHU_TIMES`, ";
		$sql2 .= "`CFRI_TIMES` ";
		$sql2 .= "FROM `ez2_import-dbChiSch` ";
		$sql2 .= "WHERE `ICHILD_ID` = '".$student_id."' ";

		$sChildSchedule = null;
		$iChildCareHours = 0;
		$aSchedule = null;
		
		$result2 = mysql_query($sql2,$dbconn);
		
		if($row2 = mysql_fetch_assoc($result2)){
			do{
				$iChildCareHours = $row2["NTOTAL"];

				$aSchedule[1] = $row2["CMON_TIMES"]; 
				$aSchedule[2] = $row2["CTUE_TIMES"];
				$aSchedule[3] = $row2["CWED_TIMES"];
				$aSchedule[4] = $row2["CTHU_TIMES"];
				$aSchedule[5] = $row2["CFRI_TIMES"];
				
			} while ($row2 = mysql_fetch_assoc($result2));
			
			mysql_free_result($result2);
		}
		
		if( empty($aAttendance[date("Ymd",strtotime($row["in_datetime"]))]["in_ISTAFF_ID"]) && empty($aAttendance[date("Ymd",strtotime($row["in_datetime"]))]["in_datetime"]) ){
			if($row["source"] != "tablet"){
				$aAttendance[date("Ymd",strtotime($row["in_datetime"]))]["in_ISTAFF_ID"] = $row["source"];
			} else {
				$aAttendance[date("Ymd",strtotime($row["in_datetime"]))]["in_ISTAFF_ID"] = $row["in_ISTAFF_ID"];
			}				
		}
		if( empty($aAttendance[date("Ymd",strtotime($row["in_datetime"]))]["out_ISTAFF_ID"]) && empty($aAttendance[date("Ymd",strtotime($row["in_datetime"]))]["out_datetime"]) ){
			if($row["source"] != "tablet"){
				$aAttendance[date("Ymd",strtotime($row["in_datetime"]))]["out_ISTAFF_ID"] = $row["source"];				
			} else {
				$aAttendance[date("Ymd",strtotime($row["in_datetime"]))]["out_ISTAFF_ID"] = $row["out_ISTAFF_ID"];					
			}
		}
		
		// if we have an existing IN time, check which value is smaller and use the smaller value
		// check that the IN time is "reasonable", ie: not earlier than 5:45 am 
		if( !empty($aAttendance[date("Ymd",strtotime($row["in_datetime"]))]["in_datetime"]) ){
			if(date("gi",$row["in_datetime"]) >= $centerOpenTime && strtotime($row["in_datetime"]) < strtotime($aAttendance[date("Ymd",strtotime($row["in_datetime"]))]["in_datetime"]) ){
				$aAttendance[date("Ymd",strtotime($row["in_datetime"]))]["in_datetime"] = $row["in_datetime"];
				
				if($row["source"] != "tablet"){
					$aAttendance[date("Ymd",strtotime($row["in_datetime"]))]["in_ISTAFF_ID"] = $row["source"];
				} else {
					$aAttendance[date("Ymd",strtotime($row["in_datetime"]))]["in_ISTAFF_ID"] = $row["in_ISTAFF_ID"];
				}
			}
		} else {
			$aAttendance[date("Ymd",strtotime($row["in_datetime"]))]["in_datetime"] = $row["in_datetime"];
		}

		// if we have an existing OUT time, check which value is larger and use the larger value
		// check that the OUT time is "reasonable", ie: not later than 6:15 am
		if(!empty($row["out_datetime"]) ){
			//$aAttendance[date("Ymd",strtotime($row["in_datetime"]))]["out_datetime"] = $row["out_datetime"];
			//$aAttendance[date("Ymd",strtotime($row["in_datetime"]))]["source"] = $row["source"];
			
			if( !empty($aAttendance[date("Ymd",strtotime($row["in_datetime"]))]["out_datetime"]) ){
				if(date("gi",$row["out_datetime"]) <= $centerCloseTime && strtotime($row["out_datetime"]) > strtotime($aAttendance[date("Ymd",strtotime($row["in_datetime"]))]["out_datetime"]) ){
					$aAttendance[date("Ymd",strtotime($row["in_datetime"]))]["out_datetime"] = $row["out_datetime"];
					
					if($row["source"] != "tablet"){
						$aAttendance[date("Ymd",strtotime($row["in_datetime"]))]["out_ISTAFF_ID"] = $row["source"];				
					} else {
						$aAttendance[date("Ymd",strtotime($row["in_datetime"]))]["out_ISTAFF_ID"] = $row["out_ISTAFF_ID"];					
					}
				}
			} else {
				$aAttendance[date("Ymd",strtotime($row["in_datetime"]))]["out_datetime"] = $row["out_datetime"];
			}
			
		} else if(empty($row["out_datetime"]) && !empty($aSchedule) ){
			$aAttendance[date("Ymd",strtotime($row["in_datetime"]))]["out_datetime"] = "<strong>No Out Time - ".$aSchedule[date("N",strtotime($row["in_datetime"]))]."</strong>";
			$aAttendance[date("Ymd",strtotime($row["in_datetime"]))]["source"] = $row["source"];
			$aAttendance[date("Ymd",strtotime($row["in_datetime"]))]["out_ISTAFF_ID"] = $row["source"];
		} else {
			$aAttendance[date("Ymd",strtotime($row["in_datetime"]))]["out_datetime"] = "<strong>No Out Time - Missing Schedule Data</strong>";
			$aAttendance[date("Ymd",strtotime($row["in_datetime"]))]["source"] = $row["source"];
			$aAttendance[date("Ymd",strtotime($row["in_datetime"]))]["out_ISTAFF_ID"] = $row["source"];
		}
		
		if( !empty($aAttendance[date("Ymd",strtotime($row["in_datetime"]))]["in1_datetime"]) ){
			if(date("gi",$row["in1_datetime"]) >= $centerOpenTime && strtotime($row["in1_datetime"]) < strtotime($aAttendance[date("Ymd",strtotime($row["in_datetime"]))]["in1_datetime"]) ){
				$aAttendance[date("Ymd",strtotime($row["in_datetime"]))]["in1_datetime"] = $row["in1_datetime"];
				$aAttendance[date("Ymd",strtotime($row["in_datetime"]))]["in1_ISTAFF_ID"] = $row["in1_ISTAFF_ID"];
			}
		} else {
			$aAttendance[date("Ymd",strtotime($row["in_datetime"]))]["in1_datetime"] = $row["in1_datetime"];
			$aAttendance[date("Ymd",strtotime($row["in_datetime"]))]["in1_ISTAFF_ID"] = $row["in1_ISTAFF_ID"];
		}		
		
		if(!empty($row["out1_datetime"]) ){
			if( !empty($aAttendance[date("Ymd",strtotime($row["in_datetime"]))]["out1_datetime"]) ){
				if(date("gi",$row["out1_datetime"]) <= $centerCloseTime && strtotime($row["out1_datetime"]) > strtotime($aAttendance[date("Ymd",strtotime($row["in_datetime"]))]["out1_datetime"]) ){
					$aAttendance[date("Ymd",strtotime($row["in_datetime"]))]["out1_datetime"] = $row["out1_datetime"];
					$aAttendance[date("Ymd",strtotime($row["in_datetime"]))]["out1_ISTAFF_ID"] = $row["out1_ISTAFF_ID"];
				}
			} else {
				$aAttendance[date("Ymd",strtotime($row["in_datetime"]))]["out1_datetime"] = $row["out1_datetime"];
				$aAttendance[date("Ymd",strtotime($row["in_datetime"]))]["out1_ISTAFF_ID"] = $row["out1_ISTAFF_ID"];
			}
			
		} else {
			$aAttendance[date("Ymd",strtotime($row["in_datetime"]))]["out1_datetime"] = null;
			$aAttendance[date("Ymd",strtotime($row["in_datetime"]))]["out1_ISTAFF_ID"] = null;
		}
		
		if( !empty($aAttendance[date("Ymd",strtotime($row["in_datetime"]))]["in2_datetime"]) ){
			if(date("gi",$row["in2_datetime"]) >= $centerOpenTime && strtotime($row["in2_datetime"]) < strtotime($aAttendance[date("Ymd",strtotime($row["in_datetime"]))]["in2_datetime"]) ){
				$aAttendance[date("Ymd",strtotime($row["in_datetime"]))]["in2_datetime"] = $row["in2_datetime"];
				$aAttendance[date("Ymd",strtotime($row["in_datetime"]))]["in2_ISTAFF_ID"] = $row["in2_ISTAFF_ID"];
			}
		} else {
			$aAttendance[date("Ymd",strtotime($row["in_datetime"]))]["in2_datetime"] = $row["in2_datetime"];
			$aAttendance[date("Ymd",strtotime($row["in_datetime"]))]["in2_ISTAFF_ID"] = $row["in2_ISTAFF_ID"];
		}		
		
		if(!empty($row["out2_datetime"]) ){
			if( !empty($aAttendance[date("Ymd",strtotime($row["in_datetime"]))]["out2_datetime"]) ){
				if(date("gi",$row["out_datetime"]) <= $centerCloseTime && strtotime($row["out1_datetime"]) > strtotime($aAttendance[date("Ymd",strtotime($row["in_datetime"]))]["out2_datetime"]) ){
					$aAttendance[date("Ymd",strtotime($row["in_datetime"]))]["out2_datetime"] = $row["out2_datetime"];
					$aAttendance[date("Ymd",strtotime($row["in_datetime"]))]["out2_ISTAFF_ID"] = $row["out2_ISTAFF_ID"];
				}
			} else {
				$aAttendance[date("Ymd",strtotime($row["in_datetime"]))]["out2_datetime"] = $row["out2_datetime"];
				$aAttendance[date("Ymd",strtotime($row["in_datetime"]))]["out2_ISTAFF_ID"] = $row["out2_ISTAFF_ID"];
			}
			
		} else {
			$aAttendance[date("Ymd",strtotime($row["in_datetime"]))]["out2_datetime"] = null;
			$aAttendance[date("Ymd",strtotime($row["in_datetime"]))]["out2_ISTAFF_ID"] = null;
		}
		
		if(empty($aAttendance[date("Ymd",strtotime($row["in_datetime"]))]["source"])){
			$aAttendance[date("Ymd",strtotime($row["in_datetime"]))]["source"] = $row["source"];
		}
							
	}
	mysql_free_result($result);
	
	return $aAttendance;
}



function getCurrentStudentAttendanceForRange($student_id, $center_id, $startDate, $endDate){
	GLOBAL $dbconn;	
	GLOBAL $centerOpenTime;
	GLOBAL $centerCloseTime;
		
	$startDate = (!empty($_GET["start_date"])?strtotime($_GET["start_date"]):$startDate);
	$endDate = (!empty($_GET["end_date"])?strtotime($_GET["end_date"]):$endDate);
	
	$sql  = "SELECT ";
	$sql .= "DISTINCT(`in_datetime`) ";
	//$sql .= "`in_datetime` ";
	$sql .= ", `out_datetime` ";
	$sql .= ", `in1_datetime` ";
	$sql .= ", `out1_datetime` ";
	$sql .= ", `in2_datetime` ";
	$sql .= ", `out2_datetime` ";
	$sql .= ", `source` ";
	$sql .= ", `in_ISTAFF_ID` ";
	$sql .= ", `out_ISTAFF_ID` ";
	$sql .= ", `in1_ISTAFF_ID` ";
	$sql .= ", `out1_ISTAFF_ID` ";
	$sql .= ", `in2_ISTAFF_ID` ";
	$sql .= ", `out2_ISTAFF_ID` ";
	$sql .= "FROM `student-attendance` ";
	$sql .= "WHERE `ICHILD_ID` = '".mysql_real_escape_string($student_id)."' ";
	$sql .= "AND `ICAB_ID` = '".mysql_real_escape_string($center_id)."' ";
	$sql .= "AND UNIX_TIMESTAMP(`in_datetime`) >= '".strtotime(date("Ymd",$startDate))."' ";
	$sql .= "AND UNIX_TIMESTAMP(`in_datetime`) < '".(strtotime(date("Ymd",$endDate)) + (3600*24) )."' ";
//	$sql .= "AND `out_datetime` IS NOT NULL ";
	$sql .= "AND `source` = 'tablet'";
	$sql .= "ORDER BY `in_datetime` ";
	$sql .= ", `source` DESC ";
	$sql .= ";";

	$result = mysql_query($sql,$dbconn);
	while($row = mysql_fetch_assoc($result)){
		/*
		$sql2  = "SELECT ";
		$sql2 .= "`NTOTAL`, ";
		$sql2 .= "`CMON_TIMES`, ";
		$sql2 .= "`CTUE_TIMES`, ";
		$sql2 .= "`CWED_TIMES`, ";
		$sql2 .= "`CTHU_TIMES`, ";
		$sql2 .= "`CFRI_TIMES` ";
		$sql2 .= "FROM `ez2_import-dbChiSch` ";
		$sql2 .= "WHERE `ICHILD_ID` = '".$student_id."' ";

		$sChildSchedule = null;
		$iChildCareHours = 0;
		$aSchedule = null;
		
		$result2 = mysql_query($sql2,$dbconn);
		
		if($row2 = mysql_fetch_assoc($result2)){
			do{
				$iChildCareHours = $row2["NTOTAL"];

				$aSchedule[1] = $row2["CMON_TIMES"]; 
				$aSchedule[2] = $row2["CTUE_TIMES"];
				$aSchedule[3] = $row2["CWED_TIMES"];
				$aSchedule[4] = $row2["CTHU_TIMES"];
				$aSchedule[5] = $row2["CFRI_TIMES"];
				
			} while ($row2 = mysql_fetch_assoc($result2));
			
			mysql_free_result($result2);
		}
		*/
	
		if($row["source"] != "tablet"){
			$aAttendance[date("Ymd",strtotime($row["in_datetime"]))]["in_ISTAFF_ID"] = $row["source"];
			$aAttendance[date("Ymd",strtotime($row["in_datetime"]))]["out_ISTAFF_ID"] = $row["source"];				
		} else {
			$aAttendance[date("Ymd",strtotime($row["in_datetime"]))]["in_ISTAFF_ID"] = $row["in_ISTAFF_ID"];				
			$aAttendance[date("Ymd",strtotime($row["in_datetime"]))]["out_ISTAFF_ID"] = $row["out_ISTAFF_ID"];					
		}
		
		if( !empty($aAttendance[date("Ymd",strtotime($row["in_datetime"]))]["in_datetime"]) ){
			if(date("gi",$row["in_datetime"]) >= $centerOpenTime && strtotime($row["in_datetime"]) < strtotime($aAttendance[date("Ymd",strtotime($row["in_datetime"]))]["in_datetime"]) ){
				$aAttendance[date("Ymd",strtotime($row["in_datetime"]))]["in_datetime"] = $row["in_datetime"];
			}
		} else {
			$aAttendance[date("Ymd",strtotime($row["in_datetime"]))]["in_datetime"] = $row["in_datetime"];
		}

		if(!empty($row["out_datetime"]) ){

			//$aAttendance[date("Ymd",strtotime($row["in_datetime"]))]["out_datetime"] = $row["out_datetime"];
			//$aAttendance[date("Ymd",strtotime($row["in_datetime"]))]["source"] = $row["source"];
			
			if( !empty($aAttendance[date("Ymd",strtotime($row["in_datetime"]))]["out_datetime"]) ){
				if(date("gi",$row["out_datetime"]) >= $centerCloseTime && strtotime($row["out_datetime"]) > strtotime($aAttendance[date("Ymd",strtotime($row["in_datetime"]))]["out_datetime"]) ){
					$aAttendance[date("Ymd",strtotime($row["in_datetime"]))]["out_datetime"] = $row["out_datetime"];
				}
			} else {
				$aAttendance[date("Ymd",strtotime($row["in_datetime"]))]["out_datetime"] = $row["out_datetime"];
			}
			
		} else if(empty($row["out_datetime"]) && !empty($aSchedule) ){
			$aAttendance[date("Ymd",strtotime($row["in_datetime"]))]["out_datetime"] = NULL;
			$aAttendance[date("Ymd",strtotime($row["in_datetime"]))]["source"] = $row["source"];
			$aAttendance[date("Ymd",strtotime($row["in_datetime"]))]["out_ISTAFF_ID"] = $row["source"];
		} else {
			$aAttendance[date("Ymd",strtotime($row["in_datetime"]))]["out_datetime"] = NULL;
			$aAttendance[date("Ymd",strtotime($row["in_datetime"]))]["source"] = $row["source"];
			$aAttendance[date("Ymd",strtotime($row["in_datetime"]))]["out_ISTAFF_ID"] = $row["source"];
		}
		
		if( !empty($aAttendance[date("Ymd",strtotime($row["in_datetime"]))]["in1_datetime"]) ){
			if(date("gi",$row["in1_datetime"]) >= $centerOpenTime && strtotime($row["in1_datetime"]) < strtotime($aAttendance[date("Ymd",strtotime($row["in_datetime"]))]["in1_datetime"]) ){
				$aAttendance[date("Ymd",strtotime($row["in_datetime"]))]["in1_datetime"] = $row["in1_datetime"];
				$aAttendance[date("Ymd",strtotime($row["in_datetime"]))]["in1_ISTAFF_ID"] = $row["in1_ISTAFF_ID"];
			}
		} else {
			$aAttendance[date("Ymd",strtotime($row["in_datetime"]))]["in1_datetime"] = $row["in1_datetime"];
			$aAttendance[date("Ymd",strtotime($row["in_datetime"]))]["in1_ISTAFF_ID"] = $row["in1_ISTAFF_ID"];
		}		
		
		if(!empty($row["out1_datetime"]) ){
			if( !empty($aAttendance[date("Ymd",strtotime($row["in_datetime"]))]["out1_datetime"]) ){
				if(date("gi",$row["out1_datetime"]) >= $centerCloseTime && strtotime($row["out1_datetime"]) > strtotime($aAttendance[date("Ymd",strtotime($row["in_datetime"]))]["out1_datetime"]) ){
					$aAttendance[date("Ymd",strtotime($row["in_datetime"]))]["out1_datetime"] = $row["out1_datetime"];
					$aAttendance[date("Ymd",strtotime($row["in_datetime"]))]["out1_ISTAFF_ID"] = $row["out1_ISTAFF_ID"];
				}
			} else {
				$aAttendance[date("Ymd",strtotime($row["in_datetime"]))]["out1_datetime"] = $row["out1_datetime"];
				$aAttendance[date("Ymd",strtotime($row["in_datetime"]))]["out1_ISTAFF_ID"] = $row["out1_ISTAFF_ID"];
			}
			
		} else {
			$aAttendance[date("Ymd",strtotime($row["in_datetime"]))]["out1_datetime"] = null;
			$aAttendance[date("Ymd",strtotime($row["in_datetime"]))]["out1_ISTAFF_ID"] = null;
		}
		
		if( !empty($aAttendance[date("Ymd",strtotime($row["in_datetime"]))]["in2_datetime"]) ){
			if(date("gi",$row["in2_datetime"]) >= $centerOpenTime && strtotime($row["in2_datetime"]) < strtotime($aAttendance[date("Ymd",strtotime($row["in_datetime"]))]["in2_datetime"]) ){
				$aAttendance[date("Ymd",strtotime($row["in_datetime"]))]["in2_datetime"] = $row["in2_datetime"];
				$aAttendance[date("Ymd",strtotime($row["in_datetime"]))]["in2_ISTAFF_ID"] = $row["in2_ISTAFF_ID"];
			}
		} else {
			$aAttendance[date("Ymd",strtotime($row["in_datetime"]))]["in2_datetime"] = $row["in2_datetime"];
			$aAttendance[date("Ymd",strtotime($row["in_datetime"]))]["in2_ISTAFF_ID"] = $row["in2_ISTAFF_ID"];
		}		
		
		if(!empty($row["out2_datetime"]) ){
			if( !empty($aAttendance[date("Ymd",strtotime($row["in_datetime"]))]["out2_datetime"]) ){
				if(date("gi",$row["out2_datetime"]) >= $centerCloseTime && strtotime($row["out1_datetime"]) > strtotime($aAttendance[date("Ymd",strtotime($row["in_datetime"]))]["out2_datetime"]) ){
					$aAttendance[date("Ymd",strtotime($row["in_datetime"]))]["out2_datetime"] = $row["out2_datetime"];
					$aAttendance[date("Ymd",strtotime($row["in_datetime"]))]["out2_ISTAFF_ID"] = $row["out2_ISTAFF_ID"];
				}
			} else {
				$aAttendance[date("Ymd",strtotime($row["in_datetime"]))]["out2_datetime"] = $row["out2_datetime"];
				$aAttendance[date("Ymd",strtotime($row["in_datetime"]))]["out2_ISTAFF_ID"] = $row["out2_ISTAFF_ID"];
			}
			
		} else {
			$aAttendance[date("Ymd",strtotime($row["in_datetime"]))]["out2_datetime"] = null;
			$aAttendance[date("Ymd",strtotime($row["in_datetime"]))]["out2_ISTAFF_ID"] = null;
		}
		
		$aAttendance[date("Ymd",strtotime($row["in_datetime"]))]["source"] = $row["source"];
							
	}
	mysql_free_result($result);
	
	return $aAttendance;
}


function getPwebEzcareForDate($student_id, $center_id, $date){

	// returns an array of attendance records that are pweb or ezcare, for a given student/center/date	
	
	GLOBAL $dbconn;	
	
	$sql  = "SELECT ";
	$sql .= "DISTINCT(`in_datetime`) ";
	$sql .= ", `out_datetime` ";
	$sql .= ", `in1_datetime` ";
	$sql .= ", `out1_datetime` ";
	$sql .= ", `in2_datetime` ";
	$sql .= ", `out2_datetime` ";
	$sql .= ", `source` ";
	$sql .= ", `in_ISTAFF_ID` ";
	$sql .= ", `out_ISTAFF_ID` ";
	$sql .= ", `in1_ISTAFF_ID` ";
	$sql .= ", `out1_ISTAFF_ID` ";
	$sql .= ", `in2_ISTAFF_ID` ";
	$sql .= ", `out2_ISTAFF_ID` ";
	$sql .= "FROM `student-attendance` ";
	$sql .= "WHERE `ICHILD_ID` = '".mysql_real_escape_string($student_id)."' ";
	$sql .= "AND `ICAB_ID` = '".mysql_real_escape_string($center_id)."' ";
	$sql .= "AND `source` NOT LIKE 'tablet' ";
	$sql .= "AND UNIX_TIMESTAMP(`in_datetime`) >= '".strtotime(date("Ymd",$date))."' ";
	$sql .= "AND UNIX_TIMESTAMP(`in_datetime`) < '".(strtotime(date("Ymd",$date)) + (3600*24) )."' ";
	$sql .= ";";
	
	$result = mysql_query($sql,$dbconn);
	while($row = mysql_fetch_assoc($result)){
		$aRet[] = $row; 	
	}
	mysql_free_result($result);
	return $aRet;
}	


function getUnpaidDays($centerId,$studentId){
	GLOBAL $dbconn;
	$sRet = null;
	$puRet = null;
	
	$enrollmentDate = null;
	$pWebStartDate = null;
	$ccidsStartDate = null;

	$sql  = "SELECT ";
	$sql .= "DISTINCT(`ez2_import-tcChild`.`CADMITDATE`) ";
	$sql .= "FROM ";
	$sql .= "`ez2_import-tcChild` ";
	$sql .= "WHERE ";
	$sql .= "`ez2_import-tcChild`.`ICHILD_ID` = '".mysql_real_escape_string($studentId)."' ";
	$sql .= "AND `ez2_import-tcChild`.`ICAB_ID` = '".mysql_real_escape_string($centerId)."' ";
	$sql .= "AND `ez2_import-tcChild`.`CADMITDATE` IS NOT NULL ";
	$sql .= "AND `ez2_import-tcChild`.`CADMITDATE` NOT LIKE '' ";
	$sql .= "; ";	
	
	$result = mysql_query($sql, $dbconn);
	if($row = mysql_fetch_assoc($result)){
		do{
			$puRet .= "EZ2 enrollment date: ".$row["CADMITDATE"]." ".date("D",strtotime($row["CADMITDATE"]))."<br>";
			$enrollmentDate = strtotime(date("Ymd",strtotime($row["CADMITDATE"])));			
		}while($row = mysql_fetch_assoc($result));
	} else {
		$puRet .= "<span class=\"color_red\">No EZ2 enrollment data found.</span><br>";	
	}		
	mysql_free_result($result);
	
	$sql  = "SELECT ";
	$sql .= "`ccids_import-transactions`.* ";
	$sql .= "FROM ";
	$sql .= "`ccids_import-transactions` ";
	$sql .= ", `ez2_import-tcChild` ";
	$sql .= "WHERE ";
	$sql .= "`ez2_import-tcChild`.`ICHILD_ID` = '".mysql_real_escape_string($studentId)."' ";
	$sql .= "AND `ccids_import-transactions`.`cfname` = SUBSTRING( `ez2_import-tcChild`.`CFIRSTNAME` , 1, IF( LOCATE( ' ', `ez2_import-tcChild`.`CFIRSTNAME` ) , LOCATE( ' ', `ez2_import-tcChild`.`CFIRSTNAME` ) , LENGTH( `ez2_import-tcChild`.`CFIRSTNAME` ) ) ) ";
	$sql .= "AND `ccids_import-transactions`.`clname` = SUBSTRING( `ez2_import-tcChild`.`CLASTNAME` , 1, IF( LOCATE( ' ', `ez2_import-tcChild`.`CLASTNAME` ) , LOCATE( ' ', `ez2_import-tcChild`.`CLASTNAME` ) , LENGTH( `ez2_import-tcChild`.`CLASTNAME` ) ) ) ";
	$sql .= "ORDER BY `ccids_import-transactions`.`week_beginning` ASC ";
	$sql .= "LIMIT 1 ";
	$sql .= "; ";

	// DEBUG
	//$sRet .= $sql."<br>";	
	
	$result = mysql_query($sql, $dbconn);
	if($row = mysql_fetch_assoc($result)){
		do{
			$puRet .= "CCIDS start week: ".$row["week_beginning"]."<br>";
			$puRet .= "CCIDS service hours paid for start week: ".$row["svc_hours"]."<br>";
			$ccidsStartDate = strtotime(date("Ymd",strtotime($row["week_beginning"])));
		}while($row = mysql_fetch_assoc($result));
	} else {
		$puRet .= (!empty($puRet)?"<br>":null);
		$puRet .= "<span class=\"color_red\">No CCIDS data found.</span><br>";	
	}

	mysql_free_result($result);
	
	$sql  = "SELECT ";
	$sql .= "`pweb_import-transaction`.* ";
	$sql .= "FROM ";
	$sql .= "`pweb_import-transaction` ";
	$sql .= ", `ez2_import-tcChild` ";
	$sql .= "WHERE ";
	$sql .= "`ez2_import-tcChild`.`ICHILD_ID` = '".mysql_real_escape_string($studentId)."' ";
	$sql .= "AND `pweb_import-transaction`.`ChildFirstName` = SUBSTRING( `ez2_import-tcChild`.`CFIRSTNAME` , 1, IF( LOCATE( ' ', `ez2_import-tcChild`.`CFIRSTNAME` ) , LOCATE( ' ', `ez2_import-tcChild`.`CFIRSTNAME` ) , LENGTH( `ez2_import-tcChild`.`CFIRSTNAME` ) ) ) ";
	$sql .= "AND `pweb_import-transaction`.`ChildLastName` = SUBSTRING( `ez2_import-tcChild`.`CLASTNAME` , 1, IF( LOCATE( ' ', `ez2_import-tcChild`.`CLASTNAME` ) , LOCATE( ' ', `ez2_import-tcChild`.`CLASTNAME` ) , LENGTH( `ez2_import-tcChild`.`CLASTNAME` ) ) ) ";
	$sql .= "ORDER BY `pweb_import-transaction`.`TransDateTime` ASC ";
	$sql .= "LIMIT 1 ";
	$sql .= "; ";
	
	$result = mysql_query($sql, $dbconn);
	if($row = mysql_fetch_assoc($result)){
		do{
			$puRet .= "pWeb start date: ".$row["TransDateTime"]." ".date("D",strtotime($row["TransDateTime"]))."<br>";
			$pWebStartDate = strtotime(date("Ymd",strtotime($row["TransDateTime"])));
		}while($row = mysql_fetch_assoc($result));
	} else {
		$puRet .= (!empty($puRet)?"<br>":null);
		$puRet .= "<span class=\"color_red\">No pWeb data found.</span><br>";	
	}
	mysql_free_result($result);

	$sql  = "SELECT DISTINCT(`ICHILD_ID`), ";
	$sql .= "`NTOTAL`, ";
	$sql .= "`CMON_TIMES`, ";
	$sql .= "`CTUE_TIMES`, ";
	$sql .= "`CWED_TIMES`, ";
	$sql .= "`CTHU_TIMES`, ";
	$sql .= "`CFRI_TIMES` ";
	$sql .= "FROM `ez2_import-dbChiSch` ";
	$sql .= "WHERE `ICHILD_ID` = '".mysql_real_escape_string($studentId)."' ";

	$result = mysql_query($sql);
	if($row = mysql_fetch_assoc($result)){
		$puRet .= (!empty($puRet)?"<br>":null);
		do {
			$puRet .= "Scheduled hours per week: ".$row["NTOTAL"]."<br>";
		} while($row = mysql_fetch_assoc($result));	
	} else {
		$puRet .= (!empty($puRet)?"<br>":null);
		$puRet .= "<span class=\"color_red\">No schedule data.</span><br>";
		$puRet .= "Assuming 6:00 am to 6:00 pm, Monday through Friday: 60<br>";
	}
	mysql_free_result($result);
	
	if(!empty($ccidsStartDate) && ($enrollmentDate >= $ccidsStartDate || $pWebStartDate <= $ccidsStartDate)){
		$puRet .= (!empty($puRet)?"<br>":null);
		$puRet .= "<span class=\"color_green\">No missing payments.</span><br>";		

		$sRet .= "<span class=\"fa fa-check color_green\"></span><br>";		
	} else {
		$ccidsEnroll = 0;
		$ccidsPweb = 0;
		
		if($enrollmentDate < $ccidsStartDate){
			$ccidsEnroll = ceil(($ccidsStartDate - $enrollmentDate)/(3600*24));
			$puRet .= (!empty($puRet)?"<br>":null);	
			$puRet .= "Unpaid days? ".$ccidsEnroll."<br><span class=\"small\">(the difference between CCIDS first transaction and EZcare2 enrollment)</span><br>";
			
			$sRet .= $ccidsEnroll."<br>";
		}
		if(date("Y",$ccidsStartDate) >= date("Y")){
			//if($pWebStartDate < $ccidsStartDate){
			if($pWebStartDate >= $ccidsStartDate){
				//$ccidsPweb = ceil(($ccidsStartDate - $pWebStartDate)/(3600*24));
				$ccidsPweb = ceil(($pWebStartDate - $ccidsStartDate)/(3600*24));
				//$puRet .= "CCIDS - pWeb: ".$ccidsPweb."<br>";
				$puRet .= (!empty($puRet)?"<br>":null);
				$puRet .= "Days after first CCIDS payment to first pWeb swipe: ".$ccidsPweb."<br>";
			}
		} else {
			$puRet .= "pWeb data not available for ".(!empty($ccidsStartDate)?date("Y",$ccidsStartDate):"NULL")."<br>"; 			
		}
		//$puRet .= $studentId." - ".date("Y/m/d",$enrollmentDate)." - ".date("Y/m/d",$ccidsStartDate)."<br>";

		$aAttendance = getStudentAttendanceForRange($studentId, $enrollmentDate, $ccidsStartDate);
		if(!empty($aAttendance)){
			$puRet .= "<br>Attendance:<br><pre>".var_export($aAttendance,true)."</pre><br>";
		} else {
			$puRet .= "<br><span class=\"color_red\">No attendance data for ".date("Y-m-d",$enrollmentDate)." to ".(!empty($ccidsStartDate)?date("Y-m-d",$ccidsStartDate):"NULL")."</span><br>";

			$aAttendanceSrc = getStudentAttendanceForRange($studentId, $enrollmentDate, time());

			$aAttendance = array_shift($aAttendanceSrc);			
			
			$puRet .= "<br>";			
			$puRet .= "First available attendance: ".date("Y-m-d h:i a",strtotime($aAttendance["in_datetime"]))." - ".(strstr($aAttendance["out_datetime"],"Missing")=== false?date("h:i a",strtotime($aAttendance["out_datetime"])):"<strong>In class</strong>")."<br>";
			$puRet .= "Source: ".$aAttendance["source"]."<br>";			
			
			$aAttendance = array_pop($aAttendanceSrc);			
			
			$puRet .= "<br>";			
			$puRet .= "Last attendance record: ".date("Y-m-d h:i a",strtotime($aAttendance["in_datetime"]))." - ".(strstr($aAttendance["out_datetime"],"Missing")=== false?date("h:i a",strtotime($aAttendance["out_datetime"])):"<strong>In class</strong>")."<br>";
			$puRet .= "Source: ".$aAttendance["source"]."<br>";
		}
		
	}	
			
	$sRet  = "<a href=\"#\" onclick=\"showHide('det".$studentId."');return false;\" style=\"text-decoration: none; font-weight: bold;\">".($sRet<=120?$sRet:"<span class=\"color_red\">---</span>")."</a>";
	$sRet .= "<div id=\"det".$studentId."\" class=\"missing_details\"><a href=\"#\" onclick=\"showHide('det".$studentId."');return false;\" class=\"close\">X</a><br>".$puRet."<br><a href=\"#\" onclick=\"showHide('det".$studentId."');return false;\" class=\"close\">X</a></div>"; 
	
	
	return $sRet; 
}

function getCenterClassIds($ICAB_ID = 215){
	GLOBAL $dbconn;
	
	$classIds = null;
	
	$sql  = "SELECT DISTINCT(`ICLASS_ID`) ";
	$sql .= ", `CCLASS_NAM` ";
	$sql .= "FROM `ez2_import-tcClass` ";
	$sql .= "WHERE `ICAB_ID` = '".mysql_real_escape_string($ICAB_ID)."' ";
	$sql .= "AND `CCLASS_NAM` NOT IN ('Office','Director') ";
	$sql .= ";";

	// DEBUG
	//echo '<br>'.$sql.'<br>';

	$result = mysql_query($sql,$dbconn);
	while($row = mysql_fetch_assoc($result)){
		//$classIds[$row["ICLASS_ID"]] = array("class_name" => stripslashes($row["CCLASS_NAM"])); 	
		$classIds[$row["ICLASS_ID"]] = stripslashes($row["CCLASS_NAM"]); 	
	}
	mysql_free_result($result);
	return $classIds;
}

function hslToRgb( $h, $s, $l ){
    $r; 
    $g; 
    $b;
 
	$c = ( 1 - abs( 2 * $l - 1 ) ) * $s;
	$x = $c * ( 1 - abs( fmod( ( $h / 60 ), 2 ) - 1 ) );
	$m = $l - ( $c / 2 );
 
	if ( $h < 60 ) {
		$r = $c;
		$g = $x;
		$b = 0;
	} else if ( $h < 120 ) {
		$r = $x;
		$g = $c;
		$b = 0;			
	} else if ( $h < 180 ) {
		$r = 0;
		$g = $c;
		$b = $x;					
	} else if ( $h < 240 ) {
		$r = 0;
		$g = $x;
		$b = $c;
	} else if ( $h < 300 ) {
		$r = $x;
		$g = 0;
		$b = $c;
	} else {
		$r = $c;
		$g = 0;
		$b = $x;
	}
 
	$r = ( $r + $m ) * 255;
	$g = ( $g + $m ) * 255;
	$b = ( $b + $m  ) * 255;
 
    return array( floor( $r ), floor( $g ), floor( $b ) );
}

function rgb2hex($rgb) {
   $hex = "#";
   $hex .= str_pad(dechex($rgb[0]), 2, "0", STR_PAD_LEFT);
   $hex .= str_pad(dechex($rgb[1]), 2, "0", STR_PAD_LEFT);
   $hex .= str_pad(dechex($rgb[2]), 2, "0", STR_PAD_LEFT);

   return $hex; // returns the hex value including the number sign (#)
}


function getTeachersForCenter($ICAB_ID){
	GLOBAL $dbconn;
	
	$sql  = "SELECT `ISTAFF_ID` ";
	$sql .= ", `CFIRSTNAME` ";
	$sql .= ", `CLASTNAME` ";
	$sql .= "FROM ";
	$sql .= "`ez2_import-tcStaff` ";
	$sql .= "WHERE ";
	$sql .= "`ICAB_ID` = '".mysql_real_escape_string($ICAB_ID,$dbconn)."' ";
	$sql .= "; ";
	
	$result = mysql_query($sql,$dbconn);
	if($row = mysql_fetch_assoc($result)){

		$aTeachers = null;		
		
		while($row = mysql_fetch_assoc($result)){
			$aTeachers[$row["ISTAFF_ID"]] = array("firstname"=>$row["CFIRSTNAME"],"lastname"=>$row["CLASTNAME"]);
		}
		return $aTeachers;

	} else {
		return null;	
	}
	mysql_free_result($result);
}

function getTeacherInitials($ISTAFF_ID){
	GLOBAL $dbconn;
	
	$sql  = "SELECT `ISTAFF_ID` ";
	$sql .= ", `CFIRSTNAME` ";
	$sql .= ", `CLASTNAME` ";
	$sql .= "FROM ";
	$sql .= "`ez2_import-tcStaff` ";
	$sql .= "WHERE ";
	$sql .= "`ISTAFF_ID` = '".mysql_real_escape_string($ISTAFF_ID,$dbconn)."' ";
	$sql .= "; ";
	
	$result = mysql_query($sql,$dbconn);
	if($row = mysql_fetch_assoc($result)){
		return substr($row["CFIRSTNAME"],0,1).".".substr($row["CLASTNAME"],0,1).".";	
	} else {
		return null;	
	}
	mysql_free_result($result);
}

function getTeacherFullName($ISTAFF_ID){
	GLOBAL $dbconn;
	
	$sql  = "SELECT `ISTAFF_ID` ";
	$sql .= ", `CFIRSTNAME` ";
	$sql .= ", `CLASTNAME` ";
	$sql .= "FROM ";
	$sql .= "`ez2_import-tcStaff` ";
	$sql .= "WHERE ";
	$sql .= "`ISTAFF_ID` = '".mysql_real_escape_string($ISTAFF_ID,$dbconn)."' ";
	$sql .= "; ";
	
	$result = mysql_query($sql,$dbconn);
	if($row = mysql_fetch_assoc($result)){
		return array("firstname"=>$row["CFIRSTNAME"],"lastname"=>$row["CLASTNAME"]);	
	} else {
		return null;	
	}
	mysql_free_result($result);
}
?>
