<?php

$dbconn = mysql_connect("localhost", "nikou_webuser", "w3bus3rp4ss");
$dbname = "nikou_emarc";

if (!$dbconn) {
    echo "Unable to connect to DB: " . mysql_error();
    exit;
}

if (!mysql_select_db($dbname)) {
    echo "Unable to select mydbname: " . mysql_error();
    exit;
}

?>