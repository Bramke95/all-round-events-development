/*
 * This is the javascript for the login page: main functions:
	-> checking the imput before sending it to the server
	-> doing the actual api calls to the server
	-> login the user in with an token and ID an making an coockie for it
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

	// function that performes api calls to the server
	function setCookie(name, value, days) {
	  var expires = "";
	  if (days) {
	    var date = new Date();
	    date.setTime(date.getTime() + (days * 24 * 60 * 60 * 1000));
	    expires = "; expires=" + date.toUTCString();
	  }
	  document.cookie = name + "=" + (value || "") + expires + "; path=/";
	}
	
	// funtion that makes all the api calls
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
	
	// valdiates and email address with an regex
	function validateEmail(email) {
    	var re = /^(([^<>()\[\]\\.,;:\s@"]+(\.[^<>()\[\]\\.,;:\s@"]+)*)|(".+"))@((\[[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\])|(([a-zA-Z\-0-9]+\.)+[a-zA-Z]{2,}))$/;
    	return re.test(String(email).toLowerCase());
	};
	
	// logging in the user with email and password
	function login(username, pass){
		api("login", {"email" : username, "pass" : pass}, loging_callback)
	};
	
	// callback for logging in, or the user is logged in or on error message is displayed
	function loging_callback(res){
		if (res == "ERROR"){
			$("#error").html("<p><strong>De server is niet bereikbaar, bent u nog verbonden met het Internet?</strong></p>");
		}
		if (res["status"] == 200) {
			setCookie('YOUR_CV_INLOG_TOKEN_AND_ID',JSON.stringify({"ID":  res.id  ,'TOKEN':  res.hash ,'is_admin': res.is_admin}), 14);
			window.location.href = "inschrijven.html";
			
		}
		else {
			if (res["error_type"] == 6){
				$("#error").html("<p><strong>De inloggegevens waren niet correct, gelieve opnieuw te proberen! </strong></p>");
			}
			else if (res["error_type"] == 5){
				$("#error").html("<p><strong>De inloggegevens waren niet correct, gelieve opnieuw te proberen! </strong></p>");
			}
			else if (res["error_type"] == 21){
				$("#error").html("<p><strong>Uw account is tijdelijk geblokkeerd omdat er teveel inlogpogingen zijn gedetecteerd. Probeer het later opnieuw.</strong></p>");
			}
			else {
				$("#error").html("<p><strong>De inloggegevens waren niet correct, gelieve opnieuw te proberen! </strong></p>");
			}
		}
	};
	
	function reset_pass_callback(data){
		$("#email_reset_pass").html('<h1>Uw wachtwoord is verzonden naar het door u opgegeven email adres. <h1><input type="submit" id="reset_pass_abort" name="abort" value="Sluiten" placeholder="" style="background-color: red ;  margin-top:10px;">');
		$("#reset_pass_abort").click(function(){
			location.reload();
		});
		
	}

// starting function for this page, mainly for putting event handlers to the login, cancel button. Some checks are performed on the content in the input boxes
	$( document ).ready(function() {
    	$("#BUT_reg").click(function() {
			var email = $("#email_textfield").val();
			email = email.replace(/\s/g, '');
			var pass = $("#pass_textfield").val();
			if (email == "" || pass == ""){
				$("#error").html("<p><strong>Niet alle velden zijn ingevuld! </strong></p>");
				return;
			}
			if (!validateEmail(email)){
				$("#error").html("<p><strong>Ongeldig email adres verwacht formaat : voorbeeld@email.com</strong></p>");
				return;
			}
			login(email, pass)
		});
		
		$("#reset_pass_start").click(function(){
			$("#email_reset_pass").fadeIn(500);
			$("#reset_pass_abort").off();
			$("#reset_pass_abort").click(function(){
				$("#email_reset_pass").fadeOut(500);
			});
			$("#reset_pass").off();
			$("#reset_pass").click(function(){
				let email_field = $("#email_field").val();
				api("reset_pass", {"email" : email_field}, reset_pass_callback)
			});
			
			
		});
		$("cancel").click(function(){
			window.location.href = "home.html";
		});

	});
})();