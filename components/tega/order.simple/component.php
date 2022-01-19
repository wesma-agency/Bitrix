<?php
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) die();
CModule::IncludeModule('subscribe');
// if (empty($arParams["PERSON_TYPE_ID"])) {
//     ShowError(\Bitrix\Main\Localization\Loc::getMessage("PERSON_TYPE_IS_NOT_SET"));
//     return;
// }
use Bitrix\Main\Context,
    Bitrix\Main\IO,
    Bitrix\Main\Application;

\Bitrix\Main\Loader::includeModule("sale");

global $USER, $APPLICATION;
$arResult = [
    "PRICES" => [],
    "BASKET" => [],
    "DELIVERY" => [],
    "PAY_SYSTEM" => [],
    "ORDER_PROPS" => []
];

// class SaleOrderAjaxCustom extends \CBitrixComponent
// {
//     public function prepareResultArray($orderObj) {
//         $this->executeEvent('OnSaleComponentOrderOneStepProcess', $orderObj);
//     }
// }

$request = Context::getCurrent()->getRequest();
$postData = $request->getPostList()->toArray();
$filesData = $request->getFileList()->toArray();
$filesData = !empty($filesData) ? $filesData[$arParams["FORM_NAME"]] : array();
$filesPaths = !empty($filesData) && !empty($arParams["FILES_ORDER_PROP"]) ? $filesData['tmp_name'][$arParams["FILES_ORDER_PROP"]] : array();
$filesNames = !empty($filesData) && !empty($arParams["FILES_ORDER_PROP"]) ? $filesData['name'][$arParams["FILES_ORDER_PROP"]] : array();
$cropImage = CModule::IncludeModule("millcom.phpthumb");
$UPDATE_ORDER = isset($postData['UPDATE_ORDER']) ? $postData['UPDATE_ORDER'] : 'N';

$addedFiles = $showFiles = array();

if (!empty($arParams["FILES_ORDER_PROP"])) {
    // Temp dir for files with USER_ID
    $rootPath = Application::getDocumentRoot();
    $S_USER_ID = !empty($_SESSION['SALE_USER_ID']) ? $_SESSION['SALE_USER_ID'] : 0;
    $tempFilesDirPath = $rootPath . '/tempFiles/' . $S_USER_ID . '/';
    $tempFilesDir = new IO\Directory($tempFilesDirPath);

    if (!$tempFilesDir->isExists()) {
        $tempFilesDir->create();
    }

    if ($filesPaths) {
        foreach ($filesPaths as $kFile => $filePath) {
            if (empty($filePath)) continue;
            $newPathFile = $tempFilesDirPath . $filesNames[$kFile];
            if (!move_uploaded_file($filePath, $newPathFile)) {
                $arResult["ERRORS"][$arParams["FILES_ORDER_PROP"]][] = 'Ошибка загрузки файла: ' . $filesNames[$kFile];
            } else {
                $prepandFileData = CFile::MakeFileArray($newPathFile);
                $fid = CFile::SaveFile($prepandFileData, "sale/order/properties", false, false, '', false);
                if (intval($fid)>0) {                    
                    $fileObj = new IO\File($newPathFile);
                    $fileExt = $fileObj->getExtension();
                    $fileObj->rename($tempFilesDirPath . '/' . $fid . '.' . $fileExt);
                } else {
                    $arResult["ERRORS"][$arParams["FILES_ORDER_PROP"]][] = 'Ошибка загрузки файла: ' . $filesNames[$kFile];
                }
            }
        }
    }

    if ($request->isPost() && isset($postData['DELETE_FILE']) && !empty($postData['DELETE_FILE'])) {
        $deleteFiles['ORIG'] = $postData['DELETE_FILE'];
        if ($cropImage) {
            $deleteFiles['CROP'] = CMillcomPhpThumb::generateImg($postData['DELETE_FILE'], 3);
        }
        foreach ($deleteFiles as $k_file => $d_file) {
            $delFileObj = new IO\File($rootPath . $d_file);
            $F_ID = intval($delFileObj->getName());
            if ($F_ID > 0 && $k_file == 'ORIG') {
                CFile::Delete($F_ID);
            }
            $delFileObj->delete();
            
        }
        
    }

    $addedFiles = $tempFilesDir->getChildren();
}

if (count($addedFiles) > 0) {
    $showFiles = array();
    foreach ($addedFiles as $file) {
        $fileDirPath = str_replace($rootPath, '', $file->getDirectoryName()) . '/';
        $fileName    = $file->getName();
        $link        = $cropImage ? CMillcomPhpThumb::generateImg($fileDirPath.$fileName, 3) : $fileDirPath.$fileName;
        $showFiles[] = array(
            'NAME' => $fileName,
            'C_LINK' => $link,
            'O_LINK' => $fileDirPath.$fileName
        );
    }
}

$arResult['SHOW_FILES'] = $showFiles;
$arResult["VALIDATE_PASSWORD"] = '';

// if (!$USER->IsAuthorized()) {
//     if (!empty($arParams["ANONYMOUS_USER_ID"])) {
//         $USER_ID = intval($arParams["ANONYMOUS_USER_ID"]);
//     }
// } else {
//     $USER_ID = (new CUser)->GetID();
// }

// if (empty($arResult["USER"])) {
//     $APPLICATION->AuthForm(\Bitrix\Main\Localization\Loc::getMessage("SALE_ACCESS_DENIED"));
// }

if ($USER->IsAuthorized()) {
    $USER_ID = (new CUser)->GetID();
} elseif( !$USER->IsAuthorized() && $request->isPost() && $postData['validation'] == 'Y' && isset($postData[$arParams["FORM_NAME"]]) && $UPDATE_ORDER == 'N' ) {

    /* get order properties */
    $dbOrderProps = (new CSaleOrderProps)->GetList(
        array("SORT" => "ASC", "ID" => "ASC"),
        array(
            "ID" => $arParams["ORDER_PROPS"],
        ),
        false,
        false,
        array()
    );
    while ($arOrderProps = $dbOrderProps->Fetch()) {
        $arResult["ORDER_PROPS_REGISTRATION"][] = $arOrderProps;
    }
    /* / get order properties */
    $arUserArray = [];
    if (!empty($arResult["ORDER_PROPS_REGISTRATION"])) {
        foreach ($arResult["ORDER_PROPS_REGISTRATION"] as $key => $arProp) {
            if($arProp['IS_PAYER'] == 'Y' || $arProp['IS_EMAIL'] == 'Y' || $arProp['CODE'] == 'PHONE'){
                $arUserArray[$arProp["CODE"]] = htmlspecialcharsbx($postData[$arParams["FORM_NAME"]][$arProp["CODE"]]);
            }
        }
    }
    if ($arUserArray && !empty($arUserArray['EMAIL'])) {
        $dbUser = \Bitrix\Main\UserTable::getList(array(
            'select' => array('ID', 'NAME', 'EMAIL'),
            'filter' => array('=EMAIL' => $arUserArray['EMAIL'])
        ));
        if ($arUser = $dbUser->fetch()){
            $USER_ID = $arUser['ID'];
        } elseif ($validEmail = filter_var($arUserArray['EMAIL'], FILTER_VALIDATE_EMAIL)) {
            $user = new \CUser;
            $fields = getDefaultData($arUserArray);
            if ($fields) {
                $fields = array_merge($arUserArray,$fields);
                $addRes = $user->Add($fields);
                if(!intval($addRes)) {
                    $arResult["ERRORS"]["EMAIL"] = $user->LAST_ERROR;
                } else {
                    $USER_ID = intval($addRes);
                }
            } else {
                $arResult["ERRORS"]["EMAIL"] = 'Заполните обязательные поля';
            }
        } else {
            $arResult["ERRORS"]["EMAIL"] = $validEmail;
        }
    } else {
        $arResult["ERRORS"]["EMAIL"] = 'Заполните обязательные поля';
    }
    unset($arResult["ORDER_PROPS_REGISTRATION"]);

    if (isset($USER_ID)) {
        $USER_GROUPS = \Bitrix\Main\UserTable::getUserGroupIds($USER_ID);
        if (!$USER_GROUPS || ($USER_GROUPS && !in_array(1, $USER_GROUPS))) {
            $USER->Authorize($USER_ID);
        } else {
            $CONFIRM_PASSWORD = isset($postData['CONFIRM_PASSWORD']) ? $postData['CONFIRM_PASSWORD'] : '';
            $isRealPassword = false;
            if (!empty($CONFIRM_PASSWORD)) {
                $isRealPassword = isRealPassword($USER_ID, $CONFIRM_PASSWORD);
                $isRealPassword = is_bool($isRealPassword) ? $isRealPassword : false;
                if ($isRealPassword) {
                    $USER->Authorize($USER_ID);
                } else {
                    $arResult["ERRORS"]["CHECK_PASSWORD"] = 'Введенный пароль не верный!';
                }                
            } else {                
                $arResult["ERRORS"]["CHECK_PASSWORD"] = 'Пользователь с такой почтой уже существует необходимо подтвердить пароль!';
            }

            if (!$isRealPassword) {
                $arResult["VALIDATE_PASSWORD"] = '<div class="wrap-input">
                                                <input class="'.(!empty($CONFIRM_PASSWORD) ? 'active':'').'" type="password" id="simple_order_form_CONFIRM_PASSWORD" name="CONFIRM_PASSWORD" value="'.$CONFIRM_PASSWORD.'" placeholder="Подтвердите пароль">
                                            </div>';
            }
        }
    }
}
if (isset($USER_ID)) {
    $user = \Bitrix\Main\UserTable::getById($USER_ID);
    $arResult["USER"] = $user->Fetch();
}


$form = $postData[$arParams["FORM_NAME"]];

// Переключатель для выбора типа плательщика для текущего сайта
$db_ptype = CSalePersonType::GetList(Array("SORT" => "ASC"), Array("LID"=>$arParams["SITE_ID"],"ACTIVE"=>"Y"));
$arResult["PERSON_TYPE"] = array();
$arParams["PERSON_TYPE_ID"] = "";
while ($pType = $db_ptype->Fetch())
{    
    if (
        (intval($form["PERSON_TYPE"]) == $pType["ID"]) ||
        (
            !isset($form["PERSON_TYPE"]) &&
            empty($arResult["PERSON_TYPE"])
        )
    ) {
        $pTypeChecked = 'Y';
        $arParams["PERSON_TYPE_ID"] = $pType["ID"];
    } else {
        $pTypeChecked = 'N';        
    }
    $arResult["PERSON_TYPE"][$pType["ID"]] = $pType;
    $arResult["PERSON_TYPE"][$pType["ID"]]['CHECKED'] = $pTypeChecked;
}

if (empty($arParams["PERSON_TYPE_ID"])) {
    ShowError(\Bitrix\Main\Localization\Loc::getMessage("PERSON_TYPE_IS_NOT_SET"));
    return;
}

$isValidationEnabled = $_POST[$arParams["ENABLE_VALIDATION_INPUT_NAME"]] == "N";
\Bitrix\Sale\DiscountCouponsManager::init();
$order = \Bitrix\Sale\Order::create($arParams["SITE_ID"], $arResult["USER"]["ID"]);
$order->setPersonTypeId($arParams["PERSON_TYPE_ID"]);

/* get basket */
$basket = \Bitrix\Sale\Basket::loadItemsForFUser(
    \Bitrix\Sale\Fuser::getId(),
    $arParams["SITE_ID"]
)->getOrderableItems();
$order->setBasket($basket);
$basketItems = $basket->getBasketItems();
foreach ($basketItems as $basketItem) {
    $arResult["BASKET"][] = $basketItem->getFields()->getValues();
}
/* / get basket */

$shipmentCollection = $order->getShipmentCollection();
$shipment = $shipmentCollection->createItem();
// $locCodeSet = isset($_SESSION['USER_LOCATION']['CODE']) && !empty($_SESSION['USER_LOCATION']['CODE']) ? $_SESSION['USER_LOCATION']['CODE'] : '0000073738';

$nameCity = isset($form['CITY']) && !empty($form['CITY']) ? $form['CITY'] : (isset($arResult["USER"]["PERSONAL_CITY"]) && !empty($arResult["USER"]["PERSONAL_CITY"]) ? $arResult["USER"]["PERSONAL_CITY"] : '');
$_SESSION['IPOLSDEK_city'] = $nameCity;
$_SESSION['IPOLSDEK_CHOSEN_ADDRESS'] = '';

if ( !empty($nameCity) ) {
    $res = Bitrix\Sale\Location\LocationTable::getList(array(
        'filter' => array('=NAME.NAME' => $nameCity, '=NAME.LANGUAGE_ID' => LANGUAGE_ID),
        'select' => array('CODE' => 'CODE', 'NAME_RU' => 'NAME.NAME', 'TYPE_CODE' => 'TYPE.CODE')
    ));
    if($item = $res->fetch()) {            
        $locCodeSet = $item["CODE"];
    }
}

$propertyCollection = $order->getPropertyCollection();
$propertyLocation = $propertyCollection->getDeliveryLocation();
if ( !is_null($propertyLocation) )
    $propertyLocation->setField('VALUE', $locCodeSet);

$deliveryList = \Bitrix\Sale\Delivery\Services\Manager::getRestrictedList(
    $shipment,
    \Bitrix\Sale\Services\Base\RestrictionManager::MODE_CLIENT
);
foreach ($deliveryList as $keyDl => $delivery) {
    if (
        (intval($form["DELIVERY"]) == $delivery["ID"]) ||
        (
            !isset($form["DELIVERY"]) &&
            empty($arResult["DELIVERY"])
        )
    ) {
        $selectedDelivery = $delivery["ID"];
    }

    if ( $delivery['CLASS_NAME'] == '\Bitrix\Sale\Delivery\Services\Automatic' ) {
        unset($deliveryList[$keyDl]);
        continue;
    }

    $arResult["DELIVERY"][$delivery["ID"]] = $delivery;
}
if (!isset($selectedDelivery) && !empty($arResult["DELIVERY"])) {
    reset($arResult["DELIVERY"]);
    $selectedDelivery = key($arResult["DELIVERY"]);
}
if (isset($selectedDelivery)) {
    $deliveryService = \Bitrix\Sale\Delivery\Services\Manager::getById($selectedDelivery);
    $arResult["DELIVERY"][$selectedDelivery] = array_merge($arResult["DELIVERY"][$selectedDelivery], ["CHECKED" => "Y"]);
    $shipment->setFields(array(
        'DELIVERY_ID' => $deliveryService['ID'],
        'DELIVERY_NAME' => $deliveryService['NAME'],
    ));
    $shipmentItemCollection = $shipment->getShipmentItemCollection();
    foreach ($order->getBasket() as $item) {
        $shipmentItem = $shipmentItemCollection->createItem($item);
        $shipmentItem->setQuantity($item->getQuantity());
    }
}

$paymentCollection = $order->getPaymentCollection();
$payment = $paymentCollection->createItem();
$paySystemList = \Bitrix\Sale\PaySystem\Manager::getListWithRestrictions(
    $payment,
    \Bitrix\Sale\Services\Base\RestrictionManager::MODE_CLIENT
);
foreach ($paySystemList as $paySystem) {
    if (
        (intval($form["PAY_SYSTEM"]) == $paySystem["ID"]) ||
        (
            !isset($form["PAY_SYSTEM"]) &&
            empty($arResult["PAY_SYSTEM"])
        )
    ) {
        $selectedPaySystem = $paySystem["ID"];
    }
    $arResult["PAY_SYSTEM"][$paySystem["ID"]] = $paySystem;
}
if (!isset($selectedPaySystem) && !empty($arResult["PAY_SYSTEM"])) {
    reset($arResult["PAY_SYSTEM"]);
    $selectedPaySystem = key($arResult["PAY_SYSTEM"]);
}
if (isset($selectedPaySystem)) {
    $paySystemService = \Bitrix\Sale\PaySystem\Manager::getObjectById($selectedPaySystem);
    $arResult["PAY_SYSTEM"][$selectedPaySystem] = array_merge($arResult["PAY_SYSTEM"][$selectedPaySystem], ["CHECKED" => "Y"]);
    $payment->setFields(array(
        'PAY_SYSTEM_ID' => $paySystemService->getField("ID"),
        'PAY_SYSTEM_NAME' => $paySystemService->getField("NAME"),
        'SUM' => $order->getPrice()
    ));
} else {
    $payment->delete();
}

$currency = $order->getCurrency();

// Получение стоимости по каждой доставке и наполнение к массиву
/*if ($deliveryList) {
    foreach ($deliveryList as $delivery) {
        $deliveryObject = \Bitrix\Sale\Delivery\Services\Manager::calculateDeliveryPrice($shipment,$delivery["ID"]);
        $deliveryPrice = $deliveryObject->getDeliveryPrice();
        $arResult["DELIVERY"][$delivery["ID"]]['PRICE'] = $deliveryPrice;
        $arResult["DELIVERY"][$delivery["ID"]]['PRICE_FORMATED'] = SaleFormatCurrency(
            $deliveryPrice,
            $currency
        );
    }
}*/

$deliveryListServices = \Bitrix\Sale\Delivery\Services\Manager::getRestrictedObjectsList($shipment);

if ($deliveryListServices) {
    foreach ($deliveryListServices as $deliveryId => $deliveryObj) {
        // $shipment->setField('DELIVERY_ID', $deliveryId);
        $calcResult = $deliveryObj->calculate($shipment);
        if (!$calcResult->isSuccess()) {
            $problemDeliveries[$deliveryId] = $deliveryObj;
        }

        $deliveryPrice = $calcResult->getDeliveryPrice();
        $deliveryPeriodFrom = $calcResult->getPeriodFrom();
        $deliveryPeriodTo = $calcResult->getPeriodTo();
        $deliveryPeriod = $calcResult->getPeriodDescription();
        if (is_null($deliveryPeriodFrom) || is_null($deliveryPeriodTo)) {
            $deliveryPeriodArr = explode('-', $deliveryPeriod);
            $deliveryPeriodFrom = (int) preg_replace('/[^0-9]/', '', $deliveryPeriodArr[0]);
            $deliveryPeriodTo = isset($deliveryPeriodArr[1]) ? (int) preg_replace('/[^0-9]/', '', $deliveryPeriodArr[1]) : $deliveryPeriodFrom;
        }
        
        $arResult["DELIVERY"][$deliveryId]['PRICE'] = $deliveryPrice;
        $priceFormated = SaleFormatCurrency(
            $deliveryPrice,
            $currency
        );
        
        $arResult["DELIVERY"][$deliveryId]['PRICE_FORMATED'] = !$deliveryPrice ? ($deliveryId == 5 ? 'рассчитывается индивидуально' : 'бесплатно') : $priceFormated;

        $arResult["DELIVERY"][$deliveryId]['PERIOD_FORMATED'] = 'В этот же день';
        if ($deliveryPeriodFrom > 0) {
            $dayWords = array('день', 'дня', 'дней');
            $arResult["DELIVERY"][$deliveryId]['PERIOD_FORMATED'] = intval($deliveryPeriodFrom) != intval($deliveryPeriodTo) ? $deliveryPeriodFrom . '-' . settings::declensionWords($deliveryPeriodTo, $dayWords) : settings::declensionWords($deliveryPeriodFrom, $dayWords);
        }
    }
}

if ($deliveryList) {
    foreach ($deliveryList as $delivery) {
        if (is_null($nSelectedDelivery) && !$arResult["DELIVERY"][$delivery["ID"]]['DISABLED'])
            $nSelectedDelivery = $delivery["ID"];
        $arResult["DELIVERY"][$delivery["ID"]]['DISABLED'] = array_key_exists($delivery['ID'], $deliveryListServices) ? 0 : 1;
    }
    if ($arResult["DELIVERY"][$selectedDelivery]['DISABLED']) {
        unset($arResult["DELIVERY"][$selectedDelivery]['CHECKED']);
        $selectedDelivery = $nSelectedDelivery;
        $deliveryService = \Bitrix\Sale\Delivery\Services\Manager::getById($selectedDelivery);
        $arResult["DELIVERY"][$selectedDelivery] = array_merge($arResult["DELIVERY"][$selectedDelivery], ["CHECKED" => "Y"]);
        $form["DELIVERY"] = $selectedDelivery;
        $shipment->setFields(array(
            'DELIVERY_ID' => $deliveryService['ID'],
            'DELIVERY_NAME' => $deliveryService['NAME'],
        ));
        $shipmentItemCollection = $shipment->getShipmentItemCollection();
        foreach ($order->getBasket() as $item) {
            $shipmentItem = $shipmentItemCollection->createItem($item);
            $shipmentItem->setQuantity($item->getQuantity());
        }
    }
}

// SaleOrderAjaxCustom::prepareResultArray($order);
// $event = new \Bitrix\Main\Event("sale", "OnSaleComponentOrderOneStepProcess",array($order));
// $event->send();
// if ($event->getResults()){
//     echo "string";
// }
// $rsEvents = GetModuleEvents("sale", "OnSaleComponentOrderOneStepProcess");
// while ($arEvent = $rsEvents->Fetch()) {
//     // settings::debugData($arEvent);
//     ExecuteModuleEvent($arEvent, $order);
//     // ExecuteModuleEventEx($arEvent, array($order));
// }

// settings::debugData($deliveryListServices);


$arResult["PRICES"]["TOTAL_PRICE"] = $order->getPrice();
$arResult["PRICES"]["TOTAL_PRICE_FORMATTED"] = SaleFormatCurrency(
    $arResult["PRICES"]["TOTAL_PRICE"],
    $currency
);
$arResult["PRICES"]["DELIVERY_PRICE"] = $order->getDeliveryPrice();
$arResult["PRICES"]["DELIVERY_PRICE_FREE"] = !$arResult["PRICES"]["DELIVERY_PRICE"] ? 'Бесплатно' : '';
$arResult["PRICES"]["DELIVERY_PRICE_FORMATTED"] = SaleFormatCurrency(
    $arResult["PRICES"]["DELIVERY_PRICE"],
    $currency
);
$arResult["PRICES"]["PRODUCTS_PRICE"] = $arResult["PRICES"]["TOTAL_PRICE"] - $arResult["PRICES"]["DELIVERY_PRICE"];
$arResult["PRICES"]["PRODUCTS_PRICE_FORMATTED"] = SaleFormatCurrency(
    $arResult["PRICES"]["PRODUCTS_PRICE"],
    $currency
);

if (floatval($arResult["PRICES"]["PRODUCTS_PRICE"]) == 0) {
    if ($arParams["BASKET_PAGE"] !== "") {
        LocalRedirect($arParams["BASKET_PAGE"]);
    } else {
        ShowError(\Bitrix\Main\Localization\Loc::getMessage("EMPTY_CART"));
        return;
    }
}

$propertiesFilter = [
    "ID" => $arParams["ORDER_PROPS"],
    "PERSON_TYPE_ID" => $arParams["PERSON_TYPE_ID"]
];

$propertiesRuntime = [];
if (isset($selectedPaySystem)) {
    $propertiesFilter[] = [
        "LOGIC" => "OR",
        [
            "REL_PS.ENTITY_ID" => $selectedPaySystem,
        ],
        [
            "REL_PS.ENTITY_ID" => false
        ]
    ];
    $propertiesRuntime[] = new \Bitrix\Main\Entity\ReferenceField(
        'REL_PS',
        '\Bitrix\Sale\Internals\OrderPropsRelationTable',
        array("=ref.PROPERTY_ID" => "this.ID", "=ref.ENTITY_TYPE" => new \Bitrix\Main\DB\SqlExpression('?', 'P')),
        array("join_type" => "left")
    );
}
if (isset($selectedDelivery)) {
    $propertiesFilter[] = [
        "LOGIC" => "OR",
        [
            "REL_DLV.ENTITY_ID" => $selectedDelivery,
        ],
        [
            "REL_DLV.ENTITY_ID" => false
        ]
    ];
    $propertiesRuntime[] = new \Bitrix\Main\Entity\ReferenceField(
        'REL_DLV',
        '\Bitrix\Sale\Internals\OrderPropsRelationTable',
        array("=this.ID" => "ref.PROPERTY_ID", "=ref.ENTITY_TYPE" => new \Bitrix\Main\DB\SqlExpression('?', 'D')),
        array("join_type" => "left")
    );
}

$properties = \Bitrix\Sale\Property::getList([
    'select' => ['*'],
    'filter' => $propertiesFilter,
    'runtime' => $propertiesRuntime,
    'group' => ['ID'],
    'order' => ['SORT' => 'ASC']
]);
$propsEnumValues = $arResult["ORDER_PROPS_ENUM"] = $tempOrderProps = array();
while ($property = $properties->fetch()) {
    $arResult["ORDER_PROPS"][] = $property;
    if ($property["ID"] == $arParams["FIO_PROPERTY"]) {
        $FIO_PROPERTY_CODE = $property["CODE"];
    }
    if ($property["ID"] == $arParams["PHONE_PROPERTY"]) {
        $PHONE_PROPERTY_CODE = $property["CODE"];
    }
    if ($property["ID"] == $arParams["EMAIL_PROPERTY"]) {
        $EMAIL_PROPERTY_CODE = $property["CODE"];
    }
    if ($property["ID"] == $arParams["DATE_PROPERTY"]) {
        $DATE_PROPERTY_CODE = $property["CODE"];
    }
    if ($property['TYPE'] == 'ENUM') {
        $propsEnumValues[] = $property["ID"];
    }
    if ($property['CODE'] != 'USER_COMMENT') {
        $tempOrderProps[$property["CODE"]] = array(
            'ID' => $property["ID"],
            'NAME' => $property["NAME"]
        );
    }
}

if ($propsEnumValues) {
    $db_propsEnum = CSaleOrderPropsVariant::GetList(
        array("SORT" => "ASC"),
        array("ORDER_PROPS_ID" => $propsEnumValues)
    );
    while ($enumValue = $db_propsEnum->Fetch()){
        $arResult["ORDER_PROPS_ENUM"][$enumValue['ORDER_PROPS_ID']][$enumValue['ID']] = $enumValue;
    }
}
unset($propsEnumValues);

/* get a list of available dates */
if ($arParams["USE_DATE_CALCULATION"] == "Y") {
    $arParams['DATE_FORMAT'] = ($arParams['DATE_FORMAT'] !== "") ? $arParams['DATE_FORMAT'] : "d.m.Y";
    $dateIterator = 0;
    if (!empty($arParams['CLOSING_TIME'])) {
        $today = new \DateTime();
        $closed = new \DateTime();
        $arClosedTime = explode(":", $arParams['CLOSING_TIME']);
        if (isset($arClosedTime[1])) {
            $closed->setTime($arClosedTime[0], $arClosedTime[1]);
            if ($today > $closed) {
                $dateIterator++;
            }
        }
        unset($today, $closed, $arClosedTime);
    }
    while ($dateIterator <= intval($arParams['DATES_INTERVAL'])) {
        $timestamp = mktime(0, 0, 0) + 86400 * intval($dateIterator);
        $obDateTime = \Bitrix\Main\Type\DateTime::createFromTimestamp($timestamp);
        if (!in_array($obDateTime->format("d.m.Y"), $arParams['PROHIBITED_DATES']) &&
            $arParams['WEEKEND_DAY_' . $obDateTime->format("w")] != "Y") {
            $arResult['AVAILABLE_DATES'][] = array(
                "DATE" => $obDateTime,
                "DATE_FORMATTED" => $obDateTime->format($arParams['DATE_FORMAT'])
            );
        }
        $dateIterator++;
    }
}
/* / get a list of available dates */

if ($arParams['USER_CONSENT'] == 'Y' && !empty($arResult["ORDER_PROPS"])) {
    foreach ($arResult["ORDER_PROPS"] as $key => $arProperty) {
        $arResult['USER_CONSENT_FIELDS'][] = $arProperty["NAME"];
    }
}
$arResult['ORDER_SERVICES_SELECTED'] = isset($postData[$arParams["FORM_NAME"]]['ORDER_SERVICES']) ? $postData[$arParams["FORM_NAME"]]['ORDER_SERVICES'] : array();
$arResult['ORDER_FILES'] = isset($filesData) ? $filesData : array();

// Получаем профили авторизованного пользователя
$currentPersonProfiles = $currentPersonProfilesIDS = $currentPersonProfilesData = array();
if ($USER->IsAuthorized() && empty($arResult["CURRENT_VALUES"]["ORDER_PROPS"]) && !empty($arResult["ORDER_PROPS"])) {
    $db_sales = CSaleOrderUserProps::GetList(
        array("DATE_UPDATE" => "DESC"),
        array("USER_ID" => $arResult["USER"]["ID"])
    );

    while ($profile = $db_sales->Fetch()) {
       $currentPersonProfiles[$profile['PERSON_TYPE_ID']] = $profile;
       $currentPersonProfilesIDS[] = $profile['ID'];
    }

    if ( $currentPersonProfilesIDS ) {
        $db_propVals = CSaleOrderUserPropsValue::GetList(
            array("ID" => "ASC"),
            array("USER_PROPS_ID" => $currentPersonProfilesIDS)
        );

        while ($arPropVals = $db_propVals->Fetch()) {
            $currentPersonProfilesData[$arPropVals['PROP_PERSON_TYPE_ID']][$arPropVals['PROP_CODE']] = $arPropVals['VALUE'];
        }

        /* get current values from profile */
        if (!empty($arParams["PERSON_TYPE_ID"])) {
            $currentPersonProfilesData = $currentPersonProfilesData[$arParams["PERSON_TYPE_ID"]];
            foreach ($currentPersonProfilesData as $codeProp => $propValue) {
                $arResult["PROFILE_PROPS"][$codeProp] = htmlspecialcharsbx($propValue);
            }
        }
    }

}

if (class_exists('sdekHelper')) {
    $tarifSdek = sdekHelper::defineDelivery($selectedDelivery);
    $arResult['SDEK_TARIF'] = $tarifSdek;
}

if (isset($form)) {
    if ($isValidationEnabled) {
        $arResult["HIDE_ERRORS"] = "Y";
    }
    $order->doFinalAction(true);
    $propertyCollection = $order->getPropertyCollection();

    /* get current values */
    if (!empty($arResult["ORDER_PROPS"])) {  
        foreach ($arResult["ORDER_PROPS"] as $key => $arProperty) {
            if ($UPDATE_ORDER == 'T') {
                $arResult["CURRENT_VALUES"]["ORDER_PROPS"][$arProperty["CODE"]] = htmlspecialcharsbx($arResult["PROFILE_PROPS"][$arProperty["CODE"]]);
            } else {
                $arResult["CURRENT_VALUES"]["ORDER_PROPS"][$arProperty["CODE"]] = empty($form[$arProperty["CODE"]]) ? htmlspecialcharsbx($arResult["PROFILE_PROPS"][$arProperty["CODE"]]) : htmlspecialcharsbx($form[$arProperty["CODE"]]);
            }

            if ($tarifSdek != 'pickup' && $arProperty["CODE"] == 'ADDRESS') {
                $chosenPickup = strpos($arResult["CURRENT_VALUES"]["ORDER_PROPS"][$arProperty["CODE"]], '#') !== false;
                if ($chosenPickup) {
                    $_SESSION['IPOLSDEK_CHOSEN_ADDRESS'] = $arResult["CURRENT_VALUES"]["ORDER_PROPS"][$arProperty["CODE"]];
                    $arResult["CURRENT_VALUES"]["ORDER_PROPS"][$arProperty["CODE"]] = '';
                    $arResult["PROFILE_PROPS"][$arProperty["CODE"]] = '';
                }
            } elseif (isset($_SESSION['IPOLSDEK_CHOSEN_ADDRESS']) && strpos($_SESSION['IPOLSDEK_CHOSEN_ADDRESS'], '#') !== false && $arProperty["CODE"] == 'ADDRESS') {
                $arResult["CURRENT_VALUES"]["ORDER_PROPS"][$arProperty["CODE"]] = $_SESSION['IPOLSDEK_CHOSEN_ADDRESS'];
            }

            // if ($_SESSION['IPOLSDEK_city'] != $_SESSION['IPOLSDEK_CHOSEN_CITY'] && $arProperty["CODE"] == 'ADDRESS') {
            //     $_SESSION['IPOLSDEK_CHOSEN_ADDRESS'] = '';
            //     $arResult["CURRENT_VALUES"]["ORDER_PROPS"][$arProperty["CODE"]] = '';
            //     $_SESSION['IPOLSDEK_CHOSEN_CITY'] = $_SESSION['IPOLSDEK_city'];
            // }

            foreach ($propertyCollection as $property) {
                if ($property->getField('CODE') == $arProperty["CODE"]) {
                    $property->setValue($arResult["CURRENT_VALUES"]["ORDER_PROPS"][$arProperty["CODE"]]);
                }
            }
        }
    }
    $arResult["CURRENT_VALUES"]["USER_COMMENT"] = htmlspecialcharsbx($form["USER_COMMENT"]);
    if (strlen($arResult["CURRENT_VALUES"]["USER_COMMENT"]) > 0) {
        $order->setField('USER_DESCRIPTION', $arResult["CURRENT_VALUES"]["USER_COMMENT"]);
    }

    if (!empty($arParams["SERVICE_ORDER_PROP"]) && count($arResult['ORDER_SERVICES_SELECTED']) > 0) {
        $ORDER_SERVICES_TEXT = htmlspecialcharsbx(implode(";\n", $arResult['ORDER_SERVICES_SELECTED']));
        foreach ($propertyCollection as $property) {
            if ($property->getField('CODE') == $arParams["SERVICE_ORDER_PROP"]) {
                $property->setValue($ORDER_SERVICES_TEXT);
            }
        }
    }

    if (count($addedFiles) > 0) {
        $setFiles = $setFilesO = array();
        foreach ($addedFiles as $file) {
            $F_ID = intval($file->getName());
            if ($F_ID > 0) {
                $setFiles[] = $F_ID;
            }
        }
        if ($setFiles) {
            foreach ($propertyCollection as $property) {
                if ($property->getField('CODE') == $arParams["FILES_ORDER_PROP"]) {
                    $property->setValue($setFiles);
                }
            }
        }
        
    }

    if ($tarifSdek != NULL && isset($_SESSION['IPOLSDEK_CHOSEN'][$tarifSdek])) {
        foreach ($propertyCollection as $property) {
            if ($property->getField('CODE') == 'IPOLSDEK_CNTDTARIF') {
                $property->setValue($_SESSION['IPOLSDEK_CHOSEN'][$tarifSdek]);
            }
        }
    }

    /* / get current values */

    $arResult["CURRENT_VALUES"]["ORDER_PROPS"]['AUTO_SUBSCRIBE'] = isset($postData[$arParams["FORM_NAME"]]['AUTO_SUBSCRIBE']) ? $postData[$arParams["FORM_NAME"]]['AUTO_SUBSCRIBE'] : '';

    /* validation */
    $arResult["ERRORS"] = !isset($arResult["ERRORS"]) ? array() : $arResult["ERRORS"];
    $arResult["ERRORS_FIELDS"] = array();
    if (!empty($arResult["ORDER_PROPS"])) {
        foreach ($arResult["ORDER_PROPS"] as $key => $arProperty) {
            if (
                (($arResult["CURRENT_VALUES"]["ORDER_PROPS"][$arProperty["CODE"]] == "") &&
                in_array($arProperty["ID"], $arParams["REQUIRED_ORDER_PROPS"])) ||
                ( $arProperty['IS_EMAIL'] == 'Y' && !filter_var($arResult["CURRENT_VALUES"]["ORDER_PROPS"][$arProperty["CODE"]], FILTER_VALIDATE_EMAIL) ) ||
                ( $arProperty['IS_PHONE'] == 'Y' && strlen(str_replace("_", '', $arResult["CURRENT_VALUES"]["ORDER_PROPS"][$arProperty["CODE"]])) != strlen($arResult["CURRENT_VALUES"]["ORDER_PROPS"][$arProperty["CODE"]]) )
            ) {
                $arResult["ERRORS"][$arProperty["CODE"]] = \Bitrix\Main\Localization\Loc::getMessage("REQUIRED_FIELD") .
                    "\"" .
                    $arProperty["NAME"] .
                    "\"";
                $arResult["ERRORS_FIELDS"][] = $arProperty["CODE"];
            }
        }
    }
    if ($selectedPaySystem != $form["PAY_SYSTEM"]) {
        $arResult["ERRORS"][] = \Bitrix\Main\Localization\Loc::getMessage("PAY_SYSTEM_ERROR");
    }
    if ($selectedDelivery != $form["DELIVERY"]) {
        $arResult["ERRORS"][] = \Bitrix\Main\Localization\Loc::getMessage("DELIVERY_ERROR");
    }
    /* / validation */
    if (empty($arResult["ERRORS"]) && $arResult["HIDE_ERRORS"] != "Y" && $UPDATE_ORDER == 'N') {

        // устраняем дублирование уведомлений при заказах
        \Bitrix\Sale\Notify::setNotifyDisable(true);
        
        $savingResult = $order->save();
        if (!$savingResult->isSuccess()) {
            $errors = $savingResult->getErrorMessages();
            $arResult["ERRORS"] = array_merge($arResult["ERRORS"], $errors);
        } else {
            $arResult["ORDER_ID"] = $order->GetId();

            // добавляем пользователю профиль
            if ($arResult["PERSON_TYPE"]) {
                $currentPersonProfiles = array();
                $db_sales = CSaleOrderUserProps::GetList(
                    array("DATE_UPDATE" => "DESC"),
                    array("USER_ID" => $arResult["USER"]["ID"])
                );

                while ($profile = $db_sales->Fetch()) {
                   $currentPersonProfiles[$profile['PERSON_TYPE_ID']] = $profile;
                }

                if (!array_key_exists($arParams["PERSON_TYPE_ID"], $currentPersonProfiles)) {
                    $arProfileFields = array(
                        "NAME" => (string)$arResult["PERSON_TYPE"][$arParams["PERSON_TYPE_ID"]]['NAME'],
                        "USER_ID" => $arResult["USER"]["ID"],
                        "PERSON_TYPE_ID" => $arParams["PERSON_TYPE_ID"]
                    );
                    $PROFILE_ID = CSaleOrderUserProps::Add($arProfileFields);

                    //если профиль создан
                    if ($PROFILE_ID) {
                        //добавляем значения свойств к созданному ранее профилю
                        foreach ($tempOrderProps as $code => $prop) {
                            $addProp = array(
                                "USER_PROPS_ID" => $PROFILE_ID,
                                "ORDER_PROPS_ID" => $prop['ID'],
                                "NAME" => $prop['NAME'],
                                "VALUE" => (string)$arResult["CURRENT_VALUES"]["ORDER_PROPS"][$code]
                            );
                            CSaleOrderUserPropsValue::Add($addProp);
                        }
                        unset($tempOrderProps);
                    }
                }
            }

            if ( isset($postData[$arParams["FORM_NAME"]]['AUTO_SUBSCRIBE']) && $postData[$arParams["FORM_NAME"]]['AUTO_SUBSCRIBE'] == 'Y' ) {
                // запрос всех рубрик
                $rub = CRubric::GetList(
                    array("LID"=>"ASC","SORT"=>"ASC","NAME"=>"ASC"),
                    array("ACTIVE"=>"Y", "LID"=>LANG)
                );
                $arRubIDS = array();
                while ($arRub = $rub->Fetch()){
                    $arRubIDS[] = $arRub['ID'];
                }
                 
                // формируем массив с полями для создания подписки
                $arFields = Array(
                    "USER_ID" => ($USER->IsAuthorized() ? $USER->GetID() : false),
                    "FORMAT" => "html",
                    "EMAIL" => (
                        isset($EMAIL_PROPERTY_CODE) &&
                        !empty($arResult["CURRENT_VALUES"]["ORDER_PROPS"][$EMAIL_PROPERTY_CODE])
                    ) ? $arResult["CURRENT_VALUES"]["ORDER_PROPS"][$EMAIL_PROPERTY_CODE] : $arResult["USER"]["EMAIL"],
                    "ACTIVE" => "Y",
                    "RUB_ID" => $arRubIDS,
                    "SEND_CONFIRM" => 'N',
                    "CONFIRMED" => 'Y'
                );         

                $subscr = new CSubscription;

                // создаем подписку
                $ID = $subscr->Add($arFields);
            }

            /* events sending */
            if (
                !empty($arParams["EVENT_TYPES"]) &&
                $USER->IsAuthorized() &&
                (
                    !empty($arResult["USER"]["EMAIL"]) ||
                    isset($EMAIL_PROPERTY_CODE) &&
                    !empty($arResult["CURRENT_VALUES"]["ORDER_PROPS"][$EMAIL_PROPERTY_CODE])
                )
            ) {
                $mailContactList = "";
                $mailUserName = "";
                $mailBasketList = "";
                if (
                    isset($PHONE_PROPERTY_CODE) &&
                    isset($arResult["CURRENT_VALUES"]["ORDER_PROPS"][$PHONE_PROPERTY_CODE])
                ) {
                    $mailContactList .= \Bitrix\Main\Localization\Loc::getMessage("PHONE_PROPERTY") .
                        $arResult["CURRENT_VALUES"]["ORDER_PROPS"][$PHONE_PROPERTY_CODE] . '<br/>';
                }
                if (
                    isset($EMAIL_PROPERTY_CODE) &&
                    isset($arResult["CURRENT_VALUES"]["ORDER_PROPS"][$EMAIL_PROPERTY_CODE])
                ) {
                    $mailContactList .= \Bitrix\Main\Localization\Loc::getMessage("EMAIL_PROPERTY") .
                        $arResult["CURRENT_VALUES"]["ORDER_PROPS"][$EMAIL_PROPERTY_CODE] . '<br/>';
                }
                if (
                    isset($DATE_PROPERTY_CODE) &&
                    isset($arResult["CURRENT_VALUES"]["ORDER_PROPS"][$DATE_PROPERTY_CODE])
                ) {
                    $mailContactList .= \Bitrix\Main\Localization\Loc::getMessage("DATE_PROPERTY") .
                        $arResult["CURRENT_VALUES"]["ORDER_PROPS"][$DATE_PROPERTY_CODE] . '<br/>';
                }
                if (
                    isset($FIO_PROPERTY_CODE) &&
                    isset($arResult["CURRENT_VALUES"]["ORDER_PROPS"][$FIO_PROPERTY_CODE])
                ) {
                    $mailUserName = $arResult["CURRENT_VALUES"]["ORDER_PROPS"][$FIO_PROPERTY_CODE];
                }
                $mailBasket = $order->getBasket();
                if(!empty($mailBasket)){
                    foreach ($mailBasket as $basketItem) {
                        $mailBasketList .= '<p>' .$basketItem->getField('NAME') .
                            ' ' .
                            $basketItem->getQuantity() .
                            ' x ' .
                            SaleFormatCurrency($basketItem->getPrice(), $currency) .
                            ' = ' .
                            SaleFormatCurrency($basketItem->getFinalPrice(), $currency) .
                            '</p>';
                    }
                }
                $arEventFieldsUs = array(
                    "ORDER_ID" => $arResult["ORDER_ID"],
                    "PRICE" => $arResult["PRICES"]["TOTAL_PRICE_FORMATTED"],
                    "DELIVERY_PRICE" => $arResult["PRICES"]["DELIVERY_PRICE_FORMATTED"],
                    "PRODUCTS_PRICE" => $arResult["PRICES"]["PRODUCTS_PRICE_FORMATTED"],
                    "ORDER_LIST" => $mailBasketList,
                    "EMAIL" => (
                        isset($EMAIL_PROPERTY_CODE) &&
                        !empty($arResult["CURRENT_VALUES"]["ORDER_PROPS"][$EMAIL_PROPERTY_CODE])
                    ) ? $arResult["CURRENT_VALUES"]["ORDER_PROPS"][$EMAIL_PROPERTY_CODE] : $arResult["USER"]["EMAIL"],
                    "PHONE" => (
                        isset($EMAIL_PROPERTY_CODE) &&
                        !empty($arResult["CURRENT_VALUES"]["ORDER_PROPS"][$PHONE_PROPERTY_CODE])
                    ) ? $arResult["CURRENT_VALUES"]["ORDER_PROPS"][$PHONE_PROPERTY_CODE] : $arResult["USER"]["PHONE"],
                    "ORDER_USER" => $mailUserName,
                    "ORDER_DATE" => date('d.m.Y'),
                    "CONTACT_LIST" => $mailContactList,
                    "SALE_EMAIL" => \Bitrix\Main\Config\Option::get("sale", "order_email", "")
                );
                if (!empty($arParams["EVENT_TYPES"])) {
                    foreach ($arParams["EVENT_TYPES"] as $eventTypeID) {
                        \Bitrix\Main\Mail\Event::send([
                            "EVENT_NAME" => $eventTypeID,
                            "LID" => $arParams["SITE_ID"],
                            "C_FIELDS" => $arEventFieldsUs
                        ]);
                    }
                }
            }
            /* / events sending */

            if (isset($tempFilesDir)) {
                $tempFilesDir->delete();
            }

            if ($arParams["ORDER_RESULT_PAGE"] !== "") {
                LocalRedirect($arParams["ORDER_RESULT_PAGE"] . "?ORDER_ID=" . $arResult["ORDER_ID"], true);
            } else {
                $arResult["ORDER_SUCCESSFULLY_CREATED"] = "Y";
            }
        }
    }
} else {
    if (
        $arParams["SET_DEFAULT_PROPERTIES_VALUES"] == "Y" &&
        $USER->IsAuthorized() &&
        isset($arResult["USER"])
    ) {
        if (isset($FIO_PROPERTY_CODE)) {
            $fullName = $arResult["USER"]["LAST_NAME"];
            $fullName .= (($fullName == "") ? "" : " ") . $arResult["USER"]["NAME"];
            $fullName .= (($fullName == "") ? "" : " ") . $arResult["USER"]["SECOND_NAME"];
            $arResult["CURRENT_VALUES"]["ORDER_PROPS"][$FIO_PROPERTY_CODE] = $fullName;
        }
        if (isset($PHONE_PROPERTY_CODE)) {
            $arResult["CURRENT_VALUES"]["ORDER_PROPS"][$PHONE_PROPERTY_CODE] = $arResult["USER"]["PERSONAL_PHONE"];
        }
        if (isset($EMAIL_PROPERTY_CODE)) {
            $arResult["CURRENT_VALUES"]["ORDER_PROPS"][$EMAIL_PROPERTY_CODE] = $arResult["USER"]["EMAIL"];
        }
        if (isset($arResult["USER"]["PERSONAL_CITY"])) {
            $arResult["CURRENT_VALUES"]["ORDER_PROPS"]['CITY'] = $arResult["USER"]["PERSONAL_CITY"];
        }
        if (isset($arResult["USER"]["PERSONAL_STREET"])) {
            $arResult["CURRENT_VALUES"]["ORDER_PROPS"]['ADDRESS'] = $arResult["USER"]["PERSONAL_STREET"];
        }
    }
}
function getDefaultData($userProps){
    $userEmail = isset($userProps['EMAIL']) ? trim((string)$userProps['EMAIL']) : '';
    $userPhone = isset($userProps['PHONE']) ? trim((string)$userProps['PHONE']) : '';
    // define("LOG_FILENAME", $_SERVER["DOCUMENT_ROOT"]."/log/log.txt");
    // AddMessage2Log(print_r($userProps,true), "getDefaultData");
    // return false;

    $newName = '';
    $newLastName = '';
    $userName = isset($userProps['FIO']) ? trim((string)$userProps['FIO']) : '';

    if (!empty($userName))
    {
        $arNames = explode(' ', $userName);
        if (isset($arNames[1]))
        {
            $newName = $arNames[1];
            $newLastName = $arNames[0];
        }
        else
        {
            $newName = $arNames[0];
        }
    } else {
        $newName = 'Анонимный пользователь';
    }

    if(empty($userEmail) || empty($userPhone) || empty($newName)) {
        // $userEmail = 'anonim_'.randString(9).'@autogen.ru';
        return false;
    }

    $groupIds = array(2);

    $arPolicy = $GLOBALS["USER"]->GetGroupPolicy($groupIds);

    $passwordMinLength = (int)$arPolicy['PASSWORD_LENGTH'];
    if ($passwordMinLength <= 0)
    {
        $passwordMinLength = 6;
    }

    $passwordChars = array(
        'abcdefghijklnmopqrstuvwxyz',
        'ABCDEFGHIJKLNMOPQRSTUVWXYZ',
        '0123456789',
    );
    if ($arPolicy['PASSWORD_PUNCTUATION'] === 'Y')
    {
        $passwordChars[] = ",.<>/?;:'\"[]{}\|`~!@#\$%^&*()-_+=";
    }

    $newPassword = randString($passwordMinLength + 2, $passwordChars);

    return array(
        'EMAIL' => $userEmail,
        'LOGIN' => $userEmail,
        'PERSONAL_PHONE' => $userPhone,
        'NAME' => $newName,
        'LAST_NAME' => $newLastName,
        'PASSWORD' => $newPassword,
        'PASSWORD_CONFIRM' => $newPassword,
        'GROUP_ID' => $groupIds
    );
}
function isRealPassword($userId, $password)
{
    global $USER;
    
    $userData = CUser::GetByID($userId)->Fetch();

    $arAuthResult = $USER->Login($userData['LOGIN'], $password, "Y");
    return $arAuthResult;
}
$this->IncludeComponentTemplate();