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
        $info = getShareInfo($db, $token, $parameters);

        /* EXISTENCE/AUTHORISATION */
        if($parameters["pID"] && !$info["share"]) {
            http_response_code(404) and exit();
        } elseif(!$token || ($parameters["pID"] && $info["user"] != $info["share"]["from_ID"] && $info["user"] != $info["share"]["to_ID"]) || $info["user"] == null) {
            http_response_code(401) and exit();
        }

        $list = Array();
        $query = $db->query("SELECT * from geoshare.shares WHERE (from_ID=" . $info["user"] . " OR to_ID=" . $info["user"] . ")" . ($parameters["pID"]? " AND pID='" . $parameters["pID"] . "'" : ""));
        while(($row = $query->fetch_assoc())) {
            $row["ID"] = $row["pID"];
            $row["from"] = getUsername($db, $row["from_ID"]);
            $row["to"] = getUsername($db, $row["to_ID"]);
            unset($row["pID"], $row["from_ID"], $row["to_ID"]);
            array_push($list, $row);
        }
        echo json_encode($parameters["pID"]? $list[0] : $list);

        break;

    case "POST":
        /* CHECK AUTHORISATION */
        $info = getShareInfo($db, $token, $parameters);
        if(!$token || $info["user"] == null) {
            http_response_code(401) and exit();
        }

        /* CATCH INVALID/UNSET DATA */
        if(!isset($data["recipient"], $data["type"], $data["long"], $data["lat"]) || $data["lat"] < 0 || $data["lat"] > 180 || $data["long"] > 90 || $data["long"] < -90 || ($data["type"] != "peer"/* && $data != "group"*/)) {
            http_response_code(400) and exit();
        } elseif(!getUserID($db, $data["recipient"])) {
            http_response_code(404) and exit();
        }

        $statement = $db->prepare("INSERT INTO geoshare.shares(from_ID, to_ID, type, `long`, lat) VALUES(?, ?, ?, ?, ?)");
        $statement->bind_param("iisdd", $info["user"], getUserID($db, $data["recipient"]), $data["type"], $data["long"], $data["lat"]);
        if($statement->execute()) {
            $result = @$db->query("SELECT pID from geoshare.shares WHERE ID=LAST_INSERT_ID()")->fetch_assoc()["pID"];
             header("Location: https://geoshare.appsbystudio.co.uk/api/share/" . $result);
            http_response_code(201);
        } else {
            //echo $db->error;
            http_response_code(500);
        }

        break;

    /*
    case "PATCH":

        break;
    */

    case "DELETE":
        /* CHECK AUTHORISATION */
        $info = getShareInfo($db, $token, $parameters);
        if(!$token || $info["user"] == null || $info["user"] != $info["share"]["from_ID"]) {
            http_response_code(401) and exit();
        }
        
        http_response_code($db->query("DELETE FROM geoshare.shares WHERE ID='" . $info["share"]["ID"] . "'")? 200 : 500);

        break;

    default:
        http_response_code(405);
        break;
}
?>