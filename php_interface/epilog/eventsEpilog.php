<?php
class eventsEpilog{

	public static function OnEpilogHandler(){
		global $APPLICATION;
		$request = \Bitrix\Main\Context::getCurrent()->getRequest();

		if ($request->get('PAGEN_1') !== null) {
			$pageNumb = intval($request->get('PAGEN_1'));
			$cTitle = $APPLICATION->GetTitle();
			$cDescription = $APPLICATION->GetPageProperty('description');
			$pageProps = $APPLICATION->GetPagePropertyList();
			$cTitle = !isset($pageProps['TITLE']) || (isset($pageProps['TITLE']) && empty($pageProps['TITLE'])) ? $cTitle : $pageProps['TITLE'];
			$cDescription = !isset($pageProps['DESCRIPTION']) || (isset($pageProps['DESCRIPTION']) && empty($pageProps['DESCRIPTION'])) ? $cDescription : $pageProps['DESCRIPTION'];

			$nTitle = !empty($cTitle) ? 'Страница ' . $pageNumb . ' - ' . $cTitle : '';
			$nDescription = !empty($cDescription) ? 'Страница ' . $pageNumb . ' - ' . $cDescription : '';
			
			if (!empty($nTitle))
				$APPLICATION->SetPageProperty('title', $nTitle);
			if (!empty($nDescription))
				$APPLICATION->SetPageProperty('description', $nDescription);
		}
	}

}