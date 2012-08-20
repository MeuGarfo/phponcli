<?php

// Set the error reporting level 
error_reporting(E_ALL);

// Require libraries
include dirname(__FILE__) . '/../src/PHPonCLI.class.php';
include dirname(__FILE__) . '/../src/PHPonCLI_UtilPack.php';
include dirname(__FILE__) . '/mycli.class.php';

// CLI mode
if (PHP_SAPI == 'cli') {
	$cli = new MyCLI();
	PHPonCLI_UtilPack::install($cli);
	$cli->setDecorator(PHPonCLI::DECORATION_AINSI);
	$cli->exec($GLOBALS['argv']);
}

// HTML mode
else {
	include 'html-demo.php';
}

?>