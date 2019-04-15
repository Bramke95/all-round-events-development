/*
 * yourcv : This is a script used for testing perpuses only, should be removed from the official release 
 *
 * @author : Bram Verachten
 * @date : 15/05/2018
 * 
 */


 // global variables that are needed to use the api 
 var USER_ID = "";
 var TOKEN = "";
 var LOGGED_IN = false;
 var url = "api.php?action="

// function that performes api calls to the server
function api(action, body, callback){
	$.ajax({
	    type: 'POST',
	    url: url + action,
	    data: JSON.stringify(body),
	    success: function(resp){
	        callback(resp);
	    }
	});
};
// logging in + callback
function login(username, pass){
	api("login", {"email" : username, "pass" : pass}, loging_callback)
};
function loging_callback(res){
	$("#results").html("")
	$("#results").append(res)
};

// creating basic accound + callback 
function create_basic_user(username, pass){
	api("new_user", {"email" : username, "pass" : pass}, loging_callback)
};
function create_basic_user_callback(res){
	$("#results").html("")
	$("#results").append(res)
};


// event handleres 
;(function() { 
	// wait till DOM is loaded
	$(document).ready(function() {
    	console.log("ready!");
	    	$("#login_but").click(function() {
				var user = $("#username").val()
				var pass = $("#pass").val()
				login(user, pass)
			});

			$("#create_login").click(function() {
				var user = $("#username").val()
				var pass = $("#pass").val()
				create_basic_user(user, pass)
			});
		});
})();