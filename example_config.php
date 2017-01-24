<?php
// -----==========----- //
// Type: Configuration File
// Filename: config.php
// Author: Matthew Heinsohn
// -----==========----- //
// ----- Autoloader ----- //
spl_autoload_register('AutoLoader');
function AutoLoader($className){
    $file = str_replace('\\',DIRECTORY_SEPARATOR,$className);
    require_once(__DIR__.DIRECTORY_SEPARATOR . $file . '.php'); 
}
// -----==========----- //
//MySQL Database Connection Creds
define('DB_HOST', '');
define('DB_USER', '');
define('DB_PASS', '');
define('DB_NAME', '');

//USPS API Key
define('USPS_API_KEY', '');

//Zipcode API Key
define('ZIPCODEAPI_KEY', '');
?>
