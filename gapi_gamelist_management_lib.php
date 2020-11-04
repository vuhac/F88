<?php
// ----------------------------------------------------------------------------
// Features:	API 遊戲清單邏輯函式庫類
// File Name:	gapi_gamelist_management_lib.php
// Author:		Letter
// Related:     gapi_gamelist_management.php
//              gapi_gamelist_management_action.php
// Class:       gapi_gamelist.php
//              gapi_hall.php
//              gapi_import_gamelist.php
// Log:
// 2019.01.31 新建 Letter
// 2020.03.02 Feature #3540 【後台】娛樂城、遊戲多語系欄位實作 - 娛樂城顯示名稱 Letter
// 2020.04.06 Feature #3540 【後台】娛樂城、遊戲多語系欄位實作 - 遊戲顯示名稱 Letter
// ----------------------------------------------------------------------------

require_once dirname(__FILE__) ."/i18n/language.php";
// 自訂函式庫
require_once dirname(__FILE__) ."/lib.php";
// API 遊戲廠商物件
require_once "gapi_hall.php";
// API 遊戲清單物件
require_once "gapi_gamelist.php";
// 暫存遊戲清單物件
require_once "gapi_import_gamelist.php";
require_once "casino_switch_process_lib.php";

class gapi_gamelist_management_lib
{

	/**
	 *  反水類別與主類別對應關係
	 *
	 * @var string[][]
	 */
	static $mainCategoryToFavorable = array(
		'game' => ['Arcade', 'Features', 'Slots'],
		'fish' => ['Fishing'],
		'live' => ['Live'],
		'lottery' => ['Lottery', 'Progressive'],
		'sports' => ['Sport'],
		'card' => ['Table'],
		'html5' => ['Arcade', 'Features', 'Slots'],
		'lotto' =>  ['Lottery', 'Progressive']
	);

	/**
	 * gapi_gamelist_management_lib constructor.
	 */
	public function __construct(){}


	/**
	 * 生成API簽證
	 *
	 * @param array $data API 所需參數
	 * @param string $apiKey API 鍵值
	 *
	 * @return string API 簽署
	 */
	function generateSign($data, $apiKey)
	{
		ksort($data);
		return md5(http_build_query($data) . $apiKey);
	}


	/**
	 * Gapi 方法
	 *
	 * @param string $method 方法名稱
	 * @param array $data 參數
	 * @param int $debug 是否為除錯模式, 0 為非除錯模式
	 *
	 * @return array 取得返回資料
	 */
	function gapi($method, $data, $debug=0)
	{
		// 設定 socket_timeout , http://php.net/manual/en/soapclient.soapclient.php
		ini_set('default_socket_timeout', 5);

		global $API_CONFIG;
		global $config;

		// Setting restful url
		$url = $API_CONFIG['url'];
		$apiKey = $config['gpk2_apikey'];
		$token = $config['gpk2_token'];

		if ($method == 'GameHallLists') {
			$data['sign'] = $this->generateSign($data, $apiKey);
			$uri = http_build_query($data);
			$url .= '/api/game/halls?' . $uri;
		} elseif ($method == 'GamenameLists') {
			$data['sign'] = $this->generateSign($data, $apiKey);
			$uri = http_build_query($data);
			$url .= '/api/game/game-list?' . $uri;
		}

		if (isset($data)) {
			$ret = array();
			try {
				// HTTP headers
				$headers = ["Content-Type: multipart/form-data", "Authorization: $token"];

				$ch = curl_init($url);
				curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
				curl_setopt($ch, CURLOPT_SSL_VERIFYHOST,  $_SERVER['DOCUMENT_ROOT'] .'/cacert.pem');
				curl_setopt($ch, CURLOPT_SSL_VERIFYPEER,  $_SERVER['DOCUMENT_ROOT'] .'/cacert.pem');
				curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
				curl_setopt($ch, CURLOPT_TIMEOUT, 30);

				$response = curl_exec($ch);

				if ($debug == 1) {
					echo $method."\n";
					echo curl_error($ch);
					var_dump($url);
					var_dump($response);
				}

				if ($response) {
					// Then, after your curl_exec call , 移除 http head 剩下 body
					$body = json_decode($response);

					if ($debug == 1) {
						var_dump($body);
					}

					// 如果 curl 讀取投注紀錄成功的話
					if (isset($body->data) and $body->status->code == 0) {
						// curl 正確
						$ret['curl_status'] = 0;
						// 計算取得的紀錄數量有多少
						$ret['count']    = (is_string($body->data)) ? '1' : count($body->data);
						// 取得紀錄沒有錯誤
						$ret['errorcode'] = 0;
						// 存下 body
						$ret['Status'] = $body->status->code;
						$ret['Result'] = $body->data;
					} else {
						// curl 正確
						$ret['curl_status'] = 0;
						// 計算取得的紀錄數量有多少
						$ret['count']    = (is_string($body->data)) ? '1' : count($body->data);
						// 取得紀錄沒有錯誤
						$ret['errorcode'] = $body->status->code;
						// 存下 body
						$ret['Status'] = $body->status->code;
						$ret['Result'] = $body->status->message;
					}
				} else {
					// curl 錯誤
					$ret['curl_status'] = 1;
					$ret['errorcode'] = curl_errno($ch);
					// 錯誤訊息
					$ret['Result'] = '系统维护中，请稍候再试';
				}
				// 關閉 curl
				curl_close($ch);
			} catch (Exception $e) {
				// curl 錯誤
				$ret['curl_status'] = 1;
				$ret['errorcode'] = 500;
				// 錯誤訊息
				$ret['Result'] = $e->getMessage();
			}
		} else {
			$ret = 'NAN';
		}

		return($ret);
	}


	/**
	 * 讀取遊戲廠商列表
	 *
	 * @param int $debug 是否為除錯模式, 0 為非除錯模式
	 *
	 * @return array 廠商列表
	 */
	public function getGameHalls($debug = 0)
	{
		$data = array();
		$list = $this->gapi('GameHallLists', $data, $debug);
		$halls = array();
		for ($i = 0; $i < count($list['Result']); $i++) {
			$hall = new gapi_hall(
				$i+1,
				$list['Result'][$i]->gamehall,
				$list['Result'][$i]->fullname,
				1
			);
			array_push($halls, $hall);
		}
		return $halls;
	}


	/**
	 * 依娛樂城讀取遊戲清單
	 *
	 * @param string $casino 娛樂城 ID
	 * @param int    $debug 是否為除錯模式, 0 為非除錯模式
	 *
	 * @return array 遊戲清單
	 */
	public function getGameListByCasino($casino = 'all', $debug = 0)
	{
		if ($casino == 'all') {
			$data = array();
		} else {
			$data = array(
				'gamehall' => $casino
			);
		}
		$list = $this->gapi('GamenameLists', $data, $debug);
		$games = array();
		for ($i = 0; $i < count($list['Result']); $i++) {
			$game = new gapi_gamelist(
				$i + 1,
				$list['Result'][$i]->gamehall,
				$list['Result'][$i]->gamecode,
				$list['Result'][$i]->name,
				$list['Result'][$i]->name_cn,
				$list['Result'][$i]->category,
				$list['Result'][$i]->platform,
				'',
				$list['Result'][$i]->sub_gamehall
			);
			array_push($games, $game);
		}
		return $games;
	}


	/**
	 * 取得資料庫娛樂城清單
	 *
	 * @param int $open 是否啟用, 0為關閉, 1為啟用, 2為關閉程序處理中, 大於 2 則取得全部
	 * @param int $debug 是否為除錯模式, 0 為非除錯模式
	 *
	 * @return array 娛樂城清單
	 */
	public function getDBCasinoListByOpen($open = 1, $debug = 0)
	{
		if ($open > 2) {
			$sql = 'SELECT * FROM "casino_list"';
		} else {
			$sql = 'SELECT * FROM "casino_list" WHERE "open" = '. $open .';';
		}
		$result = runSQLall($sql, $debug);
		$casions = array();
		if ($result[0] > 0) {
			for ($i = 1; $i <= $result[0]; $i++) {
				$casino = new gapi_hall(
					$result[$i]->id,
					$result[$i]->casinoid,
					$result[$i]->casino_name,
					0
				);
				array_push($casions, $casino);
			}
		}
		return $casions;
	}


	/**
	 * 讀取暫存資料表內娛樂城遊戲清單
	 *
	 * @param array $tableConfig DataTables 設定參數
	 *                           'cid' => 娛樂城 ID
	 *                           'pageNo' => 現在所在頁碼
	 *                           'pagePerSize' => 每頁項數
	 *                           'sEcho' => 伺服器回應數
	 *                           'search' => 搜尋值
	 *                           'orderDir' => 排序方式
	 *                           'orderCol' => 排序欄位
	 * @param int $debug 是否為除錯模式, 0 為非除錯模式
	 *
	 * @return array 遊戲清單
	 */
	public function getImportGamelistByCasino($tableConfig, $debug = 0)
	{
		// 娛樂城 ID
		$cid = $tableConfig['cid'];
		$update = $tableConfig['update'];

		// 依選擇娛樂城組合 SQL 敘述
		if ($cid == 'all') {
			$sql = 'SELECT * FROM import_gamelist WHERE "open" = 1 ';
		} else {
			$sql = 'SELECT * FROM import_gamelist WHERE "casino_id" = \''. $cid .'\' AND "open" = 1 ' ;
		}

		// 搜尋
		if ($tableConfig['search'] != '') {
			if ($tableConfig['cid'] == 'all') {
				$sql .= 'WHERE ';
			} else {
				$sql .= 'AND ' ;
			}
			$searchTerm = $tableConfig['search'];
			$sql .= <<< SQL
			(
				display_name->>'en-us' ILIKE '%{$this->translateSpecificChar("'", $searchTerm, 0)}%' OR
        		display_name->>'zh-cn' ILIKE '%{$searchTerm}%' OR
        		display_name->>'{$_SESSION['lang']}' ILIKE '%{$this->translateSpecificChar("'", $searchTerm, 0)}%'
     )
SQL;
		}

		// 排序欄位
		$colToNameMap = array(
			0 => 'id',
			1 => 'casino_id',
			2 => 'category',
			3 => 'category_name',
			4 => 'gamename',
			5 => 'gamename_cn',
			6 => 'language_name',
			7 => 'gameplatform',
			8 => 'open',
			9 => '', // 按鈕健不排序
			10 => ''  // 按鈕健不排序
		);
		$sql .= ' ORDER BY '. $colToNameMap[$tableConfig['orderCol']];

		// 排序順序
		$sql .= ' '. strtoupper($tableConfig['orderDir']);

		// 取得總數
		$total = runSQL($sql.';');
		// 所有紀錄數量
		$page['all_records'] = $total;

		// 組合分頁參數
		$sql .= ' OFFSET '. $tableConfig['pageNo'] .' LIMIT '. $tableConfig['pagePerSize'] .';';
		$result = runSQLall($sql, $debug);

		// 批次同步
		$batchSync = array();
		// 生成遊戲清單物件
		$games = array();
		if ($result[0] > 0) {
			for ($i = 1; $i <= $result[0]; $i++) {
				$game = new gapi_import_gamelist(
					$result[$i]->id,
					$result[$i]->category,
					$result[$i]->sub_category,
					$result[$i]->gametype,
					$this->translateSpecificChar("'", $this->getDisplayNameByLanguage($result[$i]->display_name, 'en-us'), 1),
					$result[$i]->gameid,
					$result[$i]->gameplatform,
					$this->getDisplayNameByLanguage($result[$i]->display_name, 'zh-cn'),
					$result[$i]->imagefilename,
					json_decode($result[$i]->marketing_strategy, true),
					$result[$i]->casino_id,
					$result[$i]->note,
					$result[$i]->open,
					$result[$i]->moduleid,
					$result[$i]->clientid,
					$result[$i]->favorable_type,
					$result[$i]->slot_line,
					$result[$i]->custom_icon,
					$result[$i]->custom_order,
					$result[$i]->category_cn,
					$result[$i]->is_new,
					$this->getCasinoName($result[$i]->casino_id),
					$this->getMCTCategoryName(json_decode($result[$i]->marketing_strategy, true)['mct']),
					'',
					$this->getGameCategoryName($result[$i]->category),
					json_decode($result[$i]->display_name, true),
					$this->translateSpecificChar("'", $this->getDisplayNameByLanguage($result[$i]->display_name,
						$_SESSION['lang']), 1)
				);
				array_push($games, $game);

				if ($game->getIsNew() == 1) {
					array_push($batchSync, $game->getId());
				}
			}
		}

		// 組成回傳資料
		$dataTableArr = array(
			"sEcho" => intval($tableConfig['sEcho']),
			'iTotalRecords' => intval($tableConfig['pagePerSize']),
			'iTotalDisplayRecords' => intval($page['all_records']),
			'data' => $games,
			'update' => $update,
			'batchSync' => $batchSync
		);

		return $dataTableArr;
	}


	/**
	 * 以娛樂城取得匯入遊戲
	 *
	 * @param string $casinoId  娛樂城 ID
	 * @param int    $debug       是否為除錯模式, 0 為非除錯模式
	 * @param int    $open         遊戲狀態
	 *                                         0  = 關閉  1 = 開啟  3 = 全取
	 *
	 * @return array 匯入遊戲清單
	 */
	public function getImportGamesByCasinoId(string $casinoId, $debug = 0, $open = 3)
	{
		global $tr;

		if ($open == 3) {
			$sql = 'SELECT * FROM import_gamelist WHERE "casino_id" = \''. $casinoId .'\';';
		} else {
			$sql = 'SELECT * FROM import_gamelist WHERE "casino_id" = \''. $casinoId .'\' AND "open" = '. $open .';';
		}

		$result = runSQLall($sql, $debug);
		$games = array();
		if ($result[0] > 0) {
			for ($i = 1; $i <= $result[0]; $i++) {
				$game = new gapi_import_gamelist(
					$result[$i]->id,
					$result[$i]->category,
					$result[$i]->sub_category,
					$result[$i]->gametype,
					$this->translateSpecificChar("'", $this->getDisplayNameByLanguage($result[$i]->display_name, 'en-us'),
						1),
					$result[$i]->gameid,
					$result[$i]->gameplatform,
					$this->getDisplayNameByLanguage($result[$i]->display_name, 'zh-cn'),
					$result[$i]->imagefilename,
					json_decode($result[$i]->marketing_strategy, true),
					$result[$i]->casino_id,
					$result[$i]->note,
					$result[$i]->open,
					$result[$i]->moduleid,
					$result[$i]->clientid,
					$result[$i]->favorable_type,
					$result[$i]->slot_line,
					$result[$i]->custom_icon,
					$result[$i]->custom_order,
					$result[$i]->category_cn,
					$result[$i]->is_new,
					$this->getCasinoName($result[$i]->casino_id),
					$this->getMCTCategoryName(json_decode($result[$i]->marketing_strategy, true)['mct']),
					'',
					$this->getGameCategoryName($result[$i]->category),
					json_decode($result[$i]->display_name, true),
					$this->translateSpecificChar("'", $this->getDisplayNameByLanguage($result[$i]->display_name,
						$_SESSION['lang']), 1)
				);
				array_push($games, $game);
			}
		}
		return $games;
	}


	/**
	 * 是否是第一次進入管理頁
	 *
	 * @return bool 判斷結果
	 */
	public function isFirstTime()
	{
		$sql = 'SELECT "id" FROM import_gamelist;';
		return runSQL($sql) == 0;
	}


	/**
	 * 新的 Gamelist 物件寫入資料庫
	 *
	 * @param array $gamelist Gamelist 物件
	 * @param int   $debug 是否為除錯模式, 0 為非除錯模式
	 */
	public function insertNewGamelist(array $gamelist, $debug = 0)
	{
		for ($i = 0; $i < count($gamelist); $i++) {
			if (!$this->isExistImportGame($gamelist[$i]->getGamecode(), $gamelist[$i]->getGamehall())) {
				$this->createImportGame($gamelist[$i], $debug);
			}
		}
	}


	/**
	 *  依據 API 遊戲清單，重新開啟匯入遊戲
	 *
	 * @param array $gamelist API遊戲清單
	 * @param int   $debug 是否為除錯模式, 0 為非除錯模式
	 */
	public function reopenImportGamelist(array $gamelist, $debug = 0)
	{
		for ($i = 0; $i < count($gamelist); $i++) {
			if ($this->isExistImportGame($gamelist[$i]->getGamecode(), $gamelist[$i]->getGamehall())) {
				$importGame = $this->getImportGameByCasinoAndGameId($gamelist[$i]->getGamehall(), $gamelist[$i]->getGamecode());
				if ($importGame->getOpen() == 0 or $importGame->getOpen() == 2) {
					$importGame->setOpen(1);
					$this->updateImportGame($importGame, $debug);
				}
			}
		}
	}


	/**
	 * 取得匯入遊戲
	 *
	 * @param string $casino 娛樂城
	 * @param string $gameId 遊戲 ID
	 * @param int    $debug  是否為除錯模式, 0 為非除錯模式
	 *
	 * @return gapi_import_gamelist|null 匯入遊戲物件，若無回傳 null
	 */
	public function getImportGameByCasinoAndGameId(string $casino, string $gameId, $debug = 0)
	{
		global $tr;
		$sql = 'SELECT * FROM import_gamelist WHERE "casino_id" = \''. $casino .'\' AND "gameid" = \''. $gameId .'\'';
		$result = runSQLall($sql, $debug);
		if ($result[0] > 0) {
			return new gapi_import_gamelist(
				$result[1]->id,
				$result[1]->category,
				$result[1]->sub_category,
				$result[1]->gametype,
				$this->translateSpecificChar("'", $this->getDisplayNameByLanguage($result[1]->display_name, 'en-us'),
					1),
				$result[1]->gameid,
				$result[1]->gameplatform,
				$this->getDisplayNameByLanguage($result[1]->display_name, 'zh-cn'),
				$result[1]->imagefilename,
				json_decode($result[1]->marketing_strategy, true),
				$result[1]->casino_id,
				$result[1]->note,
				$result[1]->open,
				$result[1]->moduleid,
				$result[1]->clientid,
				$result[1]->favorable_type,
				$result[1]->slot_line,
				$result[1]->custom_icon,
				$result[1]->custom_order,
				$result[1]->category_cn,
				$result[1]->is_new,
				$this->getCasinoName($result[1]->casino_id),
				$this->getMCTCategoryName(json_decode($result[1]->marketing_strategy, true)['mct']),
				isset($tr[$result[1]->sub_category]) ? $tr[$result[1]->sub_category] : $result[1]->sub_category,
				$this->getGameCategoryName($result[1]->category),
				json_decode($result[1]->display_name, true),
				$this->translateSpecificChar("'", $this->getDisplayNameByLanguage($result[1]->display_name,
					$_SESSION['lang']), 1)
			);
		} else {
			return null;
		}
	}


	/**
	 * 寫入 import gamelist
	 *
	 * @param gapi_gamelist $apiGame API 遊戲清單
	 * @param int           $debug 是否為除錯模式, 0 為非除錯模式
	 */
	public function createImportGame(gapi_gamelist $apiGame, $debug = 0)
	{
		$sql = 'INSERT INTO "import_gamelist" ';
		$params = [];
		$keys = [];
		// 轉換 Gapi 遊戲 至 匯入遊戲物件
		$importGamelist = $this->genImportGamelistFromApiGamelist($apiGame);
		// 組 SQL
		foreach ($importGamelist->getKeyValueMap() as $key => $value) {
			// 不須放入 SQL 參數
			if (array_keys($value)[0] == 'id' OR array_keys($value)[0] == 'casino_name' OR
				array_keys($value)[0] == 'category_name' OR array_keys($value)[0] == 'sub_category_name' or
				array_keys($value)[0] == 'game_category_name' OR array_keys($value)[0] == 'language_name') continue;
			array_push($keys, array_keys($value)[0]);
		}
		$sql .= '('. implode(' ,', $keys) .') VALUES ';
		foreach ($importGamelist->getKeyValueMap() as $key => $value) {
			// 不須放入 SQL 參數
			if (array_keys($value)[0] == 'id' OR array_keys($value)[0] == 'casino_name' OR
				array_keys($value)[0] == 'category_name' OR array_keys($value)[0] == 'sub_category_name' or
				array_keys($value)[0] == 'game_category_name' OR array_keys($value)[0] == 'language_name') continue;
			// json 格式欄位
			if (array_keys($value)[0] == 'marketing_strategy') {
				array_push($params, '\''. array_values($value)[0] .'\'');
			} elseif (array_keys($value)[0] == 'display_name') {
				array_push($params, '\''. $this->translateSpecificChar("'", array_values($value)[0], 0) .'\'');
			} elseif (array_keys($value)[0] == 'casino_id') {
				array_push($params, '\''. array_values($value)[0] .'\'');
			} elseif (array_keys($value)[0] == 'gameplatform') {
				$platform = array_values($value)[0];
				if ($platform == 'mobile' or $platform == 'web') {
					$platform = 'html5';
				}
				array_push($params, '\''. $platform .'\'');
			} else {
				if (is_null(array_values($value)[0])) {
					// 處理 NULL
					$item = 'NULL';
				} elseif (is_string(array_values($value)[0])) {
					// 字串欄位加上單引號
					if (strpos(array_values($value)[0], '\'')) {
						$preValue = str_replace('\'', '\'\'', array_values($value)[0]);
					} else {
						$preValue = array_values($value)[0];
					}
					$item = '\''.$preValue.'\'';
				} else {
					$item = array_values($value)[0];
				}
				array_push($params, $item);
			}
		}
		$sql .= '('. implode(' ,', $params) . ')' . ';';
		return runSQL($sql, $debug);
	}


	/**
	 * 轉換 Gapi gamelist 為 importGamelist
	 *
	 * @param gapi_gamelist $gamelist Gapi gamelist 物件
	 *
	 * @return gapi_import_gamelist importGamelist 物件
	 */
	public function genImportGamelistFromApiGamelist(gapi_gamelist $gamelist)
	{
		$ms = array(
			'mct' => $this->transApiCategoryToImportMct($gamelist->getCategory()),
			'cname' => '',
			'ename' => '',
			'image' => '',
			'hotgame' => '0',
			'freetrial' => '0',
			'category_2nd' => '',
			'marketing_tag' => ''
		);

		// 語系名稱
		global $supportLang;
		$langKeys = array_keys($supportLang);
		// 預設名稱(無語系)
		$display = array('default' => $gamelist->getGamecode());
		// 英文名稱
		$enName = $this->translateSpecificChar("'", $gamelist->getName(), 0);

		for ($i = 0; $i < count($langKeys); $i++) {
			if ($langKeys[$i] == 'zh-cn' or $langKeys[$i] == 'zh-tw') {
				$display[$langKeys[$i]] = $gamelist->getNameCn();
			} else {
				$display[$langKeys[$i]] = $enName;
			}
		}

		return new gapi_import_gamelist(
			0, '', NULL,	'', $enName,
			$gamelist->getGamecode(), $gamelist->getPlatform(), $gamelist->getNameCn(), '', json_encode($ms),
			$gamelist->getGamehall(), NULL, 0, NULL, NULL, NULL, NULL,
			NULL, 0, '', 1, $this->getCasinoName($gamelist->getGamehall()),
			'', '', '', json_encode($display),
			$this->getCurrentLanguageDisplayName(json_encode($display, true), $_SESSION['lang']));
	}


	/**
	 *  轉換分類
	 *
	 * @param string $category api 分類
	 *
	 * @return string 匯入遊戲分類
	 */
	public function transApiCategoryToImportMct(string $category)
	{
		switch ($category) {
			case 'board':
				$mct = 'Chessboard';
				break;
			case 'electronic':
				$mct = 'game';
				break;
			default:
				$mct = ucfirst($category);
				break;
		}
		return $mct;
	}


	/**
	 * 取得娛樂城名稱
	 *
	 * @param string $casinoId 娛樂城 ID
	 * @param int $debug 是否為除錯模式, 0 為非除錯模式
	 *
	 * @return string 娛樂城名稱
	 */
	public function getCasinoName(string $casinoId, $debug = 0)
	{
		$sql = 'SELECT display_name FROM casino_list WHERE casinoid = \''. strtoupper($casinoId) .'\';';
		$result = runSQLall($sql, $debug);
		if ($result[0] > 0) {
			$name = $this->getCurrentLanguageDisplayName($result[1]->display_name, $_SESSION['lang']);
		} else {
			$name = '';
		}
		return $name;
	}


	/**
	 *  依目前語系取得娛樂城顯示名稱
	 *
	 * @param mixed $displayNames 語系顯示名稱
	 * @param mixed $i18n 語系
	 *
	 * @return mixed 目前語系顯示名稱，若該語系無顯示名稱，回覆預設顯示名稱
	 */
	public function getCurrentLanguageDisplayName($displayNames, $i18n)
	{

		$i18nNameArr = get_object_vars(json_decode($displayNames));

		// 取得對應語系顯示名稱
		if (key_exists($i18n, $i18nNameArr)) {
			$display = $i18nNameArr[$i18n];
		} else {
			$display = $i18nNameArr['en-us'];
		}
		return $display;
	}


	/**
	 * 比對API 娛樂城是否為新
	 *
	 * @param string $casinoId 娛樂城 ID
	 *
	 * @return bool 新娛樂城回傳 True
	 */
	public function isNewCasinoToDB(string $casinoId)
	{
		return is_null($this->getCasinoById($casinoId));
	}


	/**
	 * 娛樂城是否為關閉狀態
	 *
	 * @param string $casinoId 娛樂城 ID
	 *
	 * @return bool True 為娛樂城關閉
	 */
	public function isCloseCasino(string $casinoId)
	{
		$casino = $this->getCasinoById($casinoId);
		return !is_null($casino) ? $casino->open == 0 : false;
	}


	/**
	 * 利用 casino id 取得娛樂城
	 *
	 * @param string $casinoId 娛樂城 ID
	 * @param int    $debug 是否為除錯模式, 0 為非除錯模式
	 *
	 * @return array 娛樂城
	 */
	public function getCasinoById(string $casinoId, $debug = 0)
	{
		$sql = 'SELECT * FROM casino_list WHERE "casinoid" =\''. strtoupper($casinoId) .'\'';
		$result = runSQLall($sql, $debug);
		return $result[0] > 0 ? $result[1] : null;
	}


	/**
	 * 是否存在匯入遊戲
	 *
	 * @param string $gameId 遊戲 ID
	 * @param string $casinoId 娛樂城 ID
	 * @param int    $debug 是否為除錯模式, 0 為非除錯模式
	 *
	 * @return bool 是否存在遊戲
	 */
	public function isExistImportGame(string $gameId, string $casinoId, $debug = 0)
	{

		$sql = 'SELECT "id" FROM import_gamelist WHERE "gameid" = \''. $gameId.'\' AND "casino_id" = \''. $casinoId.'\';';
		$game = runSQL($sql, $debug);
		return $game > 0;
	}


	/**
	 * 是否存在平台遊戲
	 *
	 * @param string $gameId 遊戲 ID
	 * @param string $casinoId 娛樂城 ID
	 * @param int    $debug 是否為除錯模式, 0 為非除錯模式
	 *
	 * @return int 是否存在遊戲, 存在回傳 ID, 否則回傳 0
	 */
	public function isExistGame(string $gameId, string $casinoId, $debug = 0)
	{
		$sql = 'SELECT "id" FROM casino_gameslist WHERE "gameid" = \''. $gameId.'\' AND "casino_id" = \''
			. strtoupper($casinoId) .'\';';
		$game = runSQLall($sql, $debug);
		return $game[0] > 0 ? $game[1]->id : 0;
	}


	/**
	 * 用 ID 取得 匯入遊戲
	 *
	 * @param int $id ID
	 * @param int $debug 是否為除錯模式, 0 為非除錯模式
	 *
	 * @return gapi_import_gamelist|null 匯入遊戲物件
	 */
	public function getImportGameById(int $id, $debug = 0)
	{
		global $tr;
		$sql = 'SELECT * FROM import_gamelist WHERE "id" = '. $id .';';
		$result = runSQLall($sql, $debug);
		if ($result[0] > 0) { // 若有取得轉換為 匯入遊戲 物件
			$game = new gapi_import_gamelist(
				$result[1]->id,
				$result[1]->category,
				$result[1]->sub_category,
				$result[1]->gametype,
				$this->translateSpecificChar("'", $this->getDisplayNameByLanguage($result[1]->display_name, 'en-us'),
					1),
				$result[1]->gameid,
				$result[1]->gameplatform,
				$this->getDisplayNameByLanguage($result[1]->display_name, 'zh-cn'),
				$result[1]->imagefilename,
				json_decode($result[1]->marketing_strategy, true),
				$result[1]->casino_id,
				$result[1]->note,
				$result[1]->open,
				$result[1]->moduleid,
				$result[1]->clientid,
				$result[1]->favorable_type,
				$result[1]->slot_line,
				$result[1]->custom_icon,
				$result[1]->custom_order,
				$result[1]->category_cn,
				$result[1]->is_new,
				$this->getCasinoName($result[1]->casino_id),
				$this->getMCTCategoryName(json_decode($result[1]->marketing_strategy, true)['mct']),
				isset($tr[$result[1]->sub_category]) ? $tr[$result[1]->sub_category] : $result[1]->sub_category,
				$this->getGameCategoryName($result[1]->category),
				json_decode($result[1]->display_name, true),
				$this->translateSpecificChar("'", $this->getDisplayNameByLanguage($result[1]->display_name,
					$_SESSION['lang']), 1)
			);
		} else {
			$game = null;
		}
		return $game;
	}


	/**
	 * 取得分類名稱
	 *
	 * @param string $category 分類代號
	 *
	 * @return string 分類名稱
	 */
	public function getMCTCategoryName(string $category)
	{
		global $tr;
		global $gamelobby_setting;
		switch ($category) {
			case 'Chessboard':
				$name = $tr[$gamelobby_setting['main_category_info']['Chessboard']['name']];
				break;
			case 'game':
				$name = $tr[$gamelobby_setting['main_category_info']['game']['name']];
				break;
			case 'Fishing':
				$name = $tr[$gamelobby_setting['main_category_info']['Fishing']['name']];
				break;
			case 'Live':
				$name = $tr[$gamelobby_setting['main_category_info']['Live']['name']];
				break;
			case 'Lottery':
				$name = $tr[$gamelobby_setting['main_category_info']['Lottery']['name']];
				break;
			case 'Sport':
				$name = $tr[$gamelobby_setting['main_category_info']['Sport']['name']];
				break;
			default:
				$name = '';
				break;
		}
		return $name;
	}


	/**
	 * 更新 匯入遊戲
	 *
	 * @param gapi_import_gamelist $game 匯入遊戲物件
	 * @param int                  $debug 是否為除錯模式, 0 為非除錯模式
	 *
	 * @return int 成功更新回傳 1
	 */
	public function updateImportGame(gapi_import_gamelist $game, $debug = 0)
	{
		// 組成更新 sql
		$sql = 'UPDATE import_gamelist SET ';
		foreach ($game->getKeyValueMap() as $key => $value) {
			if (array_keys($value)[0] == 'id' or array_keys($value)[0] == 'casino_name' or
				array_keys($value)[0] == 'category_name' or array_keys($value)[0] == 'sub_category_name' or
				array_keys($value)[0] == 'is_new' or array_keys($value)[0] == 'game_category_name' or
				array_keys($value)[0] == 'language_name') { // 不須更新欄位
				continue;
			} elseif (array_keys($value)[0] == 'marketing_strategy') { // json格式欄位
				$sql .= '"'. array_keys($value)[0] .'" = \''. json_encode(array_values($value)[0]) .'\', ';
			} elseif (array_keys($value)[0] == 'display_name') { // json格式欄位
				$displayArr = array_values($value)[0];
				foreach ($displayArr as $k => $v) {
					$displayArr[$k] = $this->translateSpecificChar("'", $displayArr[$k], 0);
				}
				$sql .= '"'. array_keys($value)[0] .'" = \''. json_encode($displayArr) .'\', ';
			} elseif (array_keys($value)[0] == 'gamename') {
				$strValue = $this->translateSpecificChar("'", array_values($value)[0], 0);
				$sql .= '"'. array_keys($value)[0] .'" = \''. $strValue .'\', ';
			} else {
				if (is_null(array_values($value)[0])) { // 處理 null
					$null = 'NULL';
					$sql .= '"'. array_keys($value)[0] .'" = '. $null .', ';
				} else {
					$sql .= '"'. array_keys($value)[0] .'" = \''. array_values($value)[0] .'\', ';
				}
			}
		}
		// 去除最後逗點
		$sql = substr($sql, 0, strlen($sql) - 2);
		$sql .= ' WHERE "id" = '. $game->getId() .';';
		return runSQL($sql, $debug);
	}


	/**
	 * 同步新增匯入遊戲至平台
	 *
	 * @param gapi_import_gamelist $game 匯入遊戲
	 * @param int $debug 是否為除錯模式, 0 為非除錯模式
	 *
	 * @return int 是否新增成功, 成功回傳 1
	 */
	public function syncCreateGame(gapi_import_gamelist $game, $debug = 0)
	{
		$sql = 'INSERT INTO "casino_gameslist" ';
		$params = [];
		$keys = [];
		foreach ($game->getKeyValueMap() as $key => $value) {
			if (array_keys($value)[0] == 'id' OR array_keys($value)[0] == 'casino_name' OR
				array_keys($value)[0] == 'category_name' OR array_keys($value)[0] == 'sub_category_name' OR
				array_keys($value)[0] == 'is_new' or array_keys($value)[0] == 'game_category_name' OR
				array_keys($value)[0] == 'language_name'
			) continue;
			if (array_keys($value)[0] == 'game_category_name') {
				array_push($keys, 'category_cn');
			} else {
				array_push($keys, array_keys($value)[0]);
			}
		}
		$sql .= '('. implode(' ,', $keys) .') VALUES ';
		foreach ($game->getKeyValueMap() as $key => $value) {
			if (array_keys($value)[0] == 'id' OR array_keys($value)[0] == 'casino_name' OR
				array_keys($value)[0] == 'category_name' OR array_keys($value)[0] == 'sub_category_name' OR
				array_keys($value)[0] == 'is_new' or array_keys($value)[0] == 'game_category_name' OR
				array_keys($value)[0] == 'language_name') continue;
			if (array_keys($value)[0] == 'marketing_strategy') {
				array_push($params, '\'' . json_encode(array_values($value)[0]) . '\'');
			} elseif (array_keys($value)[0] == 'display_name') {
				$displayArr = array_values($value)[0];
				foreach ($displayArr as $k => $v) {
					$displayArr[$k] = $this->translateSpecificChar("'", $v, 0);
				}
				array_push($params, '\'' . json_encode($displayArr) . '\'');
			} elseif (array_keys($value)[0] == 'category') {
				array_push($params, '\'' . ucfirst(array_values($value)[0]) . '\'');
			} elseif (array_keys($value)[0] == 'casino_id') {
				array_push($params, '\'' . strtoupper(array_values($value)[0]) . '\'');
			} else {
				if (is_null(array_values($value)[0])) {
					$item = 'NULL';
				} elseif (is_string(array_values($value)[0])) {
					if (strpos(array_values($value)[0], '\'')) {
						$preValue = str_replace('\'', '\'\'', array_values($value)[0]);
					} else {
						$preValue = array_values($value)[0];
					}
					$item = '\''.$preValue.'\'';
				} else {
					$item = array_values($value)[0];
				}
				array_push($params, $item);
			}
		}
		$sql .= '('. implode(' ,', $params) . ')' . ';';
		return runSQL($sql, $debug);
	}


	/**
	 * 同步更新匯入遊戲至平台
	 *
	 * @param gapi_import_gamelist $game 匯入遊戲
	 * @param int $id 存在平台遊戲 ID
	 * @param int $debug 是否為除錯模式, 0 為非除錯模式
	 *
	 * @return int 是否新增成功, 成功回傳 1
	 */
	public function syncUpdateGame(gapi_import_gamelist $game, $id, $debug = 0)
	{
		// 取得原有平台遊戲
		$old_game = $this->getGameByCasinoAndGameId($game->getGameid(), $game->getCasinoId());
		// 組 SQL
		$sql = 'UPDATE casino_gameslist SET ';
		foreach ($game->getKeyValueMap() as $key => $value) {
			if (array_keys($value)[0] == 'id' or array_keys($value)[0] == 'casino_name' or
				array_keys($value)[0] == 'category_name' or array_keys($value)[0] == 'sub_category_name' or
				array_keys($value)[0] == 'is_new' or array_keys($value)[0] == 'game_category_name' OR
				array_keys($value)[0] == 'language_name') { // 不須更新欄位
				continue;
			} elseif (array_keys($value)[0] == 'marketing_strategy') { // json格式欄位
				$imgLink = $old_game->getMarketingStrategy()['image'];
				$ms = array_values($value)[0];
				$ms['image'] = $imgLink;
				$sql .= '"'. array_keys($value)[0] .'" = \''. json_encode($ms) .'\', ';
			} elseif (array_keys($value)[0] == 'display_name') { // json格式欄位
				$displayArr = array_values($value)[0];
				foreach ($displayArr as $k => $v) {
					$displayArr[$k] = $this->translateSpecificChar("'", $v, 0);
				}
				$sql .= '"'. array_keys($value)[0] .'" = \''. json_encode($displayArr) .'\', ';
			} elseif (array_keys($value)[0] == 'gamename') {
				$sql .= '"'. array_keys($value)[0] .'" = \''. str_replace('\'', '\'\'', array_values($value)[0]) .'\', ';
			} elseif (array_keys($value)[0] == 'category') { // category 首字大寫
				$sql .= '"'. array_keys($value)[0] .'" = \''. ucfirst(array_values($value)[0]) .'\', ';
			} elseif (array_keys($value)[0] == 'casino_id') { // casino_id 全大寫
				$sql .= '"'. array_keys($value)[0] .'" = \''. strtoupper(array_values($value)[0]) .'\', ';
			} elseif (array_keys($value)[0] == 'imagefilename') {
				if (empty(array_values($value)[0]) OR is_null(array_values($value)[0])) {
					if ($old_game->getImagefilename() == 'undefined') {
						$sql .= '"'. array_keys($value)[0] .'" = \'\', ';
					} else {
						$sql .= '"'. array_keys($value)[0] .'" = \''. $old_game->getImagefilename() .'\', ';
					}
				} else {
					$sql .= '"'. array_keys($value)[0] .'" = \''. array_values($value)[0] .'\', ';
				}
			} else {
				if (is_null(array_values($value)[0])) { // 處理 null
					$null = 'NULL';
					$sql .= '"'. array_keys($value)[0] .'" = '. $null .', ';
				} else {
					$sql .= '"'. array_keys($value)[0] .'" = \''. array_values($value)[0] .'\', ';
				}
			}
		}
		// 去除最後逗點
		$sql = substr($sql, 0, strlen($sql) - 2);
		$sql .= ' WHERE "id" = '. $id .';';
		return runSQL($sql, $debug);
	}


	/**
	 * 用 遊戲 id 和 娛樂城 id 取得平台遊戲
	 *
	 * @param string $gameId 遊戲 id
	 * @param string $casinoId 娛樂城 id
	 * @param int $debug 是否為除錯模式, 0 為非除錯模式
	 *
	 * @return gapi_import_gamelist 轉換為匯入遊戲物件之平台遊戲, 無則回傳 null
	 */
	public function getGameByCasinoAndGameId(string $gameId, string $casinoId, $debug = 0)
	{
		global $tr;
		$sql = 'SELECT * FROM casino_gameslist WHERE "gameid" = \''. $gameId .'\' AND "casino_id" = \''
			. strtoupper($casinoId) .'\';';
		$result = runSQLall($sql, $debug);
		if ($result[0] > 0) {
			$game = new gapi_import_gamelist(
				$result[1]->id,
				$result[1]->category,
				is_null($result[1]->sub_category) ? ' ' : $result[1]->sub_category,
				$result[1]->gametype,
				$this->translateSpecificChar("'", $this->getDisplayNameByLanguage($result[1]->display_name, 'en-us'),
					1),
				$result[1]->gameid,
				$result[1]->gameplatform,
				$this->getDisplayNameByLanguage($result[1]->display_name, 'zh-cn'),
				$result[1]->imagefilename,
				json_decode($result[1]->marketing_strategy, true),
				$result[1]->casino_id,
				$result[1]->note,
				$result[1]->open,
				$result[1]->moduleid,
				$result[1]->clientid,
				$result[1]->favorable_type,
				$result[1]->slot_line,
				$result[1]->custom_icon,
				$result[1]->custom_order,
				$result[1]->category_cn,
				0, // 更新表示原來存在遊戲, 更新後狀態為已同步
				$this->getCasinoName($result[1]->casino_id),
				$this->getMCTCategoryName(json_decode($result[1]->marketing_strategy, true)['mct']),
				isset($tr[$result[1]->sub_category]) ? $tr[$result[1]->sub_category] : $result[1]->sub_category,
				$this->getGameCategoryName($result[1]->category),
				json_decode($result[1]->display_name, true),
				$this->translateSpecificChar("'", $this->getDisplayNameByLanguage($result[1]->display_name,
					$_SESSION['lang']), 1)
			);
		} else {
			$game = null;
		}
		return $game;
	}


	/**
	 * 更新匯入遊戲狀態
	 *
	 * @param int $state 狀態, 0 為已同步, 1為未同步
	 * @param int $id 匯入遊戲 ID
	 * @param int $debug 是否為除錯模式, 0 為非除錯模式
	 *
	 * @return int 更新成功回傳 1
	 */
	public function updateImportGameState(int $state, int $id, $debug = 0)
	{
		$sql = 'UPDATE import_gamelist SET "is_new" = '. $state .' WHERE "id" = '. $id .';';
		return runSQL($sql, $debug);
	}


	/**
	 * 取得平台遊戲清單之技術 (gameplatform) 欄位值
	 *
	 * @param int $debug 是否為除錯模式, 0 為非除錯模式
	 *
	 * @return array|null
	 */
	public function getGamelistGameplatform($debug = 0)
	{
		$sql = 'SELECT "value" FROM "root_protalsetting" WHERE "name" = \'game_platforms\';';
		$result = runSQLall($sql, $debug);
		if ($result[0] > 0) {
			return json_decode($result[1]->value, true);
		} else {
			return array();
		}
	}


	/**
	 * 比對匯入遊戲遊戲平台是否與平台遊戲清單相符
	 *
	 * @param string $platform 匯入遊戲遊戲平台
	 *
	 * @return bool True 為相符
	 */
	public function isMatchedPlatform(string $platform)
	{
		$match = false;
		$platforms = $this->getGamelistGameplatform();
		for ($i = 1; $i <= count($platforms) - 1; $i++) {
			if ($platform == $platforms[$i]) {
				$match = true;
				break;
			}
		}
		return $match;
	}


	/**
	 * 取得平台遊戲種類
	 *
	 * @param int $debug 是否為除錯模式, 0 為非除錯模式
	 *
	 * @return array 遊戲種類
	 */
	public function getGamelistCategory($debug = 0)
	{
		$sql = 'SELECT "value" FROM "root_protalsetting" WHERE "name" = \'game_categories\';';
		$result = runSQLall($sql, $debug);
		return json_decode($result[1]->value, true);
	}


	/**
	 * 比對匯入遊戲遊戲種類是否與平台遊戲種類相符
	 *
	 * @param mixed $category 遊戲種類
	 *
	 * @return bool True 為相符
	 */
	public function isMatchedCategory($category)
	{
		$categories = $this->getGamelistCategory();
		$exist = false;
		if (count($categories) > 0) {
			for ($i = 1; $i <= count($categories) - 1; $i++) {
				if ($category == $categories[$i]) {
					$exist = true;
					break;
				}
			}
		}
		return $exist;
	}


	/**
	 * 同步平台遊戲至匯入遊戲
	 *
	 * @param gapi_import_gamelist $platformGame
	 * @param gapi_import_gamelist $importGame
	 * @param int                  $debug
	 *
	 * @return int
	 */
	public function updateImportGameByPlatformGame(gapi_import_gamelist $platformGame, gapi_import_gamelist $importGame, $debug = 0)
	{

		// 組成更新 sql
		$sql = 'UPDATE import_gamelist SET ';
		foreach ($platformGame->getKeyValueMap() as $key => $value) {
			if (array_keys($value)[0] == 'id' or array_keys($value)[0] == 'casino_name' or
				array_keys($value)[0] == 'category_name' or array_keys($value)[0] == 'sub_category_name' or
				array_keys($value)[0] == 'casino_id' or array_keys($value)[0] == 'gameid'or
				array_keys($value)[0] == 'game_category_name' or array_keys($value)[0] == 'language_name') { // 不須更新欄位
				continue;
			} elseif (array_keys($value)[0] == 'marketing_strategy') { // json格式欄位
				$sql .= '"'. array_keys($value)[0] .'" = \''. json_encode(array_values($value)[0]) .'\', ';
			} elseif (array_keys($value)[0] == 'display_name') { // json格式欄位
				$displayArr = array_values($value)[0];
				foreach ($displayArr as $k => $v) {
					$displayArr[$k] = $this->translateSpecificChar("'", $v, 0);
				}
				$sql .= '"'. array_keys($value)[0] .'" = \''. json_encode($displayArr) .'\', ';
			} elseif (array_keys($value)[0] == 'gamename') {
				$strValue = $this->translateSpecificChar("'", array_values($value)[0], 0);
				$sql .= '"'. array_keys($value)[0] .'" = \''. $strValue .'\', ';
			} elseif (array_keys($value)[0] == 'is_new') {
				$synchronized = 0;
				$sql .= '"'. array_keys($value)[0] .'" = '. $synchronized .', ';
			} else {
				if (is_null(array_values($value)[0])) { // 處理 null
					$null = 'NULL';
					$sql .= '"'. array_keys($value)[0] .'" = '. $null .', ';
				} else {
					$strValue = str_replace('\'', '\'\'', array_values($value)[0]);
					$sql .= '"'. array_keys($value)[0] .'" = \''. $strValue .'\', ';
				}
			}
		}
		// 去除最後逗點
		$sql = substr($sql, 0, strlen($sql) - 2);
		$sql .= ' WHERE "id" = '. $importGame->getId() .';';
		return runSQL($sql, $debug);
	}


	/**
	 *  更新匯入遊戲狀態
	 *
	 * @param string $casinoId 娛樂城 ID
	 * @param array  $gameIds 變更狀態匯入遊戲 ID
	 * @param int    $open 匯入遊戲欲變更狀態，預設為 0 關閉
	 * @param int    $debug 除錯模式，預設為 0 未開啟
	 *
	 * @return int 更新狀態，大於 0 表示更新完成
	 */
	public function updateImportGamesOpen(string $casinoId, array $gameIds, $open = 0, $debug = 0)
	{
		$result = 0;
		if (count($gameIds) > 0) {
			$gameIdsSql = '';
			for ($i = 0; $i < count($gameIds); $i++) {
				$i == count($gameIds) - 1 ? $gameIdsSql .= '\''. $gameIds[$i] .'\'' : $gameIdsSql .= '\''. $gameIds[$i] .'\'' . ',';
			}
			$sql = 'UPDATE import_gamelist SET "open" = '. $open .' WHERE "gameid" IN ('. $gameIdsSql .') AND "casino_id" = \''. $casinoId .'\';';
			$result = runSQL($sql, $debug);
		}
		return $result;
	}


	/**
	 *  更新平台遊戲狀態
	 *
	 * @param string $casinoId 娛樂城 ID
	 * @param array  $gameIds 要變更狀態的遊戲 ID
	 * @param int    $open 要變更的遊戲狀態，預設為 0 => 關閉，1 => 開啟，2 => 永久關閉
	 * @param int    $isNew  匯入遊戲是否同步，預設為 0  => 是，1 => 否
	 * @param int    $debug 除錯模式，預設為 0 未開啟
	 *
	 * @return int 更新平台遊戲數量
	 */
	public function updatePlatformGamesOpen(string $casinoId, array $gameIds, $open = 0, $isNew = 0, $debug = 0)
	{
		// 取出關閉的已同步匯入遊戲
		$result = 0;
		if (count($gameIds) > 0) {
			$closedGamesSql = '';
			for ($i = 0; $i < count($gameIds); $i++) {
				$i == count($gameIds) - 1 ? $closedGamesSql .= '\''. $gameIds[$i] .'\'' : $closedGamesSql .= '\''. $gameIds[$i] .'\'' . ',';
			}
			$importSql = 'SELECT * FROM "import_gamelist" WHERE "gameid" IN ('. $closedGamesSql .') AND "is_new" = '.
				$isNew .' AND "casino_id" = \''. $casinoId .'\';';
			$closedGames = runSQLall($importSql, $debug);
			if ($closedGames[0] > 0) {
				$closePlatformGamesSql = '';
				for ($j = 1; $j <= $closedGames[0]; $j++) {
					$j == $closedGames[0] ? $closePlatformGamesSql .= '\''. $closedGames[$j]->gameid .'\'' :
						$closePlatformGamesSql .= '\''. $closedGames[$j]->gameid .'\'' . ',';
				}
				$platformSql = 'UPDATE casino_gameslist SET "open" = '. $open .' WHERE "gameid" IN ('.
					$closePlatformGamesSql .') AND "casino_id" = \''. strtoupper($casinoId) .'\';';
				$result = runSQL($platformSql, $debug);
			}
		}
		return $result;

	}


	/**
	 *  取得遊戲類別名稱
	 *
	 * @param mixed $gameCategory 遊戲類別
	 *
	 * @return mixed|string 遊戲類別名稱
	 */
	function getGameCategoryName($gameCategory)
	{
		global $tr;
		if (is_null($gameCategory)) {
			return '';
		} else {
			if (isset($tr[$gameCategory])) {
				return $tr[$gameCategory];
			} else {
				return $gameCategory;
			}
		}
	}


	/**
	 *  取得系統遊戲反水分類
	 *
	 * @param int $debug 除錯模式，預設為 0 未開啟
	 *
	 * @return array 遊戲反水分類
	 */
	function getGameFavorableTypes($debug = 0)
	{
		$sql = 'SELECT "value" FROM root_protalsetting WHERE name = \'main_category_info\'';
		$result = runSQLall($sql, $debug);
		if ($result[0] > 0) {
			return array_keys(json_decode(get_object_vars($result[1])['value'], true));
		} else {
			return array();
		}
	}


	/**
	 *  反水類別是否相同
	 *
	 * @param string $favorable 反水類別
	 *
	 * @return bool 反水類別相同回傳 true
	 */
	function isMatchFavorableType($favorable)
	{
		$favorableTypes = $this->getGameFavorableTypes();
		$exist = false;
		if (count($favorableTypes) > 0) {
			for ($i = 0; $i < count($favorableTypes); $i++) {
				if ($favorable == strtolower($favorableTypes[$i])) {
					$exist = true;
					break;
				}
			}
		}
		return $exist;
	}


	/**
	 *  取得遊戲語系名稱
	 *
	 * @param string $displayNames 語系顯示名稱
	 * @param string $i18n         語系代碼
	 *
	 * @return mixed|string 遊戲名稱
	 */
	public function getDisplayNameByLanguage($displayNames, $i18n)
	{
		$gameName = '';
		$result = json_decode($displayNames, true);
		if (count($result) > 0) {
			if (key_exists($i18n, $result)) {
				$gameName = $result[$i18n];
			} else {
				$gameName = $result['en-us'];
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
		$sql = 'SELECT display_name FROM import_gamelist WHERE "id" = '. $gameId .';';
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
					'name' => isset($names[$languageKeys[$i]]) ?
						$this->translateSpecificChar("'", $names[$languageKeys[$i]], 1) :
						$this->translateSpecificChar("'", $names['en-us'], 1)
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
		$sql = 'SELECT display_name FROM import_gamelist WHERE "id" = \''. $id .'\';';
		$gameNames = runSQLall($sql, $debug);
		if ($gameNames[0] > 0 and !empty($lang)) {
			// 取得語系陣列
			$gameNamesArr = json_decode($gameNames[1]->display_name, true);
			$gameNamesArr[$lang] = $this->translateSpecificChar("'", $name, 0);

			$updateSql = 'UPDATE import_gamelist SET display_name = \''. json_encode($gameNamesArr) .'\' WHERE id = '. $id .';';
			return runSQLall($updateSql, $debug)[0];
		} elseif (empty($lang)) { // 沒有語系
			return 0;
		} else { // 找不到遊戲
			return -1; // 回傳錯誤碼
		}
	}


	/**
	 *  轉換特殊字元
	 *  目前可轉換字元對照表
	 *  '   => +0squote
	 *  "  => +0dquote
	 *  > => +0grater
	 *  < => +0less
	 *
	 * @param mixed $source 要轉換的特殊字元
	 * @param mixed $sentence 轉換的單詞
	 * @param int $codeMode 轉換模式， 0 為編碼，1 為解碼
	 *
	 * @return string 轉換後字元
	 */
	public function translateSpecificChar($source, $sentence, $codeMode = 0)
	{
		// 解析特殊字元
		$translateArr = array(
			"'" => '+0squote',
			'"' => '+0dquote',
			'>' => '+0grater',
			'<' => '+0less'
		);

		// 轉換後字元
		$changed = '';
		if ($codeMode == 0) { // 編碼
			$changed = str_replace($source, $translateArr[$source], $sentence);
		} elseif ($codeMode == 1) { // 解碼
			$changed = str_replace($translateArr[$source], $source, $sentence);
		}

		return $changed;
	}


	/**
	 *  取得娛樂城反水類別對應主類別關係
	 *
	 * @param mixed $casinoId 娛樂城 ID
	 * @param $debug 除錯模式，0 為非除錯模式
	 *
	 * @return array  娛樂城反水類別對應主類別關係
	 */
	function getFavorableToMainCategoryByCasino($casinoId, $debug)
	{
		$casinoLib = new casino_switch_process_lib();
		$favorable = $casinoLib->getCasinoPlatformByCasinoId(strtoupper($casinoId), $debug);
		$mainCategories = array();
		foreach ($favorable as $value) {
			$mainCategoryToFavorable = $this::$mainCategoryToFavorable;
			$category = $mainCategoryToFavorable[$value];
			foreach ($category as $v) {
				if (!in_array($v, $mainCategories)) {
					array_push($mainCategories, $v);
				}
			}
		}
		return $mainCategories;
	}
}
