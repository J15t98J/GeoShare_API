<?php

include "../../common.php";

/* SET RESPONSE HEADERS */
header("Access-Control-Allow-Orgin: *");
header("Access-Control-Allow-Methods: POST");
header("Content-Type: application/json");

/* EXTRACT REQUEST DETAILS */
$requestArray = getRequestDetails();
$data = $requestArray["data"];
$parameters = $requestArray["parameters"];
$token = $requestArray["token"];

if(!isset($parameters["username"])) $parameters["username"] = @$_POST["username"]; //  TODO: remove?
if(!$token) $token = @$_POST["REST_API_TOKEN"];                                    //  TODO: remove?

/* CONNECT TO DB */
$db = connectToDB();

/* PROCESS REQUEST */
switch($_SERVER['REQUEST_METHOD']) {
    case "GET":
        /* 403 ANY REQUESTS FOR A LIST OF IMAGES */
        if(!isset($parameters["username"])) {
            http_response_code(403) and exit();
        }

        $info = getUserInfo($db, $token, $parameters);
        $names = glob("C:\\xampp\\ppic\\" . $info["pic_uri"]  . ".[a-zA-Z0-9][a-zA-Z0-9]*", GLOB_BRACE);
        if(count($names) > 1) http_response_code(500) and exit();
        $name = array_pop($names);
        $filetype = array_pop(explode(".", $name));

        $fp = @fopen($name, "rb");
        if($fp) {
            if(!isset(getallheaders()["If-Modified-Since"]) || strtotime(getallheaders()["If-Modified-Since"]) < filemtime($name)) {
                http_response_code(200);
                header("Content-Type: image/" . $filetype);
                header("Content-Length: " . filesize($name));
                fpassthru($fp);
            } else http_response_code(304);
        } else http_response_code(404);

        break;

    case "POST":
        /* CATCH INVALID DATA */
        if(!isset($_FILES, $_FILES['image'])) {
            http_response_code(400) and exit();
        }

        $filetype = array_pop(explode(".", $_FILES['image']['name']));
        
        $info = getUserInfo($db, $token, $parameters);
        if(!$token || !$info["user"]) {
            http_response_code(401) and exit();
        } elseif($info["user"] != $info["owner"]) {
            http_response_code(403) and exit();
        } elseif($filetype != "png" && $filetype != "jpg" && $filetype != "jpeg" && $filetype != "webp") {
            http_response_code(415) and exit();
        }

        $names = glob("C:\\xampp\\ppic\\" . $info["pic_uri"]  . ".[a-zA-Z0-9][a-zA-Z0-9]*", GLOB_BRACE);
        foreach($names as $name) {
            unlink($name);
        }
        http_response_code(move_uploaded_file($_FILES['image']['tmp_name'], "C:\\xampp\\ppic\\" . $info["pic_uri"] . "." . strtolower($filetype))? 204 : 500);
        
        break;

    default:
        http_response_code(405);
        break;
}