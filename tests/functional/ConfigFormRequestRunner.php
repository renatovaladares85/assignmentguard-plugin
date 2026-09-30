<?php

if ($argc !== 5) {
    exit(64);
}

$glpiRoot = realpath($argv[1]);
$post = json_decode(base64_decode($argv[4], true), true);

if ($glpiRoot === false || !is_array($post)) {
    exit(65);
}

$_SERVER['REQUEST_METHOD'] = 'POST';
$_SERVER['REQUEST_URI'] = '/plugins/assignmentguard/front/config.form.php';
$_SERVER['SCRIPT_NAME'] = '/plugins/assignmentguard/front/config.form.php';
$_SERVER['PHP_SELF'] = $_SERVER['SCRIPT_NAME'];
$_SERVER['HTTP_HOST'] = 'localhost';
$_SERVER['SERVER_PORT'] = '80';
$_SERVER['HTTP_REFERER'] = '/plugins/assignmentguard/front/config.form.php';
$_POST = $post;
$_REQUEST = $post;

define('GLPI_USE_CSRF_CHECK', true);
session_name($argv[3]);
session_id($argv[2]);
session_start();

$configForm = $glpiRoot . '/plugins/assignmentguard/front/config.form.php';
chdir(dirname($configForm));

require $configForm;
