<?php

include "../../common.php";

/* SET RESPONSE HEADERS */
header("Access-Control-Allow-Orgin: *");
header("Access-Control-Allow-Methods: GET, POST, PATCH, DELETE");
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
        } elseif($info["user"] != $info["owner"]) {
            http_response_code(403) and exit();
        }

        /* EXECUTE SQL */
        $friends = array();
        switch(@$parameters["status"]) {
            case "request":
                $result = $db->query("SELECT user.username, request.sent FROM geoshare.friendrequests request INNER JOIN geoshare.users user on request.from_ID = user.ID WHERE to_ID=" . $info["owner"]);
                break;
            case "pending":
                $result = $db->query("SELECT user.username, request.sent FROM geoshare.friendrequests request INNER JOIN geoshare.users user on request.to_ID = user.ID WHERE from_ID=" . $info["owner"]);
                break;
            case null:
                $result = $db->query("SELECT user.username, user.email, friends.created as friendship_start FROM (SELECT ID_B as ID, created FROM geoshare.friendships WHERE ID_A=" . $info["owner"] . " UNION SELECT ID_A as ID, created FROM geoshare.friendships WHERE ID_B=" . $info["owner"] . ") friends INNER JOIN geoshare.users user on friends.ID = user.ID");
                break;
            default:
                http_response_code(400) and exit();
        }

        /* RETURN USER ARRAY AS JSON OBJECT */
        while($row = $result->fetch_assoc()) {
            array_push($friends, $row);
        }
        echo json_encode($friends);
        http_response_code(200);
        
        break;

    case "POST":
        /* CHECK AUTHORISATION */
        $info = getUserInfo($db, $token, $parameters);
        if(!$info["owner"]) {
            http_response_code(404) and exit();
        } elseif(!$token || !$info["user"]) {
            http_response_code(401) and exit();
        } elseif($info["user"] == $info["owner"]) {
            http_response_code(409) and exit();
        }

        if(@$parameters["status"] == "request") {
            /* ARE THEY ALREADY FRIENDS / IS THERE AN EXISTING FRIEND REQUEST? */
            if(areFriends($db, $info["user"], $info["owner"]) || hasRequestedFriendship($db, $info["user"], $info["owner"]) || hasRequestedFriendship($db, $info["owner"], $info["user"])) {
                http_response_code(409);
                exit();
            } else {
                $statement = $db->prepare("INSERT INTO geoshare.friendrequests(from_ID, to_ID) VALUES(?, ?)");
                $statement->bind_param("ii", $info["user"], $info["owner"]);
                if($statement->execute()){
                    /* SUCCESS! INFORM THE CLIENT */
                    http_response_code(201);
                } else http_response_code(500);
            }
        } else http_response_code(403);

        break;

    case "PATCH":
        /* CHECK AUTHORISATION */
        $info = getUserInfo($db, $token, $parameters);
        if(!$token || !$info["user"]) {
            http_response_code(401) and exit();
        } elseif($info["user"] != $info["owner"]) {
            http_response_code(403) and exit();
        }

        if(@$parameters["status"] == "request") {
            if(isset($data["action"], $parameters["patchtarget"])) {
                if($data["action"] == "accept") {
                    $statement = $db->prepare("INSERT INTO geoshare.friendships(ID_A, ID_B) VALUES(?, ?)");
                    $statement->bind_param("ii", $info["owner"], getUserID($db, $parameters["patchtarget"]));
                    if($statement->execute()) {
                        /* SUCCESS! INFORM THE CLIENT */
                        http_response_code(200);
                    } else http_response_code(500);
                } elseif($data["action"] != "ignore") {
                    http_response_code(400) and exit();
                }
                $db->query("DELETE FROM geoshare.friendrequests WHERE from_ID=" . getUserID($db, $parameters["patchtarget"]) . " AND to_ID=" . $info["owner"]);
            } else http_response_code(400);
        } else http_response_code(403);
        
        break;

    case "DELETE":
        /* CHECK AUTHORISATION */
        $info = getUserInfo($db, $token, $parameters);
        if(!$token || !$info["user"]) {
            http_response_code(401) and exit();
        }

        if(@$parameters["status"] == "request") {
            if($info["user"] == $info["owner"]) {
                http_response_code(409) and exit();
            }
            $db->query("DELETE FROM geoshare.friendrequests WHERE from_ID=" . $info["user"] . " AND to_ID=" . $info["owner"]);
            http_response_code($db->affected_rows > 0? 200 : 404);
        } elseif($info["user"] == $info["owner"]) {
            $count = 0;
            $db->query("DELETE FROM geoshare.friendships WHERE ID_A=" . getUserID($db, $parameters["deletetarget"]) . " AND ID_B=" . $info["owner"]);
            $count += $db->affected_rows;
            $db->query("DELETE FROM geoshare.friendships WHERE ID_B=" . getUserID($db, $parameters["deletetarget"]) . " AND ID_A=" . $info["owner"]);
            $count += $db->affected_rows;
            http_response_code($count > 0? 200 : 404);
        } else http_response_code(403);
        
        break;

    default:
        http_response_code(405);
        break;
}
?>