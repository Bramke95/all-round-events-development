/*
 * this page is the starting point to make an new acount on this website, you need an email and password and an activation code to make this acount
 * 
 *
 * @author : Bram Verachten
 * 
 */
 
;(function() { 

	 // global variables that are needed to use the api 
	 var USER_ID = "";
	 var TOKEN = "";
	 var LOGGED_IN = false;
	 var url = "../../api.php?action="
	 var PASS = "";

	// function to set the id and Token in an coockie, that way the user is logged in on every page
	function setCookie(name, value, days) {
	  var expires = "";
	  if (days) {
	    var date = new Date();
	    date.setTime(date.getTime() + (days * 24 * 60 * 60 * 1000));
	    expires = "; expires=" + date.toUTCString();
	  }
	  document.cookie = name + "=" + (value || "") + expires + "; path=/";
	}
	
	// function that performes api calls to the server
	function api(action, body, callback){
		$.ajax({
		    type: 'POST',
		    url: url + action,
		    data: JSON.stringify(body),
		    success: function(resp){
		        callback(JSON.parse(resp));
		    },
		    error: function(XMLHttpRequest, textStatus, errorThrown) { 
                 callback("ERROR")   
            } 
		});
	};
	
	// function that validates an email address, that way we don't allow for invalid email addresses
	function validateEmail(email) {
    	var re = /^(([^<>()\[\]\\.,;:\s@"]+(\.[^<>()\[\]\\.,;:\s@"]+)*)|(".+"))@((\[[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\])|(([a-zA-Z\-0-9]+\.)+[a-zA-Z]{2,}))$/;
    	return re.test(String(email).toLowerCase());
	};
	
	// startin the process of creating an new acount on this website 
	function create_basic_user(username, pass, code){
		api("new_user", {"email" : username, "pass" : pass, "activation_code": code}, create_basic_user_callback)
	};
	
	// the callback function for creating an new acound. If ok the user is moved to the `add user date` page. otherwise an error message is given
	function create_basic_user_callback(res){
		if (res == "ERROR"){
			$("#error").html("<p><strong>Er liep helaas iets mis, probeer het later opnieuw!</strong></p>");
		}
		if (res["status"] == 200) {
			// the user is made at server end, the user us also loggin in as well
			setCookie('YOUR_CV_INLOG_TOKEN_AND_ID',JSON.stringify({"ID":  res.id  ,'TOKEN':  res.hash }), 14);
			window.location.href = "user_input.html";
		}
		else {
			if (res["error_type"] == 3){
				// the given activation code is wrong
				$("#error").html("<p><strong>De toegangscode die u opgaf is niet geldig!</strong></p>");
			}
			else if (res["error_type"] == 1){
				// the email the user is trying to use is already in use
				$("#error").html("<p><strong>U email adres is reeds gebruikt, gelieve in te loggen!</strong></p>");
			}
			else {
				// another unknown issue, just report the error massae from the server 
				$("#error").html("<p><strong>De server geeft volgende error boodschap: "+ res["error_message"]+"</strong></p>");
			}
		}
	};
	
	// starting point for the webpge, mainly button event handles for registring and cancling 
	$( document ).ready(function() {
    	$("#BUT_reg").click(function() {
			var email = $("#email_textfield").val()
			email = email.replace(/\s/g, '');
			var pass = $("#pass_textfield").val()
			var code = $("#token_textfield").val()
			GLOBAL_PASS = pass;
			// check if all the required fields are filled in
			if (email == "" || pass == "" || code == ""){
				$("#error").html("<p><strong>Niet alle velden zijn ingevuld! </strong></p>");
				return;
			}
			// check if the email is correct
			if (!validateEmail(email)){
				$("#error").html("<p><strong>Ongeldig email adres verwacht formaat : voorbeeld@email.com</strong></p>");
				return;
			}
			// check if the passowrd is somewhat decent
			if (pass.length < 5){
				$("#error").html("<p><strong> Uw passwoord moet uit minstens 6 karakters bestaan.</strong></p>");
				return;
			}
			create_basic_user(email, pass, code)
		});
		$("#cancel").click(function(){
			window.location.href = "home.html";
		});
	});
})();