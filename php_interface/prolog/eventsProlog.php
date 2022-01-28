<?php
use Bitrix\Sale;
class eventsProlog{

	public static $USER_AUTH = array(
		'NEED_CONTROL' 	=> false,
		'IS_AUTH'		=> false,
		'U_NAME'		=> '',
		'DEFAULT_TEXT'	=> '<p>Для просмотра данного раздела необходимо <a class="animate-link getPopupFormFile" data-form-type="LOGIN" data-wf-class="popup-general" data-wf-dopclass="--entry" data-wf-file-path="/local/include/ajax/auth/auth.php" href="javascript:void(0);">авторизоваться</a>!</p>'
	);

	/**
	 * Событие "OnBeforeProlog" вызывается в выполняемой части пролога сайта (после события OnPageStart).
	 */
	public static function OnBeforePrologHandler(){
		global $APPLICATION,
			   $USER;
		$curUrl = $APPLICATION->GetCurUri();
		$needAuthControl = strpos($curUrl, '/cart/') !== false || strpos($curUrl, '/catalog/') !== false || strpos($curUrl, '/order/') !== false;

		self::$USER_AUTH['NEED_CONTROL'] = $needAuthControl;
		self::$USER_AUTH['PAGE_404'] = defined('ERROR_404') && ERROR_404 == 'Y';
		if ($USER->IsAuthorized()) {
			self::$USER_AUTH['IS_AUTH'] = true;
			self::$USER_AUTH['U_NAME'] = trim($USER->GetFullName());
			if (empty(self::$USER_AUTH['U_NAME']))
				self::$USER_AUTH['U_NAME'] = trim($USER->GetLogin());
		}
	}

	/**
	 * Вызывается при выводе буферизированного контента.
	 */
	public static function OnEndBufferContentHandler(&$content){
		global $APPLICATION;

		$curUrl = $APPLICATION->GetCurUri();
		$skipUpdateMeta = strpos($curUrl, '/bitrix/') === false && strpos($curUrl, '/local/') === false;

		if ($skipUpdateMeta) {
			$findedText = array(
				'meta name="description"',
				'meta name="keywords"'
			);
			$findedTextNew = array(
				'meta itemprop="description" name="description"',
				'meta itemprop="keywords" name="keywords"'
			);
			$content = str_replace($findedText, $findedTextNew, $content);
		}
	}

}