var url = "../../api.php?action=";



$(document).ready(function() {
	
	var coockie = JSON.parse(getCookie("YOUR_CV_INLOG_TOKEN_AND_ID"));
	api("get_news", {"id" : coockie.ID, "hash" : coockie.TOKEN}, festival_processing);
	add_optional_management();
});

// callback for the get_festivals
function festival_processing(data){
	$( "#add_festival_start" ).off();
	$("#add_fesitvail").fadeOut("slow");
	$("#festival_list").html("");
	$("festivals_li").css({"textDecoration":"underline"});
	if (data.length < 1 || data.length == undefined){
		//no active festivals
		$("#festival_list").append("<div id='No_festival' class='festi3' ><span>Op dit moment zijn er geen evenementen gepland, hou deze pagina en je mailbox goed in de gaten!</span></div>");
	}
	for (let x = 0; x < data.length; x++){
		$("#festival_list").append("<div id=" + data[x].id +" class='festi2' ><div style='width:20%' class='festi_date'><p><strong>"+ data[x].data + "</strong></p></div style='width:20%'><p>"+ data[x].notification +"</p></p></div>");
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
	return '{"ID":"0","TOKEN":"0"}';
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

function add_optional_management(){
	var coockie = JSON.parse(getCookie("YOUR_CV_INLOG_TOKEN_AND_ID"));
	api("is_admin",{"id" : coockie.ID, "hash" : coockie.TOKEN}, add_optional_management_callback)
}
function add_optional_management_callback(is_admin){
	if(is_admin.status == 200){
		$("#top_menu ul").append("<li><a href='admin.html'>Beheer</a></li>");
	}
}