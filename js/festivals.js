var url = "../../api.php?action=";



$(document).ready(function() {
	
	var coockie = JSON.parse(getCookie("YOUR_CV_INLOG_TOKEN_AND_ID"));
	api("get_festivals", {"id" : coockie.ID, "hash" : coockie.TOKEN, "select": "active", "festi_id":"invalid"}, festival_processing);
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

		$("#festival_list").append("<div id=" + data[x].idfestival +" class='festi2' ><div style='width:20%' class='festi_date'><p><strong>"+ data[x].name + "</strong></p></div style='width:20%'><p> Over "+ formatDate(data[x].date) + " dagen!</p><p style='width:40%'>"+ data[x].details +"</p><p style='width:20%'>Status: "+ id_to_status(data[x].status) +"</p></div>");
		$('#' + data[x].idfestival + " select").val(data[x].status);
		// change festival
		$(".change_festival").click(function(event){
			window.scrollTo(0, 0);
			$("#change_fesitvail_dialog").fadeIn();
			var coockie = JSON.parse(getCookie("YOUR_CV_INLOG_TOKEN_AND_ID"));
			api("get_festivals", {"id" : coockie.ID, "hash" : coockie.TOKEN, "select": "select", "festi_id": event.target.attributes.id.value}, put_change_date);
			$("#change_festival_abort").click(function(event){
				$("#change_fesitvail_dialog").fadeOut(500);
			});
		});
	}
}

// get the stastus from the id 
function id_to_status(id){
	if(id == 0){
		return "opvraging interesse";
	}
	else if (id == 1){
		return "Aangekondigd";
	}
	else if (id == 3){
		return "Inschrijven mogelijk.";
	}
	else if (id == 2){
		return "Registeren mogenlijk.";
	}
	else if (id == 4){
		return "Inschrijvingen afgesloten.";
	}
	else if (id == 5){
		return "Afgelopen. ";
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
function monthDiff(d1, d2) {
	var start = Math.floor(d1.getTime() / (3600 * 24 * 1000)); //days as integer from..
	var end = Math.floor(d2.getTime() / (3600 * 24 * 1000)); //days as integer from..
	return end - start; // exact dates
}

function formatDate(date) {
	var d = new Date(date);
	var n = new Date();
	return monthDiff(n, d);

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

