<?php

include "../../common.php";

/* SET RESPONSE HEADERS */
header("Access-Control-Allow-Orgin: *");
header("Access-Control-Allow-Methods: GET");
header("Content-Type: application/json");

/* EXTRACT REQUEST DETAILS */
$requestArray = getRequestDetails();
$data = $requestArray["data"];
$parameters = $requestArray["parameters"];
$token = $requestArray["token"];

/* CONNECT TO DB */
$db = connectToDB();

/* PROCESS REQUEST */
switch($_SERVER['REQUEST_METHOD']) {
    case "GET":
        /* 403 ANY REQUESTS FOR A USER LIST */
        if(!isset($parameters["username"])) {
            http_response_code(403) and exit();
        }

        /* CHECK AUTHORISATION */
        $info = getUserInfo($db, $token, $parameters);
        if(!$token || !$info["user"]) {
            http_response_code(401) and exit();
        }

        $result = Array();
        $return = $db->query("SELECT username from geoshare.users WHERE username LIKE '" . $parameters["username"] . "%' LIMIT 100")->fetch_all(MYSQLI_ASSOC);
        foreach($return as $item) {
            array_push($result, $item["username"]);
        }
        echo json_encode($result);

        break;

    default:
        http_response_code(405);
}
?>