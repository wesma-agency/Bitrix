<?php
// включаем замер исполнения скрипта
$startTime = microtime(true);
// подключаем prolog bitrix
$_SERVER["DOCUMENT_ROOT"] = realpath(dirname(__FILE__));
require $_SERVER["DOCUMENT_ROOT"] . '/bitrix/modules/main/include/prolog_before.php';
// подключаем нужные модули
CModule::IncludeModule("iblock");
CModule::IncludeModule("catalog");
CModule::IncludeModule("sales");

$updated_items = $skip_items = 0;

if ( isset($argv[1]) && $argv[1] == 'run' ) {

	$IBLOCK_ID = settings::$catalogSkuIblockId;
	$OVERWRITE = isset($argv[2]) ? $argv[2] : 0;

	$arFilter = array(
		'IBLOCK_ID' => $IBLOCK_ID
	);
	$arSelect = array('ID', 'PROPERTY_COLOR', 'PROPERTY_SIZE', 'PROPERTY_CML2_LINK', 'PROPERTY_MORE_PHOTO', 'PROPERTY_KONTEYNER', 'PREVIEW_PICTURE', 'DETAIL_PICTURE');

	$items = settings::getIbElements($arFilter, $arSelect, false, false, false, true);

	$structuredItems = array();

	if ($items) {
		foreach ($items as $item) {
			if ( isset($item['PROPERTY_COLOR_ENUM_ID']) && intval($item['PROPERTY_COLOR_ENUM_ID']) > 0 
				&& isset($item['PROPERTY_SIZE_ENUM_ID']) && intval($item['PROPERTY_SIZE_ENUM_ID']) > 0 
				&& isset($item['PROPERTY_CML2_LINK_VALUE']) && intval($item['PROPERTY_CML2_LINK_VALUE']) > 0
				&& isset($item['PROPERTY_KONTEYNER_VALUE']) && !empty($item['PROPERTY_KONTEYNER_VALUE']) )
				$structuredItems[$item['PROPERTY_CML2_LINK_VALUE']][$item['PROPERTY_KONTEYNER_VALUE']][$item['PROPERTY_COLOR_ENUM_ID']][$item['PROPERTY_SIZE_VALUE']] = $item;
		}
	}
	if ($structuredItems) {
		foreach ($structuredItems as $item) {
			foreach ($item as $tp_N) {
				foreach ($tp_N as $sizes) {
					if ($sizes) {
						ksort($sizes);
						$firstSize = array_shift($sizes);
						$isImages = (isset($firstSize['PROPERTY_MORE_PHOTO_VALUE']) && is_array($firstSize['PROPERTY_MORE_PHOTO_VALUE']) && count($firstSize['PROPERTY_MORE_PHOTO_VALUE']) > 0) || intval($firstSize['PREVIEW_PICTURE']) || intval($firstSize['DETAIL_PICTURE']);

						if ($sizes && $isImages) {
							$PREVIEW_PICTURE = $DETAIL_PICTURE = $MORE_PHOTO = array();

							if (intval($firstSize['PREVIEW_PICTURE'])) {
								$PREVIEW_PICTURE = CFile::MakeFileArray($firstSize["PREVIEW_PICTURE"]);
							}

							if (intval($firstSize['DETAIL_PICTURE'])) {
								$DETAIL_PICTURE = CFile::MakeFileArray($firstSize["DETAIL_PICTURE"]);
							}

							if (isset($firstSize['PROPERTY_MORE_PHOTO_VALUE']) && is_array($firstSize['PROPERTY_MORE_PHOTO_VALUE']) && count($firstSize['PROPERTY_MORE_PHOTO_VALUE']) > 0) {
								foreach ($firstSize['PROPERTY_MORE_PHOTO_VALUE'] as $imageID) {
									$MORE_PHOTO[] = CFile::MakeFileArray($imageID);
								}								
							}

							foreach ($sizes as $size) {
								$el = new CIBlockElement;
								$isPREVIEW_PICTURE = intval($size['PREVIEW_PICTURE']);
								$isDETAIL_PICTURE = intval($size['DETAIL_PICTURE']);
								$isMORE_PHOTO = isset($size['PROPERTY_MORE_PHOTO_VALUE']) && is_array($size['PROPERTY_MORE_PHOTO_VALUE']) && count($size['PROPERTY_MORE_PHOTO_VALUE']) > 0;
								$UPDATE_ITEM = $UPDATE_PROPS = array();

								if ( (($isPREVIEW_PICTURE && $OVERWRITE) || !$isPREVIEW_PICTURE) && $PREVIEW_PICTURE )
									$UPDATE_ITEM['PREVIEW_PICTURE'] = $PREVIEW_PICTURE;
								
								if ( (($isDETAIL_PICTURE && $OVERWRITE) || !$isDETAIL_PICTURE) && $DETAIL_PICTURE )
									$UPDATE_ITEM['DETAIL_PICTURE'] = $DETAIL_PICTURE;
								
								if ( (($isMORE_PHOTO && $OVERWRITE) || !$isMORE_PHOTO) && $MORE_PHOTO )
									$UPDATE_PROPS['MORE_PHOTO'] = $MORE_PHOTO;

								if ($UPDATE_ITEM) {									
									if (!$el->Update($size['ID'], $UPDATE_ITEM))
										echo $el->LAST_ERROR . PHP_EOL;
								}

								if ($UPDATE_PROPS) {
									$el->SetPropertyValuesEx($size['ID'], $IBLOCK_ID, $UPDATE_PROPS);
								}

								if ($UPDATE_ITEM || $UPDATE_PROPS)
									$updated_items++;
								else
									$skip_items++;
							}

							

							// if ($isPREVIEW_PICTURE &&)

							// settings::debugData($MORE_PHOTO);
							// die();

						}

					}
				}
			}
		}
	}

}

$endTime = microtime(true);
echo 'UPDATED ITEMS: ' . $updated_items . PHP_EOL;
echo 'SKIP ITEMS: ' . $skip_items . PHP_EOL;
echo "\nStandby script time: " . ($endTime - $startTime) . " Sek.\n"; //вывод результата