<?php

include "../common.php";

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
        /* 403 ANY REQUESTS FOR A USER LIST */
        if(!isset($parameters["username"]) && !isset($parameters["email"])) {
            http_response_code(403) and exit();
        }

        /* FIND USER, ENCRYPT THE ID */
        $result = $db->query("SELECT * FROM geoshare.users WHERE"
            . (isset($parameters["username"])? " username='" . $parameters["username"] . "'" : "")
            . (isset($parameters["username"], $parameters["email"])? " AND" : "")
            . (isset($parameters["email"])? " email='" . $parameters["email"] . "'" : ""));
        if($result->num_rows > 0) {
            /* USER FOUND */
            $return = $result->fetch_assoc();
            if(!isset($parameters["username"]) && $return["findByEmail"] == "0") {
                http_response_code(404) and exit();
            }
            $parameters["username"] = $return["username"];

            /* CHECK AUTHORISATION */
            $info = getUserInfo($db, $token, $parameters);
            if(!$token || !$info["user"]) {
                http_response_code(401) and exit();
            }

            /* REMOVE FIELDS THE USER SHOULDN'T SEE */
            if($info["user"] == $info["owner"]) {
                unset($return["ID"], $return["pass_hash"], $return["pic_uri"]);
            } else {
                $return = array_intersect_key($return, array_flip($info["user"] != $info["owner"] && !areFriends($db, $info["owner"], $info["user"]) && !$info["findOwnerByEmail"] ? array("username") : array("username", "email")));
            }

            /* RETURN USER ARRAY AS JSON OBJECT */
            echo json_encode($return);
            http_response_code(200);
        } else {
            /* USER DOES NOT EXIST */
            http_response_code(404);
        }
        
        break;

    case "POST":
        /* CATCH INVALID DATA - CHECKS FOR UNSET/OOB VALUES, FAKED EMAILS OR A SUPPLIED ID */
        if(!isset($data["username"], $data["email"], $data["password"]) || isset($parameters["username"])) {
            http_response_code(400) and exit();
        } elseif(strlen($data["username"]) > 25) {
            echo json_encode(array("error" => "invalid", "invalid" => "username"));
            http_response_code(400) and exit();
        } elseif(strlen($data["email"]) > 45 || preg_match("/^.+?@.+?\..+?$/", $data["email"]) === 0) {
            echo json_encode(array("error" => "invalid", "invalid" => "email"));
            http_response_code(400) and exit();
        } elseif(preg_match("/^(\S*(?=\S{8,})(?=\S*[a-z])(?=\S*[A-Z])(?=\S*[\d])\S*)|.{16,}$/", $data["password"]) === 0) {
            echo json_encode(array("error" => "invalid", "invalid" => "password"));
            http_response_code(400) and exit();
        }

        /* EXECUTE SQL */
        $statement = $db->prepare("INSERT INTO geoshare.users(username, email, pass_hash) VALUES(?, ?, ?)");
        $statement->bind_param("sss", $data["username"], $data["email"], password_hash($data["password"], PASSWORD_BCRYPT));
        if($statement->execute()) {
            /* SUCCESS! SEND CLIENT A LINK TO THE CREATED USER */
            header("Location: https://geoshare.appsbystudio.co.uk/api/user/" . $data["username"]);
            http_response_code(201);
        } else {
            /* OOPS! ERROR */
            if(preg_match("/Duplicate entry '(.*?)' for key '(.*?)'/", $db->error, $match) === 1) {
                /* DUPLICATE USER */
                unset($data["password"]);
                echo json_encode(array("error" => "duplicate", "duplicate" => explode("_", $match[2])[0], "request" => $data));
                http_response_code(409);
            } else http_response_code(500);
        }
        
        break;

    case "PATCH":
        /* CATCH INVALID DATA */
        if(!isset($parameters["username"]) || !$data || isset($data["email"]) && (strlen($data["email"]) > 45 || preg_match("/^.+?@.+?\..+?$/", $data["email"]) === 0) || isset($data["password"]) && preg_match("/^(\S*(?=\S{8,})(?=\S*[a-z])(?=\S*[A-Z])(?=\S*[\d])\S*)|.{16,}$/", $data["password"]) === 0) {
            http_response_code(400) and exit();
        }

        /* CHECK AUTHORISATION */
        $info = getUserInfo($db, $token, $parameters);
        if(!$token || $info["user"] != $info["owner"] || $info["user"] == null) {
            http_response_code(401) and exit();
        }

        if($data["password"]) {
            $data["pass_hash"] = password_hash($data["password"], PASSWORD_BCRYPT);
        } else unset($data["pass_hash"]);

        /* REMOVE ALL BUT WHITELISTED FIELDS FROM INPUT DATA */
        $data = array_intersect_key($data, array_flip(array("email", "findByEmail", "pass_hash")));

        /* CONSTRUCT QUERY */
        $query = "UPDATE geoshare.users SET ";
        $iterator = new CachingIterator(new ArrayIterator($data));
        foreach($iterator as $field => $value) $query .= $field . "='" . $value . ($iterator->hasNext()? "', " : "' ");
        $query .= "WHERE username='" . $parameters["username"] . "'";

        /* EXECUTE AND RETURN RESULT TO USER */
        http_response_code($db->query($query)? 200 : 500);
        
        break;

    case "DELETE":
        /* CATCH INVALID DATA */
        if(!isset($parameters["username"])) {
            http_response_code(400) and exit();
        }

        /* CHECK AUTHORISATION */
        $info = getUserInfo($db, $token, $parameters);
        if(!$token || $info["user"] != $info["owner"] || $info["user"] == null) {
            http_response_code(401) and exit();
        }

        /* EXECUTE AND RETURN RESULT TO USER */
        $db->begin_transaction();
        $path = "C:\\xampp\\ppic\\" . $db->query("SELECT pic_uri FROM geoshare.users WHERE username='" . $parameters["username"] . "'")->fetch_assoc()["pic_uri"];
        if($db->query("DELETE FROM geoshare.users WHERE username='" . $parameters["username"] . "'" ) and (unlink($path) or !file_exists($path))) {
            $db->commit();
            http_response_code(200);
        } else {
            $db->rollback();
            http_response_code(500);
        }

        break;

    default:
        http_response_code(405);
        break;
}
?>