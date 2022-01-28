<?php

class ibProductReviewsUpdateElement{

	/**
	 * 
	 * @return array
	 */
	public static function UpdateElement($arFields){
		if ($arFields['IBLOCK_ID'] == settings::$catalogReviewsIblockId) {
			// define("LOG_FILENAME", $_SERVER["DOCUMENT_ROOT"]."/log/logProductReview.txt");
			$AFTER_ACTIVE = $arFields['ACTIVE'];
			$arSelect = array("ID", "ACTIVE", "PROPERTY_VOTE_REVIEW", "PROPERTY_PRODUCT_ID_REVIEW");
			$arFilter = array(
				"ID" => $arFields['ID'],
				"IBLOCK_ID" => $arFields['IBLOCK_ID'],
			);
			$rsElements = CIBlockElement::GetList(array('SORT'=>'ASC'), $arFilter, false, false, $arSelect);

			if($arElement = $rsElements->GetNext()) {
				$PRODUCT_ID = (int)$arElement['PROPERTY_PRODUCT_ID_REVIEW_VALUE'];
				$RATE = (int)$arElement['PROPERTY_VOTE_REVIEW_VALUE'];
				$BEFORE_ACTIVE = $arElement['ACTIVE'];

				if($PRODUCT_ID && $BEFORE_ACTIVE != $AFTER_ACTIVE) {
					$arSelect = array("ID", "IBLOCK_ID", "PROPERTY_VOTE_COUNT", "PROPERTY_VOTE_SUM", "PROPERTY_RATING");
					$arFilter = array(
						"ID" => $PRODUCT_ID,
						"IBLOCK_ID" => settings::$catalogIblockId,
					);
					$rsProduct = CIBlockElement::GetList(array('SORT'=>'ASC'), $arFilter, false, false, $arSelect);

					if ($arProduct = $rsProduct->GetNext()) {
						switch ($AFTER_ACTIVE) {
							case 'Y':
								$nFields["vote_count"] = intval($arProduct["PROPERTY_VOTE_COUNT_VALUE"]) + 1;
								$nFields["vote_sum"] = intval($arProduct["PROPERTY_VOTE_SUM_VALUE"]) + $RATE;
								$nFields["rating"] = round(($nFields["vote_sum"]+31.25/25)/($nFields["vote_count"]+10),2);
								CIBlockElement::SetPropertyValuesEx($PRODUCT_ID, $arProduct["IBLOCK_ID"], array(
									"vote_count" => array(
										"VALUE" => $nFields["vote_count"] < 0 ? 0 : $nFields["vote_count"],
									),
									"vote_sum" => array(
										"VALUE" => $nFields["vote_sum"] < 0 ? 0 : $nFields["vote_sum"],
									),
									"rating" => array(
										"VALUE" => $nFields["rating"] < 0 ? 0 : $nFields["rating"],
									),
								));
								break;
							
							case 'N':
								$nFields["vote_count"] = intval($arProduct["PROPERTY_VOTE_COUNT_VALUE"]) - 1;
								$nFields["vote_sum"] = intval($arProduct["PROPERTY_VOTE_SUM_VALUE"]) - $RATE;
								$nFields["rating"] = round(($nFields["vote_sum"]+31.25/25)/($nFields["vote_count"]+10),2);
								CIBlockElement::SetPropertyValuesEx($PRODUCT_ID, $arProduct["IBLOCK_ID"], array(
									"vote_count" => array(
										"VALUE" => $nFields["vote_count"] < 0 ? 0 : $nFields["vote_count"],
									),
									"vote_sum" => array(
										"VALUE" => $nFields["vote_sum"] < 0 ? 0 : $nFields["vote_sum"],
									),
									"rating" => array(
										"VALUE" => $nFields["rating"] < 0 ? 0 : $nFields["rating"],
									),
								));
								break;
						}
					}
				}
				// AddMessage2Log(print_r($arFields,true), "UpdateElementAction");
			}
		}
	}

	/**
	 * 
	 * @return array
	 */
	public static function DeleteElement($ID){
		if ($ID) {
			$arSelect = array("ID", "IBLOCK_ID", "ACTIVE", "PROPERTY_VOTE_REVIEW", "PROPERTY_PRODUCT_ID_REVIEW");
			$arFilter = array(
				"ID" => $ID,
			);
			$rsElements = CIBlockElement::GetList(array('SORT'=>'ASC'), $arFilter, false, false, $arSelect);

			if($arElement = $rsElements->GetNext()) {
				if ($arElement['IBLOCK_ID'] == settings::$catalogReviewsIblockId && $arElement['ACTIVE'] == 'Y') {
					$PRODUCT_ID = (int)$arElement['PROPERTY_PRODUCT_ID_REVIEW_VALUE'];
					$RATE = (int)$arElement['PROPERTY_VOTE_REVIEW_VALUE'];

					if($PRODUCT_ID) {
						$arSelect = array("ID", "IBLOCK_ID", "PROPERTY_VOTE_COUNT", "PROPERTY_VOTE_SUM", "PROPERTY_RATING");
						$arFilter = array(
							"ID" => $PRODUCT_ID,
							"IBLOCK_ID" => settings::$catalogIblockId,
						);
						$rsProduct = CIBlockElement::GetList(array('SORT'=>'ASC'), $arFilter, false, false, $arSelect);

						if ($arProduct = $rsProduct->GetNext()) {

							$nFields["vote_count"] = intval($arProduct["PROPERTY_VOTE_COUNT_VALUE"]) - 1;
							$nFields["vote_sum"] = intval($arProduct["PROPERTY_VOTE_SUM_VALUE"]) - $RATE;
							$nFields["rating"] = round(($nFields["vote_sum"]+31.25/25)/($nFields["vote_count"]+10),2);
							CIBlockElement::SetPropertyValuesEx($PRODUCT_ID, $arProduct["IBLOCK_ID"], array(
								"vote_count" => array(
									"VALUE" => $nFields["vote_count"] < 0 ? 0 : $nFields["vote_count"],
								),
								"vote_sum" => array(
									"VALUE" => $nFields["vote_sum"] < 0 ? 0 : $nFields["vote_sum"],
								),
								"rating" => array(
									"VALUE" => $nFields["rating"] < 0 ? 0 : $nFields["rating"],
								),
							));

						}
					}
				}
			}
		}
	}

}