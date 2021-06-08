// global variables that are needed to use the api 
var USER_ID = "";
var TOKEN = "";
var LOGGED_IN = false;
var open_id = -1;
var url = "../../api.php?action=";
unemployment = false;
		
$( document ).ready(function() {
	add_optional_management();
	load_festivals_shifts();
});

function add_optional_management(){
	var coockie = JSON.parse(getCookie("YOUR_CV_INLOG_TOKEN_AND_ID"));
	api("is_admin",{"id" : coockie.ID, "hash" : coockie.TOKEN}, add_optional_management_callback)
}
function add_optional_management_callback(is_admin){
	if(is_admin.status == 200){
		$("#top_menu ul").append("<li><a href='admin.html'>Beheer</a></li>");
	}
}

function load_festivals_shifts(){
	
	var coockie = JSON.parse(getCookie("YOUR_CV_INLOG_TOKEN_AND_ID"));
	api("get_festivals", {"id" : coockie.ID, "hash" : coockie.TOKEN, "select": "active" , "festi_id":"invalid"}, festival_shift_processing);
	api("get_main",{"id" : coockie.ID, "hash" : coockie.TOKEN}, check_user_data)
}
function check_user_data(res){
	if (res == "ERROR"){
		alert("Communication to the server failed");
	}
	if (res.error_type == 4){
		window.location.href = "login.html";
	}
	if (res.error_type == 8){
		alert("Je kan jezelf niet inschrijven zonder je persoonlijke gegevens in te vullen!")
		window.location.href = "user_input.html";
	}
	if (res.employment == "2"){
		unemployment = true;
	}
}

// function that gets the cookie for the user ID and the TOKEN that are used to do API calls 
function getCookie(name) {
	var nameEQ = name + "=";
	var ca = document.cookie.split(';');
	for (var i = 0; i < ca.length; i++) {
		var c = ca[i];
		while (c.charAt(0) == ' ') c = c.substring(1, c.length);
			if (c.indexOf(nameEQ) == 0) return c.substring(nameEQ.length, c.length);
	}
	window.location.href = "login.html";
}

// format date to the correct format for the input field 
function formatDate(date) {
	var d = new Date(date),
    month = '' + (d.getMonth() + 1),
    day = '' + d.getDate(),
    year = d.getFullYear();
	
	houre = d.getHours();
	minutes = d.getMinutes();
	seconds = "00";

	if (month.length < 2) month = '0' + month;
	if (day.length < 2) day = '0' + day;

    return [year, month, day].join('-') + " " + [houre, minutes, seconds].join(':');
}

// function that makes api calles
function api(action, body, callback){
	$.ajax({
		type: 'POST',
		url: url + action,
		data: JSON.stringify(body),
		success: function(resp){
			callback(JSON.parse(resp));
			//TODO check if token mismach, revert to login
		},
		error: function(XMLHttpRequest, textStatus, errorThrown) { 
			window.location.href = "home.html";
		} 
	});
};

//start of filling the page with all the festivals
function autofill_festivals(){
	var coockie = JSON.parse(getCookie("YOUR_CV_INLOG_TOKEN_AND_ID"));
	api("get_festivals", {"id" : coockie.ID, "hash" : coockie.TOKEN, "select": "active"}, festival_processing);
}

// get the stastus from the id 
function id_to_status(id){
	if(id == 0){
		return "opvraging interesse";
	}
	else if (id == 1){
		return "Aangekondigd";
	}
	else if (id == 2){
		return "Open met vrije inschrijving";
	}
	else if (id == 3){
		return "open met reservatie";
	}
	else if (id == 4){
		return "festival bezig";
	}
	else if (id == 5){
		return "eindafrekeningen";
	}
	else if (id == 6){
		return "afgesloten";
	}
	else if (id == 7){
		return "geannuleerd";
	}
	else {
		return "unknown";
	}
}

// get the stastus from the id 
function id_to_status(shift_id, id, is_already_subscribed, is_full, is_completely_full, is_empty_days, has_external_locations, is_registered){
	var unemployment_but = "<input type='submit' id=unemployment"+ shift_id +" class='unemployment_to_festival' name='Werkloos' value='Werkloosheidsattest' placeholder='' style='background-color: red ;  margin-left:10px;'>";
	var external_locations = "";
	if(has_external_locations){
		external_locations = "<input type='submit' id=external"+ shift_id +" class='subscribe_to_location' name='ingeschrijven' value='Opvang moment selecteren' placeholder='' style='background-color: green ;  margin-left:10px;'>";
	}
	if(is_empty_days){
		return unemployment_but + "<input type='submit' id=shift_button_unsub"+ shift_id +" class='blocked' name='afgesloten' value='Niet Actief' placeholder='' style='background-color: gray ;  margin-left:10px;'>";
	}
	if(id == 0){
		if(is_already_subscribed){
			return external_locations + unemployment_but + "<input type='submit' id=shift_button_unsub"+ shift_id +" class='sibscribe_to_festival' name='registeren' value='Uitschrijven' placeholder='' style='background-color: red ;  margin-left:10px;'>";

		}
		else {
			return unemployment_but + "<input type='submit' id=shift_button"+ shift_id +" class='sibscribe_to_festival' name='geïnteresseerd' value='Geïnteresseerd' placeholder='' style='background-color: green ;  margin-left:10px;'>";

		}
	}
	else if (id == 1){
		if(is_already_subscribed){
			if(is_registered){
				return  unemployment_but + "<input type='submit' id=shift_button"+ shift_id +" class='blocked' name='gesloten' value='geregistreerd' placeholder='' style='background-color: gray ;  margin-left:10px;'>";

			}
			else {
				return  external_locations + unemployment_but + "<input type='submit' id=shift_button"+ shift_id +" class='blocked' name='gesloten' value='Ingeschreven(uitschrijven niet mogelijk)' placeholder='' style='background-color: green ;  margin-left:10px;'>";
			}
		}
		else {
			return unemployment_but + "<input type='submit' id=shift_button"+ shift_id +" class='blocked' name='gesloten' value='Inschrijven niet mogelijk' placeholder='' style='background-color: gray ;  margin-left:10px;'>";

		}
	}
	else if (id == 2){
		if(is_already_subscribed){
			if(is_registered){
				return unemployment_but + "<input type='submit' id=shift_button_unsub"+ shift_id +" class='sibscribe_to_festival' name='registeren' value='Registratie annuleren' placeholder='' style='background-color: red ;  margin-left:10px;'>";

			}
			else {
				return external_locations +unemployment_but + "<input type='submit' id=shift_button_unsub"+ shift_id +" class='sibscribe_to_festival' name='registeren' value='Uitschrijven' placeholder='' style='background-color: red ;  margin-left:10px;'>";

			}
		}
		else if (is_completely_full){
			return unemployment_but + "<input type='submit' id=shift_button_unsub"+ shift_id +" class='sibscribe_to_festival' name='registeren' value='Volzet' placeholder='' style='background-color: red ;  margin-left:10px;'>";
		}
		else {
			return unemployment_but + "<input type='submit' id=shift_button"+ shift_id +" class='sibscribe_to_festival' name='registeren' value='Registeren' placeholder='' style='background-color: green ;  margin-left:10px;'>";

		}
		
	}
	else if (id == 3){
		if(is_already_subscribed){
			if(is_registered){
				return unemployment_but + "<input type='submit' id=shift_button_unsub"+ shift_id +" class='de_sibscribe_to_festival' name='Uitschrijven' value='Registratie annuleren' placeholder='' style='background-color: red ;  margin-left:10px;'>";

			}
			else {
				return external_locations + unemployment_but + "<input type='submit' id=shift_button_unsub"+ shift_id +" class='de_sibscribe_to_festival' name='Uitschrijven' value='Uitschrijven' placeholder='' style='background-color: red ;  margin-left:10px;'>";

			}

		}
		else if (is_completely_full){
			return unemployment_but + "<input type='submit' id=shift_button"+ shift_id +" class='de_sibscribe_to_festival' name='Uitschrijven' value='volzet' placeholder='' style='background-color: red ;  margin-left:10px;'>";

		}
		else if (is_full){
			return unemployment_but + "<input type='submit' id=shift_button"+ shift_id +" class='de_sibscribe_to_festival' name='Uitschrijven' value='volzet(inschrijven op reservelijst)' placeholder='' style='background-color: orange ;  margin-left:10px;'>";

		}

		else {
			 return unemployment_but + "<input type='submit' id=shift_button"+ shift_id +" class='sibscribe_to_festival' name='inschrijven' value='Inschrijven' placeholder='' style='background-color: green ;  margin-left:10px;'>";

		}
	}

	else if (id == 4){
		if (is_already_subscribed && !is_registered){
			return unemployment_but + "<input type='submit' id=shift_button"+ shift_id +" class='blocked' name='festival bezig' value='Ingeschreven' placeholder='' style='background-color: green ;  margin-left:10px;'>";

		}
		else {
			return unemployment_but + "<input type='submit' id=shift_button"+ shift_id +" class='blocked' name='festival bezig' value='inschrijvingen afgesloten' placeholder='' style='background-color: gray ;  margin-left:10px;'>";

		}
	}
	else if (id == 5){
		if (is_already_subscribed && !is_registered){
			return unemployment_but + "<input type='submit' id=shift_button"+ shift_id +" class='blocked' name='change festival' value='eindafrekeningen' placeholder='' style='background-color: gray ;  margin-left:10px;'>";

		}
		else {
			return unemployment_but + "<input type='submit' id=shift_button"+ shift_id +" class='blocked' name='change festival' value='Evenement afgelopen' placeholder='' style='background-color: gray ;  margin-left:10px;'>";

		}
	}
	else if (id == 6){
		return "<input type='submit' id=shift_button"+ shift_id +" class='blocked' name='afgesloten' value='Afgeloten' placeholder='' style='background-color: gray ;  margin-left:10px;'>";
	}
	else if (id == 7){
		return "<input type='submit' id=shift_button"+ shift_id +" class='blocked' name='geanuleerd' value='geanuleerd' placeholder='' style='background-color: gray ;  margin-left:10px;'>";
	}
	else {
		return "<input type='submit' id=shift_button"+ shift_id +" class='blocked' name='error' value='error' placeholder='' style='background-color: gray ;  margin-left:10px;'>";
	}
}

function festival_shift_processing(data){
	if (data.error_type == 4){
		window.location.href = "login.html";
	}
	$("#festival_list").html("");
	var coockie = JSON.parse(getCookie("YOUR_CV_INLOG_TOKEN_AND_ID"));
	api("get_shifts",{"id" : coockie.ID, "hash" : coockie.TOKEN}, shift_processing);
	if (data.length == undefined || data.length == 0){
		$("#festival_list").append("<div id=0 class='festi' ><p style='text-align:center'>Geen festivals actief, kom op een later moment terug!</p></div>");
	}
	for (let x = 0; x < data.length; x++){
		$("#festival_list").append("<div id=" + data[x].idfestival +" class='festi' ><div style='width:20%' class='festi_date'><h2>"+ data[x].name + "</h2></div style='width:10%'><p style='width:60%'>"+ data[x].details +"</p></div>");
		$('#' + data[x].idfestival + " select").val(data[x].status);
	}
	$("#festival_list").fadeIn("fast");
}

function load_locations(shift_id){
	var coockie = JSON.parse(getCookie("YOUR_CV_INLOG_TOKEN_AND_ID"));
	api("get_locations_by_shift",{"id" : coockie.ID, "hash" : coockie.TOKEN, "shift_id": shift_id}, function(locations){
		api("subscribe_external_location_user",{"id" : coockie.ID, "hash" : coockie.TOKEN}, function(location_subscribed){
			for(let y = 0; y < location_subscribed.length; y++){
				$(".radio_external_option[id="+ location_subscribed[y].location_id +"]").attr('checked', true);
			}
		});
		$("#external_location_div").html("");
		for(let x = 0; x < locations.length; x++){
			$("#external_location_div").append("<p><input class=radio_external_option type=radio id="+ locations[x].location_id + " name=location user="+ coockie.ID +" value="+ locations[x].location +"> "+ locations[x].appointment_time +" " + locations[x].location +"</p>");
		}	

	});
}

//callback adding a shift 
function shift_processing(data){
	var coockie = JSON.parse(getCookie("YOUR_CV_INLOG_TOKEN_AND_ID"));
	api("shift_work_days", {"id" : coockie.ID, "hash" : coockie.TOKEN}, function(subscriptions){
		if (subscriptions.error_type == 8){
			alert("Je kan jezelf niet inschrijven zonder profielfoto, voeg er een toe aub");
			window.location.href = "user_input.html";
		}
		// add days
		var coockie = JSON.parse(getCookie("YOUR_CV_INLOG_TOKEN_AND_ID"));
		api("get_shift_days", {"id" : coockie.ID, "hash" : coockie.TOKEN}, load_shift_days_shifts);
	
		for (let x = 0; x < data.length; x++){
		// calculate if full or not 
			let is_full = (parseInt(data[x].people_needed) <= parseInt(data[x].subscribed_final));
			let is_completely_full = ((parseInt(data[x].people_needed) + parseInt(data[x].spare_needed)) <= parseInt(data[x].subscribed));
			let is_subscrubed = false;
			let is_registered = false;
			let is_empty_days = data[x].work_days == 0;
			let has_external_locations = data[x].external_meeting_locations > 0;
			for (let y = 0; y < subscriptions.length; y++){
				if (subscriptions[y].idshifts == data[x].idshifts){
					is_subscrubed = true;
					is_registered = subscriptions[y].reservation_type == 2;
					break;
				}
			}
			$("#" + data[x].festival_idfestival).append("<div id=shift" + data[x].idshifts +" class='shift_line' ><div class='shift_title'><div style='width:20%' class='festi_date'><h2>"+ data[x].name + "</h2></div><p style='width:10%'>Dagen: "+ data[x].length +"</p><p style='width:60%'>"+ data[x].datails +"</p>"+ id_to_status(data[x].idshifts, data[x].status, is_subscrubed, is_full, is_completely_full, is_empty_days, has_external_locations, is_registered) +"</div></div>");
			$("#shift_button" + data[x].idshifts).off();
			$("#shift_button" + data[x].idshifts).click(function(event){
				if($(this).hasClass("blocked")){return}
				if(unemployment){
					if (!confirm("Aandacht! Omdat je werkloos bent, moet je voor elk evenement een toelating krijgen van de VDAB. U vindt dit document per dienst op deze pagina. Schrijf u dus alleen in als u dit document heeft ingevuld en bij de juiste instantie heeft ingeleverd. Anuleer als u uw inschrijving niet wilt vervoledigen! Klik OK als u de inschrijving wilt voltooien.")){
						
					}
					window.open("https://www.vdab.be/magezine/06-2017/vrijwilligerswerk");
				}		

				var open_id = event.target.attributes.id.value;
				var coockie = JSON.parse(getCookie("YOUR_CV_INLOG_TOKEN_AND_ID"));
				api("user_subscribe",{"id" : coockie.ID, "hash" : coockie.TOKEN, "Id_Users": coockie.ID, "idshifts": open_id.replace(/\D/g,'')}, load_festivals_shifts);
			});

			$("#unemployment" + data[x].idshifts).off(); // here
			$("#unemployment" + data[x].idshifts).click(function(event){
				var open_id = event.target.attributes.id.value;
				var coockie = JSON.parse(getCookie("YOUR_CV_INLOG_TOKEN_AND_ID"));
				window.open(url + "pdf_unemployment&ID=" + coockie.ID + "&HASH=" + coockie.TOKEN + "&shift=" + open_id.replace(/\D/g,''));
			});

			$("#shift_button_unsub" + data[x].idshifts).click(function(event){
				if($(this).hasClass("blocked")){return}
				var open_id = event.target.attributes.id.value;
				var coockie = JSON.parse(getCookie("YOUR_CV_INLOG_TOKEN_AND_ID"));
				api("user_unsubscribe",{"id" : coockie.ID, "hash" : coockie.TOKEN, "Id_Users": coockie.ID, "idshifts": open_id.replace(/\D/g,'')}, load_festivals_shifts);
			});
			$(".subscribe_to_location").off();
			$(".subscribe_to_location").click(function(event){
				let id = event.target.attributes.id.value;
        		let selected_shift = id.replace(/[a-z]/gi, '');
				load_locations(selected_shift);
				$("#add_external_location_div").fadeIn(500);
				$("#abort_external_location").off();
				$("#abort_external_location").click(function(event){
					$("#add_external_location_div").fadeOut(500);
				});
				$("#validate_external_location").off();
				$("#validate_external_location").click(function(event){
					if($(".radio_external_option:checked").length > 0){
						let id = $(".radio_external_option:checked").attr("id");
						var coockie = JSON.parse(getCookie("YOUR_CV_INLOG_TOKEN_AND_ID"));
						api("subscribe_external_location",{"id" : coockie.ID, "hash" : coockie.TOKEN,"location_id": id.replace(/\D/g,'')}, function(){});
						$("#add_external_location_div").fadeOut(500);
						return;
					}
					alert("Je hebt geen opvang keuze geselecteerd.");
					
				});
				
			});
		}
	})

}


function load_shift_days_shifts(data) {

	for(let x=0; x < data.length; x++){
		let counter = $('.shift_day_line',"#shift"+ data[x].idshifts).length + 1;
		$("#shift"+ data[x].idshifts).append("<div id='shift_day"+data[x].idshifts+"' class='shift_day_line'><p class='shift_day_title' style='width:10%'>Dag "+ counter +"<p><p style='width:20%'>Start: "+ data[x].start_date +"<p><p style='width:20%'>Einde: "+ data[x].shift_end +"<p><p style='width:20%'>Dagvergoeding: €"+ data[x].cost + "</p></div>");
	}
}




