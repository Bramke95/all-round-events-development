	
// global variables that are needed to use the api 
var USER_ID = "";
var TOKEN = "";
var LOGGED_IN = false;
var url = "../../api.php?action="
const select_type = '<select style="width:20%" class="festi_status" name="status"><option value="0">opvraging interesse</option><option value="1">Aangekondigd</option><option value="2">Open met vrije inschrijving</option><option value="3">open met reservatie</option><option value="4">festival bezig</option><option value="5">eindafrekeningen</option><option value="6">afgesloten</option><option value="7">geannuleerd</option></select>';
	
	
$( document ).ready(function() {
	check_if_admin(autofill_festivals);
	
	// add event listner to the add festival button
	$("#add_festit_init").click(function(){
		$("#add_fesitvail").show();
		$("#add_fesitvail").draggable();
		$("#add_festival_abort").click(function(){
			$("#add_fesitvail").fadeOut( "slow" );
		});
		$("#add_festival_start").click(function(){
			
			let festiname = $("#festi_name").val();
			let festival_discription = $("#festi_discription").val();
			let status = $("#festi_status").val();
			let date = $("#festi_date").val();
			var date_object = new Date(date);
			var input_date = formatDate(date_object)
			var coockie = JSON.parse(getCookie("YOUR_CV_INLOG_TOKEN_AND_ID"));
			api("add_festival",{"id" : coockie.ID, "hash" : coockie.TOKEN, name: festiname, festival_discription: festival_discription, status: status, date: input_date }, festival_processing);
			
		});
	});
});

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
			//window.location.href = "home.html";
		} 
	});
};

//start of filling the page with all the festivals
function autofill_festivals(){
	var coockie = JSON.parse(getCookie("YOUR_CV_INLOG_TOKEN_AND_ID"));
	api("get_festivals", {"id" : coockie.ID, "hash" : coockie.TOKEN, "all": 0}, festival_processing);
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


// callback for the get_festivals
function festival_processing(data){
	$( "#add_festival_start" ).off();
	$("#add_fesitvail").fadeOut("slow");
	$("#festival_list").html("");
	$("festivals_li").css({"textDecoration":"underline"});
	for (let x = 0; x < data.length; x++){
		$("#festival_list").append("<div id=" + data[x].idfestival +" class='festi' ><div style='width:20%' class='festi_date'><h2>"+ data[x].name + "</h2></div style='width:10%'><p>"+ data[x].date +"</p><p style='width:60%'>"+ data[x].details +"</p>"+ select_type + "</div>");
		$('#' + data[x].idfestival + " select").val(data[x].status);
	}
}

