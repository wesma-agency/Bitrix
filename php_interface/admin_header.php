<?
use Bitrix\Main\Page\Asset;
$Asset = Asset::getInstance();
 
CJSCore::Init(array("jquery"));
 
$Asset->addJs('/local/templates/main/js/admin_resort_items.js'); // наш JS
// стили можно тоже подключить файлом, я не заморачивался
?>
<style>
.sort_up, .sort_down {
    font-size:27px;
    font-family:monospace;
    text-decoration:none;
}
.sort_up:hover, .sort_down:hover {
    text-decoration:none;
}
</style>