<?php 
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.


/**

*
* @package    local
* @subpackage facebook
* @copyright  2013 Francisco García Ralph (francisco.garcia.ralph@gmail.com)
* 			  2015 Mihail Pozarski (mipozarski@alumnos.uai.cl)
* 			  2015 Hans Jeria (hansjeria@gmail.com)
* @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
*/

require_once (dirname ( dirname ( dirname ( __FILE__ ) ) ) . '/config.php');
include 'app/config.php';
require_once ($CFG->dirroot . '/local/facebook/forms.php');
global $DB, $USER, $CFG;

$app_name = $CFG->fbkAppNAME;
$facebook = new Facebook ( $config );
$facebook_id = $facebook->getUser ();

require_login (); // Require log in.
                  
// URL for current page
$url = new moodle_url ( '/local/facebook/connect.php' );

?>
<div id="fb-root"></div>
<script>(function(d, s, id) {
  var js, fjs = d.getElementsByTagName(s)[0];
  if (d.getElementById(id)) return;
  js = d.createElement(s); js.id = id;
  js.src = "//connect.facebook.net/es_LA/all.js#xfbml=1&appId=559078344137958";
  
  fjs.parentNode.insertBefore(js, fjs);
}(document, 'script', 'facebook-jssdk'));</script>
<?php

$context = context_system::instance ();

$PAGE->set_url ( $url );
$PAGE->set_context ( $context );
$PAGE->set_pagelayout ( 'standard' );
$connect = optional_param ( 'connect', null, PARAM_TEXT );
$disconnect = optional_param ( 'disconnect', null, PARAM_TEXT );

$PAGE->navbar->add ( get_string ( 'facebook', 'local_facebook' ) );
echo $OUTPUT->header ();

// busco si el usuario tiene enlazada la cuenta
$user_info = $DB->get_record ( 'facebook_user', array (
		'moodleid' => $USER->id,
		'status' => 1 
) );

$time = time ();
// Look if the user has accepted the permissions
// if by looking the facebook_id is 0, that means the user hasn't accepted it.

// if the status is 0 is because the user has unlink the facebook account and if the $user_info is null is because the user hasn't link the account yet.
// if any of these things happend it will give the user the option to link the account

if (isset ( $user_info->status )) {
	// If the user press the unlink account
	if ($disconnect != NULL) {
		
		// Save all the user info but with status 0
		$record = new stdClass ();
		$record->id = $user_info->id;
		$record->moodleid = $USER->id;
		$record->facebookid = $user_info->facebookid;
		$record->timemodified = $time;
		$record->status = 0;
		$record->lasttimechecked = $time;
		// Update the DB to deactivate the account.
		$DB->update_record ( 'facebook_user', $record );
		echo $OUTPUT->heading ( get_string ( 'succesfullconnect', 'local_facebook' ), 3 ) . "<a href='../../'>" . get_string ( 'back', 'local_facebook' ) . "</a>>";
	} else {
		$facebook_id = $user_info->facebookid;
		$status = $user_info->status;
		echo $OUTPUT->heading ( get_string ( 'connectheading', 'local_facebook' ) );
		// Facebook code to search the user information.
		// We have a user ID, so probably a logged in user.
		// If not, we'll get an exception, which we handle below.
		try {
			$user_profile = $facebook->api ( '' . $facebook_id . '', 'GET' );
			
			$link = $user_profile ['link'];
			$first_name = $user_profile ['first_name'];
			if (isset ( $user_profile ['middle_name'] )) {
				$middle_name = $user_profile ['middle_name'];
			} else {
				$middle_name = "";
			}
			$last_name = $user_profile ['last_name'];
		} catch ( FacebookApiException $e ) {
			// If the user is logged out, you can have a
			// user ID even though the access token is invalid.
			// In this case, we'll get an exception, so we'll
			// just ask the user to login again here.
			$login_url = $facebook->getLoginUrl();
			echo 'Please <a href="' . $login_url . '">login.</a>';
			error_log ( $e->getType () );
			error_log ( $e->getMessage () );
		}
		
		$table = table_generator ( 
				$facebook_id, 
				$link, 
				$first_name, 
				$middle_name, 
				$last_name, 
				$app_name
		);
		
		$button = new buttons ();
		$button->display ();
	}
} else if ($facebook_id == 0) { // If the user hasn't accepted the permissions
	echo $OUTPUT->heading ( get_string ( 'acountconnect', 'local_facebook' ) );
	
	$params = array (
			'scope' => 'email,publish_actions,user_birthday,user_tagged_places,user_work_history,user_about_me,user_hometown,
			user_actions.books,user_education_history,user_likes,user_friends,user_religion_politics' 
	);
	$loginUrl = $facebook->getLoginUrl ( $params );
	
	echo '<br><center><a href="' . $loginUrl . '"><img src="app/images/login.jpg"width="180" height="30"></a><center>';
} else {
	
	// If he clicked the link button.
	if ($connect != NULL) {
		
		// If the user wants to link an account that was already linked, but was unlinked that means with status 0
		
		$user_inactive = $DB->get_record ( 'facebook_user', array (
				'moodleid' => $USER->id,
				'facebookid' => $facebook_id,
				'status' => 0 
		) );
		
		if ($user_inactive) {
			
			$user_inactive->timemodified = $time;
			$user_inactive->status = '1';
			$user_inactive->lasttimechecked = $time;
			$DB->update_record ( 'facebook_user', $user_inactive );
			echo "<script>location.reload();</script>";
		}  // If the user wants to link a account that was never linked before.
else {
			
			$record = new stdClass ();
			$record->moodleid = $USER->id;
			$record->facebookid = $facebook_id;
			$record->timemodified = $time;
			$record->status = '1';
			$record->lasttimechecked = $time;
			if ($facebook_id != 0) {
				$DB->insert_record ( 'facebook_user', $record );
			}
			echo "<script>location.reload();</script>";
		}
	} else {
		
		echo $OUTPUT->heading ( get_string ( 'acountconnect', 'local_facebook' ) );
		
		echo $OUTPUT->heading ( get_string ( 'connectwith', 'local_facebook' ), 5 );
		// Facebook code to search the user information.
		// We have a user ID, so probably a logged in user.
		// If not, we'll get an exception, which we handle below.
		try {
			$user_profile = $facebook->api ( '' . $facebook_id . '', 'GET' );
			
			$link = $user_profile ['link'];
			$first_name = $user_profile ['first_name'];
			if (isset ( $user_profile ['middle_name'] )) {
				$middle_name = $user_profile ['middle_name'];
			} else {
				$middle_name = "";
			}
			$last_name = $user_profile ['last_name'];
		} catch ( FacebookApiException $e ) {
			// If the user is logged out, you can have a
			// user ID even though the access token is invalid.
			// In this case, we'll get an exception, so we'll
			// just ask the user to login again here.
			$login_url = $facebook->getLoginUrl ();
			echo 'Please <a href="' . $login_url . '">login.</a>';
			error_log ( $e->getType () );
			error_log ( $e->getMessage () );
		}
		
		$table = table_generator ( $facebook_id, $link, $first_name, $middle_name, $last_name, null );
		// Look if the account was already linked
		$duplicate = $DB->get_record ( 'facebook_user', array (
				'facebookid' => $facebook_id,
				'status' => 1 
		) );
		// if it isn't linked it will return false, if the status is 0 someone already linked it but it is not active.
		
		$button = new connect ( null, array (
				'duplicate' => $duplicate 
		) );
		$button->display ();
	}
}
// if the user has the account linkd it will show his information and some other actions the user can perform.

echo $OUTPUT->footer ();
function table_generator($facebook_id, $link, $first_name, $middle_name, $last_name, $appname) {
	$img = '<img src="https://graph.facebook.com/' . $facebook_id . '/picture?type=large">';
	$table2 = new html_table ();
	$table = new html_table ();
	$table->data [] = array (
			'',
			'' 
	);
	$table->data [] = array (
			get_string ( 'fbktablename', 'local_facebook' ),
			$first_name 
	);
	$table->data [] = array (
			'',
			'' 
	);
	$table->data [] = array (
			get_string ( 'fbktablelastname', 'local_facebook' ),
			$middle_name . ' ' . $last_name 
	);
	$table->data [] = array (
			'',
			'' 
	);
	$table->data [] = array (
			get_string ( 'profile', 'local_facebook' ),
			'<a href="' . $link . '" target=”_blank”>' . $link . '</a>' 
	);
	if ($appname != null) {
		$table->data [] = array (
				'Link a la app',
				'<a href="http://apps.facebook.com/' . $appname . '" target=”_blank”>http://apps.facebook.com/' . $appname . '</a>' 
		);
	} else {
		$table->data [] = array (
				'',
				'' 
		);
	}
	$table2->data [] = array (
			'<img src="https://graph.facebook.com/' .$facebook_id . '/picture?type=large">',
			html_writer::table ( $table ) 
	);
	echo html_writer::table ( $table2 );
}