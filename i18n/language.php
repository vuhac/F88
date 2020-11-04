<?php
// ----------------------------------------------------------------------------
// Features:	後台--多國語系的功能,可自動偵測目前瀏覽器預設的語系自動找尋該語言檔
// File Name:	language.php
// Author:		Barkley
// Related:
// Log:
// 如果該語系檔案不存在，則以英文語系為預設語系。
// Update:
// 2020.02.03 修改取得支援語系方法 Letter
// ----------------------------------------------------------------------------

// 支援多國語系 , 這段寫在 支援的檔案開頭
require_once dirname(__DIR__) . "/config.php";
// 預設語系採取 HTML ISO Country Codes，格視為 語系(小寫)-國家(大寫)
// 平台語系檔名為小寫(因平台linux、windows不同)，故在此做轉換
$defaultLang = strtolower($config['default_lang']);

// TODO 設定檔進資料庫移除
// 支援語系
global $supportLang;
$supportLang = array(
	"zh-cn" => array(
		"selector" => "选择语系",
		"display" => "简体中文"
	), "en-us" => array(
		"selector" => "Language",
		"display" => "English"
	), "zh-tw" => array(
		"selector" => "選擇語系",
		"display" => "正體中文"
	), "vi-vn" => array(
		"selector" => "Ngôn ngữ",
		"display" => "Việt Nam"
	), "id-id" => array(
		"selector" => "Bahasa",
		"display" => "Indonesia"
	), "th-th" => array(
		"selector" => "ภาษา",
		"display" => "ไทย"
	), "ja-jp" => array(
		"selector" => "言語",
		"display" => "日本語"
	)
);


$lang = NULL;
// TODO 設定檔進資料庫時改寫取得支援語系方法
/**
 *  取得支援語系
 *
 * @return array 系統支援語系
 */
function getSupportLanguages()
{
	global $supportLang;
	return array_keys($supportLang);
}


$valid_lang = getSupportLanguages();
// 有 get 以 get為主
if (isset($_GET['lang'])) {
	$lang = filter_var($_GET['lang'], FILTER_SANITIZE_STRING);
	$_SESSION['lang'] = $lang;
} elseif (isset($_SESSION['lang'])) {
	// 有 session 以 session 為主
	// 平台語系檔名為小寫(因平台linux、windows不同)，故在此做轉換
	$lang = strtolower($_SESSION['lang']);
} else {
	// 先以 zh-cn 為主, 否則測試有時候會失準 by mtchang 2017.11.1
	// 如果該語系在支援的語系內的時候，就用該語系. 否則就用 zh-cn
	if (in_array($lang, $valid_lang, true)) {
		$_SESSION['lang'] = $lang;
	} else {
		$lang = $defaultLang;
		$_SESSION['lang'] = $lang;
	}
}

// 建立系統語系
$systemLang = new Language($lang, $supportLang[$lang]["selector"], $supportLang[$lang]["display"]);

// 語系檔檔名
$langfile = $systemLang->getLangId() . '.php';

// 如果語系檔檔案存在，先 load 該語系檔。
// 避免語系檔沒有寫到的翻譯變數，所以先 load  en_us.php
$i18_base = dirname(__FILE__) . '/';
include($i18_base . 'en-us.php');

// 並依據存在的檔案，變更為該語系, 絕對路徑, 否則會出問題
if (file_exists($i18_base . $langfile)) {
	include($i18_base . $langfile);
} else {
	$langfile = 'en-us.php';
	include($i18_base . $langfile);
}

$i18_base_ui = dirname(__FILE__) . '/' . $config['website_type'] . '/';
if (file_exists($i18_base_ui . $langfile)) {
	include($i18_base_ui . $langfile);
}


// 語系切換選單，寫成一個模組. 提供所有程式選單內使用
function menu_language_choice()
{
	global $supportLang;

	// 紀錄當下的 URL, 切換LANG後可以 return 回到原本的網址
	if ($_SERVER['SERVER_PORT'] == 443) {
		$current_url = 'https://' . $_SERVER['HTTP_HOST'] . $_SERVER['DOCUMENT_URI'];
	} elseif ($_SERVER['SERVER_PORT'] == 80) {
		$current_url = 'http://' . $_SERVER['HTTP_HOST'] . $_SERVER['DOCUMENT_URI'];
	} else {
		$current_url = 'http://' . $_SERVER['HTTP_HOST'] . $_SERVER['DOCUMENT_URI'] . ':' . $_SERVER['SERVER_PORT'];
	}

	// 顯示目前的語言
	$lang = $_SESSION['lang'];
	$systemLang = new Language($lang, $supportLang[$lang]["selector"], $supportLang[$lang]["display"]);
	$show_change_lang = $systemLang->getSelectorTip();
	$show_change_lang_icon = $systemLang->getLangDisplay();

	$_SERVER['QUERY_STRING'] = preg_replace('/lang=' . $lang . '&?/i', '', $_SERVER['QUERY_STRING']);
	$supportLangIds = array_keys($supportLang);
	$langCounts = count($supportLangIds);
	if (isset($_SERVER['QUERY_STRING']) AND $_SERVER['QUERY_STRING'] != '') {
		$sub_url = '';
		for ($i = 0; $i < $langCounts; $i++) {
			$sub_url .= '<li><a href="' . $current_url . '?lang=' . $supportLangIds[$i] . '&' . $_SERVER['QUERY_STRING'] . '" target="_SELF">' . $supportLang[$supportLangIds[$i]]['display'] . '</a></li>';
		}
	} else {
		$sub_url = '';
		for ($i = 0; $i < $langCounts; $i++) {
			$sub_url .= '<li><a href="' . $current_url . '?lang=' . $supportLangIds[$i] . '" target="_SELF">' . $supportLang[$supportLangIds[$i]]['display'] . '</a></li>';
		}
	}

	// 語系切換選單，寫成一個模組. 提供所有程式使用
	$menu_language_content = '
	<ul class="nav navbar-nav navbar-right">
		<li class="dropdown-lang">
			<a href="#" title="' . $show_change_lang . '" class="dropdown-toggle" data-toggle="dropdown" role="button" aria-haspopup="true" aria-expanded="false">
	    		<span>' . $show_change_lang_icon . '</span>
		  		<span class="caret"></span>
		  	</a>
		  	<ul class="dropdown-menu">
				<li class="dropdown-header">' . $show_change_lang . '</li>
				<li role="separator" class="divider"></li>
				' . $sub_url . '
		  	</ul>
		</li>
	</ul>
	';

	return ($menu_language_content);
}


/**
 *  語系類別
 */
class Language
{
	/**
	 * @var string 語系ID
	 */
	private $langId;
	/**
	 * @var string 選項顯示
	 */
	private $selectorTip;
	/**
	 * @var string 顯示名稱
	 */
	private $langDisplay;

	/**
	 * language constructor.
	 *
	 * @param $langId
	 * @param $selectorTip
	 * @param $langDisplay
	 */
	public function __construct($langId, $selectorTip, $langDisplay)
	{
		$this->langId = $langId;
		$this->selectorTip = $selectorTip;
		$this->langDisplay = $langDisplay;
	}

	/**
	 * @return mixed
	 */
	public function getLangId()
	{
		return $this->langId;
	}

	/**
	 * @param mixed $langId
	 */
	public function setLangId($langId): void
	{
		$this->langId = $langId;
	}

	/**
	 * @return mixed
	 */
	public function getSelectorTip()
	{
		return $this->selectorTip;
	}

	/**
	 * @param mixed $selectorTip
	 */
	public function setSelectorTip($selectorTip): void
	{
		$this->selectorTip = $selectorTip;
	}

	/**
	 * @return mixed
	 */
	public function getLangDisplay()
	{
		return $this->langDisplay;
	}

	/**
	 * @param mixed $langDisplay
	 */
	public function setLangDisplay($langDisplay): void
	{
		$this->langDisplay = $langDisplay;
	}

}

?>
