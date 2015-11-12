<?php
session_start();

require($_SERVER["DOCUMENT_ROOT"]."/emarc/includes/dbconn.php");
require($_SERVER["DOCUMENT_ROOT"]."/emarc/includes/common.php");

// if we somehow ended back up at the index page, redirect to main admin landing page
if(!empty($_SESSION) && !empty($_SESSION["uid"]) && checkLoginToken($_SESSION["token"])){
	header("Location: ./emarc.php");
	exit;
}

$title = "EMARC";

if(empty($_POST)){
	$content = getLoginForm();
} else {
	$content = NULL;

	// if we've got user input, let's process it!
	if(!empty($_POST["uname"]) && !empty($_POST["pword"])){
		
		// look for the 'iv' (initialization vector) value to use when MD5 hashing the password
		$sql = "SELECT `user`.`iv` FROM `user` WHERE `user`.`username` = '".mysql_real_escape_string($_POST["uname"])."';";

		$result = mysql_query($sql);
		$row = mysql_fetch_assoc($result);

		// if we don't have a matching username, don't even bother trying to find a matching password
		if(!empty($row)){	
			// hash the user submitted password against the stored 'iv' and see if we get a match.
			$sql = "SELECT `user`.`id` as `uid`, `user`.`permissions_levelFK`,`user-permissions`.`level`, `user`.`username`, `user`.`first_name`, `user`.`last_name`, `user`.`email`, `user`.`last_login`, `user`.`iv` FROM `user`, `user-permissions` WHERE `user`.`permissions_levelFK` = `user-permissions`.`level` AND `user`.`username` = '".mysql_real_escape_string($_POST["uname"])."' AND `user`.`password` = '".mysql_real_escape_string(md5($_POST["pword"].$row["iv"]))."';";
			$result = mysql_query($sql);

			// if we've got a match, set some session values, and store this login timestamp
			if($row = mysql_fetch_assoc($result)){
				$iv = null;
				foreach($row as $k => $v){
					if($k != "iv"){
						$_SESSION[$k] = $v;
					} else {
						$iv = $v;					
					}
				}
				
				$login_token = md5($iv.generateRandomString(100));
				$_SESSION["token"] = $login_token;

				$sql = "UPDATE `user` SET `login_token` = '".mysql_real_escape_string($login_token)."', `last_login` = NOW() WHERE `id` = '".$row["uid"]."' AND `username` = '".$row["username"]."';";
				mysql_query($sql);

				// if the user was forced to login, return them to the page they were trying to access, otherwise send them to the default site management page
				if(array_key_exists("page",$_POST) && !empty($_POST["page"])){
					header("Location: ".base64_decode($_POST["page"]));
				} else {
					header("Location: ./emarc.php");
				}
		
			} else {
				// send a common error message, but this time, the password failed
				$content .= getLoginForm("<p>The username or password you entered is incorrect.<br/>Please check your input and try again.</p>");
			}
		} else {
			// send a common error message, but this time, the username failed
			$content .= getLoginForm("<p>The username or password you entered is incorrect.<br/>Please check your input and try again.</p>");
		}
	} else {
		$content = getLoginForm();	
	}
}

/* =============================== */
/* BEGIN: Page content and display */
/* =============================== */

displayOpenHTML($title);

echo "<div id=\"main_wrapper\">\n";
echo "<h1>EMARC<span>Electronic Meal and Attendance Record Calculator</span></h1>\n";
echo "<br>\n";
echo $content;
echo "</div>\n";

//displayCloseHTML();

/* =============================== */
/* END: Page content and display */
/* =============================== */

function getLoginForm($message = null){
	GLOBAL $baseURL;

	$sRet = NULL;

	$sRet .= "<form id=\"login\" action=\"".$_SERVER["PHP_SELF"]."\" method=\"post\">";
	$sRet .= "<fieldset>";
	$sRet .= "<legend>Login</legend>";
	if(!empty($message)){
		$sRet .= "<p>".$message."</p>";	
	}
	$sRet .= "<label for=\"uname\">Username: </label>";
	$sRet .= "<input type=\"text\" id=\"uname\" name=\"uname\" value=\"\"/><br/>";
	$sRet .= "<label for=\"pword\">Password: </label>";
	$sRet .= "<input type=\"password\" id=\"pword\" name=\"pword\" value=\"\"/><br/>";
	$sRet .= "<p class=\"align_right\"><input type=\"submit\" value=\"Login\"></p>";
	if(array_key_exists("page",$_GET) && !empty($_GET["page"])){
		$sRet .= "<input type=\"hidden\" name=\"page\" value=\"".$_GET["page"]."\"/>";
	}
	$sRet .= "<br class=\"clear\">";
	$sRet .= "</fieldset>";
	$sRet .= "</form>";

	return $sRet;
}

?>
