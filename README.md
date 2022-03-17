# All Round Events

## api

All api handling happens via api.php, however this php file doesn't generate HTML. Every request to this file not withing spec will result in an redirect to home.html. 

Api.php handles POST requests and only needs one query parameter: "action". Every action will have different functions. The api can be used as follows: https://all-round-events.be/api.php?action=WANTED_ACTION

## DATABASE

The DB used is a single maria DB. The DB can be created(empty) with a script avaiable in /sql

Two things need to be done manually to get everything working
	-> At least one activation code should be added 
	-> At leas one user should be made admin. Set is_admin to "1"
	
tables: 
	activation_codes: Has the codes for letting new users register to the website. Originally the idea was one activation code per activation, this plan has changed to one activation for everybody.
	external_appointment: Contains dataset indicating who needs to be at one external meeting location and it also holds of the person was present or not. 
	festivals: Contains all the events 
	hashes: Contains tokens that users use to authenticate  with every api. The php code ensures every user can only have one token. Tokens have no end dataset 
	images: Images are not stored in DB but on the server filesystem. This table only saves the location of the image. PHP code syncs DB and filesystem 
	location: All external meeting locations 
	logs: Saves every api call and failed logins. Is also used to check if too many logings happen 
	mails: All mails send in php are first put into this table. Mails that are not send have NULL as send_process. A cron job every minute will process the mailing list 
	notifications: all mails will also be printed here
	settings: all settings for mailing and logging
	shifts: all shifts 
	shift_datys: all days inside a shift
	users: critcal urser dataset
	users_data: all non critical user dataset
	work_days: records for 1 person working on one day of one shift of one event

## JAVASCRIPT

Almost all webpage run JS to make the pages dynamic. Only jquery is used. 

## CSS

vanilla CSS is used, to make it responsive @media screen and (max-width: 70rem) is used

## HTML 

vanillie HTML is used
