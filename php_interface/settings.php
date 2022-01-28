<?php

if(!defined('B_PROLOG_INCLUDED')||B_PROLOG_INCLUDED!==true)die();

use \Bitrix\Main\Grid\Declension,
	\Bitrix\Main\Loader;

Loader::includeModule("highloadblock"); 

use \Bitrix\Highloadblock as HL,
	\Bitrix\Main\Entity;

class Settings {

	public static $catalogIblockId      	=   1;
	public static $catalogSkuIblockId   	=   2;
	public static $catalogReviewsIblockId  	=   3;
	// === Лимит кол-ва отзывов в деталке (используется для показа кнопки ПОКАЗАТЬ ВСЕ)
	public static $limitCatalogReviews 		=   3;
	// === Ссылка на корзину
	public static $siteCartUrl				=   '/cart/';
	// === Ссылка на профиль
	public static $siteProfileUrl			=   '/personal/';
	// === Ссылка на страницу политики безопасности
	public static $sitePoliticUrl			=   '/politika-konfidentsialnosti/';
	// === Ссылка ИБ новостей
	public static $IblockNewsUrl			=   '/blog/';
	// === Ссылка ИБ Каталог
	public static $IblockCatalogUrl			=   '/katalog/';
	// === Ссылка Раздел для женщин
	public static $catalogWomenUrl			=   '/katalog/filter/pol-is-fd55cb64-5f18-11ec-a3c5-089798c9b252/apply/';
	// === Ссылка Раздел для мужчин
	public static $catalogMenUrl			=   '/katalog/filter/pol-is-068c46d1-5f31-11ec-a3c5-089798c9b252/apply/';
	// === Почта для отзывов
	public static $reviewsEmail				=   'developer@wesma.ru';

    // получение кол-ва избранных товаров с обновлением сессии
	public function favoritesCount() {
		global $USER;
		if ( isset( $_SESSION['CATALOG_FAVORITES_LIST'] ) ) {
			return count( $_SESSION['CATALOG_FAVORITES_LIST'] );
		}
		if ( !$USER->IsAuthorized() ) {
			$_SESSION['CATALOG_FAVORITES_LIST'] = isset( $_SESSION['CATALOG_FAVORITES_LIST'] )
				? $_SESSION['CATALOG_FAVORITES_LIST'] : array();
		}
		else {
			$idUser = $USER->GetID();
			$rsUser = CUser::GetByID( $idUser );
			$arUser = $rsUser->Fetch();
			$arElements = unserialize( $arUser['UF_FAVORITES'] );
			$_SESSION['CATALOG_FAVORITES_LIST'] = is_array( $arElements ) ? $arElements : array();
		}

		return count( $_SESSION['CATALOG_FAVORITES_LIST'] );
	}

	/*
		Функция получение элементов ИБ с их свойствами
		$filter - массив для фильтрации выборки
		$select - массив полей на выборку
		$cropImage - ключ необходимости автокропа изображения
		$cropImageID - ID профиля модуля PhpThumb
		$sort - сортировка выдачи
		$extend - сбор и объединение в массив соответствующих свойств. Необходимо использовать в множественных свойствах для получения верной картины.
	*/
	public function getIbElements( $filter = array(), $select = array(), $cropImage = false, $cropImageID = 0, $sort = false, $extend = false ) {
		$ITEMS = array();

		if ( !$sort ) {
			$sort = array( 'SORT' => 'ASC' );
		}

		$rsElements = CIBlockElement::GetList(
			$sort,
			$filter,
			false,
			false,
			$select
		);

		while ( $arElement = $rsElements->GetNext() ) {
			if ( isset($ITEMS[$arElement['ID']]) && !$extend ) {
				continue;
			}
			elseif ( isset($ITEMS[$arElement['ID']]) && $extend ) {
				foreach ($arElement as $kElem => $vElem) {
					if ( strpos($kElem, 'PROPERTY_') !== false ) {
						if ( is_array($ITEMS[$arElement['ID']][$kElem]) && !in_array($vElem, $ITEMS[$arElement['ID']][$kElem]) ) {
							$ITEMS[$arElement['ID']][$kElem][] = $vElem;
						}
						elseif ( !is_array( $ITEMS[$arElement['ID']][$kElem] ) && $ITEMS[$arElement['ID']][$kElem] != $vElem ) {
							$ITEMS[$arElement['ID']][$kElem] = array( $ITEMS[$arElement['ID']][$kElem] );
							$ITEMS[$arElement['ID']][$kElem][] = $vElem;
						}
					}
				}
				continue;
			}
			if ( array_key_exists('PREVIEW_PICTURE', $arElement) ) {
				$pathIMG = '';
				$imgID = !empty( $arElement['PREVIEW_PICTURE'] ) ? $arElement['PREVIEW_PICTURE']
					: ( !empty( $arElement['PROPERTY_MORE_PHOTO_VALUE'] ) ? $arElement['PROPERTY_MORE_PHOTO_VALUE'] : 0 );
				if ( $imgID > 0 ) {
					$pathIMG = CFile::GetPath( $imgID );
				}

				$arElement['IMG_SRC'] = $pathIMG;
				if ($cropImageID && is_array($cropImageID)) {
					foreach ($cropImageID as $vID) {
						$arElement['THUMB_SRC_'.$vID] = $cropImage ? CMillcomPhpThumb::generateImg(
							$pathIMG,
							$vID
						) : $pathIMG;
					}

				} else {
					$arElement['THUMB_SRC'] = $cropImage ? CMillcomPhpThumb::generateImg(
						$pathIMG,
						$cropImageID
					) : $pathIMG;
				}
			}
			$ITEMS[$arElement['ID']] = $arElement;
		}

		return $ITEMS;
	}

	/*
		Функция получение разделов ИБ с их свойствами
		$filter - массив для фильтрации выборки
		$select - массив полей на выборку
		$cropImage - ключ необходимости автокропа изображения
		$cropImageID - ID профиля модуля PhpThumb
		$sort - сортировка выдачи
	*/
	public function getIbSections( $filter = array(), $select = array(), $sort = false, $cropImage = false, $cropImageID = 0 ) {
		$ITEMS = array();

		if ( !$sort ) {
			$sort = array( 'SORT' => 'ASC' );
		}

		$rsElements = CIBlockSection::GetList(
			$sort,
			$filter,
			false,
			$select,
			false
		);

		while ( $arElement = $rsElements->GetNext() ) {
			if ( array_key_exists('PICTURE', $arElement) ) {
				$pathIMG = '';
				$imgID = !empty( $arElement['PICTURE'] ) ? $arElement['PICTURE'] : 0;
				if ( $imgID > 0 ) {
					$pathIMG = CFile::GetPath( $imgID );
				}

				$arElement['IMG_SRC'] = $pathIMG;
				$arElement['THUMB_SRC'] = $cropImage ? CMillcomPhpThumb::generateImg(
					$pathIMG,
					$cropImageID
				) : $pathIMG;
			}
			$ITEMS[$arElement['ID']] = $arElement;
		}

		return $ITEMS;
	}

	/*
		Функция получение элементов HL-блока
		$filter - массив для фильтрации выборки
		$select - массив полей на выборку
		$cropImage - ключ необходимости автокропа изображения
		$cropImageID - ID профиля модуля PhpThumb
		$sort - сортировка выдачи
	*/
	public function getHlElements( $hlbl = 0, $filter = array(), $select = array( '*' ), $sort = false, $cropImage = false, $cropImageID = 0 ) {

		if ( !$hlbl ) return array();
		$ITEMS = array();

		if ( !$sort ) {
			$sort = array( 'ID' => 'ASC' );
		}

		$hlblock = HL\HighloadBlockTable::getById( $hlbl )->fetch();
		if ( !$hlblock ) return array();

		$entity = HL\HighloadBlockTable::compileEntity( $hlblock );
		$entity_data_class = $entity->getDataClass();

		$rsData = $entity_data_class::getList(array(
			 "select" => $select,
			 "order"  => $sort,
			 "filter" => $filter,
		 ));

		while ($arElement = $rsData->Fetch()) {
			if ( array_key_exists('UF_IMAGE', $arElement) ) {
				$pathIMG = '';
				$imgID = !empty( $arElement['UF_IMAGE'] ) ? $arElement['UF_IMAGE'] : 0;
				if ( $imgID > 0 ) {
					$pathIMG = CFile::GetPath( $imgID );
				}

				$arElement['IMG_SRC'] = $pathIMG;
				$arElement['THUMB_SRC'] = $cropImage ? CMillcomPhpThumb::generateImg(
					$pathIMG,
					$cropImageID
				) : $pathIMG;
			}
			$ITEMS[$arElement['ID']] = $arElement;
		}

		return $ITEMS;
	}

	/*
		Функция получение свойств ИБ
		$filter - массив для фильтрации выборки
		$sort - сортировка выборки
	*/
	public function getIbProperties( $filter = array(), $sort = false ) {
		$ITEMS = array();

		if ( !$sort ) {
			$sort = array( 'SORT' => 'ASC' );
		}

		$rsElements = CIBlockProperty::GetList(
			$sort,
			$filter
		);
		while ( $arElement = $rsElements->GetNext() ) {
			$ITEMS[$arElement['CODE']] = $arElement;
		}

		return $ITEMS;
	}

	// Склонение слов по числу
	public function declensionWords( $number = 0, $words = array() ) {
		if ( !$words ) return '';
		$words = array_values( $words );
		$wordDeclension = new Declension(
			$words[0],
			$words[1],
			$words[2]
		);

		return $number . ' ' . $wordDeclension->get( $number );
	}

	// Очистка корзины
	public function clearUserBasket() {
		$errors = array();
		$res = CSaleBasket::GetList(
			array(),
			array(
				'FUSER_ID' => CSaleBasket::GetBasketUserID(),
				'LID'      => SITE_ID,
				'ORDER_ID' => 'null',
				'DELAY'    => 'N',
				'CAN_BUY'  => 'Y',
			)
		);
		while ( $row = $res->fetch() ) {
			if ( !CSaleBasket::Delete( $row['ID'] ) ) {
				$errors[] = 'Don\'t row with ID - ' . $row['ID'];
			}
		}

		return $errors;
	}

	// Debug DUMP
	public function debugData($data='', $type='', $name='', $hide=false) {
		switch ($type) {
			case 'dump':
				\Bitrix\Main\Diag\Debug::dump($data, $name, false);
				break;			
			default:
				echo "<pre".($hide ? ' style="display:none;"':'').">";
				print_r($data);
				echo "</pre>";
				break;
		}
		
	}

	// Log Data
	public function logData($data='', $filename='', $type='writeToFile') {
		\Bitrix\Main\Diag\Debug::$type($data, $filename);		
	}

	// Получение кода цвета по названию
	public function getColorCode($name = '', $format = 'hex') {
		$like = 0;
        $code = null;
        $name = mb_strtolower($name, 'utf-8');
        $name = trim($name);
        $lang = preg_replace("/[^а-яА-Я]/ui", '', $name) != '' ? 'RU' : 'EN';
        $pathColors = dirname(__FILE__).'/../include/colors/colors_'.$lang.'.php';
        $colors = array();
        if (file_exists($pathColors)) {
            $colors = include($pathColors);
        }
        if ($colors && !empty($name)) { 
	        foreach ($colors as $code_ => $code_name) {
	            foreach ((array)$code_name as $name_) {
	                $name_ = mb_strtolower($name_, 'utf-8');
	                similar_text($name, $name_, $percent);
	                if (($percent > 60.0) && ($percent > $like)) {
	                    $like = $percent;
	                    $code = $code_;
	                }
	            }
	        }
	    }
        return self::convertColor($format,$code);		
	}

	private function convertColor($format, $value = null, $raw = false)
    {
        if ($value === null) {
        	switch ($format) {
                case 'rgb':
                    $value = 'rgb(0,0,0)';
                    break;

                case 'hex':
                    $value = '#000000';
                    break;

                case 'cmyk':
                    $value = 'cmyk(0,0,0,0)';
                    break;

                case 'hsv':
                	$value = 'hsv(0,0,0)';
            }
            return $value;
        }
        $pattern = null;
        if ($value !== null) {

            switch ($format) {
                case 'rgb':
                    $value = array(
                        0xFF & ($value >> 16),
                        0xFF & ($value >> 8),
                        0xFF & $value,
                    );
                    $pattern = 'rgb(%d,%d,%d)';
                    break;

                case 'hex':
                    $value = sprintf('#%06X', $value);
                    break;

                case 'cmyk':
                    $r = 0xFF & ($value >> 16);
                    $g = 0xFF & ($value >> 8);
                    $b = 0xFF & $value;
                    $c_ = 1.0 - ($r / 255);
                    $m_ = 1.0 - ($g / 255);
                    $y_ = 1.0 - ($b / 255);

                    $black = min($c_, $m_, $y_);
                    $cyan = ($c_ - $black) / (1.0 - $black);
                    $magenta = ($m_ - $black) / (1.0 - $black);
                    $yellow = ($y_ - $black) / (1.0 - $black);
                    $pattern = 'cmyk(%0.2f,%0.2f,%0.2f,%0.2f)';
                    $value = array($cyan, $magenta, $yellow, $black);
                    break;

                case 'hsv':
                    $r = 0xFF & ($value >> 16);
                    $g = 0xFF & ($value >> 8);
                    $b = 0xFF & $value;
                    $rgb_max = max($r, $g, $b);

                    $hsv = array(
                        'hue' => 0,
                        'sat' => 0,
                        'val' => $rgb_max,
                    );

                    if ($hsv['val'] > 0) {
                        /* Normalize value to 1 */
                        $r /= $hsv['val'];
                        $g /= $hsv['val'];
                        $b /= $hsv['val'];

                        $rgb_min = min($r, $g, $b);
                        $rgb_max = max($r, $g, $b);


                        $hsv['sat'] = $rgb_max - $rgb_min;
                        if ($hsv['sat'] > 0) {
                            /* Normalize saturation to 1 */
                            $r = ($r - $rgb_min) / ($rgb_max - $rgb_min);
                            $g = ($g - $rgb_min) / ($rgb_max - $rgb_min);
                            $b = ($b - $rgb_min) / ($rgb_max - $rgb_min);
                            $rgb_max = max($r, $g, $b);

                            /* Compute hue */
                            if ($rgb_max == $r) {
                                $hsv['hue'] = 0.0 + 60.0 * ($g - $b);
                                if ($hsv['hue'] < 0.0) {
                                    $hsv['hue'] += 360.0;
                                }
                            } elseif ($rgb_max == $g) {
                                $hsv['hue'] = 120.0 + 60.0 * ($b - $r);
                            } else /* rgb_max == $b */ {
                                $hsv['hue'] = 240.0 + 60.0 * ($r - $g);
                            }
                        }
                    }
                    $pattern = 'hsv(%d,%d,%d)';
                    $value = array_values($hsv);
                    break;

                default:
                    break;
            }

            if (empty($raw) && !empty($pattern)) {
                $value = vsprintf($pattern, (array)$value);
            }
        }
        return $value;
    }

    public static function varExportToFile($var, $file, $export = true)
    {
        $result = false;
        if ($export) {
            $var = var_export($var, true);
        }
        $file_contents = "<?php\nreturn {$var};\n";

        // Attempt to write to tmp file and then rename.
        // This minimizes the risk that a half-written file will be
        // included by another process if something goes wrong.
        $dir = realpath(dirname($file));
        if ($dir) {
            $tmp_file = @tempnam($dir, basename($file));
            if ($tmp_file && $dir == realpath(dirname($tmp_file))) {
                @chmod($tmp_file, 0664);
                $result = @file_put_contents($tmp_file, $file_contents);
                $result = $result && @rename($tmp_file, $file);
            }
            if ($tmp_file && file_exists($tmp_file)) {
                @unlink($tmp_file);
            }
        }

        // Attempt to write to destination directly.
        if (!$result && (!file_exists($file) || is_writable($file))) {
            $result = @file_put_contents($file, $file_contents, LOCK_EX);
        }

        // Clear opcache so that file changes are visible to `include` immediately
        if ($result && function_exists('opcache_invalidate')) {
            @opcache_invalidate($file, true);
        }
        return !!$result;
    }

    public function getTree( $arItems ) {

		$arTmpTree = array();


		$i = 0;
		while ( $currentItem = current( $arItems ) ) {

			// === уровень текущего элемента
			$currentLevel = intval( $currentItem['DEPTH_LEVEL'] );

			// === индекс текущего элемента
			$currentIndex = key( $arItems );

			// === следующий элемент
			$nextItem = next( $arItems );


			// === первая итерация
			if ( $i === 0 ) {

				// === первый уровень вложенности
				$thirstLevel = $currentLevel;

			}


			if (!isset($arItems{$currentIndex}['CHILDS']))
				$arItems{$currentIndex}['CHILDS'] = array();


			// === следующий элемент существует
			if ( $nextItem !== false && $nextItem !== $currentItem ) {

				// === уровень следующего элемента
				$nextLevel = intval( $nextItem['DEPTH_LEVEL'] );

				// === текущий элемент - родитель
				if ( $nextLevel > $currentLevel ) {
					$arTmpTree{'level_' . $currentLevel} = &$arItems{$currentIndex};
				}

			}


			// === текущий элемент - ребенок
			if ( $currentLevel > $thirstLevel ) {

				$childIndex = count( $arTmpTree{'level_' . ( $currentLevel - 1 )}['CHILDS'] );

				$arTmpTree{'level_' . ( $currentLevel - 1 )}['CHILDS']{$currentIndex} = &$arItems{$currentIndex};
				unset( $arItems{$currentIndex} );
			}

			$i++;
		}

		unset( $arTmpTree );

		return $arItems;
	}
	
}