<?if(!defined('B_PROLOG_INCLUDED')||B_PROLOG_INCLUDED!==true)die();
use Bitrix\Main\Mail\Event;
use Bitrix\Main\EventManager;
$eventManager   =   \Bitrix\Main\EventManager::getInstance();

if(file_exists($_SERVER['DOCUMENT_ROOT'].'/local/php_interface/prolog/eventsProlog.php')){
	require_once($_SERVER['DOCUMENT_ROOT'].'/local/php_interface/prolog/eventsProlog.php');
	$eventManager->AddEventHandler("main", "OnBeforeProlog", ['eventsProlog','OnBeforePrologHandler']);
	$eventManager->AddEventHandler("main", "OnEndBufferContent", ['eventsProlog','OnEndBufferContentHandler']);
}

if(file_exists($_SERVER['DOCUMENT_ROOT'].'/local/php_interface/epilog/eventsEpilog.php')){
	require_once($_SERVER['DOCUMENT_ROOT'].'/local/php_interface/epilog/eventsEpilog.php');
	$eventManager->AddEventHandler("main", "OnEpilog", ['eventsEpilog','OnEpilogHandler']);
}

if(file_exists($_SERVER['DOCUMENT_ROOT'].'/local/php_interface/userEvents.php')){
	require_once($_SERVER['DOCUMENT_ROOT'].'/local/php_interface/userEvents.php');
	$eventManager->addEventHandler('main','OnAfterUserLogin',['userEvents','OnAfterUserLoginHandler']);
}

if(file_exists($_SERVER['DOCUMENT_ROOT'].'/local/php_interface/ibProductReviews/ibProductReviewsUpdateElement.php')){
	require_once($_SERVER['DOCUMENT_ROOT'].'/local/php_interface/ibProductReviews/ibProductReviewsUpdateElement.php');
	$eventManager->addEventHandler('iblock','OnBeforeIBlockElementUpdate',['ibProductReviewsUpdateElement','UpdateElement']);
	$eventManager->addEventHandler('iblock','OnBeforeIBlockElementDelete',['ibProductReviewsUpdateElement','DeleteElement']);
}

if(file_exists($_SERVER['DOCUMENT_ROOT'].'/local/php_interface/searchCatalog/catalogProductIndexer.php')){
	require_once($_SERVER['DOCUMENT_ROOT'].'/local/php_interface/searchCatalog/catalogProductIndexer.php');
	$eventManager->addEventHandler('search', 'BeforeIndex', ['CatalogProductIndexer','BeforeIndexHandler']);
}

if(file_exists($_SERVER['DOCUMENT_ROOT'].'/local/php_interface/import1C/customImport1CHandler.php')){
	require_once($_SERVER['DOCUMENT_ROOT'].'/local/php_interface/import1C/customImport1CHandler.php');
	$eventManager->addEventHandler('catalog', 'OnSuccessCatalogImport1C', ['customImport1C','importCatalog']);
}