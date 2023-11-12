<?php

require_once __DIR__ . '/vendor/autoload.php';


$client = new MongoDB\Client('mongodb://127.0.0.1:27017');
$db = $client->test;

$cursor = $db->command(['ping' => 1]);
var_dump($cursor);
