<?php

include "../common.php";

/* SET RESPONSE HEADERS */
header("Access-Control-Allow-Orgin: *");
header("Access-Control-Allow-Methods: GET, POST, DELETE");
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
        /* CHECK AUTHORISATION */
        $info = getUserInfo($db, $token, $parameters);
        if(!$token || !$info["user"]) {
            http_response_code(401) and exit();
        }

        /* EXECUTE SQL */
        $result = $db->query("SELECT pID AS token, IP, created, expires from geoshare.sessions WHERE user=" . $info["user"] . (isset($parameters["id"])? " AND pID='" . $parameters["id"] . "'" : ""))->fetch_all(MYSQLI_ASSOC);
        if($result) {
            /* FOUND SESSION(S) */
            echo json_encode($result);
            http_response_code(200);
        } else {
            /* NO RESULTS */
            http_response_code(404);
        }
        
        break;

    case "POST":
        /* CATCH INVALID DATA */
        if(isset($parameters["id"]) || !isset($parameters["username"]) || !isset($data["password"])) {
            http_response_code(400) and exit();
        }

        /* CHECK AUTHORISATION */
        $result = $db->query("SELECT ID, pass_hash from geoshare.users WHERE username='" . $parameters["username"] . "'")->fetch_assoc();
        if(!$result || !password_verify($data["password"], $result["pass_hash"])) {
            http_response_code(401) and exit();
        }

        /* EXECUTE SQL */
        $statement = $db->prepare("INSERT INTO geoshare.sessions(user, IP, expires) VALUES(?, ?, ?)");
        $time = (new DateTime())->add(new DateInterval(isset($data["expiry"])? $data["expiry"] : "PT12H"))->format("Y-m-d H:i:s");
        $statement->bind_param("iss", $result["ID"], $_SERVER["REMOTE_ADDR"], $time);
        if($statement->execute()) {
            /* SUCCESS! SEND CLIENT SESSION TOKEN */
            $response = $db->query("SELECT pID FROM geoshare.sessions WHERE ID='" . $db->insert_id . "'")->fetch_assoc();
            $response["username"] = $parameters["username"];
            echo json_encode($response);
            http_response_code(201);
        } else http_response_code(500);
        
        break;

    case "DELETE";
        /* CATCH INVALID DATA */
        if(!isset($parameters["id"]) || !isset($parameters["username"])) {
            http_response_code(400) and exit();
        }
        
        /* CHECK AUTHORISATION */
        $info = getUserInfo($db, $token, $parameters);
        if(!$token || $info["user"] != $info["owner"]) {
            http_response_code(401) and exit();
        }

        /* DELETE IF EXISTS; 404 OTHERWISE */
        if(!$db->query("SELECT pID FROM geoshare.sessions WHERE user=" . $info["user"] . " AND pID='" . $parameters["id"] . "'")->fetch_assoc()) http_response_code(404);
        else http_response_code($db->query("DELETE FROM geoshare.sessions WHERE pID='" . $parameters["id"] . "'") ? 200 : 500);
        
        break;

    default:
        http_response_code(405);
        break;
}