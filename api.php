<?php
	error_reporting(E_ALL ^ E_DEPRECATED);
	function token_check($id, $token_user, $db) {
		//
		// Following things can happend with the token chock
		// => The token is completelyy invalid and the api is returned with an error
		// => The token gives full access and the functions returns true
		// => The token give ony read access and the function returns false
    	$statement = $db->prepare('SELECT HASH FROM mydb.users inner join mydb.hashess on mydb.hashess.users_Id_Users = mydb.users.Id_Users where Id_Users = ?');
		$statement->execute(array($id));
		$res = $statement->fetch(PDO::FETCH_ASSOC);
		$token_db = $res["HASH"];
		$type = $res["Type"];
		// check if the token excists 
		if ($token_db != $token_user){
			exit(json_encode(array(
				'status' => 409,
				'error_type' => 6,
				'error_message' => "the request was made with an invalid token or a ID/Token mismatch"
			)));
		}

		if ($type == "read/write"){
			return true;
		} 
		return false; 
		

	}
	// include DB configuration
	require_once 'config.php';

	// connect to the database 

	$db = new PDO('mysql:host=' . $host . ';dbname=' . $name . ';charset=utf8', $user, $pass);
	$db->setAttribute( PDO::ATTR_ERRMODE, PDO::ERRMODE_WARNING );

	// gets the action that needs to be performed. 
	$action = isset($_GET['action']) ? $_GET['action'] : '';

	//
	// This is the function that adds a new user to the database. 
	// It however does a lot of checks to determine if the activation is valid 
	// -> The new user needs a valid activation code 
	// -> the new user needs a email address that was never used before
	// -> the user needs a valid password 
	// !! it is the responsability of the frontend to hash the password. Never send an plain password to this function!! 
	//
	if ($action == 'new_user') {
		// get the contenct from the api body
		$xml_dump = file_get_contents('php://input');
		$xml = json_decode($xml_dump,true);
		// check if everything is in the body that we need, going further is useless without it
		try {
			$pass = $xml["pass"];
			$email = $xml["email"];
			$activation_code = (int)$xml["activation_code"];

		} catch (Exception $e) {
			exit(json_encode(array(
				'status' => 409,
				'error_type' => 4,
				'error_message' => "Not all fields where available, need: email, pass, activation_code"
			)));
			
		}
		// check if the activation code is correct
		$statement = $db->prepare('SELECT amount FROM Activation_codes WHERE  code= ?');
		$statement->execute(array($activation_code));
		$amount = $statement->fetch(PDO::FETCH_ASSOC);
		if ($amount){
			if ($amount < 1){
			exit(json_encode(array(
				'status' => 409,
				'error_type' => 5,
				'error_message' => "The activation code is valid but was already used"
			)));
			}
		}else{
			exit(json_encode(array(
				'status' => 409,
				'error_type' => 3,
				'error_message' => "The activation code is invalid"
			)));
		}

		// determine if the email is used
		$statement = $db->prepare('SELECT email FROM users WHERE email = ?');
		$statement->execute(array($email));
		$res = $statement->fetch(PDO::FETCH_ASSOC);
		if ($res){
			exit(json_encode(array(
				'status' => 409,
				'error_type' => 1,
				'error_message' => "email is already in use"
			)));
		}
		// the password stuff, check the password, create a salt and hash the stuff together
		$salt = bin2hex(mcrypt_create_iv(22, MCRYPT_DEV_URANDOM));
		if (strlen($xml["pass"]) < 5){
			exit(json_encode(array(
				'status' => 409,
				'error_type' => 2,
				'error_message' => "password must have more than 5 characters ",
				"test" => $xml["pass"]
			)));		}
		$hashed_pass = password_hash($pass . $salt, PASSWORD_DEFAULT);

		// everything is ok, save 		
		$statement = $db->prepare('INSERT INTO users (email, pass, salt) VALUES(?, ?, ?)');
		$statement->execute(array($email, $hashed_pass, $salt));
		exit(json_encode(array(
			'status' => 200,
			'error_type' => 0,
		)));		
	}
	//
	// The whole site works with an authorization token. This token is generated in this function when 
	// password and email where correct. 
	// The token can be used to get all relevant info
	// ! no usefull information is given why a login failed, 
	//
	elseif ($action == "login") {

		// get the contenct from the api body
		$xml_dump = file_get_contents('php://input');
		$xml = json_decode($xml_dump,true);
		// check if everything is in the body that we need, going further is useless without it
		try {
			$pass = $xml["pass"];
			$email = $xml["email"];

		} catch (Exception $e) {
			exit(json_encode(array(
				'status' => 409,
				'error_type' => 4,
				'error_message' => "Not all fields where available, need: email, pass"
			)));
		}
		// checking if the email is known to us, if not the login process is stoped. Due to safety reasons it is not told to the fronted
		$statement = $db->prepare('SELECT email FROM users WHERE email = ?');
		$statement->execute(array($email));
		$res = $statement->fetch(PDO::FETCH_ASSOC);
		if (!$res){

			exit(json_encode(array(
				'status' => 409,
				'error_type' => 6,
				'error_message' => "Login failed due to bad credentials"
			)));
		}
		// getting the correct password and salt from the DB
		$statement = $db->prepare('SELECT salt,pass,Id_Users FROM users WHERE email = ?');
		$statement->execute(array($email));
		$res = $statement->fetch(PDO::FETCH_ASSOC);
		$salt = $res["salt"];
		$correct_pass = $res["pass"];
		$ID = $res["Id_Users"];
		// checking password
		if (password_verify($pass . $salt, $correct_pass)) {
			// login was succesfull, check if the user already got a previous token
			$statement = $db->prepare('SELECT HASH FROM mydb.users inner join mydb.hashess on mydb.hashess.users_Id_Users = mydb.users.Id_Users where email = ?');
			$statement->execute(array($email));
			$res = $statement->fetch(PDO::FETCH_ASSOC);
			$user_hash = "FAIL";
			// no previous token excists so we make a new one
			// MAKE A TOKEN INVALID AFTER 24 HOURE IDLE TIME
			if (!$res){
				$user_hash = bin2hex(mcrypt_create_iv(44,MCRYPT_DEV_URANDOM));
				$statement = $db->prepare('INSERT INTO  hashess (HASH, Type, users_Id_Users) VALUES(?, ?, ?)');
				$statement->execute(array($user_hash, 1,$ID));
			}
			// a previous token excists, this token is deleted and a new one is made. 
			else {
				$statement = $db->prepare('DELETE FROM hashess where users_Id_Users = ?');
				$statement->execute(array($ID));
				$user_hash = bin2hex(mcrypt_create_iv(44, MCRYPT_DEV_URANDOM));
				$statement = $db->prepare('INSERT INTO  hashess (HASH, Type, users_Id_Users) VALUES(?, ?, ?)');
				$statement->execute(array($user_hash, 1, $ID));
			}
			 	exit(json_encode(array(
					'status' => 200,
					'error_type' => 0,
					'id' => $ID,
					'hash' => $user_hash
				)));
		 }
		 // the password is incorrect sp 
		 else {
		 	exit(json_encode(array(
				'status' => 409,
				'error_type' => 5,
				'error_message' => "login failed"
			)));
		 }
	}
	//
	// Logout, this is pritty useless but provides a safety feature for the end user. The token he used is now 
	// invalided so no one can use it to acces his date
	//
	elseif ($action == "logout") {
		// get the contenct from the api body
		$xml_dump = file_get_contents('php://input');
		$xml = json_decode($xml_dump, true);
		// check if all the content is in the body of the api
		try {
			$ID = $xml["ID"];
			$HASH = $xml["HASH"];

		} catch (Exception $e) {
			exit(json_encode(array(
				'status' => 409,
				'error_type' => 4,
				'error_message' => "Not all fields where available, need: ID, HASH"
			)));
		}
		// check if the token and the ID match
		token_check($ID, $HASH, $db);
		// delete the token hash from the db 
		$statement = $db->prepare('DELETE FROM hashess where users_Id_Users = ?');
		$statement->execute(array($ID)); 
		exit(json_encode(array(
			'status' => 200,
			'error_type' => 0,
			'error_message' => "Logout ok"
		)));
	}
	//
	// This api inserts or updates the main data from. Only the password, salt, Id and email cannot be changed. 
	// This api works as an update, it will overwrite everything if the content is not the same.
	// ! a valid token is needed to acces the info
	//
	elseif ($action == "insert_main") {
		// get the contenct from the api body
		$xml_dump = file_get_contents('php://input');
		$xml = json_decode($xml_dump, true);
		try {
			$ID = $xml["id"];
			$HASH = $xml["hash"];
			$name = $xml["name"];
			$date_of_birth = $xml["date_of_birth"];
			$gender = $xml["Gender"];
			$address_line_one = $xml["adres_line_one"];
			$adress_line_two = $xml["adres_line_two"];
			$driver_license = $xml["driver_license"];
			$nationality = $xml["nationality"];
			$telephone = $xml["telephone"];
			$marital_state = $xml["marital_state"];
			$text = $xml["text"];

		} catch (Exception $e) {
			exit(json_encode(array(
				'status' => 409,
				'error_type' => 4,
				'error_message' => "Not all fields where available, need: ID, HASH"
			)));
		}
		// check if the api had a valid token that has read/write property
		if (!token_check($ID, $HASH, $db)){
			exit(json_encode(array(
				'status' => 409,
				'error_type' => 7,
				'error_message' => "Token only has reading rights! "
			)));
		}
		//  put everything in the database 
		$statement = $db->prepare('UPDATE users_data set name=?, date_of_birth=?, Gender=?, adres_line_one=?, adres_line_two=?, driver_license=?, nationality=?, telephone =?, marital_state=?, text=?, profile_complete=1');
		$statement->execute(array($name, $date_of_birth, $gender, $address_line_one, $adress_line_two, $driver_license, $nationality, $telephone, $marital_state, $text)); 
		// end the api
		exit(json_encode(array(
			'status' => 200,
			'error_type' => 0
		)));
	}
	//
	// get all main information from the database
	// To get the information an ID and a HASH is needed, the hash only needs write access
	//
	elseif ($action == "get_main") {
		$xml_dump = file_get_contents('php://input');
		$xml = json_decode($xml_dump, true);
		try {
			$ID = $xml["id"];
			$HASH = $xml["hash"];

		} catch (Exception $e) {
			exit(json_encode(array(
				'status' => 409,
				'error_type' => 4,
				'error_message' => "Not all fields where available, need: ID, HASH"
			)));
		}
		token_check($ID, $HASH, $db);
		$statement = $db->prepare('SELECT * FROM users_data WHERE id = ?');
		$statement->execute(array($ID));
		$res = $statement->fetch(PDO::FETCH_ASSOC);
		exit(json_encode(array(
			'status' => 200,
			'error_type' => 0,
			'name' => $res['name'],
			'date_of_birth' => $res['date_of_birth'],
			'Gender' => $res['Gender'],
			'adres_line_one' => $res['adres_line_one'],
			'adres_line_two' => $res['adres_line_two'],
			'driver_license' => $res['driver_license'],
			'nationality' => $res['nationality'],
			'telephone' => $res['telephone'],
			'marital_state' => $res['marital_state'],
			'text' => $res['text']
		)));

	}


	
// EOF