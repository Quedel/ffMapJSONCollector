<?php

// Report all errors except E_NOTICE
error_reporting(E_ALL ^ E_NOTICE);

require_once "ffMapJSONCollector.php";

$collector = new ffMapJSONCollector();

// set Content Type to application/json
header('Content-Type: application/json');

// Generate the JSON Files
$status = $collector->execute();

echo $status;
?>