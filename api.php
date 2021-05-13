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
		$statement = $db->prepare('INSERT INTO users_data (name,size, date_of_birth, Gender, adres_line_one, adres_line_two, driver_license, nationality, telephone, marital_state, text, users_Id_Users) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)');
				$statement->execute(array($name,$size,  $date_of_birth, $gender, $address_line_one, $adress_line_two, $driver_license, $nationality, $telephone, $marital_state, $text, $ID)); 
		}
		else {
		$statement = $db->prepare('UPDATE users_data set name=?, size=?, date_of_birth=?, Gender=?, adres_line_one=?, adres_line_two=?, driver_license=?, nationality=?, telephone =?, marital_state=?, text=? where users_Id_Users=?');
		$statement->execute(array($name, $size, $date_of_birth, $gender, $address_line_one, $adress_line_two, $driver_license, $nationality, $telephone, $marital_state, $text, $ID)); 
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
				$statement->execute(array($name, $size, $date_of_birth, $gender, $address_line_one, $adress_line_two, $driver_license, $nationality, $telephone, $marital_state, $text, $user_id)); 
		}
		else {
		$statement = $db->prepare('UPDATE users_data set name=?, size=?, date_of_birth=?, Gender=?, adres_line_one=?, adres_line_two=?, driver_license=?, nationality=?, telephone =?, marital_state=?, text=? where users_Id_Users=?');
		$statement->execute(array($name, $size, $date_of_birth, $gender, $address_line_one, $adress_line_two, $driver_license, $nationality, $telephone, $marital_state, $text, $user_id)); 
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
		
		$statement = $db->prepare('SELECT shifts.name,shifts.details,shifts.length,shifts.people_needed,shifts.spare_needed,shifts.festival_idfestival  FROM shifts inner join festivals on shifts.festival_idfestival = festivals.idfestival where festivals.status != 6;');
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
		$statement = $db->prepare('SELECT festivals.status, festivals.name AS "festiname", shifts.idshifts , shifts.name,shifts.datails,shifts.length,shifts.people_needed,shifts.spare_needed,shifts.festival_idfestival  FROM shifts inner join festivals on shifts.festival_idfestival = festivals.idfestival where festivals.status != 6;');
		$statement->execute();
		$counter = 0;
		while ($row = $statement->fetch(PDO::FETCH_ASSOC)) {
			$statement2 = $db->prepare('select count(distinct users_Id_Users) from work_day inner join shift_days on work_day.shift_days_idshift_days = shift_days.idshift_days where shift_days.shifts_idshifts = ?');
			$statement2->execute(array($row["idshifts"]));
			$res2 = $statement2->fetchAll();
			$row["subscribed"] = $res2[0]["count(distinct users_Id_Users)"];
			
			$statement2 = $db->prepare('select count(distinct users_Id_Users) from work_day inner join shift_days on work_day.shift_days_idshift_days = shift_days.idshift_days where shift_days.shifts_idshifts = ? and work_day.reservation_type = 3;');
			$statement2->execute(array($row["idshifts"]));
			$res2 = $statement2->fetchAll();
			$row["subscribed_final"] = $res2[0]["count(distinct users_Id_Users)"];
			
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
		$statement = $db->prepare('SELECT festivals.status, shifts.idshifts, shift_days.cost, shift_days.idshift_days, shift_days.shift_end, shift_days.start_date FROM shift_days inner join shifts on shifts.idshifts = shift_days.shifts_idshifts inner join festivals on festivals.idfestival = shifts.festival_idfestival where festivals.status != 6 AND festivals.status != 7;');
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
			$notification_text = 'Ja bent nu geregistreerd voor ' . $festival_name . '. Wacht je definitieve inschrijving af.';
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
								All-round Events vzw </br>
								Maatschappelijke zetel: </br>
								Grote Baan 11B2 </br>
								1673 Pepingen</small></p>" .
						"</html>";
			$headers = 'From: inschrijvingen@all-round-events.be' . "\r\n" .
			'Reply-To: inschrijvingen@all-round-events.be' . "\r\n" .
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
								All-round Events VZW
								Maatschappelijke zetel: Grote Baan 11B2 1673 Pepingen</small></p>" .
						"</html>";
			$headers = 'From: inschrijvingen@all-round-events.be' . "\r\n" .
			'Reply-To: inschrijvingen@all-round-events.be' . "\r\n" .
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
									All-round Events VZW
									Maatschappelijke zetel: Grote Baan 11B2 1673 Pepingen</small></p>" .
							"</html>";
				$headers = 'From: inschrijvingen@all-round-events.be' . "\r\n" .
				'Reply-To: inschrijvingen@all-round-events.be' . "\r\n" .
				"Content-type:text/html;charset=UTF-8" . "\r\n" .
				'X-Mailer: PHP/' . phpversion();
				mail($email, $subject, $message, $headers);
			
			
		}
		else {
			admin_check($ID, $HASH, $db);
				$notification_text = 'Je zal jammer genoeg niet kunnen deelnamen aan  ' . $festival_name . ' in shift ' . $shift_info . '. Er komen snel andere evenementen! Hou je app in de gaten!';
				$statement = $db->prepare('INSERT INTO notifications (notification, global,user_id) VALUES (?,?,?);');
				$statement->execute(array($notification_text, 0, $Id_Users));
				$subject = 'All-Round Events: Update voor  ' . $festival_name;
				$message = '<html>
								<p>Beste,</p>
								<p>Helaas zal je niet kunnen deelnamen aan  ' . $festival_name . '. </br></p>
								<p> Je had jezelf opgegeven voor volgende dagen: :</p>
								' . $shift_info .
								"<p></p>
								<p>Helaas waren we al met voldoende vrijwilligers voor dit evenement, kijk zeker uit naar onze evenementen!</p>
								<p></p>
								<p>Met vriendelijke groeten</p>
								<p><small>
									All-round Events VZW
									Maatschappelijke zetel: Grote Baan 11B2 1673 Pepingen</small></p>" .
							"</html>";
				$headers = 'From: inschrijvingen@all-round-events.be' . "\r\n" .
				'Reply-To: inschrijvingen@all-round-events.be' . "\r\n" .
				"Content-type:text/html;charset=UTF-8" . "\r\n" .
				'X-Mailer: PHP/' . phpversion();
				mail($email, $subject, $message, $headers);
			
		}
		
		$statement = $db->prepare('delete s.* from work_day s inner join shift_days w on w.idshift_days = s.shift_days_idshift_days where s.users_Id_Users = ? and w.shifts_idshifts = ?; ');
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
									All-round Events VZW
									Maatschappelijke zetel: Grote Baan 11B2 1673 Pepingen</small></p>" 
							</html>';
				$headers = 'From: aankondigen@all-round-events.be' . "\r\n" .
				'Reply-To: aankondigen@all-round-events.be' . "\r\n" .
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
									All-round Events VZW
									Maatschappelijke zetel: Grote Baan 11B2 1673 Pepingen</small></p>" 
							</html>';
				$headers = 'From: aankondigen@all-round-events.be' . "\r\n" .
				'Reply-To: aankondigen@all-round-events.be' . "\r\n" .
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
								<p>De uitbetalingen voor ' . $festival_name . ' zullen plaatsvinden de komende dagen.  </br></p>
								<p>Je zal een mail ontvangen van zodra de uitbetaling voor jou persoonlijk is gebeurt! We willen je nogmaals bedanken voor je medewerkingen en hopen je de volgende keer terug te zien! </p>
								<p> </p>
								<p>Met vriendelijke groeten</p>
								<p><small>
									All-round Events VZW
									Maatschappelijke zetel: Grote Baan 11B2 1673 Pepingen</small></p>" 
							</html>';
				$headers = 'From: aankondigen@all-round-events.be' . "\r\n" .
				'Reply-To: aankondigen@all-round-events.be' . "\r\n" .
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
				$subject = 'All-Round Events: ' . $festival_name . '  is gecanceld';
				$message = '<html>
								<p>Beste,</p>
								<p>Jammer genoeg zal  ' . $festival_name . 'niet doorgaan dit jaar. Onze excuses voor het ongemak! </br></p>

								<p>Kijk voor meer evenementen op:</p>
								<p>https://all-round-events.be/html/nl/inschrijven.html</p>
								<p> </p>
								<p>Met vriendelijke groeten</p>
								<p><small>
									All-round Events VZW
									Maatschappelijke zetel: Grote Baan 11B2 1673 Pepingen</small></p>" 
							</html>';
				$headers = 'From: aankondigen@all-round-events.be' . "\r\n" .
				'Reply-To: aankondigen@all-round-events.be' . "\r\n" .
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
		$statement = $db->prepare('select work_day.Payout, festivals.idfestival, shifts.name, work_day.users_Id_Users, shifts.idshifts, shift_days.cost, users_data.adres_line_two, users_data.name, work_day.in, work_day.out, work_day.present, shift_days.start_date from work_day inner join users_data on work_day.users_Id_Users = users_data.users_Id_Users inner join shift_days on work_day.shift_days_idshift_days = shift_days.idshift_days inner join shifts on shifts.idshifts = shift_days.shifts_idshifts inner join festivals on festivals.idfestival = shifts.festival_idfestival where festivals.idfestival = ? and work_day.reservation_type = 3;');
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
		$header = array('foto', 'Naam', 'in','out','aanwezig', '','');
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
			
			$pdf->Cell($w[2],10,$in,1);
			$pdf->Cell($w[3],10,$out,1);
			$pdf->Cell($w[4],10,$present,1);
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
						<p>Je hebt een wachtwoord reset aagevraagd, hieronder vind u uw nieuw wachtwoord. Indien u uw wachtwoord wilt wijzingen kunt u dit doen door in te loggen en naar uw profiel te gaan. </br></p>
						<p>Uw email: '. $email .'</br></p>
						<p>Uw wachtwoord: '. $pass .'</br></p>
						<p> </p>
						<p>Met vriendelijke groeten</p>
						<p><small>
							All-round Events VZW
							Maatschappelijke zetel: Grote Baan 11B2 1673 Pepingen</small></p>" 
					</html>';
		$headers = 'From: info@all-round-events.be' . "\r\n" .
		'Reply-To: info@all-round-events.be' . "\r\n" .
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
		$statement = $db->prepare("SELECT email ,users.Id_Users from work_day inner JOIN users on work_day.users_Id_Users = users.Id_Users inner JOIN shift_days on work_day.shift_days_idshift_days = shift_days.idshift_days where shift_days.shifts_idshifts = ?;");
		$statement->execute(array($shift_id));
		$res = $statement->fetchAll();
		foreach ($res as &$line) {
			$email = $line["email"];
			$id_pusher = $line["id_Users"];
			$message = "<html><p>" . str_replace("\n","</br>", $text) . "</p></html>";
			$message_mail = "<html><p>" . str_replace("\n","</br></p><p>", $text) . "</p></html>";
			$headers = 'From: info@all-round-events.be' . "\r\n" .
			'Reply-To: info@all-round-events.be' . "\r\n" .
			"Content-type:text/html;charset=UTF-8" . "\r\n" .
			'X-Mailer: PHP/' . phpversion();
			mail($email, $subject, $message_mail, $headers);

			$notification_text = $text;
			$statement = $db->prepare('INSERT INTO notifications (notification, global, user_id) VALUES (?,?,?);');
			$statement->execute(array($message, 0, $id_pusher));
		}
		 
		$statement = $db->prepare("select email from users where users.Id_Users = ?");
		$statement->execute(array($user_id));
		$res = $statement->fetchAll();
		foreach ($res as &$line) {
			$email = $line["email"];
			$id_pusher = $line["id_Users"];
			$message = "<html><p>" . str_replace("\n","</br>", $text) . "</p></html>";
			$message_mail = "<html><p>" . str_replace("\n","</br></p><p>", $text) . "</p></html>";
			$headers = 'From: info@all-round-events.be' . "\r\n" .
			'Reply-To: info@all-round-events.be' . "\r\n" .
			"Content-type:text/html;charset=UTF-8" . "\r\n" .
			'X-Mailer: PHP/' . phpversion();
			mail($email, $subject, $message_mail, $headers);

			$notification_text = $text;
			$statement = $db->prepare('INSERT INTO notifications (notification, global, user_id) VALUES (?,?,?);');
			$statement->execute(array($message, 0, $user_id));
		}

		$statement = $db->prepare("SELECT email ,users.Id_Users from work_day inner JOIN users on work_day.users_Id_Users = users.Id_Users inner JOIN shift_days on work_day.shift_days_idshift_days = shift_days.idshift_days inner JOIN shifts on shifts.idshifts = shift_days.shifts_idshifts where shifts.festival_idfestival = ?;");
		$statement->execute(array($festi_id));
		$res = $statement->fetchAll();
		foreach ($res as &$line) {
			$email = $line["email"];
			$id_pusher = $line["Id_Users"];
			$message = "<html><p>" . str_replace("\n","</br>", $text) . "</p></html>";
			$message_mail = "<html><p>" . str_replace("\n","</br></p><p>", $text) . "</p></html>";
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

	
	else {
		exit(json_encode(array(
			'status' => 404,
			'error_type' => 10,
			'error_message' => "not a valid action"
		)));
	}






	