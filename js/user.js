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
	  window.location.href = "home.html";
	}
	
	function calculateAge(date) { // birthday is a date
		var dateParts = date.split("/");
		var birthday = new Date(+dateParts[2], dateParts[1] - 1, +dateParts[0]); 
		var ageDifMs = Date.now() - birthday.getTime();
		var ageDate = new Date(ageDifMs); // miliseconds from epoch
		return Math.abs(ageDate.getUTCFullYear() - 1970);
	}

	// format date to the correct format for the input field 
	function formatDate(date) {
		var d = new Date(date),
			month = '' + (d.getMonth() + 1),
			day = '' + d.getDate(),
			year = d.getFullYear();

		if (month.length < 2) month = '0' + month;
		if (day.length < 2) day = '0' + day;

		return [day, month, year].join('/');
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
		api("get_main",{"id" : coockie.ID, "hash" : coockie.TOKEN}, autofill_callback)
		api("get_pictures",{"id" : coockie.ID, "hash" : coockie.TOKEN},get_pictures_callback)
	}

	function get_education_callback(res){
		$("#schools").html("");
		for(var i = 0; i < res.length; i++) {
			$("#schools").prepend('<tr><td>'+ res[i].from_date +'</td><td>'+ res[i].to_date +'</td><td>'+ res[i].school +'</td><td>'+ res[i].education +'</td><td>'+ res[i].percentage +'</td></tr>');
		}
		if (res.length != undefined){
			$("#schools").prepend('<tr><th>Van</th><th>Tot</th><th>school</th><th>opleiding</th><th>percentage</th></tr>');
		}
		else {
			$("#study").hide();
		}
		
	}
	function get_language_callback(res){
		$("#language").html("");
		for(var i = 0; i < res.length; i++) {
			$("#language").prepend('<tr><td>'+ res[i].language +'</td><td>'+ res[i].speaking +'</td><td>'+ res[i].writing +'</td><td>'+ res[i].reading +'</td></tr>');
		}
		if (res.length != undefined){
			$("#language").prepend('<tr><th>Taal</th><th>Lezen</th><th>schrijven</th><th>Spreken</th></tr>');
		}
		else {
			$("#langu").hide();
		}
	}
	function get_expierence_callback(res){
		$("#work").html("");
		for(var i = 0; i < res.length; i++) {
			$("#work").prepend('<tr><td>'+ res[i].compamy +'</td><td>'+ res[i].jobtitle +'</td><td>'+ res[i].from_date +'</td><td>'+ res[i].to_date +'</td></tr>');
		}
		if (res.length != undefined){
			$("#work").prepend('<tr><th>Bedrijf</th><th>job titel</th><th>Van</th><th>Tot</th></tr>');
		}
		else {
			$("#ex_work").hide();
		}

	}
	function get_pictures_callback(res){
		if (res.length > 0){
			$("#front_picture").html("");
			for(var i = 0; i < res.length; i++) {
				if (res[i].is_primary == 1){
					$("#front_picture").append('<img src=/'+ res[i].picture_name +' alt="Smiley face" ">');
				}	
			}	
		}			
	}
	function autofill_callback(res){
		$("#title_box").prepend("<h1><strong>"+ res.name +"</strong></h1>");
		if (res == "ERROR"){
			alert("Communication to the server failed")
		}
		if (res.error_type == 4){
			window.location.href = "login.html";
		}
		if (res.error_type == 8){
			window.location.href = "user_input.html";
		}
		if (res != 100){
			// infill
			var date = new Date(res.date_of_birth);
			var input_date = formatDate(date);
			var gender_name = "INVALID";
			var marital_state_name = "INVALID";
			var employment = "INVALID";

			if (res.Gender == "0"){gender_name = "man";}
			else if (res.Gender == "1"){gender_name = "vrouw";}
			else {gender_name = "anders";}

			if (res.marital_state == 0){marital_state_name = "Ongehuwd";}
			else if (res.marital_state == 1){marital_state_name = "gehuwd";}
			else if (res.marital_state == 2){marital_state_name = "gescheiden";}
			else {marital_state_name = "verweduwd";}

			if (res.employment == 0){employment = "Student";}
			else if (res.employment == 1){employment = "Bediende";}
			else if (res.employment == 2){employment = "Werkloos";}
			else {employment = "andere";}

			$("#name").text(res.name || "-");
			$("#date").text(input_date || "-");
			$("#gender").text(gender_name || "-");
			$("#address_1").text(res.adres_line_one || "-");
			$("#address_2").text(res.adres_line_two || "-");
			$("#age").text(calculateAge(input_date));
			$("#size").text(res.size || "-");
			$("#license").text(res.driver_license || "-");
			$("#nationality").text(res.nationality || "-");
			$("#email").text(res.email || "-");
			$("#tel").text(res.telephone || "-");
			$("#marital_state").text(marital_state_name || "-");
			$("#employment").text(employment || "-");

			$("#text").text(res.text);
			
		}
	}
	
	function add_optional_management(){
		var coockie = JSON.parse(getCookie("YOUR_CV_INLOG_TOKEN_AND_ID"));
		api("is_admin",{"id" : coockie.ID, "hash" : coockie.TOKEN}, add_optional_management_callback)
	}
	function add_optional_management_callback(is_admin){
		if(is_admin.status == 200){
			$("#top_menu ul").append("<li><a href='admin.html'>Beheer</a></li>");
		}
	}

	// wait till DOM is loaded
	$( document ).ready(function() {
		// get date to automaticly fill
		add_optional_management();
		autofill();
	});
})();