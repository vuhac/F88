<?php
// ----------------------------------------------------------------------------
// Features:    後台--遊戲管理函式庫
// File Name:   game_management_lib.php
// Author:      Letter
// Related:     game_management.php game_management_action.php
// Log:
// 2020.03.10 #3540 【後台】娛樂城、遊戲多語系欄位實作 - 新增 Letter
// 2020.05.13 Bug #3907 VIP後台娛樂城管理 遊戲混在不對的娛樂城分類裏，前台分類應該是正確。(前後台不一致) 、查詢錯誤 Letter
// ----------------------------------------------------------------------------

require_once dirname(__FILE__) ."/i18n/language.php";

class game_management_lib
{

	/**
	 * game_management_lib constructor.
	 */
	public function __construct(){}


	/**
	 *  取得遊戲語系名稱
	 *
	 * @param string $id ID
	 * @param string $i18n  語系代碼
	 * @param int    $debug 除錯模式，0 為非除錯模式
	 *
	 * @return mixed|string 遊戲名稱
	 */
	public function getDisplayNameByLanguage($id, $i18n, $debug = 0)
	{
		$gameName = '';
		$sql = 'SELECT display_name FROM casino_gameslist WHERE "id" = \''. $id .'\';';
		$result = runSQLall($sql, $debug);
		if ($result[0] > 0) {
			$arr = get_object_vars(json_decode($result[1]->display_name));
			// 取得對應語系顯示名稱
			if (key_exists($i18n, $arr)) {
				$gameName = $arr[$i18n];
			} else {
				$gameName = $arr['default'];
			}
		}
		return $gameName;
	}


	/**
	 *  取得遊戲全語系顯示名稱
	 *
	 * @param string $gameId 遊戲 ID
	 * @param int $debug  除錯模式，0 為非除錯模式
	 *
	 * @return array|mixed 遊戲名稱
	 */
	public function getAllGameName($gameId, $debug = 0)
	{
		$sql = 'SELECT display_name FROM casino_gameslist WHERE "id" = '. $gameId .';';
		$result = runSQLall($sql, $debug);
		if ($result[0] > 0) {
			return json_decode($result[1]->display_name, true);
		} else {
			return array();
		}
	}


	/**
	 *  取得遊戲支援語系及名稱
	 *
	 * @param mixed $gameId ID
	 * @param int $debug 除錯模式，0 為非除錯模式
	 *
	 * @return array 遊戲支援語系及名稱，[ 語系鍵值 => [ display => 語系名稱, name => 遊戲名稱]]
	 *               例: [ ''zn-ch ' => [ 'display' => '简体中文', 'name' => ''简中名称]]
	 */
	public function getGameNameLanguage($gameId, $debug = 0)
	{
		global $supportLang;
		$names = $this->getAllGameName($gameId, $debug);
		$languageKeys = array_keys($supportLang);
		$language = array();
		if (count($languageKeys) > 0) {
			for ($i = 0; $i < count($languageKeys); $i++) {
				$language[$languageKeys[$i]] = [
					'display' => $supportLang[$languageKeys[$i]]['display'],
					'name' => isset($names[$languageKeys[$i]]) ? $names[$languageKeys[$i]]:  $names['en-us']
				];
			}
		}
		return $language;
	}


	/**
	 *  更新遊戲語系顯示名稱
	 *
	 * @param mixed $id 遊戲鍵值
	 * @param string $lang 語系
	 * @param string $name 遊戲名稱
	 * @param int $debug 除錯模式，0 為非除錯模式
	 *
	 * @return int 執行結果
	 *          1 為 更新成功\
	 *          0 為 無語系值\
	 *          -1 為 更新失敗\
	 */
	public function updateGameNameByLanguage($id, $lang, $name, $debug = 0)
	{
		$sql = 'SELECT display_name FROM casino_gameslist WHERE "id" = \''. $id .'\';';
		$gameNames = runSQLall($sql, $debug);
		if ($gameNames[0] > 0 and !empty($lang)) {
			// 取得語系陣列
			$gameNamesArr = json_decode($gameNames[1]->display_name, true);
			$gameNamesArr[$lang] = $name;
			// 轉換 Json
			$updateSql = 'UPDATE casino_gameslist SET display_name = \''. json_encode($gameNamesArr) .'\' WHERE id = '. $id .';';
			return runSQLall($updateSql, $debug)[0];
		} elseif (empty($lang)) { // 沒有語系
			return 0;
		} else { // 找不到遊戲
			return -1; // 回傳錯誤碼
		}
	}


	/**
	 *  用 ID 取得遊戲
	 *
	 * @param mixed $id ID
	 * @param int $debug 除錯模式，0 為非除錯模式
	 *
	 * @return array 遊戲
	 * @throws Exception 時區轉換例外
	 */
	public function getGameById($id, $debug = 0)
	{
		$sql = 'SELECT * FROM casino_gameslist WHERE "id" = \''. $id .'\';';
		$result = runSQLall($sql, $debug);
		if ($result[0] > 0) {
			// 引入函式庫
			$casinoLib = new casino_switch_process_lib();

			// 取得娛樂城
			$casino = $casinoLib->getCasinoByCasinoId($result[1]->casino_id, $debug);

			// 主分類
			$category = '';
			if(!is_null($result[1]->category)){
				if(isset($tr[$result[1]->category])){
					$category = $tr[$result[1]->category];
				}else{
					$category = $result[1]->category;
				}
			}

			// 次分類
			$subCategory = '';
			if(isset($tr[$result[1]->sub_category])){
				$subCategory = $tr[$result[1]->sub_category];
			}else{
				$subCategory = $result[1]->sub_category;
			}

			// 行銷類別
			$ms = json_decode($result[1]->marketing_strategy,'true');

			// 英文名
			$enName = $this->getDisplayNameByLanguage($result[1]->id, 'en-us', $debug);

			// 簡中名
			$cnName = $this->getDisplayNameByLanguage($result[1]->id, 'zh-cn', $debug);

			// 顯示名稱
			$displayName = $this->getDisplayNameByLanguage($result[1]->id, $_SESSION['lang'], $debug);

			// 熱門遊戲
			$hotGame = '';
			if($ms['hotgame'] == '1'){
				$hotGame = 'checked';
			}

			// 啟用開關
			$open = '';
			if($result[1]->open == '1'){
				$open = 'checked';
			}

			// cdn 上的預設 gameicon
			global $config;
			if(strtolower($result[1]->casino_id) == 'mg' && strtolower($result[1]->gameplatform) == 'html5') {
				$gameicon_orign = $config['cdn_login']['url'].$config['cdn_login']['base_path'].'uic/gamesicon/'.strtolower($result[1]->casino_id).strtolower($result[1]->gameplatform).'/'.$result[1]->imagefilename.'.png';
			} else {
				$gameicon_orign = $config['cdn_login']['url'].$config['cdn_login']['base_path'].'uic/gamesicon/'.strtolower($result[1]->casino_id).'/'.$result[1]->imagefilename.'.png';
			}

			// 醒目提醒時間轉換
			$ndTimestamp = $result[1]->notify_datetime;
			$nd = '';
			if (!is_null($ndTimestamp)) {
				$nd = new DateTime($ndTimestamp);
				$nd = $nd->format('Y-m-d H:i');
			}

			// 組成回傳資料
			return array(
				'id' => $result[1]->id,
				'casinoid' => $result[1]->casino_id,
				'favorable' => $result[1]->favorable_type,
				'mct_tag' => $ms['mct'],
				'category' => $category,
				'category2nd' => $ms['category_2nd'],
				'sub_category' => $subCategory,
				'gamename' => $enName,
				'gamename_cn' => $cnName,
				'gamename_fix' => $result[1]->gamename,
				'gamename_cn_fix' => $result[1]->gamename_cn,
				'game_display_name' => $displayName,
				'gameid' => $result[1]->gameid,
				'gameplatform' => $result[1]->gameplatform,
				'hotgame_tag' => $hotGame,
				'marketing_tag' => $ms['marketing_tag'],
				'custom_order' => $result[1]->custom_order,
				'open_tag' => $open,
				'open' => $result[1]->open,
				'gameicon' => ($ms['image']) ? $ms['image'] : $gameicon_orign,
				'notify_datetime' => $nd,
				'casino_short_name' => $casinoLib->getCasinoDefaultName($result[1]->casino_id, $debug),
				'notify' => $casinoLib->getNewAlert($result[1]->notify_datetime),
				'casino_name' => $casino->getDisplayName()
			);
		} else {
			return array();
		}
	}

}