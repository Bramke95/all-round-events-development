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
                 window.location.href = "error.html";   
            } 
		});
	};

	// makes a JSON out of the data en makes a api call to insert it in the DB
	function insert(user, dateofbirth, gender, address_1, address_2, telephone, driver_license, country, text, marital_state, size){
		var coockie = JSON.parse(getCookie("YOUR_CV_INLOG_TOKEN_AND_ID"));
		body = {
			"id" 	: coockie.ID,
			"hash"  : coockie.TOKEN,
			"name"  : user,
			"date_of_birth" : dateofbirth,
			"Gender" : gender,
			"size" : size,
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
			alert("Alle gegevens zijn opgeslagen!");
			
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
		$("#schools").prepend('<tr><th>Van</th><th>Tot</th><th>school</th><th>opleiding</th><th>percentage</th><th></th></tr>');
		api("get_main",{"id" : coockie.ID, "hash" : coockie.TOKEN}, autofill_callback_main )
		api("get_education",{"id" : coockie.ID, "hash" : coockie.TOKEN},get_education_callback)
		api("get_languages",{"id" : coockie.ID, "hash" : coockie.TOKEN},get_language_callback)
		api("get_expierence",{"id" : coockie.ID, "hash" : coockie.TOKEN},get_expierence_callback)
		api("get_pictures",{"id" : coockie.ID, "hash" : coockie.TOKEN},get_pictures_callback)
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
			$("#size2").val(res.size);
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

	function add_language(lang, speak, write, read) {
		var coockie = JSON.parse(getCookie("YOUR_CV_INLOG_TOKEN_AND_ID"));
		if (!coockie){
			window.location.href = "login.html";
		}
		body = {
			"id": coockie.ID,
			 "hash" : coockie.TOKEN,
			 "lang" : lang,
			 "speak"   : speak,
			 "write" : write,
			 "read" : read
		};
		api("add_language",body, add_language_callback )
	}

	function add_expierence(company, jobtitle, from_date, to_date) {
		var coockie = JSON.parse(getCookie("YOUR_CV_INLOG_TOKEN_AND_ID"));
		if (!coockie){
			window.location.href = "login.html";
		}
		body = {
			"id": coockie.ID,
			 "hash" : coockie.TOKEN,
			 "company" : company,
			 "jobtitle"   : jobtitle,
			 "from_date" : from_date,
			 "to_date" : to_date
		};
		api("add_expierence",body, add_expierence_callback )
	}

	function add_education_callback(res) {
		var coockie = JSON.parse(getCookie("YOUR_CV_INLOG_TOKEN_AND_ID"));
		api("get_education",{"id" : coockie.ID, "hash" : coockie.TOKEN},get_education_callback)
	}

	function add_language_callback(res) {
		var coockie = JSON.parse(getCookie("YOUR_CV_INLOG_TOKEN_AND_ID"));
		api("get_languages",{"id" : coockie.ID, "hash" : coockie.TOKEN},get_language_callback)
	}

	function add_expierence_callback(res) {
		var coockie = JSON.parse(getCookie("YOUR_CV_INLOG_TOKEN_AND_ID"));
		api("get_expierence",{"id" : coockie.ID, "hash" : coockie.TOKEN},get_expierence_callback)
	}

	function delete_education(education_id){
		var coockie = JSON.parse(getCookie("YOUR_CV_INLOG_TOKEN_AND_ID"));
		api("delete_education",{"id" : coockie.ID, "hash" : coockie.TOKEN, "education_id" : education_id},delete_callback)
	}

	function delete_language(language_id){
		var coockie = JSON.parse(getCookie("YOUR_CV_INLOG_TOKEN_AND_ID"));
		api("delete_language",{"id" : coockie.ID, "hash" : coockie.TOKEN, "language_id" : language_id},delete_callback)
	}

	function delete_expierence(expierence_id){
		var coockie = JSON.parse(getCookie("YOUR_CV_INLOG_TOKEN_AND_ID"));
		api("delete_expierence",{"id" : coockie.ID, "hash" : coockie.TOKEN, "idexpierence" : expierence_id},delete_callback)
	}

	function delete_callback(res){
		autofill();
	}
	function picture_update_callback(res){
		var coockie = JSON.parse(getCookie("YOUR_CV_INLOG_TOKEN_AND_ID"));
		api("get_pictures",{"id" : coockie.ID, "hash" : coockie.TOKEN},get_pictures_callback)
	}	

	function get_education_callback(res){
		$("#schools").html("");
		for(var i = 0; i < res.length; i++) {
			$("#schools").prepend('<tr><td>'+ res[i].from_date +'</td><td>'+ res[i].to_date +'</td><td>'+ res[i].school +'</td><td>'+ res[i].education +'</td><td>'+ res[i].percentage +'</td><td><input type="submit" class="delete_education" name="'+ res[i].ideducations_id +'" value="Verwijderen" placeholder=""></td></tr>');
		}
		$("#schools").prepend('<tr><th>Van</th><th>Tot</th><th>school</th><th>opleiding</th><th>percentage</th><th></th></tr>');
		$("#schools").append('<tr><td><input type="text" id="from" name="from" 		 placeholder=""></td><td><input type="text" id="to" name="to" placeholder=""></td><td><input type="text" id="school" name="school_name" placeholder=""></td><td><input type="text" id="education" name="educations"  placeholder=""></td><td><input type="text" id="results" name="result"placeholder=""></td><td><input type="submit" id="submit_education" name="add" value="Toevoegen" placeholder=""style="background-color: #4CAF50;"></td></tr>');
		$("#submit_education").click(function(){
			var from = $("#from").val();
			var to = $("#to").val();
			var school_name = $("#school").val();
			var educations = $("#education").val();
			var results = $("#results").val();

			add_education(from, to, school_name, educations, results);
		});
		$(".delete_education").click(function(){
			delete_education(this.name);
		});
	}

	function get_language_callback(res){
		$("#languages").html("");
		for(var i = 0; i < res.length; i++) {
				$("#languages").prepend('<tr><td>'+ res[i].language +'</td><td>'+ res[i].speaking +'</td><td>'+ res[i].writing +'</td><td>'+ res[i].reading +'</td><td><input type="submit" class="delete_languages" name="'+ res[i].language_id +'" value="Verwijderen" placeholder=""></td></tr>');
				
		}
		$("#languages").prepend('<tr><th>Taal</th><th>Spreken</th><th>Schrijven</th><th>Lezen</th><th></th></tr>');
		$("#languages").append('<tr><td><input type="text" id="lang" name="from" placeholder=""></td><td><input type="text" id="speak" name="to" placeholder=""></td><td><input type="text" id="write" name="school_name" placeholder=""></td><td><input type="text" id="read" name="educations"  placeholder=""></td><td><input type="submit" id="submit_languages" name="add" value="Toevoegen" placeholder="" style="background-color: #4CAF50;"></td></tr>');
		$("#submit_languages").click(function(){
			var lang = $("#lang").val();
			var speak = $("#speak").val();
			var write = $("#write").val();
			var read = $("#read").val();


			add_language(lang, speak, write, read);
		});
		$(".delete_languages").click(function(){
			delete_language(this.name);
		});
	}

	function get_expierence_callback(res){
		$("#work").html("");
		for(var i = 0; i < res.length; i++) {
			$("#work").prepend('<tr><td>'+ res[i].compamy +'</td><td>'+ res[i].jobtitle +'</td><td>'+ res[i].from_date +'</td><td>'+ res[i].to_date +'</td><td><input type="submit" class="delete_expierence" name="'+ res[i].idexpierence +'" value="Verwijderen" placeholder=""></td></tr>');
		}
		$("#work").prepend('<tr><th>Bedrijf</th><th>job titel</th><th>Van</th><th>Tot</th><th></th></tr>');
		$("#work").append('<tr><td><input type="text" id="company" name="from" placeholder=""></td><td><input type="text" id="jobtitle" name="to" placeholder=""></td><td><input type="text" id="from_work" name="school_name" placeholder=""></td><td><input type="text" id="to_work" name="educations"  placeholder=""></td><td><input type="submit" id="submit_expierence" name="add" value="Toevoegen" placeholder=""style="background-color: #4CAF50;"></td></tr>');
		$("#submit_expierence").click(function(){
			var compamy = $("#company").val();
			var jobtitle = $("#jobtitle").val();
			var from_date = $("#from_work").val();
			var to_date = $("#to_work").val();


			add_expierence(compamy, jobtitle, from_date, to_date);
		});
		$(".delete_expierence").click(function(){
			delete_expierence(this.name);
		});
	}

	function get_pictures_callback(res){
		$("#picture_placeholder").html("");
		for(var i = 0; i < res.length; i++) {
			if (res[i].is_primary == 0){
				$("#picture_placeholder").append('<div class="show-image"><img src=/'+ res[i].picture_name +' /><input class="make_profile" type="button" value="profielfoto" name="'+ res[i].picture_name+'" /><input class="delete" type="button" value="Verwijder" name="'+res[i].picture_name+'"/></div>');
			}
			else {
				$("#picture_placeholder").prepend('<div class="show-image"><img style="border: 4px solid green;" src=/'+ res[i].picture_name +' /><input class="delete" type="button" value="Verwijder" name="'+res[i].picture_name+'"/><input class="delete" type="button" value="Verwijder" name="'+res[i].picture_name+'"/></div>');

			}
		}
		$(".make_profile").click(function() {
			var coockie = JSON.parse(getCookie("YOUR_CV_INLOG_TOKEN_AND_ID"));
			api("make_profile",{"id" : coockie.ID, "hash" : coockie.TOKEN, "image" : this.name}, picture_update_callback)
		});
		$(".delete").click(function() {
			var coockie = JSON.parse(getCookie("YOUR_CV_INLOG_TOKEN_AND_ID"));
			api("delete_picture",{"id" : coockie.ID, "hash" : coockie.TOKEN,  "image" : this.name}, picture_update_callback)
		});			
		
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

		// click to insert data
    	$("#submit").click(function() {
			var user = $("#fname").val();
			var date_of_birth = $("#dateofbirth").val();
			var gender = $("#gender").val();
			var address_1 = $("#address_1").val();
			var address_2 = $("#address_2").val();
			var telephone = $("#tel").val();
			var driving_license = $("#license").val();
			var size = $("#size2").val();
			var country = $("#country").val();
			var text = $("#text").val();
			var marital_state = $("#marital_state").val();
			insert(user, date_of_birth, gender, address_1, address_2, telephone, driving_license, country, text, marital_state, size);
		});
		$("#submit_pass").click(function(event) {
			let pass =  $("#pass_textfield").val();
			var coockie = JSON.parse(getCookie("YOUR_CV_INLOG_TOKEN_AND_ID"));
			api("change_pass",{"id" : coockie.ID, "hash" : coockie.TOKEN, "new_pass": pass}, function(){
				alert("wachtwoord gewijzigd.");
			})
			
		});
		

        $("#form_img").submit(function(e){
            e.preventDefault();
            var coockie = JSON.parse(getCookie("YOUR_CV_INLOG_TOKEN_AND_ID"));
            var formData = new FormData($("#form_img")[0]);
            formData.append("auth", JSON.stringify(coockie))
            $.ajax({
                url : $("#form_img").attr('action'),
                type : 'POST',
                data : formData,
                contentType : false,
                processData : false,
                success: function(resp) {
                    console.log(resp);
					if (JSON.parse(resp).error_type == 9){
						alert("Je kan niet meer dan 5 afbeeldingen opslaan per gebruiker. ");
					}
					else if (JSON.parse(resp).error_type == 11){
						alert("Geen geldige afbeeldingen gevonden, gelieve een gif, jpg, jpeg of png file te gebruiken! ");
					}
					else if (JSON.parse(resp).error_type != 0){
						alert("Het uploaden van de afbeelding is mislukt!  ");
					}
					else {
						api("get_pictures",{"id" : coockie.ID, "hash" : coockie.TOKEN},get_pictures_callback) 
					}
                                            
                },
                error: function(XMLHttpRequest, textStatus, errorThrown) { 
                    alert("Status: " + textStatus); alert("Error: " + errorThrown); 
                } 
            });
        });
		add_optional_management();
        autofill();
             
	});
})();