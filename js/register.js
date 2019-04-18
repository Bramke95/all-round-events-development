/*
 * your website
 *
 * @author : Bram Verachten
 * @date : 15/05/2018
 * 
 */
;(function() { 

	 // global variables that are needed to use the api 
	 var USER_ID = "";
	 var TOKEN = "";
	 var LOGGED_IN = false;
	 var url = "../../api.php?action="
	 var PASS = "";

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
	function validateEmail(email) {
    	var re = /^(([^<>()\[\]\\.,;:\s@"]+(\.[^<>()\[\]\\.,;:\s@"]+)*)|(".+"))@((\[[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\])|(([a-zA-Z\-0-9]+\.)+[a-zA-Z]{2,}))$/;
    	return re.test(String(email).toLowerCase());
	};
	// creating basic accound + callback 
	function create_basic_user(username, pass, code){
		api("new_user", {"email" : username, "pass" : pass, "activation_code": code}, create_basic_user_callback)
	};
	function create_basic_user_callback(res){
		if (res == "ERROR"){
			$("#error").html("<p><strong>De server is niet bereikbaar, bent u nog verbonden met het Internet?</strong></p>");
		}
		if (res["status"] == 200) {
			alert("re-direct");
			// a coocie with the token should be made
			// the user should be re-directed to his userspace page
		}
		else {
			if (res["error_type"] == 3){
				$("#error").html("<p><strong>De toegangscode die u opgaf is niet geldig!</strong></p>");
			}
			else if (res["error_type"] == 1){
				$("#error").html("<p><strong>U email adres is reeds gebruikt, gelieve in te loggen!</strong></p>");
			}
			else {
				$("#error").html("<p><strong>De server geeft volgende error code : "+ res["error_message"]+"</strong></p>");
			}
		}
	};
	$( document ).ready(function() {
		// 
    	$("#BUT_reg").click(function() {
			var email = $("#email_textfield").val()
			var pass = $("#pass_textfield").val()
			var code = $("#token_textfield").val()
			GLOBAL_PASS = pass;
			if (email == "" || pass == "" || code == ""){
				$("#error").html("<p><strong>Niet alle velden zijn ingevuld! </strong></p>");
				return;
			}
			if (!validateEmail(email)){
				$("#error").html("<p><strong>Ongeldig email adres verwacht formaat : voorbeeld@email.com</strong></p>");
				return;
			}
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