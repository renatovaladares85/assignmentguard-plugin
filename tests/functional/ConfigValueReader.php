<?php

if ($argc !== 2) {
    exit(64);
}

$glpiRoot = realpath($argv[1]);

if ($glpiRoot === false) {
    exit(65);
}

$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REQUEST_URI'] = '/plugins/assignmentguard/front/config.form.php';
$_SERVER['SCRIPT_NAME'] = '/plugins/assignmentguard/front/config.form.php';
$_SERVER['PHP_SELF'] = $_SERVER['SCRIPT_NAME'];
$_SERVER['HTTP_HOST'] = 'localhost';
$_SERVER['SERVER_PORT'] = '80';

chdir($glpiRoot);
require $glpiRoot . '/inc/includes.php';

echo json_encode(\GlpiPlugin\Assignmentguard\PluginConfig::getAll());
