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

$pathNewColors = isset($argv[1]) ? $argv[1] : '';

if ( !empty($pathNewColors) && file_exists($pathNewColors) ) {
	$newColors = include($pathNewColors);
	$addedNewColors = false;
	$lang = isset($argv[2]) ? $argv[2] : '';
	$savePath = isset($argv[3]) ? $argv[3] : '';
	$pathOriginalColors = dirname(__FILE__).'/local/include/colors/colors_'.$lang.'.php';
	if (file_exists($pathOriginalColors)) {
		$originalColors = include($pathOriginalColors);
		if ($originalColors) {
			foreach ($newColors as $code_ => $code_name) {
				if (isset($originalColors[$code_])) {
					$findCodeName = false;
					if (is_array($originalColors[$code_])) {
						$diffColors = array_diff((array)$code_name, $originalColors[$code_]);
						if ($diffColors) {
							$addedNewColors = true;
							$originalColors[$code_] = array_merge($originalColors[$code_], $diffColors);
						}
					} elseif (strncasecmp($originalColors[$code_], $code_name)) {
						$addedNewColors = true;
						$originalColors[$code_][] = $code_name;
					}
				} else {
					$addedNewColors = true;
					$originalColors[$code_] = $code_name;
				}
			}
			if ($addedNewColors) {
				$saveColors = array();
				foreach ($originalColors as $code_ => $code_name) {
					$saveColors[sprintf('0x%06X', $code_)] = $code_name;
				}
				if ($saveColors)
					settings::varExportToFile($saveColors, $savePath);
			}
		}
	} else {
		$error = 'Error: Don\'t find file with original colors!' . PHP_EOL;
	}	
} else {
	$error = 'Error: Don\'t find file with new colors!' . PHP_EOL;
}

// COMMAND: /usr/local/bin/php7.4 "/home/a/altovovu/primovello.altovovu.beget.tech/public_html/mergeNewColors.php" "/path/newcolors.php" RU "/savepath/newcolors.php"

$endTime = microtime(true);
echo $error . "\nStandby script time: " . ($endTime - $startTime) . " Sek.\n"; //вывод результата