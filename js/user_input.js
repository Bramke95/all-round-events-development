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

	// makes a JSON out of the data en makes a api call to insert it in the DB
	function insert(user, dateofbirth, gender, address_1, address_2, telephone, driver_license, country, text, marital_state){
		var coockie = JSON.parse(getCookie("YOUR_CV_INLOG_TOKEN_AND_ID"));
		body = {
			"id" 	: coockie.ID,
			"hash"  : coockie.TOKEN,
			"name"  : user,
			"date_of_birth" : dateofbirth,
			"Gender" : gender,
			"adres_line_one" : address_1,
			"adres_line_two" : address_2,
			"driver_license" : driver_license,
			"nationality" : country,
			"telephone" : telephone,
			"marital_state" : marital_state,
			"text": text,
		}
		api("insert_main", body, insert_callback)
	};

	// callback 
	function insert_callback(res){
		if (res == "ERROR"){
			$("#error").html("<p><strong>De server is niet bereikbaar, bent u nog verbonden met het Internet?</strong></p>");
		}
		if (res["status"] == 200) {
			window.location.href = "user.html";
			
		}
		else {
			if (res["error_type"] == 4){
				window.location.href = "login.html";
			}
			else if (res["error_type"] == 7){
				$("#error").html("<p><strong>U heeft niet voldoende rechten om dit profiel te wijzigen.</strong></p>");
			}
			else {
				$("#error").html("<p><strong>Er is iets misgelopen, onze excuses voor het ongemak!</strong></p>");
			}
		}
	};
	function autofill() {
		var coockie = JSON.parse(getCookie("YOUR_CV_INLOG_TOKEN_AND_ID"));
		if (!coockie){
			window.location.href = "login.html";
		}
		api("get_main",{"id" : coockie.ID, "hash" : coockie.TOKEN}, autofill_callback_main )
		api("get_education",{"id" : coockie.ID, "hash" : coockie.TOKEN},autofill_callback_education)
	}

	function autofill_callback_main(res){
		if (res == "ERROR"){
			alert("Communication to the server failed")
		}
		if (res.error_type == 4){
			window.location.href = "login.html";
		}
		if (res != 100){
			$("#fname").val(res.name);

			var date = new Date(res.date_of_birth);
			var input_date = formatDate(date)
			$("#dateofbirth").val(input_date);

			$("#gender").val(res.Gender);
			$("#address_1").val(res.adres_line_one);
			$("#address_2").val(res.adres_line_two);
			$("#tel").val(res.telephone);
			$("#license").val(res.driver_license);
			$("#country").val(res.nationality);
			$("#text").val(res.text);
			$("#marital_state").val(res.marital_state);
		}
	}

	function add_education(from, to, school_name, education, results) {
		var coockie = JSON.parse(getCookie("YOUR_CV_INLOG_TOKEN_AND_ID"));
		if (!coockie){
			window.location.href = "login.html";
		}
		body = {
			"id": coockie.ID,
			 "hash" : coockie.TOKEN,
			 "from" : from,
			 "to"   : to,
			 "school" : school_name,
			 "education" : education,
			 "percentage" : results
		};
		api("add_education",body, add_education_callback )
	}

	function add_education_callback(res) {
		api("get_education",{"id" : coockie.ID, "hash" : coockie.TOKEN},add_education_callback)
	}
	function autofill_callback_education(res) {
		console.log(res);
		for(var i = 0; i < res.length; i++) {
			"<tr><td></td><td></td><td></td><td></td><td></td><td></td></tr>"
			$("#educations_input").append();
		}	
	}


	// wait till DOM is loaded
	$( document ).ready(function() {

		// click to insert data
    	$("#submit").click(function() {
			var user = $("#fname").val();
			var date_of_birth = $("#dateofbirth").val();
			var gender = $("#gender").val();
			var address_1 = $("#address_1").val();
			var address_2 = $("#address_2").val();
			var telephone = $("#tel").val();
			var driving_license = $("#license").val();
			var country = $("#country").val();
			var text = $("#text").val();
			var marital_state = $("#marital_state").val();
			insert(user, date_of_birth, gender, address_1, address_2, telephone, driving_license, country, text, marital_state);
		});

		$("#submit_education").click(function(){
			var from = $("#from").val();
			var to = $("#to").val();
			var school_name = $("#school").val();
			var educations = $("#education").val();
			var results = $("#results").val();

			add_education(from, to, school_name, educations, results);

		});
		// get date to automaticly fill
		autofill();
	});
})();