	
// global variables that are needed to use the api 
var USER_ID = "";
var TOKEN = "";
var LOGGED_IN = false;
var url = "../../api.php?action="
	
	
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
			api("add_festival",{"id" : coockie.ID, "hash" : coockie.TOKEN, name: festiname, festival_discription: festival_discription, status: status, date: input_date },added_festival);
			
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

// a festival is added, update the page to add the evenement
function added_festival(data){
	$("#add_fesitvail").fadeOut( "slow" );
	console.log(data);
}

// callback for the get_festivals
function festival_processing(data){
	
}






