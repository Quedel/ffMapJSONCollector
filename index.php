<?php

//for CLI: Set current dir to script dir
chdir(dirname(__FILE__));

require_once "ffMapJSONCollector.php";

$collector = new ffMapJSONCollector();

// set Content Type to application/json
header('Content-Type: application/json');

// Generate the JSON Files
$status = $collector->execute();

echo $status;
?>