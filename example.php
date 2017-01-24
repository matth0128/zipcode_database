#!/usr/bin/php
<?php
//Load Configuration File
require_once('config.php');

//Load zipCodeUtilities() Class
$zipCodeUtilities = new classes\zipCodeUtilities();

$res = $zipCodeUtilities->zipcodes_by_radius('78610', '20');

echo(print_r($res,1)); 

die;
?>
