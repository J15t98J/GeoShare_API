<?php
date_default_timezone_set("Europe/London");
//require_once 'C:\xampp\php\extlibs\google-api-php-client-2.0.0\vendor\autoload.php';
require_once 'google-api-php-client-2.0.0/vendor/autoload.php';

if(!function_exists('getallheaders')) { 
    function getallheaders() { 
       $headers = ''; 
       foreach ($_SERVER as $name => $value) { 
           if (substr($name, 0, 5) == 'HTTP_') { 
               $headers[str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))))] = $value; 
           } 
       } 
       return $headers; 
    } 
}



/* GOOGLE SERVICES */
function verifyGoogleToken($token) {
    $client = new Google_Client();
    $client->setApplicationName("GeoShare");
    $client->setDeveloperKey("AIzaSyCVjg-Egy1zcgtdYVcxOHbtGqMgPxQbzPU");
    return $client->verifyIdToken($token);
}

/* USER FUNCTIONS */
function getUserInfo($db, $token, $parameters) {
    $session = @$db->query("SELECT user, expires FROM geoshare.sessions WHERE pID='" . $token . "'")->fetch_assoc();
    if(new DateTime($session["expires"]) > new DateTime("now")) $user = $session["user"]; else $user = null;
    $owner = @$db->query("SELECT ID, pic_uri, findByEmail from geoshare.users WHERE username='" . $parameters["username"] . "'")->fetch_assoc();
    return array("user" => $user, "owner" => $owner["ID"], "pic_uri" => $owner["pic_uri"], "findOwnerByEmail" => $owner["findByEmail"]);
}

function getShareInfo($db, $token, $parameters) {
    $session = @$db->query("SELECT user, expires FROM geoshare.sessions WHERE pID='" . $token . "'")->fetch_assoc();
    if(new DateTime($session["expires"]) > new DateTime("now")) $user = $session["user"]; else $user = null;
    $share = @$db->query("SELECT * FROM geoshare.shares WHERE pID='" . $parameters["pID"] . "'")->fetch_assoc();
    return array("user" => $user, "share" => $share);
}

function getUserID($db, $username) {
    $result = $db->query("SELECT ID FROM geoshare.users WHERE username='" . $username . "'");
    return $result->num_rows > 0? $result->fetch_assoc()["ID"] : false;
}

function getUsername($db, $id) {
    $result = $db->query("SELECT username FROM geoshare.users WHERE ID=" . $id);
    return $result->num_rows > 0? $result->fetch_assoc()["username"] : false;
}

function areFriends($db, $aID, $bID) {
    $result = $db->query("SELECT * FROM geoshare.friendships WHERE (ID_A=" . $aID . " and ID_B=" . $bID . ") OR (ID_A=" . $bID . " and ID_B=" . $aID . ")");
    return $result->num_rows > 0;
}

function hasRequestedFriendship($db, $fromID, $toID) {
    $result = $db->query("SELECT * FROM geoshare.friendrequests WHERE from_ID=" . $fromID . " AND to_ID =" . $toID);
    return $result->num_rows > 0;
}

/* UTILITY FUNCTIONS */
function splitQuery($str) {
    $result = array();
    foreach(explode("&", $str) as $item) {
        if($item != "") {
            $param = explode("=", $item);
            if($param[0] != "" && $param[1] != "") {
                $result[urldecode($param[0])] = urldecode($param[1]);
            }
        }
    }
    return $result;
}

function getRequestDetails() {
    switch(isset(getallheaders()["Content-Type"])? getallheaders()["Content-Type"] : "") {
        default:
        case "application/json":
            $data = json_decode(file_get_contents('php://input'), true);
            break;
        case "application/x-www-form-urlencoded":
            $data = splitQuery(file_get_contents('php://input'));
            break;
        case "multipart/form-data":
            if($_SERVER['REQUEST_METHOD'] == "POST" || $_SERVER["SCRIPT_NAME"] == "/api/user/img/index.php") {
                $data = null;
            } else http_response_code(400) and exit();
            break;
    }

    $parameters = splitQuery($_SERVER['QUERY_STRING']);
    $token = isset(getallheaders()["Rest-Api-Token"])? getallheaders()["Rest-Api-Token"] : false;
    return array("data" => $data, "parameters" => $parameters, "token" => $token);
}

function connectToDB() {
    $db = new mysqli("admin.appsbystudio.co.uk", "geoshare", "GeoShare.Apps2016", "geoshare", "3306");
    if($db->connect_error) http_response_code(503) and exit();
    return $db;
}

function writeToLog() {
    $logfile = fopen("api_log.txt", "a"); // TODO: change back so log isn't publicly visible
    fwrite($logfile, "[" . date("d/m/Y H:i:s T") . "] " . $_SERVER['REQUEST_METHOD'] . " " . $_SERVER["SCRIPT_NAME"] . "?" . $_SERVER["QUERY_STRING"] . "; responded " . http_response_code() . "\r\n");
    fclose($logfile);
}

register_shutdown_function('writeToLog');

if($_SERVER['REQUEST_METHOD'] == "POST" and getallheaders()["X-HTTP-Method-Override"] == "PATCH") {
    $_SERVER['REQUEST_METHOD'] = "PATCH";
}

// TODO: groups?
?>