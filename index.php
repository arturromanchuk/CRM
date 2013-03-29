<?php
/*
	,o888888o.    8 888888888o.            ,8.       ,8.          `8.`8888.      ,8' 
   8888     `88.  8 8888    `88.          ,888.     ,888.          `8.`8888.    ,8'  
,8 8888       `8. 8 8888     `88         .`8888.   .`8888.          `8.`8888.  ,8'   
88 8888           8 8888     ,88        ,8.`8888. ,8.`8888.          `8.`8888.,8'    
88 8888           8 8888.   ,88'       ,8'8.`8888,8^8.`8888.          `8.`88888'     
88 8888           8 888888888P'       ,8' `8.`8888' `8.`8888.         .88.`8888.     
88 8888           8 8888`8b          ,8'   `8.`88'   `8.`8888.       .8'`8.`8888.    
`8 8888       .8' 8 8888 `8b.       ,8'     `8.`'     `8.`8888.     .8'  `8.`8888.   
   8888     ,88'  8 8888   `8b.    ,8'       `8        `8.`8888.   .8'    `8.`8888.  
	`8888888P'    8 8888     `88. ,8'         `         `8.`8888. .8'      `8.`8888. 

Copyright (c) 2013 Xavi Esteve (MIT License)

Permission is hereby granted, free of charge, to any person obtaining a copy of this software and associated documentation files (the "Software"), to deal in the Software without restriction, including without limitation the rights to use, copy, modify, merge, publish, distribute, sublicense, and/or sell copies of the Software, and to permit persons to whom the Software is furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.

*/
error_reporting(E_NONE);



require_once('limonade/limonade.php');
require_once('limonade/limonade/lemons/lemon_mysql.php');
require_once('config.php');


/********************************************************
 * CONFIG
 *******************************************************/

option('debug', false);




$c = mysqli_connect(MYSQL_SERVER, MYSQL_USER, MYSQL_PASS, MYSQL_DATABASE);




/********************************************************
 * ROUTERS
 *******************************************************/

dispatch('/', 'home');
dispatch('/login/:pass', 'login');
dispatch('/login/:pass/:response', 'login'); // for API usage: /login/<passwordhere>/0
dispatch('/logout', 'logout');
dispatch('/search/:q', 'search');
dispatch('/get/:detail', 'get');


dispatch_post('/save', 'save');
dispatch_post('/comment', 'comment');
dispatch_delete('/delete/:id', 'delete');



dispatch('/api/:pass/:action/:detail', 'api');




/********************************************************
 * MODELS
 *******************************************************/


/**
 * login
 * Authenticate user
 * @params (string) User's password
 * @params (bool) Set as false to avoid redirect to the home page
 */
function login($pass, $redirect = true) {
	global $users, $form, $c;

	foreach ($users as $key => $user) {
		if ($pass==$user['pass'] && $form[$user['dbprefix']]) { // pass correct & environment exists
			$_SESSION['name'] = $user['name'];
			$_SESSION['level'] = $user['level'];
			$_SESSION['dbprefix'] = $user['dbprefix'];
			if (isset($user['sitename'])) {
				$_SESSION['sitename'] = $user['sitename'];
			}
			$_SESSION['id'] = $key;

			$q = 'CREATE TABLE IF NOT EXISTS `'.$user['dbprefix'].'people` (
				`id` int(20) NOT NULL AUTO_INCREMENT,
				`name` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
				`form` text COLLATE utf8_unicode_ci,
				`comments` text COLLATE utf8_unicode_ci,
				`created` int(11),
				`updated` int(11),
				PRIMARY KEY (`id`)
			) ENGINE=MyISAM  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;';

			$result = db($q, $c);

			if ($redirect==true) {
				header("Location: ".url_for('/'));
			}else{
				return true;
			}
		}
	}
	return false;
}






/**
 * logout
 * Close user's session
 * @params () 
 */
function logout() {
	session_destroy();
	header("Location: ".url_for(''));
	die();
}







/**
 * home
 * Load the home page as HTML
 */
function home() {

	if (!isset($_SESSION['level']) OR strpos($_SESSION['level'], 'r')===-1) {
		die('<!doctype html><html><body style="font-family: \'Helvetica Neue\',Helvetica,Arial,sans-serif;"><h1 style="font-weight:100;">You are not logged in</h1></body></html>');
	}else{
		global $form, $plugins;
		set('sitename', (isset($_SESSION['sitename'])) ? set('sitename', $_SESSION['sitename']) : set('sitename', SITE_NAME) );
		set('app_version', SITE_VERSION);
		set('username', $_SESSION['name']);
		set('form', json_encode($form[$_SESSION['dbprefix']]));


		set('people', search());
		set('plugins', json_encode($plugins));
		return html('home.html.php');
	}
}





/**
 * api
 * Authenticates and executes a request all-in-one
 * @params (string) User's password
 * @params (string) Action
 * @params (string) Details of the action
 */
function api ($pass, $action, $detail) {
	if (!login($pass, false)) {
		return json(array('status'=>'error','message'=>"API Authentication failed"));
	}

	if ($action === 'get') {
		return get($detail);
	}
}







/**
 * search
 * Search people
 * @params (string) Pass a string to search or empty for all results 
 */
function search($s='') {
	if (!isset($_SESSION['level']) OR strpos($_SESSION['level'], 'r')===-1) {
		$response = array('status'=>'error','message'=>"Your user cannot search contacts");
	}else{
		global $c;
		// Search multiple words
		$w = explode(' ', $s);
		foreach ($w as &$wi) {
			$wi = "(name LIKE  '%".$c->real_escape_string($wi)."%' OR form LIKE  '%".$c->real_escape_string($wi)."%' OR comments LIKE  '%".$c->real_escape_string($wi)."%') ";
		}
		$q = "SELECT id, name, form FROM ".$_SESSION['dbprefix']."people WHERE ". implode(' AND ', $w) ."  ORDER BY name ASC LIMIT 0, 50";
		$people = db($q, $c);

		foreach($people as &$person) {
			$person['form'] = json_decode($person['form'], true);
		}
//var_dump($people);
		if ($people) {
			return json($people);
		}else{
			if ($q) {
				return json(array('status'=>'error','message'=>'No results'));
			}
		}
	}
}








/**
 * get
 * Get person details
 * @params (integer/string) Pass either person's ID or a search with just one result
 */
function get($detail) {
	if (!isset($_SESSION['level']) OR strpos($_SESSION['level'], 'r')===-1) {
		$response = array('status'=>'error','message'=>"Your user cannot read");
	}else{
		global $c;
		if (is_numeric($detail)) {
			$people = db("SELECT * FROM ".$_SESSION['dbprefix']."people WHERE id = ".$c->real_escape_string($detail)." LIMIT 1", $c);
		}else{
			$people = db("SELECT * FROM ".$_SESSION['dbprefix']."people WHERE name LIKE '%".$c->real_escape_string($detail)."%' OR form LIKE '%".$c->real_escape_string($detail)."%' ORDER BY updated DESC LIMIT 1", $c);
		}
		if ($people) {
			$people = $people[0];
			$people['form'] = json_decode($people['form'], true); // decode to later encode but no other way :( 
			$people['comments'] = json_decode($people['comments'], true); // decode to later encode but no other way :( 
			$response = ($people);
		}else{
			$response = array('status'=>'error','message'=>'User does not exist');
		}
	}
	return json($response);
}











/**
 * save
 * Save person details
 */
function save() {
	// Check if user has enough level to Save
	if (!isset($_SESSION['level']) OR strpos($_SESSION['level'], 's')===-1) {
		$response = json(array('status'=>'error','message'=>'Your user cannot save'));

	// User can Save
	}else if (!isset($_POST['name']) OR $_POST['name']=='') {
		$response = array('status'=>'error','message'=>'Please write a name for the contact');
	}else{

		global $form, $c;
		$array = array(
			'title' => $_POST['title']
		);

		foreach ($form[$_SESSION['dbprefix']] as $field) {
			$array[$field['name']] = $_POST[$field['name']];
		}
//var_dump($array);
		if ($_POST['id']) { // update details
			$q = "UPDATE ".$_SESSION['dbprefix']."people SET 
				form = '".$c->real_escape_string(json_encode($array))."',
				name =  '".$c->real_escape_string($_POST['name'])."',
				`updated` =  '".time()."' WHERE  id = ".($_POST['id']).";";
		}else{ // create new
			$q = "INSERT INTO ".$_SESSION['dbprefix']."people VALUES (
				NULL, 
				'".$c->real_escape_string($_POST['name'])."', 
				'".$c->real_escape_string(json_encode($array))."',  
				'{}',
				'".time()."',  
				'".time()."' 
			);";
		}
//var_dump($q);
		$result = db($q, $c);

		if ($result) {
			if ($_POST['id']) {
				$response = array('status'=>'success','message'=>'Contact details saved');
			}else{
				// Get the ID
				$q = "SELECT id from ".$_SESSION['dbprefix']."people ORDER BY id DESC LIMIT 1";
				$id = db($q, $c);
				$response = json(array('id'=>$id[0]['id'],'status'=>'success','message'=>'New contact created'));
			}
		}else{
			$response = json(array('status'=>'error','message'=>'Could not save contact details'));
		}
	}
	return $response;
}









/**
 * delete
 * Delete person
 * @params (integer) Person ID 
 */
function delete($id) {
	if (!isset($_SESSION['level']) OR strpos($_SESSION['level'], 'd')===-1) {
		$response = json(array('status'=>'error','message'=>"Your user cannot delete"));
	}else{
		global $c;
		$deletion = db("DELETE FROM ".$_SESSION['dbprefix']."people WHERE id = ".$c->real_escape_string($id)."", $c);

		if ($deletion) {
			$response = json(array('status'=>'success','message'=>'Contact deleted'));
		}else{
			$response = json(array('status'=>'error','message'=>'Contact could not be deleted'));
		}
	}
	return $response;
}










/********************************************************
 * COMMENTS
 *******************************************************/

/**
 * comment
 * Add a comment to the person
 * @params (integer) Person ID 
 */
function comment($id) {
	// Check if user has enough level to Comment
	if (!isset($_SESSION['level']) OR strpos($_SESSION['level'], 'c')===-1) {
		$response = json(array('status'=>'error','message'=>'Your user cannot comment'));
	}else if (!isset($_POST['comment']) OR $_POST['comment']=='') {
		json(array('status'=>'error','message'=>'Please write a comment first'));
	}else{
		global $c;
		$comments = db("SELECT comments FROM ".$_SESSION['dbprefix']."people WHERE id = ".$c->real_escape_string($_POST['id'])."", $c);
		$comments = json_decode($comments[0]['comments'], true);
//var_dump($comments);
		array_unshift($comments, array(
			'user' => $_SESSION['name'],
			'date' => date('c', time()), // iso 8601 format
			'text' => $_POST['comment']
		));
		$q = "UPDATE ".$_SESSION['dbprefix']."people SET comments = '".$c->real_escape_string(json_encode($comments))."' WHERE id = ".$c->real_escape_string($_POST['id'])."";
		$result = db($q, $c);

		if ($result) {
			$response = json($comments);
		}else{
			$response = json(array('status'=>'error','message'=>'Could not add comment'));
		}
	}
	return $response;
}








/********************************************************
 * HELPERS
 *******************************************************/
function generateRandomString($length = 100) {
	$characters = '-_!$0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
	$randomString = '';
	for ($i = 0; $i < $length; $i++) {
		$randomString .= $characters[rand(0, strlen($characters) - 1)];
	}
	return $randomString;
}









/********************************************************
 * LOAD PLUGINS
 *******************************************************/
foreach ($plugins as $plugin) {
	if (file_exists($plugin)) {
		require_once('plugins/'.$plugin.'/'.$plugin.'.php');
	}
}










// Let the party begin!
run();
