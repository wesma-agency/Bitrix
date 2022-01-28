<?php

class CatalogProductIndexer
{
  /**
   * @var int Идентификатор инфоблока каталога 
   */
  // const IBLOCK_ID = array(settings::$catalogIblockId, settings::$catalogSkuIblockId);

  /**
   * Дополняет индексируемый массив нужными значениями
   * подписан на событие BeforeIndex модуля search
   * @param array $arFields 
   * @return array
   */
  public static function BeforeIndexHandler( $arFields = [] )
  {
    if ( !static::isInetesting( $arFields ) )
    {
      return $arFields;
    }
    /**
     * @var array Массив полей элемента, которые нас интересуют
     */
    $arSelect = [
      'ID',
      'IBLOCK_ID',
      'PROPERTY_ARTICUL'
    ];

    /**
     * @var CIblockResult Массив описывающий индексируемый элемент
     */
    $resElements = \CIBlockElement::getList(
      [],
      [
        'IBLOCK_ID' => $arFields['PARAM2'],
        'ID'        => $arFields['ITEM_ID']
      ],
      false,
      [
        'nTopCount'=>1
      ],
      $arSelect
    );

    /**
     * В случае, если элемент найден мы добавляем нужные поля 
     * в соответсвующие столбцы поиска
     */
    if ( $arElement = $resElements->fetch() )
    {
      $arFields['TITLE'] .= ' '.$arElement['PROPERTY_ARTICUL_VALUE'];
      // $arFields['BODY'] .= ' '.$arElement['PROPERTY_ARTICUL_VALUE'];
    }

    return $arFields;
  }

  /**
   * Возвращает true, если это интересующий нас элемент
   * @param array $fields 
   * @return boolean
   */
  public static function isInetesting( $fields = [] )
  {
  	$IBLOCK_IDS = array(
  		settings::$catalogIblockId,
  		settings::$catalogSkuIblockId
  	);
    return ( $fields["MODULE_ID"] == "iblock" && in_array($fields['PARAM2'], $IBLOCK_IDS) );
  }

}