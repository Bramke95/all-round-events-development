// global variables that are needed to use the api 
var USER_ID = "";
var TOKEN = "";
var LOGGED_IN = false;
var open_id = -1;
var url = "../../api.php?action=";
const select_type = '<select style="width:20%" class="festi_status" name="status"><option value="0">opvraging interesse</option><option value="1">Aangekondigd</option><option value="2">Open met vrije inschrijving</option><option value="3">open met reservatie</option><option value="4">festival bezig</option><option value="5">eindafrekeningen</option><option value="6">afgesloten</option><option value="7">geannuleerd</option></select>';
const change_button = "<input type='submit' id='change_festival' name='change festival' value='wijzingen' placeholder='' style='background-color: orange ;  margin-left:10px;'>";
		
$( document ).ready(function() {
	check_if_admin(autofill_festivals);
	
	// add event listner to the add festival button
	$("#add_festit_init").click(function(){
		$("#add_fesitvail").fadeIn(500);
		window.scrollTo(0, 0);
		$("#add_fesitvail").draggable();
		$("#add_festival_abort").click(function(){
			$("#add_fesitvail").fadeOut( "slow" );
		});
		$("#add_festival_start").click(function(event){
			let festiname = $("#festi_name").val();
			let festival_discription = $("#festi_discription").val();
			let status = $("#festi_status").val();
			let date = $("#festi_date").val();
			var date_object = new Date(date);
			var input_date = formatDate(date_object);
			var coockie = JSON.parse(getCookie("YOUR_CV_INLOG_TOKEN_AND_ID"));
			api("add_festival",{"id" : coockie.ID, "hash" : coockie.TOKEN, name: festiname, festival_discription: festival_discription, status: status, date: input_date}, festival_processing);

			
		});
	});
		$("#festivals_li").click(function(event){
			clearAll()
			$("#festival_list").fadeIn("fast");
			$("#add_festit_init").fadeIn("fast");
			autofill_festivals();
			
		});
		
		$("#shifts_li").click(function(event){
			clearAll()
			$("#add_shift_init").fadeIn("fast");
			load_festivals_shifts();
			
			
		});
		
		$("#users_li").click(function(event){
			clearAll()
		});
		
		$("#payouts_li").click(function(event){
			clearAll()
		});
		$("#subscription_li").click(function(event){
			clearAll()
			var coockie = JSON.parse(getCookie("YOUR_CV_INLOG_TOKEN_AND_ID"));
			api("get_festivals", {"id" : coockie.ID, "hash" : coockie.TOKEN, "select": "active"}, festival_shift_processing_ligth);
		});
});

function load_festivals_shifts(){
	var coockie = JSON.parse(getCookie("YOUR_CV_INLOG_TOKEN_AND_ID"));
	api("get_festivals", {"id" : coockie.ID, "hash" : coockie.TOKEN, "select": "active"}, festival_shift_processing);
}

// function that starts the page but only when it this is the admin 
function check_if_admin(callback){
		var coockie = JSON.parse(getCookie("YOUR_CV_INLOG_TOKEN_AND_ID"));
		if (!coockie){
			window.location.href = "login.html";
		}
	api("is_admin",{"id" : coockie.ID, "hash" : coockie.TOKEN}, function(admin_data){
		if(admin_data.status == 200){
			callback();
		}
		else{
			window.location.href = "home.html";
		}
		
	})
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
	window.location.href = "home.html";
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

// callback for getting the festi date for 1 festival
function put_change_date(data){
	$("#festi_name_change").val(data[0].name);
	$("#festi_discription_change").html(data[0].details);
	$("#festi_date_change").val(data[0].date.substring(0,10));
	$("#change_festival_start").off();
	$("#change_festival_start").click(function(event){
		let festiname = $("#festi_name_change").val();
		let festival_discription = $("#festi_discription_change").val();
		let date = $("#festi_date_change").val();
		var date_object = new Date(date);
		var input_date = formatDate(date_object)
		var coockie = JSON.parse(getCookie("YOUR_CV_INLOG_TOKEN_AND_ID"));
		api("change_festival_data", {"id" : coockie.ID, "hash" : coockie.TOKEN,festiname:festiname, festival_discription: festival_discription, date: input_date, idfestival: parseInt(open_id)}, autofill_festivals);
		$("#change_fesitvail_dialog").fadeOut(500);
		
	});
}

// callback for changed event
function changed_festival(){
	var coockie = JSON.parse(getCookie("YOUR_CV_INLOG_TOKEN_AND_ID"));
	api("get_festivals", {"id" : coockie.ID, "hash" : coockie.TOKEN, "select": "active"}, festival_processing);
	$("#change_fesitvail_dialog").fadeOut(500);
}

function festival_shift_processing(data){
	var coockie = JSON.parse(getCookie("YOUR_CV_INLOG_TOKEN_AND_ID"));
	api("get_shifts",{"id" : coockie.ID, "hash" : coockie.TOKEN}, shift_processing);
	$("#add_fesitvail").fadeOut("slow");
	$("#festival_list").html("");
	$("festivals_li").css({"textDecoration":"underline"});
	for (let x = 0; x < data.length; x++){
		$("#festival_list").append("<div id=" + data[x].idfestival +" class='festi' ><div style='width:20%' class='festi_date'><h2>"+ data[x].name + "</h2></div style='width:10%'><p>"+ data[x].date +"</p><p style='width:60%'>"+ data[x].details +"</p>" +  "<input type='submit' id="+ data[x].idfestival +" class='change_shift2' name='change festival' value='shift toevoegen' placeholder='' style='background-color: rgb(76, 175, 80);  margin-left:10px;'></div>");
		$('#' + data[x].idfestival + " select").val(data[x].status);
		// change festival
		$(".change_shift2").click(function(event){
			open_id = event.target.attributes.id.value;
			// delete all fields
			$("#shift_name").val("");
			$("#shift_details").val("");
			$("#people_needed").val("");
			$("#people_needed_reserved").val("");
			$("#days").val("");
			$("#add_shift").fadeIn(500);
			window.scrollTo(0, 0);
			$("#add_shift_abort").click(function(event){
				$("#add_shift").fadeOut(500);
			});
			$("#add_shift_start").off();
			$("#add_shift_start").click(function(event){
				let shiftname = $("#shift_name").val();
				let shift_discription = $("#shift_details").val();
				let people_needed = $("#people_needed").val();
				let reserved = $("#people_needed_reserved").val();
				let days = $("#days").val();
				var coockie = JSON.parse(getCookie("YOUR_CV_INLOG_TOKEN_AND_ID"));
				api("add_shift",{"id" : coockie.ID, "hash" : coockie.TOKEN, name: shiftname, discription: shift_discription, needed: people_needed, reserve: reserved, length: days, festi_id: open_id}, load_festivals_shifts);
			});
		});
	}
	$("#festival_list").fadeIn("fast");
}

function festival_shift_processing_ligth(data){
	var coockie = JSON.parse(getCookie("YOUR_CV_INLOG_TOKEN_AND_ID"));
	api("get_shifts",{"id" : coockie.ID, "hash" : coockie.TOKEN}, shift_processing_short);
	$("#add_fesitvail").fadeOut("slow");
	$("#festival_list").html("");
	for (let x = 0; x < data.length; x++){
		$("#festival_list").append("<div id=" + data[x].idfestival +" class='festi' ><div style='width:20%' class='festi_date'><h2>"+ data[x].name + "</h2></div style='width:10%'><p>"+ data[x].date +"</p><p style='width:60%'>"+ data[x].details +"</p></div>");
		$('#' + data[x].idfestival + " select").val(data[x].status);
		// change festival
	}
	$("#festival_list").fadeIn("fast");
}
//callback adding a shift 
function shift_processing(data){
	// add days
	var coockie = JSON.parse(getCookie("YOUR_CV_INLOG_TOKEN_AND_ID"));
	api("get_shift_days", {"id" : coockie.ID, "hash" : coockie.TOKEN}, load_shift_days_shifts);
	
	$("#add_shift").hide();
	for (let x = 0; x < data.length; x++){
		$("#" + data[x].festival_idfestival).append("<div id=shift" + data[x].idshifts +" class='shift_line' ><div class='shift_title'><div style='width:20%' class='festi_date'><h2>"+ data[x].name + "</h2></div><p style='width:10%'>Dagen: "+ data[x].length +"</p><p style='width:60%'>"+ data[x].datails +"</p>"+ "<p style='width:10%'>Bezetting: "+ data[x].people_needed +"</p>" + "<p style='width:10%'>Reserve: "+ data[x].spare_needed +"</p>" + "<input type='submit' id="+ data[x].idshifts +" class='add_day_shift' name='change festival' value='dag toevoegen' placeholder='' style='background-color: rgb(76, 175, 80) ;  margin-left:10px;'>" + "<input type='submit' id="+ data[x].idshifts +" class='change_shift' name='delete festival' value='Wijzigen' placeholder='' style='background-color: red ;  margin-left:10px;'>" + "<input type='submit' id=" + data[x].idshifts + " class='delete_shift' name='delete festival' value='Verwijderen' placeholder='' style='background-color: red ;  margin-left:10px;'></div></div>");
		$(".change_shift").click(function(event){
			let id = event.target.attributes.id.value;
			var coockie = JSON.parse(getCookie("YOUR_CV_INLOG_TOKEN_AND_ID"));
			api("get_shift", {"id" : coockie.ID, "hash" : coockie.TOKEN, "idshifts": id}, fill_in_change_shift);
			$("#change_shift").fadeIn(500);
			window.scrollTo(0, 0);
		});
		$(".delete_shift").click(function(event){
			let id = event.target.attributes.id.value;
			var coockie = JSON.parse(getCookie("YOUR_CV_INLOG_TOKEN_AND_ID"));
			api("delete_shift", {"id" : coockie.ID, "hash" : coockie.TOKEN, "idshifts": id}, load_festivals_shifts);
		});
		$(".add_day_shift").click(function(event){
			$("#add_shift_day").fadeIn(500);
			window.scrollTo(0, 0);
			let id = event.target.attributes.id.value;
			$("#add_shift_day_abort").click(function(event){
				$("#add_shift_day").fadeOut(500);
			});
			$("#add_shift_day_start").off();
			$("#add_shift_day_start").click(function(event){
				// api add shift 
				
				let start = $("#shift_day_start").val();
				let start_object = new Date(start);
				let start_db = formatDate(start_object)
				
				let stop = $("#shift_day_stop").val();
				let stop_object = new Date(stop);
				let stop_db = formatDate(stop_object)
				
				let money = $("#compensation").val();
				var coockie = JSON.parse(getCookie("YOUR_CV_INLOG_TOKEN_AND_ID"));
				api("add_shift_day", {"id" : coockie.ID, "hash" : coockie.TOKEN, "idshifts": id, shifts_idshifts:id, start:start_db, stop:stop_db, money:money}, load_festivals_shifts);
				$("#add_shift_day").fadeOut(500);
				
				
			});
			
		});
	}
}

//callback adding a shift 
function shift_processing_short(data){
	// add days
	var coockie = JSON.parse(getCookie("YOUR_CV_INLOG_TOKEN_AND_ID"));
	api("get_subscribers", {"id" : coockie.ID, "hash" : coockie.TOKEN}, subscribers_callback);
	
	$("#add_shift").hide();
	//TODO Add following functionality: 
	// mail all: THe option to mail all the people in the shift
	// pdf creation 
	
	for (let x = 0; x < data.length; x++){
		$("#" + data[x].festival_idfestival).append("<div id=shift" + data[x].idshifts +" class='shift_line' ><div class='shift_title'><div style='width:15%' class='festi_date'><h2>"+ data[x].name + "</h2></div><p style='width:40%'>"+ data[x].datails +"</p>"+ "<p style='width:20%'>benodigde bezetting: "+ data[x].people_needed +"</p>" + "<p style='width:20%'>gewenste reserve: "+ data[x].spare_needed +"</p> " + "<p style='width:20%'>ingeschreven: "+ data[x].subscribed_final +"</p><p style='width:20%'>geregistreerd: "+ data[x].subscribed +"</p></div></div>");	
	}
}

function subscribers_callback(data){

		for(let x=0; x < data.length; x++){
		//TODO: implement functionality 
			let user_status = "unknown";

			if (data[x].reservation_type == 2){
				user_status = "Geregistreerd";
				$("#shift"+ data[x].shifts_idshifts).append("<div id='shift"+ data[x].shifts_idshifts + "' class='user_line'><div width='15%' id='img_user' ><img src=/" + data[x].picture_name + " width='auto' height='60px'></div><p style='width:20%'>naam: "+ data[x].name +"<p><p style='width:20%'>Status: "+ user_status +"<p><input type='submit' id="+ data[x].idshift_days +" class='change_shift_day' name='delete festival' value='weigeren' placeholder='' style='background-color: red ;  margin-left:10px;'>" + "<input type='submit' id=" + data[x].idshift_days + " class='delete_shift_day' name='delete festival' value='Inschrijven' placeholder='' style='background-color: green ;  margin-left:10px;'></div>");
			}
			if (data[x].reservation_type == 3){
				user_status = "Ingeschreven";
				primary_list = primary_list + 
				$("#shift"+ data[x].shifts_idshifts).append("<div id='shift"+ data[x].shifts_idshifts + "' class='user_line'><div width='15%' id='img_user' ><img src=/" + data[x].picture_name + " width='auto' height='60px'></div><p style='width:20%'>naam: "+ data[x].name +"<p><p style='width:20%'>Status: "+ user_status +"<p><input type='submit' id="+ data[x].idshift_days +" class='change_shift_day' name='delete festival' value='Uitschrijven' placeholder='' style='background-color: red ;  margin-left:10px;'></div>");
			}
			if (data[x].reservation_type == 99){
				user_status = "reservelijst";
				$("#shift"+ data[x].shifts_idshifts).append("<div id='shift"+ data[x].shifts_idshifts + "' class='user_line'><div width='15%' id='img_user' ><img src=/" + data[x].picture_name + " width='auto' height='60px'></div><p style='width:20%'>naam: "+ data[x].name +"<p><p style='width:20%'>Status: "+ user_status +"<p><input type='submit' id="+ data[x].idshift_days +" class='change_shift_day' name='delete festival' value='Wijzigen' placeholder='' style='background-color: red ;  margin-left:10px;'>" + "<input type='submit' id=" + data[x].idshift_days + " class='delete_shift_day' name='delete festival' value='Verwijderen' placeholder='' style='background-color: red ;  margin-left:10px;'></div>");

			}
			

		}
}

//
//callback to fill in date in the change shift dialog
//
function fill_in_change_shift(data){
	
	$("#shift_name_change").val(data[0].name);
	$("#shift_details_change").val(data[0].datails);
	$("#people_needed_change").val(data[0].people_needed);
	$("#people_needed_reserved_change").val(data[0].spare_needed);
	$("#festi_days_change").val(data[0].length);
	$("#change_shift_abort").click(function(event){
		$("#change_shift").fadeOut(500);
	});
	$("#change_shift_start").off();
	$("#change_shift_start").click(function(event){
		//change data
		let name = $("#shift_name_change").val();
		let details = $("#shift_details_change").val();
		let people = $("#people_needed_change").val();
		let reserve = $("#people_needed_reserved_change").val();
		let days = $("#festi_days_change").val();
		var coockie = JSON.parse(getCookie("YOUR_CV_INLOG_TOKEN_AND_ID"));
		if (name== ""){
			alert("Geen naam opgegeven");
			return;
		}
		api("change_shift", {"id" : coockie.ID, "hash" : coockie.TOKEN, name:name, details:details, people:people, reserve:reserve, days:days, idshifts:data[0].idshifts}, load_festivals_shifts)
		$("#change_shift").fadeOut(500);
	});
}

// callback for the get_festivals
function festival_processing(data){
	$( "#add_festival_start" ).off();
	$("#add_fesitvail").fadeOut("slow");
	$("#festival_list").html("");
	$("festivals_li").css({"textDecoration":"underline"});
	for (let x = 0; x < data.length; x++){
		$("#festival_list").append("<div id=" + data[x].idfestival +" class='festi2' ><div style='width:20%' class='festi_date'><h2>"+ data[x].name + "</h2></div style='width:10%'><p>"+ data[x].date +"</p><p style='width:60%'>"+ data[x].details +"</p>"+ select_type +  "<input type='submit' id="+ data[x].idfestival +" class='change_festival' name='change festival' value='wijzingen' placeholder='' style='background-color: red ;  margin-left:10px;'></input></div>");
		$('#' + data[x].idfestival + " select").val(data[x].status);
		// change festival
		$(".change_festival").click(function(event){
			open_id = event.target.attributes.id.value;
			window.scrollTo(0, 0);
			$("#change_fesitvail_dialog").fadeIn();
			var coockie = JSON.parse(getCookie("YOUR_CV_INLOG_TOKEN_AND_ID"));
			api("get_festivals", {"id" : coockie.ID, "hash" : coockie.TOKEN, "select": "select", "festi_id": open_id}, put_change_date);
			$("#change_festival_abort").click(function(event){
				$("#change_fesitvail_dialog").fadeOut(500);
			});
		});
	}
}

function load_shift_days_shifts(data) {

	for(let x=0; x < data.length; x++){
		//TODO Counter should only count days with correct ID 
		$("#shift"+ data[x].idshifts).append("<div id='shift_day"+data[x].idshifts+"' class='shift_day_line'><p class='shift_day_title' style='width:10%'>Dag "+ ($("#shift_day" + data[x].idshifts).length +1) +"<p><p style='width:20%'>Start: "+ data[x].start_date +"<p><p style='width:20%'>Einde: "+ data[x].shift_end +"<p><p style='width:20%'>Dagvergoeding: "+ data[x].cost + "</p><input type='submit' id="+ data[x].idshift_days +" class='change_shift_day' name='delete festival' value='Wijzigen' placeholder='' style='background-color: red ;  margin-left:10px;'>" + "<input type='submit' id=" + data[x].idshift_days + " class='delete_shift_day' name='delete festival' value='Verwijderen' placeholder='' style='background-color: red ;  margin-left:10px;'></div>");
		
		$(".change_shift_day").click(function(event){
			var coockie = JSON.parse(getCookie("YOUR_CV_INLOG_TOKEN_AND_ID"));
			open_id = event.target.attributes.id.value;
			api("get_shift_day", {"id" : coockie.ID, "hash" : coockie.TOKEN,  "shift_day_id": open_id}, full_in_changed_shift_day)
			$("#change_shift_day").fadeIn(500);
			window.scrollTo(0, 0);
			
			//cancel
			$("#change_shift_day_abort").click(function(){
				$("#change_shift_day").fadeOut(500);
			});
			$("#change_shift_day_start").off();
			$("#change_shift_day_start").click(function(){
			// change
				let start = $("#shift_day_start_change").val();
				let start_object = new Date(start);
				let start_db = formatDate(start_object)
				
				let stop = $("#shift_day_stop_change").val();
				let stop_object = new Date(stop);
				let stop_db = formatDate(stop_object)
				
				let money = $("#compensation_change").val();
				var coockie = JSON.parse(getCookie("YOUR_CV_INLOG_TOKEN_AND_ID"));
				api("change_shift_day", {"id" : coockie.ID, "hash" : coockie.TOKEN, "shift_day_id": open_id,  start:start_db, stop:stop_db, money:money}, load_festivals_shifts);
				$("#change_shift_day").fadeOut(500);
			});
		});
		$(".delete_shift_day").off();
		$(".delete_shift_day").click(function(event){
			let id = event.target.attributes.id.value;
			var coockie = JSON.parse(getCookie("YOUR_CV_INLOG_TOKEN_AND_ID"));
			api("delete_shift_day", {"id" : coockie.ID, "hash" : coockie.TOKEN, "shift_day_id": id}, load_festivals_shifts);
			
		});
		
	}
}

function full_in_changed_shift_day(data){
	shift = data[0];
	// Todo set in textfield
	$("#shift_day_start_change").val(shift.start_date.replace(" ", "T"));
	$("#shift_day_stop_change").val(shift.shift_end.replace(" ", "T"));
	$("#compensation_change").val(shift.cost);
}

function clearAll(){
	$("#add_fesitvail").fadeOut("fast");
	$("#change_fesitvail_dialog").fadeOut("fast");
	$("#festival_list").fadeOut("fast");
	$("#add_festit_init").fadeOut("fast");
	$("#add_shift_init").fadeOut("fast");	
}




