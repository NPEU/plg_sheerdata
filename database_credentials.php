<?php
$public_root_path  = realpath($_SERVER['DOCUMENT_ROOT']) . '/' ;


$hostname = 'localhost';
$username = 'joomla';
$password = 'J00ml@-j311y-j@p3$';

switch ($_SERVER['SERVER_NAME']) {
    case 'dev.npeu.ox.ac.uk' :
        $application_env = 'development';
        $database = 'sheer_dev';
        break;
    case 'test.npeu.ox.ac.uk':
        $application_env = 'testing';
        $database = 'sheer_test';
        break;
    case 'sandbox.npeu.ox.ac.uk':
        $database = 'sheer_sandbox';
        $application_env = 'sandbox';
        break;
    case 'next.npeu.ox.ac.uk':
        $database = 'sheer_next';
        $application_env = 'next';
        break;
    default:
        $application_env = 'production';
        $database = 'sheer';
}

//$application_env = $_SERVER['SERVER_NAME'] == 'dev.npeu.ox.ac.uk' ? 'development' : ($_SERVER['SERVER_NAME'] == 'test.npeu.ox.ac.uk' ? 'testing' : 'production');

if (!defined('DEV')) {
    define('DEV', $application_env == 'development');
}

if (!defined('TEST')) {
    define('TEST', $application_env == 'testing');
}

if (!defined('SANDBOX')) {
    define('SANDBOX', $application_env == 'sandbox');
}

if (!defined('NEXT')) {
    define('NEXT', $application_env == 'next');
}
