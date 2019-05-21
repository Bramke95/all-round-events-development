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

	 // function that gets the cookie for the user ID and the TOKEN that are used to do API calls 
	function getCookie(name) {
	  var nameEQ = name + "=";
	  var ca = document.cookie.split(';');
	  for (var i = 0; i < ca.length; i++) {
	    var c = ca[i];
	    while (c.charAt(0) == ' ') c = c.substring(1, c.length);
	    if (c.indexOf(nameEQ) == 0) return c.substring(nameEQ.length, c.length);
	  }
	  return null;
	}

	// format date to the correct format for the input field 
	function formatDate(date) {
    var d = new Date(date),
        month = '' + (d.getMonth() + 1),
        day = '' + d.getDate(),
        year = d.getFullYear();

    if (month.length < 2) month = '0' + month;
    if (day.length < 2) day = '0' + day;

    return [year, month, day].join('-');
	}
	// function that makes api calles
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

	function autofill() {
		var coockie = JSON.parse(getCookie("YOUR_CV_INLOG_TOKEN_AND_ID"));
		if (!coockie){
			window.location.href = "login.html";
		}
		api("get_main",{"id" : coockie.ID, "hash" : coockie.TOKEN}, autofill_callback )
	}

	function autofill_callback(res){
		if (res == "ERROR"){
			alert("Communication to the server failed")
		}
		if (res.error_type == 4){
			window.location.href = "login.html";
		}
		if (res != 100){
			// infill
			$("#name").text(res.name);
			$("#date").text(res.date_of_birth);
			$("#gender").text(res.Gender);
			$("#address_1").text(res.adres_line_one);
			$("#address_2").text(res.adres_line_two);
			$("#age").text("23");
			$("#license").text(res.driver_license);
			$("#nationality").text(res.nationality);
			$("#email").text(res.email);
			$("#tel").text(res.telephone);
			$("#marital_state").text(res.marital_state);
		}
	}

	// wait till DOM is loaded
	$( document ).ready(function() {
		// get date to automaticly fill
		autofill();
	});
})();