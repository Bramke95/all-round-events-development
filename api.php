<?php
// all round events api
//-> The api that needs to be called is
//https://all-round-events.be/api.php
// always use query parameter action to define what action you want to perform

//**********************************************************************************************************
//									GENERAL HELPERS FUNTIONS
//**********************************************************************************************************
function token_check($id, $token_user, $db)
{
    //
    // Following things can happend with the token check
    // => The token is completely invalid and the api is returned with an error
    // => The token gives full access and the functions returns true

    if (is_null($id)) {
        invalidate_token($id, $db, false);
        exit(
            json_encode([
                "status" => 409,
                "error_type" => 4,
                "error_message" =>
                    "the request was made with an invalid token or a ID/Token mismatch",
            ])
        );
    }
    if (is_integer($id)) {
        invalidate_token($id, $db, false);
        exit(
            json_encode([
                "status" => 409,
                "error_type" => 4,
                "error_message" =>
                    "the request was made with an invalid token or a ID/Token mismatch",
            ])
        );
    }
    $statement = $db->prepare(
        "SELECT HASH FROM users inner join hashess on hashess.users_Id_Users = users.Id_Users where Id_Users = ?"
    );
    $statement->execute([$id]);
    $res = $statement->fetch(PDO::FETCH_ASSOC);
    if (!$res) {
        exit(
            json_encode([
                "status" => 409,
                "error_type" => 4,
                "error_message" =>
                    "the request was made with an invalid token or a ID/Token mismatch",
            ])
        );
    }
    $token_db = $res["HASH"];
    // check if the token excists
    if ($token_db != $token_user) {
        invalidate_token($id, $db, false);
        exit(
            json_encode([
                "status" => 409,
                "error_type" => 4,
                "error_message" =>
                    "the request was made with an invalid token or a ID/Token mismatch",
            ])
        );
    }
    return true;
}

function invalidate_token($id, $db, $override)
{
    if ($override) {
        return;
    }
    $statement = $db->prepare("delete from hashess where users_Id_Users	=?;");
    $statement->execute([$id]);
}

// get ip from user
function getRealUserIp()
{
    switch (true) {
        case !empty($_SERVER["HTTP_X_REAL_IP"]):
            return $_SERVER["HTTP_X_REAL_IP"];
        case !empty($_SERVER["HTTP_CLIENT_IP"]):
            return $_SERVER["HTTP_CLIENT_IP"];
        case !empty($_SERVER["HTTP_X_FORWARDED_FOR"]):
            return $_SERVER["HTTP_X_FORWARDED_FOR"];
        default:
            return $_SERVER["REMOTE_ADDR"];
    }
}

function admin_check($id, $token_user, $db, $override)
{
    //
    // does the same action as token_check but it also checks if the user is the admin, use this function for actions that need admin rights
    // => The token is completely invalid and the api is returned with an error
    //
    if (is_null($id)) {
        invalidate_token($id, $db, $override);
        exit(
            json_encode([
                "status" => 409,
                "error_type" => 4,
                "error_message" =>
                    "the request was made with an invalid token or a ID/Token mismatch",
            ])
        );
    }
    if (is_integer($id)) {
        invalidate_token($id, $db, $override);
        exit(
            json_encode([
                "status" => 409,
                "error_type" => 4,
                "error_message" =>
                    "the request was made with an invalid token or a ID/Token mismatch",
            ])
        );
    }
    $statement = $db->prepare(
        "SELECT HASH,Type FROM users inner join hashess on hashess.users_Id_Users = users.Id_Users where Id_Users = ?"
    );
    $statement->execute([$id]);
    $res = $statement->fetch(PDO::FETCH_ASSOC);
    if (!$res) {
        invalidate_token($id, $db, $override);
        exit(
            json_encode([
                "status" => 409,
                "error_type" => 4,
                "error_message" =>
                    "the request was made with an invalid token or a ID/Token mismatch",
            ])
        );
    }
    $token_db = $res["HASH"];
    $admin = $res["Type"];
    // check if the token excists
    if ($token_db == $token_user && $admin == "1") {
        return true;
    }
    invalidate_token($id, $db, $override);
    exit(
        json_encode([
            "status" => 409,
            "error_type" => 4,
            "error_message" => "No admin rights",
        ])
    );
}

function log_bad_credentials($db, $email)
{
    $ip = getRealUserIp();
    $statement = $db->prepare(
        "INSERT INTO logs (api, data, user_id, ip) VALUES(?, ?, ?, ?)"
    );
    $statement->execute(["login_fail", $email, "0", $ip]);
}

function split_name($name)
{
    // splits name into first and last name
    if (is_null($name)) {
        return ["", ""];
    }
    $name = trim($name);
    $last_name =
        strpos($name, " ") === false
            ? ""
            : preg_replace('#.*\s([\w-]*)$#', '$1', $name);
    $first_name = trim(
        preg_replace("#" . preg_quote($last_name, "#") . "#", "", $name)
    );
    return [$first_name, $last_name];
}

function add_to_mail_queue($db, $email, $subject, $message, $headers, $prio)
{
    // add mail to the mail queue
    $statement = $db->prepare(
        "INSERT INTO  mails (address, subject, mail_text, mail_headers, prio) VALUES(?, ?, ?, ?,?)"
    );
    $statement->execute([$email, $subject, $message, $headers, $prio]);
}

//******************************************************************************************************************************
//										maintanance
//******************************************************************************************************************************
/*
	$xml_dump = file_get_contents('php://input');
	$xml = json_decode($xml_dump,true);
	$ID = $xml["id"];
	if($ID != 1){
		header("Location: https://all-round-events.be/maintenance.html");
	}
	*/

//******************************************************************************************************************************
//										SETUP ENVIRONMENT
//******************************************************************************************************************************
// ignore all error reporting in production
error_reporting(E_ALL ^ E_DEPRECATED);

// include DB configuration, includes DB credentials
require_once "config.php";

// connect to the database
$db = new PDO(
    "mysql:host=" . $host . ";dbname=" . $name . ";charset=utf8",
    $user,
    $pass
);
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_WARNING);

// make sure every request is processed
ignore_user_abort(true);
// gets the action that needs to be performed.
$action = isset($_GET["action"]) ? $_GET["action"] : "";

//******************************************************************************************************************************
//										AUDIT LOGS
//******************************************************************************************************************************
$xml_dump = file_get_contents("php://input");
$xml = json_decode($xml_dump, true);
$ID = $xml["id"];
$ip = getRealUserIp();
if ($action == "login" || $action == "change_pass" || $action == "new_user") {
    $xml_dump = "User_credentials";
}
$statement = $db->prepare(
    "INSERT INTO logs (api, data, user_id, ip) VALUES(?, ?, ?, ?)"
);
$statement->execute([$action, $xml_dump, $ID, $ip]);

//******************************************************************************************************************************
//										ALL ACTIONS
//******************************************************************************************************************************
//
// This is the function that adds a new user to the database.
// It however does a lot of checks to determine if the activation is valid
// -> The new user needs a valid activation code
// -> the new user needs a email address that was never used before
// -> the user needs a valid password
//
//
if ($action == "new_user") {
    // get the contenct from the api body
    $xml_dump = file_get_contents("php://input");
    $xml = json_decode($xml_dump, true);
    // check if everything is in the body that we need, going further is useless without it
    try {
        $pass = $xml["pass"];
        $email = $xml["email"];
        $activation_code = (int) $xml["activation_code"];
    } catch (Exception $e) {
        exit(
            json_encode([
                "status" => 409,
                "error_type" => 4,
                "error_message" =>
                    "Not all fields where available, need: email, pass, activation_code",
            ])
        );
    }
    // check if the activation code is correct
    $statement = $db->prepare(
        "SELECT amount FROM Activation_codes WHERE  code= ?"
    );
    $statement->execute([$activation_code]);
    $amount = $statement->fetch(PDO::FETCH_ASSOC);
    if ($amount) {
        if ($amount < 1) {
            exit(
                json_encode([
                    "status" => 409,
                    "error_type" => 5,
                    "error_message" =>
                        "The activation code is valid but was already used",
                ])
            );
        }
    } else {
        exit(
            json_encode([
                "status" => 409,
                "error_type" => 3,
                "error_message" => "The activation code is invalid",
            ])
        );
    }

    // determine if the email is used
    $statement = $db->prepare("SELECT email FROM users WHERE email = ?");
    $statement->execute([$email]);
    $res = $statement->fetch(PDO::FETCH_ASSOC);
    if ($res) {
        exit(
            json_encode([
                "status" => 480,
                "error_type" => 1,
                "error_message" => "email is already in use",
            ])
        );
    }
    // the password stuff, check the password, create a salt and hash the stuff together
    $salt = bin2hex(openssl_random_pseudo_bytes(40));
    if (strlen($xml["pass"]) < 5) {
        exit(
            json_encode([
                "status" => 481,
                "error_type" => 2,
                "error_message" => "password must have more than 5 characters ",
            ])
        );
    }
    $hashed_pass = password_hash($pass . $salt, PASSWORD_DEFAULT);

    // everything is ok, save
    $statement = $db->prepare(
        "INSERT INTO users (email, pass, salt) VALUES(?, ?, ?)"
    );
    $statement->execute([$email, $hashed_pass, $salt]);

    $statement = $db->prepare("SELECT ID_Users from users WHERE email = ?");
    $statement->execute([$email]);
    $res = $statement->fetch(PDO::FETCH_ASSOC);
    if ($res) {
        $user_hash = bin2hex(openssl_random_pseudo_bytes(40));
        $statement = $db->prepare(
            "INSERT INTO  hashess (HASH, Type, users_Id_Users) VALUES(?, ?, ?)"
        );
        $statement->execute([$user_hash, 0, $res["ID_Users"]]);

        exit(
            json_encode([
                "status" => 200,
                "error_type" => 0,
                "id" => $res["ID_Users"],
                "hash" => $user_hash,
            ])
        );
    }
    exit(
        json_encode([
            "status" => 200,
            "error_type" => 10,
        ])
    );
}
//
// The whole site works with an authorization token. This token is generated in this function when
// password and email where correct.
// The token can be used to get all relevant info
// ! no usefull information is given why a login failed,
//
elseif ($action == "login") {
    // get the contenct from the api body
    $xml_dump = file_get_contents("php://input");
    $xml = json_decode($xml_dump, true);
    // check if everything is in the body that we need, going further is useless without it
    try {
        $pass = $xml["pass"];
        $email = $xml["email"];
    } catch (Exception $e) {
        exit(
            json_encode([
                "status" => 409,
                "error_type" => 4,
                "error_message" =>
                    "Not all fields where available, need: email, pass",
            ])
        );
    }

    $statement = $db->prepare(
        'select COUNT(*) from logs where logs.api = "login_fail" and logs.data = ? and logs.timestamp > DATE_SUB(CURRENT_TIMESTAMP(), INTERVAL 30 minute);'
    );
    $statement->execute([$email]);
    $res = $statement->fetch(PDO::FETCH_ASSOC);
    if ($res["COUNT(*)"] > 6) {
        // too many failed logins, fail the api and make hash invalid
        $statement = $db->prepare(
            "SELECT salt,pass,Id_Users,is_admin FROM users WHERE email = ?"
        );
        $statement->execute([$email]);
        $res = $statement->fetch(PDO::FETCH_ASSOC);
        $ID = $res["Id_Users"];
        $statement = $db->prepare(
            "DELETE FROM hashess where users_Id_Users = ?"
        );
        $statement->execute([$ID]);
        exit(
            json_encode([
                "status" => 403,
                "error_type" => 21,
                "error_message" => "Too many logins.",
            ])
        );
    }

    // checking if the email is known to us, if not the login process is stopped. Due to safety reasons it is not told to the frontend
    $statement = $db->prepare("SELECT email FROM users WHERE email = ?");
    $statement->execute([$email]);
    $res = $statement->fetch(PDO::FETCH_ASSOC);
    if (!$res) {
        log_bad_credentials($db, $email);
        exit(
            json_encode([
                "status" => 409,
                "error_type" => 6,
                "error_message" => "Login failed due to bad credentials",
            ])
        );
    }
    // getting the correct password and salt from the DB
    $statement = $db->prepare(
        "SELECT salt,pass,Id_Users,is_admin FROM users WHERE email = ?"
    );
    $statement->execute([$email]);
    $res = $statement->fetch(PDO::FETCH_ASSOC);
    $salt = $res["salt"];
    $correct_pass = $res["pass"];
    $ID = $res["Id_Users"];
    $is_admin = $res["is_admin"];
    // checking password
    if (password_verify($pass . $salt, $correct_pass)) {
        // login was succesfull, check if the user already got a previous token
        $statement = $db->prepare(
            "SELECT HASH FROM users inner join hashess on  hashess.users_Id_Users =  users.Id_Users where email = ?"
        );
        $statement->execute([$email]);
        $res = $statement->fetch(PDO::FETCH_ASSOC);
        $user_hash = "FAIL";
        // no previous token excists so we make a new one
        if (!$res) {
            $user_hash = bin2hex(openssl_random_pseudo_bytes(40));
            $statement = $db->prepare(
                "INSERT INTO  hashess (HASH, Type, users_Id_Users) VALUES(?, ?, ?)"
            );
            $statement->execute([$user_hash, $is_admin, $ID]);
        }
        // a previous token excists, this token is deleted and a new one is made.
        else {
            $statement = $db->prepare(
                "DELETE FROM hashess where users_Id_Users = ?"
            );
            $statement->execute([$ID]);
            $user_hash = bin2hex(openssl_random_pseudo_bytes(40));
            $statement = $db->prepare(
                "INSERT INTO  hashess (HASH, Type, users_Id_Users) VALUES(?, ?, ?)"
            );
            $statement->execute([$user_hash, $is_admin, $ID]);
        }
        exit(
            json_encode([
                "status" => 200,
                "error_type" => 0,
                "id" => $ID,
                "hash" => $user_hash,
				"is_admin" => $is_admin,
            ])
        );
    }
    // the password is incorrect sp
    else {
        log_bad_credentials($db, $email);
        $statement = $db->prepare(
            "SELECT salt,pass,Id_Users,is_admin FROM users WHERE email = ?"
        );
        $statement->execute([$email]);
        $res = $statement->fetch(PDO::FETCH_ASSOC);
        $ID = $res["Id_Users"];
        $statement = $db->prepare(
            "DELETE FROM hashess where users_Id_Users = ?"
        );
        $statement->execute([$ID]);
        exit(
            json_encode([
                "status" => 409,
                "error_type" => 6,
                "error_message" => "Login failed due to bad credentials",
            ])
        );
    }
}
//
// Logout, this is pritty useless but provides a safety feature for the end user. The token he used is now
// invalided so no one can use it to access his data
//
elseif ($action == "logout") {
    // get the contenct from the api body
    $xml_dump = file_get_contents("php://input");
    $xml = json_decode($xml_dump, true);
    // check if all the content is in the body of the api
    try {
        $ID = $xml["ID"];
        $HASH = $xml["HASH"];
    } catch (Exception $e) {
        exit(
            json_encode([
                "status" => 409,
                "error_type" => 4,
                "error_message" =>
                    "Not all fields where available, need: ID, HASH",
            ])
        );
    }
    // check if the token and the ID match
    token_check($ID, $HASH, $db);
    // delete the token hash from the db
    $statement = $db->prepare("DELETE FROM hashess where users_Id_Users = ?");
    $statement->execute([$ID]);
    exit(
        json_encode([
            "status" => 200,
            "error_type" => 0,
            "error_message" => "Logout ok",
        ])
    );
}
//
// This api inserts or updates the main data from. Only the password, salt, Id and email cannot be changed.
// This api works as an update, it will overwrite everything if the content is not the same.
// ! a valid token is needed to access the info
//
elseif ($action == "insert_main") {
    // get the contenct from the api body
    $xml_dump = file_get_contents("php://input");
    $xml = json_decode($xml_dump, true);
    try {
        $ID = $xml["id"];
        $HASH = $xml["hash"];
        $name = $xml["name"];
        $date_of_birth = $xml["date_of_birth"];
        $gender = $xml["Gender"];
        $size = $xml["size"];
        $employment = $xml["employment"];
        $address_line_one = $xml["adres_line_one"];
        $adress_line_two = $xml["adres_line_two"];
        $driver_license = $xml["driver_license"];
        $nationality = $xml["nationality"];
        $telephone = $xml["telephone"];
        $marital_state = $xml["marital_state"];
        $text = $xml["text"];
    } catch (Exception $e) {
        exit(
            json_encode([
                "status" => 409,
                "error_type" => 4,
                "error_message" =>
                    "Not all fields where available, need: ID, HASH",
            ])
        );
    }
    // check if the api had a valid token that has read/write property
    token_check($ID, $HASH, $db);
    // See if the user is setting new date or overwriting it :
    $statement = $db->prepare(
        "SELECT * FROM users_data WHERE users_Id_Users = ?"
    );
    $statement->execute([$ID]);
    $res = $statement->fetch(PDO::FETCH_ASSOC);
    //  put everything in the database
    if (!$res) {
        $statement = $db->prepare(
            "INSERT INTO users_data (name,size, date_of_birth, Gender, adres_line_one, adres_line_two, driver_license, nationality, telephone, marital_state, text, employment, users_Id_Users) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)"
        );
        $statement->execute([
            $name,
            $size,
            $date_of_birth,
            $gender,
            $address_line_one,
            $adress_line_two,
            $driver_license,
            $nationality,
            $telephone,
            $marital_state,
            $text,
            $employment,
            $ID,
        ]);
    } else {
        $statement = $db->prepare(
            "UPDATE users_data set name=?, size=?, date_of_birth=?, Gender=?, adres_line_one=?, adres_line_two=?, driver_license=?, nationality=?, telephone =?, marital_state=?, text=?, employment=? where users_Id_Users=?"
        );
        $statement->execute([
            $name,
            $size,
            $date_of_birth,
            $gender,
            $address_line_one,
            $adress_line_two,
            $driver_license,
            $nationality,
            $telephone,
            $marital_state,
            $text,
            $employment,
            $ID,
        ]);
    }
    // end the api
    exit(
        json_encode([
            "status" => 200,
            "error_type" => 0,
        ])
    );
}
//
// This api inserts or updates the main data from for other users. Only the password, salt, Id and email cannot be changed.
// This api works as an update, it will overwrite everything if the content is not the same.
// This action can only be performed by the admin
//
elseif ($action == "insert_main_admin") {
    // get the contenct from the api body
    $xml_dump = file_get_contents("php://input");
    $xml = json_decode($xml_dump, true);
    try {
        $ID = $xml["id"];
        $HASH = $xml["hash"];
        $user_id = $xml["user_id"];
        $name = $xml["name"];
        $date_of_birth = $xml["date_of_birth"];
        $gender = $xml["Gender"];
        $size = $xml["size"];
        $address_line_one = $xml["adres_line_one"];
        $adress_line_two = $xml["adres_line_two"];
        $driver_license = $xml["driver_license"];
        $nationality = $xml["nationality"];
        $telephone = $xml["telephone"];
        $marital_state = $xml["marital_state"];
        $employment = $xml["employment"];
        $text = $xml["text"];
        $remarks = $xml["remarks"];
        $blocked = $xml["blocked"];
    } catch (Exception $e) {
        exit(
            json_encode([
                "status" => 409,
                "error_type" => 4,
                "error_message" =>
                    "Not all fields where available, need: ID, HASH",
            ])
        );
    }
    // check if the api had a valid token that has read/write property
    admin_check($ID, $HASH, $db, false);
    // See if the user is setting new date or overwriting it :
    $statement = $db->prepare(
        "SELECT * FROM users_data WHERE users_Id_Users = ?"
    );
    $statement->execute([$ID]);
    $res = $statement->fetch(PDO::FETCH_ASSOC);
    //  put everything in the database
    if (!$res) {
        $statement = $db->prepare(
            "INSERT INTO users_data (name, size, date_of_birth, Gender, adres_line_one, adres_line_two, driver_license, nationality, telephone, marital_state, text, employment, remarks, blocked, users_Id_Users) VALUES (?,?,?,?,?,?,?,?,?,?,?,?);"
        );
        $statement->execute([
            $name,
            $size,
            $date_of_birth,
            $gender,
            $address_line_one,
            $adress_line_two,
            $driver_license,
            $nationality,
            $telephone,
            $marital_state,
            $text,
            $employment,
            $remarks,
            $blocked,
            $user_id,
        ]);
    } else {
        $statement = $db->prepare(
            "UPDATE users_data set name=?, size=?, date_of_birth=?, Gender=?, adres_line_one=?, adres_line_two=?, driver_license=?, nationality=?, telephone =?, marital_state=?, text=?, employment=?, remarks=?, blocked=? where users_Id_Users=?;"
        );
        $statement->execute([
            $name,
            $size,
            $date_of_birth,
            $gender,
            $address_line_one,
            $adress_line_two,
            $driver_license,
            $nationality,
            $telephone,
            $marital_state,
            $text,
            $employment,
            $remarks,
            $blocked,
            $user_id,
        ]);
    }
    // end the api
    exit(
        json_encode([
            "status" => 200,
            "error_type" => 0,
        ])
    );
}
//
// get all main information from the database
// To get the information an ID and a HASH is needed, the hash only needs write access
//
elseif ($action == "get_main") {
    $xml_dump = file_get_contents("php://input");
    $xml = json_decode($xml_dump, true);
    try {
        $ID = $xml["id"];
        $HASH = $xml["hash"];
    } catch (Exception $e) {
        exit(
            json_encode([
                "status" => 409,
                "error_type" => 4,
                "error_message" => "Not all fields where available",
            ])
        );
    }
    token_check($ID, $HASH, $db);
    $statement = $db->prepare(
        "SELECT * FROM users_data WHERE users_Id_Users = ?"
    );
    $statement->execute([$ID]);
    $res = $statement->fetch(PDO::FETCH_ASSOC);

    $statement = $db->prepare("SELECT * FROM users WHERE Id_Users = ?");
    $statement->execute([$ID]);
    $res2 = $statement->fetch(PDO::FETCH_ASSOC);

    if (!$res) {
        exit(
            json_encode([
                "status" => 409,
                "error_type" => 8,
                "errpr_message" => "no info found",
            ])
        );
    }
    exit(
        json_encode([
            "status" => 200,
            "error_type" => 0,
            "id" => $res2["Id_Users"],
            "name" => $res["name"],
            "date_of_birth" => $res["date_of_birth"],
            "Gender" => $res["Gender"],
            "adres_line_one" => $res["adres_line_one"],
            "adres_line_two" => $res["adres_line_two"],
            "driver_license" => $res["driver_license"],
            "employment" => $res["employment"],
            "size" => $res["size"],
            "nationality" => $res["nationality"],
            "telephone" => $res["telephone"],
            "marital_state" => $res["marital_state"],
            "email" => $res2["email"],
            "subscribed" => $res2["subscribed"],
            "blocked" => $res["blocked"],
            "text" => $res["text"],
        ])
    );
}
//
// get all main information from the database
// To get the information an ID and a HASH is needed, the hash only needs write access
//
elseif ($action == "get_main_admin") {
    $xml_dump = file_get_contents("php://input");
    $xml = json_decode($xml_dump, true);
    try {
        $ID = $xml["id"];
        $HASH = $xml["hash"];
        $user_id = $xml["user_id"];
    } catch (Exception $e) {
        exit(
            json_encode([
                "status" => 409,
                "error_type" => 4,
                "error_message" => "Not all fields where available",
            ])
        );
    }
    admin_check($ID, $HASH, $db, false);
    $statement = $db->prepare(
        "SELECT * FROM users_data WHERE users_Id_Users = ?"
    );
    $statement->execute([$user_id]);
    $res = $statement->fetch(PDO::FETCH_ASSOC);

    $statement = $db->prepare("SELECT * FROM users WHERE Id_Users = ?");
    $statement->execute([$user_id]);
    $res2 = $statement->fetch(PDO::FETCH_ASSOC);

    $statement = $db->prepare(
        "SELECT * FROM Images WHERE users_Id_Users = ? and is_primary=1 limit 1;"
    );
    $statement->execute([$user_id]);
    $res3 = $statement->fetch(PDO::FETCH_ASSOC);

    if (!$res) {
        exit(
            json_encode([
                "status" => 409,
                "error_type" => 8,
                "errpr_message" => "no info found",
            ])
        );
    }
    exit(
        json_encode([
            "status" => 200,
            "error_type" => 0,
            "id" => $res2["Id_Users"],
            "name" => $res["name"],
            "date_of_birth" => $res["date_of_birth"],
            "gender" => $res["Gender"],
            "adres_line_one" => $res["adres_line_one"],
            "adres_line_two" => $res["adres_line_two"],
            "driver_license" => $res["driver_license"],
            "employment" => $res["employment"],
            "size" => $res["size"],
            "nationality" => $res["nationality"],
            "telephone" => $res["telephone"],
            "marital_state" => $res["marital_state"],
            "email" => $res2["email"],
            "text" => $res["text"],
            "blocked" => $res["blocked"],
            "remarks" => $res["remarks"],
            "picture_name" => $res3["picture_name"],
        ])
    );
}

//
// DEPRECATED, but keep for maybe the future?
//
//
elseif ($action == "add_education") {
    exit(
        json_encode([
            "status" => 500,
            "error_type" => 7,
            "error_message" => "Action is DEPRECATED",
        ])
    );
    $xml_dump = file_get_contents("php://input");
    $xml = json_decode($xml_dump, true);
    try {
        $ID = $xml["id"];
        $HASH = $xml["hash"];
        $from = $xml["from"];
        $to = $xml["to"];
        $school = $xml["school"];
        $education = $xml["education"];
        $percentage = $xml["percentage"];
    } catch (Exception $e) {
        exit(
            json_encode([
                "status" => 409,
                "error_type" => 4,
                "error_message" => "Not all fields where available",
            ])
        );
    }
    if (!token_check($ID, $HASH, $db)) {
        exit(
            json_encode([
                "status" => 409,
                "error_type" => 7,
                "error_message" => "Token only has reading rights! ",
            ])
        );
    }
    $statement = $db->prepare(
        "INSERT INTO educations (from_date, to_date, school, education, percentage, users_Id_Users) VALUES (?,?,?,?,?,?)"
    );
    $statement->execute([$from, $to, $school, $education, $percentage, $ID]);

    exit(
        json_encode([
            "status" => 200,
            "error_type" => 100,
        ])
    );
}

//
// DEPRECATED
//
//
elseif ($action == "get_education") {
    exit(
        json_encode([
            "status" => 500,
            "error_type" => 7,
            "error_message" => "Action is DEPRECATED",
        ])
    );
    $xml_dump = file_get_contents("php://input");
    $xml = json_decode($xml_dump, true);
    try {
        $ID = $xml["id"];
        $HASH = $xml["hash"];
    } catch (Exception $e) {
        exit(
            json_encode([
                "status" => 409,
                "error_type" => 4,
                "error_message" =>
                    "Not all fields where available, need: ID, HASH",
            ])
        );
    }
    // check if the user is and token is valid
    token_check($ID, $HASH, $db);

    $statement = $db->prepare(
        "SELECT * FROM educations WHERE users_Id_Users = ?"
    );
    $statement->execute([$ID]);
    $res = $statement->fetchAll();

    if ($res) {
        $json = json_encode($res);
        exit($json);
    }
    exit(
        json_encode([
            "status" => 200,
            "error_type" => 4,
            "error_message" => "No education found",
        ])
    );
}

//
// DEPRECATED
//
//
elseif ($action == "delete_education") {
    exit(
        json_encode([
            "status" => 500,
            "error_type" => 7,
            "error_message" => "Action is DEPRECATED",
        ])
    );
    $xml_dump = file_get_contents("php://input");
    $xml = json_decode($xml_dump, true);
    try {
        $ID = $xml["id"];
        $HASH = $xml["hash"];
        $edu_id = $xml["education_id"];
    } catch (Exception $e) {
        exit(
            json_encode([
                "status" => 409,
                "error_type" => 4,
                "error_message" =>
                    "Not all fields where available, need: ID, HASH",
            ])
        );
    }
    // check if the user is and token is valid
    token_check($ID, $HASH, $db);
    $statement = $db->prepare(
        "DELETE FROM educations WHERE users_Id_Users = ? AND ideducations_id = ?"
    );
    $statement->execute([$ID, $edu_id]);
    // end the api
    exit(
        json_encode([
            "status" => 200,
            "error_type" => 100,
        ])
    );
}

//
// action is DEPRECATED
//
//
elseif ($action == "add_language") {
    exit(
        json_encode([
            "status" => 500,
            "error_type" => 7,
            "error_message" => "Action is DEPRECATED",
        ])
    );
    $xml_dump = file_get_contents("php://input");
    $xml = json_decode($xml_dump, true);
    try {
        $ID = $xml["id"];
        $HASH = $xml["hash"];
        $lang = $xml["lang"];
        $speak = $xml["speak"];
        $write = $xml["write"];
        $read = $xml["read"];
    } catch (Exception $e) {
        exit(
            json_encode([
                "status" => 409,
                "error_type" => 4,
                "error_message" =>
                    "Not all fields where available, need: ID, HASH",
            ])
        );
    }
    // check if the api had a valid token that has read/write property
    if (!token_check($ID, $HASH, $db)) {
        exit(
            json_encode([
                "status" => 409,
                "error_type" => 7,
                "error_message" => "Token only has reading rights! ",
            ])
        );
    }
    $statement = $db->prepare(
        "INSERT INTO language (language, speaking, writing, reading, users_Id_Users) VALUES (?,?,?,?,?)"
    );
    $statement->execute([$lang, $speak, $write, $read, $ID]);

    // end the api
    exit(
        json_encode([
            "status" => 200,
            "error_type" => 100,
        ])
    );
}

//
// DEPRECATED
//
//
elseif ($action == "get_languages") {
    exit(
        json_encode([
            "status" => 500,
            "error_type" => 7,
            "error_message" => "Action is DEPRECATED",
        ])
    );
    $xml_dump = file_get_contents("php://input");
    $xml = json_decode($xml_dump, true);
    try {
        $ID = $xml["id"];
        $HASH = $xml["hash"];
    } catch (Exception $e) {
        exit(
            json_encode([
                "status" => 409,
                "error_type" => 4,
                "error_message" =>
                    "Not all fields where available, need: ID, HASH",
            ])
        );
    }
    // check if the user is and token is valid
    token_check($ID, $HASH, $db);

    $statement = $db->prepare(
        "SELECT * FROM language WHERE users_Id_Users = ?"
    );
    $statement->execute([$ID]);
    $res = $statement->fetchAll();

    if ($res) {
        $json = json_encode($res);
        exit($json);
    }
    exit(
        json_encode([
            "status" => 200,
            "error_type" => 4,
            "error_message" => "No languages found",
        ])
    );
}

//
// DEPRECATED
//
//
elseif ($action == "delete_language") {
    exit(
        json_encode([
            "status" => 500,
            "error_type" => 7,
            "error_message" => "Action is DEPRECATED",
        ])
    );
    $xml_dump = file_get_contents("php://input");
    $xml = json_decode($xml_dump, true);
    try {
        $ID = $xml["id"];
        $HASH = $xml["hash"];
        $language_id = $xml["language_id"];
    } catch (Exception $e) {
        exit(
            json_encode([
                "status" => 409,
                "error_type" => 4,
                "error_message" =>
                    "Not all fields where available, need: ID, HASH",
            ])
        );
    }
    // check if the user is and token is valid
    token_check($ID, $HASH, $db);
    $statement = $db->prepare(
        "DELETE FROM language WHERE users_Id_Users = ? AND language_id = ?"
    );
    $statement->execute([$ID, $language_id]);
    // end the api
    exit(
        json_encode([
            "status" => 200,
            "error_type" => 100,
        ])
    );
}

//
// DEPRECATED
//
//
elseif ($action == "add_expierence") {
    exit(
        json_encode([
            "status" => 500,
            "error_type" => 7,
            "error_message" => "Action is DEPRECATED",
        ])
    );
    $xml_dump = file_get_contents("php://input");
    $xml = json_decode($xml_dump, true);
    try {
        $ID = $xml["id"];
        $HASH = $xml["hash"];
        $company = $xml["company"];
        $jobtitle = $xml["jobtitle"];
        $from_date = $xml["from_date"];
        $to_date = $xml["to_date"];
    } catch (Exception $e) {
        exit(
            json_encode([
                "status" => 409,
                "error_type" => 4,
                "error_message" =>
                    "Not all fields where available, need: ID, HASH",
            ])
        );
    }
    // check if the api had a valid token that has read/write property
    if (!token_check($ID, $HASH, $db)) {
        exit(
            json_encode([
                "status" => 409,
                "error_type" => 7,
                "error_message" => "Token only has reading rights! ",
            ])
        );
    }
    $statement = $db->prepare(
        "INSERT INTO expierence (compamy, jobtitle, from_date, to_date, users_Id_Users) VALUES (?,?,?,?,?)"
    );
    $statement->execute([$company, $jobtitle, $from_date, $to_date, $ID]);
    // end the api
    exit(
        json_encode([
            "status" => 200,
            "error_type" => 100,
        ])
    );
}

//
// DEPERECATED
//
//
elseif ($action == "get_expierence") {
    exit(
        json_encode([
            "status" => 500,
            "error_type" => 7,
            "error_message" => "Action is DEPRECATED",
        ])
    );
    $xml_dump = file_get_contents("php://input");
    $xml = json_decode($xml_dump, true);
    try {
        $ID = $xml["id"];
        $HASH = $xml["hash"];
    } catch (Exception $e) {
        exit(
            json_encode([
                "status" => 409,
                "error_type" => 4,
                "error_message" =>
                    "Not all fields where available, need: ID, HASH",
            ])
        );
    }
    // check if the user is and token is valid
    token_check($ID, $HASH, $db);

    $statement = $db->prepare(
        "SELECT * FROM expierence WHERE users_Id_Users = ?"
    );
    $statement->execute([$ID]);
    $res = $statement->fetchAll();

    if ($res) {
        $json = json_encode($res);
        exit($json);
    }
    exit(
        json_encode([
            "status" => 200,
            "error_type" => 4,
            "error_message" => "No languages found",
        ])
    );
}

//
// DEPERECATED
//
//
elseif ($action == "delete_expierence") {
    exit(
        json_encode([
            "status" => 500,
            "error_type" => 7,
            "error_message" => "Action is DEPRECATED",
        ])
    );
    $xml_dump = file_get_contents("php://input");
    $xml = json_decode($xml_dump, true);
    try {
        $ID = $xml["id"];
        $HASH = $xml["hash"];
        $language_id = $xml["idexpierence"];
    } catch (Exception $e) {
        exit(
            json_encode([
                "status" => 409,
                "error_type" => 4,
                "error_message" =>
                    "Not all fields where available, need: ID, HASH",
            ])
        );
    }
    // check if the user is and token is valid
    token_check($ID, $HASH, $db);
    $statement = $db->prepare(
        "DELETE FROM expierence WHERE users_Id_Users = ? AND idexpierence = ?"
    );
    $statement->execute([$ID, $language_id]);
    // end the api
    exit(
        json_encode([
            "status" => 200,
            "error_type" => 100,
        ])
    );
}

//
// uploading an picture to the website
// -> forsee ID, HASH
// -> forsee image as fromdata
// -> check status code
//
elseif ($action == "upload_picture") {
    $xml_dump = json_encode(json_decode($_POST["auth"]));
    $xml = json_decode($xml_dump, true);
    $is_primary = 0;
    try {
        $ID = $xml["ID"];
        $HASH = $xml["TOKEN"];
    } catch (Exception $e) {
        exit(
            json_encode([
                "status" => 409,
                "error_type" => 4,
                "error_message" =>
                    "Not all fields where available, need: ID, HASH",
            ])
        );
    }
    // check if the user is and token is valid
    token_check($ID, $HASH, $db);

    // make sure Id is clean
    $ID = str_replace('"', "", $ID);

    // Check if user has not more than 5 pictures
    $statement = $db->prepare(
        "SELECT COUNT(*) FROM Images WHERE users_Id_Users = ?;"
    );
    $statement->execute([(int) $ID]);
    $res = $statement->fetch(PDO::FETCH_ASSOC);
    $count = $res["COUNT(*)"];
    if ($count > 4) {
        exit(
            json_encode([
                "status" => 409,
                "error_type" => 9,
                'error_message => "Only 5 pictures allowed!"',
            ])
        );
    }

    // make sure that the first images is profile picure by default
    if ($count == 0) {
        $is_primary = 1;
    }

    // handle file write:
    // -> generate random hash
    // -> check if file is an images with a size in reasons
    // -> write image to file system with the random hash as name
    // -> save hash name to DB and link it to user
    $random_hash = bin2hex(openssl_random_pseudo_bytes(32));
    $target_dir = "upload/";
    $target_file = $target_dir . basename($_FILES["img"]["name"]);
    $uploadOk = 1;
    $imageFileType = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));
    $target_file = $target_dir . $random_hash . "." . $imageFileType;
    if (isset($_POST["submit"])) {
        $check = getimagesize($_FILES["img"]["tmp_name"]);
        if ($check !== false) {
            echo "File is an image - " . $check["mime"] . ".";
            $uploadOk = 1;
        } else {
            echo "File is not an image.";
            $uploadOk = 0;
        }
    }

    // Check file size
    if ($_FILES["fileToUpload"]["size"] > 250000) {
        echo "Sorry, your file is too large.";
        $uploadOk = 0;
    }
    // Allow certain file formats
    if (
        $imageFileType != "jpg" &&
        $imageFileType != "png" &&
        $imageFileType != "jpeg" &&
        $imageFileType != "gif"
    ) {
        exit(
            json_encode([
                "status" => 409,
                "error_type" => 11,
                'error_message => "Not a valid format"',
            ])
        );
        $uploadOk = 0;
    }
    // Check if $uploadOk is set to 0 by an error
    if ($uploadOk == 0) {
        echo "Sorry, your file was not uploaded.";
        // if everything is ok, try to upload file
    } else {
        if (move_uploaded_file($_FILES["img"]["tmp_name"], $target_file)) {
            $ID = str_replace('"', "", $ID);
            $statement = $db->prepare(
                "INSERT INTO Images (picture_name, is_primary, users_Id_Users) VALUES (?,?,?)"
            );
            $statement->execute([$target_file, (int) $is_primary, (int) $ID]);
            exit(
                json_encode([
                    "status" => 200,
                    "error_type" => 0,
                    "error_message" => "OK, image uploaded",
                ])
            );
        } else {
            echo "Sorry, there was an error uploading your file.";
        }
    }
}

//
// uploading an picture to the website
// -> forsee ID, HASH
// -> forsee image as fromdata
// -> check status code
//
elseif ($action == "upload_festival_file") {
    $xml_dump = json_encode(json_decode($_POST["auth"]));
    $xml1 = json_decode($xml_dump, true);
	$xml_dump = json_encode(json_decode($_POST["data"]));
	$xml2 = json_decode($xml_dump, true);
    try {
        $ID = $xml1["ID"];
        $HASH = $xml1["TOKEN"];
		$festival = $xml2["festi_id"];
    } catch (Exception $e) {
        exit(
            json_encode([
                "status" => 409,
                "error_type" => 4,
                "error_message" =>
                    "Not all fields where available, need: ID, HASH",
            ])
        );
    }
    // check if the user is and token is valid
    admin_check($ID, $HASH, $db, false);

    // make sure Id is clean
    $ID = str_replace('"', "", $ID);

    // handle file write:
    // -> generate random hash
    // -> check if file is an images with a size in reasons
    // -> write image to file system with the random hash as name
    // -> save hash name to DB and link it to user
    $random_hash = bin2hex(openssl_random_pseudo_bytes(32));
    $target_dir = "files/";
    $target_file = $target_dir . basename($_FILES["file"]["name"]);
    $uploadOk = 1;
    $imageFileType = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));
    $target_file = $target_dir . $random_hash . "." . $imageFileType;
	
    // Check file size
    if (move_uploaded_file($_FILES["file"]["tmp_name"], $target_file)) {
        $ID = str_replace('"', "", $ID);
        $statement = $db->prepare(
            "INSERT INTO festivals_files (original_filename, filename, festi_id) VALUES (?,?,?);"
        );
        $statement->execute([$_FILES["file"]["name"], $target_file, $festival]);
        exit(
            json_encode([
                "status" => 200,
                "error_type" => 0,
                "error_message" => "OK, image uploaded",
            ])
        );
    } else {
        //echo "Sorry, there was an error uploading your file.";
    }
}

//
// get all files for festival
//
//

elseif ($action == "get_festi_files") {
	$xml_dump = file_get_contents("php://input");
    $xml = json_decode($xml_dump, true);
	try {
		$ID = $xml["id"];
		$HASH = $xml["hash"];
		$festival_id = $xml["festival_id"];
	}
	catch(Exception $e){
        exit(
            json_encode([
                "status" => 409,
                "error_type" => 4,
                "error_message" =>
                    "Not all fields where available, need: ID, HASH",
            ])
        );
	}
	admin_check($ID, $HASH, $db, false);
	$statement = $db->prepare(
        "SELECT * from festivals_files where festivals_files.festi_id =?;"
    );
    $statement->execute([$festival_id]);
    $res = $statement->fetchAll();
	if ($res) {
        $json = json_encode($res);
        exit($json);
    }
	exit(json_encode(json_decode("{}")));
}

//
// requests all pictures by user, this function only provides the unique id of the picture, the picture itself is only the relative url
//
//
elseif ($action == "get_pictures") {
    $xml_dump = file_get_contents("php://input");
    $xml = json_decode($xml_dump, true);
    try {
        $ID = $xml["id"];
        $HASH = $xml["hash"];
    } catch (Exception $e) {
        exit(
            json_encode([
                "status" => 409,
                "error_type" => 4,
                "error_message" =>
                    "Not all fields where available, need: ID, HASH",
            ])
        );
    }
    // check if the user is and token is valid
    token_check($ID, $HASH, $db);

    // request all pictures by user
    $statement = $db->prepare(
        "SELECT picture_name, is_primary  FROM Images WHERE users_Id_Users = ?"
    );
    $statement->execute([$ID]);
    $res = $statement->fetchAll();

    if ($res) {
        $json = json_encode($res);
        exit($json);
    }
    exit(
        json_encode([
            "status" => 200,
            "error_type" => 4,
            "error_message" => "No pictures found",
        ])
    );
}

//
// delete a picture from a user. This function removes the DB entry AND the picture itself.
//
//
elseif ($action == "delete_festi_file") {
    $xml_dump = file_get_contents("php://input");
    $xml = json_decode($xml_dump, true);
    try {
        $ID = $xml["id"];
        $HASH = $xml["hash"];
        $festi_file = $xml["festi_file"];
    } catch (Exception $e) {
        exit(
            json_encode([
                "status" => 409,
                "error_type" => 4,
                "error_message" =>
                    "Not all fields where available, need: ID, HASH",
            ])
        );
    }
    // check if the user is and token is valid
    admin_check($ID, $HASH, $db, false);

    $statement = $db->prepare(
        "SELECT * FROM festivals_files where festivals_files_id=?;"
    );
    $statement->execute([$festi_file]);
    $res = $statement->fetch(PDO::FETCH_ASSOC);
	$file = "";
    if ($res) {
		$file = $res["filename"];
    }
	
	if (file_exists($file)) {
        unlink($file);
    }
	

    $statement = $db->prepare(
        "DELETE FROM festivals_files WHERE festivals_files_id=?;"
    );
    $statement->execute([$festi_file]);

    exit(
        json_encode([
            "status" => 200,
            "error_type" => 0,
            "error_message" => "ok",
        ])
    );
}

//
// delete a picture from a user. This function removes the DB entry AND the picture itself.
//
//
elseif ($action == "delete_picture") {
    $xml_dump = file_get_contents("php://input");
    $xml = json_decode($xml_dump, true);
    try {
        $ID = $xml["id"];
        $HASH = $xml["hash"];
        $picture = $xml["image"];
    } catch (Exception $e) {
        exit(
            json_encode([
                "status" => 409,
                "error_type" => 4,
                "error_message" =>
                    "Not all fields where available, need: ID, HASH",
            ])
        );
    }
    // check if the user is and token is valid
    token_check($ID, $HASH, $db);

    if (file_exists($picture)) {
        unlink($picture);
    }
    $statement = $db->prepare(
        "select is_primary from Images WHERE users_Id_Users = ? and picture_name = ?"
    );
    $statement->execute([$ID, $picture]);
    $res = $statement->fetch(PDO::FETCH_ASSOC);
    if ($res) {
        if ($res["is_primary"] == 1) {
            $statement = $db->prepare(
                "select picture_name from Images WHERE users_Id_Users = ? AND is_primary !=1 LIMIT 1"
            );
            $statement->execute([$ID]);
            $res = $statement->fetch(PDO::FETCH_ASSOC);
            $name = $res["picture_name"];
            $statement = $db->prepare(
                "UPDATE Images set is_primary=1 where users_Id_Users=? and picture_name=?"
            );
            $statement->execute([(int) $ID, $name]);
        }
    }

    $statement = $db->prepare(
        "DELETE FROM Images WHERE users_Id_Users = ? and picture_name = ?"
    );
    $statement->execute([$ID, $picture]);

    exit(
        json_encode([
            "status" => 200,
            "error_type" => 0,
            "error_message" => "ok",
        ])
    );
}

//
// make a different piture your profile picture, all other pictures will be removed from profile and the picture given to this api is enabled
//
//
elseif ($action == "make_profile") {
    $xml_dump = file_get_contents("php://input");
    $xml = json_decode($xml_dump, true);
    try {
        $ID = $xml["id"];
        $HASH = $xml["hash"];
        $picture = $xml["image"];
    } catch (Exception $e) {
        exit(
            json_encode([
                "status" => 409,
                "error_type" => 4,
                "error_message" =>
                    "Not all fields where available, need: ID, HASH",
            ])
        );
    }
    // check if the user is and token is valid
    token_check($ID, $HASH, $db);
    //check if the picture atually excists, then remove it
    if (file_exists($picture)) {
        $statement = $db->prepare(
            "UPDATE Images set is_primary=0 where users_Id_Users=?"
        );
        $statement->execute([(int) $ID]);
        $statement = $db->prepare(
            "UPDATE Images set is_primary=1 where users_Id_Users=? and picture_name=?"
        );
        $statement->execute([(int) $ID, $picture]);
        exit(
            json_encode([
                "status" => 200,
                "error_type" => 0,
                "error_message" => "OK",
            ])
        );
    } else {
        exit(
            json_encode([
                "status" => 200,
                "error_type" => 10,
                "error_message" => "File not found",
            ])
        );
    }
}

//
// check if the current user has administrator privileges.
//
//
elseif ($action == "is_admin") {
    $xml_dump = file_get_contents("php://input");
    $xml = json_decode($xml_dump, true);
    try {
        $ID = $xml["id"];
        $HASH = $xml["hash"];
    } catch (Exception $e) {
        exit(
            json_encode([
                "status" => 409,
                "error_type" => 4,
                "error_message" =>
                    "Not all fields where available, need: ID, HASH",
            ])
        );
    }
    // check if the user is and token is valid and it is admin
    admin_check($ID, $HASH, $db, true);
    exit(
        json_encode([
            "status" => 200,
            "error_type" => 0,
            "error_message" => "person is admin",
        ])
    );
}

//
// This action adds a festival/evenement to the database, This only adds the pure evenement in the database, not the shifts/days
// This action can only be performed by an administrator
// this api also returns all the opened festivals
//
elseif ($action == "add_festival") {
    // get the contenct from the api body
    $xml_dump = file_get_contents("php://input");
    $xml = json_decode($xml_dump, true);
    try {
        $ID = $xml["id"];
        $HASH = $xml["hash"];
        $date = $xml["date"];
        $status = $xml["status"];
        $name = $xml["name"];
        $details = $xml["festival_discription"];
    } catch (Exception $e) {
        exit(
            json_encode([
                "status" => 409,
                "error_type" => 4,
                "error_message" =>
                    "Not all fields where available, need: name, details, status, date, ID, HASH",
            ])
        );
    }
    // this is an admin action, check if this is an admin
    admin_check($ID, $HASH, $db, false);
    // entering the festical in the DB
    $statement = $db->prepare(
        "INSERT INTO festivals (date, details, status, name, full_shifts) VALUES (?,?,?,?,?)"
    );
    $statement->execute([$date, $details, $status, $name, 0]);

    // request all active festivals
    $statement = $db->prepare(
        "SELECT * FROM `festivals` ORDER BY date DESC limit 15;"
    );
    $statement->execute([]);
    $res = $statement->fetchAll();

    // return all the festivals
    if ($res) {
        $json = json_encode($res);
        exit($json);
    }

    exit(
        json_encode([
            "status" => 200,
            "error_type" => 0,
        ])
    );
}
//
// get a list of all the festivals, you can select... see code
//
//
elseif ($action == "get_festivals") {
    $xml_dump = file_get_contents("php://input");
    $xml = json_decode($xml_dump, true);
    try {
        $ID = $xml["id"];
        $HASH = $xml["hash"];
        $type = $xml["select"];
        $festi_id = $xml["festi_id"];
    } catch (Exception $e) {
        exit(
            json_encode([
                "status" => 409,
                "error_type" => 4,
                "error_message" =>
                    "Not all fields where available, need: ID, HASH",
            ])
        );
    }
    $query = "";
    // select one specific festival, only admin
    if ($type == "select") {
        $query = "SELECT * FROM festivals WHERE idfestival = ? ;";
        $statement = $db->prepare($query);
        $statement->execute([$festi_id]);
        admin_check($ID, $HASH, $db, false);
    }
    // select all active events, also the hidden once
    elseif ($type == "active") {
        $query =
            "SELECT * FROM festivals WHERE status != 6 and status != 7 ORDER BY date ASC;";
        $statement = $db->prepare($query);
        $statement->execute([]);
        admin_check($ID, $HASH, $db, false);
    }

    // select all active festivals
    elseif ($type == "active_and_open") {
        $query =
            "SELECT * FROM festivals WHERE status != 6 and status != 7 and status != 8 ORDER BY date ASC;";
        $statement = $db->prepare($query);
        $statement->execute([]);
    } else {
        // select last 15 festivals
        $query = "SELECT * FROM `festivals`ORDER BY date DESC limit 25;";
        admin_check($ID, $HASH, $db, false);
        $statement = $db->prepare($query);
        $statement->execute([]);
    }
    // get result
    $res = $statement->fetchAll();

    // return result
    if ($res) {
        $json = json_encode($res);
        exit($json);
    }
    exit(json_encode(json_decode("{}")));
}

//
// This action changes the date of a festival/evenement. IMPORTAND, it does not change the status, this is another api (Because changing the status has a lot of other results)
// This action can only be performed by an administrator
//
elseif ($action == "change_festival_data") {
    // get the contenct from the api body
    $xml_dump = file_get_contents("php://input");
    $xml = json_decode($xml_dump, true);
    try {
        $ID = $xml["id"];
        $HASH = $xml["hash"];
        $date = $xml["date"];
        $name = $xml["festiname"];
        $idfestival = $xml["idfestival"];
        $details = $xml["festival_discription"];
    } catch (Exception $e) {
        exit(
            json_encode([
                "status" => 409,
                "error_type" => 4,
                "error_message" =>
                    "Not all fields where available, need: name, details, status, date, ID, HASH",
            ])
        );
    }
    // this is an admin action, check if this is an admin
    admin_check($ID, $HASH, $db, false);
    // changing the festival data
    $statement = $db->prepare(
        "UPDATE festivals SET date=?, details=?, name=? where idfestival=?;"
    );
    $statement->execute([$date, $details, $name, $idfestival]);
    exit(
        json_encode([
            "status" => 200,
            "error_type" => 0,
        ])
    );
}

//
// This action adds a shift to the festival, this is the middel of the logic (Festivals -> Shifts -> Days)
// This action can only be performed by an administrator
//
elseif ($action == "add_shift") {
    // get the contenct from the api body
    $xml_dump = file_get_contents("php://input");
    $xml = json_decode($xml_dump, true);
    try {
        $ID = $xml["id"];
        $HASH = $xml["hash"];
        $name = $xml["name"];
        $discription = $xml["discription"];
        $needed = $xml["needed"];
        $reserve = $xml["reserve"];
        $length = $xml["length"];
        $festi_id = $xml["festi_id"];
    } catch (Exception $e) {
        exit(
            json_encode([
                "status" => 409,
                "error_type" => 4,
                "error_message" =>
                    "Not all fields where available, need: name, details, status, date, ID, HASH",
            ])
        );
    }
    // this is an admin action, check if this is an admin
    admin_check($ID, $HASH, $db, false);
    // entering the shift in the DB
    $statement = $db->prepare(
        "INSERT INTO shifts (name, datails, length, people_needed, spare_needed, festival_idfestival) VALUES (?,?,?,?,?,?)"
    );
    $statement->execute([
        $name,
        $discription,
        $length,
        $needed,
        $reserve,
        $festi_id,
    ]);

    // select all shift data
    $statement = $db->prepare(
        "SELECT shifts.name,shifts.details,shifts.length,shifts.people_needed,shifts.spare_needed,shifts.festival_idfestival  FROM shifts inner join festivals on shifts.festival_idfestival = festivals.idfestival where festivals.status != 6 or festivals.status != 7;"
    );
    $statement->execute([]);
    $res = $statement->fetchAll();

    // return shift data
    if ($res) {
        $json = json_encode($res);
        exit($json);
    }

    exit(
        json_encode([
            "status" => 200,
            "error_type" => 0,
        ])
    );
}

//
// get a list of all the shifts that are active/open
//
//
elseif ($action == "get_shifts") {
    // Todo => This gives too much data to the frontend.
    $xml_dump = file_get_contents("php://input");
    $xml = json_decode($xml_dump, true);
    try {
        $ID = $xml["id"];
        $HASH = $xml["hash"];
    } catch (Exception $e) {
        exit(
            json_encode([
                "status" => 409,
                "error_type" => 4,
                "error_message" =>
                    "Not all fields where available, need: ID, HASH",
            ])
        );
    }

    admin_check($ID, $HASH, $db, false);
    $statement = $db->prepare(
        'SELECT festivals.status, festivals.name AS "festiname", shifts.idshifts , shifts.name,shifts.datails,shifts.length,shifts.people_needed,shifts.spare_needed,shifts.festival_idfestival  FROM shifts inner join festivals on shifts.festival_idfestival = festivals.idfestival where festivals.status != 6 and festivals.status != 7;'
    );
    $statement->execute();
    $counter = 0;
    while ($row = $statement->fetch(PDO::FETCH_ASSOC)) {
        $statement2 = $db->prepare(
            "select count(distinct users_Id_Users) from work_day inner join shift_days on work_day.shift_days_idshift_days = shift_days.idshift_days where shift_days.shifts_idshifts = ? and work_day.reservation_type != 5 and work_day.reservation_type != 50;"
        );
        $statement2->execute([$row["idshifts"]]);
        $res2 = $statement2->fetchAll();
        $row["subscribed"] = $res2[0]["count(distinct users_Id_Users)"];

        $statement2 = $db->prepare(
            "select count(distinct users_Id_Users) from work_day inner join shift_days on work_day.shift_days_idshift_days = shift_days.idshift_days where shift_days.shifts_idshifts = ? and work_day.reservation_type = 3;"
        );
        $statement2->execute([$row["idshifts"]]);
        $res2 = $statement2->fetchAll();
        $row["subscribed_final"] = $res2[0]["count(distinct users_Id_Users)"];

        $statement3 = $db->prepare(
            "select * from shift_days where 	shifts_idshifts=?"
        );
        $statement3->execute([$row["idshifts"]]);
        $res3 = $statement3->fetchAll();
        $row["work_days"] = count($res3);

        $statement4 = $db->prepare("select * from locations where shift_id=?");
        $statement4->execute([$row["idshifts"]]);
        $res4 = $statement4->fetchAll();
        $row["external_meeting_locations"] = count($res4);

        $res[$counter] = $row;
        $counter++;
    }

    if ($res) {
        $json = json_encode($res);
        exit($json);
    }
    exit(json_encode(json_decode("{}")));
}

//
// get a list of all the shifts with open info
//
//
elseif ($action == "get_shifts_limited") {
    // Todo => This gives too much data to the frontend.
    $xml_dump = file_get_contents("php://input");
    $xml = json_decode($xml_dump, true);
    try {
        $ID = $xml["id"];
        $HASH = $xml["hash"];
    } catch (Exception $e) {
        exit(
            json_encode([
                "status" => 409,
                "error_type" => 4,
                "error_message" =>
                    "Not all fields where available, need: ID, HASH",
            ])
        );
    }

    token_check($ID, $HASH, $db);
    $result = [];
    $statement = $db->prepare(
        'SELECT festivals.status, festivals.name AS "festiname", shifts.idshifts , shifts.name,shifts.datails,shifts.length,shifts.people_needed,shifts.spare_needed,shifts.festival_idfestival  FROM shifts inner join festivals on shifts.festival_idfestival = festivals.idfestival where festivals.status != 6 and festivals.status != 7 and festivals.status != 8;'
    );
    $statement->execute();
    $counter = 0;
    while ($row = $statement->fetch(PDO::FETCH_ASSOC)) {
        $statement2 = $db->prepare(
            "select count(distinct users_Id_Users) from work_day inner join shift_days on work_day.shift_days_idshift_days = shift_days.idshift_days where shift_days.shifts_idshifts = ? and work_day.reservation_type != 5 and work_day.reservation_type != 50;"
        );
        $statement2->execute([$row["idshifts"]]);
        $res2 = $statement2->fetchAll();
        $subscribed = $res2[0]["count(distinct users_Id_Users)"];

        $statement2 = $db->prepare(
            "select count(distinct users_Id_Users) from work_day inner join shift_days on work_day.shift_days_idshift_days = shift_days.idshift_days where shift_days.shifts_idshifts = ? and work_day.reservation_type = 3;"
        );
        $statement2->execute([$row["idshifts"]]);
        $res2 = $statement2->fetchAll();
        $subscribed_final = $res2[0]["count(distinct users_Id_Users)"];

        $statement3 = $db->prepare(
            "select * from shift_days where 	shifts_idshifts=?"
        );
        $statement3->execute([$row["idshifts"]]);
        $res3 = $statement3->fetchAll();
        $work_days = count($res3);

        $statement4 = $db->prepare("select * from locations where shift_id=?");
        $statement4->execute([$row["idshifts"]]);
        $res4 = $statement4->fetchAll();
        $external_meeting_locations = count($res4);

        $result[$counter]["festiname"] = $row["festiname"];
        $result[$counter]["festival_idfestival"] = $row["festival_idfestival"];
        $result[$counter]["idshifts"] = $row["idshifts"];
        $result[$counter]["name"] = $row["name"];
        $result[$counter]["datails"] = $row["datails"];
        $result[$counter]["status"] = $row["status"];
        $result[$counter]["length"] = $row["length"];
        $result[$counter]["work_days"] = $work_days;
        $result[$counter][
            "external_meeting_locations"
        ] = $external_meeting_locations;
        $result[$counter]["is_full"] =
            $row["people_needed"] <= $subscribed_final;
        $result[$counter]["is_completely_full"] =
            $row["people_needed"] + $row["spare_needed"] <= $subscribed;
        $counter++;
    }

    if ($result) {
        $json = json_encode($result);
        exit($json);
    }
    exit(json_encode(json_decode("{}")));
}

//
// get a list of all the shifts
//
//
elseif ($action == "get_shift") {
    $xml_dump = file_get_contents("php://input");
    $xml = json_decode($xml_dump, true);
    try {
        $ID = $xml["id"];
        $HASH = $xml["hash"];
        $shift_id = $xml["idshifts"];
    } catch (Exception $e) {
        exit(
            json_encode([
                "status" => 409,
                "error_type" => 4,
                "error_message" =>
                    "Not all fields where available, need: ID, HASH",
            ])
        );
    }
    token_check($ID, $HASH, $db);
    $statement = $db->prepare("SELECT * FROM shifts where idshifts = ?");
    $statement->execute([$shift_id]);
    $res = $statement->fetchAll();

    if ($res) {
        $json = json_encode($res);
        exit($json);
    } else {
        exit(json_encode(json_decode("{}")));
    }
}

//
// This action changes the date of a festival/evenement shift. IMPORTAND, it does not change the status, this is another api (Because changing the status has a lot of other results)
// This action can only be performed by an administrator
//
elseif ($action == "change_shift") {
    // get the contenct from the api body
    $xml_dump = file_get_contents("php://input");
    $xml = json_decode($xml_dump, true);
    try {
        $ID = $xml["id"];
        $HASH = $xml["hash"];
        $name = $xml["name"];
        $details = $xml["details"];
        $people = $xml["people"];
        $reserve = $xml["reserve"];
        $days = $xml["days"];
        $idshifts = $xml["idshifts"];
    } catch (Exception $e) {
        exit(
            json_encode([
                "status" => 409,
                "error_type" => 4,
                "error_message" =>
                    "Not all fields where available, need: name, details, status, date, ID, HASH",
            ])
        );
    }
    // this is an admin action, check if this is an admin
    admin_check($ID, $HASH, $db, false);
    // changing the festival data
    $statement = $db->prepare(
        "UPDATE shifts SET name=?, datails=?, people_needed=? , spare_needed=? , length=? WHERE idshifts=?;"
    );
    $statement->execute([$name, $details, $people, $reserve, $days, $idshifts]);

    exit(
        json_encode([
            "status" => 200,
            "error_type" => 0,
        ])
    );
}
//
// This action deletes a shift, this can only happen when no user are connected to the shift, these users need te be deleted before this action can take place
// This is an admin action, only an admin can perform this
//
elseif ($action == "delete_shift") {
    // get the contenct from the api body
    $xml_dump = file_get_contents("php://input");
    $xml = json_decode($xml_dump, true);
    try {
        $ID = $xml["id"];
        $HASH = $xml["hash"];
        $idshifts = $xml["idshifts"];
    } catch (Exception $e) {
        exit(
            json_encode([
                "status" => 409,
                "error_type" => 4,
                "error_message" =>
                    "Not all fields where available, need: name, details, status, date, ID, HASH",
            ])
        );
    }
    // this is an admin action, check if this is an admin
    admin_check($ID, $HASH, $db, false);

    $statement = $db->prepare(
        "SELECT * FROM work_day where shift_days_idshift_days = ?"
    );
    $statement->execute([$idshifts]);
    $res = $statement->fetchAll();

    if ($res) {
        exit(
            json_encode([
                "status" => 409,
                "error_type" => 10,
                "error_message" =>
                    "one or more people are registered in this shift, you have to delete them first",
            ])
        );
    } else {
        $statement = $db->prepare("DELETE FROM shifts WHERE idshifts=?;");
        $statement->execute([$idshifts]);
        exit(
            json_encode([
                "status" => 200,
                "error_type" => 0,
            ])
        );
    }
}
//
// This action adds a shift to the shift day, this is the logic (Festivals -> Shifts -> Days)
// This action can only be performed by an administrator
//
elseif ($action == "add_shift_day") {
    // get the contenct from the api body
    $xml_dump = file_get_contents("php://input");
    $xml = json_decode($xml_dump, true);
    try {
        $ID = $xml["id"];
        $HASH = $xml["hash"];

        $start = $xml["start"];
        $stop = $xml["stop"];
        $length = $xml["length"];
        $money = $xml["money"];
        $shifts_idshifts = $xml["shifts_idshifts"];
    } catch (Exception $e) {
        exit(
            json_encode([
                "status" => 409,
                "error_type" => 4,
                "error_message" =>
                    "Not all fields where available, need: name, details, status, date, ID, HASH",
            ])
        );
    }
    // this is an admin action, check if this is an admin
    admin_check($ID, $HASH, $db, false);
    // entering the complaint in the DB
    $statement = $db->prepare(
        "INSERT INTO shift_days (cost, start_date, shift_end, length, shifts_idshifts) VALUES (?,?,?,?,?)"
    );
    $statement->execute([$money, $start, $stop, $length, $shifts_idshifts]);

    exit(
        json_encode([
            "status" => 200,
            "error_type" => 0,
        ])
    );
}
//
// returns a list of al the shift days available for only active festivals.
//
//
elseif ($action == "get_shift_days_admin") {
    // get the contenct from the api body
    $xml_dump = file_get_contents("php://input");
    $xml = json_decode($xml_dump, true);
    try {
        $ID = $xml["id"];
        $HASH = $xml["hash"];
    } catch (Exception $e) {
        exit(
            json_encode([
                "status" => 409,
                "error_type" => 4,
                "error_message" =>
                    "Not all fields where available, need: ID, HASH",
            ])
        );
    }
    // this is an admin action, check if this is an admin
    admin_check($ID, $HASH, $db, false);
    $statement = $db->prepare(
        "SELECT festivals.idfestival, festivals.status, shifts.idshifts, shift_days.cost, shift_days.idshift_days, shift_days.shift_end, shift_days.start_date, shifts.name FROM shift_days inner join shifts on shifts.idshifts = shift_days.shifts_idshifts inner join festivals on festivals.idfestival = shifts.festival_idfestival where festivals.status != 6 AND festivals.status != 7;"
    );
    $statement->execute([]);
    $counter = 0;
    while ($row = $statement->fetch(PDO::FETCH_ASSOC)) {
        $statement3 = $db->prepare(
            "select * from work_day where work_day.shift_days_idshift_days = ?"
        );
        $statement3->execute([$row["idshift_days"]]);
        $res3 = $statement3->fetchAll();
        $row["users_total"] = count($res3);
        $res[$counter] = $row;
        $counter++;
    }

    if ($res) {
        $json = json_encode($res);
        exit($json);
    } else {
        exit(json_encode(json_decode("{}")));
    }
} elseif ($action == "get_shift_days") {
    // get the contenct from the api body
    $xml_dump = file_get_contents("php://input");
    $xml = json_decode($xml_dump, true);
    try {
        $ID = $xml["id"];
        $HASH = $xml["hash"];
    } catch (Exception $e) {
        exit(
            json_encode([
                "status" => 409,
                "error_type" => 4,
                "error_message" =>
                    "Not all fields where available, need: ID, HASH",
            ])
        );
    }
    // this is an admin action, check if this is an admin
    token_check($ID, $HASH, $db);
    $statement = $db->prepare(
        "SELECT festivals.idfestival, festivals.status, shifts.idshifts, shift_days.cost, shift_days.idshift_days, shift_days.shift_end, shift_days.start_date, shifts.name FROM shift_days inner join shifts on shifts.idshifts = shift_days.shifts_idshifts inner join festivals on festivals.idfestival = shifts.festival_idfestival where festivals.status != 6 AND festivals.status != 7;"
    );
    $statement->execute([]);
    $res = $statement->fetchAll();
    if ($res) {
        $json = json_encode($res);
        exit($json);
    } else {
        exit(json_encode(json_decode("{}")));
    }
}
//
// returns all information about one shift day
//
//
elseif ($action == "get_shift_day") {
    // get the contenct from the api body
    $xml_dump = file_get_contents("php://input");
    $xml = json_decode($xml_dump, true);
    try {
        $ID = $xml["id"];
        $HASH = $xml["hash"];
        $shift_days_id = $xml["shift_day_id"];
    } catch (Exception $e) {
        exit(
            json_encode([
                "status" => 409,
                "error_type" => 4,
                "error_message" =>
                    "Not all fields where available, need: name, details, status, date, ID, HASH",
            ])
        );
    }
    // this is an admin action, check if this is an admin
    token_check($ID, $HASH, $db);
    $statement = $db->prepare("SELECT * FROM shift_days WHERE idshift_days=? ");
    $statement->execute([$shift_days_id]);
    $res = $statement->fetchAll();
    if ($res) {
        $json = json_encode($res);
        exit($json);
    } else {
        exit(json_encode(json_decode("{}")));
    }
}

//
// This action changes the date of a festival/evenement shift day. IMPORTAND, it does not change the status, this is another api (Because changing the status has a lot of other results)
// This action can only be performed by an administrator
//
elseif ($action == "change_shift_day") {
    // get the contenct from the api body
    $xml_dump = file_get_contents("php://input");
    $xml = json_decode($xml_dump, true);
    try {
        $ID = $xml["id"];
        $HASH = $xml["hash"];
        $start = $xml["start"];
        $stop = $xml["stop"];
        $money = $xml["money"];
        $shift_days_id = $xml["shift_day_id"];
    } catch (Exception $e) {
        exit(
            json_encode([
                "status" => 409,
                "error_type" => 4,
                "error_message" =>
                    "Not all fields where available, need: name, details, status, date, ID, HASH",
            ])
        );
    }
    // this is an admin action, check if this is an admin
    admin_check($ID, $HASH, $db, false);
    // changing the festival data
    $statement = $db->prepare(
        "UPDATE shift_days SET cost=?, start_date=?, shift_end=? WHERE idshift_days=?;"
    );
    $statement->execute([$money, $start, $stop, $shift_days_id]);
    exit(
        json_encode([
            "status" => 200,
            "error_type" => 0,
        ])
    );
}

//
// This action deletes a shift, this can only happen when no user are connected to the shift, these users need te be deleted before this action can take place
// This is an admin action, only an admin can perform this
//
elseif ($action == "delete_shift_day") {
    // get the contenct from the api body
    $xml_dump = file_get_contents("php://input");
    $xml = json_decode($xml_dump, true);
    try {
        $ID = $xml["id"];
        $HASH = $xml["hash"];
        $shift_day_id = $xml["shift_day_id"];
    } catch (Exception $e) {
        exit(
            json_encode([
                "status" => 409,
                "error_type" => 4,
                "error_message" =>
                    "Not all fields where available, need: name, details, status, date, ID, HASH",
            ])
        );
    }
    // this is an admin action, check if this is an admin
    admin_check($ID, $HASH, $db, false);

    $statement = $db->prepare(
        "SELECT * FROM work_day where shift_days_idshift_days = ?"
    );
    $statement->execute([$shift_day_id]);
    $res = $statement->fetchAll();

    if ($res) {
        exit(
            json_encode([
                "status" => 409,
                "error_type" => 10,
                "error_message" =>
                    "one or more people are registered in this shift, you have to delete them first",
            ])
        );
    } else {
        $statement = $db->prepare(
            "DELETE FROM shift_days WHERE idshift_days=?;"
        );
        $statement->execute([$shift_day_id]);
        exit(
            json_encode([
                "status" => 200,
                "error_type" => 0,
            ])
        );
    }
}
//
// get all workdays for the user that is doing the api, this prevents leaking information from other users
//
//
elseif ($action == "shift_work_days") {
    // get the contenct from the api body
    $xml_dump = file_get_contents("php://input");
    $xml = json_decode($xml_dump, true);
    try {
        $ID = $xml["id"];
        $HASH = $xml["hash"];
    } catch (Exception $e) {
        exit(
            json_encode([
                "status" => 409,
                "error_type" => 4,
                "error_message" =>
                    "Not all fields where available, need: name, details, status, date, ID, HASH",
            ])
        );
    }
    token_check($ID, $HASH, $db);
    $statement = $db->prepare(
        "select * from Images where users_Id_Users =? and is_primary = 1"
    );
    $statement->execute([$ID]);
    $res = $statement->fetchAll();
    if (count($res) == 0) {
        exit(
            json_encode([
                "status" => 409,
                "error_type" => 8,
                "error_message" => "profile picture is needed",
            ])
        );
    }

    $statement = $db->prepare(
        "SELECT reservation_type, idshifts, friend FROM work_day INNER JOIN shift_days ON work_day.shift_days_idshift_days = shift_days.idshift_days INNER JOIN shifts ON shift_days.shifts_idshifts = shifts.idshifts INNER JOIN festivals on festivals.idfestival = shifts.festival_idfestival where work_day.users_Id_Users = ? AND festivals.status != 6 AND festivals.status != 7"
    );
    $statement->execute([$ID]);
    $res = $statement->fetchAll();

    if ($res) {
        $json = json_encode($res);
        exit($json);
    } else {
        exit(json_encode(json_decode("{}")));
    }
}

//
// subscribe user to an evenement. It :
// -> Checks the festival status and subscribes the user to it with the correct status
// -> checks if the shift is not full yet
// -> send mail
//
elseif ($action == "user_subscribe") {
    // get the contenct from the api body
    $xml_dump = file_get_contents("php://input");
    $xml = json_decode($xml_dump, true);
    $people_subscribed = 0;
    $overrule = false;
    try {
        $ID = $xml["id"];
        $HASH = $xml["hash"];
        $shift_id = $xml["idshifts"];
        $Id_Users = $xml["Id_Users"];
		$reserve_override = $xml["reserve_override"];
    } catch (Exception $e) {
        exit(
            json_encode([
                "status" => 409,
                "error_type" => 4,
                "error_message" =>
                    "Not all fields where available, need: name, details, status, date, ID, HASH",
            ])
        );
    }
    if ($Id_Users == "admin") {
        $Id_Users = $ID;
        $overrule = true;
    }
    token_check($ID, $HASH, $db);

    $statement = $db->prepare(
        "SELECT * FROM users_data WHERE users_Id_Users = ?"
    );
    $statement->execute([$ID]);
    $res = $statement->fetch(PDO::FETCH_ASSOC);

    if (!$res) {
        exit(
            json_encode([
                "status" => 409,
                "error_type" => 8,
                "errpr_message" => "no info found, fill in user data",
            ])
        );
    }
    //check if is blocked
    if ($res["blocked"] == "1") {
        exit(
            json_encode([
                "status" => 500,
                "error_type" => 9,
                "errpr_message" => "User is blocked",
            ])
        );
    }

    $statement = $db->prepare(
        "delete s.* from work_day s inner join shift_days w on w.idshift_days = s.shift_days_idshift_days where s.users_Id_Users = ? and w.shifts_idshifts = ?; "
    );
    $statement->execute([$Id_Users, $shift_id]);

    $statement = $db->prepare(
        "SELECT festivals.status, festivals.name FROM festivals INNER JOIN shifts on festivals.idfestival = shifts.festival_idfestival WHERE shifts.idshifts = ?;"
    );
    $statement->execute([$shift_id]);
    $res = $statement->fetchAll();
    $status = $res[0]["status"];
    $festival_name = $res[0]["name"];
    if (
        ($status == 0 || $status == 2 || $status == 3) &&
        $ID == $Id_Users &&
        !$overrule
    ) {
        //the user can subscribe
        token_check($ID, $HASH, $db);
        $statement2 = $db->prepare(
            "select count(distinct users_Id_Users) from work_day inner join shift_days on work_day.shift_days_idshift_days = shift_days.idshift_days where shift_days.shifts_idshifts = ? and work_day.reservation_type !=50;;"
        );
        $statement2->execute([$shift_id]);
        $res2 = $statement2->fetchAll();
        $people_subscribed = $res2[0]["count(distinct users_Id_Users)"];
    } else {
        // the user cannot subscribe because the festival is closed OR he is subscribing another user, the admin can however do anything he wants
        admin_check($ID, $HASH, $db, false);
        $status = 3;
		if($reserve_override == "1"){
			$status = 99;
		}
    }

    $statement = $db->prepare(
        "select idshift_days, start_date, shift_end, cost, people_needed, spare_needed from shift_days INNER JOIN shifts ON shifts.idshifts = shift_days.shifts_idshifts where shifts.idshifts = ?;"
    );
    $statement->execute([$shift_id]);
    $res = $statement->fetchAll();
    $shift_info = "";
    $people_needed = $res[0]["people_needed"];
    $reserve_needed = $res[0]["spare_needed"];

    if ($people_needed + $reserve_needed <= $people_subscribed) {
        exit(
            json_encode([
                "status" => 400,
                "error_type" => 11,
                "error_message" => "This shift is full",
            ])
        );
    }
    if ($people_needed <= $people_subscribed) {
        $status = 99;
    }
    foreach ($res as &$shift) {
        $statement = $db->prepare(
            "INSERT INTO work_day (reservation_type, shift_days_idshift_days, users_Id_Users) VALUES (?,?,?);"
        );
        $statement->execute([$status, $shift["idshift_days"], $Id_Users]);
        $shift_info .=
            "<p>Van " .
            $shift["start_date"] .
            " tot " .
            $shift["shift_end"] .
            " voor " .
            $shift["cost"] .
            " euro </p>";
    }

    // mail the user!
    $statement = $db->prepare("SELECT email from users where Id_Users = ?");
    $statement->execute([$Id_Users]);
    $res = $statement->fetchAll();
    $email = $res[0]["email"];

    if ($status == 2) {
        $notification_text =
            "Je bent nu geregistreerd voor " .
            $festival_name .
            ". Wacht je definitieve inschrijving af.";
        $statement = $db->prepare(
            "INSERT INTO notifications (notification, global,user_id) VALUES (?,?,?);"
        );
        $statement->execute([$notification_text, 0, $Id_Users]);

        $subject = "All-Round Events: Registratie voor " . $festival_name;
        $message =
            '<html>
							<p>Beste,</p>
							<p>Je bent geregisteerd om deel te nemen aan ' .
            $festival_name .
            '. </br></p>
							<p> Je ben voor volgende shift geregisteerd:</p>
							' .
            $shift_info .
            "<p>We kijken uit naar een leuke en vlotte samenwerking!</p>
							<p></p>
							<p><strong>Opgelet!! Je bent nog niet ingeschreven, enkel geregisteerd! Je ontvangt een mail als je registratie wordt verwerkt. </strong></p>
							<p></p>
							<p>Met vriendelijke groeten</p>
							<p><small>
								All Round Events VZW
								Meester Van Der Borghtstraat 10
								2580 Putte
								BTW: BE 0886 674 723
								IBAN: BE68 7310 4460 6534
								RPR Mechelen" .
            "</small></html>";
        $headers =
            "From: inschrijvingen@all-round-events.be" .
            "\r\n" .
            "Reply-To: info@all-roundevents.be" .
            "\r\n" .
            "Content-type:text/html;charset=UTF-8" .
            "\r\n" .
            "X-Mailer: PHP/" .
            phpversion();
        add_to_mail_queue($db, $email, $subject, $message, $headers, 2);
    }
    if ($status == 3) {
        $notification_text =
            "Ja bent nu ingeschreven voor " . $festival_name . ". Tot dan!";
        $statement = $db->prepare(
            "INSERT INTO notifications (notification, global,user_id) VALUES (?,?,?);"
        );
        $statement->execute([$notification_text, 0, $Id_Users]);
        $subject =
            "All-Round Events: Inschrijving bevestigd voor " . $festival_name;
        $message =
            '<html>
							<p>Beste,</p>
							<p>Je bent ingeschreven om te komen werken op ' .
            $festival_name .
            '. </br></p>
							<p> Je wordt op volgende momenten verwacht.</p>
							' .
            $shift_info .
            "<p></p>
							<p>Alvast enorm bedankt dat jij deel wilt uitmaken van ons team! </p>
							<p></p>
							<p>Met vriendelijke groeten</p>
							<p><small>
								All Round Events VZW
								Meester Van Der Borghtstraat 10
								2580 Putte
								BTW: BE 0886 674 723
								IBAN: BE68 7310 4460 6534
								RPR Mechelen</small></p>" .
            "</html>";
        $headers =
            "From: inschrijvingen@all-round-events.be" .
            "\r\n" .
            "Reply-To: info@all-roundevents.be" .
            "\r\n" .
            "Content-type:text/html;charset=UTF-8" .
            "\r\n" .
            "X-Mailer: PHP/" .
            phpversion();
        add_to_mail_queue($db, $email, $subject, $message, $headers, 2);
    }
    if ($status == 99) {
        exit(
            json_encode([
                "status" => 550,
                "error_type" => 0,
                "error_message" => "Added to reserved capacity.",
            ])
        );
    }

    exit(
        json_encode([
            "status" => 200,
            "error_type" => 0,
            "error_message" => "None",
        ])
    );
} elseif ($action == "user_unsubscribe") {
    // get the contenct from the api body
    //Todo: Send mail with info!
    $xml_dump = file_get_contents("php://input");
    $xml = json_decode($xml_dump, true);
    try {
        $ID = $xml["id"];
        $HASH = $xml["hash"];
        $shift_id = $xml["idshifts"];
        $Id_Users = $xml["Id_Users"];
        //TODO: add type so the admin can add specifc type
    } catch (Exception $e) {
        exit(
            json_encode([
                "status" => 409,
                "error_type" => 4,
                "error_message" =>
                    "Not all fields where available, need: name, details, status, date, ID, HASH",
            ])
        );
    }

    $statement = $db->prepare(
        "SELECT festivals.status, festivals.name FROM festivals INNER JOIN shifts on festivals.idfestival = shifts.festival_idfestival WHERE shifts.idshifts = ?;"
    );
    $statement->execute([$shift_id]);
    $res = $statement->fetchAll();
    $status = $res[0]["status"];
    $festival_name = $res[0]["name"];

    // get the user email
    $statement = $db->prepare("SELECT email from users where Id_Users = ?");
    $statement->execute([$Id_Users]);
    $res = $statement->fetchAll();
    $email = $res[0]["email"];

	// get the shift name and shift day data for mail content
    $statement = $db->prepare(
        "select idshift_days, start_date, shift_end, cost  from shift_days INNER JOIN shifts ON shifts.idshifts = shift_days.shifts_idshifts where shifts.idshifts = ?;"
    );
    $statement->execute([$shift_id]);
    $res = $statement->fetchAll();
	$shiftname = $res[0]["name"];
    $shift_info = "";

    foreach ($res as &$shift) {
        $shift_info .=
            "<p>Van " .
            $shift["start_date"] .
            " tot " .
            $shift["shift_end"] .
            " voor " .
            $shift["cost"] .
            "euro </p>";
    }
	
	//check what the current user status is
	$statement = $db->prepare("select work_day.reservation_type from work_day inner join shift_days on shift_days.idshift_days = work_day.shift_days_idshift_days where work_day.users_Id_Users = ? and shift_days.shifts_idshifts = ?;");
	$statement->execute([$Id_Users, $shift_id]);
	$res = $statement->fetchAll();
	$current_status = $res[0]["reservation_type"];

    if ($ID == $Id_Users && $status != "0") {
        token_check($ID, $HASH, $db);
        $notification_text =
            "Ja bent nu uitgeschreven voor " .
            $festival_name .
            " in shift " .
            $shiftname .
            " . Hopelijk tot een volgende keer!";
        $statement = $db->prepare(
            "INSERT INTO notifications (notification, global,user_id) VALUES (?,?,?);"
        );
        $statement->execute([$notification_text, 0, $Id_Users]);
        $subject = "All-Round Events: Uitgeschreven voor " . $festival_name;
        $message =
            '<html>
								<p>Beste,</p>
								<p>Je hebt jezelf uitgeschreven voor festival ' .
            $festival_name .
            '. </br></p>
								<p> Je bent uitgeschreven voor volgende dagen:</p>
								' .
            $shift_info .
            "<p></p>
								<p>Alvast bedankt om ons te verwittigen! </p>
								<p></p>
								<p>Met vriendelijke groeten</p>
								<p><small>
									All Round Events VZW
									Meester Van Der Borghtstraat 10
									2580 Putte
									BTW: BE 0886 674 723
									IBAN: BE68 7310 4460 6534
									RPR Mechelen</small></p>" .
            "</html>";
        $headers =
            "From: inschrijvingen@all-round-events.be" .
            "\r\n" .
            "Reply-To: info@all-roundevents.be" .
            "\r\n" .
            "Content-type:text/html;charset=UTF-8" .
            "\r\n" .
            "X-Mailer: PHP/" .
            phpversion();
        add_to_mail_queue($db, $email, $subject, $message, $headers, 2);
		
		// update workday status to 50 
		if($current_status == 3){
			$statement = $db->prepare("update work_day inner join shift_days on shift_days.idshift_days = work_day.shift_days_idshift_days set work_day.reservation_type=50 where work_day.users_Id_Users = ? and shift_days.shifts_idshifts = ?;");
			$statement->execute([$Id_Users, $shift_id]);
		}
		else {
			$statement = $db->prepare(
			"delete s.* from work_day s inner join shift_days w on w.idshift_days = s.shift_days_idshift_days where s.users_Id_Users = ? and w.shifts_idshifts = ?; "
			);
			$statement->execute([$Id_Users, $shift_id]);

			$statement = $db->prepare(
				"delete external_appointment from external_appointment inner join locations on locations.location_id = external_appointment.location_id where external_appointment.user_id=? and locations.shift_id = ?"
			);
			$statement->execute([$Id_Users, $shift_id]);
		}
		
    } elseif ($status != "0") {
        admin_check($ID, $HASH, $db, false);
		
		// check if workday status is 50, in that case we don't send mail
		
		
		
		
        $notification_text =
            "Je zal jammer genoeg niet kunnen deelnemen aan  " .
            $festival_name .
            " in shift " .
            $shift_info .
            ". Er komen snel andere evenementen! Hou je app in de gaten!";
        $statement = $db->prepare(
            "INSERT INTO notifications (notification, global,user_id) VALUES (?,?,?);"
        );
        $statement->execute([$notification_text, 0, $Id_Users]);
        $subject = "All-Round Events: Uitgeschreven voor  " . $festival_name;
        $message =
            '<html>
								<p>Beste,</p>
								<p>Helaas zal je niet kunnen deelnemen aan  ' .
            $festival_name .
            '. </br></p>
								<p> Je had jezelf opgegeven voor volgende dagen: :</p>
								' .
            $shift_info .
            "<p></p>
								<p>Wellicht waren er al voldoende mensen ingeschreven voor dit evenement.</p>
								<p></p>
								<p>Met vriendelijke groeten en hopelijk tot een andere keer!</p>
								<p><small>
									All Round Events VZW
									Meester Van Der Borghtstraat 10
									2580 Putte
									BTW: BE 0886 674 723
									IBAN: BE68 7310 4460 6534
									RPR Mechelen</small></p>" .
            "</html>";
        $headers =
            "From: inschrijvingen@all-round-events.be" .
            "\r\n" .
            "Reply-To: info@all-roundevents.be" .
            "\r\n" .
            "Content-type:text/html;charset=UTF-8" .
            "\r\n" .
            "X-Mailer: PHP/" .
            phpversion();
		if($current_status != 50){
			add_to_mail_queue($db, $email, $subject, $message, $headers, 2);
		}
		// remove all 
	    $statement = $db->prepare(
        "delete s.* from work_day s inner join shift_days w on w.idshift_days = s.shift_days_idshift_days where s.users_Id_Users = ? and w.shifts_idshifts = ?; "
		);
		$statement->execute([$Id_Users, $shift_id]);

		$statement = $db->prepare(
			"delete external_appointment from external_appointment inner join locations on locations.location_id = external_appointment.location_id where external_appointment.user_id=? and locations.shift_id = ?"
		);
		$statement->execute([$Id_Users, $shift_id]);
    }

    exit(
        json_encode([
            "status" => 200,
            "error_type" => 0,
            "error_message" => "None",
        ])
    );
} elseif ($action == "get_subscribers") {
    // get the contenct from the api body
    $xml_dump = file_get_contents("php://input");
    $xml = json_decode($xml_dump, true);
    try {
        $ID = $xml["id"];
        $HASH = $xml["hash"];
    } catch (Exception $e) {
        exit(
            json_encode([
                "status" => 409,
                "error_type" => 4,
                "error_message" =>
                    "Not all fields where available, need: name, details, status, date, ID, HASH",
            ])
        );
    }
    admin_check($ID, $HASH, $db, false);
    $statement = $db->prepare(
        "select friend, work_day.users_Id_Users, users_data.name, shifts_idshifts, reservation_type, idwork_day, picture_name from work_day inner join shift_days on shift_days.idshift_days = work_day.shift_days_idshift_days inner join users_data on users_data.users_Id_Users = work_day.users_Id_Users inner join Images on (Images.users_Id_Users = work_day.users_Id_Users and Images.is_primary = 1) inner join shifts on shifts.idshifts = shift_days.shifts_idshifts inner join festivals on shifts.festival_idfestival = festivals.idfestival where  festivals.status != 6 and festivals.status != 7 GROUP BY work_day.users_Id_Users,shifts_idshifts  order by idwork_day;"
    );
    $statement->execute([]);
    $res = $statement->fetchAll();
    if ($res) {
        $json = json_encode($res);
        exit($json);
    } else {
        exit(json_encode(json_decode("{}")));
    }
} elseif ($action == "get_workdays_subscribers") {
    // get the contenct from the api body
    $xml_dump = file_get_contents("php://input");
    $xml = json_decode($xml_dump, true);
    try {
        $ID = $xml["id"];
        $HASH = $xml["hash"];
    } catch (Exception $e) {
        exit(
            json_encode([
                "status" => 409,
                "error_type" => 4,
                "error_message" =>
                    "Not all fields where available, need: name, details, status, date, ID, HASH",
            ])
        );
    }
    admin_check($ID, $HASH, $db, false);
    $statement = $db->prepare(
        "select work_day.reservation_type ,work_day.shift_days_idshift_days, work_day.users_Id_Users,work_day.in, work_day.out, work_day.present, users_data.telephone, users_data.name, shifts_idshifts, reservation_type, idwork_day, picture_name, friend from work_day inner join shift_days on shift_days.idshift_days = work_day.shift_days_idshift_days inner join users_data on users_data.users_Id_Users = work_day.users_Id_Users inner join Images on (Images.users_Id_Users = work_day.users_Id_Users and Images.is_primary = 1) inner join shifts on shifts.idshifts = shift_days.shifts_idshifts inner join festivals on shifts.festival_idfestival = festivals.idfestival where  festivals.status != 6 and festivals.status != 7 order by idwork_day;"
    );
    $statement->execute([]);
    $res = $statement->fetchAll();
    if ($res) {
        $json = json_encode($res);
        exit($json);
    } else {
        exit(json_encode(json_decode("{}")));
    }
} elseif ($action == "user_search") {
    // get the contenct from the api body
    $xml_dump = file_get_contents("php://input");
    $xml = json_decode($xml_dump, true);
    try {
        $ID = $xml["id"];
        $HASH = $xml["hash"];
        $search = $xml["search"];
    } catch (Exception $e) {
        exit(
            json_encode([
                "status" => 409,
                "error_type" => 4,
                "error_message" =>
                    "Not all fields where available, need: name, details, status, date, ID, HASH",
            ])
        );
    }
    admin_check($ID, $HASH, $db, false);
    $statement = $db->prepare(
        "select  picture_name, name,id_users as users_Id_Users,  size, date_of_birth, gender, adres_line_one, adres_line_two, driver_license, nationality, text, telephone, marital_state, employment, remarks, blocked, email from users_data INNER JOIN users on users.Id_Users = users_data.users_Id_Users inner join Images on (Images.users_Id_Users = users_data.users_Id_Users and Images.is_primary = 1) where name like ? limit 10;"
    );
    $statement->execute(["%" . $search . "%"]);
    $res = $statement->fetchAll();
    if ($res) {
        $json = json_encode($res);
        exit($json);
    } else {
        exit(json_encode(json_decode("{}")));
    }
} elseif ($action == "change_festival_status") {
    $xml_dump = file_get_contents("php://input");
    $xml = json_decode($xml_dump, true);
    try {
        $ID = $xml["id"];
        $HASH = $xml["hash"];
        $festi = $xml["festival_id"];
        $status = $xml["status"];
    } catch (Exception $e) {
        exit(
            json_encode([
                "status" => 409,
                "error_type" => 4,
                "error_message" =>
                    "Not all fields where available, need: name, details, status, date, ID, HASH",
            ])
        );
    }
    admin_check($ID, $HASH, $db, false);
    $statement = $db->prepare("select * from festivals where idfestival = ?;");
    $statement->execute([$festi]);
    $res = $statement->fetchAll();
    $festival_name = $res[0]["name"];
    $festi_id = $res[0]["idfestival"];
    if ($res[0]["status"] == $status) {
        exit(
            json_encode([
                "status" => 200,
                "error_type" => -1,
                "error_message" => "Updating was not needed",
            ])
        );
    }
    $statement = $db->prepare(
        "update festivals set status = ? where idfestival=?"
    );
    $statement->execute([$status, $festi]);
} elseif ($action == "festival_status_mail") {
    $xml_dump = file_get_contents("php://input");
    $xml = json_decode($xml_dump, true);
    try {
        $ID = $xml["id"];
        $HASH = $xml["hash"];
        $festi = $xml["festival_id"];
        $status = $xml["status"];
    } catch (Exception $e) {
        exit(
            json_encode([
                "status" => 409,
                "error_type" => 4,
                "error_message" =>
                    "Not all fields where available, need: name, details, status, date, ID, HASH",
            ])
        );
    }
    admin_check($ID, $HASH, $db, false);
    $statement = $db->prepare("select * from festivals where idfestival = ?;");
    $statement->execute([$festi]);
    $res = $statement->fetchAll();
    $festival_name = $res[0]["name"];
    $festi_id = $res[0]["idfestival"];
    $status = $res[0]["status"];

    if ($status == 0) {
    }
    if ($status == 1) {
        $notification_text =
            "Jawel,  " .
            $festival_name .
            " komt er binnenkort aan! Hou de inschrijvingspagina goed in de gaten! ";
        $statement = $db->prepare(
            "INSERT INTO notifications (notification, global,user_id) VALUES (?,?,?);"
        );
        $statement->execute([$notification_text, 1, -1]);
    }

    if ($status == 2) {
        // mail to everyone that the event is now open in register
        $notification_text =
            "Je kan je registeren voor " .
            $festival_name .
            ", registreer je snel om erbij te kunnen zijn!";
        $statement = $db->prepare(
            "INSERT INTO notifications (notification, global,user_id) VALUES (?,?,?);"
        );
        $statement->execute([$notification_text, 1, -1]);

        $statement = $db->prepare(
            "SELECT email FROM users where subscribed = 1;"
        );
        $statement->execute([]);
        $res = $statement->fetchAll();
        foreach ($res as &$line) {
            $email = $line["email"];
            $subject =
                "All-Round Events: Registratie open voor  " . $festival_name;
            $message =
                '<html>
								<p>Beste,</p>
								<p>Vanaf nu kan je jezelf registeren voor  ' .
                $festival_name .
                '. </br></p>

								<p>Ga naar onze website en registreer je voor je gewenste shift, je kan dit doen met de volgende link: </p>
								<p>https://all-round-events.be/html/nl/inschrijven.html</p>
								<p> </p>
								<p>Opgelet, registeren betekent niet dat je ingeschreven bent. Je zal zo snel mogelijk een mail ontvangen met je definitieve inschrijving! Kijk zeker je gegevens na voor je je inschrijft voor dit evenement.</p>
								<p>Veel succes en hopelijk tot snel!</p>
								<p><small>
									All Round Events VZW
									Meester Van Der Borghtstraat 10
									2580 Putte
									BTW: BE 0886 674 723
									IBAN: BE68 7310 4460 6534
									RPR Mechelen</small></p></br>
									<small>Geen mails meer ontvangen? Verwijderd jezelf op de mailinglijst op de website.</small>" 
							</html>';
            $headers =
                "From: aankondigen@all-round-events.be" .
                "\r\n" .
                "Reply-To: info@all-roundevents.be " .
                "\r\n" .
                "Content-type:text/html;charset=UTF-8" .
                "\r\n" .
                "X-Mailer: PHP/" .
                phpversion();
            add_to_mail_queue($db, $email, $subject, $message, $headers, 3);
        }
        exit(json_encode(json_decode("{}")));
    }
    if ($status == 3) {
        // mail to everyone that the event is now open in subscription mode
        $notification_text =
            "Je kan je inschrijven voor " .
            $festival_name .
            ", registreer je snel om erbij te kunnen zijn!";
        $statement = $db->prepare(
            "INSERT INTO notifications (notification, global,user_id) VALUES (?,?,?);"
        );
        $statement->execute([$notification_text, 1, -1]);

        $statement = $db->prepare(
            "SELECT email FROM users where subscribed = 1;"
        );
        $statement->execute([]);
        $res = $statement->fetchAll();
        foreach ($res as &$line) {
            $email = $line["email"];
            $subject =
                "All-Round Events: Registratie open voor  " . $festival_name;
            $message =
                '<html>
								<p>Beste,</p>
								<p>Vanaf vandaag kan je jezelf inschrijven voor  ' .
                $festival_name .
                '. </br></p>

								<p>Ga naar de website en schrijf je in voor je gewenste shift, je kan dit doen met de volgende link: </p>
								<p>https://all-round-events.be/html/nl/inschrijven.html</p>
								<p> </p>
								<p>Veel succes en hopelijk tot snel</p>
								<p><small>
									All Round Events VZW
									Meester Van Der Borghtstraat 10
									2580 Putte
									BTW: BE 0886 674 723
									IBAN: BE68 7310 4460 6534
									RPR Mechelen</small></p></br>
									<small>Geen mails meer ontvangen? Verwijderd jezelf op de mailinglijst op de website.</small> 
							</html>';
            $headers =
                "From: aankondigen@all-round-events.be" .
                "\r\n" .
                "Reply-To: info@all-roundevents.be" .
                "\r\n" .
                "Content-type:text/html;charset=UTF-8" .
                "\r\n" .
                "X-Mailer: PHP/" .
                phpversion();

            add_to_mail_queue($db, $email, $subject, $message, $headers, 3);
        }
    }
    if ($status == 4) {
        // nothing sould be happening
    }
    if ($status == 5) {
        // mail is send to all the user that payout will be hapening
        $statement = $db->prepare(
            "SELECT email FROM users inner join work_day on work_day.users_Id_Users = users.Id_Users inner join shift_days on shift_days.idshift_days = work_day.shift_days_idshift_days inner join shifts on shifts.idshifts = shift_days.shifts_idshifts inner join festivals on festivals.idfestival = shifts.festival_idfestival where subscribed = 1 and festivals.idfestival = ? group by email;"
        );
        $statement->execute([$festi_id]);
        $res = $statement->fetchAll();
        foreach ($res as &$line) {
            $email = $line["email"];
            $subject =
                "All-Round Events: Uitbetaling starten voor" .
                $festival_name .
                " .";
            $message =
                '<html>
								<p>Beste,</p>
								<p>De uitbetalingen voor ' .
                $festival_name .
                ' zullen plaatsvinden tijdens komende dagen.  </br></p>
								<p>We willen je nogmaals bedanken voor je inzet en hopen je graag op een volgend evenement terug te zien!</p>
								<p> </p>
								<p>Met vriendelijke groeten</p>
								<p><small>
									All Round Events VZW
									Meester Van Der Borghtstraat 10
									2580 Putte
									BTW: BE 0886 674 723
									IBAN: BE68 7310 4460 6534
									RPR Mechelen</small></p></br>
									<small>Geen mails meer ontvangen? Verwijderd jezelf op de mailinglijst op de website.</small>									
							</html>';
            $headers =
                "From: aankondigen@all-round-events.be" .
                "\r\n" .
                "Reply-To: info@all-roundevents.be " .
                "\r\n" .
                "Content-type:text/html;charset=UTF-8" .
                "\r\n" .
                "X-Mailer: PHP/" .
                phpversion();
            add_to_mail_queue($db, $email, $subject, $message, $headers, 3);
        }
    }
    if ($status == 6) {
        // nothing should be hapening
    }
    if ($status == 7) {
        $statement = $db->prepare(
            "SELECT email FROM users where subscribed = 1;"
        );
        $statement->execute([]);
        $res = $statement->fetchAll();
        foreach ($res as &$line) {
            $email = $line["email"];
            $subject =
                "All-Round Events: " . $festival_name . " gaat niet door.";
            $message =
                '<html>
								<p>Beste,</p>
								<p>Jammer genoeg zal  ' .
                $festival_name .
                'niet doorgaan dit jaar. Onze excuses voor het ongemak! </br></p>

								<p>Kijk voor meer evenementen op:</p>
								<p>https://all-round-events.be/html/nl/inschrijven.html</p>
								<p> </p>
								<p>Met vriendelijke groeten</p>
								<p><small>
									All Round Events VZW
									Meester Van Der Borghtstraat 10
									2580 Putte
									BTW: BE 0886 674 723
									IBAN: BE68 7310 4460 6534
									RPR Mechelen</small></p></br>
								<small>Geen mails meer ontvangen? Verwijderd jezelf op de mailinglijst op de website.</small>									
							</html>';
            $headers =
                "From: aankondigen@all-round-events.be" .
                "\r\n" .
                "Reply-To: info@all-roundevents.be " .
                "\r\n" .
                "Content-type:text/html;charset=UTF-8" .
                "\r\n" .
                "X-Mailer: PHP/" .
                phpversion();
            add_to_mail_queue($db, $email, $subject, $message, $headers, 3);
        }
    }
} elseif ($action == "user_present") {
    // get the contenct from the api body
    $xml_dump = file_get_contents("php://input");
    $xml = json_decode($xml_dump, true);
    try {
        $ID = $xml["id"];
        $HASH = $xml["hash"];
        $user = $xml["user"];
        $work_day = $xml["work_day"];

        $in = $xml["in"];
        $out = $xml["out"];
        $present = $xml["present"];
    } catch (Exception $e) {
        exit(
            json_encode([
                "status" => 409,
                "error_type" => 4,
                "error_message" =>
                    "Not all fields where available, need: name, details, status, date, ID, HASH",
            ])
        );
    }
    admin_check($ID, $HASH, $db, false);

    if ($in != 2) {
        $statement = $db->prepare(
            "update work_day set work_day.in=? where idwork_day=? and users_Id_Users=?;"
        );
        $statement->execute([$in, $work_day, $user]);
    }
    if ($out != 2) {
        $statement = $db->prepare(
            "Update work_day set work_day.out=? where idwork_day=? and users_Id_Users=?;"
        );
        $statement->execute([$out, $work_day, $user]);
    }
    if ($present != 2) {
        $statement = $db->prepare(
            "Update work_day set work_day.present=? where idwork_day=? and users_Id_Users=?;"
        );
        $statement->execute([$present, $work_day, $user]);
    }

    exit(
        json_encode([
            "status" => 200,
            "error_type" => -1,
            "error_message" => "",
        ])
    );
} elseif ($action == "payouts_list") {
    // get the contenct from the api body
    $xml_dump = file_get_contents("php://input");
    $xml = json_decode($xml_dump, true);
    try {
        $ID = $xml["id"];
        $HASH = $xml["hash"];
        $festi_id = $xml["festi_id"];
    } catch (Exception $e) {
        exit(
            json_encode([
                "status" => 409,
                "error_type" => 4,
                "error_message" =>
                    "Not all fields where available, need: name, details, status, date, ID, HASH",
            ])
        );
    }
    admin_check($ID, $HASH, $db, false);
    $statement = $db->prepare(
        "select work_day.Payout, festivals.idfestival, shifts.name, work_day.users_Id_Users, shifts.idshifts, shift_days.cost, users_data.adres_line_two, users_data.name, work_day.in, work_day.out, work_day.present, shift_days.start_date from work_day inner join users_data on work_day.users_Id_Users = users_data.users_Id_Users inner join shift_days on work_day.shift_days_idshift_days = shift_days.idshift_days inner join shifts on shifts.idshifts = shift_days.shifts_idshifts inner join festivals on festivals.idfestival = shifts.festival_idfestival where festivals.idfestival = ? and (work_day.reservation_type = 3 or work_day.reservation_type = 5) ORDER BY work_day.users_Id_Users;"
    );
    $statement->execute([$festi_id]);
    $res = $statement->fetchAll();
    if ($res) {
        $json = json_encode($res);
        exit($json);
    } else {
        exit(json_encode(json_decode("{}")));
    }
} elseif ($action == "apply_payout") {
    // get the contenct from the api body
    //
    // payout id 0 => No payout
    // payout id 1 -> payout performed
    // payout id 2 -> payout refused
    $xml_dump = file_get_contents("php://input");
    $xml = json_decode($xml_dump, true);
    try {
        $ID = $xml["id"];
        $HASH = $xml["hash"];
        $shift_id = $xml["shift_id"];
        $payout_type_id = $xml["payout_type"];
        $user_id = $xml["user_id"];
    } catch (Exception $e) {
        exit(
            json_encode([
                "status" => 409,
                "error_type" => 4,
                "error_message" =>
                    "Not all fields where available, need: name, details, status, date, ID, HASH",
            ])
        );
    }
    if ($payout_type_id == 1) {
        $notification_text =
            "Er is een betaling onderweg, houd je bankrekening in de gaten! ";
        $statement = $db->prepare(
            "INSERT INTO notifications (notification, global,user_id) VALUES (?,?,?);"
        );
        $statement->execute([$notification_text, 0, $user_id]);
    }
    admin_check($ID, $HASH, $db, false);
    $statement = $db->prepare(
        "update shifts inner join shift_days on shift_days.shifts_idshifts = shifts.idshifts  inner join work_day on work_day.shift_days_idshift_days=shift_days.idshift_days set work_day.Payout = ? where idshifts=? and work_day.users_Id_Users=?;"
    );
    $statement->execute([$payout_type_id, $shift_id, $user_id]);
    $res = $statement->fetchAll();
    exit(json_encode(json_decode("{}")));
} elseif ($action == "pdf_unemployment") {
    $ID = isset($_GET["ID"]) ? $_GET["ID"] : "";
    $HASH = isset($_GET["HASH"]) ? $_GET["HASH"] : "";
    $shift = isset($_GET["shift"]) ? $_GET["shift"] : "";
    token_check($ID, $HASH, $db);

    $statement = $db->prepare(
        "SELECT * FROM users_data WHERE users_Id_Users = ?"
    );
    $statement->execute([$ID]);
    $user_data = $statement->fetch(PDO::FETCH_ASSOC);

    $statement = $db->prepare("SELECT * FROM users WHERE Id_Users = ?");
    $statement->execute([$ID]);
    $user = $statement->fetch(PDO::FETCH_ASSOC);

    $statement = $db->prepare(
        "SELECT festivals.name FROM festivals inner join shifts on festivals.idfestival=shifts.festival_idfestival WHERE shifts.idshifts = ? and (festivals.status != 6 and festivals.status != 7 and festivals.status != 8);"
    );
    $statement->execute([$shift]);
    $festival = $statement->fetch(PDO::FETCH_ASSOC);

    if (!$festival) {
        exit("userpermissionexception: shift data is not public for user");
    }

    $statement = $db->prepare(
        "SELECT * FROM `shift_days` WHERE shift_days.shifts_idshifts = ? ORDER BY shift_days.start_date ASC  LIMIT 1;"
    );
    $statement->execute([$shift]);
    $start_day = $statement->fetch(PDO::FETCH_ASSOC);

    $statement = $db->prepare(
        "SELECT * FROM `shift_days` WHERE shift_days.shifts_idshifts = ? ORDER BY shift_days.start_date DESC  LIMIT 1;"
    );
    $statement->execute([$shift]);
    $end_day = $statement->fetch(PDO::FETCH_ASSOC);

    require "fpdf.php";
    $pdf = new FPDF("P", "mm", "A4");
    $pdf->SetTitle("werkloosheidsatest");
    $pdf->AddPage();
    $pdf->SetFont("Arial", "", 14);
    $pdf->SetXY(20, 10);
    $pdf->Image(
        "https://all-round-events.be/img/rva.jpeg",
        $pdf->GetX(),
        $pdf->GetY(),
        0,
        30
    );
    $pdf->SetXY(60, 10);
    $pdf->SetTextColor(190, 190, 190);
    $pdf->Write(5, "Aangifte van vrijwilligerswerk voor een ");
    $pdf->SetXY(70, 15);
    $pdf->Write(5, "niet-commerciele organisatie");
    $pdf->SetXY(85, 20);
    $pdf->SetTextColor(0, 0, 0);
    $pdf->SetFont("Arial", "", 7);
    $pdf->Write(5, "Art. 45bis KB 25.11.1991");
    $pdf->SetFont("Arial", "", 14);
    $pdf->SetXY(60, 25);
    $pdf->Write(5, "Deel I: in te vullen door de werkloze of de ");
    $pdf->SetXY(70, 30);
    $pdf->Write(5, "werkloze met bedrijfstoeslag");
    $pdf->SetXY(160, 10);
    $pdf->Rect(160, 10, 40, 30);
    $pdf->SetFont("Arial", "", 10);
    $pdf->SetXY(168, 13);
    $pdf->SetTextColor(190, 190, 190);
    $pdf->Write(5, "Datumstempel");
    $pdf->SetXY(163, 16);
    $pdf->Write(5, "uitbetalingsinstelling");
    $pdf->SetTextColor(0, 0, 0);

    $pdf->Line(10, 45, 200, 45);

    $pdf->SetFont("Arial", "", 14);
    $pdf->SetTextColor(0, 0, 0);
    $pdf->SetXY(25, 50);
    $pdf->Write(5, "Uw identiteit");
    $pdf->SetFont("Arial", "", 10);
    $pdf->SetXY(50, 65);
    $pdf->Write(5, "Voornaam en naam");
    $pdf->SetXY(50, 80);
    $pdf->Write(5, "Adres");
    $pdf->SetXY(90, 65);
    $pdf->SetFont("Arial", "B", 14);
    $pdf->Write(5, $user_data["name"]);
    $pdf->SetXY(90, 80);
    $pdf->Write(5, $user_data["adres_line_one"]);
    $pdf->SetFont("Arial", "B", 8);
    $pdf->SetTextColor(190, 190, 190);
    $pdf->SetXY(25, 100);
    $pdf->Write(5, "Uw INSZ-nummer staat op de");
    $pdf->SetXY(25, 105);
    $pdf->Write(5, "keerzijde van uw identiteitskaart");
    $pdf->SetXY(25, 115);
    $pdf->Write(5, "De gegevens telefoon en e-mail");
    $pdf->SetXY(25, 120);
    $pdf->Write(5, "zijn facultatief");
    $pdf->SetFont("Arial", "", 10);
    $pdf->SetTextColor(0, 0, 0);
    $pdf->SetXY(75, 103);
    $pdf->Write(5, "Rijksregisternr. (INSZ)");
    $pdf->SetXY(120, 103);
    $pdf->SetFont("Arial", "B", 10);
    $pdf->Write(5, $user_data["driver_license"]);
    $pdf->SetXY(75, 112);
    $pdf->SetFont("Arial", "", 10);
    $pdf->Write(5, "Telefoon");
    $pdf->SetXY(120, 112);
    $pdf->SetFont("Arial", "B", 10);
    $pdf->Write(5, $user_data["telephone"]);
    $pdf->SetFont("Arial", "", 10);
    $pdf->SetXY(75, 118);
    $pdf->Write(5, "E-mail");
    $pdf->SetXY(120, 118);
    $pdf->SetFont("Arial", "B", 10);
    $pdf->Write(5, $user["email"]);

    $pdf->Line(10, 130, 200, 130);

    $pdf->SetFont("Arial", "B", 10);
    $pdf->SetTextColor(0, 0, 0);
    $pdf->SetXY(25, 135);
    $pdf->Write(5, "Uw vrijwilligerswerk ");
    $pdf->SetFont("Arial", "", 7);
    $pdf->SetTextColor(190, 190, 190);
    $pdf->SetXY(25, 138);
    $pdf->Write(5, "Duid de vakjes aan die op u van ");
    $pdf->SetXY(25, 141);
    $pdf->Write(5, "toepassing zijn.");
    $pdf->SetFont("Arial", "", 10);
    $pdf->SetTextColor(0, 0, 0);
    $pdf->SetXY(75, 135);
    $pdf->Write(
        5,
        "Ik wens vrijwilligerswerk te verrichten voor een niet-commerciele organisatie"
    );
    $pdf->SetXY(75, 140);
    $pdf->Write(5, "Naam van deze organisatie:");
    $pdf->SetXY(130, 140);
    $pdf->SetFont("Arial", "B", 10);
    $pdf->Write(5, "ALL-ROUND EVENTS VZW");
    $pdf->SetFont("Arial", "", 10);
    $pdf->SetXY(75, 145);
    $pdf->Write(5, "Ik wil dit vrijwilligerswerk verrichten:");
    $pdf->SetXY(76, 151);
    $pdf->SetFont("ZapfDingbats", "", 10);
    $pdf->Cell(3, 3, 4, 1, 0); // checkbox
    $pdf->SetFont("Arial", "", 10);
    $pdf->SetXY(80, 150);
    $pdf->Write(
        5,
        "tijdens de periode van " .
            $start_day["start_date"] .
            " tot " .
            $end_day["shift_end"]
    );
    $pdf->SetXY(76, 156);
    $pdf->SetFont("ZapfDingbats", "", 10);
    $pdf->Cell(3, 3, "", 1, 0);
    $pdf->SetFont("Arial", "", 10);
    $pdf->SetXY(80, 155);
    $pdf->Write(5, "voor onbepaalde duur.");
    $pdf->SetXY(75, 160);
    $pdf->Write(5, "Ik wil dit vrijwilligerswerk verrichten:");
    $pdf->SetFont("ZapfDingbats", "", 10);
    $pdf->SetXY(76, 166);
    $pdf->Cell(3, 3, "", 1, 0);
    $pdf->SetFont("Arial", "", 10);
    $pdf->SetXY(80, 165);
    $pdf->Write(
        5,
        "op occasionele basis, nl. ............. keer per maand en ............ keer per jaar. "
    );
    $pdf->SetFont("ZapfDingbats", "", 10);
    $pdf->SetXY(76, 171);
    $pdf->Cell(3, 3, "", 1, 0);
    $pdf->SetFont("Arial", "", 10);
    $pdf->SetXY(80, 170);
    $pdf->Write(5, "op de volgende dagen:  ma  di  wo  do  vr  za  zo");
    $pdf->SetFont("ZapfDingbats", "", 10);
    $pdf->SetXY(76, 176);
    $pdf->Cell(3, 3, 4, 1, 0);
    $pdf->SetFont("Arial", "", 10);
    $pdf->SetXY(80, 175);
    $pdf->Write(
        5,
        "maar de frequentie ervan is niet vooraf te bepalen. In dit geval geeft u de"
    );
    $pdf->SetXY(80, 180);
    $pdf->Write(5, "rede op:");
    $pdf->SetXY(80, 185);
    $pdf->SetFont("Arial", "B", 10);
    $pdf->Write(5, "In functie van de planning van de festivalkalender.");
    $pdf->SetFont("Arial", "", 10);
    $pdf->SetXY(80, 190);
    $pdf->Write(
        5,
        "...................................................................................................................."
    );
    $pdf->SetXY(80, 195);
    $pdf->Write(
        5,
        "...................................................................................................................."
    );
    $pdf->SetXY(75, 200);
    $pdf->Write(5, "Het maximum aantal uren van het vrijwilligerswerk:");
    $pdf->SetXY(76, 206);
    $pdf->SetFont("ZapfDingbats", "", 10);
    $pdf->Cell(3, 3, "", 1, 0);
    $pdf->SetFont("Arial", "", 10);
    $pdf->SetXY(80, 205);
    $pdf->Write(
        5,
        "bedraagt .............uur per week en ............... per maand."
    );
    $pdf->SetFont("ZapfDingbats", "", 10);
    $pdf->SetXY(76, 211);
    $pdf->Cell(3, 3, 4, 1, 0);
    $pdf->SetFont("Arial", "", 10);
    $pdf->SetXY(80, 210);
    $pdf->Write(
        5,
        "is niet vooraf te bepalen. In dit geval geeft u de reden op:"
    );
    $pdf->SetXY(80, 215);
    $pdf->SetFont("Arial", "B", 10);
    $pdf->Write(
        5,
        "In functie van de festivalkalender & festivaluren, en bijhorende"
    );
    $pdf->SetFont("Arial", "B", 10);
    $pdf->SetXY(80, 220);
    $pdf->Write(5, "bezetting");
    $pdf->SetFont("Arial", "", 10);
    $pdf->SetXY(80, 225);
    $pdf->Write(
        5,
        "...................................................................................................................."
    );

    $pdf->SetXY(25, 270);
    $pdf->Write(5, "Versie 28.12.2016/833.20.042");
    $pdf->SetXY(100, 270);
    $pdf->Write(5, "1/4");
    $pdf->SetXY(140, 270);
    $pdf->Write(5, "FORMULIER C45B");

    $pdf->AddPage();

    $pdf->SetXY(25, 15);
    $pdf->Write(5, "Rijksregisternr. (INSZ)");
    $pdf->Write(5, "");
    $pdf->SetXY(70, 15);
    $pdf->Write(5, $user_data["driver_license"]);
    $pdf->SetTextColor(190, 190, 190);
    $pdf->SetFont("Arial", "", 9);
    $pdf->SetXY(25, 30);
    $pdf->Write(5, "Antwoord neen als u enkel de");
    $pdf->SetXY(25, 34);
    $pdf->Write(5, "terugbetaling van uw reele kosten");
    $pdf->SetXY(25, 38);
    $pdf->Write(5, "ontvangt (materiaal, vervoer, enz). ");
    $pdf->SetXY(25, 42);
    $pdf->Write(5, "Om cumuleerbaar te zijn met");
    $pdf->SetXY(25, 46);
    $pdf->Write(5, "werkloosheidsuitkeringen mag deze");
    $pdf->SetXY(25, 50);
    $pdf->Write(5, "forfaitaire vergoeding tot terugbetaling");
    $pdf->SetXY(25, 54);
    $pdf->Write(5, "van de onkosten een bepaald");
    $pdf->SetXY(25, 58);
    $pdf->Write(5, "dagbedrag niet overschrijden. Het totaal");
    $pdf->SetXY(25, 62);
    $pdf->Write(5, "van de dagvergoedingen mag een");
    $pdf->SetXY(25, 66);
    $pdf->Write(5, "jaarlijks grensbedrag niet overschrijden.");
    $pdf->SetXY(25, 70);
    $pdf->Write(5, "U vindt deze bedragen in het infoblad");
    $pdf->SetXY(25, 74);
    $pdf->Write(5, "T7 en T42 op de site www.rva.be");
    $pdf->SetFont("Arial", "", 10);
    $pdf->SetTextColor(0, 0, 0);
    $pdf->SetXY(90, 30);
    $pdf->Write(5, "Ik zal een vergoeding ontvangen van de organisatie:");
    $pdf->SetXY(91, 36);
    $pdf->SetFont("ZapfDingbats", "", 10);
    $pdf->Cell(3, 3, "", 1, 0);
    $pdf->SetFont("Arial", "", 10);
    $pdf->SetXY(95, 35);
    $pdf->Write(5, "neen.");
    $pdf->SetXY(91, 41);
    $pdf->SetFont("ZapfDingbats", "", 10);
    $pdf->Cell(3, 3, 4, 1, 0);
    $pdf->SetFont("Arial", "", 10);
    $pdf->SetXY(95, 40);
    $pdf->Write(5, "Ja.");
    $pdf->SetXY(95, 45);
    $pdf->Write(5, "Bedrag " . $start_day["cost"] . " Euro per");
    $pdf->SetXY(128, 46);
    $pdf->SetFont("ZapfDingbats", "", 10);
    $pdf->Cell(3, 3, "", 1, 0);
    $pdf->SetFont("Arial", "", 10);
    $pdf->SetXY(132, 45);
    $pdf->Write(5, "Uur");
    $pdf->SetXY(140, 46);
    $pdf->SetFont("ZapfDingbats", "", 10);
    $pdf->Cell(3, 3, 4, 1, 0);
    $pdf->SetFont("Arial", "", 10);
    $pdf->SetXY(144, 45);
    $pdf->Write(5, "dag");
    $pdf->SetXY(152, 46);
    $pdf->SetFont("ZapfDingbats", "", 10);
    $pdf->Cell(3, 3, "", 1, 0);
    $pdf->SetFont("Arial", "", 10);
    $pdf->SetXY(155, 45);
    $pdf->Write(5, "week");
    $pdf->SetXY(165, 46);
    $pdf->SetFont("ZapfDingbats", "", 10);
    $pdf->Cell(3, 3, "", 1, 0);
    $pdf->SetFont("Arial", "", 10);
    $pdf->SetXY(169, 45);
    $pdf->Write(5, "maand");
    $pdf->SetXY(91, 51);
    $pdf->SetFont("ZapfDingbats", "", 10);
    $pdf->Cell(3, 3, 4, 1, 0);
    $pdf->SetFont("Arial", "", 10);
    $pdf->SetXY(95, 50);
    $pdf->Write(
        5,
        "het gaat om een forfaitaire vergoeding tot terugbetaling van de"
    );
    $pdf->SetXY(95, 55);
    $pdf->Write(5, "onkosten.");
    $pdf->SetXY(91, 61);
    $pdf->SetFont("ZapfDingbats", "", 10);
    $pdf->Cell(3, 3, "", 1, 0);
    $pdf->SetFont("Arial", "", 10);
    $pdf->SetXY(95, 60);
    $pdf->Write(5, "het gaat om een andere vergoeding of materieel voordeel,:");
    $pdf->SetXY(95, 65);
    $pdf->Write(5, "namelijk:");
    $pdf->SetXY(95, 70);
    $pdf->Write(
        5,
        "...................................................................................................."
    );
    $pdf->SetXY(95, 75);
    $pdf->Write(
        5,
        "...................................................................................................."
    );
    $pdf->SetXY(95, 80);
    $pdf->Write(
        5,
        "...................................................................................................."
    );

    $pdf->Line(10, 90, 200, 90);

    $pdf->SetXY(25, 95);
    $pdf->SetFont("Arial", "B", 10);
    $pdf->Write(5, "Handtekening");
    $pdf->SetFont("Arial", "", 9);
    $pdf->SetTextColor(190, 190, 190);
    $pdf->SetXY(25, 100);
    $pdf->Write(5, "Uw verklaringen worden bewaard in");
    $pdf->SetXY(25, 104);
    $pdf->Write(5, "informaticabestanden. Meer informatie ");
    $pdf->SetXY(25, 108);
    $pdf->Write(5, "over de bescherming van deze");
    $pdf->SetXY(25, 112);
    $pdf->Write(5, "gegevens vindt u in de brochure over de");
    $pdf->SetXY(25, 116);
    $pdf->Write(5, "bescherming van de persoonlijke");
    $pdf->SetXY(25, 120);
    $pdf->Write(5, "levenssfeer, beschikbaar bij de RVA");
    $pdf->SetXY(25, 124);
    $pdf->Write(5, "Meer info op www.rva.be.");
    $pdf->SetTextColor(0, 0, 0);
    $pdf->SetFont("Arial", "", 10);
    $pdf->SetXY(90, 100);
    $pdf->Write(5, "Ik bevestig dat mijn verklaringen echt en volledig zijn.");
    $pdf->SetXY(90, 105);
    $pdf->Write(
        5,
        "Ik vermeld mijn rijksregisternummer (INSZ) eveneens bovenaan"
    );
    $pdf->SetXY(90, 110);
    $pdf->Write(5, "pagina 2, 3 en 4.");
    $pdf->SetXY(90, 120);
    $pdf->SetFont("Arial", "B", 10);
    $pdf->Write(5, "Datum : 06/01/2021     Handtekening");

    $pdf->SetFont("Arial", "", 10);
    $pdf->SetXY(25, 270);
    $pdf->Write(5, "Versie 28.12.2016/833.20.042");
    $pdf->SetXY(100, 270);
    $pdf->Write(5, "2/4");
    $pdf->SetXY(140, 270);
    $pdf->Write(5, "FORMULIER C45B");

    $pdf->AddPage();

    $pdf->SetFont("Arial", "", 10);
    $pdf->SetXY(25, 15);
    $pdf->Write(5, "Rijksregisternr. (INSZ)");
    $pdf->SetXY(70, 15);
    $pdf->Write(5, $user_data["driver_license"]);
    $pdf->SetFont("Arial", "B", 14);
    $pdf->SetXY(60, 30);
    $pdf->Write(5, "Deel II : in te vullen door de organisatie");

    $pdf->Line(10, 40, 200, 40);

    $pdf->SetXY(25, 45);
    $pdf->SetFont("Arial", "B", 10);
    $pdf->Write(5, "De organisatie");
    $pdf->SetFont("Arial", "", 10);
    $pdf->SetXY(90, 55);
    $pdf->Write(5, "Naam");
    $pdf->SetFont("Arial", "B", 10);
    $pdf->SetXY(105, 55);
    $pdf->Write(5, "ALL-ROUND EVENTS VZW");
    $pdf->SetFont("Arial", "", 10);
    $pdf->SetXY(90, 60);
    $pdf->Write(5, "Straat en nummer");
    $pdf->SetFont("Arial", "B", 10);
    $pdf->SetXY(120, 60);
    $pdf->Write(5, "Meester Van Der Borghtstraat 10");
    $pdf->SetFont("Arial", "", 10);
    $pdf->SetXY(90, 65);
    $pdf->Write(5, "Postcode en gemeente");
    $pdf->SetFont("Arial", "B", 10);
    $pdf->SetXY(130, 65);
    $pdf->Write(5, "2580 Putte");
    $pdf->SetFont("Arial", "", 10);
    $pdf->SetXY(90, 70);
    $pdf->Write(5, "Ondernemingsnummer");
    $pdf->SetFont("Arial", "B", 10);
    $pdf->SetXY(130, 70);
    $pdf->Write(5, "BE0886.674.723");
    $pdf->SetFont("Arial", "", 10);
    $pdf->SetXY(90, 75);
    $pdf->Write(5, "De organisatie is:");
    $pdf->SetXY(91, 81);
    $pdf->SetFont("ZapfDingbats", "", 10);
    $pdf->Cell(3, 3, "", 1, 0);
    $pdf->SetFont("Arial", "", 10);
    $pdf->SetXY(95, 80);
    $pdf->Write(5, "een openbare dienst");
    $pdf->SetXY(91, 86);
    $pdf->SetFont("ZapfDingbats", "", 10);
    $pdf->Cell(3, 3, 4, 1, 0);
    $pdf->SetFont("Arial", "B", 10);
    $pdf->SetXY(95, 85);
    $pdf->Write(5, "een vzw, met als maatschappelijk doel het organiseren en");
    $pdf->SetXY(95, 90);
    $pdf->Write(5, "ondersteunen van diverse manifestaties.");
    $pdf->SetFont("Arial", "", 10);
    $pdf->SetXY(91, 96);
    $pdf->SetFont("ZapfDingbats", "", 10);
    $pdf->Cell(3, 3, "", 1, 0);
    $pdf->SetFont("Arial", "", 10);
    $pdf->SetXY(95, 95);
    $pdf->Write(
        5,
        "andere,......................................................................................."
    );
    $pdf->SetXY(95, 100);
    $pdf->Write(
        5,
        "met als maatschappelijk doel......................................................"
    );
    $pdf->SetXY(95, 105);
    $pdf->Write(
        5,
        "...................................................................................................."
    );
    $pdf->SetXY(95, 110);
    $pdf->Write(
        5,
        "...................................................................................................."
    );
    $pdf->SetXY(90, 115);
    $pdf->Write(
        5,
        "Algemeen toelatingsnummer van de RVA ( zie FORMULIER C45F):"
    );
    $pdf->SetXY(90, 120);
    $pdf->SetFont("Arial", "B", 10);
    $pdf->Write(
        5,
        "Y02/ ....................../......................... /45bis"
    );
    $pdf->SetFont("Arial", "", 10);
    $pdf->SetTextColor(190, 190, 190);
    $pdf->SetXY(25, 70);
    $pdf->Write(5, "Duid de vakjes aan die op uw");
    $pdf->SetXY(25, 74);
    $pdf->Write(5, "organisatie van toepassing zijn.");
    $pdf->SetXY(25, 110);
    $pdf->Write(5, "Facultatief: enkel in te vullen als de RVA");
    $pdf->SetXY(25, 114);
    $pdf->Write(5, "u een toelatingsnummer heeft ");
    $pdf->SetXY(25, 118);
    $pdf->Write(5, "toegekend in het kader van een regio");
    $pdf->SetXY(25, 122);
    $pdf->Write(5, "overschrijdend project (de organisatie is");
    $pdf->SetXY(25, 126);
    $pdf->Write(5, "gevestigd in heel het land of in");
    $pdf->SetXY(25, 130);
    $pdf->Write(5, "verschillende delen van het land en/of");
    $pdf->SetXY(25, 134);
    $pdf->Write(5, "de vrijwilligers wonen in verschillende ");
    $pdf->SetXY(25, 138);
    $pdf->Write(5, "werkloosheidsregios).");

    $pdf->Line(10, 150, 200, 150);

    $pdf->SetXY(25, 160);
    $pdf->SetFont("Arial", "B", 10);
    $pdf->SetTextColor(0, 0, 0);
    $pdf->Write(5, "Het vrijwilligerswerk");
    $pdf->SetTextColor(190, 190, 190);
    $pdf->SetFont("Arial", "", 9);
    $pdf->SetXY(25, 170);
    $pdf->Write(5, "Opgelet:");
    $pdf->SetXY(25, 180);
    $pdf->Write(5, "In het kader van het vrijwilligerswerk");
    $pdf->SetXY(25, 184);
    $pdf->Write(5, "kan er een forfaitaire vergoeding tot");
    $pdf->SetXY(25, 188);
    $pdf->Write(5, "terugbetaling van de onkosten");
    $pdf->SetXY(25, 192);
    $pdf->Write(5, "toegekend worden (artikel 13 van de");
    $pdf->SetXY(25, 196);
    $pdf->Write(5, "wet van 3.07.2005) ");
    $pdf->SetXY(25, 200);
    $pdf->Write(5, "Om cumuleerbaar te zijn met");
    $pdf->SetXY(25, 205);
    $pdf->Write(5, "werkloosheidsuitkeringen mag deze");
    $pdf->SetXY(25, 209);
    $pdf->Write(5, "forfaitaire vergoeding tot terugbetaling");
    $pdf->SetXY(25, 213);
    $pdf->Write(5, "van de onkosten mag een bepaald ");
    $pdf->SetXY(25, 217);
    $pdf->Write(5, "dagbedrag niet overschrijden. Het totaal");
    $pdf->SetXY(25, 221);
    $pdf->Write(5, "van de dagvergoedingen mag een");
    $pdf->SetXY(25, 225);
    $pdf->Write(5, "jaarlijks grensbedrag niet overschrijden.");
    $pdf->SetXY(25, 229);
    $pdf->Write(5, "U vindt deze bedragen in het infoblad");
    $pdf->SetXY(25, 233);
    $pdf->Write(5, "E39 op de site www.rva.be");
    $pdf->SetTextColor(0, 0, 0);
    $pdf->SetFont("Arial", "", 10);
    $pdf->SetXY(90, 170);
    $pdf->Write(
        5,
        "Ik bevestig de verklaring van de werkloze of de werkloze met"
    );
    $pdf->SetXY(90, 174);
    $pdf->Write(5, "bedrijfstoeslag in verband met het verrichten van het");
    $pdf->SetXY(90, 179);
    $pdf->Write(5, "vrijwilligerswerk.");
    $pdf->SetXY(90, 184);
    $pdf->Write(5, "Ik beschrijf beknopt dit vrijwilligerswerk: ");
    $pdf->SetXY(90, 189);
    $pdf->SetFont("Arial", "B", 10);
    $pdf->Write(
        5,
        "Bandjescontrole, bemannen in en uitgangen op een festival."
    );
    $pdf->SetFont("Arial", "", 10);
    $pdf->SetXY(90, 194);
    $pdf->Write(
        5,
        "......................................................................................................."
    );
    $pdf->SetXY(90, 199);
    $pdf->Write(
        5,
        "Ik preciseer de doelgroep van de diensten aangeboden door mijn"
    );
    $pdf->SetXY(90, 204);
    $pdf->Write(5, "organisatie:");
    $pdf->SetXY(110, 204);
    $pdf->SetFont("Arial", "B", 10);
    $pdf->Write(5, "12 - 65 jarigen");
    $pdf->SetFont("Arial", "", 10);
    $pdf->SetXY(90, 209);
    $pdf->Write(
        5,
        "Ik preciseer de tegenprestatie die de doelgroep moet betalen in ruil"
    );
    $pdf->SetXY(90, 214);
    $pdf->Write(5, "voor de diensten:");
    $pdf->SetXY(90, 219);
    $pdf->SetFont("Arial", "B", 10);
    $pdf->Write(
        5,
        "Vrijwilligersvergoeding van " . $start_day["cost"] . " Euro"
    );
    $pdf->SetFont("Arial", "", 10);
    $pdf->SetXY(90, 224);
    $pdf->Write(5, "Dit vrijwilligerswerk wordt verricht:");
    $pdf->SetXY(91, 231);
    $pdf->SetFont("ZapfDingbats", "", 10);
    $pdf->Cell(3, 3, "", 1, 0);
    $pdf->SetFont("Arial", "", 10);
    $pdf->SetXY(95, 230);
    $pdf->Write(5, "op het adres van de organisatie;");
    $pdf->SetXY(91, 236);
    $pdf->SetFont("ZapfDingbats", "", 10);
    $pdf->Cell(3, 3, 4, 1, 0);
    $pdf->SetFont("Arial", "", 10);
    $pdf->SetXY(95, 235);
    $pdf->Write(5, "op een ander adres, nanelijk: ");
    $pdf->SetXY(95, 240);
    $pdf->SetFont("Arial", "B", 10);
    $pdf->Write(5, $festival["name"]);
    $pdf->SetFont("Arial", "", 10);

    $pdf->SetFont("Arial", "", 10);
    $pdf->SetXY(25, 270);
    $pdf->Write(5, "Versie 28.12.2016/833.20.042");
    $pdf->SetXY(100, 270);
    $pdf->Write(5, "3/4");
    $pdf->SetXY(140, 270);
    $pdf->Write(5, "FORMULIER C45B");

    $pdf->AddPage();

    $pdf->SetFont("Arial", "", 10);
    $pdf->SetXY(25, 15);
    $pdf->Write(5, "Rijksregisternr. (INSZ)");
    $pdf->SetXY(70, 15);
    $pdf->Write(5, $user_data["driver_license"]);
    $pdf->Line(10, 30, 200, 30);
    $pdf->SetXY(25, 40);
    $pdf->SetFont("Arial", "B", 10);
    $pdf->Write(5, "Handtekening");
    $pdf->SetXY(85, 90);
    $pdf->Write(
        5,
        "Datum: " .
            date("d-m-Y") .
            "    Handtekening verantwoordelijke     Stempel"
    );
    $pdf->SetXY(85, 100);
    $pdf->Write(5, "Contactpersoon:     Bart Tops");
    $pdf->SetXY(85, 110);
    $pdf->Write(5, "Telefoon:     0471 01 34 07");
    $pdf->SetFont("Arial", "", 10);
    $pdf->SetXY(25, 270);
    $pdf->Write(5, "Versie 28.12.2016/833.20.042");
    $pdf->SetXY(100, 270);
    $pdf->Write(5, "4/4");
    $pdf->SetXY(140, 270);
    $pdf->Write(5, "FORMULIER C45B");

    $pdf->Output();
} elseif ($action == "pdf_listing") {
    $ID = isset($_GET["ID"]) ? $_GET["ID"] : "";
    $HASH = isset($_GET["HASH"]) ? $_GET["HASH"] : "";
    $shift_day = isset($_GET["shift_day"]) ? $_GET["shift_day"] : "";
    admin_check($ID, $HASH, $db, false);
    $statement = $db->prepare(
        "select shifts.name, shift_days.start_date, shift_days.shift_end , work_day.shift_days_idshift_days, work_day.users_Id_Users,work_day.in, work_day.out, work_day.present, users_data.telephone, users_data.name, shifts_idshifts, reservation_type, idwork_day, picture_name from work_day inner join shift_days on shift_days.idshift_days = work_day.shift_days_idshift_days inner join users_data on users_data.users_Id_Users = work_day.users_Id_Users inner join Images on (Images.users_Id_Users = work_day.users_Id_Users and Images.is_primary = 1) inner join shifts on shifts.idshifts = shift_days.shifts_idshifts inner join festivals on shifts.festival_idfestival = festivals.idfestival where shift_days.idshift_days=? and (work_day.reservation_type = 3 or work_day.reservation_type = 5);"
    );
    $statement->execute([$shift_day]);
    $res = $statement->fetchAll();
    require "fpdf.php";
    $w = [30, 55, 20, 20, 20, 20, 20, 20];
    $pdf = new FPDF("P", "mm", "A4");
    $pdf->SetTitle("Aanwezigheden");
    $pdf->AddPage();
    $pdf->SetFont("Arial", "", 14);
    $pdf->Write(
        10,
        $res[0][0] .
            ":   Start: " .
            $res[0]["start_date"] .
            " Tot: " .
            $res[0]["shift_end"]
    );
    $pdf->Ln();
    $pdf->SetFont("Arial", "", 8);
    $header = ["foto", "Naam", "nummer", "in", "out", "aanwezig", ""];
    for ($i = 0; $i < count($header); $i++) {
        $pdf->Cell($w[$i], 7, $header[$i], 1, 0, "C");
    }
    $pdf->Ln();
    foreach ($res as &$line) {
        $image1 = $line["picture_name"];
        $pdf->Cell(
            $w[0],
            10,
            $pdf->Image($image1, $pdf->GetX(), $pdf->GetY(), 0, 9.9),
            1
        );
        $pdf->Cell($w[1], 10, $line["name"], 1);

        $in = "";
        $out = "";
        $present = "";
        if ($line["in"] == 1) {
            $in = "           X";
        }
        if ($line["out"] == 1) {
            $out = "           X";
        }
        if ($line["present"] == 1) {
            $present = "           X";
        }
        $pdf->Cell($w[2], 10, $line["telephone"], 1);
        $pdf->Cell($w[3], 10, $in, 1);
        $pdf->Cell($w[4], 10, $out, 1);
        $pdf->Cell($w[5], 10, $present, 1);
        $pdf->Cell($w[6], 10, "", 1);
        $pdf->Ln();
    }
    $pdf->Output();
} elseif ($action == "pdf_listing_external") {
    $ID = isset($_GET["ID"]) ? $_GET["ID"] : "";
    $HASH = isset($_GET["HASH"]) ? $_GET["HASH"] : "";
    $location_id = isset($_GET["location_id"]) ? $_GET["location_id"] : "";
    admin_check($ID, $HASH, $db, false);
    $statement = $db->prepare(
        "SELECT external_appointment.present, locations.location, locations.appointment_time, Images.picture_name,users_data.name, users_data.telephone FROM `external_appointment` INNER JOIN locations on locations.location_id = external_appointment.location_id INNER JOIN users_data on users_data.users_Id_Users = external_appointment.user_id INNER JOIN Images on Images.users_Id_Users = users_data.users_Id_Users WHERE external_appointment.location_id = ? and Images.is_primary =1"
    );
    $statement->execute([$location_id]);
    $res = $statement->fetchAll();
    require "fpdf.php";
    $w = [30, 55, 20, 20, 20, 20, 20, 20];
    $pdf = new FPDF("P", "mm", "A4");
    $pdf->SetTitle("Aanwezigheden opvang");
    $pdf->AddPage();
    $pdf->SetFont("Arial", "", 14);
    $pdf->Write(
        10,
        "Opvang" . " " . $res[0]["appointment_time"] . " " . $res[0]["location"]
    );
    $pdf->Ln();
    $pdf->SetFont("Arial", "", 8);
    $header = ["foto", "Naam", "nummer", "aanwezig", "", "", ""];
    for ($i = 0; $i < count($header); $i++) {
        $pdf->Cell($w[$i], 7, $header[$i], 1, 0, "C");
    }
    $pdf->Ln();
    foreach ($res as &$line) {
        $image1 = $line["picture_name"];
        $pdf->Cell(
            $w[0],
            10,
            $pdf->Image($image1, $pdf->GetX(), $pdf->GetY(), 0, 9.9),
            1
        );
        $pdf->Cell($w[1], 10, $line["name"], 1);
        $present = "";

        if ($line["present"] == 1) {
            $present = "           X";
        }
        $pdf->Cell($w[2], 10, $line["telephone"], 1);
        $pdf->Cell($w[3], 10, $present, 1);
        $pdf->Cell($w[4], 10, "", 1);
        $pdf->Cell($w[5], 10, "", 1);
        $pdf->Cell($w[6], 10, "", 1);
        $pdf->Ln();
    }
    $pdf->Output();
}
if ($action == "change_pass") {
    $xml_dump = file_get_contents("php://input");
    $xml = json_decode($xml_dump, true);
    try {
        $ID = $xml["id"];
        $HASH = $xml["hash"];
        $new_pass = $xml["new_pass"];
    } catch (Exception $e) {
        exit(
            json_encode([
                "status" => 409,
                "error_type" => 4,
                "error_message" =>
                    "Not all fields where available, need: name, details, status, date, ID, HASH",
            ])
        );
    }
    token_check($ID, $HASH, $db);
    $salt = bin2hex(openssl_random_pseudo_bytes(40));
    if (strlen($new_pass) < 5) {
        exit(
            json_encode([
                "status" => 409,
                "error_type" => 2,
                "error_message" => "password must have more than 5 characters ",
            ])
        );
    }
    $hashed_pass = password_hash($new_pass . $salt, PASSWORD_DEFAULT);

    // everything is ok, save
    $statement = $db->prepare(
        "UPDATE users set pass=?, salt=? where Id_Users=?"
    );
    $statement->execute([$hashed_pass, $salt, $ID]);
    exit(
        json_encode([
            "status" => 200,
            "error_type" => 0,
        ])
    );
}

if ($action == "get_news") {
    $xml_dump = file_get_contents("php://input");
    $xml = json_decode($xml_dump, true);
    try {
        $ID = $xml["id"];
        $HASH = $xml["hash"];
    } catch (Exception $e) {
        exit(
            json_encode([
                "status" => 409,
                "error_type" => 4,
                "error_message" =>
                    "Not all fields where available, need: name, details, status, date, ID, HASH",
            ])
        );
    }
    token_check($ID, $HASH, $db);

    // everything is ok, save
    $statement = $db->prepare(
        "SELECT * FROM notifications where global=1 or user_id=? order by notifications.id DESC limit 10"
    );
    $statement->execute([$ID]);
    $res = $statement->fetchAll();
    if ($res) {
        $json = json_encode($res);
        exit($json);
    }
    exit(
        json_encode([
            "status" => 200,
            "error_type" => 0,
        ])
    );
}
if ($action == "reset_pass") {
    $xml_dump = file_get_contents("php://input");
    $xml = json_decode($xml_dump, true);
    try {
        $email = $xml["email"];
    } catch (Exception $e) {
        exit(
            json_encode([
                "status" => 404,
                "error_type" => 4,
                "error_message" => "Not all fields where available, email",
            ])
        );
    }
    $email = str_replace(" ", "", $email);
    $pass = bin2hex(openssl_random_pseudo_bytes(8));
    $salt = bin2hex(openssl_random_pseudo_bytes(40));
    $hashed_pass = password_hash($pass . $salt, PASSWORD_DEFAULT);

    $statement = $db->prepare("select * from users where users.email=?;");
    $statement->execute([$email]);
    $res = $statement->fetchAll();
    if (!$res) {
        $subject = "Wachtwoord reset";
        $message = '<html>
							<p>Beste,</p>
							<p>Je hebt een wachtwoord aangevraagd voor all-round-events, wij hebben echter geen account gevonden waarbij dit email adres is gebruikt. Kijk dus zeker na of je geen ander email adres hebt gebruikt.</br></p>
							<p> </p>
							<p>Met vriendelijke groeten</p>
							<p><small>
								All Round Events VZW
								Meester Van Der Borghtstraat 10
								2580 Putte
								BTW: BE 0886 674 723
								IBAN: BE68 7310 4460 6534
								RPR Mechelen</small></p>
						</html>';
        $headers =
            "From: info@all-round-events.be" .
            "\r\n" .
            "Reply-To: info@all-roundevents.be " .
            "\r\n" .
            "Content-type:text/html;charset=UTF-8" .
            "\r\n" .
            "X-Mailer: PHP/" .
            phpversion();
        add_to_mail_queue($db, $email, $subject, $message, $headers, 1);
        exit(
            json_encode([
                "status" => "OK",
                "error_type" => 0,
                "error_message" => "OK",
            ])
        );
    }

    $statement = $db->prepare("UPDATE users set pass=?, salt=? where email=?");
    $statement->execute([$hashed_pass, $salt, $email]);
    # send email
    $subject = "Wachtwoord reset";
    $message =
        '<html>
						<p>Beste,</p>
						<p>Je hebt een wachtwoord aangevraagd, hieronder vindt u uw nieuw wachtwoord. Indien u uw wachtwoord wilt wijzingen kunt u dit doen door in te loggen en naar uw profiel aanpassen te gaan. </br></p>
						<p>Uw email: ' .
        $email .
        '</br></p>
						<p>Uw nieuw wachtwoord: ' .
        $pass .
        '</br></p>
						<p> </p>
						<p>Met vriendelijke groeten</p>
						<p><small>
							All Round Events VZW
							Meester Van Der Borghtstraat 10
							2580 Putte
							BTW: BE 0886 674 723
							IBAN: BE68 7310 4460 6534
							RPR Mechelen</small></p>" 
					</html>';
    $headers =
        "From: info@all-round-events.be" .
        "\r\n" .
        "Reply-To: info@all-roundevents.be " .
        "\r\n" .
        "Content-type:text/html;charset=UTF-8" .
        "\r\n" .
        "X-Mailer: PHP/" .
        phpversion();
    add_to_mail_queue($db, $email, $subject, $message, $headers, 1);

    exit(
        json_encode([
            "status" => "OK",
            "error_type" => 0,
            "error_message" => "OK",
        ])
    );
}

if ($action == "message") {
    $xml_dump = file_get_contents("php://input");
    $xml = json_decode($xml_dump, true);
    try {
        $ID = $xml["id"];
        $HASH = $xml["hash"];
        $subject = $xml["subject"];
        $text = $xml["text"];
        $festi_id = $xml["festi_id"];
        $shift_id = $xml["shift_id"];
        $email = $xml["email"];
    } catch (Exception $e) {
        exit(
            json_encode([
                "status" => 404,
                "error_type" => 4,
                "error_message" => "Not all fields where available, email",
            ])
        );
    }
    admin_check($ID, $HASH, $db, false);

    // personal mail
    if ($festi_id == -2) {
        $id_pusher = $line["id_Users"];
        $message =
            "<html><p>" . str_replace("\n", "</br>", $text) . "</p></html>";
        $message_mail =
            "<html><p>" .
            str_replace("\n", "</br></p><p>", $text) .
            "</p><p><small>
																						All Round Events VZW
																						Meester Van Der Borghtstraat 10
																						2580 Putte
																						BTW: BE 0886 674 723
																						IBAN: BE68 7310 4460 6534
																					RPR Mechelen</small></br>
																					</p></html>";
        $headers =
            "From: info@all-round-events.be" .
            "\r\n" .
            "Reply-To: info@all-roundevents.be" .
            "\r\n" .
            "Content-type:text/html;charset=UTF-8" .
            "\r\n" .
            "X-Mailer: PHP/" .
            phpversion();

        add_to_mail_queue($db, $email, $subject, $message_mail, $headers, 2);
        exit(json_encode(json_decode("{}")));
    }

    // mail everybody except the people that have disabled from the mailing list
    if ($festi_id == -1) {
        $statement = $db->prepare("SELECT * FROM users where subscribed = 1;");
        $statement->execute([]);
        $res = $statement->fetchAll();
        foreach ($res as &$line) {
            $email = $line["email"];
            $id_pusher = $line["id_Users"];
            $message =
                "<html><p>" . str_replace("\n", "</br>", $text) . "</p></html>";
            $message_mail =
                "<html><p>" .
                str_replace("\n", "</br></p><p>", $text) .
                "</p><p><small>
																							All Round Events VZW
																							Meester Van Der Borghtstraat 10
																							2580 Putte
																							BTW: BE 0886 674 723
																							IBAN: BE68 7310 4460 6534
																						RPR Mechelen</small></br>
																						<small>Geen mails meer ontvangen? Verwijderd jezelf op de mailinglijst op de website.</small>
																						</p></html>";
            $headers =
                "From: info@all-round-events.be" .
                "\r\n" .
                "Reply-To: info@all-roundevents.be" .
                "\r\n" .
                "Content-type:text/html;charset=UTF-8" .
                "\r\n" .
                "X-Mailer: PHP/" .
                phpversion();

            add_to_mail_queue(
                $db,
                $email,
                $subject,
                $message_mail,
                $headers,
                3
            );

            $notification_text = $text;
            $statement = $db->prepare(
                "INSERT INTO notifications (notification, global, user_id) VALUES (?,?,?);"
            );
            $statement->execute([$message, 0, $id_pusher]);
        }
        exit(json_encode(json_decode("{}")));
    }

    // select all the id's and email from one shift
    $statement = $db->prepare(
        "SELECT DISTINCT email ,users.Id_Users from work_day inner JOIN users on work_day.users_Id_Users = users.Id_Users inner JOIN shift_days on work_day.shift_days_idshift_days = shift_days.idshift_days where shift_days.shifts_idshifts = ?;"
    );
    $statement->execute([$shift_id]);
    $res = $statement->fetchAll();
    foreach ($res as &$line) {
        $email = $line["email"];
        $id_pusher = $line["id_Users"];
        $message =
            "<html><p>" . str_replace("\n", "</br>", $text) . "</p></html>";
        $message_mail =
            "<html><p>" .
            str_replace("\n", "</br></p><p>", $text) .
            "</p><p><small>
																						All Round Events VZW
																						Meester Van Der Borghtstraat 10
																						2580 Putte
																						BTW: BE 0886 674 723
																						IBAN: BE68 7310 4460 6534
																					RPR Mechelen</small></p></html>";
        $headers =
            "From: info@all-round-events.be" .
            "\r\n" .
            "Reply-To: info@all-roundevents.be" .
            "\r\n" .
            "Content-type:text/html;charset=UTF-8" .
            "\r\n" .
            "X-Mailer: PHP/" .
            phpversion();

        add_to_mail_queue($db, $email, $subject, $message_mail, $headers, 3);
        $notification_text = $text;
        $statement = $db->prepare(
            "INSERT INTO notifications (notification, global, user_id) VALUES (?,?,?);"
        );
        $statement->execute([$message, 0, $id_pusher]);
    }

    $statement = $db->prepare(
        "SELECT DISTINCT email ,users.Id_Users from work_day inner JOIN users on work_day.users_Id_Users = users.Id_Users inner JOIN shift_days on work_day.shift_days_idshift_days = shift_days.idshift_days inner JOIN shifts on shifts.idshifts = shift_days.shifts_idshifts where shifts.festival_idfestival = ?;"
    );
    $statement->execute([$festi_id]);
    $res = $statement->fetchAll();
    foreach ($res as &$line) {
        $email = $line["email"];
        $id_pusher = $line["Id_Users"];
        $message =
            "<html><p>" . str_replace("\n", "</br>", $text) . "</p></html>";
        $message_mail =
            "<html><p>" .
            str_replace("\n", "</br></p><p>", $text) .
            "</p><p><small>
																						All Round Events VZW
																						Meester Van Der Borghtstraat 10
																						2580 Putte
																						BTW: BE 0886 674 723
																						IBAN: BE68 7310 4460 6534
																					RPR Mechelen</small></p></html>";
        $headers =
            "From: info@all-round-events.be" .
            "\r\n" .
            "Reply-To: info@all-roundevents.be" .
            "\r\n" .
            "Content-type:text/html;charset=UTF-8" .
            "\r\n" .
            "X-Mailer: PHP/" .
            phpversion();

        add_to_mail_queue($db, $email, $subject, $message_mail, $headers, 3);
        $notification_text = $text;
        $statement = $db->prepare(
            "INSERT INTO notifications (notification, global, user_id) VALUES (?,?,?);"
        );
        $statement->execute([$message, 0, $id_pusher]);
    }

    exit(json_encode(json_decode("{}")));
} elseif ($action == "tshirts") {
    // get the contenct from the api body
    //
    $xml_dump = file_get_contents("php://input");
    $xml = json_decode($xml_dump, true);
    try {
        $ID = $xml["id"];
        $HASH = $xml["hash"];
        $festival_id = $xml["festi_id"];
    } catch (Exception $e) {
        exit(
            json_encode([
                "status" => 409,
                "error_type" => 4,
                "error_message" =>
                    "Not all fields where available, need: name, details, status, date, ID, HASH",
            ])
        );
    }
    admin_check($ID, $HASH, $db, false);
    $statement = $db->prepare(
        "select DISTINCT COUNT(size) as size, users_data.size from work_day inner join users_data on work_day.users_Id_Users = users_data.users_Id_Users inner join shift_days on work_day.shift_days_idshift_days = shift_days.idshift_days inner join shifts on shifts.idshifts = shift_days.shifts_idshifts inner join festivals on festivals.idfestival = shifts.festival_idfestival where festivals.idfestival = ? and work_day.reservation_type = 3 GROUP BY users_data.size;"
    );
    $statement->execute([$festival_id]);
    $res = $statement->fetchAll();
    if ($res) {
        $json = json_encode($res);
        exit($json);
    } else {
        exit(json_encode(json_decode("{}")));
    }
    exit(json_encode(json_decode("{}")));
} elseif ($action == "add_location") {
    // get the contenct from the api body
    //
    $xml_dump = file_get_contents("php://input");
    $xml = json_decode($xml_dump, true);
    try {
        $ID = $xml["id"];
        $HASH = $xml["hash"];
        $shift_id = $xml["shift_id"];
        $location = $xml["location"];
        $appointment_time = $xml["appointment_time"];
    } catch (Exception $e) {
        exit(
            json_encode([
                "status" => 409,
                "error_type" => 4,
                "error_message" =>
                    "Not all fields where available, need: name, details, status, date, ID, HASH",
            ])
        );
    }
    admin_check($ID, $HASH, $db, false);
    $statement = $db->prepare(
        "INSERT INTO locations (location, appointment_time, shift_id) VALUES (?,?,?);"
    );
    $statement->execute([$location, $appointment_time, $shift_id]);
    exit(json_encode(json_decode("{}")));
} elseif ($action == "change_location") {
    // get the contenct from the api body
    //
    $xml_dump = file_get_contents("php://input");
    $xml = json_decode($xml_dump, true);
    try {
        $ID = $xml["id"];
        $HASH = $xml["hash"];
        $location = $xml["location"];
        $appointment_time = $xml["appointment_time"];
        $location_id = $xml["location_id"];
    } catch (Exception $e) {
        exit(
            json_encode([
                "status" => 409,
                "error_type" => 4,
                "error_message" =>
                    "Not all fields where available, need: name, details, status, date, ID, HASH",
            ])
        );
    }
    admin_check($ID, $HASH, $db, false);
    $statement = $db->prepare(
        "UPDATE locations SET location = ?, appointment_time = ? WHERE location_id = ?;"
    );
    $statement->execute([$location, $appointment_time, $location_id]);
    exit(json_encode(json_decode("{}")));
} elseif ($action == "delete_location") {
    // get the contenct from the api body
    //
    $xml_dump = file_get_contents("php://input");
    $xml = json_decode($xml_dump, true);
    try {
        $ID = $xml["id"];
        $HASH = $xml["hash"];
        $location_id = $xml["location_id"];
    } catch (Exception $e) {
        exit(
            json_encode([
                "status" => 409,
                "error_type" => 4,
                "error_message" =>
                    "Not all fields where available, need: name, details, status, date, ID, HASH",
            ])
        );
    }
    admin_check($ID, $HASH, $db, false);
    $statement = $db->prepare("DELETE FROM locations WHERE location_id=?;");
    $statement->execute([$location_id]);
    exit(json_encode(json_decode("{}")));
} elseif ($action == "get_locations") {
    // get the contenct from the api body
    //
    $xml_dump = file_get_contents("php://input");
    $xml = json_decode($xml_dump, true);
    try {
        $ID = $xml["id"];
        $HASH = $xml["hash"];
    } catch (Exception $e) {
        exit(
            json_encode([
                "status" => 409,
                "error_type" => 4,
                "error_message" =>
                    "Not all fields where available, need: name, details, status, date, ID, HASH",
            ])
        );
    }
    admin_check($ID, $HASH, $db, false);
    $statement = $db->prepare(
        "SELECT locations.appointment_time, locations.location_id, locations.location, locations.shift_id, shifts.idshifts, shifts.datails, shifts.name, shifts.festival_idfestival FROM `locations` inner join shifts on locations.shift_id = shifts.idshifts inner join festivals on festivals.idfestival = shifts.festival_idfestival where festivals.status != 6 or festivals.status != 7;"
    );
    $statement->execute([]);
    $res = $statement->fetchAll();
    if ($res) {
        $json = json_encode($res);
        exit($json);
    }
    exit(json_encode(json_decode("{}")));
} elseif ($action == "get_locations_by_shift") {
    // get the contenct from the api body
    //
    $xml_dump = file_get_contents("php://input");
    $xml = json_decode($xml_dump, true);
    try {
        $ID = $xml["id"];
        $HASH = $xml["hash"];
        $shift_id = $xml["shift_id"];
    } catch (Exception $e) {
        exit(
            json_encode([
                "status" => 409,
                "error_type" => 4,
                "error_message" =>
                    "Not all fields where available, need: name, details, status, date, ID, HASH",
            ])
        );
    }
    token_check($ID, $HASH, $db);
    $statement = $db->prepare("SELECT * FROM `locations` where  shift_id=?");
    $statement->execute([$shift_id]);
    $res = $statement->fetchAll();
    if ($res) {
        $json = json_encode($res);
        exit($json);
    }
    exit(json_encode(json_decode("{}")));
} elseif ($action == "get_location") {
    // get the contenct from the api body
    //
    $xml_dump = file_get_contents("php://input");
    $xml = json_decode($xml_dump, true);
    try {
        $ID = $xml["id"];
        $HASH = $xml["hash"];
        $location_id = $xml["location_id"];
    } catch (Exception $e) {
        exit(
            json_encode([
                "status" => 409,
                "error_type" => 4,
                "error_message" =>
                    "Not all fields where available, need: name, details, status, date, ID, HASH",
            ])
        );
    }
    admin_check($ID, $HASH, $db, false);
    $statement = $db->prepare("SELECT * FROM `locations` where location_id=?;");
    $statement->execute([$location_id]);
    $res = $statement->fetchAll();
    if ($res) {
        $json = json_encode($res);
        exit($json);
    }
    exit(json_encode(json_decode("{}")));
} elseif ($action == "add_external_appointment") {
    // get the contenct from the api body
    //
    $xml_dump = file_get_contents("php://input");
    $xml = json_decode($xml_dump, true);
    try {
        $ID = $xml["id"];
        $HASH = $xml["hash"];
        $location_id = $xml["location_id"];
        $location = $xml["location"];
        $user_id = $xml["user_id"];
    } catch (Exception $e) {
        exit(
            json_encode([
                "status" => 409,
                "error_type" => 4,
                "error_message" =>
                    "Not all fields where available, need: name, details, status, date, ID, HASH",
            ])
        );
    }

    if ($ID == $user_id) {
        token_check($ID, $HASH, $db, false);
    } else {
        admin_check($ID, $HASH, $db, false);
    }
    // check if festival is open
    $statement = $db->prepare(
        "INSERT INTO external_appointment_id (location_id, user_id) VALUES (?,?);"
    );
    $statement->execute([$location, $user_id]);
    exit(json_encode(json_decode("{}")));
} elseif ($action == "change_external_appointment") {
    // get the contenct from the api body
    //
    $xml_dump = file_get_contents("php://input");
    $xml = json_decode($xml_dump, true);
    try {
        $ID = $xml["id"];
        $HASH = $xml["hash"];
        $location_id_old = $xml["old_location_id"];
        $location = $xml["location"];
        $user_id = $xml["user_id"];
    } catch (Exception $e) {
        exit(
            json_encode([
                "status" => 409,
                "error_type" => 4,
                "error_message" =>
                    "Not all fields where available, need: name, details, status, date, ID, HASH",
            ])
        );
    }

    if ($ID == $user_id) {
        token_check($ID, $HASH, $db, false);
    } else {
        admin_check($ID, $HASH, $db, false);
    }
    // check if festival is open
    $statement = $db->prepare(
        "update external_appointment_id set location_id = ? where location_id = ? and user_id=?;"
    );
    $statement->execute([$location, $user_id]);
    exit(json_encode(json_decode("{}")));
} elseif ($action == "subscribe_external_location") {
    // get the contenct from the api body
    //
    $xml_dump = file_get_contents("php://input");
    $xml = json_decode($xml_dump, true);
    try {
        $ID = $xml["id"];
        $HASH = $xml["hash"];
        $location_id = $xml["location_id"];
        $location = $xml["location"];
    } catch (Exception $e) {
        exit(
            json_encode([
                "status" => 409,
                "error_type" => 4,
                "error_message" =>
                    "Not all fields where available, need: name, details, status, date, ID, HASH",
            ])
        );
    }

    token_check($ID, $HASH, $db);
    // check if festival is open
    $statement = $db->prepare(
        "SELECT festivals.name, festivals.status, idshifts from shifts INNER JOIN festivals on festivals.idfestival = shifts.festival_idfestival inner JOIN locations on locations.shift_id = shifts.idshifts where locations.location_id = ? LIMIT 1;"
    );
    $statement->execute([$location_id]);
    $res = $statement->fetchAll();
    $festival = $res[0]["name"];
    $shift = $res[0]["idshifts"];
    if ($res[0]["status"] > 3) {
        exit("Event in wrong state to push external event");
    }
    $statement = $db->prepare(
        "select * from work_day inner join shift_days on work_day.shift_days_idshift_days = shift_days.idshift_days inner join shifts on shifts.idshifts = shift_days.shifts_idshifts inner join locations on locations.shift_id = shifts.idshifts where locations.location_id = ? and work_day.users_Id_Users = ?"
    );
    $statement->execute([$location_id, $ID]);
    $res = $statement->fetchAll();
    if (count($res) < 1) {
        exit(
            "Cannot subscribe to event when user is not part of event itself."
        );
    }
    $statement = $db->prepare(
        "DELETE external_appointment from external_appointment  inner JOIN locations on locations.location_id = external_appointment.location_id inner join shifts on shifts.idshifts = locations.shift_id where shifts.idshifts = ? and external_appointment.user_id=?"
    );
    $statement->execute([$shift, $ID]);

    $statement = $db->prepare(
        "insert into external_appointment (external_appointment.location_id, external_appointment.user_id, present) VALUES (?,?,?);"
    );
    $statement->execute([$location_id, $ID, 0]);

    $statement = $db->prepare("SELECT * FROM users where users.Id_Users = ?;");
    $statement->execute([$ID]);
    $res = $statement->fetchAll();
    $email = $res[0]["email"];
    $name = $res[0]["name"];
    $statement = $db->prepare(
        "SELECT * FROM locations where locations.location_id = ?"
    );
    $statement->execute([$location_id]);
    $location = $statement->fetchAll();

    $subject = "Opvang keuze voor " . $festival;
    $message =
        '<html>
				<p>Beste, ' .
        $name .
        '</p>
				<p></br></p>
				<p>Je hebt gezozen voor een opvang moment, je wordt verwacht op ' .
        $location[0]["appointment_time"] .
        '</br></p>
				<p>op de volgende locatie: ' .
        $location[0]["location"] .
        '</p>
				<p></br></p>

				<p>Indien je niet meer kan deelnemen aan dit evenement gelieve je dan zo vlug mogelijk uit te schrijven op de <a href="https://all-round-events.be/html/nl/inschrijven.html">website</a> of door te antwoorden op deze mail.</p>
				<p>Indien u nog meer vragen hebt kan u altijd antwoorden op deze mail of een kijkje nemen op in onze <a href="https://all-round-events.be/html/nl/info.html">FAQ</a>.</p>

				<p>Met vriendelijke groeten</p>
				<p><small>
					All Round Events VZW
					Meester Van Der Borghtstraat 10
					2580 Putte
					BTW: BE 0886 674 723
					IBAN: BE68 7310 4460 6534
					RPR Mechelen 
				</small></html>';
    $headers =
        "From: info@all-round-events.be" .
        "\r\n" .
        "Reply-To: info@all-roundevents.be" .
        "\r\n" .
        "Content-type:text/html;charset=UTF-8" .
        "\r\n" .
        "X-Mailer: PHP/" .
        phpversion();
    add_to_mail_queue($db, $email, $subject, $message, $headers, 2);

    exit(json_encode(json_decode("{}")));
} elseif ($action == "subscribe_external_location_admin") {
    // get the contenct from the api body
    //
    $xml_dump = file_get_contents("php://input");
    $xml = json_decode($xml_dump, true);
    try {
        $ID = $xml["id"];
        $HASH = $xml["hash"];
        $location_id = $xml["location_id"];
        $user_id = $xml["user_id"];
        $shift_id = $xml["shift_id"];
    } catch (Exception $e) {
        exit(
            json_encode([
                "status" => 409,
                "error_type" => 4,
                "error_message" =>
                    "Not all fields where available, need: name, details, status, date, ID, HASH",
            ])
        );
    }

    admin_check($ID, $HASH, $db, false);

    $statement = $db->prepare(
        "select festivals.name from festivals inner JOIN shifts ON festivals.idfestival = shifts.festival_idfestival where shifts.idshifts = 27;"
    );
    $statement->execute([$shift_id]);
    $res = $statement->fetchAll();
    $festival = $res[0]["name"];

    $statement = $db->prepare(
        "DELETE external_appointment from external_appointment  inner JOIN locations on locations.location_id = external_appointment.location_id inner join shifts on shifts.idshifts = locations.shift_id where shifts.idshifts = ? and external_appointment.user_id=?"
    );
    $statement->execute([$shift_id, $user_id]);

    $statement = $db->prepare(
        "insert into external_appointment (external_appointment.location_id, external_appointment.user_id, present) VALUES (?,?,?);"
    );
    $statement->execute([$location_id, $user_id, 0]);

    $statement = $db->prepare("SELECT * FROM users where users.Id_Users = ?;");
    $statement->execute([$user_id]);
    $res = $statement->fetchAll();
    $email = $res[0]["email"];
    $name = $res[0]["name"];
    $statement = $db->prepare(
        "SELECT locations.appointment_time, locations.location from locations inner join shifts on locations.shift_id = shifts.idshifts where shifts.idshifts = ?;"
    );
    $statement->execute([$shift_id]);
    $location = $statement->fetchAll();

    $subject = "Opvang voor " . $festival;
    $message =
        '<html>
				<p>Beste, ' .
        $name .
        '</p>
				<p></br></p>
				<p>Je wordt verwacht op ' .
        $location[0]["appointment_time"] .
        '</br></p>
				<p>op de volgende locatie: ' .
        $location[0]["location"] .
        '</p>
				<p></br></p>

				<p>Indien je niet meer kan deelnemen aan dit evenement gelieve je dan zo vlug mogelijk uit te schrijven op de <a href="https://all-round-events.be/html/nl/inschrijven.html">website</a> of door te antwoorden op deze mail.</p>
				<p>Indien u nog meer vragen hebt kan u altijd antwoorden op deze mail of een kijkje nemen op in onze <a href="https://all-round-events.be/html/nl/info.html">FAQ</a>.</p>

				<p>Met vriendelijke groeten</p>
				<p><small>
					All Round Events VZW
					Meester Van Der Borghtstraat 10
					2580 Putte
					BTW: BE 0886 674 723
					IBAN: BE68 7310 4460 6534
					RPR Mechelen 
				</small></html>';
    $headers =
        "From: info@all-round-events.be" .
        "\r\n" .
        "Reply-To: info@all-roundevents.be" .
        "\r\n" .
        "Content-type:text/html;charset=UTF-8" .
        "\r\n" .
        "X-Mailer: PHP/" .
        phpversion();
    add_to_mail_queue($db, $email, $subject, $message, $headers, 3);

    exit(json_encode(json_decode("{}")));
} elseif ($action == "subscribe_external_location_admin_manual") {
    // get the contenct from the api body
    //
    $xml_dump = file_get_contents("php://input");
    $xml = json_decode($xml_dump, true);
    try {
        $ID = $xml["id"];
        $HASH = $xml["hash"];
        $location_id = $xml["location_id"];
        $user_id = $xml["user_id"];
    } catch (Exception $e) {
        exit(
            json_encode([
                "status" => 409,
                "error_type" => 4,
                "error_message" =>
                    "Not all fields where available, need: name, details, status, date, ID, HASH",
            ])
        );
    }

    admin_check($ID, $HASH, $db, false);

    $statement = $db->prepare(
        "SELECT festivals.name, festivals.status, idshifts from shifts INNER JOIN festivals on festivals.idfestival = shifts.festival_idfestival inner JOIN locations on locations.shift_id = shifts.idshifts where locations.location_id = ? LIMIT 1;"
    );
    $statement->execute([$location_id]);
    $res = $statement->fetchAll();
    $festival = $res[0]["name"];

    $statement = $db->prepare(
        "DELETE external_appointment from external_appointment  inner JOIN locations on locations.location_id = external_appointment.location_id where locations.location_id =? and external_appointment.user_id=?"
    );
    $statement->execute([$location_id, $user_id]);

    $statement = $db->prepare(
        "insert into external_appointment (external_appointment.location_id, external_appointment.user_id, present) VALUES (?,?,?);"
    );
    $statement->execute([$location_id, $user_id, 0]);

    $statement = $db->prepare("SELECT * FROM users where users.Id_Users = ?;");
    $statement->execute([$user_id]);
    $res = $statement->fetchAll();
    $email = $res[0]["email"];
    $name = $res[0]["name"];
    $statement = $db->prepare(
        "SELECT * FROM locations where locations.location_id = ?"
    );
    $statement->execute([$location_id]);
    $location = $statement->fetchAll();

    $subject = "Opvang voor " . $festival;
    $message =
        '<html>
				<p>Beste, ' .
        $name .
        '</p>
				<p></br></p>
				<p>Je wordt verwacht op ' .
        $location[0]["appointment_time"] .
        '</br></p>
				<p>op de volgende locatie: ' .
        $location[0]["location"] .
        '</p>
				<p></br></p>

				<p>Indien je niet meer kan deelnemen aan dit evenement gelieve je dan zo vlug mogelijk uit te schrijven op de <a href="https://all-round-events.be/html/nl/inschrijven.html">website</a> of door te antwoorden op deze mail.</p>
				<p>Indien u nog meer vragen hebt kan u altijd antwoorden op deze mail of een kijkje nemen op in onze <a href="https://all-round-events.be/html/nl/info.html">FAQ</a>.</p>

				<p>Met vriendelijke groeten</p>
				<p><small>
					All Round Events VZW
					Meester Van Der Borghtstraat 10
					2580 Putte
					BTW: BE 0886 674 723
					IBAN: BE68 7310 4460 6534
					RPR Mechelen 
				</small></html>';
    $headers =
        "From: info@all-round-events.be" .
        "\r\n" .
        "Reply-To: info@all-roundevents.be" .
        "\r\n" .
        "Content-type:text/html;charset=UTF-8" .
        "\r\n" .
        "X-Mailer: PHP/" .
        phpversion();
    add_to_mail_queue($db, $email, $subject, $message, $headers, 3);

    exit(json_encode(json_decode("{}")));
} elseif ($action == "unsubscribe_external_location_admin_manual") {
    // get the contenct from the api body
    //
    $xml_dump = file_get_contents("php://input");
    $xml = json_decode($xml_dump, true);
    try {
        $ID = $xml["id"];
        $HASH = $xml["hash"];
        $location_id = $xml["location_id"];
        $user_id = $xml["user_id"];
    } catch (Exception $e) {
        exit(
            json_encode([
                "status" => 409,
                "error_type" => 4,
                "error_message" =>
                    "Not all fields where available, need: name, details, status, date, ID, HASH",
            ])
        );
    }

    admin_check($ID, $HASH, $db, false);
    //mail
    $statement = $db->prepare(
        "DELETE external_appointment from external_appointment  inner JOIN locations on locations.location_id = external_appointment.location_id where locations.location_id =? and external_appointment.user_id=?"
    );
    $statement->execute([$location_id, $user_id]);
    exit(json_encode(json_decode("{}")));
} elseif ($action == "subscribe_external_location_user") {
    // get the contenct from the api body
    //
    $xml_dump = file_get_contents("php://input");
    $xml = json_decode($xml_dump, true);
    try {
        $ID = $xml["id"];
        $HASH = $xml["hash"];
    } catch (Exception $e) {
        exit(
            json_encode([
                "status" => 409,
                "error_type" => 4,
                "error_message" =>
                    "Not all fields where available, need: name, details, status, date, ID, HASH",
            ])
        );
    }
    token_check($ID, $HASH, $db);
    $statement = $db->prepare(
        "SELECT external_appointment.external_appointment_id, external_appointment.location_id, external_appointment.user_id, external_appointment.present FROM `external_appointment` inner join locations on locations.location_id = external_appointment.location_id inner join shifts on locations.shift_id = shifts.idshifts inner join festivals on festivals.idfestival = shifts.festival_idfestival where (festivals.status != 6 or festivals.status != 7) and external_appointment.user_id = ?"
    );
    $statement->execute([$ID]);
    $res = $statement->fetchAll();
    if ($res) {
        $json = json_encode($res);
        exit($json);
    }
    exit(json_encode(json_decode("{}")));
} elseif ($action == "subscribe_external_location_active") {
    // get the contenct from the api body
    //
    $xml_dump = file_get_contents("php://input");
    $xml = json_decode($xml_dump, true);
    try {
        $ID = $xml["id"];
        $HASH = $xml["hash"];
    } catch (Exception $e) {
        exit(
            json_encode([
                "status" => 409,
                "error_type" => 4,
                "error_message" =>
                    "Not all fields where available, need: name, details, status, date, ID, HASH",
            ])
        );
    }
    admin_check($ID, $HASH, $db, false);
    $statement = $db->prepare(
        "SELECT external_appointment.external_appointment_id, external_appointment.location_id, external_appointment.user_id, external_appointment.present,locations.shift_id, users_data.name, Images.picture_name FROM `external_appointment` inner join locations on locations.location_id = external_appointment.location_id inner join shifts on locations.shift_id = shifts.idshifts inner join festivals on festivals.idfestival = shifts.festival_idfestival inner join users_data on users_data.users_Id_Users = external_appointment.user_id inner join Images on Images.users_Id_Users = external_appointment.user_id where (festivals.status != 6 or festivals.status != 7) and Images.is_primary = 1"
    );
    $statement->execute([]);
    $res = $statement->fetchAll();
    if ($res) {
        $json = json_encode($res);
        exit($json);
    }
    exit(json_encode(json_decode("{}")));
} elseif ($action == "subscribe_external_location_listing") {
    // get the contenct from the api body
    //
    $xml_dump = file_get_contents("php://input");
    $xml = json_decode($xml_dump, true);
    try {
        $ID = $xml["id"];
        $HASH = $xml["hash"];
        $location_id = $xml["location_id"];
    } catch (Exception $e) {
        exit(
            json_encode([
                "status" => 409,
                "error_type" => 4,
                "error_message" =>
                    "Not all fields where available, need: name, details, status, date, ID, HASH",
            ])
        );
    }
    admin_check($ID, $HASH, $db, false);
    $statement = $db->prepare(
        "SELECT locations.location_id, locations.appointment_time, locations.location, locations.shift_id, users_data.name, users_data.telephone, Images.picture_name, users_data.users_Id_Users, external_appointment.present from locations inner join external_appointment on external_appointment.location_id = locations.location_id inner join users_data on users_data.users_Id_Users = external_appointment.user_id INNER join Images on Images.users_Id_Users=users_data.users_Id_Users WHERE Images.is_primary = 1 and locations.location_id = ?"
    );
    $statement->execute([$location_id]);
    $res = $statement->fetchAll();
    if ($res) {
        $json = json_encode($res);
        exit($json);
    }
    exit(json_encode(json_decode("{}")));
} elseif ($action == "present_set_external_location") {
    // get the contenct from the api body
    //
    $xml_dump = file_get_contents("php://input");
    $xml = json_decode($xml_dump, true);
    try {
        $ID = $xml["id"];
        $HASH = $xml["hash"];
        $present = $xml["present"];
        $location_id = $xml["location_id"];
        $user_id = $xml["user_id"];
    } catch (Exception $e) {
        exit(
            json_encode([
                "status" => 409,
                "error_type" => 4,
                "error_message" =>
                    "Not all fields where available, need: name, details, status, date, ID, HASH",
            ])
        );
    }
    admin_check($ID, $HASH, $db, false);
    $statement = $db->prepare(
        "update external_appointment set present=? where user_id=? and location_id=?;"
    );
    $statement->execute([$present, $user_id, $location_id]);
    exit(json_encode(json_decode("{}")));
} elseif ($action == "festival_mail_external_location") {
    // get the contenct from the api body
    //
    $xml_dump = file_get_contents("php://input");
    $xml = json_decode($xml_dump, true);
    try {
        $ID = $xml["id"];
        $HASH = $xml["hash"];
        $festival_id = $xml["festival_id"];
    } catch (Exception $e) {
        exit(
            json_encode([
                "status" => 409,
                "error_type" => 4,
                "error_message" =>
                    "Not all fields where available, need: name, details, status, date, ID, HASH",
            ])
        );
    }
    admin_check($ID, $HASH, $db, false);
    // select all the id's and email from one shift
    $statement = $db->prepare(
        "select DISTINCT users.email, users.id_Users ,users_data.name, festivals.name as festival_name from work_day INNER JOIN users on work_day.users_Id_Users = users.Id_Users inner join shift_days on shift_days.idshift_days = work_day.shift_days_idshift_days inner join users_data on users_data.users_Id_Users = work_day.users_Id_Users inner JOIN shifts on shifts.idshifts = shift_days.shifts_idshifts inner join festivals on festivals.idfestival = shifts.festival_idfestival where shifts.festival_idfestival = ? and users.Id_Users not in (select DISTINCT external_appointment.user_id from external_appointment inner JOIN locations on locations.location_id = external_appointment.location_id inner join shifts on shifts.idshifts = locations.shift_id where shifts.festival_idfestival = ?)"
    );
    $statement->execute([$festival_id, $festival_id]);
    $res = $statement->fetchAll();
    foreach ($res as &$line) {
        $email = $line["email"];
        $festival_name = $line["festival_name"];
        $name = $line["name"];
        $id_pusher = $line["id_Users"];
        $subject = "Opvang keuze voor " . $festival_name;
        $message =
            '<html>
				<p>Beste, ' .
            $name .
            '</p>
				<p>Binnenkort is het zover en zal jij als vrijwillger aan de slag gaan op ' .
            $festival_name .
            '. </br></p>
				<p></p>
				<p>Je kan vanaf nu een opvang locatie kiezen op de <a href="https://all-round-events.be/html/nl/inschrijven.html">website</a>, gelieve in te loggen en naar inschrijvingen te gaan. Gelieve hier je opvang locatie en uur naar keuze door te geven voor dit evenement.</p>

				<p>Indien je niet meer kan deelnemen aan dit evenement gelieve je dan zo snel mogelijk uit te schrijven op de <a href="https://all-round-events.be/html/nl/inschrijven.html">website</a> of door te antwoorden op deze mail.</p>
				<p>Indien u nog meer vragen hebt kan u altijd antwoorden op deze mail of een kijkje nemen op in onze <a href="https://all-round-events.be/html/nl/info.html">FAQ</a>.</p>

				<p>Met vriendelijke groeten</p>
				<p><small>
					All Round Events VZW
					Meester Van Der Borghtstraat 10
					2580 Putte
					BTW: BE 0886 674 723
					IBAN: BE68 7310 4460 6534
					RPR Mechelen 
				</small></html>';
        $headers =
            "From: info@all-round-events.be" .
            "\r\n" .
            "Reply-To: info@all-roundevents.be" .
            "\r\n" .
            "Content-type:text/html;charset=UTF-8" .
            "\r\n" .
            "X-Mailer: PHP/" .
            phpversion();

        add_to_mail_queue($db, $email, $subject, $message, $headers, 3);

        $message2 =
            "Binnenkort is het zover en zal jij als vrijwillger aan de slag gaan op " .
            $festival_name .
            "!  Je kan vanaf nu een opvang locatie kiezen op de website, gelieve in te loggen en naar inschrijvingen te gaan. Gelieve hier je opvang locatie en uur naar keuze door te geven voor dit evenement.";
        $statement = $db->prepare(
            "INSERT INTO notifications (notification, global, user_id) VALUES (?,?,?);"
        );
        $statement->execute([$message2, 0, $id_pusher]);
    }
    exit(json_encode(json_decode("{}")));
} elseif ($action == "add_user_to_day_manual") {
    // get the contenct from the api body
    //
    $xml_dump = file_get_contents("php://input");
    $xml = json_decode($xml_dump, true);
    try {
        $ID = $xml["id"];
        $HASH = $xml["hash"];
        $Id_Users = $xml["Id_Users"];
        $shift_day_id = $xml["shift_day_id"];
    } catch (Exception $e) {
        exit(
            json_encode([
                "status" => 409,
                "error_type" => 4,
                "error_message" =>
                    "Not all fields where available, need: name, details, status, date, ID, HASH",
            ])
        );
    }
    admin_check($ID, $HASH, $db, false);
    // check if festival is open
    $statement = $db->prepare(
        "INSERT INTO work_day (reservation_type, shift_days_idshift_days, users_Id_Users) VALUES (?,?,?);"
    );
    $statement->execute(["5", $shift_day_id, $Id_Users]);
    exit(json_encode(json_decode("{}")));
} elseif ($action == "remove_user_to_day_manual") {
    // get the contenct from the api body
    //
    $xml_dump = file_get_contents("php://input");
    $xml = json_decode($xml_dump, true);
    try {
        $ID = $xml["id"];
        $HASH = $xml["hash"];
        $Id_Users = $xml["Id_Users"];
        $shift_day_id = $xml["shift_day_id"];
    } catch (Exception $e) {
        exit(
            json_encode([
                "status" => 409,
                "error_type" => 4,
                "error_message" =>
                    "Not all fields where available, need: name, details, status, date, ID, HASH",
            ])
        );
    }
    admin_check($ID, $HASH, $db, false);
    // check if festival is open
    $statement = $db->prepare(
        "DELETE work_day from work_day  where work_day.reservation_type = ? and work_day.shift_days_idshift_days = ? and work_day.users_Id_Users =?;"
    );
    $statement->execute(["5", $shift_day_id, $Id_Users]);
    exit(json_encode(json_decode("{}")));
} elseif ($action == "mail_user_by_shift_day") {
    // get the contenct from the api body
    //
    $xml_dump = file_get_contents("php://input");
    $xml = json_decode($xml_dump, true);
    try {
        $ID = $xml["id"];
        $HASH = $xml["hash"];
        $Id_Users = $xml["Id_Users"];
        $shift_day_id = $xml["shift_day_id"];
    } catch (Exception $e) {
        exit(
            json_encode([
                "status" => 409,
                "error_type" => 4,
                "error_message" =>
                    "Not all fields where available, need: name, details, status, date, ID, HASH",
            ])
        );
    }
    admin_check($ID, $HASH, $db, false);
    $statement = $db->prepare(
        "select festivals.idfestival from shift_days inner join shifts on shifts.idshifts = shift_days.shifts_idshifts inner join festivals on festivals.idfestival = shifts.festival_idfestival where shift_days.idshift_days = ?;"
    );
    $statement->execute([$shift_day_id]);
    $res = $statement->fetchAll();
    $festival = $res[0]["idfestival"];
    $statement = $db->prepare(
        "select users.email, shift_days.start_date, shift_days.shift_end,shift_days.cost, festivals.name from work_day inner join shift_days on shift_days.idshift_days = work_day.shift_days_idshift_days inner join shifts on shift_days.shifts_idshifts = shifts.idshifts inner join festivals on festivals.idfestival = shifts.festival_idfestival inner join users on users.Id_Users = work_day.users_Id_Users where festivals.idfestival = ? and users.Id_Users = ?;"
    );
    $statement->execute([$festival, $Id_Users]);
    $res = $statement->fetchAll();
    $shift_info = "";
    $email = $res[0]["email"];
    $festival_name = $res[0]["name"];
    foreach ($res as &$shift) {
        $shift_info .=
            "<p>Van " .
            $shift["start_date"] .
            " tot " .
            $shift["shift_end"] .
            " voor " .
            $shift["cost"] .
            "euro </p>";
    }
    $subject = "All-Round Events: Update voor  " . $festival_name;
    $message =
        '<html>
		<p>Beste,</p>
		<p>Je bent ingeschreven om te komen werken op  ' .
        $festival_name .
        '. </br></p>
		<p> We hebben je ingeschreven voor volgende momenten:</p>
		' .
        $shift_info .
        "<p></p>
		<p>Indien bovenstaande data niet correct zijn of moest je niet meer kunnen komen, gelieve dan zo snel mogelijk een mail te sturen door te antwoorden op deze mail!</p>
		<p></p>
		<p>Met vriendelijke groeten</p>
		<p><small>
			All Round Events VZW
			Meester Van Der Borghtstraat 10
			2580 Putte
			BTW: BE 0886 674 723
			IBAN: BE68 7310 4460 6534
			RPR Mechelen</small></p>" .
        "</html>";
    $headers =
        "From: inschrijvingen@all-round-events.be" .
        "\r\n" .
        "Reply-To: info@all-roundevents.be" .
        "\r\n" .
        "Content-type:text/html;charset=UTF-8" .
        "\r\n" .
        "X-Mailer: PHP/" .
        phpversion();
    add_to_mail_queue($db, $email, $subject, $message, $headers, 2);

    // check if festival is open

    exit(json_encode(json_decode("{}")));
} elseif ($action == "csv_listing_festival") {
    $ID = isset($_GET["ID"]) ? $_GET["ID"] : "";
    $HASH = isset($_GET["HASH"]) ? $_GET["HASH"] : "";
    $festi_id = isset($_GET["festi_id"]) ? $_GET["festi_id"] : "";
    admin_check($ID, $HASH, $db, false);
    $statement = $db->prepare(
        "select users_data.name,  DATE(users_data.date_of_birth), users_data.driver_license from work_day inner JOIN shift_days on shift_days.idshift_days = work_day.shift_days_idshift_days inner join shifts on shifts.idshifts = shift_days.shifts_idshifts inner join festivals on festivals.idfestival = shifts.festival_idfestival inner join users_data on users_data.users_Id_Users = work_day.users_Id_Users where festivals.idfestival = ? GROUP BY work_day.users_Id_Users"
    );
    $statement->execute([$festi_id]);
    $res = $statement->fetchAll();
    header("Content-Type: text/csv");
    header('Content-Disposition: attachment; filename="deelnemers.csv"');
    $data = [];
    foreach ($res as &$user) {
        array_push(
            $data,
            $user["name"] .
                "," .
                $user["DATE(users_data.date_of_birth)"] .
                "," .
                $user["driver_license"]
        );
    }
    $fp = fopen("php://output", "wb");
    foreach ($data as $line) {
        $val = explode(",", $line);
        fputcsv($fp, $val);
    }
    fclose($fp);
} elseif ($action == "excel_listing_pukkelpop") {
    $ID = isset($_GET["ID"]) ? $_GET["ID"] : "";
    $HASH = isset($_GET["HASH"]) ? $_GET["HASH"] : "";
    $festi_id = isset($_GET["festi_id"]) ? $_GET["festi_id"] : "";
    admin_check($ID, $HASH, $db, false);
    $statement = $db->prepare(
        "select users.is_admin, users_data.name, users.email, users_data.date_of_birth, users_data.driver_license, users_data.Gender, users_data.users_Id_Users from work_day inner JOIN shift_days on shift_days.idshift_days = work_day.shift_days_idshift_days inner join shifts on shifts.idshifts = shift_days.shifts_idshifts inner join festivals on festivals.idfestival = shifts.festival_idfestival inner join users_data on users_data.users_Id_Users = work_day.users_Id_Users inner join users on users.Id_Users = work_day.users_Id_Users where festivals.idfestival = ? and (work_day.reservation_type = 3 or work_day.reservation_type = 5) GROUP BY work_day.users_Id_Users ORDER BY `users_data`.`date_of_birth` DESC;"
    );
    $statement->execute([$festi_id]);
    $res = $statement->fetchAll();
    $excel_data = [
        [
            "Voornaam",
            "Achternaam",
            "E-mail (verplicht)",
            "GSM (met landcode)",
            "Functie",
            "Geboortedatum(verplicht)(dag-maand-jaar)",
            "Rijksregisternummer(verplicht)",
            "Europees Rijkregisternummer (verplicht indien geen rijksregisternummer)",
            "Donderdag",
            "Vrijdag",
            "Zaterdag",
            "Zondag",
            "Opbouw/Afbraak",
            "Overnachten",
            "Verantwoordelijke",
            "Straat",
            "Nummer",
            "Busnummer",
            "Postcode",
            "Gemeente",
            "Postcode",
            "Land",
            "Postcode",
            "Telefoon",
            "Postcode",
            "ICE Telefoon",
            "Geslacht",
            "Geboorteplaats",
        ],
    ];

    foreach ($res as &$user) {
        $gender = "m";
        if ($user["Gender"] > 0) {
            $gender = "v";
        }
        $responsable = 0;
        $function = "crew medewerker";
        if ($user["is_admin"]) {
            $function = "verantwoordelijke";
            $responsable = 1;
        }

        $surname = split_name($user["name"])[0];
        $lastname = split_name($user["name"])[1];

        $date_of_birth_database = $user["date_of_birth"];
        $date_of_birth = date("d-m-Y", strtotime($date_of_birth_database));

        array_push($excel_data, [
            $surname,
            $lastname,
            $user["email"],
            "",
            $function,
            $date_of_birth,
            $user["driver_license"],
            "",
            "1",
            "1",
            "1",
            "1",
            "0",
            "1",
            $responsable,
            "",
            "",
            "",
            "",
            "",
            "",
            "",
            "",
            "",
            "",
            "",
            $gender,
            "",
        ]);
    }

    require "excel_lib.php";

    $xlsx = SimpleXLSXGen::fromArray($excel_data);
    $xlsx->downloadAs("pukkelpop_excel_alle_deelnemers.xlsx");
}

//
// get
//
//
elseif ($action == "csv_listing_festival_payout") {
    $ID = isset($_GET["ID"]) ? $_GET["ID"] : "";
    $HASH = isset($_GET["HASH"]) ? $_GET["HASH"] : "";
    $festi_id = isset($_GET["festi_id"]) ? $_GET["festi_id"] : "";
    admin_check($ID, $HASH, $db, false);
    $statement = $db->prepare(
        "select work_day.users_Id_Users, shift_days.cost, users_data.name, users_data.adres_line_two, festivals.name as festiname, work_day.Payout from work_day inner JOIN shift_days on work_day.shift_days_idshift_days = shift_days.idshift_days inner join shifts on shifts.idshifts = shift_days.shifts_idshifts INNER JOIN festivals on festivals.idfestival = shifts.festival_idfestival inner JOIN users_data on users_data.users_Id_Users = work_day.users_Id_Users where festivals.idfestival = ? and ((work_day.in = 1 and work_day.out = 1) or work_day.present = 1);"
    );
    $statement->execute([$festi_id]);
    $res_detail = $statement->fetchAll();

    $statement = $db->prepare(
        "select count(*), SUM(result1) from(select work_day.users_Id_Users, SUM(shift_days.cost) as result1, users_data.name, users_data.adres_line_two, festivals.name as festiname, work_day.Payout from work_day inner JOIN shift_days on work_day.shift_days_idshift_days = shift_days.idshift_days inner join shifts on shifts.idshifts = shift_days.shifts_idshifts INNER JOIN festivals on festivals.idfestival = shifts.festival_idfestival inner JOIN users_data on users_data.users_Id_Users = work_day.users_Id_Users where festivals.idfestival = ? and ((work_day.in = 1 and work_day.out = 1) or work_day.present = 1) GROUP BY work_day.users_Id_Users) src;"
    );
    $statement->execute([$festi_id]);
    $res_overview = $statement->fetchAll();
    header("Content-Type: text/xml");
    header('Content-Disposition: attachment; filename="uitbetaling.xml"');
    $date_now = date_create()->format("Y-m-d H:i:s");
    $date_now = str_replace(" ", "T", $date_now);

    $data =
        '<?xml version="1.0" encoding="utf-8" ?>
<Document xmlns="urn:iso:std:iso:20022:tech:xsd:pain.001.001.03">
  <CstmrCdtTrfInitn>
    <GrpHdr>
      <MsgId>' .
        $res_detail[0]["festiname"] .
        '</MsgId>
      <CreDtTm>' .
        $date_now .
        '</CreDtTm>
      <NbOfTxs>' .
        count($res_detail) .
        '</NbOfTxs>
      <CtrlSum>' .
        $res_overview[0]["SUM(result1)"] .
        '</CtrlSum>
      <InitgPty>
        <Nm>all-round-events</Nm>
        <Id>
          <OrgId>
            <Othr>
              <Id>0886674723</Id>
              <Issr>KBO-BCE</Issr>
            </Othr>
          </OrgId>
        </Id>
      </InitgPty>
    </GrpHdr>';
    foreach ($res_detail as &$user) {
        $bank_number = str_replace(" ", "", $user["adres_line_two"]);
		$bank_number = strtoupper($bank_number);
        $data =
            $data .
            '
    <PmtInf>
      <PmtInfId>' .
            $res_detail[0]["festiname"] .
            '</PmtInfId>
      <PmtMtd>TRF</PmtMtd>
      <BtchBookg>false</BtchBookg>
      <PmtTpInf>
        <InstrPrty>NORM</InstrPrty>
        <SvcLvl>
          <Cd>SEPA</Cd>
        </SvcLvl>
      </PmtTpInf>
      <ReqdExctnDt>' .
            date_create()->format("Y-m-d") .
            '</ReqdExctnDt>
      <Dbtr>
        <Nm>All RoundEvents</Nm>
      </Dbtr>
      <DbtrAcct>
        <Id>
          <IBAN>BE68731044606534</IBAN>
        </Id>
        <Ccy>EUR</Ccy>
      </DbtrAcct>
      <DbtrAgt>
        <FinInstnId>
          <BIC>KREDBEBB</BIC>
        </FinInstnId>
      </DbtrAgt>
      <CdtTrfTxInf>
        <PmtId>
          <EndToEndId>NOT PROVIDED</EndToEndId>
        </PmtId>
        <Amt>
          <InstdAmt Ccy="EUR">' .
            $user["cost"] .
            '</InstdAmt>
        </Amt>
        <CdtrAgt>
          <FinInstnId>
            <BIC>KREDBEBB</BIC>
          </FinInstnId>
        </CdtrAgt>
        <Cdtr>
          <Nm>' .
            $user["name"] .
            '</Nm>
          <PstlAdr>
            <Ctry>BE</Ctry>
          </PstlAdr>
        </Cdtr>
        <CdtrAcct>
          <Id>
            <IBAN>' .
            $bank_number .
            '</IBAN>
          </Id>
        </CdtrAcct>
        <RmtInf>
          <Ustrd>Vrijwilligersvergoeding: ' .
            $res_detail[0]["festiname"] .
            '</Ustrd>
        </RmtInf>
      </CdtTrfTxInf>
    </PmtInf>';
    }
    $data =
        $data .
        '
  </CstmrCdtTrfInitn>
</Document>';
    exit($data);
}

//
// get all the workdays by user
//
//
elseif ($action == "user_work_days") {
    // get the contenct from the api body
    $xml_dump = file_get_contents("php://input");
    $xml = json_decode($xml_dump, true);
    try {
        $ID = $xml["id"];
        $HASH = $xml["hash"];
        $user_id = $xml["user_id"];
    } catch (Exception $e) {
        exit(
            json_encode([
                "status" => 409,
                "error_type" => 4,
                "error_message" =>
                    "Not all fields where available, need: name, details, status, date, ID, HASH",
            ])
        );
    }
    admin_check($ID, $HASH, $db, false);
    $statement = $db->prepare(
        "select * from Images where users_Id_Users =? and is_primary = 1"
    );
    $statement->execute([$user_id]);
    $res = $statement->fetchAll();
    if (count($res) == 0) {
        exit(
            json_encode([
                "status" => 409,
                "error_type" => 8,
                "error_message" => "profile picture is needed",
            ])
        );
    }

    $statement = $db->prepare(
        "SELECT reservation_type, idshifts, shift_days.start_date, shift_days.shift_end, shifts.name, festivals.name as festiname FROM work_day INNER JOIN shift_days ON work_day.shift_days_idshift_days = shift_days.idshift_days INNER JOIN shifts ON shift_days.shifts_idshifts = shifts.idshifts INNER JOIN festivals on festivals.idfestival = shifts.festival_idfestival where work_day.users_Id_Users = ? AND festivals.status != 6 AND festivals.status != 7 and (reservation_type = 3 or reservation_type = 2);"
    );
    $statement->execute([$user_id]);
    $res = $statement->fetchAll();

    if ($res) {
        $json = json_encode($res);
        exit($json);
    } else {
        exit(json_encode(json_decode("{}")));
    }
}
//
// get all the logs;
//
//
elseif ($action == "get_api_logs") {
    // get the contenct from the api body
    $xml_dump = file_get_contents("php://input");
    $xml = json_decode($xml_dump, true);
    try {
        $ID = $xml["id"];
        $HASH = $xml["hash"];
    } catch (Exception $e) {
        exit(
            json_encode([
                "status" => 409,
                "error_type" => 4,
                "error_message" =>
                    "Not all fields where available, need: name, details, status, date, ID, HASH",
            ])
        );
    }
    admin_check($ID, $HASH, $db, false);
    $statement = $db->prepare(
        "select id,api,data,user_id,name,ip,timestamp from logs left join users_data on logs.user_id = users_data.users_Id_Users order by id desc;"
    );
    $statement->execute([]);
    $res = $statement->fetchAll();

    if ($res) {
        $json = json_encode($res);
        exit($json);
    }
    exit(json_encode(json_decode("{}")));
}
//
// get all the mails send in order
//
//
elseif ($action == "get_mail_logs") {
    // get the contenct from the api body
    $xml_dump = file_get_contents("php://input");
    $xml = json_decode($xml_dump, true);
    try {
        $ID = $xml["id"];
        $HASH = $xml["hash"];
    } catch (Exception $e) {
        exit(
            json_encode([
                "status" => 409,
                "error_type" => 4,
                "error_message" =>
                    "Not all fields where available, need: name, details, status, date, ID, HASH",
            ])
        );
    }
    admin_check($ID, $HASH, $db, false);
    $statement = $db->prepare(
        "select * from mails order by send_request desc;"
    );
    $statement->execute([]);
    $res = $statement->fetchAll();

    if ($res) {
        $json = json_encode($res);
        exit($json);
    }
    exit(json_encode(json_decode("{}")));
}

//
// get all main information from the database
// To get the information an ID and a HASH is needed, the hash only needs write access
//
elseif ($action == "get_stats") {
    $xml_dump = file_get_contents("php://input");
    $xml = json_decode($xml_dump, true);
    try {
        $ID = $xml["id"];
        $HASH = $xml["hash"];
    } catch (Exception $e) {
        exit(
            json_encode([
                "status" => 409,
                "error_type" => 4,
                "error_message" => "Not all fields where available",
            ])
        );
    }
    admin_check($ID, $HASH, $db, false);
    $statement = $db->prepare(
        "SELECT COUNT(*) FROM `logs` WHERE logs.timestamp > DATE_SUB(CURRENT_TIMESTAMP(), INTERVAL 1440 minute);"
    );
    $statement->execute([]);
    $res = $statement->fetch(PDO::FETCH_ASSOC);

    $statement = $db->prepare(
        'SELECT COUNT(*) FROM `logs` WHERE logs.timestamp > DATE_SUB(CURRENT_TIMESTAMP(), INTERVAL 1440 minute) and logs.api = "login";'
    );
    $statement->execute([]);
    $res2 = $statement->fetch(PDO::FETCH_ASSOC);

    $statement = $db->prepare(
        "SELECT COUNT(*) FROM `mails` WHERE mails.send_request > DATE_SUB(CURRENT_TIMESTAMP(), INTERVAL 1440 minute);"
    );
    $statement->execute([]);
    $res3 = $statement->fetch(PDO::FETCH_ASSOC);

    $statement = $db->prepare(
        "SELECT COUNT(*) FROM `mails` WHERE mails.send_process > DATE_SUB(CURRENT_TIMESTAMP(), INTERVAL 1440 minute);"
    );
    $statement->execute([]);
    $res4 = $statement->fetch(PDO::FETCH_ASSOC);

    $statement = $db->prepare(
        "SELECT COUNT(*) FROM `mails` where mails.send_process is null;"
    );
    $statement->execute([]);
    $res5 = $statement->fetch(PDO::FETCH_ASSOC);

    $statement = $db->prepare(
        "SELECT COUNT( DISTINCT ip) FROM `logs` WHERE logs.timestamp > DATE_SUB(CURRENT_TIMESTAMP(), INTERVAL 1440 minute);"
    );
    $statement->execute([]);
    $res6 = $statement->fetch(PDO::FETCH_ASSOC);

    $statement = $db->prepare(
        "SELECT COUNT( DISTINCT user_id) FROM `logs` WHERE logs.timestamp > DATE_SUB(CURRENT_TIMESTAMP(), INTERVAL 1440 minute);"
    );
    $statement->execute([]);
    $res7 = $statement->fetch(PDO::FETCH_ASSOC);

    $statement = $db->prepare(
        'SELECT COUNT(*) FROM `logs` WHERE logs.timestamp > DATE_SUB(CURRENT_TIMESTAMP(), INTERVAL 1440 minute) and logs.api = "cron_6b075fee6c0701feba287db06923fc54";'
    );
    $statement->execute([]);
    $res8 = $statement->fetch(PDO::FETCH_ASSOC);

    $statement = $db->prepare(
        "SELECT COUNT(*) FROM hashess WHERE hashess.begin_date > DATE_SUB(CURRENT_TIMESTAMP(), INTERVAL 1440 minute);"
    );
    $statement->execute([]);
    $res9 = $statement->fetch(PDO::FETCH_ASSOC);

    $statement = $db->prepare(
        'SELECT COUNT(*) FROM `logs` WHERE logs.timestamp > DATE_SUB(CURRENT_TIMESTAMP(), INTERVAL 1440 minute) and logs.api = "login_fail";'
    );
    $statement->execute([]);
    $res10 = $statement->fetch(PDO::FETCH_ASSOC);

    exit(
        json_encode([
            "status" => 200,
            "error_type" => 0,
            "total_api_request" => $res["COUNT(*)"],
            "total_api_login" => $res2["COUNT(*)"],
            "total_mail_request" => $res3["COUNT(*)"],
            "total_mail_send" => $res4["COUNT(*)"],
            "mails_buffered" => $res5["COUNT(*)"],
            "unique_visitors" => $res6["COUNT( DISTINCT ip)"],
            "unique_users" => $res7["COUNT( DISTINCT user_id)"],
            "cron_requests" => $res8["COUNT(*)"],
            "success_logins" => $res9["COUNT(*)"],
            "failed_logins" => $res10["COUNT(*)"],
        ])
    );
}
//
// get the current log settings
//
//
elseif ($action == "get_settings") {
    $xml_dump = file_get_contents("php://input");
    $xml = json_decode($xml_dump, true);
    try {
        $ID = $xml["id"];
        $HASH = $xml["hash"];
    } catch (Exception $e) {
        exit(
            json_encode([
                "status" => 409,
                "error_type" => 4,
                "error_message" => "Not all fields where available",
            ])
        );
    }
    admin_check($ID, $HASH, $db, false);
    $statement = $db->prepare(
        "select * from settings where settings.settings_id = 1;"
    );
    $statement->execute([]);
    $res = $statement->fetch(PDO::FETCH_ASSOC);
    if ($res) {
        $json = json_encode($res);
        exit($json);
    }
    exit(json_encode(json_decode("{}")));
}
//
// change the log/api settings
//
//
elseif ($action == "set_settings") {
    $xml_dump = file_get_contents("php://input");
    $xml = json_decode($xml_dump, true);
    try {
        $ID = $xml["id"];
        $HASH = $xml["hash"];

        $mail_interval_time = $xml["mail_interval_time"];
        $mails_per_interval = $xml["mails_per_interval"];
        $max_api_logs = $xml["max_api_logs"];
        $max_mail_logs = $xml["max_mail_logs"];
    } catch (Exception $e) {
        exit(
            json_encode([
                "status" => 409,
                "error_type" => 4,
                "error_message" => "Not all fields where available",
            ])
        );
    }
    admin_check($ID, $HASH, $db, false);
    $statement = $db->prepare(
        "update settings set mail_interval_time=?, mails_per_interval=?, max_mail_logs=?, max_api_logs=? where settings_id=1"
    );
    $statement->execute([
        $mail_interval_time,
        $mails_per_interval,
        $max_mail_logs,
        $max_api_logs,
    ]);
    exit(json_encode(json_decode("{}")));
}

//
// option to not get mails anymore that are send to all users
//
//
elseif ($action == "mail_unsubscribe") {
    // get the contenct from the api body
    $xml_dump = file_get_contents("php://input");
    $xml = json_decode($xml_dump, true);
    try {
        $ID = $xml["id"];
        $HASH = $xml["hash"];
    } catch (Exception $e) {
        exit(
            json_encode([
                "status" => 409,
                "error_type" => 4,
                "error_message" =>
                    "Not all fields where available, need: name, details, status, date, ID, HASH",
            ])
        );
    }
    token_check($ID, $HASH, $db);
    $statement = $db->prepare(
        "update users set subscribed=? where users.Id_Users=?;"
    );
    $statement->execute([0, $ID]);
    exit(json_encode(json_decode("{}")));
}
//
// subscribe to mailing list
//
//
elseif ($action == "mail_subscribe") {
    // get the contenct from the api body
    $xml_dump = file_get_contents("php://input");
    $xml = json_decode($xml_dump, true);
    try {
        $ID = $xml["id"];
        $HASH = $xml["hash"];
    } catch (Exception $e) {
        exit(
            json_encode([
                "status" => 409,
                "error_type" => 4,
                "error_message" =>
                    "Not all fields where available, need: name, details, status, date, ID, HASH",
            ])
        );
    }
    token_check($ID, $HASH, $db);
    $statement = $db->prepare(
        "update users set subscribed=? where users.Id_Users=?;"
    );
    $statement->execute([1, $ID]);
    exit(json_encode(json_decode("{}")));
}

//
// add friend to workday
//
//
elseif ($action == "add_friend") {
    // get the contenct from the api body
    $xml_dump = file_get_contents("php://input");
    $xml = json_decode($xml_dump, true);
    try {
        $ID = $xml["id"];
        $HASH = $xml["hash"];
        $shift_id = $xml["shift_id"];
        $friend_name = $xml["friend_name"];
    } catch (Exception $e) {
        exit(
            json_encode([
                "status" => 409,
                "error_type" => 4,
                "error_message" =>
                    "Not all fields where available, need: name, details, status, date, ID, HASH",
            ])
        );
    }
    token_check($ID, $HASH, $db);
    $statement = $db->prepare(
        "update work_day INNER join shift_days on shift_days.idshift_days = work_day.shift_days_idshift_days inner join shifts on shifts.idshifts = shift_days.shifts_idshifts set work_day.friend = ? where work_day.users_Id_Users = ? and shifts.idshifts = ?;"
    );
    $statement->execute([$friend_name, $ID, $shift_id]);
    exit(json_encode(json_decode("{}")));
}

//
// get all the rest access logs and the mail logs
//
//
elseif ($action == "get_logs") {
    $ID = isset($_GET["ID"]) ? $_GET["ID"] : "";
    $HASH = isset($_GET["HASH"]) ? $_GET["HASH"] : "";
    admin_check($ID, $HASH, $db, false);

    $statement = $db->prepare(
        "SELECT * FROM `logs` left join users_data on logs.user_id = users_data.users_Id_Users ORDER BY `timestamp` DESC LIMIT 5000"
    );
    $statement->execute([]);
    $res_logs = $statement->fetchAll();

    $statement = $db->prepare(
        "SELECT * FROM `mails` ORDER BY `mails`.`send_request` DESC LIMIT 5000"
    );
    $statement->execute([]);
    $mail_logs = $statement->fetchAll();
    header("Content-type: text/plain");
    header(
        "Content-Disposition: attachment; filename=all_round_events_logs.log"
    );

    // do your Db stuff here to get the content into $content
    foreach ($res_logs as &$line) {
        print $line["id"] .
            "@time:" .
            $line["timestamp"] .
            "@remoteAddress:" .
            $line["ip"] .
            "	" .
            $line["api"] .
            "	" .
            $line["data"] .
            "	" .
            $line["name"] .
            "\n";
    }
    print "\n";
    print "\n";
    print "################################################################################MAILS###################################################################################################";
    print "\n";
    foreach ($mail_logs as &$line) {
        print "\n@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@ \n";
        print $line["mail_id"] .
            "@REQUESTED:" .
            $line["send_request"] .
            " PROCCESED:	" .
            $line["send_process"] .
            "\n	prio:" .
            $line["prio"] .
            "\n   " .
            $line["address"] .
            "\n	" .
            $line["subject"] .
            "\n	" .
            $line["mail_text"] .
            "\n	" .
            $line["mail_headers"];
    }
} elseif ($action == "cron_6b075fee6c0701feba287db06923fc54") {
    // mail service. This will be hit every 2 minutes and checks if mails need to be send
    ignore_user_abort(true);
    set_time_limit(0);

    // select mails we can send
    $statement = $db->prepare("SELECT * FROM settings where settings_id = 1;");
    $statement->execute();
    $settings = $statement->fetchAll();
    // delete old logs
    $statement = $db->prepare(
        "DELETE FROM logs WHERE id NOT IN (SELECT * FROM (SELECT id FROM logs ORDER BY timestamp desc LIMIT " .
            strval($settings[0]["max_api_logs"]) .
            ") s);"
    );
    $statement->execute([]);

    // delete old mails
    $statement = $db->prepare(
        "DELETE FROM mails WHERE mail_id NOT IN (SELECT * FROM (SELECT mail_id FROM logs ORDER BY send_process desc LIMIT " .
            strval($settings[0]["max_mail_logs"]) .
            ") s);"
    );
    $statement->execute([]);

    // ask the DB how many mails where send in the lsat x min
    $statement = $db->prepare(
        "SELECT COUNT(*) FROM `mails` WHERE mails.send_process > DATE_SUB(CURRENT_TIMESTAMP(), INTERVAL " .
            strval($settings[0]["mail_interval_time"]) .
            " minute);"
    );
    $statement->execute([]);
    $res = $statement->fetchAll();

    //
    $count = intval($settings[0]["mails_per_interval"]) - $res[0]["COUNT(*)"];
    // select mails we can send
    $statement = $db->prepare(
        "SELECT * FROM mails where mails.send_process is NULL order by mails.prio asc LIMIT " .
            strval($count) .
            ";"
    );
    $statement->execute([]);
    $res = $statement->fetchAll();

    foreach ($res as &$line) {
        mail(
            $line["address"],
            $line["subject"],
            $line["mail_text"],
            $line["mail_headers"]
        );
        $statement = $db->prepare(
            "update mails set mails.send_process=now() where mails.mail_id=?;"
        );
        $statement->execute([$line["mail_id"]]);
        sleep(4);
    }

    // if mails need
    exit("Cron run OK.");
} else {
    // no string matched so we move the client to home
    header("Location: https://all-round-events.be/html/nl/home.html");
}
