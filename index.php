<?php

require 'vendor/autoload.php';
$config = require 'config.php';

$app = new \App\App($config);
$app->init();