/*
 * This is 
 *
 *
 * 
 */


 // global variables that are needed to use the api 
 var USER_ID = "";
 var TOKEN = "";
 var LOGGED_IN = false;
 var url = "../../api.php?action=";

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
            callback("ERROR")   ;
        } 
	});
};

// function to validate email
function validateEmail(email) {
    var re = /^(([^<>()\[\]\\.,;:\s@"]+(\.[^<>()\[\]\\.,;:\s@"]+)*)|(".+"))@((\[[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\])|(([a-zA-Z\-0-9]+\.)+[a-zA-Z]{2,}))$/;
    return re.test(String(email).toLowerCase());
};


// send an complaint to the server 
function complaint(name, first_name, type, text){
	api("insert_complaint", {"name" : name , "first_name" : first_name, "type": type,"text" : text}, complaint_callback);
};

// a complain is send, or something whent wrong and an error message is displayed or 
function complaint_callback(res){
	if (res == "ERROR"){
			$("#error").html("<p><strong>Dat ging niet hellemaal goed, probeert u het later opnieuw! </strong></p>");
	}
	else {
		if (res["status"] == 200){
				$("#contact_space").html("");
	    		$("#contact_space").html('<div id="success"><p>Uw vraag werd verstuurd, U kan <a href="home.html">terug gaan.</a></p></div>');
	    		return
		}
		else {
			$("#error").html("<p><strong>Dat ging niet hellemaal goed, probeert u het later opnieuw! </strong></p>");
		}
	}
};
;(function() { 

	// add event listeners to the page 
	$(document).ready(function() {
	    	$("#send").click(function() {
				var name = $("#name").val();
				var first_name = $("#first_name").val();
				var email = $("#email").val();
				var type = $("#type :selected").text();
				var text = $("#text").val()
				if(name =="" || first_name == "" || type == "" || text == ""){
					$("#error").html("<p><strong>Gelieve alle velden in te vullen!</strong></p>");
					return
				}
				if (!validateEmail(email)){
					$("#error").html("<p><strong>Het email adres is niet geldig, gelieve een correct email adres op te geven! </strong></p>");
					return;
				}
				complaint(name, first_name, type, text);
			});
		});
})();