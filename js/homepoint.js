/*
 * your website
 *
 * @author : Bram Verachten
 * @date : 15/05/2018
 * 
 */
;(function() { 
	var url = "../../api.php?action="
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
		api("home_page",{}, autofill_callback)
	}
	function autofill_callback(res) {
		$("#main_wrapper").html("");
		for(var i = 0; i < res.length; i++) {
			if (res[i].Gender == "0"){gender_name = "man";}
			else if (res[i].Gender == "1"){gender_name = "vrouw";}
			else {gender_name = "anders";}
			$("#main_wrapper").prepend('<div id="person_box" PersonID="32bit"><h2>'+ res[i].name +'</h2><img src=/'+ res[i].picture_name +' alt="Smiley face" height="42" width="42"><h3>leeftijd</h3><p>22</p><h3>taal</h3><p>nederlands, engels</p><h3>land</h3><p>'+ res[i].nationality +'</p><h3>opleiding</h3><p>electronica-ict</p><h3>geslacht</h3><p>'+ gender_name +'</p><h3>quote</h3><p>it is never to late to do the right thing</p><h3>info</h3><p>'+res[i].text+'</p></div>');
		}
		
	}	
	'use strict';
	// wait till DOM is loaded

	$( document ).ready(function() {
		autofill();
	});
})();