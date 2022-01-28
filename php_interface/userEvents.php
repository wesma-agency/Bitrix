<?php
class userEvents{

	/**
	 * срабатывает после авторизации пользователя
	 * @param $id
	 * @param $arFields
	 */
	public static function OnAfterUserLoginHandler(&$fields){
		
		if( $fields['USER_ID'] <= 0 ) return;

		$rsUser = CUser::GetByID($fields['USER_ID']);
	    $arUser = $rsUser->Fetch();
	    $arElements = unserialize($arUser['UF_FAVORITES']);
	    $_SESSION['CATALOG_FAVORITES_LIST'] = $arElements;
		
		// define("LOG_FILENAME", $_SERVER["DOCUMENT_ROOT"]."/log/log.txt");
		// AddMessage2Log(print_r($GIFTS_IDS,true), "php_init");
	}

}