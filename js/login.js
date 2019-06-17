/*
 * your website
 *
 * @author : Bram Verachten
 * @date : 15/05/2019
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
	function validateEmail(email) {
    	var re = /^(([^<>()\[\]\\.,;:\s@"]+(\.[^<>()\[\]\\.,;:\s@"]+)*)|(".+"))@((\[[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\])|(([a-zA-Z\-0-9]+\.)+[a-zA-Z]{2,}))$/;
    	return re.test(String(email).toLowerCase());
	};
	// logging in + callback
	function login(username, pass){
		api("login", {"email" : username, "pass" : pass}, loging_callback)
	};
	function loging_callback(res){
		if (res == "ERROR"){
			$("#error").html("<p><strong>De server is niet bereikbaar, bent u nog verbonden met het Internet?</strong></p>");
		}
		if (res["status"] == 200) {
			setCookie('YOUR_CV_INLOG_TOKEN_AND_ID',JSON.stringify({"ID":  res.id  ,'TOKEN':  res.hash }), 14);
			window.location.href = "user.html";
			
		}
		else {
			if (res["error_type"] == 6){
				$("#error").html("<p><strong>De inloggegevens waren niet correct, gelieve opnieuw te proberen! </strong></p>");
			}
			else if (res["error_type"] == 5){
				$("#error").html("<p><strong>De inloggegevens waren niet correct, gelieve opnieuw te proberen! </strong></p>");
			}
			else {
				$$("#error").html("<p><strong>De inloggegevens waren niet correct, gelieve opnieuw te proberen! </strong></p>");
			}
		}
	};

	$( document ).ready(function() {
    	$("#BUT_reg").click(function() {
			var email = $("#email_textfield").val()
			var pass = $("#pass_textfield").val()
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
		$("cancel").click(function(){
			window.location.href = "home.html";
		});

	});
})();