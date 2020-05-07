var url = "../../api.php?action=";

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

$( document ).ready(function() {
	$("#login_but").click(function(){
		api("mail", {}, function(){});
	});
	
});

