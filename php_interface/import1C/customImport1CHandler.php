<?php
class customImport1C{

	public static function importCatalog($arParams, $arFields){
		$rootPath = \Bitrix\Main\Application::getDocumentRoot();
		if (!empty($arFields)) {
			$file = new \Bitrix\Main\IO\File($arFields);
			if ($file->isExists()) {
				$fileName = $file->getName();
				$content = $file->getContents();
				$newFile = new \Bitrix\Main\IO\File($rootPath . '/1c_temp/' . $fileName);
				$newFile->putContents($content);
			}
		}
	}

}