<?php
session_start();

$startTime = time();

header("Expires: Sat, 26 Jul 1997 05:00:00 GMT");

require($_SERVER["DOCUMENT_ROOT"]."/emarc/includes/dbconn.php");
require($_SERVER["DOCUMENT_ROOT"]."/emarc/includes/common.php");

if(checkLoginToken($_SESSION["token"]) === false){
	header("Location: logout.php?page=".base64_encode($_SERVER["REQUEST_URI"]));
	exit;
}

/* =============================== */
/* BEGIN: Page content and display */
/* =============================== */

displayOpenHTML('EMARC');
?>

<div id="main_wrapper">
	<h1>EMARC<span>Electronic Meal and Attendance Record Calculator</span></h1>
	<div class="table">
	<div class="col_left">
		<?php displayLeftNavigation(); ?>
	</div>
	<div class="col_right">
		<div id="main_content">
		<h2>Welcome to EMARC</h2>
		<?php
		if(!empty($_GET["noexec"])){
			outputSummaryReport();
		} else if(!empty($_GET["USDA"])){
			outputUSDAreport();	
		} else if(!empty($_GET["cid"]) && !empty($_GET["ar"])){
			outputAttendanceReports();
		} else if(!empty($_GET["cid"]) && !empty($_GET["ared"])){
			editAttendanceRecords();
		} else {
			outputExecSummary();
			outputCenterBackswipeDetails();
			outputMonthlyAttendanceCalendar();
			echo "(<a href=\"emarc.php?noexec=1\">See Details for All Centers</a>)<br>";
			echo "<br>";
		}
		echo "<div style=\"text-align: right; font-size: 75%;\">elapsed: ".(time() - $startTime)."</div>";
		?>
		</div>
	</div>
	</div>
</div>

<?php

displayCloseHTML();

/* =============================== */
/* END: Page content and display */
/* =============================== */

function outputSummaryReport(){
	GLOBAL $dbconn;
	GLOBAL $daysToGoBack;

	if($_SESSION["permissions_levelFK"] < 3){
		
# Get Teacher Name, Class Id, Class Name, Class Description, Center Id, Center Name

$sql  = "SELECT "; 
$sql .= "`ez2_import-tcStaff`.`ISTAFF_ID` as 'Staff Id', "; 
$sql .= "`ez2_import-tcStaff`.`CFIRSTNAME` as 'Teacher First Name', "; 
$sql .= "`ez2_import-tcStaff`.`CLASTNAME` as 'Teacher Last Name', "; 
$sql .= "`ez2_import-tcStaff`.`ICLASS_ID`, "; 
//$sql .= "`ez2_import-tcClass`.`ITCCLASSID` as 'ITCCLASS_ID', "; 
$sql .= "`ez2_import-tcClass`.`CCLASS_NAM` as 'Class Name', "; 
$sql .= "`ez2_import-tcClass`.`CDES` as 'Class Description', "; 
$sql .= "`ez2_import-tcClass`.`ICAPACITY`, "; 
$sql .= "`ez2_import-tcStaff`.`ICAB_ID`, "; 
$sql .= "`ez2_import-tcCabReg`.`CCAB_NAME` as 'Center Name' "; 
$sql .= "FROM "; 
$sql .= "`ez2_import-tcStaff`, "; 
$sql .= "`ez2_import-tcClass`, "; 
$sql .= "`ez2_import-tcCabReg` "; 
$sql .= "WHERE "; 
$sql .= "`ez2_import-tcCabReg`.`CCAB_NAME` LIKE '%".date("Y")."%' AND "; 
$sql .= "`ez2_import-tcCabReg`.`ICAB_ID` = `ez2_import-tcStaff`.`ICAB_ID` AND "; 
$sql .= "`ez2_import-tcStaff`.`ICAB_ID` = `ez2_import-tcClass`.`ICAB_ID` AND "; 
$sql .= "`ez2_import-tcClass`.`ICLASS_ID` = `ez2_import-tcStaff`.`ICLASS_ID` "; 
$sql .= "ORDER BY "; 
$sql .= "`ez2_import-tcStaff`.`ICAB_ID` ASC, "; 
$sql .= "`ez2_import-tcStaff`.`ICLASS_ID` ASC, "; 
$sql .= "`ez2_import-tcStaff`.`ISTAFF_ID` ASC "; 
$sql .= ";";		

$result = mysql_query($sql);
if($row = mysql_fetch_assoc($result)){

	echo "<script type=\"text/javascript\">";
	echo "function showAttendance(clickedObj, tableID){";
	
	echo " if(clickedObj.childNodes[0].getAttribute('class') == 'fa fa-arrow-down'){";
	echo " 	clickedObj.childNodes[0].setAttribute('class', 'fa fa-arrow-up');";
	echo " } else if(clickedObj.childNodes[0].getAttribute('class') == 'fa fa-arrow-up'){";
	echo " 	clickedObj.childNodes[0].setAttribute('class', 'fa fa-arrow-down');";
	echo " }";
	echo " if(document.getElementById(tableID).style.display=='none' || document.getElementById(tableID).style.display==''){";
	echo "	document.getElementById(tableID).style.display = 'block';";
	echo " } else {";
	echo "	document.getElementById(tableID).style.display = 'none';";
	echo " }";
	echo "return false;";
	echo "}";
	echo "</script>";	
	
	echo "<table>";
	echo "<tr><td colspan=\"".count($row)."\" class=\"noborder\"><h4>".$row["Center Name"]." - ".$row["Class Name"]." (".$row["ICLASS_ID"].")<span>".$row["Class Description"]." - Capaity: ".$row["ICAPACITY"]."</span></h4></td></tr>";
	echo "<tr>";
	foreach($row as $k => $v){
		if(strpos($k,"_ID") === false && strpos($k,"ICAPACITY") === false && strpos($k,"Center Name") === false && strpos($k,"Class Name") === false && strpos($k,"Class Description") === false){
			echo "<th>".$k."</th>";
		}
	}
	echo "</tr>";
	$lastIcabId = null;
	$lastIclassId = null;
	$lastITCCLASSID = null;
	do{
		if(!empty($lastIclassId) && $lastIclassId != $row["ICLASS_ID"]){

			$sql1  = "SELECT ";
			$sql1 .= "`ez2_import-tcChild`.`ICHILD_ID`, ";
			$sql1 .= "`ez2_import-tcChild`.`ICHILD_ID` as 'Student Id', ";
			//$sql1 .= "`ez2_import-tcChild`.`ITCCHILDID` as 'Attendance Id', ";
			//$sql1 .= "`ez2_import-tcChild`.`ITCCHILDID` as 'ITCCHILD_ID', ";
			$sql1 .= "`ez2_import-tcChild`.`ICAB_ID`, ";
			$sql1 .= "`ez2_import-tcChild`.`ICLASS_ID`, ";
			$sql1 .= "`ez2_import-tcChild`.`CFIRSTNAME` as 'Child First Name', ";
			$sql1 .= "`ez2_import-tcChild`.`CLASTNAME` as 'Child Last Name', ";
			$sql1 .= "`ez2_import-tcChild`.`CBIRTHDATE` as 'Birthdate', ";
			$sql1 .= "`ez2_import-tcChild`.`CCHILDAGE` as 'Age', ";
			$sql1 .= "`ez2_import-tcChild`.`IFAMILY_ID` ";
			$sql1 .= "FROM `ez2_import-tcChild` ";
			$sql1 .= "WHERE `ez2_import-tcChild`.`ICAB_ID` = '".$lastIcabId."' ";
			$sql1 .= "AND `ez2_import-tcChild`.`ICLASS_ID` = '".$lastIclassId."' ";
			$sql1 .= "AND `ez2_import-tcChild`.`ISTATUS` = 1 ";
			$sql1 .= ";";

			$result1 = mysql_query($sql1);
			if($row1 = mysql_fetch_assoc($result1)){
				echo "<tr class=\"noborder\"><td colspan=\"".count($row)."\" class=\"noborder\">";
				echo "<h4>".mysql_num_rows($result1)." enrolled students</h4>";
				echo "<table>";
				echo "<tr>";
				foreach($row1 as $k => $v){
					if(strpos($k,"_ID") === false){
						echo "<th>".$k."</th>";
					}			
				}
				echo "<th>Title 20</th>";
				echo "<th>Backswipe (max in range: ".daysMinusWeekends($daysToGoBack).")</th>";
				echo "</tr>";
				do{
					echo "<tr>";
					$colCount = 0;
					foreach($row1 as $k => $v){
						if(strpos($k,"_ID") === false){
							echo "<td>".$v."</td>";
							$colCount++;
						}			
					}
					// added 2 additional columns - see below schedule and attendance
					$colCount = $colCount + 2;
					
					$sql2  = "SELECT ";
					$sql2 .= "`NTOTAL`, ";
					$sql2 .= "`CMON_TIMES`, ";
					$sql2 .= "`CTUE_TIMES`, ";
					$sql2 .= "`CWED_TIMES`, ";
					$sql2 .= "`CTHU_TIMES`, ";
					$sql2 .= "`CFRI_TIMES` ";
					$sql2 .= "FROM `ez2_import-dbChiSch` ";
					$sql2 .= "WHERE `ICHILD_ID` = '".$row1["ICHILD_ID"]."' ";
					//$sql2 .= "AND `ICLASS_ID` = '".$row1["ICLASS_ID"]."' ";

					$sChildSchedule = null;
					$iChildCareHours = 0;
					$aSchedule = null;
					
					$result2 = mysql_query($sql2);
					
					if($row2 = mysql_fetch_assoc($result2)){
						$sChildSchedule .= "<tr><td colspan=\"".$colCount."\" class=\"nopadding\">";
						$sChildSchedule .= "<table>";
						$sChildSchedule .= "<tr>";						
						foreach($row2 as $k => $v){
							$sChildSchedule .= "<th>".$k."</th>";
						}
						$sChildSchedule .= "</tr>";
						do{
							$sChildSchedule .= "<tr>";
							foreach($row2 as $k => $v){
								$sChildSchedule .= "<td>".$v."</td>";
							}
							$sChildSchedule .= "</tr>";
							$iChildCareHours = $iChildCareHours + $row2["NTOTAL"];

							$aSchedule[1] = $row2["CMON_TIMES"]; 
							$aSchedule[2] = $row2["CTUE_TIMES"];
							$aSchedule[3] = $row2["CWED_TIMES"];
							$aSchedule[4] = $row2["CTHU_TIMES"];
							$aSchedule[5] = $row2["CFRI_TIMES"];
							
						} while ($row2 = mysql_fetch_assoc($result2));
						$sChildSchedule .= "</table><br>";
						$sChildSchedule .= "</td></tr>";
						mysql_free_result($result2);
					}

					$sql2  = "SELECT ";
					$sql2 .= "`ICHILD_ID`, ";
					$sql2 .= "`ICLASS_ID`, ";
					$sql2 .= "`ICAB_ID`, ";
					$sql2 .= "`source`, ";
					$sql2 .= "`in_datetime`, ";
					$sql2 .= "`in_status`, ";
					$sql2 .= "`out_datetime`, ";
					$sql2 .= "`out_status` ";
					$sql2 .= "FROM `student-attendance` ";
					$sql2 .= "WHERE 1 = 1 ";
					$sql2 .= "AND (`source` = 'pweb_import-transaction' OR `source` = 'tablet') ";
					$sql2 .= "AND `ICHILD_ID` = '".$row1["ICHILD_ID"]."' ";
					$sql2 .= "AND `ICLASS_ID` = '".$row1["ICLASS_ID"]."' ";
					$sql2 .= "AND `ICAB_ID` = '".$row1["ICAB_ID"]."' ";
					$sql2 .= "AND UNIX_TIMESTAMP(`in_datetime`) <= '".time()."' ";
					$sql2 .= "AND UNIX_TIMESTAMP(`in_datetime`) >= '".mktime(0, 0, 0, date("m"), date("d")-$daysToGoBack, date("Y"))."' ";
					$sql2 .= "AND `out_datetime` IS NOT NULL ";
					$sql2 .= "ORDER BY `in_datetime` DESC ";
					$sql2 .= ", `source` DESC ";
					$sql2 .= ";";
					
					$result2 = mysql_query($sql2);

					$sAttendance = null;
					$aAttendance = null;
					$pWebCnt = 0;
					$$tabletOrCnt = 0;
					$$tabletCnt = 0;

					if($row2 = mysql_fetch_assoc($result2)){
						
						// DEBUG						
						//$sAttendance .= "<tr><td colspan=\"".$colCount."\" class=\"nopadding\">".$sql2."</td></tr>";
						 
						$sAttendance .= "<tr><td colspan=\"".$colCount."\" class=\"nopadding\"><table id=\"attendance_".$row1["ICHILD_ID"]."\" class=\"attendance_table\">";
						$sAttendance .= "<tr>";
						$sAttendance .= "<th>IN</th>";
						$sAttendance .= "<th>IN - Status</th>";
						$sAttendance .= "<th>OUT</th>";
						$sAttendance .= "<th>OUT - Status</th>";
						$sAttendance .= "<th>Source</th>";
						$sAttendance .= "</tr>";

						$lastSource = $row2["source"];
						$lastInTime = strtotime($row2["in_datetime"]);							
						$lastOutTime = strtotime($row2["out_datetime"]);						
						

						$lastSource = $row2["source"];
						$lastOutStatus = $row2["out_status"];
						$lastOutTime = strtotime($row2["out_datetime"]);						
						
						do{
							//$sAttendance .= "<tr>";
							//$sAttendance .= "<td>";

							if( date("Ymd",strtotime($row2["in_datetime"])) == date("Ymd",$lastInTime) && strtotime($row2["in_datetime"]) != $lastInTime && ($lastSource == "pweb_import-transaction" && $row2["source"] == "tablet" ) ){
								// DEBUG
								$sAttendance .= "<tr><td colspan=\"5\">";
								$sAttendance .= "<strong>use tablet: ".$row2["in_datetime"]." instead of : ".date("Y-m-d H:i:s",$lastInTime)." - ".$lastInStatus."</strong>";
								$sAttendance .= "</td></tr>";

								$aAttendance[date("Ymd",strtotime($row2["in_datetime"]))]["IN"] = date("Y-m-d H:i:s",strtotime($row2["in_datetime"]));
								$aAttendance[date("Ymd",strtotime($row2["in_datetime"]))]["IN-Status"] = $row2["in_status"];									
								$aAttendance[date("Ymd",strtotime($row2["in_datetime"]))]["Source"] = ($row2["source"]=="tablet"?"Tablet":($row2["source"]=="pweb_import-transaction"?"pWeb":($row2["source"]=="ez2_import-tcActAtt"?"ez2Care":"&nbsp;")));									
									
							} else {
								//$sAttendance .= $row2["in_datetime"];
									
								$aAttendance[date("Ymd",strtotime($row2["in_datetime"]))]["IN"] = date("Y-m-d H:i:s",strtotime($row2["in_datetime"]));
								$aAttendance[date("Ymd",strtotime($row2["in_datetime"]))]["IN-Status"] = $row2["in_status"];
								$aAttendance[date("Ymd",strtotime($row2["in_datetime"]))]["Source"] = ($row2["source"]=="tablet"?"Tablet":($row2["source"]=="pweb_import-transaction"?"pWeb":($row2["source"]=="ez2_import-tcActAtt"?"ez2Care":"&nbsp;")));
								
							}
							//$sAttendance .= "</td>";							
							//$sAttendance .= "<td>".$row2["in_status"]."</td>";
							
							//$sAttendance .= "<td>";
							if( date("Ymd",strtotime($row2["out_datetime"])) == date("Ymd",$lastOutTime) && strtotime($row2["out_datetime"]) != $lastOutTime && ($lastSource == "pweb_import-transaction" && $row2["source"] == "tablet" ) ){
								//$sAttendance .= "<strong>use tablet: ".$row2["out_datetime"]." instead of : ".date("Y-m-d H:i:s",$lastOutTime)." - ".$lastOutStatus."</strong>";
									
								$aAttendance[date("Ymd",strtotime($row2["out_datetime"]))]["OUT"] = date("Y-m-d H:i:s",strtotime($row2["out_datetime"]));
								$aAttendance[date("Ymd",strtotime($row2["out_datetime"]))]["OUT-Status"] = $row2["out_status"];
								$aAttendance[date("Ymd",strtotime($row2["out_datetime"]))]["Source"] = ($row2["source"]=="tablet"?"Tablet":($row2["source"]=="pweb_import-transaction"?"pWeb":($row2["source"]=="ez2_import-tcActAtt"?"ez2Care":"&nbsp;")));
									
								$tabletOrCnt++;
								$tabletCnt++;
									
							} else {
								//$sAttendance .= $row2["out_datetime"];
									
								$aAttendance[date("Ymd",strtotime($row2["out_datetime"]))]["OUT"] = date("Y-m-d H:i:s",strtotime($row2["out_datetime"]));
								$aAttendance[date("Ymd",strtotime($row2["out_datetime"]))]["OUT-Status"] = $row2["out_status"];
								$aAttendance[date("Ymd",strtotime($row2["out_datetime"]))]["Source"] = ($row2["source"]=="tablet"?"Tablet":($row2["source"]=="pweb_import-transaction"?"pWeb":($row2["source"]=="ez2_import-tcActAtt"?"ez2Care":"&nbsp;")));
								
								if($row2["source"]!="tablet"){
									$pWebCnt++;
								} else if ($lastSource == "tablet" && $row2["source"] == "tablet" ) {
									$tabletOrCnt++;								
								}
							}

														
							
							//$sAttendance .= "</td>";
							//$sAttendance .= "<td>".$row2["out_status"]."</td>";							
							//$sAttendance .= "<td>".($row2["source"]=="tablet"?"Tablet":($row2["source"]=="pweb_import-transaction"?"pWeb":($row2["source"]=="ez2_import-tcActAtt"?"ez2Care":"&nbsp;")))."</td>";							
							//$sAttendance .= "</tr>";
								
								
							$lastSource = $row2["source"];
							$lastInStatus = $row2["in_status"];
							$lastOutStatus = $row2["out_status"];
							$lastInTime = strtotime($row2["in_datetime"]);							
							$lastOutTime = strtotime($row2["out_datetime"]);							
							
						} while($row2 = mysql_fetch_assoc($result2));
						
						// DEBUG
						//$sAttendance .= "<tr><td colspan=\"5\"><pre>".var_export($aAttendance,true)."</pre></td></tr>";
												
						foreach($aAttendance as $myK => $myVals){
							$sAttendance .= "<tr>";
							$sAttendance .= "<td>".$myVals["IN"]."</td>";						
							$sAttendance .= "<td>".$myVals["IN-Status"]."</td>";						
							$sAttendance .= "<td>".$myVals["OUT"]."</td>";						
							$sAttendance .= "<td>".$myVals["OUT-Status"]."</td>";
							$sAttendance .= "<td>".$myVals["Source"]."</td>";
							$sAttendance .= "</tr>";
						}	
						$sAttendance .= "</table></td></tr>";

						mysql_free_result($result2);
					}

					$isTitle20 = getTitle20($row1["ICHILD_ID"], $row1["ICAB_ID"]);
					
					echo "<td class=\"text-align_center\">".($isTitle20 === true?"<span class=\"fa fa-check color_green\"></span>":"<span class=\"fa fa-ban color_red\"></span>")."</td>";
					if($isTitle20 === true){
						$schDaysInRange = scheduledDaysInRange($daysToGoBack, $aSchedule);
						//echo "<td class=\"align_right\">".((daysMinusWeekends($daysToGoBack) - (daysMinusWeekends($daysToGoBack) - scheduledDaysInRange($daysToGoBack, $aSchedule)) ) - $pWebCnt).($pWebCnt == daysMinusWeekends($daysToGoBack)?"&nbsp;&nbsp;&nbsp;<a href=\"#\" onclick=\"return showAttendance(this, 'attendance_".$row1["ICHILD_ID"]."');\"><span class=\"fa fa-check color_green\"></span></a>":($pWebCnt > 0?"&nbsp;&nbsp;&nbsp;<a href=\"#\" onclick=\"return showAttendance(this, 'attendance_".$row1["ICHILD_ID"]."');\"><span class=\"fa fa-arrow-down\"></span></a>":($pWebCnt == 0?"&nbsp;&nbsp;&nbsp;<span class=\"fa fa-ban color_red\"></span>":null)))." *Tablet overrides: ".$tabletOrCnt." *Tablet count: ".$tabletCnt."</td>";
						echo "<td>";
						echo "days in range: ".daysMinusWeekends($daysToGoBack)."<br>";
						echo "max swipes in range: ".$schDaysInRange."<br>";
						echo "pWeb count: ".$pWebCnt."<br>";
						echo "tablet overrides: ".$tabletOrCnt."<br>";
						echo "backswipes (max. in range - pWeb count): ".($schDaysInRange - $pWebCnt)."<br>";
						if( $pWebCnt == 0 && $tabletOrCnt == 0){
							echo "<span class=\"fa fa-ban color_red\"></span>";
						} else if( $pWebCnt == $schDaysInRange ){
							echo "<a href=\"#\" onclick=\"return showAttendance(this, 'attendance_".$row1["ICHILD_ID"]."');\"><span class=\"fa fa-check color_green\"></span></a>";
						} else {
							echo "<a href=\"#\" onclick=\"return showAttendance(this, 'attendance_".$row1["ICHILD_ID"]."');\"><span class=\"fa fa-arrow-down\"></span></a>";
						}
						echo "</td>";
					} else {
						echo "<td>&nbsp;</td>";
					}
					echo "</tr>";
					
					echo $sAttendance;

					if($isTitle20 === true){
						echo $sChildSchedule;
					}

					$pWebCnt = 0;
					$tabletCnt = 0;
					$tabletOrCnt = 0;					
					
				} while ($row1 = mysql_fetch_assoc($result1));

				echo "</table>";

				mysql_free_result($result1);
				echo "</td></tr>";
				
			}

			echo "<tr><td colspan=\"".count($row)."\" class=\"noborder\"><br><h4>".$row["Center Name"]." - ".$row["Class Name"]."<span>".$row["Class Description"]." - Capacity: ".$row["ICAPACITY"]."</span></h4></td></tr>";
			
			echo "<tr>";
			foreach($row as $k => $v){
				if(strpos($k,"_ID") === false && strpos($k,"ICAPACITY") === false && strpos($k,"Center Name") === false && strpos($k,"Class Name") === false && strpos($k,"Class Description") === false){
					echo "<th>".$k."</th>";
				}
			}
			echo "</tr>";
	
			$lastIcabId = null;
			$lastIclassId = null;		
		}
		
		echo "<tr>";
		foreach($row as $k => $v){
			if(strpos($k,"_ID") === false && strpos($k,"ICAPACITY") === false && strpos($k,"Center Name") === false && strpos($k,"Class Name") === false && strpos($k,"Class Description") === false){
				echo "<td>".$v."</td>";
			}
		}
		echo "</tr>";
		
		
		$lastIcabId = $row["ICAB_ID"];
		$lastIclassId = $row["ICLASS_ID"];
		//$lastITCCLASSID = $row["ITCCLASS_ID"];
		
		
	} while($row = mysql_fetch_assoc($result));
	mysql_free_result($result);
	echo "</table>";
}		
		
	} else if($_SESSION["permissions_levelFK"] == 3) {
		echo "<p>Show summary of individual center.</p>";
	} else {
		echo "<p>Show teacher attendance summary.</p>";	
	}

}


function outputExecSummary(){

	GLOBAL $dbconn;	
	GLOBAL $daysToGoBack;
	GLOBAL $aPwebCredentials;
	
	/*
	Quick color reference:
		0 – red
		60 – yellow
		120 – green
		180 – turquoise
		240 – blue
		300 – pink
		360 – red
	*/			
	$colorStart = 0; 	
	$colorEnd = 120;
	$clip = 0; // we can "increase" the size of the ZERO range by "clipping" the data set
	
	if(date("N") == 1){
		$yesterday = date("Y-m-d",mktime(0,0,0,date("m"),date("d")-3,date("Y")));
	} else if(date("N") <= 5){
		$yesterday = date("Y-m-d",mktime(0,0,0,date("m"),date("d")-1,date("Y")));
	} else {
		$yesterday = date("Y-m-d",mktime(0,0,0,date("m"),date("d")-(date("N") - 5),date("Y")));	
	}

	// set a default 
	if(empty($_GET["cid"])) { $_GET["cid"] = 215; }

	$cnt = 0;
	foreach($aPwebCredentials as $k => $v){
		if($cnt > 0){
			echo " - ";		
		}
		echo "<a href=\"emarc.php?cid=".$v["ICAB_ID"]."\">".$k."</a>";
		if($v["ICAB_ID"] == $_GET["cid"]){ $sLocation = $k; } 
		$cnt++;	
	}	
	echo "<br><br>";
	echo "<h3>Summary Report for ".$sLocation."</h3>";
	echo "<br>";

	if($_SESSION["permissions_levelFK"] < 3){
				
		# Get Teacher Name, Class Id, Class Name, Class Description, Center Id, Center Name
		$sql  = "SELECT "; 
		$sql .= "`ez2_import-tcStaff`.`ISTAFF_ID` as 'Staff Id', "; 
		$sql .= "`ez2_import-tcStaff`.`CFIRSTNAME` as 'Teacher First Name', "; 
		$sql .= "`ez2_import-tcStaff`.`CLASTNAME` as 'Teacher Last Name', "; 
		$sql .= "`ez2_import-tcStaff`.`ICLASS_ID`, "; 
		//$sql .= "`ez2_import-tcClass`.`ITCCLASSID` as 'ITCCLASS_ID', "; 
		$sql .= "`ez2_import-tcClass`.`CCLASS_NAM` as 'Class Name', "; 
		$sql .= "`ez2_import-tcClass`.`CDES` as 'Class Description', "; 
		$sql .= "`ez2_import-tcClass`.`ICAPACITY`, "; 
		$sql .= "`ez2_import-tcStaff`.`ICAB_ID`, "; 
		$sql .= "`ez2_import-tcCabReg`.`CCAB_NAME` as 'Center Name' "; 
		$sql .= "FROM "; 
		$sql .= "`ez2_import-tcStaff`, "; 
		$sql .= "`ez2_import-tcClass`, "; 
		$sql .= "`ez2_import-tcCabReg` "; 
		$sql .= "WHERE "; 
		$sql .= "`ez2_import-tcCabReg`.`CCAB_NAME` LIKE '%".date("Y")."%' AND "; 
		$sql .= "`ez2_import-tcCabReg`.`ICAB_ID` = `ez2_import-tcStaff`.`ICAB_ID` AND "; 
		$sql .= "`ez2_import-tcStaff`.`ICAB_ID` = `ez2_import-tcClass`.`ICAB_ID` AND "; 
		$sql .= "`ez2_import-tcClass`.`ICLASS_ID` = `ez2_import-tcStaff`.`ICLASS_ID` AND "; 
		$sql .= "`ez2_import-tcStaff`.`ICAB_ID` = '".mysql_real_escape_string($_GET["cid"])."' "; 
		$sql .= "ORDER BY "; 
		$sql .= "`ez2_import-tcStaff`.`ICAB_ID` ASC, "; 
		$sql .= "`ez2_import-tcStaff`.`ICLASS_ID` ASC, "; 
		$sql .= "`ez2_import-tcStaff`.`ISTAFF_ID` ASC "; 
		$sql .= ";";		
		
		$totalBackswipes = 0;
		$totalBackswipesYesterday = 0;
		
		$result = mysql_query($sql);
		if($row = mysql_fetch_assoc($result)){
			
			$lastIcabId = null;
			$lastIclassId = null;
			$lastITCCLASSID = null;
			$title20cnt = 0;
			$pWebCntYesterday = 0;
			do{
				if(!empty($lastIclassId) && $lastIclassId != $row["ICLASS_ID"]){
		
					$sql1  = "SELECT ";
					$sql1 .= "`ez2_import-tcChild`.`ICHILD_ID`, ";
					$sql1 .= "`ez2_import-tcChild`.`ICHILD_ID` as 'Student Id', ";
					//$sql1 .= "`ez2_import-tcChild`.`ITCCHILDID` as 'Attendance Id', ";
					//$sql1 .= "`ez2_import-tcChild`.`ITCCHILDID` as 'ITCCHILD_ID', ";
					$sql1 .= "`ez2_import-tcChild`.`ICAB_ID`, ";
					$sql1 .= "`ez2_import-tcChild`.`ICLASS_ID`, ";
					$sql1 .= "`ez2_import-tcChild`.`CFIRSTNAME` as 'Child First Name', ";
					$sql1 .= "`ez2_import-tcChild`.`CLASTNAME` as 'Child Last Name', ";
					$sql1 .= "`ez2_import-tcChild`.`CBIRTHDATE` as 'Birthdate', ";
					$sql1 .= "`ez2_import-tcChild`.`CCHILDAGE` as 'Age', ";
					$sql1 .= "`ez2_import-tcChild`.`IFAMILY_ID` ";
					$sql1 .= "FROM `ez2_import-tcChild` ";
					$sql1 .= "WHERE `ez2_import-tcChild`.`ICAB_ID` = '".$lastIcabId."' ";
					$sql1 .= "AND `ez2_import-tcChild`.`ICLASS_ID` = '".$lastIclassId."' ";
					$sql1 .= "AND `ez2_import-tcChild`.`ISTATUS` = 1 ";
		
					$result1 = mysql_query($sql1);
					if($row1 = mysql_fetch_assoc($result1)){
										
						do{
							$isTitle20 = getTitle20($row1["ICHILD_ID"], $row1["ICAB_ID"]);
							
							if($isTitle20 === true){
								$sql2  = "SELECT ";
								$sql2 .= "`NTOTAL`, ";
								$sql2 .= "`CMON_TIMES`, ";
								$sql2 .= "`CTUE_TIMES`, ";
								$sql2 .= "`CWED_TIMES`, ";
								$sql2 .= "`CTHU_TIMES`, ";
								$sql2 .= "`CFRI_TIMES` ";
								$sql2 .= "FROM `ez2_import-dbChiSch` ";
								$sql2 .= "WHERE `ICHILD_ID` = '".$row1["ICHILD_ID"]."' ";
								//$sql2 .= "AND `ICLASS_ID` = '".$row1["ICLASS_ID"]."' ";
			
								$sChildSchedule = null;
								$iChildCareHours = 0;
								$aSchedule = null;
								
								$result2 = mysql_query($sql2);
								
								if($row2 = mysql_fetch_assoc($result2)){
									do{
										$iChildCareHours = $iChildCareHours + $row2["NTOTAL"];
			
										$aSchedule[1] = $row2["CMON_TIMES"]; 
										$aSchedule[2] = $row2["CTUE_TIMES"];
										$aSchedule[3] = $row2["CWED_TIMES"];
										$aSchedule[4] = $row2["CTHU_TIMES"];
										$aSchedule[5] = $row2["CFRI_TIMES"];
										
									} while ($row2 = mysql_fetch_assoc($result2));
									
									mysql_free_result($result2);
								}
								
								
								$sql2  = "SELECT ";
								$sql2 .= "`ICHILD_ID`, ";
								$sql2 .= "`ICLASS_ID`, ";
								$sql2 .= "`ICAB_ID`, ";
								$sql2 .= "`source`, ";
								$sql2 .= "`in_datetime`, ";
								$sql2 .= "`in_status`, ";
								$sql2 .= "`out_datetime`, ";
								$sql2 .= "`out_status` ";
								$sql2 .= "FROM `student-attendance` ";
								$sql2 .= "WHERE 1 = 1 ";
								$sql2 .= "AND (`source` = 'pweb_import-transaction' OR `source` = 'tablet') ";
								$sql2 .= "AND `ICHILD_ID` = '".$row1["ICHILD_ID"]."' ";
								$sql2 .= "AND `ICLASS_ID` = '".$row1["ICLASS_ID"]."' ";
								$sql2 .= "AND `ICAB_ID` = '".$row1["ICAB_ID"]."' ";
								$sql2 .= "AND UNIX_TIMESTAMP(`in_datetime`) <= '".time()."' ";
								$sql2 .= "AND UNIX_TIMESTAMP(`in_datetime`) >= '".mktime(0, 0, 0, date("m"), date("d")-$daysToGoBack, date("Y"))."' ";
								$sql2 .= "AND `out_datetime` IS NOT NULL ";
								$sql2 .= "ORDER BY `in_datetime` DESC ";
								$sql2 .= ", `source` DESC ";
								$sql2 .= ";";
								
								$result2 = mysql_query($sql2);
			
								$sAttendance = null;
								$aAttendance = null;
								$pWebCnt = 0;
								$tabletOrCnt = 0;
								$tabletCnt = 0;
			
								if($row2 = mysql_fetch_assoc($result2)){
			
									$lastSource = $row2["source"];
									$lastInTime = strtotime($row2["in_datetime"]);							
									$lastOutTime = strtotime($row2["out_datetime"]);						
									
									$lastSource = $row2["source"];
									$lastOutStatus = $row2["out_status"];
									$lastOutTime = strtotime($row2["out_datetime"]);						
									
									do{
										if( date("Ymd",strtotime($row2["in_datetime"])) == date("Ymd",$lastInTime) && strtotime($row2["in_datetime"]) != $lastInTime && ($lastSource == "pweb_import-transaction" && $row2["source"] == "tablet" ) ){
			
											$aAttendance[date("Ymd",strtotime($row2["in_datetime"]))]["IN"] = date("Y-m-d H:i:s",strtotime($row2["in_datetime"]));
											$aAttendance[date("Ymd",strtotime($row2["in_datetime"]))]["IN-Status"] = $row2["in_status"];									
											$aAttendance[date("Ymd",strtotime($row2["in_datetime"]))]["Source"] = ($row2["source"]=="tablet"?"Tablet":($row2["source"]=="pweb_import-transaction"?"pWeb":($row2["source"]=="ez2_import-tcActAtt"?"ez2Care":"&nbsp;")));									
												
										} else {
												
											$aAttendance[date("Ymd",strtotime($row2["in_datetime"]))]["IN"] = date("Y-m-d H:i:s",strtotime($row2["in_datetime"]));
											$aAttendance[date("Ymd",strtotime($row2["in_datetime"]))]["IN-Status"] = $row2["in_status"];
											$aAttendance[date("Ymd",strtotime($row2["in_datetime"]))]["Source"] = ($row2["source"]=="tablet"?"Tablet":($row2["source"]=="pweb_import-transaction"?"pWeb":($row2["source"]=="ez2_import-tcActAtt"?"ez2Care":"&nbsp;")));
											
										}
										if( date("Ymd",strtotime($row2["out_datetime"])) == date("Ymd",$lastOutTime) && strtotime($row2["out_datetime"]) != $lastOutTime && ($lastSource == "pweb_import-transaction" && $row2["source"] == "tablet" ) ){
												
											$aAttendance[date("Ymd",strtotime($row2["out_datetime"]))]["OUT"] = date("Y-m-d H:i:s",strtotime($row2["out_datetime"]));
											$aAttendance[date("Ymd",strtotime($row2["out_datetime"]))]["OUT-Status"] = $row2["out_status"];
											$aAttendance[date("Ymd",strtotime($row2["out_datetime"]))]["Source"] = ($row2["source"]=="tablet"?"Tablet":($row2["source"]=="pweb_import-transaction"?"pWeb":($row2["source"]=="ez2_import-tcActAtt"?"ez2Care":"&nbsp;")));
												
											$tabletOrCnt++;
											$tabletCnt++;
												
										} else {
			
											$aAttendance[date("Ymd",strtotime($row2["out_datetime"]))]["OUT"] = date("Y-m-d H:i:s",strtotime($row2["out_datetime"]));
											$aAttendance[date("Ymd",strtotime($row2["out_datetime"]))]["OUT-Status"] = $row2["out_status"];
											$aAttendance[date("Ymd",strtotime($row2["out_datetime"]))]["Source"] = ($row2["source"]=="tablet"?"Tablet":($row2["source"]=="pweb_import-transaction"?"pWeb":($row2["source"]=="ez2_import-tcActAtt"?"ez2Care":"&nbsp;")));
											
											if($row2["source"]!="tablet"){
												$pWebCnt++;
												
												if(strtotime(date("Y-m-d",strtotime($row2["in_datetime"]))) == strtotime($yesterday) && $isTitle20 === true){
													$pWebCntYesterday++;
												}
												
											} else if ($lastSource == "tablet" && $row2["source"] == "tablet" ) {
												$tabletOrCnt++;								
											}
										}
			
										$lastSource = $row2["source"];
										$lastInStatus = $row2["in_status"];
										$lastOutStatus = $row2["out_status"];
										$lastInTime = strtotime($row2["in_datetime"]);							
										$lastOutTime = strtotime($row2["out_datetime"]);							
										
									} while($row2 = mysql_fetch_assoc($result2));
								}
								
								if($isTitle20 === true){
									$totalBackswipes = $totalBackswipes + (scheduledDaysInRange($daysToGoBack, $aSchedule) - $pWebCnt);
									$title20cnt++;
								} 
			
								$pWebCnt = 0;
								$tabletCnt = 0;
								$tabletOrCnt = 0;		
							}
						} while ($row1 = mysql_fetch_assoc($result1));
						mysql_free_result($result1);
					}
			
					$lastIcabId = null;
					$lastIclassId = null;		
				} 
				
				$lastIcabId = $row["ICAB_ID"];
				$lastIclassId = $row["ICLASS_ID"];
				//$lastITCCLASSID = $row["ITCCLASS_ID"];
				
				
			} while($row = mysql_fetch_assoc($result));
			
			mysql_free_result($result);

			$centerCurrentAttendance = getCenterCurrentAttendance($_GET["cid"]);			
			
			echo $centerCurrentAttendance ." students at ".date("Y-m-d H:i:s",strtotime(date("Y-m-d H:i:s")." -10:00"))." ";
			echo "with ".getCenterClassAttendanceReporting($_GET["cid"])." of ".getCenterClassCount($_GET["cid"])." classes reporting<br>";

			$percentage = round((($centerCurrentAttendance/getCenterCapacity($_GET["cid"]))*100));
 			   
			$a = ($percentage <= $clip) ? 0 : ( ($percentage - $clip) / (100 - $clip)) ;
			$b = abs($colorEnd - $colorStart)*$a;
			$c = ($colorEnd > $colorStart) ? ($colorStart + $b) : ($colorStart - $b); 
				
			$aRGB = hslToRgb( $c, 1, 0.5 );
			$sHexColor = rgb2hex($aRGB);
			
			echo "<span style=\"color: ".$sHexColor."; font-size: 24px; font-weight: bold; vertical-align: top;-webkit-text-shadow: -1px -1px 0 rgba(0,0,0,0.5), 1px -1px 0 rgba(0,0,0,0.5), -1px  1px 0 rgba(0,0,0,0.5), 1px  1px 0 rgba(0,0,0,0.5); -moz-text-shadow: -1px -1px 0 rgba(0,0,0,0.5), 1px -1px 0 rgba(0,0,0,0.5), -1px  1px 0 rgba(0,0,0,0.5), 1px  1px 0 rgba(0,0,0,0.5); text-shadow: -1px -1px 0 rgba(0,0,0,0.5), 1px -1px 0 rgba(0,0,0,0.5), -1px  1px 0 rgba(0,0,0,0.5), 1px  1px 0 rgba(0,0,0,0.5);\">".$percentage."</span>% capacity (out of ".getCenterCapacity($_GET["cid"])." total)<br>";

			$fb = 1;
			for($i = 0; $i <= 100; $i += 5){
			   $percentage = $i;
		 			   
				$a = ($percentage <= $clip) ? 0 : ( ($percentage - $clip) / (100 - $clip)) ;
				$b = abs($colorEnd - $colorStart)*$a;
				$c = ($colorEnd > $colorStart) ? ($colorStart + $b) : ($colorStart - $b);
				
				$aRGB = hslToRgb( $c, 1, 0.5 );
				$sHexColor = rgb2hex($aRGB);
						
				//echo "<div style=\"text-align: center; min-width: 32px; border: 1px solid #000; margin-right: 4px; float: left; background: hsl(".$c.",100%,50%);\">".$i."%</div>";
				if($fb == 0){	
					echo "<div style=\" background: ".$sHexColor."; width: 6px; border: 1px solid ".$sHexColor."; margin: 0; float: left; font-size: 10px;\">&nbsp;</div>";
					$fb = 1;
				} else {
					echo "<div style=\" background: ".$sHexColor."; text-align: center; min-width: 22px;  border: 1px solid #000; border-top: 1px solid ".$sHexColor."; border-bottom: 1px solid ".$sHexColor."; margin: 0; padding: 0 2px; font-size: 10px; float: left;\">".$i."</div>";
					$fb = 0;
				} 	
			}
						
			echo "<br style=\"clear: all;\">";
			echo "<br>";
			
			// TO DO:
			// Fix backswipe counts to be correct, like in the actual backswipe report. 
			// The calculations shown here were developed early on and were based on naive understandings of the data
						
			/*			
			echo $totalBackswipes." backswipes needed for the last ".daysMinusWeekends($daysToGoBack)." days ";
			//echo "(<a href=\"emarc.php?cid=".$_GET["cid"]."&gd=1\">See Details</a>)";
			echo "<br>";
			echo ($title20cnt - $pWebCntYesterday)." backswipes needed for ".$yesterday."<br>";
			echo "<br>";
			*/
			
			echo "<a href=\"emarc.php?cid=".$_GET["cid"]."&USDA=1\">USDA report for ".$sLocation."</a><br>";
			echo "<a href=\"output.usda.forms.php?cid=".$_GET["cid"]."&ml=1\" target=\"_blank\">MASTER LIST report for ".$sLocation."</a><br>";
			echo "<br>";
			
			echo "<a href=\"emarc.php?cid=".$_GET["cid"]."&ar=1\">Attendance Reports</a><br>";
			echo "<br>";

//			mysql_free_result($result);
		}	
		
	} else if($_SESSION["permissions_levelFK"] == 3) {
		echo "<p>Show summary of individual center.</p>";
	} else {
		echo "<p>Show teacher attendance summary.</p>";	
	}

}


function outputCenterBackswipeDetails(){

	GLOBAL $dbconn;	
	GLOBAL $daysToGoBack;
	GLOBAL $aPwebCredentials;

	$sOutput = null;	
	
	if(date("N") == 1){
		$yesterday = date("Y-m-d",mktime(0,0,0,date("m"),date("d")-3,date("Y")));
	} else if(date("N") <= 5){
		$yesterday = date("Y-m-d",mktime(0,0,0,date("m"),date("d")-1,date("Y")));
	} else {
		$yesterday = date("Y-m-d",mktime(0,0,0,date("m"),date("d")-(date("N") - 5),date("Y")));	
	}

	// set a default 
	if(empty($_GET["cid"])) { $_GET["cid"] = 215; }
	
	$filename = "backswipe.report.".$_GET["cid"].".html";

	if(file_exists($filename) && filemtime($filename) >= (time() - (3600 * 3)) ){
		echo file_get_contents($filename);
		echo "<div style=\"font-size: 75%;\">This cached report was generated on: ".date("Y-m-d h:i:s a",filemtime($filename)+(3600*3))." EDT</div>";
		echo "<br>";
	} else {
		
		$cnt = 0;
		foreach($aPwebCredentials as $k => $v){
			//if($cnt > 0){
			//	echo " - ";		
			//}
			//echo "<a href=\"emarc.php?cid=".$v["ICAB_ID"]."\">".$k."</a>";
			if($v["ICAB_ID"] == $_GET["cid"]){ $sLocation = $k; } 
			//$cnt++;	
		}	
		
		$sOutput .= "<br>";
		$sOutput .= "<h3>Missing backswipes at ".$sLocation."</h3>";
		$sOutput .= "<br>";
	
		if($_SESSION["permissions_levelFK"] < 3){
					
			# Get Teacher Name, Class Id, Class Name, Class Description, Center Id, Center Name
			$sql  = "SELECT ";
			$sql .= "DISTINCT(`ez2_import-tcStaff`.`ICLASS_ID`), "; 
	//		$sql .= "`ez2_import-tcStaff`.`ISTAFF_ID` as 'Staff Id', "; 
	//		$sql .= "`ez2_import-tcStaff`.`CFIRSTNAME` as 'Teacher First Name', "; 
	//		$sql .= "`ez2_import-tcStaff`.`CLASTNAME` as 'Teacher Last Name', "; 
			//$sql .= "`ez2_import-tcStaff`.`ICLASS_ID`, "; 
			//$sql .= "`ez2_import-tcClass`.`ITCCLASSID` as 'ITCCLASS_ID', "; 
			$sql .= "`ez2_import-tcClass`.`CCLASS_NAM` as 'Class Name', "; 
			$sql .= "`ez2_import-tcClass`.`CDES` as 'Class Description', "; 
			$sql .= "`ez2_import-tcClass`.`ICAPACITY`, "; 
			$sql .= "`ez2_import-tcStaff`.`ICAB_ID`, "; 
			$sql .= "`ez2_import-tcCabReg`.`CCAB_NAME` as 'Center Name' "; 
			$sql .= "FROM "; 
			$sql .= "`ez2_import-tcStaff`, "; 
			$sql .= "`ez2_import-tcClass`, "; 
			$sql .= "`ez2_import-tcCabReg` "; 
			$sql .= "WHERE "; 
			$sql .= "`ez2_import-tcCabReg`.`CCAB_NAME` LIKE '%".date("Y")."%' AND "; 
			$sql .= "`ez2_import-tcCabReg`.`ICAB_ID` = `ez2_import-tcStaff`.`ICAB_ID` AND "; 
			$sql .= "`ez2_import-tcStaff`.`ICAB_ID` = `ez2_import-tcClass`.`ICAB_ID` AND "; 
			$sql .= "`ez2_import-tcClass`.`ICLASS_ID` = `ez2_import-tcStaff`.`ICLASS_ID` AND "; 
			$sql .= "`ez2_import-tcStaff`.`ICAB_ID` = '".mysql_real_escape_string($_GET["cid"])."' "; 
			$sql .= "ORDER BY "; 
			$sql .= "`ez2_import-tcStaff`.`ICAB_ID` ASC, "; 
			$sql .= "`ez2_import-tcStaff`.`ICLASS_ID` ASC, "; 
			$sql .= "`ez2_import-tcStaff`.`ISTAFF_ID` ASC "; 
			$sql .= ";";		
			
			$totalBackswipes = 0;
	
			$result = mysql_query($sql);
			if($row = mysql_fetch_assoc($result)){
	
				// DEBUG
				//echo "New classroom: ".date("h:i:s",time())."<br>";			
				
				$sOutput .= "<table>";		
				$sOutput .= "<tr>";
				$sOutput .= "<th>Name</th>";
				$sOutput .= "<th>Status</th>";
				$sOutput .= "<th>Fr/Rd</th>";
				//$sOutput .= "<th>last ".daysMinusWeekends($daysToGoBack)." days</th>";
				$sOutput .= "<th class=\"align_center\">missing swipes</th>";
				//$sOutput .= "<th>for ".$yesterday."</th>";
				$sOutput .= "<th>unpaid days</th>";
				$sOutput .= "</tr>";
					
				
				$lastIcabId = null;
				$lastIclassId = null;
				$lastITCCLASSID = null;
				do{
					if(!empty($lastIclassId) && $lastIclassId != $row["ICLASS_ID"]){
			
						$sql1  = "SELECT ";
						$sql1 .= "DISTINCT(`ez2_import-tcChild`.`ICHILD_ID`), ";
						$sql1 .= "`ez2_import-tcChild`.`ICHILD_ID` as 'Student Id', ";
						//$sql1 .= "`ez2_import-tcChild`.`ITCCHILDID` as 'Attendance Id', ";
						//$sql1 .= "`ez2_import-tcChild`.`ITCCHILDID` as 'ITCCHILD_ID', ";
						$sql1 .= "`ez2_import-tcChild`.`ICAB_ID`, ";
						$sql1 .= "`ez2_import-dbChiSch`.`ICLASS_ID`, ";
						$sql1 .= "`ez2_import-tcChild`.`CFIRSTNAME` as 'Child First Name', ";
						$sql1 .= "`ez2_import-tcChild`.`CLASTNAME` as 'Child Last Name', ";
						$sql1 .= "`ez2_import-tcChild`.`CBIRTHDATE` as 'Birthdate', ";
	//					$sql1 .= "`ez2_import-tcChild`.`CCHILDAGE` as 'Age', ";
						$sql1 .= "`ez2_import-tcChild`.`IFAMILY_ID` ";
						$sql1 .= "FROM `ez2_import-tcChild`, `ez2_import-dbChiSch` ";
						$sql1 .= "WHERE `ez2_import-dbChiSch`.`ICAB_ID` = '".$lastIcabId."' ";
						$sql1 .= "AND `ez2_import-tcChild`.`ICAB_ID` = '".$lastIcabId."' ";
						$sql1 .= "AND `ez2_import-dbChiSch`.`ICHILD_ID` = `ez2_import-tcChild`.`ICHILD_ID` ";
						$sql1 .= "AND `ez2_import-dbChiSch`.`ICLASS_ID` = '".$lastIclassId."' ";
						$sql1 .= "AND `ez2_import-tcChild`.`ISTATUS` = 1 ";
						$sql1 .= "ORDER BY `ez2_import-tcChild`.`CLASTNAME` ASC ";
						$sql1 .= ", `ez2_import-tcChild`.`CFIRSTNAME` ASC ";
	
						$result1 = mysql_query($sql1);
						if($row1 = mysql_fetch_assoc($result1)){
							
							do{
								$isTitle20 = getTitle20($row1["ICHILD_ID"], $row1["ICAB_ID"]);
								
								// DEBUG
								//echo "<tr><td colspan=\"5\">Just checked title20 for: ".$row1["ICHILD_ID"]." - ".date("h:i:s",time())."</td></tr>";
								
								//if($isTitle20 === true){
									//echo $isTitle20." ".$row1["ICHILD_ID"]." ".$row1["Child Last Name"].", ".$row1["Child First Name"]."<br>";
									//echo $row1["ICHILD_ID"]." ".$row1["Child Last Name"].", ".$row1["Child First Name"]."<br>";
								//}
	
								// DEBUG
								//echo "t20? ".$isTitle20."<br>";
								//echo $row1["ICHILD_ID"]." - ".$row1["ICAB_ID"]."<br>";
								if($isTitle20 === true){
									
									$sql2  = "SELECT ";
									$sql2 .= "`NTOTAL`, ";
									$sql2 .= "`CMON_TIMES`, ";
									$sql2 .= "`CTUE_TIMES`, ";
									$sql2 .= "`CWED_TIMES`, ";
									$sql2 .= "`CTHU_TIMES`, ";
									$sql2 .= "`CFRI_TIMES` ";
									$sql2 .= "FROM `ez2_import-dbChiSch` ";
									$sql2 .= "WHERE `ICHILD_ID` = '".$row1["ICHILD_ID"]."' ";
									//$sql2 .= "AND `ICLASS_ID` = '".$row1["ICLASS_ID"]."' ";
				
									$sChildSchedule = null;
									$iChildCareHours = 0;
									$aSchedule = null;
									
									$result2 = mysql_query($sql2);
									
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
									
									// DEBUG
									//echo "<tr><td colspan=\"5\">Just finished schedule lookup: ".date("h:i:s",time())."</td></tr>";
									
									$sql2  = "SELECT ";
									$sql2 .= "`ICHILD_ID`, ";
//									$sql2 .= "`ICLASS_ID`, ";
									$sql2 .= "`ICAB_ID`, ";
									$sql2 .= "`source`, ";
									$sql2 .= "`in_datetime`, ";
									$sql2 .= "`in_status`, ";
									$sql2 .= "`out_datetime`, ";
									$sql2 .= "`out_status` ";
									$sql2 .= "FROM `student-attendance` ";
									$sql2 .= "WHERE 1 = 1 ";
									$sql2 .= "AND (`source` = 'pweb_import-transaction' OR `source` = 'tablet') ";
									$sql2 .= "AND `ICHILD_ID` = '".$row1["ICHILD_ID"]."' ";
//									$sql2 .= "AND `ICLASS_ID` = '".$row1["ICLASS_ID"]."' ";
									$sql2 .= "AND `ICAB_ID` = '".$row1["ICAB_ID"]."' ";
									$sql2 .= "AND UNIX_TIMESTAMP(`in_datetime`) <= '".time()."' ";
									$sql2 .= "AND UNIX_TIMESTAMP(`in_datetime`) >= '".mktime(0, 0, 0, date("m"), date("d")-$daysToGoBack, date("Y"))."' ";
									$sql2 .= "AND `out_datetime` IS NOT NULL ";
									$sql2 .= "ORDER BY `in_datetime` DESC ";
									$sql2 .= ", `source` DESC ";
									$sql2 .= ";";
									
									$result2 = mysql_query($sql2);
									
									// DEBUG
									//echo "<tr><td colspan=\"5\">";
									//echo "sql2: ".$sql2."<br>";
									//echo "</td></tr>";
				
									$sAttendance = null;
									$aAttendance = null;
									$pWebCnt = 0;
									$aBackSwipeDates = null;
									$aPwebDates = null;
									$pWebCntYesterday = 0;
									$tabletOrCnt = 0;
									$tabletCnt = 0;
				
									if($row2 = mysql_fetch_assoc($result2)){
										
										// DEBUG
										//echo "<tr><td colspan=\"5\">Found attendance records: ".date("h:i:s",time())."</td></tr>";
										//echo "<tr><td colspan=\"5\">Time to compare attendance vs. pWeb swipes.</td></tr>";
				
										$lastSource = $row2["source"];
										$lastInTime = strtotime($row2["in_datetime"]);							
										$lastOutTime = strtotime($row2["out_datetime"]);						
										
										$lastSource = $row2["source"];
										$lastOutStatus = $row2["out_status"];
										$lastOutTime = strtotime($row2["out_datetime"]);						
										
										do{
											//if( date("Ymd",strtotime($row2["in_datetime"])) == date("Ymd",$lastInTime) && strtotime($row2["in_datetime"]) != $lastInTime && ($lastSource == "pweb_import-transaction" && $row2["source"] == "tablet" ) ){
				
											//	$aAttendance[date("Ymd",strtotime($row2["in_datetime"]))]["IN"] = date("Y-m-d H:i:s",strtotime($row2["in_datetime"]));
											//	$aAttendance[date("Ymd",strtotime($row2["in_datetime"]))]["IN-Status"] = $row2["in_status"];									
											//	$aAttendance[date("Ymd",strtotime($row2["in_datetime"]))]["Source"] = ($row2["source"]=="tablet"?"Tablet":($row2["source"]=="pweb_import-transaction"?"pWeb":($row2["source"]=="ez2_import-tcActAtt"?"ez2Care":"&nbsp;")));									
													
											//} else {
													
												$aAttendance[date("Ymd",strtotime($row2["in_datetime"]))]["IN"] = date("Y-m-d H:i:s",strtotime($row2["in_datetime"]));
												$aAttendance[date("Ymd",strtotime($row2["in_datetime"]))]["IN-Status"] = $row2["in_status"];
												$aAttendance[date("Ymd",strtotime($row2["in_datetime"]))]["Source"] = ($row2["source"]=="tablet"?"Tablet":($row2["source"]=="pweb_import-transaction"?"pWeb":($row2["source"]=="ez2_import-tcActAtt"?"ez2Care":"&nbsp;")));
												
											//}
										
											if( date("Ymd",strtotime($row2["out_datetime"])) == date("Ymd",$lastOutTime) && strtotime($row2["out_datetime"]) != $lastOutTime && ($lastSource == "pweb_import-transaction" && $row2["source"] == "tablet" ) ){
													
												$aAttendance[date("Ymd",strtotime($row2["out_datetime"]))]["OUT"] = date("Y-m-d H:i:s",strtotime($row2["out_datetime"]));
												$aAttendance[date("Ymd",strtotime($row2["out_datetime"]))]["OUT-Status"] = $row2["out_status"];
												$aAttendance[date("Ymd",strtotime($row2["out_datetime"]))]["Source"] = ($row2["source"]=="tablet"?"Tablet":($row2["source"]=="pweb_import-transaction"?"pWeb":($row2["source"]=="ez2_import-tcActAtt"?"ez2Care":"&nbsp;")));
													
												$tabletOrCnt++;
												$tabletCnt++;
													
											} else {
				
												$aAttendance[date("Ymd",strtotime($row2["out_datetime"]))]["OUT"] = date("Y-m-d H:i:s",strtotime($row2["out_datetime"]));
												$aAttendance[date("Ymd",strtotime($row2["out_datetime"]))]["OUT-Status"] = $row2["out_status"];
												$aAttendance[date("Ymd",strtotime($row2["out_datetime"]))]["Source"] = ($row2["source"]=="tablet"?"Tablet":($row2["source"]=="pweb_import-transaction"?"pWeb":($row2["source"]=="ez2_import-tcActAtt"?"ez2Care":"&nbsp;")));
												
												if($row2["source"]!="tablet"){
													$pWebCnt++;
		
													if($row2["source"]!="pweb"){
														$aPwebDates[date("Ymd",strtotime($row2["out_datetime"]))] = date("Ymd",strtotime($row2["out_datetime"]));
													}
		
													if(strtotime(date("Y-m-d",strtotime($row2["in_datetime"]))) == strtotime($yesterday) && $isTitle20 === true){
														$pWebCntYesterday++;
														//echo "<tr><td colspan=\"4\">found a yesterday! ".$pWebCntYesterday." - ".$row1["Child First Name"]." ".$row1["Child Last Name"]."</td></tr>";
													}
												} else if ($lastSource == "tablet" && $row2["source"] == "tablet" ) {
													$tabletOrCnt++;
												}
											}
				
											$lastSource = $row2["source"];
											$lastInStatus = $row2["in_status"];
											$lastOutStatus = $row2["out_status"];
											$lastInTime = strtotime($row2["in_datetime"]);							
											$lastOutTime = strtotime($row2["out_datetime"]);							
	
											// DEBUG
											//echo "<tr><td colspan=\"5\">Comparing records: ".date("h:i:s",time())."</td></tr>";
											
										} while($row2 = mysql_fetch_assoc($result2));
										
										mysql_free_result($result2);
									}
									
									// DEBUG
									/*
									echo "<tr><td colspan=\"5\">";
									echo "aPwebDates:<br>";
									echo "<pre>".var_export($aPwebDates,true)."</pre>";
									echo "<hr>";
									echo "aAttendance:<br>";
									echo "<pre>".var_export($aAttendance,true)."</pre>";
									echo "</td></tr>";
									*/
	
									$schDaysInRange = scheduledDaysInRange($daysToGoBack, $aSchedule);							
								
									$totalBackswipes = $totalBackswipes + ($schDaysInRange - $pWebCnt);
									
									if(($schDaysInRange - $pWebCnt) > 0 && count($aAttendance) > 0){
										
										$bs = ($schDaysInRange - $pWebCnt);
	
										$bs = 0;
										foreach($aAttendance as $k => $v){
											if(empty($aPwebDates[$k])){
												$bs++;
											}									
										}
										
										$sOutput .= "<tr>";
										$sOutput .= "<td>".$row1["Child Last Name"].", ".$row1["Child First Name"]."</td>";
										$sOutput .= "<td class=\"text-align_center\">".($iChildCareHours > 0?(($iChildCareHours >= 25)?"(FT)":"(PT)"):"FT - ?")."</td>";
										$sOutput .= "<td class=\"text-align_center\">".strtoupper(substr(getFreeReduced($row1["ICHILD_ID"], $row1["ICAB_ID"]),0,1))."</td>";
										$sOutput .= "<td class=\"text-align_right\">".($bs==0?"<span class=\"fa fa-check color_green\"></span>":$bs);
										//echo "<hr>".var_export($aPwebDates,true)."</pre>";
										//echo "<hr>".var_export($aSchedule,true)."</pre>";
										//echo "<hr>";
										//echo "scheduled days: ".scheduledDaysInRange($daysToGoBack, $aSchedule)."<br>";
										//echo "# of attendance records: ".count($aAttendance)."<br>";
										if($bs > 0){
											$sOutput .= " missing swipe".($bs > 1?"s":null)." for:"; 
											foreach($aAttendance as $k => $v){
												if(empty($aPwebDates[$k])){
													$sOutput .= "<br>";
													$sOutput .= date("m-d-Y",strtotime($k));
													$sOutput .= " - ".date("h:i a",strtotime($v["IN"]))." - ".date("h:i a",strtotime($v["OUT"]));
													//echo "<br><pre>".var_export($aAttendance[$k],true)."</pre><br>";
												}									
											}
										}
										$sOutput .= "</td>";
										//echo "<td class=\"text-align_center\">".($pWebCntYesterday>0?"<span class=\"fa fa-check color_green\"></span>":"<span class=\"fa fa-ban color_red\"></span>")."</td>";
										$sOutput .= "<td class=\"text-align_center\">".getUnpaidDays($_GET["cid"],$row1["ICHILD_ID"])."</td>";
										$sOutput .= "</tr>";
										$numChildren++;
										
										// DEBUG
										//echo "<tr><td colspan=\"5\">Finished displaying data for ".$row1["ICHILD_ID"]." - ".date("h:i:s",time())."</td></tr>";
										
									}
									
								} 
			
								$pWebCnt = 0;
								$pWebCntYesterday = 0;
								$tabletCnt = 0;
								$tabletOrCnt = 0;					
								
							} while ($row1 = mysql_fetch_assoc($result1));
							mysql_free_result($result1);
						}
				
						$lastIcabId = null;
						$lastIclassId = null;		
					} 
					
					$lastIcabId = $row["ICAB_ID"];
					$lastIclassId = $row["ICLASS_ID"];
					//$lastITCCLASSID = $row["ITCCLASS_ID"];
					
					
				} while($row = mysql_fetch_assoc($result));
				mysql_free_result($result);
				
				$sOutput .= "</table>";
				
				// DEBUG
				//echo "Finished displaying backswipe details: ".date("h:i:s",time())."<br>";
	
				$sOutput .= "<br>";
				$sOutput .= "Title20 students: ".$numChildren."<br>";			
				//$sOutput .= getCenterCurrentAttendance($_GET["cid"])." students at ".date("Y-m-d H:i:s")."<br>";
				//$sOutput .= round(((getCenterCurrentAttendance($_GET["cid"])/getCenterCapacity($_GET["cid"]))*100))."% capacity (out of ".getCenterCapacity($_GET["cid"])." total)<br>";
				//$sOutput .= $totalBackswipes." backswipes needed for the last ".daysMinusWeekends($daysToGoBack)." days<br>";
				//$sOutput .= "<br>";
				//$sOutput .= "(<a href=\"emarc.php?noexec=1\">See Details for All Centers</a>)<br>";
				$sOutput .= "<br>";
				
			}		

			if(is_writable($filename)) {
		   	if (!$handle = fopen($filename, 'w+')) {
		      	echo "Cannot open file ($filename)";
		   	 	exit;
			   }
		
			   if (fwrite($handle, $sOutput) === FALSE) {
			   	echo "Cannot write to file ($filename)";
			   	exit;
			   }
			   fclose($handle);
			}
			
			echo $sOutput;			
			
		} else if($_SESSION["permissions_levelFK"] == 3) {
			$sOutput .= "<p>Show summary of individual center.</p>";
		} else {
			$sOutput .= "<p>Show teacher attendance summary.</p>";	
		}
		
	}

}

function outputMonthlyAttendanceCalendar(){
	
	/*
	Quick color reference:
		0 – red
		60 – yellow
		120 – green
		180 – turquoise
		240 – blue
		300 – pink
		360 – red
	*/			
	$colorStart = 0; 	
	$colorEnd = 120;
	$clip = 0; // we can "increase" the size of the ZERO range by "clipping" the data set
	
	// set a default 
	if(empty($_GET["cid"])) { $_GET["cid"] = 215; }
	
	$capacity = getCenterCapacity($_GET["cid"]);
	
	$startDate = (!empty($_GET["start_date"])?strtotime($_GET["start_date"]):mktime(0, 0, 0, date("m"), 1, date("Y")));
	$calendarStartDate = $startDate;
	$endDate = (!empty($_GET["end_date"])?strtotime($_GET["end_date"]):mktime(0, 0, 0, date("m"), date("d")-1, date("Y")));
	$calendarEndDate = ($endDate < strtotime(date("Ymt",$endDate))?strtotime(date("Ymt",$endDate)):$endDate );

	if(date("N",$startDate) > 1){
		$calendarStartDate = $startDate - ((3600 * 24) * (date("N",$startDate) - 1));
	}
	if(date("N",$calendarEndDate) < 7){
		$calendarEndDate = $calendarEndDate + ((3600 * 24) * (7 - date("N",$calendarEndDate)));
	}

	if(date("n",$startDate) == date("n",$endDate) && date("Y",$startDate) == date("Y",$endDate)){
		echo "<h2>".date("F Y",$startDate)."</h2>";
	} else {
		echo "<h2>".date("F Y",$startDate)." - ".date("F - Y",$endDate)."</h2>";	
	}
	
	echo "<table class=\"capacity_calendar\">";
	echo "<tr>";
	echo "<th>Monday</th>";
	echo "<th>Tuesday</th>";
	echo "<th>Wednesday</th>";
	echo "<th>Thursday</th>";
	echo "<th>Friday</th>";
	//echo "<th>Saturday</th>";
	//echo "<th>Sunday</th>";
	echo "</tr>";
	for($i = $calendarStartDate; $i <= $calendarEndDate; $i += (3600 * 24) ){
		if(date("N",$i) <= 5){
			if(date("N",$i) == 1){
				echo "<tr>";		
			}
			echo "<td>";
			//echo date("N - D - Ymd",$i)." - ";	
			if($i >= $startDate && $i <= $endDate){
				echo date("jS",$i)." - ";
				$dailyAttendance = getCenterAttendanceForDate($_GET["cid"],$i);

 			   $percentage = round((($dailyAttendance/$capacity)*100));
 			   
				$a = ($percentage <= $clip) ? 0 : ( ($percentage - $clip) / (100 - $clip)) ;
				$b = abs($colorEnd - $colorStart)*$a;
				$c = ($colorEnd > $colorStart) ? ($colorStart + $b) : ($colorStart - $b); 
				
				$aRGB = hslToRgb( $c, 1, 0.5 );
				$sHexColor = rgb2hex($aRGB);
				
				//echo "<span style=\"color: hsl(".$c.",100%,50%); vertical-align: top; font-size: 30px; font-weight: bold; display: inline-block; margin: 0; padding: 0;\">".$dailyAttendance."</span>";
				echo "<span style=\"color: ".$sHexColor.";\">".$dailyAttendance."</span>";
			} else if ( $i == strtotime(date("Ymd"))) {
				echo date("jS",$i)." - <strong>Today</strong>";
			} else {
				echo "<span>&nbsp;</span>";		
			}
			echo "</td>";
			if(date("N",$i) == 5){
				echo "</tr>";		
			}
		}
	}
	
	echo "<tr class=\"noborder\"><td colspan=\"5\" class=\"noborder\">";
	$fb = 1;
	for($i = 0; $i <= 100; $i += 5){
	   $percentage = $i;
 			   
		$a = ($percentage <= $clip) ? 0 : ( ($percentage - $clip) / (100 - $clip)) ;
		$b = abs($colorEnd - $colorStart)*$a;
		$c = ($colorEnd > $colorStart) ? ($colorStart + $b) : ($colorStart - $b);
		
		$aRGB = hslToRgb( $c, 1, 0.5 );
		$sHexColor = rgb2hex($aRGB);
				
		//echo "<div style=\"text-align: center; min-width: 32px; border: 1px solid #000; margin-right: 4px; float: left; background: hsl(".$c.",100%,50%);\">".$i."%</div>";
		if($fb == 0){	
			echo "<div style=\" background: ".$sHexColor."; width: 6px; border: 1px solid ".$sHexColor."; margin: 0; float: left;\">&nbsp;</div>";
			$fb = 1;
		} else {
			echo "<div style=\" background: ".$sHexColor."; text-align: center; min-width: 32px; border: 1px solid #000; margin: 0; float: left;\">".$i."%</div>";
			$fb = 0;
		} 	
	}
	echo "</td></tr>";
	echo "</table>";	
	echo "<br>";
	
	echo "<br style=\"clear: both;\">";
	echo "<br>";
}

function outputUSDAreport(){
	GLOBAL $aPwebCredentials;		
	GLOBAL $aMealTimes;	
	
	$startDate = (!empty($_GET["wk"])?$_GET["wk"]:time());
	$endDate = (!empty($_GET["wk"])?$_GET["wk"] + ((3600*24)*4):time());

	if(date("N",$startDate) > 1){
		$startDate = $startDate - ((3600 * 24) * (date("N",$startDate) - 1));
	}
	if(empty($_GET["wk"])){ $_GET["wk"] = $startDate; }
	$calendarStartDate = $startDate;

	if(date("N",$endDate) < 5){
		$endDate = $endDate + ((3600 * 24) * (5 - date("N",$endDate)));
	} else if(date("N",$endDate) > 5){
		$endDate = $endDate - ((3600 * 24) * (date("N",$endDate) - 5));
	}
	
	$cnt = 0;
	foreach($aPwebCredentials as $k => $v){
		if($cnt > 0){
			echo " - ";		
		}
		echo "<a href=\"emarc.php?cid=".$v["ICAB_ID"]."\">".$k."</a>";
		if($v["ICAB_ID"] == $_GET["cid"]){ $sLocation = $k; } 
		$cnt++;	
	}
	echo "<br><br>";

	//echo "<div style=\"float: left; margin-right: 20px;\">";	
	echo "<h3>Meal Report for ".$sLocation."</h3>";
	echo "<br>";
	
	$aMinMaxWeeks = getAttendanceOldestNewest($_GET["cid"]);

	echo "<form action=\"".$_SERVER["PHP_SELF"]."\" method=\"get\">";
	echo "<label for=\"wk\">Generate report for the week of </label>";
	echo "<select name=\"wk\" id=\"wk\" style=\"width: auto;\">";
	for($i = $aMinMaxWeeks["first"];$i <= $aMinMaxWeeks["last"];$i += ((3600*24)*7) ){
		echo "<option value=\"".$i."\" ";
		if( date("Ymd",$startDate) == date("Ymd",$i) ){
			echo "selected=\"selected\" ";		
		}
		echo " >".date("M j",$i)." - ".date("M j, Y",$i + ((3600*24)*4))."</option>";	
	}	
	echo "</select>";
	echo "<input type=\"hidden\" name=\"cid\" value=\"".$_GET["cid"]."\">";
	echo "<input type=\"hidden\" name=\"USDA\" value=\"".$_GET["USDA"]."\">";
	echo "&nbsp;&nbsp;&nbsp;";
	echo "<input class=\"button\" type=\"submit\" value=\"Get Report\">";
	echo "</form>";
	//echo "</div>";
	echo "<br>";
	
	$aClassIds = getCenterClassIds($_GET["cid"]);
	foreach($aClassIds as $class_id => $class_name){
		$aStudents = getCenterClassStudentIds($_GET["cid"],$class_id);

		if(!empty($aStudents)){
			if(strpos($class_name,"Infant") !== false){
				echo "<a href=\"output.usda.forms.php?wk=".$_GET["wk"]."&cid=".$_GET["cid"]."&clid=".$class_id."\" target=\"_blank\">Generate Infant USDA forms - ".$class_name."</a><br>";		
			} else {
				echo "<a href=\"output.usda.forms.php?wk=".$_GET["wk"]."&cid=".$_GET["cid"]."&clid=".$class_id."\" target=\"_blank\">Generate Pre-K USDA forms - ".$class_name."</a><br>";
			}
		}
	}		
	echo "<br>";
	
	echo "<fieldset style=\"display: block; width: 360px; font-size: 80%;\">";
	echo "<legend>Key</legend>";
	echo "<p><span class=\"fa fa-check color_green\"></span> = Child present for meal</p>";
	echo "<p><span class=\"fa fa-lg fa-frown-o color_red\"></span> Bad Out Time = There was an IN time, but the OUT time is missing or bad.</p>";
	echo "<p>Days with no information = Days when the child is not in class.</p>";
	echo "<p>In the T20 (Title20) column:</p>";	
	echo "<p><span class=\"fa fa-check\"></span> = pWeb swipe</p>";
	echo "<p><span class=\"fa fa-ban color_red\"></span> = pWeb backswipe needed</p>";
	echo "<p>An empty T20 column indicates that the child is not enrolled for Title20 benefits.</p>";
	echo "</fieldset>";

	echo "<br class=\"clear\">";
	
	$numDays = ((($endDate + (3600*24)) - $startDate)/(3600*24)); 	
	foreach($aClassIds as $class_id => $class_name){
		
		$aStudents = getCenterClassStudentIds($_GET["cid"],$class_id);

		if(!empty($aStudents)){
		
			echo "<h3>".$class_name."</h3>";
			echo "<table class=\"usda\">";
			echo "<tr>";
			echo "<th rowspan=\"2\">Student Name</th>";
			echo "<th rowspan=\"2\" class=\"text-align_center\">Age</th>";
			echo "<th rowspan=\"2\" class=\"text-align_center\">Birthdate</th>";
	
			for($i = $startDate; $i <= $endDate; $i += (3600*24) ){
				echo "<th colspan=\"5\" class=\"text-align_center\">".date("Y-m-d",$i)."</th>";
			}
			echo "</tr><tr>";
				
			for($i = 0; $i < $numDays; $i++){
				echo "<th>B'fast</th>";
				echo "<th>AM</th>";
				echo "<th>Lunch</th>";
				echo "<th>PM</th>";
				echo "<th>T20</th>";
			}
			echo "</tr>";			
			
			$rowBit = 1;
			foreach($aStudents as $student_id => $fl_name){
				
				$aAttendance = getStudentAttendanceForRange($student_id,$_GET["cid"],$startDate,$endDate);
				$i = $startDate;
				
				if(!empty($aAttendance)){
	
					$colBit = 1;
					
					echo "<tr style=\"background:".($rowBit == 1?"#f0f0f0":"#ffffff").";\">";
					echo "<td>".$fl_name["last_name"].", ".$fl_name["first_name"]."</td>";
					$age = floor( floor( ( time() - strtotime(date("Ymd",strtotime($fl_name["birthday"]))) ) / (3600 * 24) ) / 30);
					echo "<td style=\"background:".($rowBit == 1?($colBit == 1?"#E8ECEF":"#f0f0f0"):($colBit == 1?"#F2F6F9":"#ffffff")).";\" class=\"text-align_right\">".( $age < 36?$age." mos":round(($age/12),1)." yrs" )."</td>";
					echo "<td class=\"text-align_right\">".$fl_name["birthday"]."</td>";
							
					for($i = $startDate; $i <= $endDate ; $i += (3600*24)){			
					
						$day = date("Y-m-d",$i);
						$disp = 0;
		
						if(date("Ymd",$i) == date("Ymd",strtotime($aAttendance[date("Ymd",$i)]["in_datetime"])) ) {
						
							if(strpos($aAttendance[date("Ymd",$i)]["out_datetime"],"strong") != 1){
								
								foreach($aMealTimes as $k => $v){
									if( ($v["start"] >= date("Hi",strtotime($aAttendance[date("Ymd",$i)]["in_datetime"])) || date("Hi",strtotime($aAttendance[date("Ymd",$i)]["in_datetime"])) <= $v["end"] )  && (date("Hi",strtotime($aAttendance[date("Ymd",$i)]["out_datetime"])) >= $v["start"] || $v["end"] <= date("Hi",strtotime($aAttendance[date("Ymd",$i)]["out_datetime"])) ) ) {
										echo "<td style=\"background:".($rowBit == 1?($colBit == 1?"#E8ECEF":"#f0f0f0"):($colBit == 1?"#F2F6F9":"#ffffff")).";\" class=\"text-align_center\"><span class=\"fa fa-check color_green\"></span></td>";
										$disp = 1;
									} else if( ($v["start"] >= date("Hi",strtotime($aAttendance[date("Ymd",$i)]["in1_datetime"])) || date("Hi",strtotime($aAttendance[date("Ymd",$i)]["in1_datetime"])) <= $v["end"] )  && (date("Hi",strtotime($aAttendance[date("Ymd",$i)]["out1_datetime"])) >= $v["start"] || $v["end"] <= date("Hi",strtotime($aAttendance[date("Ymd",$i)]["out1_datetime"])) ) ) {
										echo "<td style=\"background:".($rowBit == 1?($colBit == 1?"#E8ECEF":"#f0f0f0"):($colBit == 1?"#F2F6F9":"#ffffff")).";\" class=\"text-align_center\"><span class=\"fa fa-check color_green\"></span></td>";
										$disp = 1;
									} else if( ($v["start"] >= date("Hi",strtotime($aAttendance[date("Ymd",$i)]["in2_datetime"])) || date("Hi",strtotime($aAttendance[date("Ymd",$i)]["in2_datetime"])) <= $v["end"] )  && (date("Hi",strtotime($aAttendance[date("Ymd",$i)]["out2_datetime"])) >= $v["start"] || $v["end"] <= date("Hi",strtotime($aAttendance[date("Ymd",$i)]["out2_datetime"])) ) ) {
										echo "<td style=\"background:".($rowBit == 1?($colBit == 1?"#E8ECEF":"#f0f0f0"):($colBit == 1?"#F2F6F9":"#ffffff")).";\" class=\"text-align_center\"><span class=\"fa fa-check color_green\"></span></td>";
										$disp = 1;
									} else {
										echo "<td style=\"background:".($rowBit == 1?($colBit == 1?"#E8ECEF":"#f0f0f0"):($colBit == 1?"#F2F6F9":"#ffffff")).";\" class=\"text-align_center\">&nbsp;</td>";								
									}
								}
							} else {
								if(date("Ymd",$i) == date("Ymd")){
									echo "<td style=\"background:".($rowBit == 1?($colBit == 1?"#E8ECEF":"#f0f0f0"):($colBit == 1?"#F2F6F9":"#ffffff")).";\" colspan=\"4\" class=\"text-align_center\"><strong>In Class Today</strong></td>";
									$disp = 1;
								} else {
									echo "<td style=\"background:".($rowBit == 1?($colBit == 1?"#E8ECEF":"#f0f0f0"):($colBit == 1?"#F2F6F9":"#ffffff")).";\" colspan=\"4\" class=\"text-align_center\"><span class=\"fa fa-lg fa-frown-o color_red\"></span> Bad Out Time</td>";
									$disp = 1;
								}
							}
						} else {
							echo "<td style=\"background:".($rowBit == 1?($colBit == 1?"#E8ECEF":"#f0f0f0"):($colBit == 1?"#F2F6F9":"#ffffff")).";\" colspan=\"4\">&nbsp;</td>";
						}
						$colBit = ($colBit == 1?0:1);
						$isTitle20 = getTitle20($student_id, $_GET["cid"]);
						
						echo "<td class=\"text-align_center\">";
						if($isTitle20 === true && $disp == 1){
							$bT20checked = getTitle20SwipeForDate($student_id, $class_id, $_GET["cid"], $day);
							
							if($bT20checked === true){
								echo "<span class=\"fa fa-check\"></span>";
							} else {
								echo "<span class=\"fa fa-ban color_red\"></span>";							
							}
						} else {
							echo "&nbsp;";
						}
						echo "</td>";
						
					}
					echo "</tr>";

					$rowBit = ($rowBit == 1?0:1);

//				} else {
//					echo "<tr>";
//					echo "<td>".$fl_name["last_name"].", ".$fl_name["first_name"]."</td>";	
//					for($i = $startDate; $i <= $endDate ; $i += (3600*24)){
//						echo "<td colspan=\"4\" class=\"text-align_center\"><span class=\"fa fa-ban color_red\"></span> No Data For Range</td>";
//					}	
//					echo "</tr>";		
				}
			}	
			echo "</table>";
		
			if(strpos($class_name,"Infant") !== false){
				echo "<a href=\"output.usda.forms.php?wk=".$_GET["wk"]."&cid=".$_GET["cid"]."&clid=".$class_id."\" target=\"_blank\">Generate Infant USDA forms</a><br>";		
			} else {
				echo "<a href=\"output.usda.forms.php?wk=".$_GET["wk"]."&cid=".$_GET["cid"]."&clid=".$class_id."\" target=\"_blank\">Generate Pre-K USDA forms</a><br>";
			}
		}
			
		echo "<br>";			
	}
}






function outputAttendanceReports(){
	GLOBAL $aPwebCredentials;
	
	$sLocation = null;
	$cnt = 0;
	foreach($aPwebCredentials as $k => $v){
		if($cnt > 0){
			echo " - ";		
		}
		echo "<a href=\"emarc.php?cid=".$v["ICAB_ID"]."\">".$k."</a>";
		if($v["ICAB_ID"] == $_GET["cid"]){ $sLocation = $k; } 
		$cnt++;	
	}
	echo "<br><br>";
	echo "<h3>Attendance report for ".$sLocation."</h3>";
 
	$startDate = (!empty($_GET["wk"])?$_GET["wk"]:time());
	$endDate = (!empty($_GET["wk"])?$_GET["wk"] + ((3600*24)*4):time());

	if(date("N",$startDate) > 1){
		$startDate = $startDate - ((3600 * 24) * (date("N",$startDate) - 1));
	}
	if(empty($_GET["wk"])){ $_GET["wk"] = $startDate; }
	$calendarStartDate = $startDate;

	if(date("N",$endDate) < 5){
		$endDate = $endDate + ((3600 * 24) * (5 - date("N",$endDate)));
	} else if(date("N",$endDate) > 5){
		$endDate = $endDate - ((3600 * 24) * (date("N",$endDate) - 5));
	}
	
	//echo "Center ID: ".$_GET["cid"]."<br>";
	$classIDs = getCenterClassIds($_GET["cid"]);

	$aMinMaxWeeks = getAttendanceOldestNewest($_GET["cid"]);
	
	echo "<form action=\"".$_SERVER["PHP_SELF"]."\" method=\"get\">";
	echo "<label for=\"wk\">Generate report for the week of </label>";
	echo "<select name=\"wk\" id=\"wk\" style=\"width: auto;\">";
	for($i = $aMinMaxWeeks["first"];$i <= $aMinMaxWeeks["last"];$i += ((3600*24)*7) ){
		echo "<option value=\"".$i."\" ";
		if( date("Ymd",$startDate) == date("Ymd",$i) ){
			echo "selected=\"selected\" ";		
		}
		echo " >".date("M j",$i)." - ".date("M j, Y",$i + ((3600*24)*4))."</option>";	
	}	
	echo "</select>";
	echo "<input type=\"hidden\" name=\"cid\" value=\"".$_GET["cid"]."\">";
	echo "<input type=\"hidden\" name=\"ar\" value=\"".$_GET["ar"]."\">";
	echo "&nbsp;&nbsp;&nbsp;";
	echo "<input class=\"button\" type=\"submit\" value=\"Get Report\">";
	echo "</form>";
	
	echo "<br>";
	echo "<a href=\"output.attendance.reports.php?cid=".$_GET["cid"]."&wk=".$startDate."\" target=\"_blank\">Generate printable PDF version</a>";
	echo "<br>";
	echo "<br>";
	echo "<a href=\"emarc.php?cid=".$_GET["cid"]."&wk=".$startDate."&ared=1\">Edit Times</a>";
	echo "<br>";
	echo "<br>";
	
	foreach($classIDs as $class_id => $class_details){
		//echo "<a href=\"emarc.php?cid=".$_GET["cid"]."&ar=1&clid=".$class_id."\">".$class_details["class_name"]."</a><br>";
		
		$aStudents = getCenterClassStudentIds($_GET["cid"],$class_id);

		if(!empty($aStudents)){
		
			echo "<h3>".(is_array($class_details)?$class_details["class_name"]:$class_details)."</h3>";
			echo "<table class=\"usda\" style=\"min-width: 670px;\">";
			echo "<tr>";
			echo "<th rowspan=\"2\" style=\"vertical-align: bottom;\">Student Name</th>";
			echo "<th rowspan=\"2\" class=\"text-align_center\" style=\"vertical-align: bottom;\">Age</th>";
			echo "<th rowspan=\"2\" class=\"text-align_center\" style=\"vertical-align: bottom;\">Birthdate</th>";
	
			for($i = $startDate; $i <= $endDate; $i += (3600*24) ){
				echo "<th class=\"text-align_center\">".date("D. M jS",$i)."</th>";
			}
			echo "</tr>";

			echo "<tr>";
			for($i = $startDate; $i <= $endDate; $i += (3600*24) ){
				echo "<th class=\"text-align_center\"><table cellpadding=\"0\" cellspacing=\"0\" border=\"0\" class=\"noborder\"><tr class=\"noborder\"><td class=\"noborder text-align_center\" style=\"width:49%;\">IN</td><td class=\"noborder\" style=\"width:2%;\">-</td><td class=\"noborder text-align_center\" style=\"width:49%;\">OUT</td></tr></table></th>";
			}
			echo "</tr>";

			$rowBit = 1;
			foreach($aStudents as $student_id => $fl_name){
				
				$displayAttendance = 0;
				
				if(strtotime($fl_name["admit_date"]) <= $endDate){
					$displayAttendance = 1;
					
					if(strtotime($fl_name["admit_date"]) >= $startDate ){
						$displayAttendance = 1;
						//echo "<tr><td colspan=\"8\">Start Date condition : ".$displayAttendance." - ".$fl_name["last_name"].", ".$fl_name["first_name"]." - ".$student_id."</td></tr>";
						//echo "<tr><td colspan=\"8\">B-day: ".strtotime($fl_name["birthday"])." | start: ".$startDate." | end: ".$endDate."</td></tr>";
						//echo "<tr><td colspan=\"8\">B-day: ".$fl_name["birthday"]." | start: ".date("m/d/Y",$startDate)." | end: ".date("m/d/Y",$endDate)."</td></tr>";
						//echo "<tr><td colspan=\"8\"><hr></td></tr>";
					}
				}	

							
				$aAttendance = null;
				
				$aAttendance = getStudentAttendanceForRange($student_id,$_GET["cid"],$startDate,$endDate);
				
				$i = $startDate;
				
				if(!empty($aAttendance)){
	
					$colBit = 1;
					
					echo "<tr style=\"background:".($rowBit == 1?"#f0f0f0":"#ffffff").";\">";
					echo "<td>".$fl_name["last_name"].", ".$fl_name["first_name"]." - ".$student_id."</td>";
					//echo "<td>".$fl_name["last_name"].", ".$fl_name["first_name"]."</td>";
					$age = floor( floor( ( time() - strtotime(date("Ymd",strtotime($fl_name["birthday"]))) ) / (3600 * 24) ) / 30);
					echo "<td style=\"background:".($rowBit == 1?($colBit == 1?"#E8ECEF":"#f0f0f0"):($colBit == 1?"#F2F6F9":"#ffffff")).";\" class=\"text-align_right\">".( $age < 36?$age." mos":round(($age/12),1)." yrs" )."</td>";
					echo "<td class=\"text-align_right\">".$fl_name["birthday"]."</td>";
							
					for($i = $startDate; $i <= $endDate ; $i += (3600*24)){			
					
						$day = date("Y-m-d",$i);
		
						if(date("Ymd",$i) == date("Ymd",strtotime($aAttendance[date("Ymd",$i)]["in_datetime"])) ) {
							echo "<td style=\"background:".($rowBit == 1?($colBit == 1?"#E8ECEF":"#f0f0f0"):($colBit == 1?"#F2F6F9":"#ffffff")).";\" class=\"text-align_center\">";

							// DEBUG
							//echo "<pre>".var_export($aAttendance,true)."</pre><br>";							
							
							if(strpos($aAttendance[date("Ymd",$i)]["out_datetime"],"strong") != 1){

								echo "<table cellpadding=\"0\" cellspacing=\"0\" border=\"0\" class=\"noborder\" width=\"100%\">";
								echo "<tr class=\"noborder\">";
								echo "<td class=\"noborder text-align_center\" style=\"width:49%;\">".date("g:i a",strtotime($aAttendance[date("Ymd",$i)]["in_datetime"]))."</td>";
								echo "<td class=\"noborder\" style=\"width:2%;\">-</td>";
								echo "<td class=\"noborder text-align_center\" style=\"width:49%;\">".date("g:i a",strtotime($aAttendance[date("Ymd",$i)]["out_datetime"]))."</td>";
								echo "</td>";
								echo "</tr>";
								if(!empty($aAttendance[date("Ymd",$i)]["in_ISTAFF_ID"]) || !empty($aAttendance[date("Ymd",$i)]["out_ISTAFF_ID"]) ){
									echo "<tr class=\"noborder\">";
									echo "<td class=\"noborder text-align_center\">";
									if(is_numeric($aAttendance[date("Ymd",$i)]["in_ISTAFF_ID"])){
										echo getTeacherInitials($aAttendance[date("Ymd",$i)]["in_ISTAFF_ID"]);
									} else if(strstr($aAttendance[date("Ymd",$i)]["in_ISTAFF_ID"],"pweb")!== false){
										echo "pWeb";
									} else if (strstr($aAttendance[date("Ymd",$i)]["in_ISTAFF_ID"],"ez")!== false){
										echo "ezCare2";
									} else {
										echo "&nbsp;";
									}
									echo "</td>";
									echo "<td class=\"noborder\">-</td>";
									echo "<td class=\"noborder text-align_center\">";
									if(is_numeric($aAttendance[date("Ymd",$i)]["out_ISTAFF_ID"])){
										echo getTeacherInitials($aAttendance[date("Ymd",$i)]["out_ISTAFF_ID"]);
									} else if(strstr($aAttendance[date("Ymd",$i)]["out_ISTAFF_ID"],"pweb")!== false){
										echo "pWeb";
									} else if (strstr($aAttendance[date("Ymd",$i)]["out_ISTAFF_ID"],"ez")!== false){
										echo "ezCare2";
									} else {
										echo "&nbsp;";
									}
									echo "</td>";
									echo "</tr>";
								}
								
								if(!empty($aAttendance[date("Ymd",$i)]["in1_datetime"])){
									echo "<tr class=\"noborder\">";
									echo "<td class=\"noborder text-align_center\" style=\"width:49%;\">".date("g:i a",strtotime($aAttendance[date("Ymd",$i)]["in1_datetime"]))."</td>";
									echo "<td class=\"noborder\" style=\"width:2%;\">-</td>";
									echo "<td class=\"noborder text-align_center\" style=\"width:49%;\">".(!empty($aAttendance[date("Ymd",$i)]["out1_datetime"])?date("g:i a",strtotime($aAttendance[date("Ymd",$i)]["out1_datetime"])):" ");
									echo "</td>";
									//echo date("g:i a",strtotime($aAttendance[date("Ymd",$i)]["in_datetime"]))."</td><td>-</td><td>".date("g:i a",strtotime($aAttendance[date("Ymd",$i)]["out_datetime"]));
									echo "</tr>";
									if(!empty($aAttendance[date("Ymd",$i)]["in1_ISTAFF_ID"]) || !empty($aAttendance[date("Ymd",$i)]["out1_ISTAFF_ID"]) ){
										echo "<tr class=\"noborder\">";
										echo "<td class=\"noborder text-align_center\">";
										if(is_numeric($aAttendance[date("Ymd",$i)]["in1_ISTAFF_ID"])){
											echo getTeacherInitials($aAttendance[date("Ymd",$i)]["in1_ISTAFF_ID"]);
										} else if(strstr($aAttendance[date("Ymd",$i)]["in1_ISTAFF_ID"],"pweb")!== false){
											echo "pWeb";
										} else if (strstr($aAttendance[date("Ymd",$i)]["in1_ISTAFF_ID"],"ez")!== false){
											echo "ezCare2";
										} else {
											echo "&nbsp;";
										}
										echo "</td>";
										echo "<td class=\"noborder\">-</td>";
										echo "<td class=\"noborder text-align_center\">";
										if(is_numeric($aAttendance[date("Ymd",$i)]["out1_ISTAFF_ID"])){
											echo getTeacherInitials($aAttendance[date("Ymd",$i)]["out1_ISTAFF_ID"]);
										} else if(strstr($aAttendance[date("Ymd",$i)]["out1_ISTAFF_ID"],"pweb")!== false){
											echo "pWeb";
										} else if (strstr($aAttendance[date("Ymd",$i)]["out1_ISTAFF_ID"],"ez")!== false){
											echo "ezCare2";
										} else {
											echo "&nbsp;";
										}
										echo "</td>";
										echo "</tr>";
									}
								}
								
								if(!empty($aAttendance[date("Ymd",$i)]["in2_datetime"])){
									echo "<tr class=\"noborder\">";
									echo "<td class=\"noborder text-align_center\" style=\"width:49%;\">".date("g:i a",strtotime($aAttendance[date("Ymd",$i)]["in2_datetime"]))."</td>";
									echo "<td class=\"noborder\" style=\"width:2%;\">-</td>";
									echo "<td class=\"noborder text-align_center\" style=\"width:49%;\">".(!empty($aAttendance[date("Ymd",$i)]["out2_datetime"])?date("g:i a",strtotime($aAttendance[date("Ymd",$i)]["out2_datetime"])):" ");
									echo "</td>";
									//echo date("g:i a",strtotime($aAttendance[date("Ymd",$i)]["in_datetime"]))."</td><td>-</td><td>".date("g:i a",strtotime($aAttendance[date("Ymd",$i)]["out_datetime"]));
									echo "</tr>";
									if(!empty($aAttendance[date("Ymd",$i)]["in2_ISTAFF_ID"]) || !empty($aAttendance[date("Ymd",$i)]["out2_ISTAFF_ID"]) ){
										echo "<tr class=\"noborder\">";
										echo "<td class=\"noborder text-align_center\">".(is_numeric($aAttendance[date("Ymd",$i)]["in2_ISTAFF_ID"])?getTeacherInitials($aAttendance[date("Ymd",$i)]["in2_ISTAFF_ID"]):(strstr($aAttendance[date("Ymd",$i)]["in2_ISTAFF_ID"],"pweb")!== false?"pWeb":(strstr($aAttendance[date("Ymd",$i)]["in2_ISTAFF_ID"],"ez")!== false?"ezCare2":"&nbsp;")))."</td>";
										echo "<td class=\"noborder\">-</td>";
										echo "<td class=\"noborder text-align_center\">".(is_numeric($aAttendance[date("Ymd",$i)]["out2_ISTAFF_ID"])?getTeacherInitials($aAttendance[date("Ymd",$i)]["out2_ISTAFF_ID"]):(strstr($aAttendance[date("Ymd",$i)]["out2_ISTAFF_ID"],"pweb")!== false?"pWeb":(strstr($aAttendance[date("Ymd",$i)]["out2_ISTAFF_ID"],"ez")!== false?"ezCare2":"&nbsp;")))."</td>";
										echo "</tr>";
									}
								}
								
								echo "</table>";
									
								
							} else {
								echo "<table cellpadding=\"0\" cellspacing=\"0\" border=\"0\" class=\"noborder\">";
								echo "<tr class=\"noborder\">";
								echo "<td class=\"noborder text-align_center\" style=\"width:49%;\">".date("g:i a",strtotime($aAttendance[date("Ymd",$i)]["in_datetime"]))."</td>";
								echo "<td class=\"noborder\" style=\"width:2%;\">-</td>";
								echo "<td class=\"noborder text-align_center\" style=\"width:49%;\">";
										
								if(date("Ymd",$i) == date("Ymd")){
									echo "<strong>In Class Today</strong>";
									//$disp = 1;
								} else {
									echo "<span class=\"fa fa-lg fa-frown-o color_red\"></span> Bad Out Time";
									//$disp = 1;
								}
								
								echo "</td>";
								echo "</tr>";
								
								if(!empty($aAttendance[date("Ymd",$i)]["in_ISTAFF_ID"]) || !empty($aAttendance[date("Ymd",$i)]["out_ISTAFF_ID"]) ){
									echo "<tr class=\"noborder\">";
									echo "<td class=\"noborder text-align_center\">";

									if (is_numeric($aAttendance[date("Ymd",$i)]["in_ISTAFF_ID"])) {
										echo getTeacherInitials($aAttendance[date("Ymd",$i)]["out1_ISTAFF_ID"]);
									} else if (strstr($aAttendance[date("Ymd",$i)]["in_ISTAFF_ID"],"pweb")!== false){
										echo "pWeb";
									} else if (strstr($aAttendance[date("Ymd",$i)]["in_ISTAFF_ID"],"ez")!== false){
										echo "ezCare2";
									} else {
										echo "&nbsp;";
									} 
									echo "</td>";
									echo "<td class=\"noborder\">-</td>";
									echo "<td class=\"noborder text-align_center\">";
																			
									//if (is_numeric($aAttendance[date("Ymd",$i)]["out_ISTAFF_ID"])) {
									//	echo getTeacherInitials($aAttendance[date("Ymd",$i)]["out_ISTAFF_ID"]);
									//} else if (strstr($aAttendance[date("Ymd",$i)]["out_ISTAFF_ID"],"pweb")!== false){
									//	echo "pWeb";
									//} else if (strstr($aAttendance[date("Ymd",$i)]["out_ISTAFF_ID"],"ez")!== false){
									//	echo "ezCare2";
									//} else {
									//	echo "&nbsp;";
									//}
									echo "&nbsp;"; 
									echo "</td>";
									echo "</tr>";
								}

										
								echo "</table>";
								//echo ($aAttendance[date("Ymd",$i)]["source"] != "tablet"?"<span class=\"small\">".(strstr($aAttendance[date("Ymd",$i)]["source"],"pweb")!== false?"pWeb":"ezCare2")."</span>":null); 
							}

							echo "</td>";
						} else {
							echo "<td style=\"background:".($rowBit == 1?($colBit == 1?"#E8ECEF":"#f0f0f0"):($colBit == 1?"#F2F6F9":"#ffffff")).";\">&nbsp;</td>";
						}
						$colBit = ($colBit == 1?0:1);
//						$isTitle20 = getTitle20($student_id, $_GET["cid"]);
//						$bT20checked = getTitle20SwipeForDate($student_id, $class_id, $_GET["cid"], $day);
//						echo "<td class=\"text-align_center\">";
//						if($isTitle20 === true && $disp == 1){
//							if($bT20checked === true){
//								echo "<span class=\"fa fa-check\"></span>";
//							} else {
//								echo "<span class=\"fa fa-ban color_red\"></span>";							
//							}
//						} else {
//							echo "&nbsp;";
//						}
//						echo "</td>";
						
					}
					echo "</tr>";

					$rowBit = ($rowBit == 1?0:1);

//				} else {
//					echo "<tr>";
//					echo "<td>".$fl_name["last_name"].", ".$fl_name["first_name"]."</td>";	
//					for($i = $startDate; $i <= $endDate ; $i += (3600*24)){
//						echo "<td colspan=\"4\" class=\"text-align_center\"><span class=\"fa fa-ban color_red\"></span> No Data For Range</td>";
//					}	
//					echo "</tr>";		
				} else {
					
					if($displayAttendance == 1){
						echo "<tr style=\"background:".($rowBit == 1?"#f0f0f0":"#ffffff").";\">";
						echo "<td>".$fl_name["last_name"].", ".$fl_name["first_name"]." - ".$student_id."</td>";
						//echo "<td>".$fl_name["last_name"].", ".$fl_name["first_name"]."</td>";
						$age = floor( floor( ( time() - strtotime(date("Ymd",strtotime($fl_name["birthday"]))) ) / (3600 * 24) ) / 30);
						$colBit = ($colBit == 1?0:1);
						echo "<td style=\"background:".($rowBit == 1?($colBit == 1?"#E8ECEF":"#f0f0f0"):($colBit == 1?"#F2F6F9":"#ffffff")).";\" class=\"text-align_right\">".( $age < 36?$age." mos":round(($age/12),1)." yrs" )."</td>";
						echo "<td class=\"text-align_right\">".$fl_name["birthday"]."</td>";	
						echo "<td style=\"background:".($rowBit == 1?($colBit == 1?"#E8ECEF":"#f0f0f0"):($colBit == 1?"#F2F6F9":"#ffffff")).";\" class=\"text-align_center\">&nbsp;</td>";
						$colBit = ($colBit == 1?0:1);			
						echo "<td style=\"background:".($rowBit == 1?($colBit == 1?"#E8ECEF":"#f0f0f0"):($colBit == 1?"#F2F6F9":"#ffffff")).";\" class=\"text-align_center\">&nbsp;</td>";
						$colBit = ($colBit == 1?0:1);			
						echo "<td style=\"background:".($rowBit == 1?($colBit == 1?"#E8ECEF":"#f0f0f0"):($colBit == 1?"#F2F6F9":"#ffffff")).";\" class=\"text-align_center\">&nbsp;</td>";
						$colBit = ($colBit == 1?0:1);			
						echo "<td style=\"background:".($rowBit == 1?($colBit == 1?"#E8ECEF":"#f0f0f0"):($colBit == 1?"#F2F6F9":"#ffffff")).";\" class=\"text-align_center\">&nbsp;</td>";
						$colBit = ($colBit == 1?0:1);			
						echo "<td style=\"background:".($rowBit == 1?($colBit == 1?"#E8ECEF":"#f0f0f0"):($colBit == 1?"#F2F6F9":"#ffffff")).";\" class=\"text-align_center\">&nbsp;</td>";
						$colBit = ($colBit == 1?0:1);
						echo "</tr>";	
						$rowBit = ($rowBit == 1?0:1);		
					}
				}
			}	
			echo "</table>";
			echo "<br>";
		}
	}

}



function editAttendanceRecords(){
	GLOBAL $dbconn;
	GLOBAL $aPwebCredentials;
	
	if(!empty($_POST)){
		
		foreach($_POST as $key => $val){

			$aTmp = split("-",$key);
			$recordDate = $aTmp[0];
			$studentId = $aTmp[1];
			$fieldName = $aTmp[2]."-".$aTmp[3];

			//echo "date: ".date("Y-m-d",$recordDate)."<br>";
			//echo "student id: ".$studentId."<br>";
			//echo "field name: ".$fieldName."<br>";
			//echo "value: ".$val."<br>";
			//echo "<hr>";

			if(!empty($val)){
				$aTmpVals[$studentId][$recordDate][$fieldName] = $val;
			}
		}
		
		foreach($aTmpVals as $student_id => $aData){
			
			foreach($aData as $date => $vals){

				$sqlColsVals = null;
				
//				$sqlInsertCommon = "`datestamp`, `ICHILD_ID`, `ICAB_ID`, `ICLASS_ID`, `source` ";
//				$sqlInsertCols = null;
//				$sqlInsertCommonVals = "NOW(), '".$student_id."', '".$vals["I-CAB_ID"]."', '".$vals["I-CLASS_ID"]."', 'tablet' ";
//				$sqlInsertVals = null;
//				$sqlUpdateCommon = "`datestamp` = NOW(), `ICHILD_ID` = '".$student_id."', `ICAB_ID` = '".$vals["I-CAB_ID"]."', `ICLASS_ID` = '".$vals["I-CLASS_ID"]."', `source` = 'tablet' ";
				$sqlUpdate = null;
				
				$sqlWhereCommon = "`ICHILD_ID` = '".$student_id."' AND `ICAB_ID` = '".$vals["I-CAB_ID"]."' AND `ICLASS_ID` = '".$vals["I-CLASS_ID"]."' AND `source` = 'tablet' ";
				$sqlWhere = null;
				
				if (!empty($vals["IN-hour"])){
					//echo "IN: ICHILD_ID: ".$student_id." - ICAB_ID: ".$vals["I-CAB_ID"]." - ICLASS_ID: ".$vals["I-CLASS_ID"]." source - tablet - in_status - IN - in_datetime: ".date("Y-m-d",$date)." ".$vals["IN-hour"].":".$vals["IN-minute"]." ".$vals["IN-ampm"]." - in_ISTAFF_ID: ".$vals["IN-teacherID"]."<br>";

					$sqlColsVals["in_status"] = "IN";

					$vals["IN-hour"] = $vals["IN-hour"]+($vals["IN-hour"] != 12 && strpos($vals["IN-ampm"],"am")===false?12:0);
					$sqlColsVals["in_datetime"] = date("Y-m-d",$date);
					$sqlColsVals["in_datetime"] .= " ".$vals["IN-hour"].":".$vals["IN-minute"].":".(!empty($vals["IN-second"])?$vals["IN-second"]:"00");
					
					$sqlColsVals["in_ISTAFF_ID"] = $vals["IN-teacherID"];
					
					$sqlWhere  .= " AND `in_status` = 'IN' AND YEAR(`in_datetime`) = '".date("Y",$date)."' AND MONTH(`in_datetime`) = '".date("m",$date)."' AND DAY(`in_datetime`) = '".date("d",$date)."' AND `in_ISTAFF_ID` = '".$vals["IN-teacherID"]."' ";
					
//					$sql .= "UPDATE `student-attendance` SET `datestamp`=NOW(), `ICHILD_ID`='".$student_id."', `ICAB_ID`='".$vals["I-CAB_ID"]."', `ICLASS_ID`='".$vals["I-CLASS_ID"]."', `source`='tablet', `in_status`='IN', `in_datetime`='".date("Y-m-d",$date)." ".date("G:i",strtotime($vals["IN-hour"].":".$vals["IN-minute"]." ".$vals["IN-ampm"]))."', `in_ISTAFF_ID`='".$vals["IN-teacherID"]."' WHERE `id` = '".."';";
				}			
				if (!empty($vals["OUT-hour"])){
					//echo "OUT: ICHILD_ID: ".$student_id." - ICAB_ID: ".$vals["I-CAB_ID"]." - ICLASS_ID: ".$vals["I-CLASS_ID"]." source - tablet - out_status - OUT - out_datetime: ".date("Y-m-d",$date)." ".$vals["OUT-hour"].":".$vals["OUT-minute"]." ".$vals["OUT-ampm"]." - out_ISTAFF_ID: ".$vals["OUT-teacherID"]."<br>";

					$sqlColsVals["out_status"] = "OUT";

					$vals["OUT-hour"] = $vals["OUT-hour"]+($vals["OUT-hour"] != 12 && strpos($vals["OUT-ampm"],"am")===false?12:0);
					$sqlColsVals["out_datetime"] = date("Y-m-d",$date);
					$sqlColsVals["out_datetime"] .= " ".$vals["OUT-hour"].":".$vals["OUT-minute"].":".(!empty($vals["OUT-second"])?$vals["OUT-second"]:"00");
					
					$sqlColsVals["out_ISTAFF_ID"] = $vals["OUT-teacherID"];			
					
//					$sqlWhere  .= " AND `out_status` = 'OUT' AND YEAR(`out_datetime`) = '".date("Y",$date)."' AND MONTH(`out_datetime`) = '".date("m",$date)."' AND DAY(`out_datetime`) = '".date("d",$date)."' AND `out_ISTAFF_ID` = '".$vals["OUT-teacherID"]."' ";
					
//					$sql .= "UPDATE `student-attendance` SET `datestamp`=NOW(), `ICHILD_ID`='".$student_id."', `ICAB_ID`='".$vals["I-CAB_ID"]."', `ICLASS_ID`='".$vals["I-CLASS_ID"]."', `source`='tablet', `out_status`='OUT', `out_datetime`='".date("Y-m-d",$date)." ".date("G:i",strtotime($vals["OUT-hour"].":".$vals["OUT-minute"]." ".$vals["OUT-ampm"]))."', `out_ISTAFF_ID`='".$vals["OUT-teacherID"]."' WHERE `id` = '".."';";
				}			
				if (!empty($vals["IN1-hour"])){
					//echo "IN1: ICHILD_ID: ".$student_id." - ICAB_ID: ".$vals["I-CAB_ID"]." - ICLASS_ID: ".$vals["I-CLASS_ID"]." source - tablet - in1_status - IN - in1_datetime: ".date("Y-m-d",$date)." ".$vals["IN1-hour"].":".$vals["IN1-minute"]." ".$vals["IN1-ampm"]." - in1_ISTAFF_ID: ".$vals["IN1-teacherID"]."<br>";

					$sqlColsVals["in1_status"] = "IN";

					$vals["IN1-hour"] = $vals["IN1-hour"]+($vals["IN1-hour"] != 12 && strpos($vals["IN1-ampm"],"am")===false?12:0);
					$sqlColsVals["in1_datetime"] = date("Y-m-d",$date);
					$sqlColsVals["in1_datetime"] .= " ".$vals["IN1-hour"].":".$vals["IN1-minute"].":".(!empty($vals["IN1-second"])?$vals["IN1-second"]:"00");				
					
					$sqlColsVals["in1_ISTAFF_ID"] = $vals["IN1-teacherID"];				
					
//					$sqlWhere  .= " AND `in1_status` = 'IN' AND YEAR(`in1_datetime`) = '".date("Y",$date)."' AND MONTH(`in1_datetime`) = '".date("m",$date)."' AND DAY(`in1_datetime`) = '".date("d",$date)."' AND `in1_ISTAFF_ID` = '".$vals["IN-teacherID"]."' ";
					
//					$sql .= "UPDATE `student-attendance` SET `datestamp`=NOW(), `ICHILD_ID`='".$student_id."', `ICAB_ID`='".$vals["I-CAB_ID"]."', `ICLASS_ID`='".$vals["I-CLASS_ID"]."', `source`='tablet', `in1_status`='IN', `in1_datetime`='".date("Y-m-d",$date)." ".date("G:i",strtotime($vals["IN1-hour"].":".$vals["IN1-minute"]." ".$vals["IN1-ampm"]))."', `in1_ISTAFF_ID`='".$vals["IN1-teacherID"]."' WHERE `id` = '".."';";
				}			
				if (!empty($vals["OUT1-hour"])){
					//echo "OUT1: ICHILD_ID: ".$student_id." - ICAB_ID: ".$vals["I-CAB_ID"]." - ICLASS_ID: ".$vals["I-CLASS_ID"]." source - tablet - out1_status - OUT - out1_datetime: ".date("Y-m-d",$date)." ".$vals["OUT1-hour"].":".$vals["OUT1-minute"]." ".$vals["OUT1-ampm"]." - out1_ISTAFF_ID: ".$vals["OUT1-teacherID"]."<br>";
					
					$sqlColsVals["out1_status"] = "OUT";

					$vals["OUT1-hour"] = $vals["OUT1-hour"]+($vals["OUT1-hour"] != 12 && strpos($vals["OUT1-ampm"],"am")===false?12:0);
					$sqlColsVals["out1_datetime"] = date("Y-m-d",$date);
					$sqlColsVals["out1_datetime"] .= " ".$vals["OUT1-hour"].":".$vals["OUT1-minute"].":".(!empty($vals["OUT1-second"])?$vals["OUT1-second"]:"00");					

					$sqlColsVals["out1_ISTAFF_ID"] = $vals["OUT1-teacherID"];
					
//					$sqlWhere  .= " AND `out1_status` = 'OUT' AND YEAR(`out1_datetime`) = '".date("Y",$date)."' AND MONTH(`out1_datetime`) = '".date("m",$date)."' AND DAY(`out1_datetime`) = '".date("d",$date)."' AND `out1_ISTAFF_ID` = '".$vals["OUT-teacherID"]."' ";					
					
//					$sql .= "UPDATE `student-attendance` SET `datestamp`=NOW(), `ICHILD_ID`='".$student_id."', `ICAB_ID`='".$vals["I-CAB_ID"]."', `ICLASS_ID`='".$vals["I-CLASS_ID"]."', `source`='tablet', `out1_status`='OUT', `out1_datetime`='".date("Y-m-d",$date)." ".date("G:i",strtotime($vals["OUT1-hour"].":".$vals["OUT1-minute"]." ".$vals["OUT1-ampm"]))."', `out1_ISTAFF_ID`='".$vals["OUT1-teacherID"]."' WHERE `id` = '".."';";
				}			
				
				if(!empty($sqlWhere)){
					$sql = "SELECT * FROM `student-attendance` WHERE ".$sqlWhereCommon.$sqlWhere.";";
					
					$result = mysql_query($sql,$dbconn);
					if(!empty($result) && mysql_num_rows($result) > 0){
						
						// we've found a matching student-attendance record, so let's update it.				
						$row = mysql_fetch_assoc($result);
						
						$runQuery = 0;
						
						if(!empty($sqlColsVals)){
							$sqlColsVals["ICHILD_ID"] = $student_id;
							$sqlColsVals["ICAB_ID"] = $vals["I-CAB_ID"];
							$sqlColsVals["ICLASS_ID"] = $vals["I-CLASS_ID"];
							$sqlColsVals["source"] = 'tablet';

							$mySql  = "UPDATE `student-attendance` SET `datestamp` = NOW() ";
							foreach($sqlColsVals as $col => $val){
								if($row[$col] != $val){
		
									// DEBUG
									//echo "<hr>";
									//echo $col."<br>";
									//echo $row[$col]."<br>";
									//echo $val."<br>";
									//echo "<br>";
									//echo $sqlWhereCommon.$sqlWhere;
									//echo "<br>";
									//echo "<hr>";
																
									
									$mySql .= ", `".$col."` = '".$val."' ";	
									$runQuery = 1;					
								}
							}
							$mySql .= " WHERE `id` = ".$row["id"].";";
		
							if($runQuery == 1){
								//echo $mySql."<br>";
								mysql_query($mySql,$dbconn);
							}
						}
						unset($aTmpVals[$student_id][$date]);
						
					} else {
						// otherwise, we need to insert a new record.
						if(array_key_exists("in_datetime",$sqlColsVals)){

							//echo "<pre>".var_export($sqlColsVals,true)."</pre><br>";

							if(!empty($sqlColsVals)){
								$sqlColsVals["ICHILD_ID"] = $student_id;
								$sqlColsVals["ICAB_ID"] = $vals["I-CAB_ID"];
								$sqlColsVals["ICLASS_ID"] = $vals["I-CLASS_ID"];
								$sqlColsVals["source"] = 'tablet';
	
								$mySql = "INSERT INTO `student-attendance` ( `datestamp` ";
								foreach($sqlColsVals as $col => $val){
									$mySql .= ", `".$col."` ";
								}
								$mySql .= ") VALUES (";
								$mySql .= "NOW() ";
								foreach($sqlColsVals as $col => $val){
									$mySql .= ", '".$val."' ";
								}
								$mySql .= ");";
			
								//echo $mySql."<br>";
								mysql_query($mySql,$dbconn);
							}
						}
						unset($aTmpVals[$student_id][$date]);
					}
				}
				$sqlWhere = null;
				
			}
			unset($aTmpVals[$student_id]);
		}

		outputAttendanceReports();
		//editAttendanceRecords();
		
		
	} else {
	
		$sLocation = null;
		$cnt = 0;
		foreach($aPwebCredentials as $k => $v){
			if($cnt > 0){
				echo " - ";		
			}
			echo "<a href=\"emarc.php?cid=".$v["ICAB_ID"]."\">".$k."</a>\n";
			if($v["ICAB_ID"] == $_GET["cid"]){ $sLocation = $k; } 
			$cnt++;	
		}
		echo "<br><br>\n";
		echo "<h3>Edit attendance records for ".$sLocation."</h3>\n";
	 
		$startDate = (!empty($_GET["wk"])?$_GET["wk"]:time());
		$endDate = (!empty($_GET["wk"])?$_GET["wk"] + ((3600*24)*4):time());
	
		if(date("N",$startDate) > 1){
			$startDate = $startDate - ((3600 * 24) * (date("N",$startDate) - 1));
		}
		if(empty($_GET["wk"])){ $_GET["wk"] = $startDate; }
		$calendarStartDate = $startDate;
	
		if(date("N",$endDate) < 5){
			$endDate = $endDate + ((3600 * 24) * (5 - date("N",$endDate)));
		} else if(date("N",$endDate) > 5){
			$endDate = $endDate - ((3600 * 24) * (date("N",$endDate) - 5));
		}
		
		//echo "Center ID: ".$_GET["cid"]."<br>";
		$classIDs = getCenterClassIds($_GET["cid"]);
		$aTeachers = getTeachersForCenter($_GET["cid"]);
	
	
		$aMinMaxWeeks = getAttendanceOldestNewest($_GET["cid"]);
		
		echo "<form action=\"".$_SERVER["PHP_SELF"]."\" method=\"get\">\n";
		echo "<label for=\"wk\">Edit records for the week of </label>\n";
		echo "<select name=\"wk\" id=\"wk\" style=\"width: auto;\">\n";
		for($i = $aMinMaxWeeks["first"];$i <= $aMinMaxWeeks["last"];$i += ((3600*24)*7) ){
			echo "<option value=\"".$i."\" ";
			if( date("Ymd",$startDate) == date("Ymd",$i) ){
				echo "selected=\"selected\" ";		
			}
			echo " >".date("M j",$i)." - ".date("M j, Y",$i + ((3600*24)*4))."</option>\n";	
		}	
		echo "</select>\n";
		echo "<input type=\"hidden\" name=\"cid\" value=\"".$_GET["cid"]."\">\n";
		echo "<input type=\"hidden\" name=\"ared\" value=\"".$_GET["ared"]."\">\n";
		echo "&nbsp;&nbsp;&nbsp;";
		echo "<input class=\"button\" type=\"submit\" value=\"Get Report\">\n";
		echo "</form>\n";
		
		echo "<br>\n";
		echo "<br>\n";
		
		echo "<form action=\"emarc.php?cid=".$_GET["cid"]."&wk=".$_GET["wk"]."&ared=1\" method=\"post\">\n";
		
		foreach($classIDs as $class_id => $class_details){
			
			$aStudents = getCenterClassStudentIds($_GET["cid"],$class_id);
	
			if(!empty($aStudents)){
			
				echo "<input type=\"submit\">\n";
				echo "<br>\n";
				echo "<br>\n";
				echo "<h3>".(is_array($class_details)?$class_details["class_name"]:$class_details)."</h3>\n";
				echo "<table class=\"edits\" style=\"width: 100%;\">\n";
				echo "<tr>\n";
				echo "<th rowspan=\"2\" style=\"vertical-align: bottom;\">Student Name</th>\n";
				echo "<th rowspan=\"2\" class=\"text-align_center\" style=\"vertical-align: bottom;\">Age</th>\n";
				echo "<th rowspan=\"2\" class=\"text-align_center\" style=\"vertical-align: bottom;\">Birthdate</th>\n";
		
				for($i = $startDate; $i <= $endDate; $i += (3600*24) ){
					echo "<th class=\"text-align_center\">".date("D. M jS",$i)."</th>\n";
				}
				echo "</tr>\n";
	
				echo "<tr>\n";
				for($i = $startDate; $i <= $endDate; $i += (3600*24) ){
					echo "<th class=\"text-align_center\">\n\t<table cellpadding=\"0\" cellspacing=\"0\" border=\"0\" class=\"noborder\">\n\t<tr class=\"noborder\">\n\t<td class=\"noborder text-align_center\" style=\"width:49%;\">IN</td>\n\t<td class=\"noborder\" style=\"width:2%;\">-</td>\n\t<td class=\"noborder text-align_center\" style=\"width:49%;\">OUT</td>\n\t</tr>\n\t</table>\n</th>\n";
				}
				echo "</tr>\n";
	
				$rowBit = 1;
				foreach($aStudents as $student_id => $fl_name){
					
					$aAttendance = getStudentAttendanceForRange($student_id,$_GET["cid"],$startDate,$endDate);
					//$i = $startDate;
		
					$colBit = 1;
					
					$seconds = 0;
					for($j = $startDate; $j <= $endDate ; $j += (3600*24)){	
						$aTmp = getPwebEzcareForDate($student_id, $_GET["cid"], $j);
						if(!empty($aTmp)){
							foreach($aTmp as $k => $v){
								if(strstr($v["source"],"pweb")!== false){
									if(!empty($v["out_datetime"])){
										$seconds += strtotime($v["out_datetime"])-strtotime($v["in_datetime"]);
										//$H = floor($seconds / 3600);
										//$i = ($seconds / 60) % 60;
										//$s = $seconds % 60;
									}
								}									
							}
						}
						$aTmp = null;
					}
										
					//$i = $startDate;
					
					echo "<tr style=\"background:".($rowBit == 1?"#f0f0f0":"#ffffff").";\">\n";
					//echo "<td>".$fl_name["last_name"].", ".$fl_name["first_name"]." - ".$student_id;
					echo "<td>".$fl_name["last_name"].", ".$fl_name["first_name"];
					if($seconds > 0){
						$H = floor($seconds / 3600);
						$i = ($seconds / 60) % 60;
						$s = $seconds % 60;
						
						echo "<br>";
						echo "<br>";
						if($H < 25){ echo "<span style=\"color: #ff0000;\">";}
						echo "pWeb: ".$H.":".$i;
						if($H < 25){ echo "</span>";}
						echo "<br>";					
					}
					echo "</td>\n";
					$age = floor( floor( ( time() - strtotime(date("Ymd",strtotime($fl_name["birthday"]))) ) / (3600 * 24) ) / 30);
					echo "<td style=\"background:".($rowBit == 1?($colBit == 1?"#E8ECEF":"#f0f0f0"):($colBit == 1?"#F2F6F9":"#ffffff")).";\" class=\"text-align_right\">".( $age < 36?$age." mos":round(($age/12),1)." yrs" )."</td>\n";
					echo "<td class=\"text-align_right\">".$fl_name["birthday"]."</td>\n";
							
					for($i = $startDate; $i <= $endDate ; $i += (3600*24)){			
					
						$day = date("Y-m-d",$i);
		
						//if(date("Ymd",$i) == date("Ymd",strtotime($aAttendance[date("Ymd",$i)]["in_datetime"])) ) {
							echo "<td style=\"background:".($rowBit == 1?($colBit == 1?"#E8ECEF":"#f0f0f0"):($colBit == 1?"#F2F6F9":"#ffffff")).";\" class=\"text-align_center\">\n";
							
							echo "<input type=\"hidden\" name=\"".$i."-".$student_id."-I-CLASS_ID\" value=\"".$class_id."\">\n";
							echo "<input type=\"hidden\" name=\"".$i."-".$student_id."-I-CAB_ID\" value=\"".$_GET["cid"]."\">\n";

							echo "<table cellpadding=\"0\" cellspacing=\"0\" border=\"0\" class=\"noborder\" width=\"100%\">\n";
							echo "<tr class=\"noborder\">\n";
							echo "<td class=\"noborder text-align_center\" style=\"width:49%;\">\n";
								echo "<input type=\"text\" name=\"".$i."-".$student_id."-IN-hour\" value=\"".(!empty($aAttendance[date("Ymd",$i)]["in_datetime"]) && is_numeric($aAttendance[date("Ymd",$i)]["in_ISTAFF_ID"])?date("g",strtotime($aAttendance[date("Ymd",$i)]["in_datetime"])):null)."\" style=\"width: 2.5em;\">";
								echo " : ";
								echo "<input type=\"text\" name=\"".$i."-".$student_id."-IN-minute\" value=\"".(!empty($aAttendance[date("Ymd",$i)]["in_datetime"]) && is_numeric($aAttendance[date("Ymd",$i)]["in_ISTAFF_ID"])?date("i",strtotime($aAttendance[date("Ymd",$i)]["in_datetime"])):null)."\" style=\"width: 2.5em;\">\n";
								echo "<input type=\"hidden\" name=\"".$i."-".$student_id."-IN-second\" value=\"".(!empty($aAttendance[date("Ymd",$i)]["in_datetime"]) && is_numeric($aAttendance[date("Ymd",$i)]["in_ISTAFF_ID"])?date("s",strtotime($aAttendance[date("Ymd",$i)]["in_datetime"])):null)."\">\n";
								echo "<select name=\"".$i."-".$student_id."-IN-ampm\" style=\"width: auto;\">\n";
								echo "<option></option>\n";
								echo "<option value=\"am\" ";
								if(!empty($aAttendance[date("Ymd",$i)]["in_datetime"]) && date("a",strtotime($aAttendance[date("Ymd",$i)]["in_datetime"])) == "am"){ echo " selected=\"selected\" ";}
								echo ">AM</option>\n";									
								echo "<option value=\"pm\" ";
								if(!empty($aAttendance[date("Ymd",$i)]["in_datetime"]) && date("a",strtotime($aAttendance[date("Ymd",$i)]["in_datetime"])) == "pm"){ echo " selected=\"selected\" ";}
								echo ">PM</option>\n";
								echo "</select>\n";

								if(strstr($aAttendance[date("Ymd",$i)]["in_ISTAFF_ID"],"pweb")!== false){
									echo "<br>".date("g:i a",strtotime($aAttendance[date("Ymd",$i)]["in_datetime"]))." pWeb";
								} else if (strstr($aAttendance[date("Ymd",$i)]["in_ISTAFF_ID"],"ez")!== false){
									echo "<br>".date("g:i a",strtotime($aAttendance[date("Ymd",$i)]["in_datetime"]))." ezCare2";
								} else {
									$aTmp = getPwebEzcareForDate($student_id, $_GET["cid"], $i);
									if(!empty($aTmp)){
										foreach($aTmp as $k => $v){
											echo "<br>".date("g:i a",strtotime($v["in_datetime"]))." ";
											if(strstr($v["source"],"pweb")!== false){
												echo "pWeb";
											} else if (strstr($v["source"],"ez")!== false){
												echo "ezCare2";
											}									
										}
									}							
								}							
								
								
							echo "</td>\n";
							echo "<td class=\"noborder\" style=\"width:2%;\">-</td>\n";
							echo "<td class=\"noborder text-align_center\" style=\"width:49%;\">\n";
								echo "<input type=\"text\" name=\"".$i."-".$student_id."-OUT-hour\" value=\"".(!empty($aAttendance[date("Ymd",$i)]["out_datetime"]) && is_numeric($aAttendance[date("Ymd",$i)]["out_ISTAFF_ID"])?date("g",strtotime($aAttendance[date("Ymd",$i)]["out_datetime"])):null)."\" style=\"width: 2.5em;\">";
								echo " : ";
								echo "<input type=\"text\" name=\"".$i."-".$student_id."-OUT-minute\" value=\"".(!empty($aAttendance[date("Ymd",$i)]["out_datetime"]) && is_numeric($aAttendance[date("Ymd",$i)]["out_ISTAFF_ID"])?date("i",strtotime($aAttendance[date("Ymd",$i)]["out_datetime"])):null)."\" style=\"width: 2.5em;\">\n";
								echo "<input type=\"hidden\" name=\"".$i."-".$student_id."-OUT-second\" value=\"".(!empty($aAttendance[date("Ymd",$i)]["out_datetime"]) && is_numeric($aAttendance[date("Ymd",$i)]["out_ISTAFF_ID"])?date("s",strtotime($aAttendance[date("Ymd",$i)]["out_datetime"])):null)."\">\n";
								echo "<select name=\"".$i."-".$student_id."-OUT-ampm\" style=\"width: auto;\">\n";
								echo "<option></option>";
								echo "<option value=\"am\" ";
								if(!empty($aAttendance[date("Ymd",$i)]["out_datetime"]) && date("a",strtotime($aAttendance[date("Ymd",$i)]["out_datetime"])) == "am"){ echo " selected=\"selected\" ";}
								echo ">AM</option>";									
								echo "<option value=\"pm\" ";
								if(!empty($aAttendance[date("Ymd",$i)]["out_datetime"]) && date("a",strtotime($aAttendance[date("Ymd",$i)]["out_datetime"])) == "pm"){ echo " selected=\"selected\" ";}
								echo ">PM</option>";
								echo "</select>";
								
								if(strstr($aAttendance[date("Ymd",$i)]["out_ISTAFF_ID"],"pweb")!== false){
									echo "<br>".date("g:i a",strtotime($aAttendance[date("Ymd",$i)]["out_datetime"]))." pWeb";
								} else if (strstr($aAttendance[date("Ymd",$i)]["out_ISTAFF_ID"],"ez")!== false){
									echo "<br>".date("g:i a",strtotime($aAttendance[date("Ymd",$i)]["out_datetime"]))." ezCare2";
								} else {
									if(!empty($aTmp)){
										foreach($aTmp as $k => $v){
											echo "<br>".date("g:i a",strtotime($v["out_datetime"]))." ";
											if(strstr($v["source"],"pweb")!== false){
												echo "pWeb";
											} else if (strstr($v["source"],"ez")!== false){
												echo "ezCare2";
											}									
										}
									}
									unset($aTmp);							
								}
							echo "</td>\n";
							echo "</tr>\n";
							
							echo "<tr class=\"noborder\">\n";
							echo "<td class=\"noborder text-align_center\">\n";
							
								echo "<select name=\"".$i."-".$student_id."-IN-teacherID\" style=\"width: auto;\">";
								echo "<option></option>";
								foreach($aTeachers as $tid => $tname){
									echo "<option value=\"".$tid."\" ";
									if(substr($tname["firstname"],0,1).".".substr($tname["lastname"],0,1)."." == getTeacherInitials($aAttendance[date("Ymd",$i)]["in_ISTAFF_ID"])){ echo " selected=\"selected\" ";}
									echo ">".substr($tname["firstname"],0,1).".".substr($tname["lastname"],0,1)."."."</option>";
								}
								echo "</select>";		
							
							echo "</td>\n";
							echo "<td class=\"noborder\">-</td>\n";
							echo "<td class=\"noborder text-align_center\">\n";
							
								echo "<select name=\"".$i."-".$student_id."-OUT-teacherID\" style=\"width: auto;\">";
								echo "<option></option>";
								foreach($aTeachers as $tid => $tname){
									echo "<option value=\"".$tid."\" ";
									if(substr($tname["firstname"],0,1).".".substr($tname["lastname"],0,1)."." == getTeacherInitials($aAttendance[date("Ymd",$i)]["out_ISTAFF_ID"])){ echo " selected=\"selected\" ";}
									echo ">".substr($tname["firstname"],0,1).".".substr($tname["lastname"],0,1)."."."</option>";
								}
								echo "</select>";
							
							echo "</td>";
							echo "</tr>";
							
							echo "<tr class=\"noborder\">";
							echo "<td class=\"noborder text-align_center\" style=\"width:49%;\">";
								echo "<input type=\"text\" name=\"".$i."-".$student_id."-IN1-hour\" value=\"".(!empty($aAttendance[date("Ymd",$i)]["in1_datetime"]) && is_numeric($aAttendance[date("Ymd",$i)]["in1_ISTAFF_ID"])?date("g",strtotime($aAttendance[date("Ymd",$i)]["in1_datetime"])):null)."\" style=\"width: 2.5em;\">";
								echo " : ";
								echo "<input type=\"text\" name=\"".$i."-".$student_id."-IN1-minute\" value=\"".(!empty($aAttendance[date("Ymd",$i)]["in1_datetime"]) && is_numeric($aAttendance[date("Ymd",$i)]["in1_ISTAFF_ID"])?date("i",strtotime($aAttendance[date("Ymd",$i)]["in1_datetime"])):null)."\" style=\"width: 2.5em;\">";
								echo "<input type=\"hidden\" name=\"".$i."-".$student_id."-IN1-second\" value=\"".(!empty($aAttendance[date("Ymd",$i)]["in1_datetime"]) && is_numeric($aAttendance[date("Ymd",$i)]["in1_ISTAFF_ID"])?date("s",strtotime($aAttendance[date("Ymd",$i)]["in1_datetime"])):null)."\">\n";
								echo "<select name=\"".$i."-".$student_id."-IN1-ampm\" style=\"width: auto;\">";
								echo "<option></option>";
								echo "<option value=\"am\" ";
								if(!empty($aAttendance[date("Ymd",$i)]["in1_datetime"]) && date("a",strtotime($aAttendance[date("Ymd",$i)]["in1_datetime"])) == "am"){ echo " selected=\"selected\" ";}
								echo ">AM</option>";									
								echo "<option value=\"pm\" ";
								if(!empty($aAttendance[date("Ymd",$i)]["in1_datetime"]) && date("a",strtotime($aAttendance[date("Ymd",$i)]["in1_datetime"])) == "pm"){ echo " selected=\"selected\" ";}
								echo ">PM</option>";
								echo "</select>";										
							echo "</td>";
							echo "<td class=\"noborder\" style=\"width:2%;\">-</td>";
							echo "<td class=\"noborder text-align_center\" style=\"width:49%;\">";
								echo "<input type=\"text\" name=\"".$i."-".$student_id."-OUT1-hour\" value=\"".(!empty($aAttendance[date("Ymd",$i)]["out1_datetime"]) && is_numeric($aAttendance[date("Ymd",$i)]["out1_ISTAFF_ID"])?date("g",strtotime($aAttendance[date("Ymd",$i)]["out1_datetime"])):null)."\" style=\"width: 2.5em;\">";
								echo " : ";
								echo "<input type=\"text\" name=\"".$i."-".$student_id."-OUT1-minute\" value=\"".(!empty($aAttendance[date("Ymd",$i)]["out1_datetime"]) && is_numeric($aAttendance[date("Ymd",$i)]["out1_ISTAFF_ID"])?date("i",strtotime($aAttendance[date("Ymd",$i)]["out1_datetime"])):null)."\" style=\"width: 2.5em;\">";
								echo "<input type=\"hidden\" name=\"".$i."-".$student_id."-OUT1-second\" value=\"".(!empty($aAttendance[date("Ymd",$i)]["out1_datetime"]) && is_numeric($aAttendance[date("Ymd",$i)]["out1_ISTAFF_ID"])?date("s",strtotime($aAttendance[date("Ymd",$i)]["out1_datetime"])):null)."\">\n";
								echo "<select name=\"".$i."-".$student_id."-OUT1-ampm\" style=\"width: auto;\">";
								echo "<option></option>";
								echo "<option value=\"am\" ";
								if(!empty($aAttendance[date("Ymd",$i)]["out1_datetime"]) && date("a",strtotime($aAttendance[date("Ymd",$i)]["out1_datetime"])) == "am"){ echo " selected=\"selected\" ";}
								echo ">AM</option>";									
								echo "<option value=\"pm\" ";
								if(!empty($aAttendance[date("Ymd",$i)]["out1_datetime"]) && date("a",strtotime($aAttendance[date("Ymd",$i)]["out1_datetime"])) == "pm"){ echo " selected=\"selected\" ";}
								echo ">PM</option>";
								echo "</select>";
							echo "</td>";
							echo "</tr>";
							
							echo "<tr class=\"noborder\">";
							echo "<td class=\"noborder text-align_center\">";
							
							if(strstr($aAttendance[date("Ymd",$i)]["in1_ISTAFF_ID"],"pweb")!== false){
								echo "pWeb";
							} else if (strstr($aAttendance[date("Ymd",$i)]["in1_ISTAFF_ID"],"ez")!== false){
								echo "ezCare2";
							} else {
								echo "<select name=\"".$i."-".$student_id."-IN1-teacherID\" style=\"width: auto;\">";
								echo "<option></option>";
								echo "<option></option>";
								foreach($aTeachers as $tid => $tname){
									echo "<option value=\"".$tid."\" ";
									if(substr($tname["firstname"],0,1).".".substr($tname["lastname"],0,1)."." == getTeacherInitials($aAttendance[date("Ymd",$i)]["in1_ISTAFF_ID"])){ echo " selected=\"selected\" ";}
									echo ">".substr($tname["firstname"],0,1).".".substr($tname["lastname"],0,1)."."."</option>";
								}
								echo "</select>";
							}
							echo "</td>";
							echo "<td class=\"noborder\">-</td>";
							echo "<td class=\"noborder text-align_center\">";
							if(strstr($aAttendance[date("Ymd",$i)]["out1_ISTAFF_ID"],"pweb")!== false){
								echo "pWeb";
							} else if (strstr($aAttendance[date("Ymd",$i)]["out1_ISTAFF_ID"],"ez")!== false){
								echo "ezCare2";
							} else {
								echo "<select name=\"".$i."-".$student_id."-OUT1-teacherID\" style=\"width: auto;\">";
								echo "<option></option>";
								echo "<option></option>";
								foreach($aTeachers as $tid => $tname){
									echo "<option value=\"".$tid."\" ";
									if(substr($tname["firstname"],0,1).".".substr($tname["lastname"],0,1)."." == getTeacherInitials($aAttendance[date("Ymd",$i)]["out1_ISTAFF_ID"])){ echo " selected=\"selected\" ";}
									echo ">".substr($tname["firstname"],0,1).".".substr($tname["lastname"],0,1)."."."</option>";
								}
								echo "</select>";
							}
							echo "</td>";
							echo "</tr>";
								
							
							if(!empty($aAttendance[date("Ymd",$i)]["in2_datetime"])){
								echo "<tr class=\"noborder\">";
								echo "<td class=\"noborder text-align_center\" style=\"width:49%;\">".date("g:i a",strtotime($aAttendance[date("Ymd",$i)]["in2_datetime"]))."</td>";
								echo "<td class=\"noborder\" style=\"width:2%;\">-</td>";
								echo "<td class=\"noborder text-align_center\" style=\"width:49%;\">".(!empty($aAttendance[date("Ymd",$i)]["out2_datetime"])?date("g:i a",strtotime($aAttendance[date("Ymd",$i)]["out2_datetime"])):" ");
								echo "</td>";
								//echo date("g:i a",strtotime($aAttendance[date("Ymd",$i)]["in_datetime"]))."</td><td>-</td><td>".date("g:i a",strtotime($aAttendance[date("Ymd",$i)]["out_datetime"]));
								echo "</tr>";
								if(!empty($aAttendance[date("Ymd",$i)]["in2_ISTAFF_ID"]) || !empty($aAttendance[date("Ymd",$i)]["out2_ISTAFF_ID"]) ){
									echo "<tr class=\"noborder\">";
									echo "<td class=\"noborder text-align_center\">".(is_numeric($aAttendance[date("Ymd",$i)]["in2_ISTAFF_ID"])?getTeacherInitials($aAttendance[date("Ymd",$i)]["in2_ISTAFF_ID"]):(strstr($aAttendance[date("Ymd",$i)]["in2_ISTAFF_ID"],"pweb")!== false?"pWeb":(strstr($aAttendance[date("Ymd",$i)]["in2_ISTAFF_ID"],"ez")!== false?"ezCare2":"&nbsp;")))."</td>";
									echo "<td class=\"noborder\">-</td>";
									echo "<td class=\"noborder text-align_center\">".(is_numeric($aAttendance[date("Ymd",$i)]["out2_ISTAFF_ID"])?getTeacherInitials($aAttendance[date("Ymd",$i)]["out2_ISTAFF_ID"]):(strstr($aAttendance[date("Ymd",$i)]["out2_ISTAFF_ID"],"pweb")!== false?"pWeb":(strstr($aAttendance[date("Ymd",$i)]["out2_ISTAFF_ID"],"ez")!== false?"ezCare2":"&nbsp;")))."</td>";
									echo "</tr>";
								}
							}
							
							echo "</table>";

							echo "</td>";

						$colBit = ($colBit == 1?0:1);
						
					}
					echo "</tr>";

					$rowBit = ($rowBit == 1?0:1);
			
				}	
				echo "</table>";
				echo "<br>";
			}
		}
		echo "<input type=\"submit\">";
		echo "</form>";
	}
}






?>
