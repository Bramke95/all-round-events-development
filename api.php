<?php
	error_reporting(E_ALL ^ E_DEPRECATED);

	function token_check($id, $token_user, $db) {
		//
		// Following things can happend with the token check
		// => The token is completely invalid and the api is returned with an error
		// => The token gives full access and the functions returns true

		if(is_null($id)){
			exit(json_encode(array(
				'status' => 409,
				'error_type' => 4,
				'error_message' => "the request was made with an invalid token or a ID/Token mismatch"
			)));
		}
		if(is_integer($id)){
			exit(json_encode(array(
				'status' => 409,
				'error_type' => 4,
				'error_message' => "the request was made with an invalid token or a ID/Token mismatch"
			)));
		}
    	$statement = $db->prepare('SELECT HASH FROM users inner join hashess on hashess.users_Id_Users = users.Id_Users where Id_Users = ?');
		$statement->execute(array($id));
		$res = $statement->fetch(PDO::FETCH_ASSOC);
		if(!$res){
				exit(json_encode(array(
				'status' => 409,
				'error_type' => 4,
				'error_message' => "the request was made with an invalid token or a ID/Token mismatch"
			)));
		}
		$token_db = $res["HASH"];
		// check if the token excists 
		if ($token_db != $token_user){
			exit(json_encode(array(
				'status' => 409,
				'error_type' => 4,
				'error_message' => "the request was made with an invalid token or a ID/Token mismatch"
			)));
		}
		return true; 
		
	}
	function admin_check($id, $token_user, $db) {
		//
		// does the same action as token_check but it also checks if the user is the admin, use this function for actions that need admin rights
		// => The token is completely invalid and the api is returned with an error
		// 
		if(is_null($id)){
			exit(json_encode(array(
				'status' => 409,
				'error_type' => 4,
				'error_message' => "the request was made with an invalid token or a ID/Token mismatch"
			)));
		}
		if(is_integer($id)){
			exit(json_encode(array(
				'status' => 409,
				'error_type' => 4,
				'error_message' => "the request was made with an invalid token or a ID/Token mismatch"
			)));
		}
    	$statement = $db->prepare('SELECT HASH,Type FROM users inner join hashess on hashess.users_Id_Users = users.Id_Users where Id_Users = ?');
		$statement->execute(array($id));
		$res = $statement->fetch(PDO::FETCH_ASSOC);
		if(!$res){
			exit(json_encode(array(
				'status' => 409,
				'error_type' => 4,
				'error_message' => "the request was made with an invalid token or a ID/Token mismatch"
			)));
		}
		$token_db = $res["HASH"];
		$admin = $res["Type"];
		// check if the token excists 
		if ($token_db != $token_user || $admin != "1"){
			exit(json_encode(array(
				'status' => 409,
				'error_type' => 4,
				'error_message' => "No admin rights"
			)));
		}
		return true; 
		
	}
	// include DB configuration
	require_once 'config.php';
	

	// connect to the database 

	$db = new PDO('mysql:host=' . $host . ';dbname=' . $name . ';charset=utf8', $user, $pass);
	$db->setAttribute( PDO::ATTR_ERRMODE, PDO::ERRMODE_WARNING );
	
	// mailing stuff 

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
				'status' => 480,
				'error_type' => 1,
				'error_message' => "email is already in use"
			)));
		}
		// the password stuff, check the password, create a salt and hash the stuff together
		$salt = bin2hex(openssl_random_pseudo_bytes(40));
		if (strlen($xml["pass"]) < 5){
			exit(json_encode(array(
				'status' => 481,
				'error_type' => 2,
				'error_message' => "password must have more than 5 characters "
			)));		}
		$hashed_pass = password_hash($pass . $salt, PASSWORD_DEFAULT);

		// everything is ok, save 		
		$statement = $db->prepare('INSERT INTO users (email, pass, salt) VALUES(?, ?, ?)');
		$statement->execute(array($email, $hashed_pass, $salt));

		$statement = $db->prepare('SELECT ID_Users from users WHERE email = ?');
		$statement->execute(array($email));
		$res = $statement->fetch(PDO::FETCH_ASSOC);
		if ($res){

			$user_hash = bin2hex(openssl_random_pseudo_bytes(40));
			$statement = $db->prepare('INSERT INTO  hashess (HASH, Type, users_Id_Users) VALUES(?, ?, ?)');
			$statement->execute(array($user_hash, 0,$res["ID_Users"]));

			exit(json_encode(array(
				'status' => 200,
				'error_type' => 0,
				'id' => $res["ID_Users"],
				'hash' => $user_hash,
			)));
		}
		exit(json_encode(array(
			'status' => 200,
			'error_type' => 10,
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
		// checking if the email is known to us, if not the login process is stoped. Due to safety reasons it is not told to the frontend
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
		$statement = $db->prepare('SELECT salt,pass,Id_Users,is_admin FROM users WHERE email = ?');
		$statement->execute(array($email));
		$res = $statement->fetch(PDO::FETCH_ASSOC);
		$salt = $res["salt"];
		$correct_pass = $res["pass"];
		$ID = $res["Id_Users"];
		$is_admin = $res["is_admin"];
		// checking password
		if (password_verify($pass . $salt, $correct_pass)) {
			// login was succesfull, check if the user already got a previous token
			$statement = $db->prepare('SELECT HASH FROM users inner join hashess on  hashess.users_Id_Users =  users.Id_Users where email = ?');
			$statement->execute(array($email));
			$res = $statement->fetch(PDO::FETCH_ASSOC);
			$user_hash = "FAIL";
			// no previous token excists so we make a new one
			if (!$res){
				$user_hash = bin2hex(openssl_random_pseudo_bytes(40));
				$statement = $db->prepare('INSERT INTO  hashess (HASH, Type, users_Id_Users) VALUES(?, ?, ?)');
				$statement->execute(array($user_hash, $is_admin,$ID));
			}
			// a previous token excists, this token is deleted and a new one is made. 
			else {
				$statement = $db->prepare('DELETE FROM hashess where users_Id_Users = ?');
				$statement->execute(array($ID));
				$user_hash = bin2hex(openssl_random_pseudo_bytes(40));
				$statement = $db->prepare('INSERT INTO  hashess (HASH, Type, users_Id_Users) VALUES(?, ?, ?)');
				$statement->execute(array($user_hash, $is_admin, $ID));
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
				'error_type' => 6,
				'error_message' => "Login failed due to bad credentials"
			)));
		 }
	}
	//
	// Logout, this is pritty useless but provides a safety feature for the end user. The token he used is now 
	// invalided so no one can use it to access his data
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
	// ! a valid token is needed to access the info
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
			exit(json_encode(array(
				'status' => 409,
				'error_type' => 4,
				'error_message' => "Not all fields where available, need: ID, HASH"
			)));
		}
		// check if the api had a valid token that has read/write property
		token_check($ID, $HASH, $db);
		// See if the user is setting new date or overwriting it : 
		$statement = $db->prepare('SELECT * FROM users_data WHERE users_Id_Users = ?');
		$statement->execute(array($ID));
		$res = $statement->fetch(PDO::FETCH_ASSOC);
		//  put everything in the database 
		if(!$res){
		$statement = $db->prepare('INSERT INTO users_data (name,size, date_of_birth, Gender, adres_line_one, adres_line_two, driver_license, nationality, telephone, marital_state, text, users_Id_Users) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)');
				$statement->execute(array($name,$size,  $date_of_birth, $gender, $address_line_one, $adress_line_two, $driver_license, $nationality, $telephone, $marital_state, $text, $employment, $ID)); 
		}
		else {
		$statement = $db->prepare('UPDATE users_data set name=?, size=?, date_of_birth=?, Gender=?, adres_line_one=?, adres_line_two=?, driver_license=?, nationality=?, telephone =?, marital_state=?, text=?, employment=? where users_Id_Users=?');
		$statement->execute(array($name, $size, $date_of_birth, $gender, $address_line_one, $adress_line_two, $driver_license, $nationality, $telephone, $marital_state, $text, $employment, $ID)); 
		}
		// end the api
		exit(json_encode(array(
			'status' => 200,
			'error_type' => 0
		)));
	}
		//
	// This api inserts or updates the main data from for other users. Only the password, salt, Id and email cannot be changed. 
	// This api works as an update, it will overwrite everything if the content is not the same.
	// This action can only be performed by the admin
	//
	elseif ($action == "insert_main_admin") {
		// get the contenct from the api body
		$xml_dump = file_get_contents('php://input');
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

		} catch (Exception $e) {
			exit(json_encode(array(
				'status' => 409,
				'error_type' => 4,
				'error_message' => "Not all fields where available, need: ID, HASH"
			)));
		}
		// check if the api had a valid token that has read/write property
		admin_check($ID, $HASH, $db);
		// See if the user is setting new date or overwriting it : 
		$statement = $db->prepare('SELECT * FROM users_data WHERE users_Id_Users = ?');
		$statement->execute(array($ID));
		$res = $statement->fetch(PDO::FETCH_ASSOC);
		//  put everything in the database 
		if(!$res){
		$statement = $db->prepare('INSERT INTO users_data (name, size, date_of_birth, Gender, adres_line_one, adres_line_two, driver_license, nationality, telephone, marital_state, text, users_Id_Users) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)');
				$statement->execute(array($name, $size, $date_of_birth, $gender, $address_line_one, $adress_line_two, $driver_license, $nationality, $telephone, $marital_state, $text, $employment, $user_id)); 
		}
		else {
		$statement = $db->prepare('UPDATE users_data set name=?, size=?, date_of_birth=?, Gender=?, adres_line_one=?, adres_line_two=?, driver_license=?, nationality=?, telephone =?, marital_state=?, text=?, employment=? where users_Id_Users=?');
		$statement->execute(array($name, $size, $date_of_birth, $gender, $address_line_one, $adress_line_two, $driver_license, $nationality, $telephone, $marital_state, $text, $employment, $user_id)); 
		}
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
		$statement = $db->prepare('SELECT * FROM users_data WHERE users_Id_Users = ?');
		$statement->execute(array($ID));
		$res = $statement->fetch(PDO::FETCH_ASSOC);

		$statement = $db->prepare('SELECT * FROM users WHERE Id_Users = ?');
		$statement->execute(array($ID));
		$res2 = $statement->fetch(PDO::FETCH_ASSOC);

		if (!$res){
			exit(json_encode(array(
				'status' => 409,
				'error_type' => 8,
				'errpr_message' => "no info found",
			)));
		}
		exit(json_encode(array(
			'status' => 200,
			'error_type' => 0,
			'name' => $res['name'],
			'date_of_birth' => $res['date_of_birth'],
			'Gender' => $res['Gender'],
			'adres_line_one' => $res['adres_line_one'],
			'adres_line_two' => $res['adres_line_two'],
			'driver_license' => $res['driver_license'],
			'employment' => $res['employment'],
			'size' => $res['size'],
			'nationality' => $res['nationality'],
			'telephone' => $res['telephone'],
			'marital_state' => $res['marital_state'],
			'email' => $res2['email'],
			'text' => $res['text']
		)));

	}

	//
	// this is where the complaints are stored, the user must not be logged in for this section. 
	// The only requirement is that all string are there. 
	//
	elseif ($action == "insert_complaint") {
		// get the contenct from the api body
		$xml_dump = file_get_contents('php://input');
		$xml = json_decode($xml_dump, true);
		try {
			$name = $xml["name"];
			$first_name = $xml["first_name"];
			$type = $xml["type"];
			$text = $xml["text"];
			

		} catch (Exception $e) {
			exit(json_encode(array(
				'status' => 409,
				'error_type' => 4,
				'error_message' => "Not all fields where available, need: name, first_name, type, text"
			)));
		}
		// entering the complaint in the DB
		$statement = $db->prepare('INSERT INTO complains (name, first_name, type, text) VALUES (?,?,?,?)');
		$statement->execute(array($name, $first_name, $type, $text));
		exit(json_encode(array(
			'status' => 200,
			'error_type' => 0
		)));
	}

	//
	// this if statement adds a education to the databse 
	// the record is added for one specific devide 
	//
	elseif ($action == "add_education") {
				// get the contenct from the api body
		$xml_dump = file_get_contents('php://input');
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
		$statement = $db->prepare('INSERT INTO educations (from_date, to_date, school, education, percentage, users_Id_Users) VALUES (?,?,?,?,?,?)');
				$statement->execute(array($from, $to, $school, $education, $percentage, $ID)); 
		

		// end the api
		exit(json_encode(array(
			'status' => 200,
			'error_type' => 100
		)));
	}

	// 
	// gets a list of all the educations 
	//
	//
	elseif ($action == "get_education") {
		// get all the info from the api 
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
		// check if the user is and token is valid 
		token_check($ID, $HASH, $db);

		$statement = $db->prepare('SELECT * FROM educations WHERE users_Id_Users = ?');
		$statement->execute(array($ID));
		$res = $statement->fetchAll();

		if ($res){
			$json = json_encode($res);
			exit($json);
		}
		exit(json_encode(array(
			'status' => 200,
			'error_type' => 4,
			'error_message' => "No education found"
		)));
		
	}	

	elseif ($action == "delete_education") {
		// get all the info from the api 
		$xml_dump = file_get_contents('php://input');
		$xml = json_decode($xml_dump, true);
		try {
			$ID = $xml["id"];
			$HASH = $xml["hash"];
			$edu_id = $xml["education_id"];

		} catch (Exception $e) {
			exit(json_encode(array(
				'status' => 409,
				'error_type' => 4,
				'error_message' => "Not all fields where available, need: ID, HASH"
			)));
		}
		// check if the user is and token is valid 
		token_check($ID, $HASH, $db);
		$statement = $db->prepare('DELETE FROM educations WHERE users_Id_Users = ? AND ideducations_id = ?');
		$statement->execute(array($ID, $edu_id));
		// end the api
		exit(json_encode(array(
			'status' => 200,
			'error_type' => 100
		)));
	}

	//
	// this if statement adds a education to the databse 
	// the record is added for one specific devide 
	//
	elseif ($action == "add_language") {
				// get the contenct from the api body
		$xml_dump = file_get_contents('php://input');
		$xml = json_decode($xml_dump, true);
		try {
			$ID = $xml["id"];
			$HASH = $xml["hash"];
			$lang = $xml["lang"];
			$speak = $xml["speak"];
			$write = $xml["write"];
			$read = $xml["read"];


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
		$statement = $db->prepare('INSERT INTO language (language, speaking, writing, reading, users_Id_Users) VALUES (?,?,?,?,?)');
		$statement->execute(array($lang, $speak, $write, $read, $ID)); 
		

		// end the api
		exit(json_encode(array(
			'status' => 200,
			'error_type' => 100
		)));
	}

	// 
	// gets a list of all the languages
	//
	//
	elseif ($action == "get_languages") {
		// get all the info from the api 
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
		// check if the user is and token is valid 
		token_check($ID, $HASH, $db);

		$statement = $db->prepare('SELECT * FROM language WHERE users_Id_Users = ?');
		$statement->execute(array($ID));
		$res = $statement->fetchAll();

		if ($res){
			$json = json_encode($res);
			exit($json);
		}
		exit(json_encode(array(
			'status' => 200,
			'error_type' => 4,
			'error_message' => "No languages found"
		)));
		
	}	

	elseif ($action == "delete_language") {
		// get all the info from the api 
		$xml_dump = file_get_contents('php://input');
		$xml = json_decode($xml_dump, true);
		try {
			$ID = $xml["id"];
			$HASH = $xml["hash"];
			$language_id = $xml["language_id"];

		} catch (Exception $e) {
			exit(json_encode(array(
				'status' => 409,
				'error_type' => 4,
				'error_message' => "Not all fields where available, need: ID, HASH"
			)));
		}
		// check if the user is and token is valid 
		token_check($ID, $HASH, $db);
		$statement = $db->prepare('DELETE FROM language WHERE users_Id_Users = ? AND language_id = ?');
		$statement->execute(array($ID, $language_id));
		// end the api
		exit(json_encode(array(
			'status' => 200,
			'error_type' => 100
		)));
	}

	//
	// this if statement adds a expierences to the databse 
	// the record is added for one specific devide 
	//
	elseif ($action == "add_expierence") {
				// get the contenct from the api body
		$xml_dump = file_get_contents('php://input');
		$xml = json_decode($xml_dump, true);
		try {
			$ID = $xml["id"];
			$HASH = $xml["hash"];
			$company = $xml["company"];
			$jobtitle = $xml["jobtitle"];
			$from_date = $xml["from_date"];
			$to_date = $xml["to_date"];


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
		$statement = $db->prepare('INSERT INTO expierence (compamy, jobtitle, from_date, to_date, users_Id_Users) VALUES (?,?,?,?,?)');
		$statement->execute(array($company, $jobtitle, $from_date, $to_date, $ID)); 
		// end the api
		exit(json_encode(array(
			'status' => 200,
			'error_type' => 100
		)));
	}

	// 
	// gets a list of all the expierences
	//
	//
	elseif ($action == "get_expierence") {
		// get all the info from the api 
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
		// check if the user is and token is valid 
		token_check($ID, $HASH, $db);

		$statement = $db->prepare('SELECT * FROM expierence WHERE users_Id_Users = ?');
		$statement->execute(array($ID));
		$res = $statement->fetchAll();

		if ($res){
			$json = json_encode($res);
			exit($json);
		}
		exit(json_encode(array(
			'status' => 200,
			'error_type' => 4,
			'error_message' => "No languages found"
		)));
		
	}	
	//
	// delete expierences from db
	//
	elseif ($action == "delete_expierence") {
		// get all the info from the api 
		$xml_dump = file_get_contents('php://input');
		$xml = json_decode($xml_dump, true);
		try {
			$ID = $xml["id"];
			$HASH = $xml["hash"];
			$language_id = $xml["idexpierence"];

		} catch (Exception $e) {
			exit(json_encode(array(
				'status' => 409,
				'error_type' => 4,
				'error_message' => "Not all fields where available, need: ID, HASH"
			)));
		}
		// check if the user is and token is valid 
		token_check($ID, $HASH, $db);
		$statement = $db->prepare('DELETE FROM expierence WHERE users_Id_Users = ? AND idexpierence = ?');
		$statement->execute(array($ID, $language_id));
		// end the api
		exit(json_encode(array(
			'status' => 200,
			'error_type' => 100
		)));
	}
	//
	// adding an picture to the DB 
	// The image is stored on the local file system. Only the name and location 
	//
	elseif ($action == "upload_picture"){
		$xml_dump = json_encode(json_decode($_POST["auth"]));
		$xml = json_decode($xml_dump, true);
		$is_primary = 0; 
		try {
			$ID = $xml["ID"];
			$HASH = $xml["TOKEN"];

		} catch (Exception $e) {
			exit(json_encode(array(
				'status' => 409,
				'error_type' => 4,
				'error_message' => "Not all fields where available, need: ID, HASH"
			)));
		}
		// check if the user is and token is valid 
		token_check($ID, $HASH, $db);
		

		$ID = str_replace('"', "", $ID);
		$statement = $db->prepare('SELECT COUNT(*) FROM Images WHERE users_Id_Users = ?;');
		$statement->execute(array((int)$ID));
		$res = $statement->fetch(PDO::FETCH_ASSOC);
		$count = $res["COUNT(*)"];
		if ($count > 4){
			exit(json_encode(array(
				'status' => 409,
				'error_type' => 9,
				'error_message => "Only 5 pictures allowed!"'
			)));
		}
		if ($count == 0){
			$is_primary = 1;
		}	
		$random_hash = bin2hex(openssl_random_pseudo_bytes(32));
		$target_dir = "upload/";
		$target_file = $target_dir . basename($_FILES["img"]["name"]);
		$uploadOk = 1;
		$imageFileType = strtolower(pathinfo($target_file,PATHINFO_EXTENSION));
		$target_file = $target_dir . $random_hash . ".". $imageFileType;
		if(isset($_POST["submit"])) {
	    	$check = getimagesize($_FILES["img"]["tmp_name"]);
	    	if($check !== false) {
	        	echo "File is an image - " . $check["mime"] . ".";
	        	$uploadOk = 1;
	    	} else {
	        		echo "File is not an image.";
	        		$uploadOk = 0;
	    	}
		}

		// Check file size
		//throw new Exception($_FILES);
		if ($_FILES["fileToUpload"]["size"] > 250000) {
	    	echo "Sorry, your file is too large.";
	    	$uploadOk = 0;
			}
		// Allow certain file formats
		if($imageFileType != "jpg" && $imageFileType != "png" && $imageFileType != "jpeg"
		&& $imageFileType != "gif" ) {
	    	exit(json_encode(array(
				'status' => 409,
				'error_type' => 11,
				'error_message => "Not a valid format"'
			)));
	    	$uploadOk = 0;
		}
		// Check if $uploadOk is set to 0 by an error
		if ($uploadOk == 0) {
	    		echo "Sorry, your file was not uploaded.";
				// if everything is ok, try to upload file
		} else {
	    	if (move_uploaded_file($_FILES["img"]["tmp_name"], $target_file)) {
	    			$ID = str_replace('"', "", $ID);
	        		$statement = $db->prepare('INSERT INTO Images (picture_name, is_primary, users_Id_Users) VALUES (?,?,?)');
					$statement->execute(array($target_file,(int)$is_primary, (int)$ID)); 
					exit(json_encode(array(
						'status' => 200,
						'error_type' => 0,
						'error_message' => "OK, image uploaded"
					)));
	    	} else {
	        	echo "Sorry, there was an error uploading your file.";
	    	}
		}
	}
	//
	// This function gets all the pictures locactions from the user id
	// 
	//
	elseif ($action == "get_pictures"){
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
		// check if the user is and token is valid 
		token_check($ID, $HASH, $db);

		$statement = $db->prepare('SELECT picture_name, is_primary  FROM Images WHERE users_Id_Users = ?');
		$statement->execute(array($ID));
		$res = $statement->fetchAll();

		if ($res){
			$json = json_encode($res);
			exit($json);
		}
		exit(json_encode(array(
			'status' => 200,
			'error_type' => 4,
			'error_message' => "No pictures found"
		)));

	}
	// delete a picture from a used
	//
	//
	elseif ($action == "delete_picture"){	
		$xml_dump = file_get_contents('php://input');
		$xml = json_decode($xml_dump, true);
		try {
			$ID = $xml["id"];
			$HASH = $xml["hash"];
			$picture = $xml["image"];

		} catch (Exception $e) {
			exit(json_encode(array(
				'status' => 409,
				'error_type' => 4,
				'error_message' => "Not all fields where available, need: ID, HASH"
			)));
		}
		// check if the user is and token is valid 
		token_check($ID, $HASH, $db);
		
		if (file_exists($picture)) {
			unlink($picture);
		}
		$statement = $db->prepare('select is_primary from Images WHERE users_Id_Users = ? and picture_name = ?');
		$statement->execute(array($ID, $picture));
		$res = $statement->fetch(PDO::FETCH_ASSOC);
		if ($res) {
			if ($res["is_primary"] == 1){
				$statement = $db->prepare('select picture_name from Images WHERE users_Id_Users = ? AND is_primary !=1 LIMIT 1');
				$statement->execute(array($ID));
				$res = $statement->fetch(PDO::FETCH_ASSOC);
				$name = $res["picture_name"];	
				$statement = $db->prepare('UPDATE Images set is_primary=1 where users_Id_Users=? and picture_name=?');
				$statement->execute(array((int)$ID, $name)); 		
			}
		}
		
		$statement = $db->prepare('DELETE FROM Images WHERE users_Id_Users = ? and picture_name = ?');
		$statement->execute(array($ID, $picture));

		
		exit(json_encode(array(
			'status' => 200,
			'error_type' => 0,
			'error_message' => "ok"
		)));
		
	}
	// maka another picture the profile
	//
	//
	elseif ($action == "make_profile"){
		$xml_dump = file_get_contents('php://input');
		$xml = json_decode($xml_dump, true);
		try {
			$ID = $xml["id"];
			$HASH = $xml["hash"];
			$picture = $xml["image"];

		} catch (Exception $e) {
			exit(json_encode(array(
				'status' => 409,
				'error_type' => 4,
				'error_message' => "Not all fields where available, need: ID, HASH"
			)));
		}
		// check if the user is and token is valid 
		token_check($ID, $HASH, $db);
		if (file_exists($picture)) {
			$statement = $db->prepare('UPDATE Images set is_primary=0 where users_Id_Users=?');
			$statement->execute(array((int)$ID)); 
			$statement = $db->prepare('UPDATE Images set is_primary=1 where users_Id_Users=? and picture_name=?');
			$statement->execute(array((int)$ID, $picture)); 
			exit(json_encode(array(
				'status' => 200,
				'error_type' => 0,
				'error_message' => "OK"
			)));
		}else{
			exit(json_encode(array(
				'status' => 200,
				'error_type' => 10,
				'error_message' => "File does not excists"
			)));
		}
	}
	
	
	// returns ok if the user is admin, alse not, this can be used to check if certain functionallity needs to be visable or not
	//
	//
	elseif ($action == "is_admin"){
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
		// check if the user is and token is valid and it is admin
		admin_check($ID, $HASH, $db);
		exit(json_encode(array(
			'status' => 200,
			'error_type' => 0,
			'error_message' => "person is admin"
		)));
	}
	
	// add an evenement to the database, 
	//
	//
	elseif ($action == "is_admin"){
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
		// check if the user is and token is valid and it is admin
		admin_check($ID, $HASH, $db);
		exit(json_encode(array(
			'status' => 200,
			'error_type' => 0,
			'error_message' => "person is admin"
		)));
	}
	
	
	//
	// This action adds a festival/evenement to the database, This only adds the pure evenement in the database, not the shifts/days
	// This action can only be performed by an administrator
	//
	elseif ($action == "add_festival") {
		// get the contenct from the api body
		$xml_dump = file_get_contents('php://input');
		$xml = json_decode($xml_dump, true);
		try {
			$ID = $xml["id"];
			$HASH = $xml["hash"];
			$date = $xml["date"];
			$status = $xml["status"];
			$name = $xml["name"];
			$details = $xml["festival_discription"];
		} catch (Exception $e) {
			exit(json_encode(array(
				'status' => 409,
				'error_type' => 4,
				'error_message' => "Not all fields where available, need: name, details, status, date, ID, HASH"
			)));
		}
		// this is an admin action, check if this is an admin
		admin_check($ID, $HASH, $db);
		// entering the complaint in the DB
		$statement = $db->prepare('INSERT INTO festivals (date, details, status, name, full_shifts) VALUES (?,?,?,?,?)');
		$statement->execute(array($date, $details, $status, $name, 0));
		
		$statement = $db->prepare('SELECT * FROM festivals WHERE status != 6 and status != 7');
		$statement->execute(array($ID));
		$res = $statement->fetchAll();

		if ($res){
			$json = json_encode($res);
			exit($json);
		}
		
		exit(json_encode(array(
			'status' => 200,
			'error_type' => 0
		)));
	}
	//
	// get a list of all the festivals 
	//
	elseif ($action == "get_festivals") {
		$xml_dump = file_get_contents('php://input');
		$xml = json_decode($xml_dump, true);
		try {
			$ID = $xml["id"];
			$HASH = $xml["hash"];
			$type = $xml["select"];
			$festi_id = $xml["festi_id"];

		} catch (Exception $e) {
			exit(json_encode(array(
				'status' => 409,
				'error_type' => 4,
				'error_message' => "Not all fields where available, need: ID, HASH"
			)));
		}
		$query = '';
		if ($type == "select"){
			$query ='SELECT * FROM festivals WHERE idfestival = ? ;';
			token_check($ID, $HASH, $db);
		}
		else if ("active"){
			$query ='SELECT * FROM festivals WHERE status != 6 and status != 7;';
		}
		else {
			$query ='SELECT * FROM festivals;';
			token_check($ID, $HASH, $db);
		}
		
		
		$statement = $db->prepare($query);
		$statement->execute(array($festi_id));
		$res = $statement->fetchAll();

		if ($res){
			$json = json_encode($res);
			exit($json);
		}
		exit(json_encode (json_decode ("{}")));
	}
	
	//
	// This action changes the date of a festival/evenement. IMPORTAND, it does not change the status, this is another api (Because changing the status has a lot of other results)
	// This action can only be performed by an administrator
	//
	elseif ($action == "change_festival_data") {
		// get the contenct from the api body
		$xml_dump = file_get_contents('php://input');
		$xml = json_decode($xml_dump, true);
		try {
			$ID = $xml["id"];
			$HASH = $xml["hash"];
			$date = $xml["date"];
			$name = $xml["festiname"];
			$idfestival = $xml["idfestival"];
			$details = $xml["festival_discription"];
		} catch (Exception $e) {
			exit(json_encode(array(
				'status' => 409,
				'error_type' => 4,
				'error_message' => "Not all fields where available, need: name, details, status, date, ID, HASH"
			)));
		}
		// this is an admin action, check if this is an admin
		admin_check($ID, $HASH, $db);
		// changing the festival data
		$statement = $db->prepare('UPDATE festivals SET date=?, details=?, name=? where idfestival=?;');
		$statement->execute(array($date, $details, $name,$idfestival));
	
		exit(json_encode(array(
			'status' => 200,
			'error_type' => 0
		)));
	}
	
	//
	// This action adds a shift to the festival, this is the middel of the logic (Festivals -> Shifts -> Days)
	// This action can only be performed by an administrator
	//
	elseif ($action == "add_shift") {
		// get the contenct from the api body
		$xml_dump = file_get_contents('php://input');
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
			exit(json_encode(array(
				'status' => 409,
				'error_type' => 4,
				'error_message' => "Not all fields where available, need: name, details, status, date, ID, HASH"
			)));
		}
		// this is an admin action, check if this is an admin
		admin_check($ID, $HASH, $db);
		// entering the complaint in the DB
		$statement = $db->prepare('INSERT INTO shifts (name, datails, length, people_needed, spare_needed, festival_idfestival) VALUES (?,?,?,?,?,?)');
		$statement->execute(array($name ,$discription,$length, $needed, $reserve,  $festi_id));
		
		$statement = $db->prepare('SELECT shifts.name,shifts.details,shifts.length,shifts.people_needed,shifts.spare_needed,shifts.festival_idfestival  FROM shifts inner join festivals on shifts.festival_idfestival = festivals.idfestival where festivals.status != 6 or festivals.status != 7;');
		$statement->execute(array($festi_id));
		$res = $statement->fetchAll();

		if ($res){
			$json = json_encode($res);
			exit($json);
		}
		
		exit(json_encode(array(
			'status' => 200,
			'error_type' => 0
		)));
	}
	
	//
	// get a list of all the shifts that are active
	//
	elseif ($action == "get_shifts") {
		// Todo => add data for reserve, full or not
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
		$statement = $db->prepare('SELECT festivals.status, festivals.name AS "festiname", shifts.idshifts , shifts.name,shifts.datails,shifts.length,shifts.people_needed,shifts.spare_needed,shifts.festival_idfestival  FROM shifts inner join festivals on shifts.festival_idfestival = festivals.idfestival where festivals.status != 6 and festivals.status != 7;');
		$statement->execute();
		$counter = 0;
		while ($row = $statement->fetch(PDO::FETCH_ASSOC)) {
			$statement2 = $db->prepare('select count(distinct users_Id_Users) from work_day inner join shift_days on work_day.shift_days_idshift_days = shift_days.idshift_days where shift_days.shifts_idshifts = ? and work_day.reservation_type != 5');
			$statement2->execute(array($row["idshifts"]));
			$res2 = $statement2->fetchAll();
			$row["subscribed"] = $res2[0]["count(distinct users_Id_Users)"];
			
			$statement2 = $db->prepare('select count(distinct users_Id_Users) from work_day inner join shift_days on work_day.shift_days_idshift_days = shift_days.idshift_days where shift_days.shifts_idshifts = ? and work_day.reservation_type = 3;');
			$statement2->execute(array($row["idshifts"]));
			$res2 = $statement2->fetchAll();
			$row["subscribed_final"] = $res2[0]["count(distinct users_Id_Users)"];


			$statement3 = $db->prepare('select * from shift_days where 	shifts_idshifts=?');
			$statement3->execute(array($row["idshifts"]));
			$res3 = $statement3->fetchAll();
			$row["work_days"] = count($res3);

			$statement4 = $db->prepare('select * from locations where shift_id=?');
			$statement4->execute(array($row["idshifts"]));
			$res4 = $statement4->fetchAll();
			$row["external_meeting_locations"] = count($res4);  
			
			$res[$counter] = $row;
			$counter++;			
			
		}

		if ($res){
			$json = json_encode($res);
			exit($json);
		}
		exit(json_encode (json_decode ("{}")));
	}
	
		//
	// get a list of all the shifts
	//
	elseif ($action == "get_shift") {
		$xml_dump = file_get_contents('php://input');
		$xml = json_decode($xml_dump, true);
		try {
			$ID = $xml["id"];
			$HASH = $xml["hash"];
			$shift_id = $xml["idshifts"];

		} catch (Exception $e) {
			exit(json_encode(array(
				'status' => 409,
				'error_type' => 4,
				'error_message' => "Not all fields where available, need: ID, HASH"
			)));
		}
		token_check($ID, $HASH, $db);
		$statement = $db->prepare('SELECT * FROM shifts where idshifts = ?');
		$statement->execute(array($shift_id));
		$res = $statement->fetchAll();


		if ($res){
			$json = json_encode($res);
			exit($json);
		}
		else {
			exit(json_encode (json_decode ("{}")));
		}
	}
	
	//
	// This action changes the date of a festival/evenement shift. IMPORTAND, it does not change the status, this is another api (Because changing the status has a lot of other results)
	// This action can only be performed by an administrator
	//
	elseif ($action == "change_shift") {
		// get the contenct from the api body
		$xml_dump = file_get_contents('php://input');
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
			exit(json_encode(array(
				'status' => 409,
				'error_type' => 4,
				'error_message' => "Not all fields where available, need: name, details, status, date, ID, HASH"
			)));
		}
		// this is an admin action, check if this is an admin
		admin_check($ID, $HASH, $db);
		// changing the festival data
		$statement = $db->prepare('UPDATE shifts SET name=?, datails=?, people_needed=? , spare_needed=? , length=? WHERE idshifts=?;');
		$statement->execute(array($name, $details, $people, $reserve, $days,$idshifts));
	
		exit(json_encode(array(
			'status' => 200,
			'error_type' => 0
		)));
	}
	//
	// This action deletes a shift, this can only happen when no user are connected to the shift, these users need te be deleted before this action can take place
	// This is an admin action, only an admin can perform this 
	//
	elseif ($action == "delete_shift") {
		// get the contenct from the api body
		$xml_dump = file_get_contents('php://input');
		$xml = json_decode($xml_dump, true);
		try {
			$ID = $xml["id"];
			$HASH = $xml["hash"];
			$idshifts = $xml["idshifts"];
		} catch (Exception $e) {
			exit(json_encode(array(
				'status' => 409,
				'error_type' => 4,
				'error_message' => "Not all fields where available, need: name, details, status, date, ID, HASH"
			)));
		}
		// this is an admin action, check if this is an admin
		admin_check($ID, $HASH, $db);
		
		$statement = $db->prepare('SELECT * FROM work_day where shift_days_idshift_days = ?');
		$statement->execute(array($idshifts));
		$res = $statement->fetchAll();


		if ($res){
			exit(json_encode(array(
				'status' => 409,
				'error_type' => 10,
				'error_message' => "one or more people are registered in this shift, you have to delete them first"
			)));
		}
		else {
			
			$statement = $db->prepare('DELETE FROM shifts WHERE idshifts=?;');
			$statement->execute(array($idshifts));
			exit(json_encode(array(
				'status' => 200,
				'error_type' => 0
			)));
			
		}
	}
	//
	// This action adds a shift to the shift day, this is the logic (Festivals -> Shifts -> Days)
	// This action can only be performed by an administrator
	//
	elseif ($action == "add_shift_day") {
		// get the contenct from the api body
		$xml_dump = file_get_contents('php://input');
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
			exit(json_encode(array(
				'status' => 409,
				'error_type' => 4,
				'error_message' => "Not all fields where available, need: name, details, status, date, ID, HASH"
			)));
		}
		// this is an admin action, check if this is an admin
		admin_check($ID, $HASH, $db);
		// entering the complaint in the DB
		$statement = $db->prepare('INSERT INTO shift_days (cost, start_date, shift_end, length, shifts_idshifts) VALUES (?,?,?,?,?)');
		$statement->execute(array($money ,$start, $stop, $length, $shifts_idshifts));
		
		
		exit(json_encode(array(
			'status' => 200,
			'error_type' => 0
		)));
	}
	//
	// returns a list of al the shift days available for only active festivals. 
	//
	//
	elseif ($action == "get_shift_days_admin") {
		// get the contenct from the api body
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
		// this is an admin action, check if this is an admin
		admin_check($ID, $HASH, $db);
		$statement = $db->prepare('SELECT festivals.idfestival, festivals.status, shifts.idshifts, shift_days.cost, shift_days.idshift_days, shift_days.shift_end, shift_days.start_date, shifts.name FROM shift_days inner join shifts on shifts.idshifts = shift_days.shifts_idshifts inner join festivals on festivals.idfestival = shifts.festival_idfestival where festivals.status != 6 AND festivals.status != 7;');
		$statement->execute(array());
		$counter = 0;
		while ($row = $statement->fetch(PDO::FETCH_ASSOC)) {

			$statement3 = $db->prepare('select * from work_day where work_day.shift_days_idshift_days = ?');
			$statement3->execute(array($row["idshift_days"]));
			$res3 = $statement3->fetchAll();
			$row["users_total"] = count($res3);
			$res[$counter] = $row;
			$counter++;	
		}




		if ($res){
			$json = json_encode($res);
			exit($json);
		}
		else {
			exit(json_encode (json_decode ("{}")));
		}
	}
	elseif ($action == "get_shift_days") {
		// get the contenct from the api body
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
		// this is an admin action, check if this is an admin
		token_check($ID, $HASH, $db);
		$statement = $db->prepare('SELECT festivals.idfestival, festivals.status, shifts.idshifts, shift_days.cost, shift_days.idshift_days, shift_days.shift_end, shift_days.start_date, shifts.name FROM shift_days inner join shifts on shifts.idshifts = shift_days.shifts_idshifts inner join festivals on festivals.idfestival = shifts.festival_idfestival where festivals.status != 6 AND festivals.status != 7;');
		$statement->execute(array());
		$res = $statement->fetchAll();
		if ($res){
			$json = json_encode($res);
			exit($json);
		}
		else {
			exit(json_encode (json_decode ("{}")));
		}
	}
	//
	// returns all information about one shift day
	//
	//
	elseif ($action == "get_shift_day") {
		// get the contenct from the api body
		$xml_dump = file_get_contents('php://input');
		$xml = json_decode($xml_dump, true);
		try {
			$ID = $xml["id"];
			$HASH = $xml["hash"];
			$shift_days_id = $xml["shift_day_id"];

		} catch (Exception $e) {
			exit(json_encode(array(
				'status' => 409,
				'error_type' => 4,
				'error_message' => "Not all fields where available, need: name, details, status, date, ID, HASH"
			)));
		}
		// this is an admin action, check if this is an admin
		token_check($ID, $HASH, $db);
		$statement = $db->prepare('SELECT * FROM shift_days WHERE idshift_days=? ');
		$statement->execute(array($shift_days_id));
		$res = $statement->fetchAll();
		if ($res){
			$json = json_encode($res);
			exit($json);
		}
		else {
			exit(json_encode (json_decode ("{}")));
		}
	}
	
	//
	// This action changes the date of a festival/evenement shift day. IMPORTAND, it does not change the status, this is another api (Because changing the status has a lot of other results)
	// This action can only be performed by an administrator
	//
	elseif ($action == "change_shift_day") {
		// get the contenct from the api body
		$xml_dump = file_get_contents('php://input');
		$xml = json_decode($xml_dump, true);
		try {
			
			$ID = $xml["id"];
			$HASH = $xml["hash"];
			$start = $xml["start"];
			$stop = $xml["stop"];
			$money = $xml["money"];
			$shift_days_id = $xml["shift_day_id"];
			
		} catch (Exception $e) {
			exit(json_encode(array(
				'status' => 409,
				'error_type' => 4,
				'error_message' => "Not all fields where available, need: name, details, status, date, ID, HASH"
			)));
		}
		// this is an admin action, check if this is an admin
		admin_check($ID, $HASH, $db);
		// changing the festival data
		$statement = $db->prepare('UPDATE shift_days SET cost=?, start_date=?, shift_end=? WHERE idshift_days=?;');
		$statement->execute(array($money, $start, $stop,$shift_days_id));		
		exit(json_encode(array(
			'status' => 200,
			'error_type' => 0
		)));
	}
	
	//
	// This action deletes a shift, this can only happen when no user are connected to the shift, these users need te be deleted before this action can take place
	// This is an admin action, only an admin can perform this 
	//
	elseif ($action == "delete_shift_day") {
		// get the contenct from the api body
		$xml_dump = file_get_contents('php://input');
		$xml = json_decode($xml_dump, true);
		try {
			$ID = $xml["id"];
			$HASH = $xml["hash"];
			$shift_day_id = $xml["shift_day_id"];
		} catch (Exception $e) {
			exit(json_encode(array(
				'status' => 409,
				'error_type' => 4,
				'error_message' => "Not all fields where available, need: name, details, status, date, ID, HASH"
			)));
		}
		// this is an admin action, check if this is an admin
		admin_check($ID, $HASH, $db);
		
		$statement = $db->prepare('SELECT * FROM work_day where shift_days_idshift_days = ?');
		$statement->execute(array($shift_day_id));
		$res = $statement->fetchAll();


		if ($res){
			exit(json_encode(array(
				'status' => 409,
				'error_type' => 10,
				'error_message' => "one or more people are registered in this shift, you have to delete them first"
			)));
		}
		else {
			
			$statement = $db->prepare('DELETE FROM shift_days WHERE idshift_days=?;');
			$statement->execute(array($shift_day_id));
			exit(json_encode(array(
				'status' => 200,
				'error_type' => 0
			)));
		}
	}
	//
	// get all workdays for the user that is doing the api, this prevents leaking information from other users
	//
	//
	elseif ($action == "shift_work_days") {
		// get the contenct from the api body
		$xml_dump = file_get_contents('php://input');
		$xml = json_decode($xml_dump, true);
		try {
			$ID = $xml["id"];
			$HASH = $xml["hash"];
		} catch (Exception $e) {
			exit(json_encode(array(
				'status' => 409,
				'error_type' => 4,
				'error_message' => "Not all fields where available, need: name, details, status, date, ID, HASH"
			)));
		}
		token_check($ID, $HASH, $db);
		$statement = $db->prepare('select * from Images where users_Id_Users =? and is_primary = 1');
		$statement->execute(array($ID));
		$res = $statement->fetchAll();
		if(count($res) == 0){
			exit(json_encode(array(
				'status' => 409,
				'error_type' => 8,
				'error_message' => "profile picture is needed"
			)));
		}
		
		$statement = $db->prepare('SELECT reservation_type, idshifts FROM work_day INNER JOIN shift_days ON work_day.shift_days_idshift_days = shift_days.idshift_days INNER JOIN shifts ON shift_days.shifts_idshifts = shifts.idshifts INNER JOIN festivals on festivals.idfestival = shifts.festival_idfestival where work_day.users_Id_Users = ? AND festivals.status != 6 AND festivals.status != 7');
		$statement->execute(array($ID));
		$res = $statement->fetchAll();
		
		if ($res){
			$json = json_encode($res);
			exit($json);
		}
		else {
			exit(json_encode (json_decode ("{}")));
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
		$xml_dump = file_get_contents('php://input');
		$xml = json_decode($xml_dump, true);
		$people_subscribed = 0;
		$overrule = false;
		try {
			$ID = $xml["id"];
			$HASH = $xml["hash"];
			$shift_id = $xml["idshifts"];
			$Id_Users = $xml["Id_Users"];
			
		} catch (Exception $e) {
			exit(json_encode(array(
				'status' => 409,
				'error_type' => 4,
				'error_message' => "Not all fields where available, need: name, details, status, date, ID, HASH"
			)));
		}
		if($Id_Users == "admin"){
			$Id_Users = $ID;
			$overrule = true;
		}
		token_check($ID, $HASH, $db);

		$statement = $db->prepare('SELECT * FROM users_data WHERE users_Id_Users = ?');
		$statement->execute(array($ID));
		$res = $statement->fetch(PDO::FETCH_ASSOC);

		if (!$res){
			exit(json_encode(array(
				'status' => 409,
				'error_type' => 8,
				'errpr_message' => "no info found, fill in user data",
			)));
		}

		
		$statement = $db->prepare('delete s.* from work_day s inner join shift_days w on w.idshift_days = s.shift_days_idshift_days where s.users_Id_Users = ? and w.shifts_idshifts = ?; ');
		$statement->execute(array($Id_Users, $shift_id ));
		
		$statement = $db->prepare('SELECT festivals.status, festivals.name FROM festivals INNER JOIN shifts on festivals.idfestival = shifts.festival_idfestival WHERE shifts.idshifts = ?;');
		$statement->execute(array($shift_id));
		$res = $statement->fetchAll();
		$status = $res[0]["status"];
		$festival_name = $res[0]["name"];
		if (($status   == 0 ||$status == 2 || $status == 3) && ($ID == $Id_Users ) && !$overrule){
			//the user can subscribe 
			token_check($ID, $HASH, $db);
			$statement2 = $db->prepare('select count(distinct users_Id_Users) from work_day inner join shift_days on work_day.shift_days_idshift_days = shift_days.idshift_days where shift_days.shifts_idshifts = ?;');
			$statement2->execute(array($shift_id));
			$res2 = $statement2->fetchAll();
			$people_subscribed = $res2[0]["count(distinct users_Id_Users)"];
		}
		else {
			// the user cannot subscribe because the festival is closed OR he is subscribing another user, the admin can however do anything he wants 
			admin_check($ID, $HASH, $db);
			$status = 3;
		}

		$statement = $db->prepare('select idshift_days, start_date, shift_end, cost, people_needed, spare_needed from shift_days INNER JOIN shifts ON shifts.idshifts = shift_days.shifts_idshifts where shifts.idshifts = ?;');
		$statement->execute(array($shift_id));
		$res = $statement->fetchAll();
		$shift_info = "";
		$people_needed = $res[0]["people_needed"];
		$reserve_needed = $res[0]["spare_needed"];
		
		if ($people_needed + $reserve_needed <=  $people_subscribed){
			exit(json_encode(array(
				'status' => 400,
				'error_type' => 11,
				'error_message' => "This shift is full"
			)));
		}
		if ($people_needed <= $people_subscribed){
			$status = 99;
		}
		foreach ($res as &$shift) {
			$statement = $db->prepare('INSERT INTO work_day (reservation_type, shift_days_idshift_days, users_Id_Users) VALUES (?,?,?);');
			$statement->execute(array($status, $shift["idshift_days"], $Id_Users));
			$shift_info .= "<p>Van " . $shift["start_date"] . " tot " .  $shift["shift_end"] . " voor " . $shift["cost"] . " euro </p>" ;
		}
		
		// mail the user!
		$statement = $db->prepare('SELECT email from users where Id_Users = ?');
		$statement->execute(array($Id_Users));
		$res = $statement->fetchAll();
		$email = $res[0]['email'];
		
		if ($status == 2){
			$notification_text = 'Je bent nu geregistreerd voor ' . $festival_name . '. Wacht je definitieve inschrijving af.';
			$statement = $db->prepare('INSERT INTO notifications (notification, global,user_id) VALUES (?,?,?);');
			$statement->execute(array($notification_text, 0, $Id_Users));

			$subject = 'All-Round Events: Registratie voor ' . $festival_name;
			$message = '<html>
							<p>Beste,</p>
							<p>Je bent geregisteerd om deel te nemen aan ' . $festival_name . '. </br></p>
							<p> Je ben voor volgende shift geregisteerd:</p>
							' . $shift_info .
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
			$headers = 'From: inschrijvingen@all-round-events.be' . "\r\n" .
			'Reply-To: info@all-roundevents.be' . "\r\n" .
			"Content-type:text/html;charset=UTF-8" . "\r\n" .
			'X-Mailer: PHP/' . phpversion();
			mail($email, $subject, $message, $headers);
		}
		if ($status == 3){
			$notification_text = 'Ja bent nu ingeschreven voor ' . $festival_name . '. Tot dan!';
			$statement = $db->prepare('INSERT INTO notifications (notification, global,user_id) VALUES (?,?,?);');
			$statement->execute(array($notification_text, 0,  $Id_Users));
			$subject = 'All-Round Events: Inschrijving bevestigd voor ' . $festival_name;
			$message = '<html>
							<p>Beste,</p>
							<p>Je bent ingeschreven om te komen werken op ' . $festival_name . '. </br></p>
							<p> Je wordt op volgende momenten verwacht.</p>
							' . $shift_info .
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
			$headers = 'From: inschrijvingen@all-round-events.be' . "\r\n" .
			'Reply-To: info@all-roundevents.be' . "\r\n" .
			"Content-type:text/html;charset=UTF-8" . "\r\n" .
			'X-Mailer: PHP/' . phpversion();
			mail($email, $subject, $message, $headers);
		}
		
		
		exit(json_encode(array(
			'status' => 200,
			'error_type' => 0,
			'error_message' => "None"
		)));	
	}
	
	elseif ($action == "user_unsubscribe") {
		// get the contenct from the api body
		//Todo: Send mail with info! 
		$xml_dump = file_get_contents('php://input');
		$xml = json_decode($xml_dump, true);
		try {
			$ID = $xml["id"];
			$HASH = $xml["hash"];
			$shift_id = $xml["idshifts"];
			$Id_Users = $xml["Id_Users"];
			//TODO: add type so the admin can add specifc type
			
		} catch (Exception $e) {
			exit(json_encode(array(
				'status' => 409,
				'error_type' => 4,
				'error_message' => "Not all fields where available, need: name, details, status, date, ID, HASH"
			)));
		}
		
		$statement = $db->prepare('SELECT festivals.status, festivals.name FROM festivals INNER JOIN shifts on festivals.idfestival = shifts.festival_idfestival WHERE shifts.idshifts = ?;');
		$statement->execute(array($shift_id));
		$res = $statement->fetchAll();
		$status = $res[0]["status"];
		$festival_name = $res[0]["name"];
		
		// mail the user!
		$statement = $db->prepare('SELECT email from users where Id_Users = ?');
		$statement->execute(array($Id_Users));
		$res = $statement->fetchAll();
		$email = $res[0]['email'];
		
		$statement = $db->prepare('select idshift_days, start_date, shift_end, cost  from shift_days INNER JOIN shifts ON shifts.idshifts = shift_days.shifts_idshifts where shifts.idshifts = ?;');
		$statement->execute(array($shift_id));
		$res = $statement->fetchAll();
		$shift_info = "";
	
		foreach ($res as &$shift) {
			$shift_info .= "<p>Van " . $shift["start_date"] . " tot " .  $shift["shift_end"] . " voor " . $shift["cost"] . "euro </p>" ;
		}
		
		if ($ID == $Id_Users){
				token_check($ID, $HASH, $db);
				$notification_text = 'Ja bent nu uitgeschreven voor ' . $festival_name . ' in shift ' . $shift["name"] . ' . Hopelijk tot een volgende keer!';
				$statement = $db->prepare('INSERT INTO notifications (notification, global,user_id) VALUES (?,?,?);');
				$statement->execute(array($notification_text, 0, $Id_Users));
				$subject = 'All-Round Events: Uitgeschreven voor ' . $festival_name;
				$message = '<html>
								<p>Beste,</p>
								<p>Je hebt jezelf uitgeschreven voor festival ' . $festival_name . '. </br></p>
								<p> Je bent uitgeschreven voor volgende dagen:</p>
								' . $shift_info .
								"<p></p>
								<p>Alvast bedankt om ons te verwittigen en hopelijk tot een andere keer!  </p>
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
				$headers = 'From: inschrijvingen@all-round-events.be' . "\r\n" .
				'Reply-To: info@all-roundevents.be' . "\r\n" .
				"Content-type:text/html;charset=UTF-8" . "\r\n" .
				'X-Mailer: PHP/' . phpversion();
				mail($email, $subject, $message, $headers);
			
			
		}
		else {
			admin_check($ID, $HASH, $db);
				$notification_text = 'Je zal jammer genoeg niet kunnen deelnemen aan  ' . $festival_name . ' in shift ' . $shift_info . '. Er komen snel andere evenementen! Hou je app in de gaten!';
				$statement = $db->prepare('INSERT INTO notifications (notification, global,user_id) VALUES (?,?,?);');
				$statement->execute(array($notification_text, 0, $Id_Users));
				$subject = 'All-Round Events: Update voor  ' . $festival_name;
				$message = '<html>
								<p>Beste,</p>
								<p>Helaas zal je niet kunnen deelnemen aan  ' . $festival_name . '. </br></p>
								<p> Je had jezelf opgegeven voor volgende dagen: :</p>
								' . $shift_info .
								"<p></p>
								<p>Helaas waren we al met voldoende vrijwilligers voor dit evenement, kijk zeker uit naar onze evenementen!</p>
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
				$headers = 'From: inschrijvingen@all-round-events.be' . "\r\n" .
				'Reply-To: info@all-roundevents.be' . "\r\n" .
				"Content-type:text/html;charset=UTF-8" . "\r\n" .
				'X-Mailer: PHP/' . phpversion();
				mail($email, $subject, $message, $headers);
			
		}
		
		$statement = $db->prepare('delete s.* from work_day s inner join shift_days w on w.idshift_days = s.shift_days_idshift_days where s.users_Id_Users = ? and w.shifts_idshifts = ?; ');
		$statement->execute(array($Id_Users, $shift_id ));

		$statement = $db->prepare('delete external_appointment from external_appointment inner join locations on locations.location_id = external_appointment.location_id where external_appointment.user_id=? and locations.shift_id = ?');
		$statement->execute(array($Id_Users, $shift_id ));

		exit(json_encode(array(
			'status' => 200,
			'error_type' => 0,
			'error_message' => "None"
		)));
	}
	elseif ($action == "get_subscribers") {
		// get the contenct from the api body
		$xml_dump = file_get_contents('php://input');
		$xml = json_decode($xml_dump, true);
		try {
			$ID = $xml["id"];
			$HASH = $xml["hash"];
			
		} catch (Exception $e) {
			exit(json_encode(array(
				'status' => 409,
				'error_type' => 4,
				'error_message' => "Not all fields where available, need: name, details, status, date, ID, HASH"
			)));
		}
		admin_check($ID, $HASH, $db);
		$statement = $db->prepare('select work_day.users_Id_Users, users_data.name, shifts_idshifts, reservation_type, idwork_day, picture_name from work_day inner join shift_days on shift_days.idshift_days = work_day.shift_days_idshift_days inner join users_data on users_data.users_Id_Users = work_day.users_Id_Users inner join Images on (Images.users_Id_Users = work_day.users_Id_Users and Images.is_primary = 1) inner join shifts on shifts.idshifts = shift_days.shifts_idshifts inner join festivals on shifts.festival_idfestival = festivals.idfestival where  festivals.status != 6 and festivals.status != 7 GROUP BY work_day.users_Id_Users,shifts_idshifts  order by idwork_day;');
		$statement->execute(array());
		$res = $statement->fetchAll();
		if ($res){
			$json = json_encode($res);
			exit($json);
		}
		else {
			exit(json_encode (json_decode ("{}")));
		}
	}
	
	elseif ($action == "get_workdays_subscribers") {
		// get the contenct from the api body
		$xml_dump = file_get_contents('php://input');
		$xml = json_decode($xml_dump, true);
		try {
			$ID = $xml["id"];
			$HASH = $xml["hash"];
			
		} catch (Exception $e) {
			exit(json_encode(array(
				'status' => 409,
				'error_type' => 4,
				'error_message' => "Not all fields where available, need: name, details, status, date, ID, HASH"
			)));
		}
		admin_check($ID, $HASH, $db);
		$statement = $db->prepare('select work_day.reservation_type ,work_day.shift_days_idshift_days, work_day.users_Id_Users,work_day.in, work_day.out, work_day.present, users_data.telephone, users_data.name, shifts_idshifts, reservation_type, idwork_day, picture_name from work_day inner join shift_days on shift_days.idshift_days = work_day.shift_days_idshift_days inner join users_data on users_data.users_Id_Users = work_day.users_Id_Users inner join Images on (Images.users_Id_Users = work_day.users_Id_Users and Images.is_primary = 1) inner join shifts on shifts.idshifts = shift_days.shifts_idshifts inner join festivals on shifts.festival_idfestival = festivals.idfestival where  festivals.status != 6 and festivals.status != 7 order by idwork_day;');
		$statement->execute(array());
		$res = $statement->fetchAll();
		if ($res){
			$json = json_encode($res);
			exit($json);
		}
		else {
			exit(json_encode (json_decode ("{}")));
		}
	}
	
	elseif ($action == "user_search") {
		// get the contenct from the api body
		$xml_dump = file_get_contents('php://input');
		$xml = json_decode($xml_dump, true);
		try {
			$ID = $xml["id"];
			$HASH = $xml["hash"];
			$search = $xml["search"];
			
		} catch (Exception $e) {
			exit(json_encode(array(
				'status' => 409,
				'error_type' => 4,
				'error_message' => "Not all fields where available, need: name, details, status, date, ID, HASH"
			)));
		}
		admin_check($ID, $HASH, $db);
		$statement = $db->prepare('select * from users_data inner join Images on (Images.users_Id_Users = users_data.users_Id_Users and Images.is_primary = 1) where name like ? limit 10; ');
		$statement->execute(array("%" . $search . "%"));
		$res = $statement->fetchAll();
		if ($res){
			$json = json_encode($res);
			exit($json);
		}
		else {
			exit(json_encode (json_decode ("{}")));
		}
		
		
	}
	elseif ($action == "change_festival_status") {
		$xml_dump = file_get_contents('php://input');
		$xml = json_decode($xml_dump, true);
		try {
			$ID = $xml["id"];
			$HASH = $xml["hash"];
			$festi = $xml["festival_id"];
			$status = $xml["status"];
			
		} catch (Exception $e) {
			exit(json_encode(array(
				'status' => 409,
				'error_type' => 4,
				'error_message' => "Not all fields where available, need: name, details, status, date, ID, HASH"
			)));
		}
		admin_check($ID, $HASH, $db);
		$statement = $db->prepare("select * from festivals where idfestival = ?;");
		$statement->execute(array($festi));
		$res = $statement->fetchAll();
		$festival_name = $res[0]["name"];
		$festi_id = $res[0]["idfestival"];
		if ($res[0]["status"] == $status){
			exit(json_encode(array(
				'status' => 200,
				'error_type' => -1,
				'error_message' => "Updating was not needed"
			)));
		}
		$statement = $db->prepare("update festivals set status = ? where idfestival=?");
		$statement->execute(array($status,$festi));
	}

	elseif ($action == "festival_status_mail") {
		$xml_dump = file_get_contents('php://input');
		$xml = json_decode($xml_dump, true);
		try {
			$ID = $xml["id"];
			$HASH = $xml["hash"];
			$festi = $xml["festival_id"];
			$status = $xml["status"];
			
		} catch (Exception $e) {
			exit(json_encode(array(
				'status' => 409,
				'error_type' => 4,
				'error_message' => "Not all fields where available, need: name, details, status, date, ID, HASH"
			)));
		}
		admin_check($ID, $HASH, $db);
		$statement = $db->prepare("select * from festivals where idfestival = ?;");
		$statement->execute(array($festi));
		$res = $statement->fetchAll();
		$festival_name = $res[0]["name"];
		$festi_id = $res[0]["idfestival"];
		$status = $res[0]["status"];

		if($status == 0){

			
		}
		if($status == 1){
			$notification_text = 'Jawel,  ' . $festival_name . ' komt er binnenkort aan! Hou de inschrijvingspagina goed in de gaten! ';
			$statement = $db->prepare('INSERT INTO notifications (notification, global,user_id) VALUES (?,?,?);');
			$statement->execute(array($notification_text, 1,-1));
		}

		if($status == 2){
			// mail to everyone that the event is now open in register 
			$notification_text = 'Je kan je registeren voor ' . $festival_name . ', registreer je snel om erbij te kunnen zijn!';
			$statement = $db->prepare('INSERT INTO notifications (notification, global,user_id) VALUES (?,?,?);');
			$statement->execute(array($notification_text, 1, -1));

			$statement = $db->prepare("SELECT email FROM users;");
			$statement->execute(array());
			$res = $statement->fetchAll();
			foreach ($res as &$line) {
				$email = $line["email"];
				$subject = 'All-Round Events: Registratie open voor  ' . $festival_name;
				$message = '<html>
								<p>Beste,</p>
								<p>Vanaf vandaag kan je jezelf registeren voor  ' . $festival_name . '. </br></p>

								<p>Ga naar de website en registreer je voor je gewenste shift, je kan dit doen met de volgende link: </p>
								<p>https://all-round-events.be/html/nl/inschrijven.html</p>
								<p> </p>
								<p>Opgelet, registeren betekent niet dat je ingeschreven bent. Je zal zo snel mogelijk een mail ontvangen met het resultaat van je registratie! </p>
								<p>Veel succes en hopelijk tot snel</p>
								<p><small>
									All Round Events VZW
									Meester Van Der Borghtstraat 10
									2580 Putte
									BTW: BE 0886 674 723
									IBAN: BE68 7310 4460 6534
									RPR Mechelen</small></p>" 
							</html>';
				$headers = 'From: aankondigen@all-round-events.be' . "\r\n" .
				'Reply-To: info@all-roundevents.be ' . "\r\n" .
				"Content-type:text/html;charset=UTF-8" . "\r\n" .
				'X-Mailer: PHP/' . phpversion();
				mail($email, $subject, $message, $headers);
				
				
			}
		}
		if($status == 3){
			// mail to everyone that the event is now open in subscription mode
			$notification_text = 'Je kan je inschrijven voor ' . $festival_name . ', registreer je snel om erbij te kunnen zijn!';
			$statement = $db->prepare('INSERT INTO notifications (notification, global,user_id) VALUES (?,?,?);');
			$statement->execute(array($notification_text, 1, -1));

			$statement = $db->prepare("SELECT email FROM users;");
			$statement->execute(array());
			$res = $statement->fetchAll();
			foreach ($res as &$line) {
				$email = $line["email"];
				$subject = 'All-Round Events: Registratie open voor  ' . $festival_name;
				$message = '<html>
								<p>Beste,</p>
								<p>Vanaf vandaag kan je jezelf inschrijven voor  ' . $festival_name . '. </br></p>

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
									RPR Mechelen</small></p>" 
							</html>';
				$headers = 'From: aankondigen@all-round-events.be' . "\r\n" .
				'Reply-To: info@all-roundevents.be ' . "\r\n" .
				"Content-type:text/html;charset=UTF-8" . "\r\n" .
				'X-Mailer: PHP/' . phpversion();
				mail($email, $subject, $message, $headers);
				
				
			}
		}
		if($status == 4){
			// nothing sould be happening
		}
		if($status == 5){
			
			// mail is send to all the user that payout will be hapening
			$statement = $db->prepare("SELECT email FROM users inner join work_day on work_day.users_Id_Users = users.Id_Users inner join shift_days on shift_days.idshift_days = work_day.shift_days_idshift_days inner join shifts on shifts.idshifts = shift_days.shifts_idshifts inner join festivals on festivals.idfestival = shifts.festival_idfestival where festivals.idfestival = ? group by email;");
			$statement->execute(array($festi_id));
			$res = $statement->fetchAll();
			foreach ($res as &$line) {
				$email = $line["email"];
				$subject = 'All-Round Events: Uitbetaling starten voor' . $festival_name . ' .';
				$message = '<html>
								<p>Beste,</p>
								<p>De uitbetalingen voor ' . $festival_name . ' zullen plaatsvinden tijdens komende dagen.  </br></p>
								<p>We willen je nogmaals bedanken voor je inzet en hopen je graag op een volgend evenement terug te zien!</p>
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
				$headers = 'From: aankondigen@all-round-events.be' . "\r\n" .
				'Reply-To: info@all-roundevents.be ' . "\r\n" .
				"Content-type:text/html;charset=UTF-8" . "\r\n" .
				'X-Mailer: PHP/' . phpversion();
				mail($email, $subject, $message, $headers);
				
				
			}
		}
		if($status == 6){
			// nothing should be hapening
		}
		if($status == 7){
			$statement = $db->prepare("SELECT email FROM users;");
			$statement->execute(array());
			$res = $statement->fetchAll();
			foreach ($res as &$line) {
				$email = $line["email"];
				$subject = 'All-Round Events: ' . $festival_name . ' gaat niet door.';
				$message = '<html>
								<p>Beste,</p>
								<p>Jammer genoeg zal  ' . $festival_name . 'niet doorgaan dit jaar. Onze excuses voor het ongemak! </br></p>

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
									RPR Mechelen</small></p>" 
							</html>';
				$headers = 'From: aankondigen@all-round-events.be' . "\r\n" .
				'Reply-To: info@all-roundevents.be ' . "\r\n" .
				"Content-type:text/html;charset=UTF-8" . "\r\n" .
				'X-Mailer: PHP/' . phpversion();
				mail($email, $subject, $message, $headers);
				
				
			}
		}

	}
	
	elseif ($action == "user_present") {
		// get the contenct from the api body
		$xml_dump = file_get_contents('php://input');
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
			exit(json_encode(array(
				'status' => 409,
				'error_type' => 4,
				'error_message' => "Not all fields where available, need: name, details, status, date, ID, HASH"
			)));
		}
		admin_check($ID, $HASH, $db);
		
		if($in != 2){
			$statement = $db->prepare('update work_day set work_day.in=? where idwork_day=? and users_Id_Users=?;');
			$statement->execute(array($in, $work_day, $user));
		}
		if($out != 2){
			$statement = $db->prepare('Update work_day set work_day.out=? where idwork_day=? and users_Id_Users=?;');
			$statement->execute(array($out, $work_day, $user));
		}
		if($present != 2){
			$statement = $db->prepare('Update work_day set work_day.present=? where idwork_day=? and users_Id_Users=?;');
			$statement->execute(array($present, $work_day, $user));
		}

		
		exit(json_encode(array(
			'status' => 200,
			'error_type' => -1,
			'error_message' => ""
		)));

	}
	
	elseif ($action == "payouts_list") {
		// get the contenct from the api body
		$xml_dump = file_get_contents('php://input');
		$xml = json_decode($xml_dump, true);
		try {
			$ID = $xml["id"];
			$HASH = $xml["hash"];
			$festi_id = $xml["festi_id"];
		} catch (Exception $e) {
			exit(json_encode(array(
				'status' => 409,
				'error_type' => 4,
				'error_message' => "Not all fields where available, need: name, details, status, date, ID, HASH"
			)));
		}
		admin_check($ID, $HASH, $db);
		$statement = $db->prepare('select work_day.Payout, festivals.idfestival, shifts.name, work_day.users_Id_Users, shifts.idshifts, shift_days.cost, users_data.adres_line_two, users_data.name, work_day.in, work_day.out, work_day.present, shift_days.start_date from work_day inner join users_data on work_day.users_Id_Users = users_data.users_Id_Users inner join shift_days on work_day.shift_days_idshift_days = shift_days.idshift_days inner join shifts on shifts.idshifts = shift_days.shifts_idshifts inner join festivals on festivals.idfestival = shifts.festival_idfestival where festivals.idfestival = ? and (work_day.reservation_type = 3 or work_day.reservation_type = 5) ORDER BY work_day.users_Id_Users;');
		$statement->execute(array($festi_id));
		$res = $statement->fetchAll();
		if ($res){
			$json = json_encode($res);
			exit($json);
		}
		else {
			exit(json_encode (json_decode ("{}")));
		}
	}
	
	elseif ($action == "apply_payout") {
		// get the contenct from the api body
		//
		// payout id 0 => No payout
		// payout id 1 -> payout performed
		// payout id 2 -> payout refused
		$xml_dump = file_get_contents('php://input');
		$xml = json_decode($xml_dump, true);
		try {
			$ID = $xml["id"];
			$HASH = $xml["hash"];
			$shift_id = $xml["shift_id"];
			$payout_type_id = $xml["payout_type"];
			$user_id = $xml["user_id"];
		} catch (Exception $e) {
			exit(json_encode(array(
				'status' => 409,
				'error_type' => 4,
				'error_message' => "Not all fields where available, need: name, details, status, date, ID, HASH"
			)));
		}
		if($payout_type_id == 1){
			$notification_text = 'Er is een betaling onderweg, houd je bankrekening in de gaten! ';
			$statement = $db->prepare('INSERT INTO notifications (notification, global,user_id) VALUES (?,?,?);');
			$statement->execute(array($notification_text, 0, $user_id));
		}
		admin_check($ID, $HASH, $db);
		$statement = $db->prepare('update shifts inner join shift_days on shift_days.shifts_idshifts = shifts.idshifts  inner join work_day on work_day.shift_days_idshift_days=shift_days.idshift_days set work_day.Payout = ? where idshifts=? and work_day.users_Id_Users=?;');
		$statement->execute(array($payout_type_id, $shift_id, $user_id));
		$res = $statement->fetchAll();
		exit(json_encode (json_decode ("{}")));
	}


	elseif ($action == "pdf_unemployment") {
		$ID = isset($_GET['ID']) ? $_GET['ID'] : '';
		$HASH = isset($_GET['HASH']) ? $_GET['HASH'] : '';
		$shift = isset($_GET['shift']) ? $_GET['shift'] : '';
		token_check($ID, $HASH, $db);

		$statement = $db->prepare('SELECT * FROM users_data WHERE users_Id_Users = ?');
		$statement->execute(array($ID));
		$user_data = $statement->fetch(PDO::FETCH_ASSOC);

		$statement = $db->prepare('SELECT * FROM users WHERE Id_Users = ?');
		$statement->execute(array($ID));
		$user = $statement->fetch(PDO::FETCH_ASSOC);

		$statement = $db->prepare('SELECT festivals.name FROM festivals inner join shifts on festivals.idfestival=shifts.festival_idfestival WHERE shifts.idshifts = ?;');
		$statement->execute(array($shift));
		$festival = $statement->fetch(PDO::FETCH_ASSOC);
		
		$statement = $db->prepare('SELECT * FROM `shift_days` WHERE shift_days.shifts_idshifts = ? ORDER BY shift_days.start_date ASC  LIMIT 1;');
		$statement->execute(array($shift));
		$start_day = $statement->fetch(PDO::FETCH_ASSOC);

		$statement = $db->prepare('SELECT * FROM `shift_days` WHERE shift_days.shifts_idshifts = ? ORDER BY shift_days.start_date DESC  LIMIT 1;');
		$statement->execute(array($shift));
		$end_day = $statement->fetch(PDO::FETCH_ASSOC);

		require('fpdf.php');
		$pdf = new FPDF('P','mm','A4');
		$pdf->SetTitle("werkloosheidsatest");
		$pdf->AddPage();
		$pdf->SetFont('Arial','',14);
		$pdf->SetXY(20, 10);
		$pdf->Image("https://all-round-events.be/img/rva.jpeg", $pdf->GetX(), $pdf->GetY(), 0, 30);
		$pdf->SetXY(60, 10);
		$pdf->SetTextColor(190,190,190);
		$pdf->Write(5, "Aangifte van vrijwilligerswerk voor een ");
		$pdf->SetXY(70, 15);
		$pdf->Write(5, "niet-commerciele organisatie");
		$pdf->SetXY(85, 20);
		$pdf->SetTextColor(0,0,0);
		$pdf->SetFont('Arial','',7);
		$pdf->Write(5, "Art. 45bis KB 25.11.1991");
		$pdf->SetFont('Arial','',14);
		$pdf->SetXY(60, 25);
		$pdf->Write(5, "Deel I: in te vullen door de werkloze of de ");
		$pdf->SetXY(70, 30);
		$pdf->Write(5, "werkloze met bedrijfstoeslag");
		$pdf->SetXY(160, 10);
		$pdf->Rect(160, 10, 40, 30);
		$pdf->SetFont('Arial','',10);
		$pdf->SetXY(168, 13);
		$pdf->SetTextColor(190,190,190);
		$pdf->Write(5, "Datumstempel");
		$pdf->SetXY(163, 16);
		$pdf->Write(5, "uitbetalingsinstelling");
		$pdf->SetTextColor(0,0,0);

		$pdf->Line(10, 45, 200, 45);

		$pdf->SetFont('Arial','',14);
		$pdf->SetTextColor(0,0,0);
		$pdf->SetXY(25, 50);
		$pdf->Write(5, "Uw identiteit");
		$pdf->SetFont('Arial','',10);
		$pdf->SetXY(50, 65);
		$pdf->Write(5, "Voornaam en naam");
		$pdf->SetXY(50, 80);
		$pdf->Write(5, "Adres");
		$pdf->SetXY(90, 65);
		$pdf->SetFont('Arial','B',14);
		$pdf->Write(5, $user_data["name"]);
		$pdf->SetXY(90, 80);
		$pdf->Write(5, $user_data["adres_line_one"]);
		$pdf->SetFont('Arial','B',8);
		$pdf->SetTextColor(190,190,190);
		$pdf->SetXY(25, 100);
		$pdf->Write(5, "Uw INSZ-nummer staat op de");
		$pdf->SetXY(25, 105);
		$pdf->Write(5, "keerzijde van uw identiteitskaart");
		$pdf->SetXY(25, 115);
		$pdf->Write(5, "De gegevens telefoon en e-mail");
		$pdf->SetXY(25, 120);
		$pdf->Write(5, "zijn facultatief");
		$pdf->SetFont('Arial','',10);
		$pdf->SetTextColor(0,0,0);
		$pdf->SetXY(75, 103);
		$pdf->Write(5, "Rijksregisternr. (INSZ)");
		$pdf->SetXY(120, 103);
		$pdf->SetFont('Arial','B',10);
		$pdf->Write(5, $user_data["driver_license"]);
		$pdf->SetXY(75, 112);
		$pdf->SetFont('Arial','',10);
		$pdf->Write(5, "Telefoon");
		$pdf->SetXY(120, 112);
		$pdf->SetFont('Arial','B',10);
		$pdf->Write(5, $user_data["telephone"]);
		$pdf->SetFont('Arial','',10);
		$pdf->SetXY(75, 118);
		$pdf->Write(5, "E-mail");
		$pdf->SetXY(120, 118);
		$pdf->SetFont('Arial','B',10);
		$pdf->Write(5, $user["email"]);

		$pdf->Line(10, 130, 200, 130);

		$pdf->SetFont('Arial','B',10);
		$pdf->SetTextColor(0,0,0);
		$pdf->SetXY(25, 135);
		$pdf->Write(5, "Uw vrijwilligerswerk ");
		$pdf->SetFont('Arial','',7);
		$pdf->SetTextColor(190,190,190);
		$pdf->SetXY(25, 138);
		$pdf->Write(5, "Duid de vakjes aan die op u van ");
		$pdf->SetXY(25, 141);
		$pdf->Write(5, "toepassing zijn.");
		$pdf->SetFont('Arial','',10);
		$pdf->SetTextColor(0,0,0);
		$pdf->SetXY(75, 135);
		$pdf->Write(5, "Ik wens vrijwilligerswerk te verrichten voor een niet-commerciele organisatie");
		$pdf->SetXY(75, 140);
		$pdf->Write(5, "Naam van deze organisatie:");
		$pdf->SetXY(130, 140);
		$pdf->SetFont('Arial','B',10);
		$pdf->Write(5, "ALL-ROUND EVENTS VZW");
		$pdf->SetFont('Arial','',10);
		$pdf->SetXY(75, 145);
		$pdf->Write(5, "Ik wil dit vrijwilligerswerk verrichten:");
		$pdf->SetXY(76, 151);
		$pdf->SetFont('ZapfDingbats','', 10);
		$pdf->Cell(3, 3, 4, 1, 0); // checkbox
		$pdf->SetFont('Arial','',10);
		$pdf->SetXY(80, 150);
		$pdf->Write(5, "tijdens de periode van " . $start_day["start_date"] . " tot " . $end_day["shift_end"]);
		$pdf->SetXY(76, 156);
		$pdf->SetFont('ZapfDingbats','', 10);
		$pdf->Cell(3, 3, "", 1, 0);
		$pdf->SetFont('Arial','',10);
		$pdf->SetXY(80, 155);
		$pdf->Write(5, "voor onbepaalde duur.");
		$pdf->SetXY(75, 160);
		$pdf->Write(5, "Ik wil dit vrijwilligerswerk verrichten:");
		$pdf->SetFont('ZapfDingbats','', 10);
		$pdf->SetXY(76, 166);
		$pdf->Cell(3, 3, "", 1, 0);
		$pdf->SetFont('Arial','',10);
		$pdf->SetXY(80, 165);
		$pdf->Write(5, "op occasionele basis, nl. ............. keer per maand en ............ keer per jaar. ");
		$pdf->SetFont('ZapfDingbats','', 10);
		$pdf->SetXY(76, 171);
		$pdf->Cell(3, 3, "", 1, 0);
		$pdf->SetFont('Arial','',10);
		$pdf->SetXY(80, 170);
		$pdf->Write(5, "op de volgende dagen:  ma  di  wo  do  vr  za  zo");
		$pdf->SetFont('ZapfDingbats','', 10);
		$pdf->SetXY(76, 176);
		$pdf->Cell(3, 3, 4, 1, 0);
		$pdf->SetFont('Arial','',10);
		$pdf->SetXY(80, 175);
		$pdf->Write(5, "maar de frequentie ervan is niet vooraf te bepalen. In dit geval geeft u de");
		$pdf->SetXY(80, 180);
		$pdf->Write(5, "rede op:");
		$pdf->SetXY(80, 185);
		$pdf->SetFont('Arial','B',10);
		$pdf->Write(5, "In functie van de planning van de festivalkalender.");
		$pdf->SetFont('Arial','',10);
		$pdf->SetXY(80, 190);
		$pdf->Write(5, "....................................................................................................................");
		$pdf->SetXY(80, 195);
		$pdf->Write(5, "....................................................................................................................");
		$pdf->SetXY(75, 200);
		$pdf->Write(5, "Het maximum aantal uren van het vrijwilligerswerk:");
		$pdf->SetXY(76, 206);
		$pdf->SetFont('ZapfDingbats','', 10);
		$pdf->Cell(3, 3, "", 1, 0);
		$pdf->SetFont('Arial','',10);
		$pdf->SetXY(80, 205);
		$pdf->Write(5, "bedraagt .............uur per week en ............... per maand.");
		$pdf->SetFont('ZapfDingbats','', 10);
		$pdf->SetXY(76, 211);
		$pdf->Cell(3, 3, 4, 1, 0);
		$pdf->SetFont('Arial','',10);
		$pdf->SetXY(80, 210);
		$pdf->Write(5, "is niet vooraf te bepalen. In dit geval geeft u de reden op:");
		$pdf->SetXY(80, 215);
		$pdf->SetFont('Arial','B',10);
		$pdf->Write(5, "In functie van de festivalkalender & festivaluren, en bijhorende");
		$pdf->SetFont('Arial','B',10);
		$pdf->SetXY(80, 220);
		$pdf->Write(5, "bezetting");
		$pdf->SetFont('Arial','',10);
		$pdf->SetXY(80, 225);
		$pdf->Write(5, "....................................................................................................................");

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
		$pdf->SetTextColor(190,190,190);
		$pdf->SetFont('Arial','',9);
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
		$pdf->SetFont('Arial','',10);
		$pdf->SetTextColor(0,0,0);
		$pdf->SetXY(90, 30);
		$pdf->Write(5, "Ik zal een vergoeding ontvangen van de organisatie:");
		$pdf->SetXY(91, 36);
		$pdf->SetFont('ZapfDingbats','', 10);
		$pdf->Cell(3, 3, "", 1, 0);
		$pdf->SetFont('Arial','',10);
		$pdf->SetXY(95, 35);
		$pdf->Write(5, "neen.");
		$pdf->SetXY(91, 41);
		$pdf->SetFont('ZapfDingbats','', 10);
		$pdf->Cell(3, 3, 4, 1, 0);
		$pdf->SetFont('Arial','',10);
		$pdf->SetXY(95, 40);
		$pdf->Write(5, "Ja.");
		$pdf->SetXY(95, 45);
		$pdf->Write(5, "Bedrag ". $start_day["cost"] ." Euro per");
		$pdf->SetXY(128, 46);
		$pdf->SetFont('ZapfDingbats','', 10);
		$pdf->Cell(3, 3, "", 1, 0);
		$pdf->SetFont('Arial','',10);
		$pdf->SetXY(132, 45);
		$pdf->Write(5, "Uur");
		$pdf->SetXY(140, 46);
		$pdf->SetFont('ZapfDingbats','', 10);
		$pdf->Cell(3, 3, 4, 1, 0);
		$pdf->SetFont('Arial','',10);
		$pdf->SetXY(144, 45);
		$pdf->Write(5, "dag");
		$pdf->SetXY(152, 46);
		$pdf->SetFont('ZapfDingbats','', 10);
		$pdf->Cell(3, 3, "", 1, 0);
		$pdf->SetFont('Arial','',10);
		$pdf->SetXY(155, 45);
		$pdf->Write(5, "week");
		$pdf->SetXY(165, 46);
		$pdf->SetFont('ZapfDingbats','', 10);
		$pdf->Cell(3, 3, "", 1, 0);
		$pdf->SetFont('Arial','',10);
		$pdf->SetXY(169, 45);
		$pdf->Write(5, "maand");
		$pdf->SetXY(91, 51);
		$pdf->SetFont('ZapfDingbats','', 10);
		$pdf->Cell(3, 3, 4, 1, 0);
		$pdf->SetFont('Arial','',10);
		$pdf->SetXY(95, 50);
		$pdf->Write(5, "het gaat om een forfaitaire vergoeding tot terugbetaling van de");
		$pdf->SetXY(95, 55);
		$pdf->Write(5, "onkosten.");
		$pdf->SetXY(91, 61);
		$pdf->SetFont('ZapfDingbats','', 10);
		$pdf->Cell(3, 3, "", 1, 0);
		$pdf->SetFont('Arial','',10);
		$pdf->SetXY(95, 60);
		$pdf->Write(5, "het gaat om een andere vergoeding of materieel voordeel,:");
		$pdf->SetXY(95, 65);
		$pdf->Write(5, "namelijk:");
		$pdf->SetXY(95, 70);
		$pdf->Write(5, "....................................................................................................");
		$pdf->SetXY(95, 75);
		$pdf->Write(5, "....................................................................................................");
		$pdf->SetXY(95, 80);
		$pdf->Write(5, "....................................................................................................");

		$pdf->Line(10, 90, 200, 90);

		$pdf->SetXY(25, 95);
		$pdf->SetFont('Arial','B',10);
		$pdf->Write(5, "Handtekening");
		$pdf->SetFont('Arial','',9);
		$pdf->SetTextColor(190,190,190);
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
		$pdf->SetTextColor(0,0,0);
		$pdf->SetFont('Arial','',10);
		$pdf->SetXY(90, 100);
		$pdf->Write(5, "Ik bevestig dat mijn verklaringen echt en volledig zijn.");
		$pdf->SetXY(90, 105);
		$pdf->Write(5, "Ik vermeld mijn rijksregisternummer (INSZ) eveneens bovenaan");
		$pdf->SetXY(90, 110);
		$pdf->Write(5, "pagina 2, 3 en 4.");
		$pdf->SetXY(90, 120);
		$pdf->SetFont('Arial','B',10);
		$pdf->Write(5, "Datum : 06/01/2021     Handtekening");

		$pdf->SetFont('Arial','',10);
		$pdf->SetXY(25, 270);
		$pdf->Write(5, "Versie 28.12.2016/833.20.042");
		$pdf->SetXY(100, 270);
		$pdf->Write(5, "2/4");
		$pdf->SetXY(140, 270);
		$pdf->Write(5, "FORMULIER C45B");

		$pdf->AddPage();

		$pdf->SetFont('Arial','',10);
		$pdf->SetXY(25, 15);
		$pdf->Write(5, "Rijksregisternr. (INSZ)");
		$pdf->SetXY(70, 15);
		$pdf->Write(5, $user_data["driver_license"]);
		$pdf->SetFont('Arial','B',14);
		$pdf->SetXY(60, 30);
		$pdf->Write(5, "Deel II : in te vullen door de organisatie");

		$pdf->Line(10, 40, 200, 40);

		$pdf->SetXY(25, 45);
		$pdf->SetFont('Arial','B',10);
		$pdf->Write(5, "De organisatie");
		$pdf->SetFont('Arial','',10);
		$pdf->SetXY(90, 55);
		$pdf->Write(5, "Naam");
		$pdf->SetFont('Arial','B',10);
		$pdf->SetXY(105, 55);
		$pdf->Write(5, "ALL-ROUND EVENTS VZW");
		$pdf->SetFont('Arial','',10);
		$pdf->SetXY(90, 60);
		$pdf->Write(5, "Straat en nummer");
		$pdf->SetFont('Arial','B',10);
		$pdf->SetXY(120, 60);
		$pdf->Write(5, "Meester Van Der Borghtstraat 10");
		$pdf->SetFont('Arial','',10);
		$pdf->SetXY(90, 65);
		$pdf->Write(5, "Postcode en gemeente");
		$pdf->SetFont('Arial','B',10);
		$pdf->SetXY(130, 65);
		$pdf->Write(5, "2580 Putte");
		$pdf->SetFont('Arial','',10);
		$pdf->SetXY(90, 70);
		$pdf->Write(5, "Ondernemingsnummer");
		$pdf->SetFont('Arial','B',10);
		$pdf->SetXY(130, 70);
		$pdf->Write(5, "BE0886.674.723");
		$pdf->SetFont('Arial','',10);
		$pdf->SetXY(90, 75);
		$pdf->Write(5, "De organisatie is:");
		$pdf->SetXY(91, 81);
		$pdf->SetFont('ZapfDingbats','', 10);
		$pdf->Cell(3, 3, "", 1, 0);
		$pdf->SetFont('Arial','',10);
		$pdf->SetXY(95, 80);
		$pdf->Write(5, "een openbare dienst");
		$pdf->SetXY(91, 86);
		$pdf->SetFont('ZapfDingbats','', 10);
		$pdf->Cell(3, 3, 4, 1, 0);
		$pdf->SetFont('Arial','B',10);
		$pdf->SetXY(95, 85);
		$pdf->Write(5, "een vzw, met als maatschappelijk doel het organiseren en");
		$pdf->SetXY(95, 90);
		$pdf->Write(5, "ondersteunen van diverse manifestaties.");
		$pdf->SetFont('Arial','',10);
		$pdf->SetXY(91, 96);
		$pdf->SetFont('ZapfDingbats','', 10);
		$pdf->Cell(3, 3, "", 1, 0);
		$pdf->SetFont('Arial','',10);
		$pdf->SetXY(95, 95);
		$pdf->Write(5, "andere,.......................................................................................");
		$pdf->SetXY(95, 100);
		$pdf->Write(5, "met als maatschappelijk doel......................................................");
		$pdf->SetXY(95, 105);
		$pdf->Write(5, "....................................................................................................");
		$pdf->SetXY(95, 110);
		$pdf->Write(5, "....................................................................................................");
		$pdf->SetXY(90, 115);
		$pdf->Write(5, "Algemeen toelatingsnummer van de RVA ( zie FORMULIER C45F):");
		$pdf->SetXY(90, 120);
		$pdf->SetFont('Arial','B',10);
		$pdf->Write(5, "Y02/ ....................../......................... /45bis");
		$pdf->SetFont('Arial','',10);
		$pdf->SetTextColor(190,190,190);
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
		$pdf->SetFont('Arial','B',10);
		$pdf->SetTextColor(0,0,0);
		$pdf->Write(5, "Het vrijwilligerswerk");
		$pdf->SetTextColor(190,190,190);
		$pdf->SetFont('Arial','',9);
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
		$pdf->SetTextColor(0,0,0);
		$pdf->SetFont('Arial','',10);
		$pdf->SetXY(90, 170);
		$pdf->Write(5, "Ik bevestig de verklaring van de werkloze of de werkloze met");
		$pdf->SetXY(90, 174);
		$pdf->Write(5, "bedrijfstoeslag in verband met het verrichten van het");
		$pdf->SetXY(90, 179);
		$pdf->Write(5, "vrijwilligerswerk.");
		$pdf->SetXY(90, 184);
		$pdf->Write(5, "Ik beschrijf beknopt dit vrijwilligerswerk: ");
		$pdf->SetXY(90, 189);
		$pdf->SetFont('Arial','B',10);
		$pdf->Write(5, "Bandjescontrole, bemannen in en uitgangen op een festival.");
		$pdf->SetFont('Arial','',10);
		$pdf->SetXY(90, 194);
		$pdf->Write(5, ".......................................................................................................");
		$pdf->SetXY(90, 199);
		$pdf->Write(5, "Ik preciseer de doelgroep van de diensten aangeboden door mijn");
		$pdf->SetXY(90, 204);
		$pdf->Write(5, "organisatie:");
		$pdf->SetXY(110, 204);
		$pdf->SetFont('Arial','B',10);
		$pdf->Write(5, "12 - 65 jarigen");
		$pdf->SetFont('Arial','',10);
		$pdf->SetXY(90, 209);
		$pdf->Write(5, "Ik preciseer de tegenprestatie die de doelgroep moet betalen in ruil");
		$pdf->SetXY(90, 214);
		$pdf->Write(5, "voor de diensten:");
		$pdf->SetXY(90, 219);
		$pdf->SetFont('Arial','B',10);
		$pdf->Write(5, "Vrijwilligersvergoeding van ". $start_day["cost"] ." Euro");
		$pdf->SetFont('Arial','',10);
		$pdf->SetXY(90, 224);
		$pdf->Write(5, "Dit vrijwilligerswerk wordt verricht:");
		$pdf->SetXY(91, 231);
		$pdf->SetFont('ZapfDingbats','', 10);
		$pdf->Cell(3, 3, "", 1, 0);
		$pdf->SetFont('Arial','',10);
		$pdf->SetXY(95, 230);
		$pdf->Write(5, "op het adres van de organisatie;");
		$pdf->SetXY(91, 236);
		$pdf->SetFont('ZapfDingbats','', 10);
		$pdf->Cell(3, 3, 4, 1, 0);
		$pdf->SetFont('Arial','',10);
		$pdf->SetXY(95, 235);
		$pdf->Write(5, "op een ander adres, nanelijk: ");
		$pdf->SetXY(95, 240);
		$pdf->SetFont('Arial','B',10);
		$pdf->Write(5, $festival["name"]);
		$pdf->SetFont('Arial','',10);

		$pdf->SetFont('Arial','',10);
		$pdf->SetXY(25, 270);
		$pdf->Write(5, "Versie 28.12.2016/833.20.042");
		$pdf->SetXY(100, 270);
		$pdf->Write(5, "3/4");
		$pdf->SetXY(140, 270);
		$pdf->Write(5, "FORMULIER C45B");

		$pdf->AddPage();

		$pdf->SetFont('Arial','',10);
		$pdf->SetXY(25, 15);
		$pdf->Write(5, "Rijksregisternr. (INSZ)");
		$pdf->SetXY(70, 15);
		$pdf->Write(5, $user_data["driver_license"]);
		$pdf->Line(10, 30, 200, 30);
		$pdf->SetXY(25, 40);
		$pdf->SetFont('Arial','B',10);
		$pdf->Write(5, "Handtekening");
		$pdf->SetXY(85, 90);
		$pdf->Write(5, "Datum: ". date("d-m-Y") ."    Handtekening verantwoordelijke     Stempel");
		$pdf->SetXY(85, 100);
		$pdf->Write(5, "Contactpersoon:     Bart Tops");
		$pdf->SetXY(85, 110);
		$pdf->Write(5, "Telefoon:     0471 01 34 07");
		$pdf->SetFont('Arial','',10);
		$pdf->SetXY(25, 270);
		$pdf->Write(5, "Versie 28.12.2016/833.20.042");
		$pdf->SetXY(100, 270);
		$pdf->Write(5, "4/4");
		$pdf->SetXY(140, 270);
		$pdf->Write(5, "FORMULIER C45B");

		$pdf->Output();
	}
	
	elseif ($action == "pdf_listing") {
		$ID = isset($_GET['ID']) ? $_GET['ID'] : '';
		$HASH = isset($_GET['HASH']) ? $_GET['HASH'] : '';
		$shift_day = isset($_GET['shift_day']) ? $_GET['shift_day'] : '';
		admin_check($ID, $HASH, $db);
		$statement = $db->prepare('select shifts.name, shift_days.start_date, shift_days.shift_end , work_day.shift_days_idshift_days, work_day.users_Id_Users,work_day.in, work_day.out, work_day.present, users_data.telephone, users_data.name, shifts_idshifts, reservation_type, idwork_day, picture_name from work_day inner join shift_days on shift_days.idshift_days = work_day.shift_days_idshift_days inner join users_data on users_data.users_Id_Users = work_day.users_Id_Users inner join Images on (Images.users_Id_Users = work_day.users_Id_Users and Images.is_primary = 1) inner join shifts on shifts.idshifts = shift_days.shifts_idshifts inner join festivals on shifts.festival_idfestival = festivals.idfestival where shift_days.idshift_days=? and work_day.reservation_type = 3;');
		$statement->execute(array($shift_day));
		$res = $statement->fetchAll();
		require('fpdf.php');
		$w=array(30,55,20,20,20,20,20,20);
		$pdf = new FPDF('P','mm','A4');
		$pdf->SetTitle("Aanwezigheden");
		$pdf->AddPage();
		$pdf->SetFont('Arial','',14);
		$pdf->Write(10, $res[0][0] . ":   Start: " . $res[0]["start_date"] . " Tot: " . $res[0]["shift_end"]);
		$pdf->Ln();
		$pdf->SetFont('Arial','',8);
		$header = array('foto', 'Naam', 'nummer','in','out','aanwezig', '');
		for($i=0;$i<count($header);$i++){
			$pdf->Cell($w[$i],7,$header[$i],1,0,'C');
		}
		$pdf->Ln();
		foreach ($res as &$line) {
			$image1 = $line["picture_name"];
			$pdf->Cell($w[0],10,$pdf->Image($image1, $pdf->GetX(), $pdf->GetY(), 0, 9.9),1);
			$pdf->Cell($w[1],10,$line["name"],1);
			
			$in = '';
			$out = '';
			$present = '';
			if ($line["in"] == 1){
				$in = '           X';
			}
			if ($line["out"] == 1){
				$out = '           X';
			}
			if ($line["present"] == 1){
				$present = '           X';
			}
			$pdf->Cell($w[2],10,$line["telephone"],1);
			$pdf->Cell($w[3],10,$in,1);
			$pdf->Cell($w[4],10,$out,1);
			$pdf->Cell($w[5],10,$present,1);
			$pdf->Cell($w[6],10,"",1);
			$pdf->Ln();
		}
		$pdf->Output();
	}
	elseif ($action == "pdf_listing_external") {
		$ID = isset($_GET['ID']) ? $_GET['ID'] : '';
		$HASH = isset($_GET['HASH']) ? $_GET['HASH'] : '';
		$location_id= isset($_GET['location_id']) ? $_GET['location_id'] : '';
		admin_check($ID, $HASH, $db);
		$statement = $db->prepare('SELECT external_appointment.present, locations.location, locations.appointment_time, Images.picture_name,users_data.name, users_data.telephone FROM `external_appointment` INNER JOIN locations on locations.location_id = external_appointment.location_id INNER JOIN users_data on users_data.users_Id_Users = external_appointment.user_id INNER JOIN Images on Images.users_Id_Users = users_data.users_Id_Users WHERE external_appointment.location_id = ? and Images.is_primary =1');
		$statement->execute(array($location_id));
		$res = $statement->fetchAll();
		require('fpdf.php');
		$w=array(30,55,20,20,20,20,20,20);
		$pdf = new FPDF('P','mm','A4');
		$pdf->SetTitle("Aanwezigheden opvang");
		$pdf->AddPage();
		$pdf->SetFont('Arial','',14);
		$pdf->Write(10, "Opvang" . " " .$res[0]["appointment_time"] . " " . $res[0]["location"]);
		$pdf->Ln();
		$pdf->SetFont('Arial','',8);
		$header = array('foto', 'Naam', 'nummer','aanwezig','','', '');
		for($i=0;$i<count($header);$i++){
			$pdf->Cell($w[$i],7,$header[$i],1,0,'C');
		}
		$pdf->Ln();
		foreach ($res as &$line) {
			$image1 = $line["picture_name"];
			$pdf->Cell($w[0],10,$pdf->Image($image1, $pdf->GetX(), $pdf->GetY(), 0, 9.9),1);
			$pdf->Cell($w[1],10,$line["name"],1);
			$present = '';

			if ($line["present"] == 1){
				$present = '           X';
			}
			$pdf->Cell($w[2],10,$line["telephone"],1);
			$pdf->Cell($w[3],10,$present,1);
			$pdf->Cell($w[4],10,"",1);
			$pdf->Cell($w[5],10,"",1);
			$pdf->Cell($w[6],10,"",1);
			$pdf->Ln();
		}
		$pdf->Output();
	}
	if ($action == "change_pass"){
		
		$xml_dump = file_get_contents('php://input');
		$xml = json_decode($xml_dump, true);
		try {
			$ID = $xml["id"];
			$HASH = $xml["hash"];
			$new_pass = $xml["new_pass"];
		} catch (Exception $e) {
			exit(json_encode(array(
				'status' => 409,
				'error_type' => 4,
				'error_message' => "Not all fields where available, need: name, details, status, date, ID, HASH"
			)));
		}
		token_check($ID, $HASH, $db);
		$salt = bin2hex(openssl_random_pseudo_bytes(40));
		if (strlen($new_pass) < 5){
			exit(json_encode(array(
				'status' => 409,
				'error_type' => 2,
				'error_message' => "password must have more than 5 characters "
			)));		}
		$hashed_pass = password_hash($new_pass . $salt, PASSWORD_DEFAULT);

		// everything is ok, save 		
		$statement = $db->prepare('UPDATE users set pass=?, salt=? where Id_Users=?');
		$statement->execute(array($hashed_pass, $salt, $ID));
		exit(json_encode(array(
			'status' => 200,
			'error_type' => 0
			
		)));
		
	}

	if ($action == "get_news"){
		$xml_dump = file_get_contents('php://input');
		$xml = json_decode($xml_dump, true);
		try {
			$ID = $xml["id"];
			$HASH = $xml["hash"];
		} catch (Exception $e) {
			exit(json_encode(array(
				'status' => 409,
				'error_type' => 4,
				'error_message' => "Not all fields where available, need: name, details, status, date, ID, HASH"
			)));
		}
		token_check($ID, $HASH, $db);

		// everything is ok, save 		
		$statement = $db->prepare('SELECT * FROM notifications where global=1 or user_id=? order by notifications.id DESC limit 10');
		$statement->execute(array($ID));
		$res = $statement->fetchAll();
		if ($res){
			$json = json_encode($res);
			exit($json);
		}
		exit(json_encode(array(
			'status' => 200,
			'error_type' => 0
			
		)));
		
	}
	if ($action == "reset_pass"){
		$xml_dump = file_get_contents('php://input');
		$xml = json_decode($xml_dump, true);
		try {
			$email = $xml["email"];
		} catch (Exception $e) {
			exit(json_encode(array(
				'status' => 404,
				'error_type' => 4,
				'error_message' => "Not all fields where available, email"
			)));
		}
		$pass = bin2hex(openssl_random_pseudo_bytes(8));
		$salt = bin2hex(openssl_random_pseudo_bytes(40));
		$hashed_pass = password_hash($pass . $salt, PASSWORD_DEFAULT);
		$statement = $db->prepare('UPDATE users inner join users_data on users_data.users_Id_Users = users.Id_Users set pass=?, salt=? where email=?');
		$statement->execute(array($hashed_pass, $salt, $email));
		# send email 
		$subject = 'Wachtwoord reset';
		$message = '<html>
						<p>Beste,</p>
						<p>Je hebt een wachtwoord reset aagevraagd, hieronder vind u uw nieuw wachtwoord. Indien u uw wachtwoord wilt wijzingen kunt u dit doen door in te loggen en naar uw profiel aanpassen te gaan. </br></p>
						<p>Uw email: '. $email .'</br></p>
						<p>Uw nieuw wachtwoord: '. $pass .'</br></p>
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
		$headers = 'From: info@all-round-events.be' . "\r\n" .
		'Reply-To: info@all-roundevents.be ' . "\r\n" .
		"Content-type:text/html;charset=UTF-8" . "\r\n" .
		'X-Mailer: PHP/' . phpversion();
		mail($email, $subject, $message, $headers);
	
		exit(json_encode(array(
			'status' => "OK",
			'error_type' => 0,
			'error_message' => "OK"
		)));
	}


	if ($action == "message"){
		$xml_dump = file_get_contents('php://input');
		$xml = json_decode($xml_dump, true);
		try {
			$ID = $xml["id"];
			$HASH = $xml["hash"];
			$subject = $xml["subject"];
			$text = $xml["text"];
			$festi_id = $xml["festi_id"];
			$shift_id = $xml["shift_id"];
			$user_id = $xml["user_id"];


		} catch (Exception $e) {
			exit(json_encode(array(
				'status' => 404,
				'error_type' => 4,
				'error_message' => "Not all fields where available, email"
			)));
		}
		admin_check($ID, $HASH, $db);
		// select all the id's and email from one shift
		$statement = $db->prepare("SELECT DISTINCT email ,users.Id_Users from work_day inner JOIN users on work_day.users_Id_Users = users.Id_Users inner JOIN shift_days on work_day.shift_days_idshift_days = shift_days.idshift_days where shift_days.shifts_idshifts = ?;");
		$statement->execute(array($shift_id));
		$res = $statement->fetchAll();
		foreach ($res as &$line) {
			$email = $line["email"];
			$id_pusher = $line["id_Users"];
			$message = "<html><p>" . str_replace("\n","</br>", $text) . "</p></html>";
			$message_mail = "<html><p>" . str_replace("\n","</br></p><p>", $text) . "</p><p><small>
																						All Round Events VZW
																						Meester Van Der Borghtstraat 10
																						2580 Putte
																						BTW: BE 0886 674 723
																						IBAN: BE68 7310 4460 6534
																					RPR Mechelen</small></p></html>";
			$headers = 'From: info@all-round-events.be' . "\r\n" .
			'Reply-To: info@all-round-events.be' . "\r\n" .
			"Content-type:text/html;charset=UTF-8" . "\r\n" .
			'X-Mailer: PHP/' . phpversion();
			mail($email, $subject, $message_mail, $headers);

			$notification_text = $text;
			$statement = $db->prepare('INSERT INTO notifications (notification, global, user_id) VALUES (?,?,?);');
			$statement->execute(array($message, 0, $id_pusher));
		}

		$statement = $db->prepare("SELECT DISTINCT email ,users.Id_Users from work_day inner JOIN users on work_day.users_Id_Users = users.Id_Users inner JOIN shift_days on work_day.shift_days_idshift_days = shift_days.idshift_days inner JOIN shifts on shifts.idshifts = shift_days.shifts_idshifts where shifts.festival_idfestival = ?;");
		$statement->execute(array($festi_id));
		$res = $statement->fetchAll();
		foreach ($res as &$line) {
			$email = $line["email"];
			$id_pusher = $line["Id_Users"];
			$message = "<html><p>" . str_replace("\n","</br>", $text) . "</p></html>";
			$message_mail = "<html><p>" . str_replace("\n","</br></p><p>", $text) . "</p><p><small>
																						All Round Events VZW
																						Meester Van Der Borghtstraat 10
																						2580 Putte
																						BTW: BE 0886 674 723
																						IBAN: BE68 7310 4460 6534
																					RPR Mechelen</small></p></html>";
			$headers = 'From: info@all-round-events.be' . "\r\n" .
			'Reply-To: info@all-round-events.be' . "\r\n" .
			"Content-type:text/html;charset=UTF-8" . "\r\n" .
			'X-Mailer: PHP/' . phpversion();
			mail($email, $subject, $message_mail, $headers);

			$notification_text = $text;
			$statement = $db->prepare('INSERT INTO notifications (notification, global, user_id) VALUES (?,?,?);');
			$statement->execute(array($message, 0, $id_pusher));
		}

	exit(json_encode (json_decode ("{}")));
	}
	elseif ($action == "tshirts") {
		// get the contenct from the api body
		//
		$xml_dump = file_get_contents('php://input');
		$xml = json_decode($xml_dump, true);
		try {
			$ID = $xml["id"];
			$HASH = $xml["hash"];
			$festival_id = $xml["festi_id"];
		} catch (Exception $e) {
			exit(json_encode(array(
				'status' => 409,
				'error_type' => 4,
				'error_message' => "Not all fields where available, need: name, details, status, date, ID, HASH"
			)));
		}
		admin_check($ID, $HASH, $db);
		$statement = $db->prepare('select DISTINCT COUNT(size) as size, users_data.size from work_day inner join users_data on work_day.users_Id_Users = users_data.users_Id_Users inner join shift_days on work_day.shift_days_idshift_days = shift_days.idshift_days inner join shifts on shifts.idshifts = shift_days.shifts_idshifts inner join festivals on festivals.idfestival = shifts.festival_idfestival where festivals.idfestival = ? and work_day.reservation_type = 3 GROUP BY users_data.size;');
		$statement->execute(array($festival_id));
		$res = $statement->fetchAll();
		if ($res){
			$json = json_encode($res);
			exit($json);
		}
		else {
			exit(json_encode (json_decode ("{}")));
		}
		exit(json_encode (json_decode ("{}")));
	}
	elseif ($action == "add_location") {
		// get the contenct from the api body
		//
		$xml_dump = file_get_contents('php://input');
		$xml = json_decode($xml_dump, true);
		try {
			$ID = $xml["id"];
			$HASH = $xml["hash"];
			$shift_id = $xml["shift_id"];
			$location = $xml["location"];
			$appointment_time = $xml["appointment_time"];
		} catch (Exception $e) {
			exit(json_encode(array(
				'status' => 409,
				'error_type' => 4,
				'error_message' => "Not all fields where available, need: name, details, status, date, ID, HASH"
			)));
		}
		admin_check($ID, $HASH, $db);
		$statement = $db->prepare('INSERT INTO locations (location, appointment_time, shift_id) VALUES (?,?,?);');
		$statement->execute(array($location, $appointment_time, $shift_id));
		exit(json_encode (json_decode ("{}")));
	}
	elseif ($action == "change_location") {
		// get the contenct from the api body
		//
		$xml_dump = file_get_contents('php://input');
		$xml = json_decode($xml_dump, true);
		try {
			$ID = $xml["id"];
			$HASH = $xml["hash"];
			$location = $xml["location"];
			$appointment_time = $xml["appointment_time"];
			$location_id = $xml["location_id"];
		} catch (Exception $e) {
			exit(json_encode(array(
				'status' => 409,
				'error_type' => 4,
				'error_message' => "Not all fields where available, need: name, details, status, date, ID, HASH"
			)));
		}
		admin_check($ID, $HASH, $db);
		$statement = $db->prepare('UPDATE locations SET location = ?, appointment_time = ? WHERE location_id = ?;');
		$statement->execute(array($location, $appointment_time, $location_id));
		exit(json_encode (json_decode ("{}")));
	}

	elseif ($action == "delete_location") {
		// get the contenct from the api body
		//
		$xml_dump = file_get_contents('php://input');
		$xml = json_decode($xml_dump, true);
		try {
			$ID = $xml["id"];
			$HASH = $xml["hash"];
			$location_id = $xml["location_id"];
		} catch (Exception $e) {
			exit(json_encode(array(
				'status' => 409,
				'error_type' => 4,
				'error_message' => "Not all fields where available, need: name, details, status, date, ID, HASH"
			)));
		}
		admin_check($ID, $HASH, $db);
		$statement = $db->prepare('DELETE FROM locations WHERE location_id=?;');
		$statement->execute(array($location_id));
		exit(json_encode (json_decode ("{}")));
	}

	elseif ($action == "get_locations") {
		// get the contenct from the api body
		//
		$xml_dump = file_get_contents('php://input');
		$xml = json_decode($xml_dump, true);
		try {
			$ID = $xml["id"];
			$HASH = $xml["hash"];
		} catch (Exception $e) {
			exit(json_encode(array(
				'status' => 409,
				'error_type' => 4,
				'error_message' => "Not all fields where available, need: name, details, status, date, ID, HASH"
			)));
		}
		admin_check($ID, $HASH, $db);
		$statement = $db->prepare("SELECT locations.appointment_time, locations.location_id, locations.location, locations.shift_id, shifts.idshifts, shifts.datails, shifts.name, shifts.festival_idfestival FROM `locations` inner join shifts on locations.shift_id = shifts.idshifts inner join festivals on festivals.idfestival = shifts.festival_idfestival where festivals.status != 6 or festivals.status != 7;");
		$statement->execute(array());
		$res = $statement->fetchAll();
		if ($res){
			$json = json_encode($res);
			exit($json);
		}
		exit(json_encode (json_decode ("{}")));
	}

	elseif ($action == "get_locations_by_shift") {
		// get the contenct from the api body
		//
		$xml_dump = file_get_contents('php://input');
		$xml = json_decode($xml_dump, true);
		try {
			$ID = $xml["id"];
			$HASH = $xml["hash"];
			$shift_id = $xml["shift_id"];
		} catch (Exception $e) {
			exit(json_encode(array(
				'status' => 409,
				'error_type' => 4,
				'error_message' => "Not all fields where available, need: name, details, status, date, ID, HASH"
			)));
		}
		token_check($ID, $HASH, $db);
		$statement = $db->prepare("SELECT * FROM `locations` where  shift_id=?");
		$statement->execute(array($shift_id));
		$res = $statement->fetchAll();
		if ($res){
			$json = json_encode($res);
			exit($json);
		}
		exit(json_encode (json_decode ("{}")));
	}

	elseif ($action == "get_location") {
		// get the contenct from the api body
		//
		$xml_dump = file_get_contents('php://input');
		$xml = json_decode($xml_dump, true);
		try {
			$ID = $xml["id"];
			$HASH = $xml["hash"];
			$location_id = $xml["location_id"];
		} catch (Exception $e) {
			exit(json_encode(array(
				'status' => 409,
				'error_type' => 4,
				'error_message' => "Not all fields where available, need: name, details, status, date, ID, HASH"
			)));
		}
		admin_check($ID, $HASH, $db);
		$statement = $db->prepare("SELECT * FROM `locations` where location_id=?;");
		$statement->execute(array($location_id));
		$res = $statement->fetchAll();
		if ($res){
			$json = json_encode($res);
			exit($json);
		}
		exit(json_encode (json_decode ("{}")));
	}


	elseif ($action == "add_external_appointment") {
		// get the contenct from the api body
		//
		$xml_dump = file_get_contents('php://input');
		$xml = json_decode($xml_dump, true);
		try {
			$ID = $xml["id"];
			$HASH = $xml["hash"];
			$location_id = $xml["location_id"];
			$location = $xml["location"];
			$user_id = $xml["user_id"];
		} catch (Exception $e) {
			exit(json_encode(array(
				'status' => 409,
				'error_type' => 4,
				'error_message' => "Not all fields where available, need: name, details, status, date, ID, HASH"
			)));
		}

		if ($ID == $user_id){
			token_check($ID, $HASH, $db);
		}
		else {
			admin_check($ID, $HASH, $db);
		}
		// check if festival is open
		$statement = $db->prepare('INSERT INTO external_appointment_id (location_id, user_id) VALUES (?,?);');
		$statement->execute(array($location, $user_id));
		exit(json_encode (json_decode ("{}")));
	}
	elseif ($action == "change_external_appointment") {
		// get the contenct from the api body
		//
		$xml_dump = file_get_contents('php://input');
		$xml = json_decode($xml_dump, true);
		try {
			$ID = $xml["id"];
			$HASH = $xml["hash"];
			$location_id_old = $xml["old_location_id"];
			$location = $xml["location"];
			$user_id = $xml["user_id"];
		} catch (Exception $e) {
			exit(json_encode(array(
				'status' => 409,
				'error_type' => 4,
				'error_message' => "Not all fields where available, need: name, details, status, date, ID, HASH"
			)));
		}

		if ($ID == $user_id){
			token_check($ID, $HASH, $db);
		}
		else {
			admin_check($ID, $HASH, $db);
		}
		// check if festival is open
		$statement = $db->prepare('update external_appointment_id set location_id = ? where location_id = ? and user_id=?;');
		$statement->execute(array($location, $user_id));
		exit(json_encode (json_decode ("{}")));
	}

	elseif ($action == "subscribe_external_location") {
		// get the contenct from the api body
		//
		$xml_dump = file_get_contents('php://input');
		$xml = json_decode($xml_dump, true);
		try {
			$ID = $xml["id"];
			$HASH = $xml["hash"];
			$location_id = $xml["location_id"];
			$location = $xml["location"];
		} catch (Exception $e) {
			exit(json_encode(array(
				'status' => 409,
				'error_type' => 4,
				'error_message' => "Not all fields where available, need: name, details, status, date, ID, HASH"
			)));
		}

		token_check($ID, $HASH, $db);
		
		// check if festival is open 

		// check if festival is open
		$statement = $db->prepare('SELECT festivals.name, festivals.status, idshifts from shifts INNER JOIN festivals on festivals.idfestival = shifts.festival_idfestival inner JOIN locations on locations.shift_id = shifts.idshifts where locations.location_id = ? LIMIT 1;');
		$statement->execute(array($location_id));
		$res = $statement->fetchAll();
		$festival = $res[0]["name"];
		$shift = $res[0]["idshifts"];
		if($res[0]["status"] > 3 ) {
			exit("Event in wrong state to push external event");
		}
		$statement = $db->prepare('select * from work_day inner join shift_days on work_day.shift_days_idshift_days = shift_days.idshift_days inner join shifts on shifts.idshifts = shift_days.shifts_idshifts inner join locations on locations.shift_id = shifts.idshifts where locations.location_id = ? and work_day.users_Id_Users = ?');
		$statement->execute(array($location_id, $ID));
		$res = $statement->fetchAll();
		if(count($res) < 1){
			exit("Cannot subscribe to event when user is not part of event itself.");
		}
		$statement = $db->prepare("DELETE external_appointment from external_appointment  inner JOIN locations on locations.location_id = external_appointment.location_id inner join shifts on shifts.idshifts = locations.shift_id where shifts.idshifts = ? and external_appointment.user_id=?");
		$statement->execute(array($shift, $ID));

		$statement = $db->prepare("insert into external_appointment (external_appointment.location_id, external_appointment.user_id, present) VALUES (?,?,?);");
		$statement->execute(array($location_id, $ID, 0));

		$statement = $db->prepare("SELECT * FROM users where users.Id_Users = ?;");
		$statement->execute(array($ID));
		$res = $statement->fetchAll();
		$email = $res[0]['email'];
		$name = $res[0]['name'];
		$statement = $db->prepare("SELECT * FROM locations where locations.location_id = ?");
		$statement->execute(array($location_id));
		$location = $statement->fetchAll();

		$subject = "Opvang keuze voor " . $festival;
		$message = '<html>
				<p>Beste, '. $name .'</p>
				<p></br></p>
				<p>Je hebt gezozen voor een opvang moment, je wordt verwacht op '. $location[0]["appointment_time"] .'</br></p>
				<p>op de volgende locatie: '. $location[0]["location"] .'</p>
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
		$headers = 'From: info@all-round-events.be' . "\r\n" .
		'Reply-To: info@all-round-events.be' . "\r\n" .
		"Content-type:text/html;charset=UTF-8" . "\r\n" .
		'X-Mailer: PHP/' . phpversion();
		mail($email, $subject, $message, $headers);


		exit(json_encode (json_decode ("{}")));
	}

	elseif ($action == "subscribe_external_location_admin") {
		// get the contenct from the api body
		//
		$xml_dump = file_get_contents('php://input');
		$xml = json_decode($xml_dump, true);
		try {
			$ID = $xml["id"];
			$HASH = $xml["hash"];
			$location_id = $xml["location_id"];
			$user_id = $xml["user_id"];
			$shift_id = $xml["shift_id"];
		} catch (Exception $e) {
			exit(json_encode(array(
				'status' => 409,
				'error_type' => 4,
				'error_message' => "Not all fields where available, need: name, details, status, date, ID, HASH"
			)));
		}

		admin_check($ID, $HASH, $db);

		$statement = $db->prepare('select festivals.name from festivals inner JOIN shifts ON festivals.idfestival = shifts.festival_idfestival where shifts.idshifts = 27;');
		$statement->execute(array($shift_id));
		$res = $statement->fetchAll();
		$festival = $res[0]["name"];

		
		$statement = $db->prepare("DELETE external_appointment from external_appointment  inner JOIN locations on locations.location_id = external_appointment.location_id inner join shifts on shifts.idshifts = locations.shift_id where shifts.idshifts = ? and external_appointment.user_id=?");
		$statement->execute(array($shift_id, $user_id));

		$statement = $db->prepare("insert into external_appointment (external_appointment.location_id, external_appointment.user_id, present) VALUES (?,?,?);");
		$statement->execute(array($location_id, $user_id, 0));

		$statement = $db->prepare("SELECT * FROM users where users.Id_Users = ?;");
		$statement->execute(array($user_id));
		$res = $statement->fetchAll();
		$email = $res[0]['email'];
		$name = $res[0]['name'];
		$statement = $db->prepare("SELECT locations.appointment_time, locations.location from locations inner join shifts on locations.shift_id = shifts.idshifts where shifts.idshifts = ?;");
		$statement->execute(array($shift_id));
		$location = $statement->fetchAll();

		$subject = "Opvang voor " . $festival;
		$message = '<html>
				<p>Beste, '. $name .'</p>
				<p></br></p>
				<p>Je wordt verwacht op '. $location[0]["appointment_time"] .'</br></p>
				<p>op de volgende locatie: '. $location[0]["location"] .'</p>
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
		$headers = 'From: info@all-round-events.be' . "\r\n" .
		'Reply-To: info@all-round-events.be' . "\r\n" .
		"Content-type:text/html;charset=UTF-8" . "\r\n" .
		'X-Mailer: PHP/' . phpversion();
		mail($email, $subject, $message, $headers);

		exit(json_encode (json_decode ("{}")));
	}
	elseif ($action == "subscribe_external_location_admin_manual") {
		// get the contenct from the api body
		//
		$xml_dump = file_get_contents('php://input');
		$xml = json_decode($xml_dump, true);
		try {
			$ID = $xml["id"];
			$HASH = $xml["hash"];
			$location_id = $xml["location_id"];
			$user_id = $xml["user_id"];
		} catch (Exception $e) {
			exit(json_encode(array(
				'status' => 409,
				'error_type' => 4,
				'error_message' => "Not all fields where available, need: name, details, status, date, ID, HASH"
			)));
		}

		admin_check($ID, $HASH, $db);
		
		$statement = $db->prepare('SELECT festivals.name, festivals.status, idshifts from shifts INNER JOIN festivals on festivals.idfestival = shifts.festival_idfestival inner JOIN locations on locations.shift_id = shifts.idshifts where locations.location_id = ? LIMIT 1;');
		$statement->execute(array($location_id));
		$res = $statement->fetchAll();
		$festival = $res[0]["name"];

		$statement = $db->prepare("DELETE external_appointment from external_appointment  inner JOIN locations on locations.location_id = external_appointment.location_id where locations.location_id =? and external_appointment.user_id=?");
		$statement->execute(array($location_id, $user_id));

		$statement = $db->prepare("insert into external_appointment (external_appointment.location_id, external_appointment.user_id, present) VALUES (?,?,?);");
		$statement->execute(array($location_id, $user_id, 0));

		$statement = $db->prepare("SELECT * FROM users where users.Id_Users = ?;");
		$statement->execute(array($user_id));
		$res = $statement->fetchAll();
		$email = $res[0]['email'];
		$name = $res[0]['name'];
		$statement = $db->prepare("SELECT * FROM locations where locations.location_id = ?");
		$statement->execute(array($location_id));
		$location = $statement->fetchAll();

		$subject = "Opvang voor " . $festival;
		$message = '<html>
				<p>Beste, '. $name .'</p>
				<p></br></p>
				<p>Je wordt verwacht op '. $location[0]["appointment_time"] .'</br></p>
				<p>op de volgende locatie: '. $location[0]["location"] .'</p>
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
		$headers = 'From: info@all-round-events.be' . "\r\n" .
		'Reply-To: info@all-round-events.be' . "\r\n" .
		"Content-type:text/html;charset=UTF-8" . "\r\n" .
		'X-Mailer: PHP/' . phpversion();
		mail($email, $subject, $message, $headers);

		exit(json_encode (json_decode ("{}")));
	}
	elseif ($action == "unsubscribe_external_location_admin_manual") {
		// get the contenct from the api body
		//
		$xml_dump = file_get_contents('php://input');
		$xml = json_decode($xml_dump, true);
		try {
			$ID = $xml["id"];
			$HASH = $xml["hash"];
			$location_id = $xml["location_id"];
			$user_id = $xml["user_id"];
		} catch (Exception $e) {
			exit(json_encode(array(
				'status' => 409,
				'error_type' => 4,
				'error_message' => "Not all fields where available, need: name, details, status, date, ID, HASH"
			)));
		}

		admin_check($ID, $HASH, $db);
		//mail
		$statement = $db->prepare("DELETE external_appointment from external_appointment  inner JOIN locations on locations.location_id = external_appointment.location_id where locations.location_id =? and external_appointment.user_id=?");
		$statement->execute(array($location_id, $user_id));
		exit(json_encode (json_decode ("{}")));
	}

	elseif ($action == "subscribe_external_location_user") {
		// get the contenct from the api body
		//
		$xml_dump = file_get_contents('php://input');
		$xml = json_decode($xml_dump, true);
		try {
			$ID = $xml["id"];
			$HASH = $xml["hash"];
		} catch (Exception $e) {
			exit(json_encode(array(
				'status' => 409,
				'error_type' => 4,
				'error_message' => "Not all fields where available, need: name, details, status, date, ID, HASH"
			)));
		}
		token_check($ID, $HASH, $db);
		$statement = $db->prepare("SELECT external_appointment.external_appointment_id, external_appointment.location_id, external_appointment.user_id, external_appointment.present FROM `external_appointment` inner join locations on locations.location_id = external_appointment.location_id inner join shifts on locations.shift_id = shifts.idshifts inner join festivals on festivals.idfestival = shifts.festival_idfestival where (festivals.status != 6 or festivals.status != 7) and external_appointment.user_id = ?");
		$statement->execute(array($ID));
		$res = $statement->fetchAll();
		if ($res){
			$json = json_encode($res);
			exit($json);
		}
		exit(json_encode (json_decode ("{}")));
	}
	elseif ($action == "subscribe_external_location_active") {
		// get the contenct from the api body
		//
		$xml_dump = file_get_contents('php://input');
		$xml = json_decode($xml_dump, true);
		try {
			$ID = $xml["id"];
			$HASH = $xml["hash"];
		} catch (Exception $e) {
			exit(json_encode(array(
				'status' => 409,
				'error_type' => 4,
				'error_message' => "Not all fields where available, need: name, details, status, date, ID, HASH"
			)));
		}
		admin_check($ID, $HASH, $db);
		$statement = $db->prepare("SELECT external_appointment.external_appointment_id, external_appointment.location_id, external_appointment.user_id, external_appointment.present,locations.shift_id, users_data.name, Images.picture_name FROM `external_appointment` inner join locations on locations.location_id = external_appointment.location_id inner join shifts on locations.shift_id = shifts.idshifts inner join festivals on festivals.idfestival = shifts.festival_idfestival inner join users_data on users_data.users_Id_Users = external_appointment.user_id inner join Images on Images.users_Id_Users = external_appointment.user_id where (festivals.status != 6 or festivals.status != 7) and Images.is_primary = 1");
		$statement->execute(array());
		$res = $statement->fetchAll();
		if ($res){
			$json = json_encode($res);
			exit($json);
		}
		exit(json_encode (json_decode ("{}")));
	}

	elseif ($action == "subscribe_external_location_listing") {
		// get the contenct from the api body
		//
		$xml_dump = file_get_contents('php://input');
		$xml = json_decode($xml_dump, true);
		try {
			$ID = $xml["id"];
			$HASH = $xml["hash"];
			$location_id = $xml["location_id"];
		} catch (Exception $e) {
			exit(json_encode(array(
				'status' => 409,
				'error_type' => 4,
				'error_message' => "Not all fields where available, need: name, details, status, date, ID, HASH"
			)));
		}
		admin_check($ID, $HASH, $db);
		$statement = $db->prepare("SELECT locations.location_id, locations.appointment_time, locations.location, locations.shift_id, users_data.name, users_data.telephone, Images.picture_name, users_data.users_Id_Users, external_appointment.present from locations inner join external_appointment on external_appointment.location_id = locations.location_id inner join users_data on users_data.users_Id_Users = external_appointment.user_id INNER join Images on Images.users_Id_Users=users_data.users_Id_Users WHERE locations.location_id = ?");
		$statement->execute(array($location_id));
		$res = $statement->fetchAll();
		if ($res){
			$json = json_encode($res);
			exit($json);
		}
		exit(json_encode (json_decode ("{}")));
	}
	elseif ($action == "present_set_external_location") {
		// get the contenct from the api body
		//
		$xml_dump = file_get_contents('php://input');
		$xml = json_decode($xml_dump, true);
		try {
			$ID = $xml["id"];
			$HASH = $xml["hash"];
			$present = $xml["present"];
			$location_id = $xml["location_id"];
			$user_id = $xml["user_id"];
		} catch (Exception $e) {
			exit(json_encode(array(
				'status' => 409,
				'error_type' => 4,
				'error_message' => "Not all fields where available, need: name, details, status, date, ID, HASH"
			)));
		}
		admin_check($ID, $HASH, $db);
		$statement = $db->prepare("update external_appointment set present=? where user_id=? and location_id=?;");
		$statement->execute(array($present, $user_id, $location_id));
		exit(json_encode (json_decode ("{}")));
	}

	elseif ($action == "festival_mail_external_location") {
		// get the contenct from the api body
		//
		$xml_dump = file_get_contents('php://input');
		$xml = json_decode($xml_dump, true);
		try {
			$ID = $xml["id"];
			$HASH = $xml["hash"];
			$festival_id = $xml["festival_id"];

		} catch (Exception $e) {
			exit(json_encode(array(
				'status' => 409,
				'error_type' => 4,
				'error_message' => "Not all fields where available, need: name, details, status, date, ID, HASH"
			)));
		}
		admin_check($ID, $HASH, $db);
		// select all the id's and email from one shift
		$statement = $db->prepare("select DISTINCT users.email, users_data.name, festivals.name as festival_name from work_day INNER JOIN users on work_day.users_Id_Users = users.Id_Users inner join shift_days on shift_days.idshift_days = work_day.shift_days_idshift_days inner join users_data on users_data.users_Id_Users = work_day.users_Id_Users inner JOIN shifts on shifts.idshifts = shift_days.shifts_idshifts inner join festivals on festivals.idfestival = shifts.festival_idfestival where shifts.festival_idfestival = ? and users.Id_Users not in (select DISTINCT external_appointment.user_id from external_appointment inner JOIN locations on locations.shift_id inner join shifts on shifts.idshifts = locations.shift_id where shifts.festival_idfestival = ?)");
		$statement->execute(array($festival_id, $festival_id));
		$res = $statement->fetchAll();
		foreach ($res as &$line) {
			$email = $line["email"];
			$festival_name = $line["festival_name"];
			$name = $line["name"];
			$subject = "Opvang keuze voor " . $festival_name;
			$message = '<html>
				<p>Beste, '. $name .'</p>
				<p>Binnenkort is het zover en zal jij als vrijwillger aan de slag gaan op ' . $festival_name . '. </br></p>
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
			$headers = 'From: info@all-round-events.be' . "\r\n" .
			'Reply-To: info@all-round-events.be' . "\r\n" .
			"Content-type:text/html;charset=UTF-8" . "\r\n" .
			'X-Mailer: PHP/' . phpversion();
			mail($email, $subject, $message, $headers);

			$notification_text = $text;
			$statement = $db->prepare('INSERT INTO notifications (notification, global, user_id) VALUES (?,?,?);');
			$statement->execute(array($message, 0, $id_pusher));
		}
	}
	elseif ($action == "add_user_to_day_manual") {
		// get the contenct from the api body
		//
		$xml_dump = file_get_contents('php://input');
		$xml = json_decode($xml_dump, true);
		try {
			$ID = $xml["id"];
			$HASH = $xml["hash"];
			$Id_Users = $xml["Id_Users"];
			$shift_day_id = $xml["shift_day_id"];

		} catch (Exception $e) {
			exit(json_encode(array(
				'status' => 409,
				'error_type' => 4,
				'error_message' => "Not all fields where available, need: name, details, status, date, ID, HASH"
			)));
		}
		admin_check($ID, $HASH, $db);
		// check if festival is open
		$statement = $db->prepare('INSERT INTO work_day (reservation_type, shift_days_idshift_days, users_Id_Users) VALUES (?,?,?);');
		$statement->execute(array("5" ,$shift_day_id, $Id_Users));
		exit(json_encode (json_decode ("{}")));
	}
	elseif ($action == "remove_user_to_day_manual") {
		// get the contenct from the api body
		//
		$xml_dump = file_get_contents('php://input');
		$xml = json_decode($xml_dump, true);
		try {
			$ID = $xml["id"];
			$HASH = $xml["hash"];
			$Id_Users = $xml["Id_Users"];
			$shift_day_id = $xml["shift_day_id"];

		} catch (Exception $e) {
			exit(json_encode(array(
				'status' => 409,
				'error_type' => 4,
				'error_message' => "Not all fields where available, need: name, details, status, date, ID, HASH"
			)));
		}
		admin_check($ID, $HASH, $db);
		// check if festival is open
		$statement = $db->prepare('DELETE work_day from work_day  where work_day.reservation_type = ? and work_day.shift_days_idshift_days = ? and work_day.users_Id_Users =?;');
		$statement->execute(array("5" ,$shift_day_id, $Id_Users));
		exit(json_encode (json_decode ("{}")));
	}

	elseif ($action == "mail_user_by_shift_day") {
		// get the contenct from the api body
		//
		$xml_dump = file_get_contents('php://input');
		$xml = json_decode($xml_dump, true);
		try {
			$ID = $xml["id"];
			$HASH = $xml["hash"];
			$Id_Users = $xml["Id_Users"];
			$shift_day_id = $xml["shift_day_id"];

		} catch (Exception $e) {
			exit(json_encode(array(
				'status' => 409,
				'error_type' => 4,
				'error_message' => "Not all fields where available, need: name, details, status, date, ID, HASH"
			)));
		}
		admin_check($ID, $HASH, $db);
		$statement = $db->prepare('select festivals.idfestival from shift_days inner join shifts on shifts.idshifts = shift_days.shifts_idshifts inner join festivals on festivals.idfestival = shifts.festival_idfestival where shift_days.idshift_days = ?;');
		$statement->execute(array($shift_day_id));
		$res = $statement->fetchAll();
		$festival = $res[0]["idfestival"];
		$statement = $db->prepare('select users.email, shift_days.start_date, shift_days.shift_end,shift_days.cost, festivals.name from work_day inner join shift_days on shift_days.idshift_days = work_day.shift_days_idshift_days inner join shifts on shift_days.shifts_idshifts = shifts.idshifts inner join festivals on festivals.idfestival = shifts.festival_idfestival inner join users on users.Id_Users = work_day.users_Id_Users where festivals.idfestival = ? and users.Id_Users = ?;');
		$statement->execute(array($festival, $Id_Users));
		$res = $statement->fetchAll();
		$shift_info = "";
		$email = $res[0]["email"];
		$festival_name = $res[0]["name"];
		foreach ($res as &$shift) {
			$shift_info .= "<p>Van " . $shift["start_date"] . " tot " .  $shift["shift_end"] . " voor " . $shift["cost"] . "euro </p>" ;
		}
		$subject = 'All-Round Events: Update voor  ' . $festival_name;
		$message = '<html>
		<p>Beste,</p>
		<p>Je bent ingeschreven om te komen werken op  ' . $festival_name . '. </br></p>
		<p> We hebben je ingeschreven voor volgende momenten:</p>
		' . $shift_info .
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
		$headers = 'From: inschrijvingen@all-round-events.be' . "\r\n" .
		'Reply-To: info@all-roundevents.be' . "\r\n" .
		"Content-type:text/html;charset=UTF-8" . "\r\n" .
		'X-Mailer: PHP/' . phpversion();
		mail($email, $subject, $message, $headers);


		// check if festival is open
		
		exit(json_encode (json_decode ("{}")));
	}
	elseif ($action == "csv_listing_festival") {
		$ID = isset($_GET['ID']) ? $_GET['ID'] : '';
		$HASH = isset($_GET['HASH']) ? $_GET['HASH'] : '';
		$festi_id= isset($_GET['festi_id']) ? $_GET['festi_id'] : '';
		admin_check($ID, $HASH, $db);
		$statement = $db->prepare('select users_data.name,  DATE(users_data.date_of_birth), users_data.driver_license from work_day inner JOIN shift_days on shift_days.idshift_days = work_day.shift_days_idshift_days inner join shifts on shifts.idshifts = shift_days.shifts_idshifts inner join festivals on festivals.idfestival = shifts.festival_idfestival inner join users_data on users_data.users_Id_Users = work_day.users_Id_Users where festivals.idfestival = ? GROUP BY work_day.users_Id_Users');
		$statement->execute(array($festi_id));
		$res = $statement->fetchAll();
		header('Content-Type: text/csv');
		header('Content-Disposition: attachment; filename="deelnemers.csv"');
		$data = array();
		foreach ($res as &$user) {
			array_push($data, ($user["name"].",". $user["DATE(users_data.date_of_birth)"] . "," . $user["driver_license"]));
		}
		$fp = fopen('php://output', 'wb');
		foreach ( $data as $line ) {
    		$val = explode(",", $line);
    		fputcsv($fp, $val);
		}
		fclose($fp);
	}
	elseif ($action == "csv_listing_festival_payout") {
		$ID = isset($_GET['ID']) ? $_GET['ID'] : '';
		$HASH = isset($_GET['HASH']) ? $_GET['HASH'] : '';
		$festi_id= isset($_GET['festi_id']) ? $_GET['festi_id'] : '';
		admin_check($ID, $HASH, $db);
		$statement = $db->prepare('select work_day.users_Id_Users, SUM(shift_days.cost), users_data.name, users_data.adres_line_two, festivals.name as festiname, work_day.Payout from work_day inner JOIN shift_days on work_day.shift_days_idshift_days = shift_days.idshift_days inner join shifts on shifts.idshifts = shift_days.shifts_idshifts INNER JOIN festivals on festivals.idfestival = shifts.festival_idfestival inner JOIN users_data on users_data.users_Id_Users = work_day.users_Id_Users where festivals.idfestival = ? and ((work_day.in = 1 and work_day.out = 1) or work_day.present = 1) GROUP BY work_day.users_Id_Users');
		$statement->execute(array($festi_id));
		$res = $statement->fetchAll();
		header('Content-Type: text/csv');
		header('Content-Disposition: attachment; filename="uitbetaling.csv"');
		$data = array();
		foreach ($res as &$user) {
			array_push($data, ($user["name"].",". $user["adres_line_two"] . "," . $user["SUM(shift_days.cost)"] . ", Vrijwilligersvergoeding " . $user["festiname"]));
		}
		$fp = fopen('php://output', 'wb');
		foreach ( $data as $line ) {
    		$val = explode(",", $line);
    		fputcsv($fp, $val);
		}
		fclose($fp);
	}

	else {
		exit(json_encode(array(
			'status' => 404,
			'error_type' => 10,
			'error_message' => "not a valid action"
		)));
	}










	