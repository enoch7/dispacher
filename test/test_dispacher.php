<?php
require_once __DIR__ . "/../src/Dispacher.php";
$config = require_once(__DIR__ . '/../config/config.php');

$dispacher = new Dispacher($config);

$key = '20180110';
$num = $dispacher->getSequence($key);
echo $num . "\n";