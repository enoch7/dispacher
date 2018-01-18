<?php
require_once __DIR__ . "/../src/Moniter.php";
$config = require_once(__DIR__ . '/../config/config.php');

$moniter = new Moniter($config);
$moniter->start();
