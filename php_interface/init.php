<?php

if(file_exists($_SERVER['DOCUMENT_ROOT'].'/local/php_interface/settings.php')){
    require_once($_SERVER['DOCUMENT_ROOT'].'/local/php_interface/settings.php');
}

if(file_exists($_SERVER['DOCUMENT_ROOT'].'/local/php_interface/events.php')){
	require_once($_SERVER['DOCUMENT_ROOT'].'/local/php_interface/events.php');
}