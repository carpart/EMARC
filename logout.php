<?php
session_start();

header("Expires: Sat, 26 Jul 1997 05:00:00 GMT");

if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

foreach($_SESSION as $k){
	unset($_SESSION[$k]);
}

// Finally, destroy the session.
session_destroy();

if(!empty($_GET["page"])){
	header("Location: index.php?page=".$_GET["page"]);
} else {
	header("Location: index.php");
}

?>