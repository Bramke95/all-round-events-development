// global variables that are needed to use the api 
var USER_ID = "";
var TOKEN = "";
var LOGGED_IN = false;
var open_id = -1;
var url = "../../api.php?action=";

		
$( document ).ready(function() {
	load_festivals_shifts();
});

function load_festivals_shifts(){
	var coockie = JSON.parse(getCookie("YOUR_CV_INLOG_TOKEN_AND_ID"));
	api("get_festivals", {"id" : coockie.ID, "hash" : coockie.TOKEN, "select": "active"}, festival_shift_processing);
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
	return null;
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
		},
		error: function(XMLHttpRequest, textStatus, errorThrown) { 
			//window.location.href = "home.html";
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
function id_to_status(shift_id, id, is_already_subscribed, is_full, reserve){
	if(id == 0){
		return "<input type='submit' id=shift_button"+ shift_id +" class='sibscribe_to_festival' name='geïnteresseerd' value='Geïnteresseerd' placeholder='' style='background-color: green ;  margin-left:10px;'>";
	}
	else if (id == 1){
		return "<input type='submit' id=shift_button"+ shift_id +" class='blocked' name='gesloten' value='Gesloten' placeholder='' style='background-color: red ;  margin-left:10px;'>";
	}
	else if (id == 2){
		return "<input type='submit' id=shift_button"+ shift_id +" class='sibscribe_to_festival' name='inschrijven' value='Inschrijven' placeholder='' style='background-color: green ;  margin-left:10px;'>";
	}
	else if (id == 3){
		return "<input type='submit' id=shift_button"+ shift_id +" class='sibscribe_to_festival' name='registeren' value='Registeren' placeholder='' style='background-color: green ;  margin-left:10px;'>";
	}
	else if (id == 4){
		return "<input type='submit' id=shift_button"+ shift_id +" class='blocked' name='festival bezig' value='Afgesloten' placeholder='' style='background-color: gray ;  margin-left:10px;'>";
	}
	else if (id == 5){
		return "<input type='submit' id=shift_button"+ shift_id +" class='blocked' name='change festival' value='Evenement afgelopen' placeholder='' style='background-color: gray ;  margin-left:10px;'>";
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
	var coockie = JSON.parse(getCookie("YOUR_CV_INLOG_TOKEN_AND_ID"));
	api("get_shifts",{"id" : coockie.ID, "hash" : coockie.TOKEN}, shift_processing);
	for (let x = 0; x < data.length; x++){
		$("#festival_list").append("<div id=" + data[x].idfestival +" class='festi' ><div style='width:20%' class='festi_date'><h2>"+ data[x].name + "</h2></div style='width:10%'><p>"+ data[x].date +"</p><p style='width:60%'>"+ data[x].details +"</p></div>");
		$('#' + data[x].idfestival + " select").val(data[x].status);
	}
	$("#festival_list").fadeIn("fast");
}

//callback adding a shift 
function shift_processing(data){
	var coockie = JSON.parse(getCookie("YOUR_CV_INLOG_TOKEN_AND_ID"));
	api("shift_work_days", {"id" : coockie.ID, "hash" : coockie.TOKEN}, function(subscriptions){
		// add days
		var coockie = JSON.parse(getCookie("YOUR_CV_INLOG_TOKEN_AND_ID"));
		api("get_shift_days", {"id" : coockie.ID, "hash" : coockie.TOKEN}, load_shift_days_shifts);
		
		for (let x = 0; x < data.length; x++){
			$("#" + data[x].festival_idfestival).append("<div id=shift" + data[x].idshifts +" class='shift_line' ><div class='shift_title'><div style='width:20%' class='festi_date'><h2>"+ data[x].name + "</h2></div><p style='width:10%'>Dagen: "+ data[x].length +"</p><p style='width:60%'>"+ data[x].datails +"</p>"+ id_to_status(data[x].idshifts, data[x].status, true, true) +"</div></div>");
			$("#shift_button" + data[x].idshifts).click(function(event){
				open_id = event.target.attributes.id.value;
				var coockie = JSON.parse(getCookie("YOUR_CV_INLOG_TOKEN_AND_ID"));
				api("user_subscribe",{"id" : coockie.ID, "hash" : coockie.TOKEN, "Id_Users": coockie.ID, "idshifts": open_id.replace(/\D/g,'')} );
			});
		}
	})

}


function load_shift_days_shifts(data) {

	for(let x=0; x < data.length; x++){
		$("#shift"+ data[x].idshifts).append("<div id='shift_day"+data[x].idshifts+"' class='shift_day_line'><p class='shift_day_title' style='width:10%'>Dag "+ ($("#shift_day" + data[x].idshifts).length +1) +"<p><p style='width:20%'>Start: "+ data[x].start_date +"<p><p style='width:20%'>Einde: "+ data[x].shift_end +"<p><p style='width:20%'>Dagvergoeding: "+ data[x].cost + "</p></div>");
	}
}




