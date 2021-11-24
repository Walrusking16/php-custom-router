<?php

session_start();

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/src/Router.php';
require_once __DIR__ . '/src/DB.php';

global $dotenv;

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

$db = new DB();

$api = new Router();

$api->merge(include "aws.php");

$api->run();