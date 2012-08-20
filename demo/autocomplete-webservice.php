<?php

// Response content type
header('Content-type: application/json');

// Check query parameter
if (!isset($_REQUEST['q'])) {
	echo '[]';
	exit;
}

// Set the error reporting level 
error_reporting(E_ALL);

// Require libraries
include dirname(__FILE__) . '/../src/PHPonCLI.class.php';
include dirname(__FILE__) . '/mycli.class.php';

// Create CLI api
$cli = new MyCLI();

// Display as JSON
echo json_encode($cli->autocomplete($_REQUEST['q']));

?>