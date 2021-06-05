// global variables that are needed to use the api 
var USER_ID = "";
var TOKEN = "";
var LOGGED_IN = false;
var open_id = -1;
var url = "../../api.php?action=";
const select_type = '<select style="width:20%" class="festi_status" name="status"><option value="0">opvraging interesse</option><option value="1">Aangekondigd</option><option value="2">open met reservatie</option><option value="3"> Open met vrije inschrijving</option><option value="4">festival bezig</option><option value="5">eindafrekeningen</option><option value="6">afgesloten</option><option value="7">geannuleerd</option></select>';
const change_button = "<input type='submit' id='change_festival' name='change festival' value='wijzingen' placeholder='' style='background-color: orange ;  margin-left:10px;'>";
var user_list = [];
var selected_shift = 0;
var selected_user = 0;
var selected_festival_presense = 0;
var selected_shift_presense = 0;
var festival_payout = -1;
selected_workday_presense = 0;
festi_days = 1;
var messenger_specific_festival = -1;

$(document).ready(function() {
    check_if_admin(autofill_festivals);

    // add event listner to the add festival button
    $("#add_festit_init").click(function() {
        $("#add_fesitvail").fadeIn(500);
        $("#add_fesitvail").draggable();
        $("#add_festival_abort").click(function() {
            $("#add_fesitvail").fadeOut("slow");
        });
        $("#add_festival_start").click(function(event) {
            let festiname = $("#festi_name").val();
            let festival_discription = $("#festi_discription").val();
            let status = $("#festi_status").val();
            let date = $("#festi_date").val();
            var date_object = new Date(date);
            var input_date = formatDate(date_object);
            var coockie = JSON.parse(getCookie("YOUR_CV_INLOG_TOKEN_AND_ID"));
            api("add_festival", {
                "id": coockie.ID,
                "hash": coockie.TOKEN,
                name: festiname,
                festival_discription: festival_discription,
                status: status,
                date: input_date
            }, festival_processing);


        });
    });
    $("#festivals_li").click(function(event) {
        clearAll();
        $("#festivals_li").css({
            "textDecoration": "underline"
        });
        $("#festival_list").fadeIn("fast");
        $("#add_festit_init").fadeIn("fast");
        autofill_festivals();

    });

    $("#shifts_li").click(function(event) {
        clearAll();
        $("#shifts_li").css({
            "textDecoration": "underline"
        });
        $("#add_shift_init").fadeIn("fast");
        load_festivals_shifts();


    });

    $("#users_li").click(function(event) {
        clearAll();
        $("#users_li").css({
            "textDecoration": "underline"
        });
		users_select_box();
    });

    $("#payouts_li").click(function(event) {
        clearAll();
        $("#payouts_li").css({
            "textDecoration": "underline"
        });
        payout_festival_list();
    });
    $("#subscription_li").click(function(event) {

        festival_shift_subscribers();

    });

    $("#present_li").click(function(event) {
        festival_checkbox_listing();
    });

    $("#messemgers_li").click(function(event) {
        clearAll();
        messenger_listing();
        $("#messenger").fadeIn("fast");
        $("#send_messenger").off();
        $("#send_messenger").click(function(event) {
            let festid_id = $("#festivals_mes").children(":selected").attr("id");
            let shift_id = $("#shift_mes").children(":selected").attr("id");

            let text = $("#text_text_messenger").val();
            let subject = $("#text_subject_messenger").val();
            if(shift_id != -1){
                 festid_id = -1;
            }

            var coockie = JSON.parse(getCookie("YOUR_CV_INLOG_TOKEN_AND_ID"));
            api("message", {
                "id": coockie.ID,
                "hash": coockie.TOKEN,
                "festi_id": festid_id,
                "shift_id": shift_id,
                "text": text,
                "subject": subject
            }, callback_messenger);
        });
    });
});

    var coockie = JSON.parse(getCookie("YOUR_CV_INLOG_TOKEN_AND_ID"));
    api("get_festivals", {
        "id": coockie.ID,
        "hash": coockie.TOKEN,
        "select": "active",
        "festi_id": "invalid"
    }, festival_checkbox);

function callback_messenger(data){
    alert("Bericht Verzonen")
}

function messenger_listing() {
    var coockie = JSON.parse(getCookie("YOUR_CV_INLOG_TOKEN_AND_ID"));
    api("get_festivals", {
        "id": coockie.ID,
        "hash": coockie.TOKEN,
        "select": "active",
        "festi_id": "invalid"
    }, festival_messenger_boxing);
        api("get_shifts", {
        "id": coockie.ID,
        "hash": coockie.TOKEN
    }, shift_messenger_boxing);
};

function festival_messenger_boxing(data){
    let festi_html = "<select id='festivals_mes'>";
    festi_html = festi_html + "<option class='select_festi_option_messenger' id='-1'>Geen</option>";
    for (let x = 0; x < data.length; x++) {
        festi_html = festi_html + "<option class='select_festi_option_messenger' id=" + data[x].idfestival + ">" + data[x].name + "</option>";
    }
    festi_html = festi_html + "</select><div id='shift_select_placeholder_messenger'></div>";
    $("#festival_messenger").html(festi_html);
    $("#festival_messenger").fadeIn("fast");
    $("#festivals_mes").off();
    $("#festivals_mes").change(function(event) {
        messenger_specific_festival = $("#festivals_mes").children(":selected").attr("id");
        api("get_shifts", {
        "id": coockie.ID,
        "hash": coockie.TOKEN
    }, shift_messenger_boxing_specific);
    });

};

function shift_messenger_boxing(data){
    let festi_html = "<select id='shift_mes'>";
    festi_html = festi_html + "<option class='select_festi_option' id='-1'>Alle shiften in festival</option>";
    for (let x = 0; x < data.length; x++) {
        festi_html = festi_html + "<option class='select_festi_option' id=" + data[x].idshifts + ">" + data[x].festiname + " "+  data[x].name + "</option>";
    }
    festi_html = festi_html + "</select><div id='shift_select_placeholder'></div>";
    $("#shift_messenger").html(festi_html);
    $("#shift_messenger").fadeIn("fast");
};

function shift_messenger_boxing_specific(data){
    let festi_html = "<select id='shift_mes'>";
    festi_html = festi_html + "<option class='select_festi_option' id='-1'>Alle shiften in festival</option>";
    for (let x = 0; x < data.length; x++) {
        if(data[x].festival_idfestival == messenger_specific_festival){
            festi_html = festi_html + "<option class='select_festi_option' id=" + data[x].idshifts + ">" + data[x].festiname + " "+  data[x].name + "</option>";
        }
    }
    festi_html = festi_html + "</select><div id='shift_select_placeholder'></div>";
    $("#shift_messenger").html(festi_html);
    $("#shift_messenger").fadeIn("fast");
};

function users_select_box(){
    $("#festival_list").fadeOut("fast");
    $("#user_select").show()

    $("#user_search2").keydown(function() {
    	let user_part = $("#user_search").val();
    	var coockie = JSON.parse(getCookie("YOUR_CV_INLOG_TOKEN_AND_ID"));
    	api("user_search", {
    		"id": coockie.ID,
    		"hash": coockie.TOKEN,
    		"search": user_part
		}, add_user_search_result2);
	});		
}

function add_user_search_result2(data){
	user_list = data;
    $("#myDropdown2 a").remove();
    for (let x = 0; x < data.length; x++) {
        $("#myDropdown2").append("<a id='user" + data[x].users_Id_Users + "' class ='user_select_list' href='#';>" + data[x].name + "</a>");
        $(".user_select_list").off();
        $(".user_select_list").click(function(event) {
            let id = event.target.attributes.id.value;
            id = id.replace(/[a-z]/gi, '');
            let user = user_list.find(function(user) {
                return user.users_Id_Users == id;
            })
            selected_user = id;
			$("#fname").val(user.name);
			var date = new Date(user.date_of_birth);
			var input_date = formatDate(date)
			$("#dateofbirth").val(input_date);

			$("#gender").val(user.Gender);
			$("#address_1").val(user.adres_line_one);
			$("#address_2").val(user.adres_line_two);
			$("#tel").val(user.telephone);
			$("#license").val(user.driver_license);
            $("#size").val(user.size);
			$("#country").val(user.nationality);
			$("#text").val(user.text);
			$("#marital_state").val(user.marital_state);
			$("#user_info").show();
			
					// click to insert data
            $("#submit").off();
			$("#submit").click(function() {
				var user = $("#fname").val();
				var date_of_birth = $("#dateofbirth").val();
				var gender = $("#gender").val();
				var address_1 = $("#address_1").val();
				var address_2 = $("#address_2").val();
                var size = $("#size").val();
				var telephone = $("#tel").val();
				var driving_license = $("#license").val();
				var country = $("#country").val();
				var text = $("#text").val();
				var marital_state = $("#marital_state").val();
				insert(user, date_of_birth, gender, address_1, address_2, telephone, driving_license, country, text, marital_state, size);
			});
        })
    }
    $(window).click(function() {
        $("#myDropdown2 a").remove();

    });
}


function get_select(id) {
    return '<select id="' + id + '" style="width:20%" class="festi_status" name="status"><option value="0">opvraging interesse</option><option value="1">Aangekondigd</option><option value="3">Open met vrije inschrijving</option><option value="2">open met reservatie</option><option value="4">festival bezig</option><option value="5">eindafrekeningen</option><option value="6">afgesloten</option><option value="7">geannuleerd</option></select>';
}

function festival_shift_subscribers() {
    clearAll();
    $("#subscription_li").css({
        "textDecoration": "underline"
    });
    var coockie = JSON.parse(getCookie("YOUR_CV_INLOG_TOKEN_AND_ID"));
    api("get_festivals", {
        "id": coockie.ID,
        "hash": coockie.TOKEN,
        "select": "active",
        "festi_id": "invalid"
    }, festival_shift_processing_ligth);
}

function insert(user, dateofbirth, gender, address_1, address_2, telephone, driver_license, country, text, marital_state, size){
	var coockie = JSON.parse(getCookie("YOUR_CV_INLOG_TOKEN_AND_ID"));
	body = {
		"id" 	: coockie.ID,
		"hash"  : coockie.TOKEN,
		"user_id": selected_user,
		"name"  : user,
		"date_of_birth" : dateofbirth,
		"Gender" : gender,
		"adres_line_one" : address_1,
		"adres_line_two" : address_2,
        "size" : size,
		"driver_license" : driver_license,
		"nationality" : country,
		"telephone" : telephone,
		"marital_state" : marital_state,
		"text": text,
	}
	api("insert_main_admin", body, function(){
		alert("opgeslagen");
	})
};
	
	
function festival_checkbox_listing() {
    clearAll();
    $("#present_li").css({
        "textDecoration": "underline"
    });

    var coockie = JSON.parse(getCookie("YOUR_CV_INLOG_TOKEN_AND_ID"));
    api("get_festivals", {
        "id": coockie.ID,
        "hash": coockie.TOKEN,
        "select": "active",
        "festi_id": "invalid"
    }, festival_checkbox);
}

function load_festivals_shifts() {
    var coockie = JSON.parse(getCookie("YOUR_CV_INLOG_TOKEN_AND_ID"));
    api("get_festivals", {
        "id": coockie.ID,
        "hash": coockie.TOKEN,
        "select": "active",
        "festi_id": "invalid"
    }, festival_shift_processing);
}

// function that starts the page but only when it this is the admin 
function check_if_admin(callback) {
    var coockie = JSON.parse(getCookie("YOUR_CV_INLOG_TOKEN_AND_ID"));
    if (!coockie) {
        window.location.href = "login.html";
    }
    api("is_admin", {
        "id": coockie.ID,
        "hash": coockie.TOKEN
    }, function(admin_data) {
        if (admin_data.status == 200) {
            callback();
        } else {
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
function api(action, body, callback) {
    $.ajax({
        type: 'POST',
        url: url + action,
        data: JSON.stringify(body),
        success: function(resp) {
            callback(JSON.parse(resp));
        },
        error: function(XMLHttpRequest, textStatus, errorThrown) {
            //window.location.href = "home.html";
        }
    });
};

//start of filling the page with all the festivals
function autofill_festivals() {
    var coockie = JSON.parse(getCookie("YOUR_CV_INLOG_TOKEN_AND_ID"));
    api("get_festivals", {
        "id": coockie.ID,
        "hash": coockie.TOKEN,
        "select": "active",
        "festi_id": "invalid"
    }, festival_processing);
}

// get the stastus from the id 
function id_to_status(id) {
    if (id == 0) {
        return "opvraging interesse";
    } else if (id == 1) {
        return "Aangekondigd";
    } else if (id == 2) {
        return "open met reservatie";
    } else if (id == 3) {
        return "Open met vrije inschrijving";
    } else if (id == 4) {
        return "festival bezig";
    } else if (id == 5) {
        return "eindafrekeningen";
    } else if (id == 6) {
        return "afgesloten";
    } else if (id == 7) {
        return "geannuleerd";
    } else {
        return "unknown";
    }
}

// callback for getting the festi date for 1 festival
function put_change_date(data) {
    $("#festi_name_change").val(data[0].name);
    $("#festi_discription_change").html(data[0].details);
    $("#festi_date_change").val(data[0].date.substring(0, 10));
    $("#change_festival_start").off();
    $("#change_festival_start").click(function(event) {
        let festiname = $("#festi_name_change").val();
        let festival_discription = $("#festi_discription_change").val();
        let date = $("#festi_date_change").val();
        var date_object = new Date(date);
        var input_date = formatDate(date_object)
        var coockie = JSON.parse(getCookie("YOUR_CV_INLOG_TOKEN_AND_ID"));
        api("change_festival_data", {
            "id": coockie.ID,
            "hash": coockie.TOKEN,
            festiname: festiname,
            festival_discription: festival_discription,
            date: input_date,
            idfestival: parseInt(open_id)
        }, autofill_festivals);
        $("#change_fesitvail_dialog").fadeOut(500);

    });
}

// callback for changed event
function changed_festival() {
    var coockie = JSON.parse(getCookie("YOUR_CV_INLOG_TOKEN_AND_ID"));
    api("get_festivals", {
        "id": coockie.ID,
        "hash": coockie.TOKEN,
        "select": "active"
    }, festival_processing);
    $("#change_fesitvail_dialog").fadeOut(500);
}

function payout_festival_list() {
    var coockie = JSON.parse(getCookie("YOUR_CV_INLOG_TOKEN_AND_ID"));
    api("get_festivals", {
        "id": coockie.ID,
        "hash": coockie.TOKEN
    }, payout_festivals);
}

function payout_festivals(data) {
    let festi_html = "<select id='festivals'>";
	if (data.length == 0 || data.length == undefined){
		$("#festival_list").html("");
		$("#festival_list").append("<div id='empty' class='festi2' ><p>Geen actieve festivals. </p></div>");
		$("#festival_list").show();
		return;
	}
    festival_idfestival = data[0].idfestival;
    for (let x = 0; x < data.length; x++) {

        festi_html = festi_html + "<option class='select_festi_option' id=" + data[x].idfestival + ">" + data[x].name + "</option>";

    }
	var coockie = JSON.parse(getCookie("YOUR_CV_INLOG_TOKEN_AND_ID"));
    api("payouts_list", {
        "id": coockie.ID,
        "hash": coockie.TOKEN,
        "festi_id": festival_idfestival
    }, payout_listing);
	
    festi_html = festi_html + "</select><div id='payout_list'></div>";
    $("#festival_list").html(festi_html);
    $("#festival_list").fadeIn("fast");
    $("#festivals").change(function(event) {
        festival_idfestival = $(this).children(":selected").attr("id");
        var coockie = JSON.parse(getCookie("YOUR_CV_INLOG_TOKEN_AND_ID"));
        api("payouts_list", {
            "id": coockie.ID,
            "hash": coockie.TOKEN,
            "festi_id": festival_idfestival
        }, payout_listing);
        $("#payout_list").html("");
    })
}

function re_load_payouts(){
	      api("payouts_list", {
            "id": coockie.ID,
            "hash": coockie.TOKEN,
            "festi_id": festival_payout
        }, payout_listing);
}
function payout_listing(data) {
    let users_Id_Users = 0;
    let cost = 0;
    let ok = true;
    let nok_html = "";
	let festival_payout = data[0].idfestival;

    for (let x = 0; x < data.length + 1; x++) {
        if (x == data.length) {
            if (ok) {
                $("#payout_list").append("<div style='background-color:green'  id='shift" + data[x - 1].idshifts + "' class='shift_day_line'><p style='width:33%'>naam:" + data[x - 1].name + "</p><p style='width:33%'>" + data[x - 1].adres_line_two + "</p><p style='width:33%'>bedrag:" + cost + "</p><input type='submit' id=" + data[x - 1].idshifts + " user=" + data[x - 1].users_Id_Users +" class='payout_approved' name='payout' value='Betaald' placeholder='' style='background-color: Blue ;  margin-left:10px;'><input type='submit' id=" + data[x - 1].idshifts + " user=" + data[x - 1].users_Id_Users + "  class='payout_denied' name='payout' value='Geweigerd' placeholder='' style='background-color: Blue ;  margin-left:10px;'></div>");

            } else {
                $("#payout_list").append("<div style='background-color:red' id='shift" + data[x - 1].idshifts + "' class='shift_day_line'><p style='width:33%'>naam:" + data[x - 1].name + "</p><p style='width:33%'>" + data[x - 1].adres_line_two + "</p><p style='width:33%'>bedrag:" + cost + "</p><input type='submit' id=" + data[x - 1].idshifts + " user=" + data[x - 1].users_Id_Users +" class='payout_approved' name='payout' value='Betaald' placeholder='' style='background-color: Blue ;  margin-left:10px;'><input type='submit' id=" + data[x - 1].idshifts + " user=" + data[x - 1].users_Id_Users +" class='payout_denied' name='payout' value='Geweigerd' placeholder='' style='background-color: Blue ;  margin-left:10px;'></div>");
                $("#payout_list").append(nok_html);

            }
            $(".payout_approved").off();
			$(".payout_approved").click(function(event) {
				let id = event.target.attributes.id.value;
				let user_id = event.target.attributes.user.value;
				var coockie = JSON.parse(getCookie("YOUR_CV_INLOG_TOKEN_AND_ID"));
				api("apply_payout", {
					"id": coockie.ID,
					"hash": coockie.TOKEN,
					"shift_id": id,
					"payout_type": 1,
					"user_id": user_id
				}, payout_festival_list);
				
			});
            $(".payout_denied").off();
			$(".payout_denied").click(function(event) {
				let id = event.target.attributes.id.value;
				let user_id = event.target.attributes.user.value;
				var coockie = JSON.parse(getCookie("YOUR_CV_INLOG_TOKEN_AND_ID"));
				api("apply_payout", {
					"id": coockie.ID,
					"hash": coockie.TOKEN,
					"shift_id": id,
					"payout_type": 2,
					"user_id": user_id
				}, payout_festival_list);
				
			});
            break;
        }
        if (data[x].users_Id_Users != users_Id_Users && x != 0) {
            if (ok) {
                $("#payout_list").append("<div style='background-color:green' id='shift" + data[x - 1].idshifts + "' class='shift_day_line'><p style='width:33%'>naam:" + data[x - 1].name + "</p><p style='width:33%'>" + data[x - 1].adres_line_two + "</p><p style='width:33%'>bedrag:" + cost + "</p><input type='submit' id=" + data[x - 1].idshifts + " user=" + data[x - 1].users_Id_Users +" class='payout_approved' name='payout' value='Betaald' placeholder='' style='background-color: Blue ;  margin-left:10px;'><input type='submit' id=" + data[x - 1].idshifts + " user=" + data[x - 1].users_Id_Users +" class='payout_denied' name='payout' value='Geweigerd' placeholder='' style='background-color: Blue ;  margin-left:10px;'></div>");

            } else {
                $("#payout_list").append("<div style='background-color:red' id='shift" + data[x - 1].idshifts + "' class='shift_day_line'><p style='width:33%'>naam:" + data[x - 1].name + "</p><p style='width:33%'>" + data[x - 1].adres_line_two + "</p><p style='width:33%'>bedrag:" + cost + "</p><input type='submit' id=" + data[x - 1].idshifts + " user=" + data[x - 1].users_Id_Users +" class='payout_approved' name='payout' value='Betaald' placeholder='' style='background-color: Blue ;  margin-left:10px;'><input type='submit' id=" + data[x - 1].idshifts + " user=" + data[x - 1].users_Id_Users +" class='payout_denied' name='payout' value='Geweigerd' placeholder='' style='background-color: Blue ;  margin-left:10px;'></div>");
                $("#payout_list").append(nok_html);

            }
            cost = 0;
            ok = true;
            nok_html = "";
        }
        $(".payout_approved").off();
		$(".payout_approved").click(function(event) {
            let id = event.target.attributes.id.value;
			let user_id = event.target.attributes.user.value;
			var coockie = JSON.parse(getCookie("YOUR_CV_INLOG_TOKEN_AND_ID"));
			api("apply_payout", {
				"id": coockie.ID,
				"hash": coockie.TOKEN,
				"shift_id": id,
				"payout_type": 1,
				"user_id": user_id
			}, payout_festival_list);
		}); 
        $(".payout_denied").off();
		$(".payout_denied").click(function(event) {
			let id = event.target.attributes.id.value;
			let user_id = event.target.attributes.user.value;
			var coockie = JSON.parse(getCookie("YOUR_CV_INLOG_TOKEN_AND_ID"));
			api("apply_payout", {
				"id": coockie.ID,
				"hash": coockie.TOKEN,
				"shift_id": id,
				"payout_type": 2,
				"user_id": user_id
			}, payout_festival_list);
		});
        users_Id_Users = data[x].users_Id_Users;
        cost = cost + parseFloat(data[x].cost);
        if (data[x].Payout == 1){cost = " reeds betaald "}
        if (data[x].Payout == 2){cost = " Betaling geweigerd "}
        if (ok) {
            ok = (data[x].present == 1 || (data[x].in == 1 && data[x].out == 1));
        }
        if (!(data[x].present == 1 || (data[x].in == 1 && data[x].out == 1))) {
            nok_html = nok_html + "<div class='nok_payout'><p style='width:5%'> </p><p style='width:15%'>€" + data[x].cost + " </p><p style='width:15%'>date: " + data[x].start_date + "</p><p style='width:15%'>Niet aanwezig<p></div>";

        }
    }
}

function festival_checkbox(data) {

    let festi_html = "<select id='festivals'>";
	if (data.length == 0 || data.length == undefined){
		$("#festival_list").html("");
		$("#festival_list").append("<div id='empty' class='festi2' ><p>Geen actieve festivals. </p></div>");
		$("#festival_list").show();
		return;
	}
    festival_idfestival = data[0].idfestival;
    for (let x = 0; x < data.length; x++) {

        festi_html = festi_html + "<option class='select_festi_option' id=" + data[x].idfestival + ">" + data[x].name + "</option>";

    }
    festi_html = festi_html + "</select><div id='shift_select_placeholder'></div>";
    $("#festival_list").html(festi_html);
    $("#festival_list").fadeIn("fast");
    $("#festivals").change(function(event) {
        festival_idfestival = $(this).children(":selected").attr("id");
        var coockie = JSON.parse(getCookie("YOUR_CV_INLOG_TOKEN_AND_ID"));
        api("get_shifts", {
            "id": coockie.ID,
            "hash": coockie.TOKEN
        }, shift_processing_checkbox);
        $("#list_select_placeholder").html("");
    })
    var coockie = JSON.parse(getCookie("YOUR_CV_INLOG_TOKEN_AND_ID"));
    api("get_shifts", {
        "id": coockie.ID,
        "hash": coockie.TOKEN
    }, shift_processing_checkbox);
}



function shift_processing_checkbox(data) {
    let shift_html = "<select id='shifts'>";
    let first = true;
    for (let x = 0; x < data.length; x++) {
        if (data[x].festival_idfestival == festival_idfestival) {
            if (first) {
                selected_shift_presense = data[x].idshifts;
                var coockie = JSON.parse(getCookie("YOUR_CV_INLOG_TOKEN_AND_ID"));
                api("get_shift_days", {
                    "id": coockie.ID,
                    "hash": coockie.TOKEN
                }, shift_day_processing_checkbox);
                first = false;
            }
            shift_html = shift_html + "<option class='select_shift_option' id=shift" + data[x].idshifts + ">" + data[x].name + "</option>";
        }
    }
    shift_html = shift_html + "</select><div id='shift_day_select_placeholder'></div>";
    $("#shift_select_placeholder").html(shift_html);

    $("#shifts").change(function(event) {
        let id = $(this).children(":selected").attr("id");
        selected_shift_presense = id.replace(/[a-z]/gi, '');
        var coockie = JSON.parse(getCookie("YOUR_CV_INLOG_TOKEN_AND_ID"));
        api("get_shift_days", {
            "id": coockie.ID,
            "hash": coockie.TOKEN
        }, shift_day_processing_checkbox);
        $("#list_select_placeholder").html("");
    })
}

function shift_day_processing_checkbox(data) {
    let shift_day_html = "<select id='shift_days'>";
    let first = true;
    for (let x = 0; x < data.length; x++) {
        if (data[x].idshifts == selected_shift_presense) {
            if (first) {
                selected_workday_presense = data[x].idshift_days;
                var coockie = JSON.parse(getCookie("YOUR_CV_INLOG_TOKEN_AND_ID"));
                api("get_workdays_subscribers", {
                    "id": coockie.ID,
                    "hash": coockie.TOKEN
                }, get_subscribers_checkbox_callback);
                first = false;
            }
            shift_day_html = shift_day_html + "<option class='select_shift_day_option' id=shiftday" + data[x].idshift_days + ">Van " + data[x].start_date + " tot " + data[x].shift_end + "</option>";
        }
    }
    shift_day_html = shift_day_html + "</select><div id='list_select_placeholder'></div>";
    $("#shift_day_select_placeholder").html(shift_day_html);
    $("#shift_days").change(function() {
        let id = $(this).children(":selected").attr("id");
        selected_workday_presense = id.replace(/[a-z]/gi, '');
        var coockie = JSON.parse(getCookie("YOUR_CV_INLOG_TOKEN_AND_ID"));
        api("get_workdays_subscribers", {
            "id": coockie.ID,
            "hash": coockie.TOKEN
        }, get_subscribers_checkbox_callback);
    })

}

function get_subscribers_checkbox_callback(data) {
    let user_html = "<input type='submit' id='download_pdf' shift_day='"+selected_workday_presense+"' name='change festival' value='download pdf' placeholder='' style='background-color: green;width:100%;  margin-left:10px;  margin-top:10px;  margin-bottom:10px'>"
    for (let x = 0; x < data.length; x++) {
        if (selected_workday_presense == data[x].shift_days_idshift_days && data[x].reservation_type == 3) {
            let in_ = "";
            let out = "";
            let present = "";
            if (data[x].in == 1) {
                in_ = "checked";
            }
            if (data[x].out == 1) {
                out = "checked";
            }
            if (data[x].present == 1) {
                present = "checked";
            }

            user_html = user_html + "<div id='shift" + data[x].shifts_idshifts + "' class='shift_day_line'><div style='width:15%' id='img_user' ><img src=/" + data[x].picture_name + " width='auto' height='60px'></div><p style='width:20%'>naam: " + data[x].name + "<p><p style='width:20%'>Tel: " + data[x].telephone + "</p><label for='title'>In:</label><input work_day='" + data[x].idwork_day + "' user='" + data[x].users_Id_Users + "' type='checkbox' class='checkbox_in' name='in'" + in_ + "><label for='title'>Out:</label><input user='" + data[x].users_Id_Users + "' work_day='" + data[x].idwork_day + "' type='checkbox' class='checkbox_out' name='in'" + out + "><label for='title'>Aanwezig:</label><input user='" + data[x].users_Id_Users + "' work_day='" + data[x].idwork_day + "' type='checkbox' class='checkbox_present' name='in'" + present + "></div>";


        }

    }
    $("#list_select_placeholder").html(user_html);
	
	
	$("#download_pdf").click(function(event) {
		var coockie = JSON.parse(getCookie("YOUR_CV_INLOG_TOKEN_AND_ID"));
		let day = event.target.attributes.shift_day.value
		window.open(url + "pdf_listing&ID=" + coockie.ID + "&HASH=" + coockie.TOKEN + "&shift_day=" + day);
		
	});
	
    $(".checkbox_in").change(function(event) {
        let user = event.target.attributes.user.value;
        let work_day = event.target.attributes.work_day.value;
        let coockie = JSON.parse(getCookie("YOUR_CV_INLOG_TOKEN_AND_ID"));
        let in_ = this.checked;
        let in__ = 0;
        if (in_) {
            in__ = 1;
        }

        api("user_present", {
            "id": coockie.ID,
            "hash": coockie.TOKEN,
            "user": parseInt(user),
            "work_day": parseInt(work_day),
            "in": in__,
            "out": 2,
            "present": 2
        }, function() {
            api("get_workdays_subscribers", {
                "id": coockie.ID,
                "hash": coockie.TOKEN
            }, get_subscribers_checkbox_callback);
        })

    })
    $(".checkbox_out").change(function(event) {
        let user = event.target.attributes.user.value;
        let work_day = event.target.attributes.work_day.value;
        let coockie = JSON.parse(getCookie("YOUR_CV_INLOG_TOKEN_AND_ID"));
        let out = this.checked;
        let out_ = 0;
        if (out) {
            out_ = 1;
        }

        api("user_present", {
            "id": coockie.ID,
            "hash": coockie.TOKEN,
            "user": parseInt(user),
            "work_day": parseInt(work_day),
            "in": 2,
            "out": out_,
            "present": 2
        }, function() {
            api("get_workdays_subscribers", {
                "id": coockie.ID,
                "hash": coockie.TOKEN
            }, get_subscribers_checkbox_callback);
        })

    })
    $(".checkbox_present").change(function(event) {
        let user = event.target.attributes.user.value;
        let work_day = event.target.attributes.work_day.value;
        let coockie = JSON.parse(getCookie("YOUR_CV_INLOG_TOKEN_AND_ID"));
        let present = this.checked;
        let present_ = 0;
        if (present) {
            present_ = 1;
        }

        api("user_present", {
            "id": coockie.ID,
            "hash": coockie.TOKEN,
            "user": parseInt(user),
            "work_day": parseInt(work_day),
            "in": 2,
            "out": 2,
            "present": present_
        }, function() {
            api("get_workdays_subscribers", {
                "id": coockie.ID,
                "hash": coockie.TOKEN
            }, get_subscribers_checkbox_callback);
        })

    })

}


function festival_shift_processing(data) {
	if (data.length == 0 || data.length == undefined){
		$("#festival_list").html("");
		$("#festival_list").append("<div id='empty' class='festi2' ><p>Geen actieve festivals. </p></div>");
		$("#festival_list").show();
		return;
	}
    var coockie = JSON.parse(getCookie("YOUR_CV_INLOG_TOKEN_AND_ID"));
    api("get_shifts", {
        "id": coockie.ID,
        "hash": coockie.TOKEN
    }, shift_processing);
    $("#add_fesitvail").fadeOut("slow");
    $("#festival_list").html("");
    for (let x = 0; x < data.length; x++) {
        $("#festival_list").append("<div id=festival" + data[x].idfestival + " class='festi' ><div style='width:20%' class='festi_date'><h2>" + data[x].name + "</h2></div style='width:10%'><p>" + data[x].date + "</p><p style='width:60%'>" + data[x].details + "</p>" + "<input type='submit' id=" + data[x].idfestival + " class='change_shift2' name='change festival' value='shift toevoegen' placeholder='' style='background-color: rgb(76, 175, 80);  margin-left:10px;'></div>");
        $('#' + data[x].idfestival + " select").val(data[x].status);
        // change festival
        $(".change_shift2").click(function(event) {
            open_id = event.target.attributes.id.value;
            // delete all fields
            $("#shift_name").val("");
            $("#shift_details").val("");
            $("#people_needed").val("");
            $("#people_needed_reserved").val("");
            $("#days").val("");
            $("#add_shift").fadeIn(500);
            $("#add_shift_abort").click(function(event) {
                $("#add_shift").fadeOut(500);
            });
            $("#add_shift_start").off();
            $("#add_shift_start").click(function(event) {
                let shiftname = $("#shift_name").val();
                let shift_discription = $("#shift_details").val();
                let people_needed = $("#people_needed").val();
                let reserved = $("#people_needed_reserved").val();
                let days = $("#days").val();
                var coockie = JSON.parse(getCookie("YOUR_CV_INLOG_TOKEN_AND_ID"));
                api("add_shift", {
                    "id": coockie.ID,
                    "hash": coockie.TOKEN,
                    name: shiftname,
                    discription: shift_discription,
                    needed: people_needed,
                    reserve: reserved,
                    length: days,
                    festi_id: open_id
                }, load_festivals_shifts);
            });
        });
    }
    $("#festival_list").fadeIn("fast");
}

function festival_shift_processing_ligth(data) {
	if (data.length == 0 || data.length == undefined){
		$("#festival_list").html("");
		$("#festival_list").append("<div id='empty' class='festi2' ><p>Geen actieve festivals. </p></div>");
		$("#festival_list").show();
		return;
	}
    var coockie = JSON.parse(getCookie("YOUR_CV_INLOG_TOKEN_AND_ID"));
    api("get_shifts", {
        "id": coockie.ID,
        "hash": coockie.TOKEN
    }, shift_processing_short);
    $("#festival_list").html("");
	if (data.length == 0 || data.length == undefined){
		$("#festival_list").html("");
		$("#festival_list").append("<div id='empty' class='festi2' ><p>Geen actieve festivals. </p></div>");
		$("#festival_list").show();
	}
    for (let x = 0; x < data.length; x++) {
        $("#festival_list").append("<div id=" + data[x].idfestival + " class='festi' ><div style='width:20%' class='festi_date'><h2>" + data[x].name + "</h2></div style='width:10%'><p>" + data[x].date + "</p><input type='submit' id=" + data[x].idfestival + " class='shirts' name='shirts' value='T-shirts' placeholder='' style='background-color: rgb(76, 175, 80);  margin-left:10px;float:none;'><p style='width:60%'>" + data[x].details + "</p></div>");
        $('#' + data[x].idfestival + " select").val(data[x].status);
        $(".shirts").off();
        $(".shirts").click(function(event) {
        let id = event.target.attributes.id.value;
        var coockie = JSON.parse(getCookie("YOUR_CV_INLOG_TOKEN_AND_ID"));
        api("tshirts", {
            "id": coockie.ID,
            "hash": coockie.TOKEN,
            "festi_id": id
        }, show_shirts_dialog);
        });

        // change festival
    }
    $("#festival_list").fadeIn("fast");
}
function show_shirts_dialog(data){
    $("#shirt_list").show();
    $("#shirt_close").click(function(event){
        $("#shirt_list").fadeOut(200); 
    });
    let shirt_html = ""
    if(data.length < 1 || data.length == undefined) {
        shirt_html = shirt_html + "<p>Niemand ingeschreven</p>";
    }
    for(x = 0; x < data.length; x++){
        shirt_html = shirt_html + "<p>" + data[x].size + ": "+ data[x][0]+" Stuks</p>";
    }
    $("#size_holder").html(shirt_html);

}

//callback adding a shift 
function shift_processing(data) {
    // add days
    var coockie = JSON.parse(getCookie("YOUR_CV_INLOG_TOKEN_AND_ID"));
    api("get_shift_days", {
        "id": coockie.ID,
        "hash": coockie.TOKEN
    }, load_shift_days_shifts);
    api("get_locations", {
        "id": coockie.ID,
        "hash": coockie.TOKEN
    }, load_shift_days_shifts_locations);

    $("#add_shift").hide();
    for (let x = 0; x < data.length; x++) {
        $("#festival" + data[x].festival_idfestival).append("<div id=shift" + data[x].idshifts + " class='shift_line' ><div class='shift_title'><div style='width:20%' class='festi_date'><h2>" + data[x].name + "</h2></div><p style='width:10%'>Dagen: " + data[x].length + "</p><p style='width:60%'>" + data[x].datails + "</p>" + "<p style='width:10%'>Bezetting: " + data[x].people_needed + "</p>" + "<p style='width:10%'>Reserve: " + data[x].spare_needed + "</p>" + "<input type='submit' id=" + data[x].idshifts + " class='add_location_day_shift' name='change festival' value='Opvang toevoegen' toevoegen' placeholder='' style='background-color: rgb(76, 175, 80) ;  margin-left:10px;'>" + "<input type='submit' id=" + data[x].idshifts + " class='add_day_shift' name='change festival' value='Dag toevoegen' placeholder='' style='background-color: rgb(76, 175, 80) ;  margin-left:10px;'>" + "<input type='submit' id=" + data[x].idshifts + " class='change_shift' name='delete festival' value='Wijzigen' placeholder='' style='background-color: red ;  margin-left:10px;'>" + "<input type='submit' id=" + data[x].idshifts + " class='delete_shift' name='delete festival' value='Verwijderen' placeholder='' style='background-color: red ;  margin-left:10px;'></div></div>");
        $(".change_shift").click(function(event) {
            let id = event.target.attributes.id.value;
            var coockie = JSON.parse(getCookie("YOUR_CV_INLOG_TOKEN_AND_ID"));
            api("get_shift", {
                "id": coockie.ID,
                "hash": coockie.TOKEN,
                "idshifts": id
            }, fill_in_change_shift);
            $("#change_shift").fadeIn(500);
        });
        $(".delete_shift").click(function(event) {
            let id = event.target.attributes.id.value;
            var coockie = JSON.parse(getCookie("YOUR_CV_INLOG_TOKEN_AND_ID"));
            api("delete_shift", {
                "id": coockie.ID,
                "hash": coockie.TOKEN,
                "idshifts": id
            }, load_festivals_shifts);
        });
        $(".add_day_shift").click(function(event) {
            $("#add_shift_day").fadeIn(500);
            let id = event.target.attributes.id.value;
            $("#add_shift_day_abort").click(function(event) {
                $("#add_shift_day").fadeOut(500);
            });
            $("#add_shift_day_start").off();
            $("#add_shift_day_start").click(function(event) {
                // api add shift 

                let start = $("#shift_day_start").val();
                let start_object = new Date(start);
                let start_db = formatDate(start_object)

                let stop = $("#shift_day_stop").val();
                let stop_object = new Date(stop);
                let stop_db = formatDate(stop_object)

                let money = $("#compensation").val();
                var coockie = JSON.parse(getCookie("YOUR_CV_INLOG_TOKEN_AND_ID"));
                api("add_shift_day", {
                    "id": coockie.ID,
                    "hash": coockie.TOKEN,
                    "idshifts": id,
                    shifts_idshifts: id,
                    start: start_db,
                    stop: stop_db,
                    money: money
                }, load_festivals_shifts);
                $("#add_shift_day").fadeOut(500);


            });

        });
        $(".add_location_day_shift").off();
        $(".add_location_day_shift").click(function(event) {
            $("#add_shift_external").fadeIn(500);
            let id = event.target.attributes.id.value;
            $("#add_shift_external_abort").click(function(event) {
                $("#add_shift_external").fadeOut(500);
            });
            $("#add_shift_external_start").off();
            $("#add_shift_external_start").click(function(event) {

                let loc_date = $("#location_time").val();
                let loc_date_object = new Date(loc_date);
                let loc_date_db = formatDate(loc_date_object)

                let location = $("#external_location_info").val();
                api("add_location", {
                    "id": coockie.ID,
                    "hash": coockie.TOKEN,
                    "shift_id": id,
                    location: location,
                    appointment_time: loc_date_db
                }, load_festivals_shifts);
                $("#add_shift_external").fadeOut(500);
            });

        });     
    }
}

function reload_subscription(){
	var coockie = JSON.parse(getCookie("YOUR_CV_INLOG_TOKEN_AND_ID"));
    api("get_subscribers", {
        "id": coockie.ID,
        "hash": coockie.TOKEN
    }, subscribers_callback);
}

//callback adding a shift 
function shift_processing_short(data) {
    // add days
    var coockie = JSON.parse(getCookie("YOUR_CV_INLOG_TOKEN_AND_ID"));
    api("get_subscribers", {
        "id": coockie.ID,
        "hash": coockie.TOKEN
    }, subscribers_callback);

    $("#add_shift").hide();
    //TODO Add following functionality: 
    // mail all: THe option to mail all the people in the shift
    // pdf creation 

    
    for (let x = 0; x < data.length; x++) {
            let button_sub = "";
        if(data[x].work_days == 0){
            button_sub = "<p>Geen dagen in shift!</p>";
        }
        else {
            button_sub = "<input type='submit' id=useradd" + data[x].idshifts + " class='add_user_to_shift' name='change festival' value='manueel inschrijven' placeholder='' style='background-color: green ;  margin-left:15px;;  margin-right:15px'>";   
        }
        $("#" + data[x].festival_idfestival).append("<div id=shift" + data[x].idshifts + " class='shift_line' ><div class='shift_title'><div style='width:15%' class='festi_date'><h2>" + data[x].name + "</h2></div>"+ button_sub +"</input><p style='width:20%'>benodigde bezetting: " + data[x].people_needed + "</p>" + "<p style='width:20%'>gewenste reserve: " + data[x].spare_needed + "</p> " + "<p style='width:20%'>ingeschreven: " + data[x].subscribed_final + "</p><p style='width:20%'>geregistreerd: " + (data[x].subscribed - data[x].subscribed_final) + "</p></div></div>");
    }
    $(".add_user_to_shift").click(function() {
        let id = event.target.attributes.id.value;
        selected_shift = id.replace(/[a-z]/gi, '');
        $("#add_user_manual").fadeIn(500);
        $("#manual_user_abort").click(function() {
            $("#add_user_manual").fadeOut(500);
        });
        $("#user_search").keydown(function() {
            let user_part = $("#user_search").val();
            var coockie = JSON.parse(getCookie("YOUR_CV_INLOG_TOKEN_AND_ID"));
            api("user_search", {
                "id": coockie.ID,
                "hash": coockie.TOKEN,
                "search": user_part
            }, add_user_search_result);
        });
    });


}

function add_user_search_result(data) {
    user_list = data;
    $("#myDropdown a").remove();
    for (let x = 0; x < data.length; x++) {
        $("#myDropdown").append("<a id='user" + data[x].users_Id_Users + "' class ='user_select_list' href='#';>" + data[x].name + "</a>");
        $(".user_select_list").off();
        $(".user_select_list").click(function(event) {
            let id = event.target.attributes.id.value;
            id = id.replace(/[a-z]/gi, '');
            let user = user_list.find(function(user) {
                return user.users_Id_Users == id;
            })
            selected_user = id;
            $("#user_data").html("<img src=/" + user.picture_name + " alt='Toevoegen van lid'><label><strong>Naam: </strong></label><p>" + user.name + "<p>	<label><strong>Geboortedatum: </strong></label><p>" + user.date_of_birth + "<p>		<label><strong>rijksregister: </strong></label><p>" + user.driver_license + "<p>");
        })
    }
    $(window).click(function() {
        $("#myDropdown a").remove();
    });
    $("#manual_user_start").off();
    $("#manual_user_start").click(function(event) {
        $("#add_user_manual").fadeOut(500);
        var coockie = JSON.parse(getCookie("YOUR_CV_INLOG_TOKEN_AND_ID"));
        if (coockie.ID == selected_user) {
            selected_user = "admin";
        }
        api("user_subscribe", {
            "id": coockie.ID,
            "hash": coockie.TOKEN,
            "Id_Users": selected_user,
            "idshifts": selected_shift
        }, festival_shift_subscribers);

    });
}

function user_lookup(user, user_id) {

}

function subscribers_callback(data) {
	$(".shift_day_line").remove();
    for (let x = 0; x < data.length; x++) {
        let user_status = "unknown";

        if (data[x].reservation_type == 2) {
            user_status = "Geregistreerd";
            $("#shift" + data[x].shifts_idshifts).append("<div id='shift" + data[x].shifts_idshifts + "' class='shift_day_line'><div style='width:15%' id='img_user' ><img src=/" + data[x].picture_name + " width='auto' height='60px'></div><p style='width:20%'>naam: " + data[x].name + "<p><p style='width:20%'>Status: " + user_status + "<p><input type='submit' id=" + data[x].users_Id_Users + " shift ='" + data[x].shifts_idshifts + "' class='unsubscribe_user' name='delete festival' value='weigeren' placeholder='' style='background-color: red ;  margin-left:10px;'>" + "<input type='submit' id=" + data[x].users_Id_Users + " shift ='" + data[x].shifts_idshifts + "' class='subscribe_user' name='delete festival' value='Inschrijven' placeholder='' style='background-color: green ;  margin-left:10px;'></div>");
        }
        if (data[x].reservation_type == 3) {
            user_status = "Ingeschreven";
            $("#shift" + data[x].shifts_idshifts).append("<div id='shift" + data[x].shifts_idshifts + "' class='shift_day_line'><div style='width:15%' id='img_user' ><img src=/" + data[x].picture_name + " width='auto' height='60px'></div><p style='width:20%'>naam: " + data[x].name + "<p><p style='width:20%'>Status: " + user_status + "<p><input type='submit' id=" + data[x].users_Id_Users + " shift ='" + data[x].shifts_idshifts + "' class='unsubscribe_user' name='delete festival' value='Uitschrijven' placeholder='' style='background-color: red ;  margin-left:10px;'></div>");
        }
        if (data[x].reservation_type == 99) {
            user_status = "reservelijst";
            $("#shift" + data[x].shifts_idshifts).append("<div id='shift" + data[x].shifts_idshifts + "' class='shift_day_line'><div style='width:15%' id='img_user' ><img src=/" + data[x].picture_name + " width='auto' height='60px'></div><p style='width:20%'>naam: " + data[x].name + "<p><p style='width:20%'>Status: " + user_status + "<p><input type='submit' id=" + data[x].users_Id_Users + " shift ='" + data[x].shifts_idshifts + "' class='unsubscribe_user' name='delete festival' value='weigeren' placeholder='' style='background-color: red ;  margin-left:10px;'>" + "<input type='submit' id=" + data[x].users_Id_Users + " shift ='" + data[x].shifts_idshifts + "' class='subscribe_user' name='delete festival' value='Inschrijven' placeholder='' style='background-color: green ;  margin-left:10px;'></div>");
        }
        $(".unsubscribe_user").off();
        $(".unsubscribe_user").click(function(event) {
            var coockie = JSON.parse(getCookie("YOUR_CV_INLOG_TOKEN_AND_ID"));
            let user = event.target.attributes.id.value;
            let shift = event.target.attributes.shift.value
            api("user_unsubscribe", {
                "id": coockie.ID,
                "hash": coockie.TOKEN,
                "idshifts": shift,
                "Id_Users": user
            }, reload_subscription);
        });
        $(".subscribe_user").off();
        $(".subscribe_user").click(function(event) {
            var coockie = JSON.parse(getCookie("YOUR_CV_INLOG_TOKEN_AND_ID"));
            let user = event.target.attributes.id.value;
            let shift = event.target.attributes.shift.value
            if (user == coockie.ID) {
                user = "admin"
            }
            api("user_subscribe", {
                "id": coockie.ID,
                "hash": coockie.TOKEN,
                "idshifts": shift,
                "Id_Users": user
            }, reload_subscription);
        });


    }
}

//
//callback to fill in date in the change shift dialog
//
function fill_in_change_shift(data) {

    $("#shift_name_change").val(data[0].name);
    $("#shift_details_change").val(data[0].datails);
    $("#people_needed_change").val(data[0].people_needed);
    $("#people_needed_reserved_change").val(data[0].spare_needed);
    $("#festi_days_change").val(data[0].length);
    $("#change_shift_abort").click(function(event) {
        $("#change_shift").fadeOut(500);
    });
    $("#change_shift_start").off();
    $("#change_shift_start").click(function(event) {
        //change data
        let name = $("#shift_name_change").val();
        let details = $("#shift_details_change").val();
        let people = $("#people_needed_change").val();
        let reserve = $("#people_needed_reserved_change").val();
        let days = $("#festi_days_change").val();
        var coockie = JSON.parse(getCookie("YOUR_CV_INLOG_TOKEN_AND_ID"));
        if (name == "") {
            alert("Geen naam opgegeven");
            return;
        }
        api("change_shift", {
            "id": coockie.ID,
            "hash": coockie.TOKEN,
            name: name,
            details: details,
            people: people,
            reserve: reserve,
            days: days,
            idshifts: data[0].idshifts
        }, load_festivals_shifts)
        $("#change_shift").fadeOut(500);
    });
}

// callback for the get_festivals
function festival_processing(data) {
    $("#add_festival_start").off();
    $("#add_fesitvail").fadeOut("slow");
    $("#festival_list").html("");
	if (data.length == 0 || data.length == undefined){
		$("#festival_list").html("");
		$("#festival_list").append("<div id='empty' class='festi2' ><p>Geen actieve festivals. </p></div>");
		$("#festival_list").show();
		return;
	}
    for (let x = 0; x < data.length; x++) {
        $("#festival_list").append("<div id=" + data[x].idfestival + " class='festi2' ><div style='width:20%' class='festi_date'><h2>" + data[x].name + "</h2></div style='width:10%'><p>" + data[x].date + "</p><p style='width:60%'>" + data[x].details + "</p>" + get_select(data[x].idfestival) + "<input type='submit' id=" + data[x].idfestival + " class='change_festival' name='change festival' value='wijzingen' placeholder='' style='background-color: red ;  margin-left:10px;'></input></div>");
        $('#' + data[x].idfestival + " select").val(data[x].status);
        // change festival
        $(".change_festival").off();
        $(".change_festival").click(function(event) {
            open_id = event.target.attributes.id.value;
            $("#change_fesitvail_dialog").fadeIn();
            var coockie = JSON.parse(getCookie("YOUR_CV_INLOG_TOKEN_AND_ID"));
            api("get_festivals", {
                "id": coockie.ID,
                "hash": coockie.TOKEN,
                "select": "select",
                "festi_id": open_id
            }, put_change_date);
            $("#change_festival_abort").click(function(event) {
                $("#change_fesitvail_dialog").fadeOut(500);
            });
        });
        $(".festi_status").off();
        $(".festi_status").change(function(event) {
            let festi = event.target.attributes.id.value;
            let type = $(this).val()
            var coockie = JSON.parse(getCookie("YOUR_CV_INLOG_TOKEN_AND_ID"));
            api("change_festival_status", {
                "id": coockie.ID,
                "hash": coockie.TOKEN,
                "festival_id": festi,
                "status": type
            }, autofill_festivals);
        });
    }
}

function load_shift_days_shifts(data) {

    for (let x = 0; x < data.length; x++) {
        //TODO Counter should only count days with correct ID 
        let counter = $('.shift_day_line', "#shift" + data[x].idshifts).length + 1;
        $("#shift" + data[x].idshifts).append("<div id='shift_day" + data[x].idshifts + "' class='shift_day_line'><p class='shift_day_title' style='width:10%'>Dag " + counter + "<p><p style='width:20%'>Start: " + data[x].start_date + "<p><p style='width:20%'>Einde: " + data[x].shift_end + "<p><p style='width:20%'>Dagvergoeding: " + data[x].cost + "</p><input type='submit' id=" + data[x].idshift_days + " class='change_shift_day' name='delete festival' value='Wijzigen' placeholder='' style='background-color: red ;  margin-left:10px;'>" + "<input type='submit' id=" + data[x].idshift_days + " class='delete_shift_day' name='delete festival' value='Verwijderen' placeholder='' style='background-color: red ;  margin-left:10px;'></div>");

        $(".change_shift_day").click(function(event) {
            var coockie = JSON.parse(getCookie("YOUR_CV_INLOG_TOKEN_AND_ID"));
            open_id = event.target.attributes.id.value;
            api("get_shift_day", {
                "id": coockie.ID,
                "hash": coockie.TOKEN,
                "shift_day_id": open_id
            }, full_in_changed_shift_day)
            $("#change_shift_day").fadeIn(500);
            //cancel
            $("#change_shift_day_abort").click(function() {
                $("#change_shift_day").fadeOut(500);
            });
            $("#change_shift_day_start").off();
            $("#change_shift_day_start").click(function() {
                // change
                let start = $("#shift_day_start_change").val();
                let start_object = new Date(start);
                let start_db = formatDate(start_object)

                let stop = $("#shift_day_stop_change").val();
                let stop_object = new Date(stop);
                let stop_db = formatDate(stop_object)

                let money = $("#compensation_change").val();
                var coockie = JSON.parse(getCookie("YOUR_CV_INLOG_TOKEN_AND_ID"));
                api("change_shift_day", {
                    "id": coockie.ID,
                    "hash": coockie.TOKEN,
                    "shift_day_id": open_id,
                    start: start_db,
                    stop: stop_db,
                    money: money
                }, load_festivals_shifts);
                $("#change_shift_day").fadeOut(500);
            });
        });
        $(".delete_shift_day").off();
        $(".delete_shift_day").click(function(event) {
            let id = event.target.attributes.id.value;
            var coockie = JSON.parse(getCookie("YOUR_CV_INLOG_TOKEN_AND_ID"));
            api("delete_shift_day", {
                "id": coockie.ID,
                "hash": coockie.TOKEN,
                "shift_day_id": id
            }, load_festivals_shifts);

        });
    }
}

function load_shift_days_shifts_locations(data) {

    for (let x = 0; x < data.length; x++) {
        //TODO Counter should only count days with correct ID 
        let counter = $('.shift_day_line', "#shift" + data[x].idshifts).length + 1;
        $("#shift" + data[x].idshifts).append("<div id='shift_day" + data[x].idshifts + "' class='shift_day_line2'><p class='shift_day_title' style='width:10%'>Opvang moment<p><p style='width:20%'>Tijdsip: " + data[x].appointment_time + "<p><p style='width:20%'>Plaats: " + data[x].location + "</p><p style='width:20%'></p><input type='submit' id=" + data[x].location_id + " class='change_shift_day_location' name='delete festival' value='Wijzigen' placeholder='' style='background-color: red ;  margin-left:10px;'>" + "<input type='submit' id=" + data[x].location_id + " class='delete_shift_day_location' name='delete festival' value='Verwijderen' placeholder='' style='background-color: red ;  margin-left:10px;'></div>");

        $(".change_shift_day").click(function(event) {
            var coockie = JSON.parse(getCookie("YOUR_CV_INLOG_TOKEN_AND_ID"));
            open_id = event.target.attributes.id.value;
            api("get_shift_day", {
                "id": coockie.ID,
                "hash": coockie.TOKEN,
                "shift_day_id": open_id
            }, full_in_changed_shift_day)
            $("#change_shift_day").fadeIn(500);
            //cancel
            $("#change_shift_day_abort").click(function() {
                $("#change_shift_day").fadeOut(500);
            });
            $("#change_shift_day_start").off();
            $("#change_shift_day_start").click(function() {
                // change
                let start = $("#shift_day_start_change").val();
                let start_object = new Date(start);
                let start_db = formatDate(start_object)

                let stop = $("#shift_day_stop_change").val();
                let stop_object = new Date(stop);
                let stop_db = formatDate(stop_object)

                let money = $("#compensation_change").val();
                var coockie = JSON.parse(getCookie("YOUR_CV_INLOG_TOKEN_AND_ID"));
                api("change_shift_day", {
                    "id": coockie.ID,
                    "hash": coockie.TOKEN,
                    "shift_day_id": open_id,
                    start: start_db,
                    stop: stop_db,
                    money: money
                }, load_festivals_shifts);
                $("#change_shift_day").fadeOut(500);
            });
        });
        $(".delete_shift_day_location").off();
        $(".delete_shift_day_location").click(function(event) {
            let id = event.target.attributes.id.value;
            var coockie = JSON.parse(getCookie("YOUR_CV_INLOG_TOKEN_AND_ID"));
            api("delete_location", {
                "id": coockie.ID,
                "hash": coockie.TOKEN,
                "location_id": id
            }, load_festivals_shifts);

        });
    }
}


function full_in_changed_shift_day(data) {
    shift = data[0];
    // Todo set in textfield
    $("#shift_day_start_change").val(shift.start_date.replace(" ", "T"));
    $("#shift_day_stop_change").val(shift.shift_end.replace(" ", "T"));
    $("#compensation_change").val(shift.cost);
}

function clearAll() {
    $("#add_fesitvail").fadeOut("fast");
    $("#festival_list").fadeOut("fast");
    $("#change_fesitvail_dialog").fadeOut("fast");
    $("#add_festit_init").fadeOut("fast");
    $("#add_shift_init").fadeOut("fast");
	$("#messenger").fadeOut("fast");
	$("#user_info").fadeOut("fast");
	$("#user_select").fadeOut("fast");

    $("#festivals_li").css({
        "textDecoration": "none"
    });
    $("#shifts_li").css({
        "textDecoration": "none"
    });
    $("#subscription_li").css({
        "textDecoration": "none"
    });
    $("#present_li").css({
        "textDecoration": "none"
    });
    $("#users_li").css({
        "textDecoration": "none"
    });
    $("#payouts_li").css({
        "textDecoration": "none"
    });

}