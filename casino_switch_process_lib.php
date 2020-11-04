<?php
// ----------------------------------------------------------------------------
// Features:	後台--娛樂城管理
// File Name:	casino_switch_process_lib.php
// Author:		Letter
// Related:		casino_switch_process_action.php casino_switch_process.php
// Log:
// 2019.05.13 新建 Letter
// ----------------------------------------------------------------------------

require_once 'lib.php';
require_once 'casino.php';
require_once 'casino/casino_config.php';

class casino_switch_process_lib
{
	static public $debug = 0;

	static public $ops = 'ops';
	static public $master = 'master';
	static public $theRole = 'R';

	static public $isNew = 1;
	static public $isOld = 0;
	static public $noSet = -1;

	/**
	 * casino_switch_process_lib constructor.
	 */
	public function __construct(){}


	/**
	 * 依帳號取得權限
	 *
	 * @param string $account 帳號
	 *
	 * @return string 權限
	 */
	public function getPermissionByAccount(string $account)
	{
		global $su;
		if (in_array($account, $su[$this::$ops])) {
			return $this::$ops;
		} elseif (in_array($account, $su[$this::$master])) {
			return $this::$master;
		} else {
			return $this->getTheroleByAccount($account);
		}
	}


	/**
	 * 依帳號取得資料庫內會員權限
	 *
	 * @param string $account 帳號
	 *
	 * @return string 會員權限
	 */
	public function getTheroleByAccount(string $account)
	{
		$sql = 'SELECT therole FROM "root_member" WHERE "account" = \''. $account .'\';';
		$role = runSQLall($sql, $this::$debug);
		return $role[0] > 0 ? $role[1]->therole : ' ';
	}


	/**
	 * 依狀態選擇娛樂城
	 *
	 * @param string $status 娛樂城狀態
	 * @param bool   $show   顯示永久停用，true 為顯示
	 * @param array  $sort   排序設定，[ '排序資料表欄位', '排序方式' ]
	 * @param int    $debug  除錯模式，0 為非除錯模式
	 *
	 * @return mixed 符合狀態的娛樂城
	 * @throws Exception
	 */
	public function getCasinosByStatus(string $status, bool $show, array $sort, int $debug = 0)
	{
		global $tr;

		$sql = <<<SQL
			SELECT * ,to_char((notify_datetime at time zone 'AST'),'YYYY-MM-DD HH24:MI:SS') as notify_datetime_char 
			FROM casino_list
SQL;

		// 娛樂城狀態
		switch ($status) {
			case $this::$ops:
				$sql .= ' WHERE "open" <> '. casino::$casinoDeprecated;
				// 顯示永久停用
				if ($show) {
					$sql .= ' OR "open" = '. casino::$casinoDeprecated;
				}
				break;
			case $this::$master:
			case $this::$theRole:
				$sql .= ' WHERE "open" = '. casino::$casinoOff .' OR "open" = '. casino::$casinoOn .' OR "open" = '. casino::$casinoEmgForCasinoOn .' OR "open" = '. casino::$casinoEmgForCasinoOff;
				break;
			case casino::$casinoNew:
				$sql .= ' WHERE "notify_datetime" > now() AND ("open" = '. casino::$casinoOff .' OR "open" = '.
					casino::$casinoOn .' OR "open" = '. casino::$casinoEmg .')';
				break;
			case casino::$casinoOff:
				$sql .= ' WHERE "open" = '. casino::$casinoOff;
				break;
			case casino::$casinoEmg:
				$sql .= ' WHERE "open" = '. casino::$casinoEmgForCasinoOn .' OR "open" = '. casino::$casinoEmgForCasinoOff;
				break;
			case casino::$casinoClose:
				$sql .= ' WHERE "open" = '. casino::$casinoCloseForCasinoOn .' OR "open" = '. casino::$casinoCloseForCasinoOff;
				break;
			case casino::$casinoDeprecated:
			default:
				break;
		}

		// 排序
		$sql .= ' ORDER BY '. $sort['columnIndex'] .' '. strtoupper($sort['sortFormat']) .';';
  
		$result = runSQLall($sql, $debug);
		debugMode($this::$debug, $result);

		$casinos = array();
		if ($result[0] > 0) {
			for ($i = 1; $i <= $result[0]; $i++) {
				$casino = new casino(
					$result[$i]->id,
					$result[$i]->casinoid,
					$this->getCurrentLanguageCasinoName($result[$i]->display_name, 'default'),
					$result[$i]->casino_dbtable,
					$result[$i]->note,
					$result[$i]->open,
					$result[$i]->account_column,
					$result[$i]->bettingrecords_tables,
					$result[$i]->casino_order,
					json_decode($result[$i]->game_flatform_list, true),
					$result[$i]->notify_datetime_char,
					$result[$i]->api_update,
					$this->getCurrentLanguageCasinoName($result[$i]->display_name, $_SESSION['lang']),
					$this->getNewAlert($result[$i]->notify_datetime),
					$this->getCurrentLanguageCasinoName($result[$i]->display_name, 'zh-cn'),
					$this->getCurrentLanguageCasinoName($result[$i]->display_name, 'en-us')
				);
				array_push($casinos, $casino);
			}
		}

		return $casinos;
	}


	/**
	 * 取得最新提醒
	 *
	 * @param $notifyTime
	 *
	 * @return int
	 * @throws Exception
	 */
	public function getNewAlert($notifyTime)
	{
		$now = new DateTime();
		if (is_null($notifyTime)) {
			return $this::$noSet;
		}
		$nt = new DateTime($notifyTime);
		$result = $this::$noSet;
		if ($nt >= $now) {
			$result = $this::$isNew;
		} elseif ($nt < $now) {
			$result = $this::$isOld;
		}
		return $result;
	}


	/**
	 * 更新娛樂城排序
	 *
	 * @param int $id 序號
	 * @param int $order 順序
	 * @param int $debug 除錯模式，0 為非除錯模式
	 *
	 * @return mixed 被更新資料數
	 */
	public function updateCasinoOrder($id, $order, int $debug = 0)
	{
		$sql = 'UPDATE "casino_list" SET casino_order = '. $order .' WHERE id = '. $id .';';
		$result = runSQLall($sql, $debug);
		return $result;
	}


	/**
	 * 取得所有娛樂城
	 *
	 * @param int $debug 除錯模式，0 為非除錯模式
	 *
	 * @return array 娛樂城物件
	 */
	public function getCasinos($debug = 0)
	{
		$sql = 'SELECT * FROM "casino_list"';
		return runSQLall($sql, $debug);
	}


	/**
	 * 取得娛樂城總數
	 *
	 * @param int $debug 除錯模式，0 為非除錯模式
	 *
	 * @return int 娛樂城總數
	 */
	public function getCasinosCount($debug = 0)
	{
		$result = $this->getCasinos($debug);
		return $result[0];
	}


	/**
	 * 更新娛樂城資料表欄位資料
	 *
	 * @param int $id ID
	 * @param string $column 資料表欄位
	 * @param string $value 更新數值
	 * @param int    $debug 除錯模式，0 為非除錯模式
	 *
	 * @return int 資料表變動資料數
	 */
	public function updateCasinoColumnById($id, string $column, string $value, int $debug = 0)
	{
		$typeValue = $this->getColumnTypeValue($column, $value);
		$sql = 'UPDATE "casino_list" SET '. $column .' = \''. $typeValue .'\' WHERE id = '. $id .';';
		$result = runSQLall($sql, $debug);
		return $result[0];
	}


	/**
	 * 取得欄位資料型態資料
	 *
	 * @param string $column 欄位名稱
	 * @param mixed $value 欲轉換資料
	 *
	 * @return mixed 轉換後資料
	 */
	public function getColumnTypeValue($column, $value)
	{
		switch ($column) {
			case 'game_flatform_list':
				$result = json_encode($value);
				break;
			default:
				$result = $value;
		}
		return $result;
	}


	/**
	 *  依目前語系取得娛樂城顯示名稱
	 *
	 * @param mixed $displayNames 語系顯示名稱
	 * @param mixed $i18n 語系
	 *
	 * @return mixed 目前語系顯示名稱，若該語系無顯示名稱，回覆預設顯示名稱
	 */
	public function getCurrentLanguageCasinoName($displayNames, $i18n)
	{

		$i18nNameArr = get_object_vars(json_decode($displayNames));

		// 取得對應語系顯示名稱
		if (key_exists($i18n, $i18nNameArr)) {
			$display = $i18nNameArr[$i18n];
		} else {
			$display = $i18nNameArr['en-us'];
		}
		return urldecode($display);
	}


	/**
	 * 用娛樂城 ID 取得娛樂城
	 *
	 * @param mixed $casinoId 娛樂城 ID
	 * @param int $debug 除錯模式，0 為非除錯模式
	 *
	 * @return casino 娛樂城物件
	 * @throws Exception 時間換算例外
	 */
	function getCasinoByCasinoId($casinoId, $debug = 0)
	{
		$sql = 'SELECT * FROM casino_list WHERE casinoid = \''. $casinoId .'\'';
		$result = runSQLall($sql, $debug);
		if ($result[0] > 0) {
			$casino = new casino(
				$result[1]->id,
				$result[1]->casinoid,
				$this->getCurrentLanguageCasinoName($result[1]->display_name, 'default'),
				$result[1]->casino_dbtable,
				$result[1]->note,
				$result[1]->open,
				$result[1]->account_column,
				$result[1]->bettingrecords_tables,
				$result[1]->casino_order,
				json_decode($result[1]->game_flatform_list, true),
				$result[1]->notify_datetime,
				$result[1]->api_update,
				$this->getCurrentLanguageCasinoName($result[1]->display_name, $_SESSION['lang']),
				$this->getNewAlert($result[1]->notify_datetime),
				$this->getCurrentLanguageCasinoName($result[1]->display_name, 'zh-cn'),
				$this->getCurrentLanguageCasinoName($result[1]->display_name, 'en-us')

			);
		} else {
			$casino = null;
		}
		return $casino;
	}


	/**
	 * 取得娛樂城預設名稱
	 *
	 * @param mixed $casinoId 娛樂城 ID
	 * @param int $debug 除錯模式，0 為非除錯模式
	 *
	 * @return string 娛樂城預設名稱
	 */
	function getCasinoDefaultName($casinoId, $debug = 0)
	{
		$sql = 'SELECT * FROM casino_list WHERE casinoid = \''. $casinoId .'\'';
		$result = runSQLall($sql, $debug);
		$casinoDefaultName = '';
		if ($result[0] > 0 and !is_null($result[1]->display_name)) {
			$casinoDefaultName = $this->getCurrentLanguageCasinoName($result[1]->display_name, 'default');
		}
		return $casinoDefaultName;
	}


	/**
	 *  取得系統支援語系娛樂城名稱
	 *
	 * @param mixed $casinoId 娛樂城 ID
	 * @param int $debug 除錯模式，預設 0 為非除錯模式
	 *
	 * @return array 娛樂城名稱，若娛樂城無該語系名稱則顯示英文名稱
	 */
	function getAllLanguageCasinoNames($casinoId, $debug = 0)
	{
		global $supportLang;
		global $tr;
		$supportLangKey = array_keys($supportLang);
		$sql = 'SELECT display_name FROM casino_list WHERE casinoid = \''. $casinoId .'\'';
		$result = runSQLall($sql, $debug);

		// 取得支援語系娛樂城名稱
		$casinoNames = [];
		if ($result[0] > 0 and !is_null($result[1]->display_name)) {
			$dbCasinoNames = json_decode($result[1]->display_name, true);
			$casinoNames['default'] = [
				'display' => $tr['grade default'],
				'name' => $dbCasinoNames['default']
			];
			for ($i = 0; $i < count($supportLangKey); $i++) {
				$casinoNames[$supportLangKey[$i]] = [
					'display' => $supportLang[$supportLangKey[$i]]['display'],
					'name' => isset($dbCasinoNames[$supportLangKey[$i]]) ? urldecode($dbCasinoNames[$supportLangKey[$i]]): urldecode($dbCasinoNames['en-us'])
				];
			}
		}

		return $casinoNames;
	}


	/**
	 *  更新娛樂城語系名稱
	 *
	 * @param mixed $casinoId 娛樂城 ID
	 * @param string $langKey 語系鍵值
	 * @param string $casinoName 娛樂城語系名稱
	 * @param int $debug 除錯模式，預設 0 為非除錯模式
	 *
	 * @return array 更新結果，回傳 result  大於 0 為更新成功
	 */
	function updateCasinoNameByLanguage($casinoId, $langKey, $casinoName, $debug = 0)
	{
		$getLangNamesSql = 'SELECT display_name FROM casino_list WHERE "casinoid" = \''. $casinoId .'\';';
		$casinoNames = runSQLall($getLangNamesSql, $debug);
		if ($casinoNames[0] > 0 and !empty($langKey)) {
			// 取得語系陣列
			$casinoNamesArr = json_decode($casinoNames[1]->display_name, true);
			$casinoNamesArr[$langKey] = $casinoName;
			// 轉換 Json
			$updateSql = 'UPDATE casino_list SET display_name = \''. json_encode($casinoNamesArr) .'\' WHERE casinoid = \''. $casinoId .'\';';
			return [
				'result' => runSQLall($updateSql, $debug)[0],
				'langKey' => $langKey,
			    'casinoName'=> $casinoName,
				'casinoId'=> $casinoId
			];
		} elseif (empty($lang)) { // 沒有語系
			return [
				'result' => 0,
				'langKey' => '',
				'casinoName' => '',
				'casinoId' => ''
			];
		} else { // 找不到遊戲
			return [
				'result' => -1, // 回傳錯誤碼
				'langKey' => '',
				'casinoName'=> '',
				'casinoId' => ''
			];
		}
	}


	/**
	 *  更新娛樂城
	 *
	 * @param casino $casino 娛樂城物件
	 * @param int $debug 除錯模式，預設 0 為非除錯模式
	 *
	 * @return int|null 更新成功回傳 1
	 */
	function updateCasino(casino $casino, $debug = 0)
	{
		// 組成更新 sql
		$sql = 'UPDATE casino_list SET ';
		foreach ($casino->getKeyValueMap() as $key => $value) {
			if (array_keys($value)[0] == 'id' or array_keys($value)[0] == 'casinoid'
				or array_keys($value)[0] == 'new_alert' or array_keys($value)[0] == 'zh_cn_name'
				or array_keys($value)[0] == 'en_us_name' ) { // 不須更新
				continue;
			} elseif (array_keys($value)[0] == 'game_flatform_list' or array_keys($value)[0] == 'display_name') { // JSON
				$sql .= '"'. array_keys($value)[0] .'" = \''. json_encode(array_values($value)[0]) .'\', ';
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
		$sql .= ' WHERE "id" = '. $casino->getId() .';';
		return runSQL($sql, $debug);
	}


	/**
	 *  API 娛樂城是否存在平台
	 *
	 * @param mixed $casinoId 娛樂城 ID
	 * @param int $debug 除錯模式，預設 0 為非除錯模式
	 *
	 * @return bool 存在平台回傳 true
	 */
	function existCasino($casinoId, $debug = 0)
	{
		$sql = 'SELECT * FROM casino_list WHERE casinoid = \''. strtoupper($casinoId) .'\';';
		$result = runSQLall($sql, $debug);
		$exist = false;
		if ($result[0] > 0) {
			$exist = true;
		}
		return $exist;
	}


	/**
	 *  新增娛樂城
	 *
	 * @param casino $casino 娛樂城
	 * @param int $debug 除錯模式，預設 0 為非除錯模式
	 *
	 * @return mixed 新增結果，1 為新增成功
	 */
	function createCasino(casino $casino, $debug = 0)
	{
		$sql = 'INSERT INTO "casino_list" ';
		$params = [];
		$keys = [];
		foreach ($casino->getKeyValueMap() as $key => $value) {
			// 不須放入SQL
			if (array_keys($value)[0] == 'id' or array_keys($value)[0] == 'new_alert'
				or array_keys($value)[0] == 'zh_cn_name' or array_keys($value)[0] == 'en_us_name') continue;
			array_push($keys, array_keys($value)[0]);
		}
		// 組合 SQL
		$sql .= '('. implode(' ,', $keys) .') VALUES ';
		foreach ($casino->getKeyValueMap() as $key => $value) {
			// 不須放入SQL
			if (array_keys($value)[0] == 'id' or array_keys($value)[0] == 'new_alert'
				or array_keys($value)[0] == 'zh_cn_name' or array_keys($value)[0] == 'en_us_name') {
				continue;
			} elseif (array_keys($value)[0] == 'game_flatform_list' or array_keys($value)[0] == 'display_name') { // JSON
				array_push($params, '\''. array_values($value)[0] .'\'');
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
		return runSQLall($sql, $debug)[0];
	}


	/**
	 * 取得開啟的娛樂城
	 *
	 * @param int $debug 除錯模式，預設 0 為非除錯模式
	 *
	 * @return array 狀態為開啟的娛樂城
	 * @throws Exception 時間換算例外
	 */
	function getOpenCasinos($debug = 0)
	{
		$sql = 'SELECT * FROM casino_list WHERE "open" = 1;';
		$result = runSQLall($sql, $debug);
		$casinos = array();
		if ($result[0] > 0) {
			for ($i = 1; $i <= $result[0]; $i++) {
				$casino = new casino(
					$result[$i]->id,
					$result[$i]->casinoid,
					$this->getCurrentLanguageCasinoName($result[$i]->display_name, 'default'),
					$result[$i]->casino_dbtable,
					$result[$i]->note,
					$result[$i]->open,
					$result[$i]->account_column,
					$result[$i]->bettingrecords_tables,
					$result[$i]->casino_order,
					json_decode($result[$i]->game_flatform_list, true),
					$result[$i]->notify_datetime,
					$result[$i]->api_update,
					$this->getCurrentLanguageCasinoName($result[$i]->display_name, $_SESSION['lang']),
					$this->getNewAlert($result[$i]->notify_datetime),
					$this->getCurrentLanguageCasinoName($result[$i]->display_name, 'zh-cn'),
					$this->getCurrentLanguageCasinoName($result[$i]->display_name, 'en-us')
				);
				array_push($casinos, $casino);
			}
		}

		return $casinos;
	}


	/**
	 *  取得開啟娛樂城 ID
	 *
	 * @param int $debug 除錯模式，預設 0 為非除錯模式
	 *
	 * @return array 狀態為開啟的娛樂城 ID
	 * @throws Exception 時間換算例外
	 */
	function getOpenCasinoIds($debug = 0)
	{
		$casinos = $this->getOpenCasinos($debug);
		$cids = array();
		if (count($casinos) > 0) {
			for ($i = 0; $i < count($casinos); $i++) {
				$cids[$i] = $casinos[$i]->getCasinoid();
			}
		}
		return $cids;
	}


	/**
	 *  取得娛樂城遊戲反水類別
	 *
	 * @param mixed $casinoId 娛樂城 ID
	 * @param int $debug 除錯模式，預設 0 為非除錯模式
	 *
	 * @return array 娛樂城遊戲反水類別
	 */
	function getCasinoPlatformByCasinoId($casinoId, $debug = 0)
	{
		$sql = 'SELECT game_flatform_list FROM casino_list WHERE "casinoid" = \''. $casinoId .'\' AND "open" <> 5';
		$result = runSQLall($sql, $debug);
		if ($result[0] > 0) {
			return json_decode($result[1]->game_flatform_list, true);
		} else {
			return array();
		}
	}


	/**
	 *  取得娛樂城反水類別與行銷類別對應關係
	 *
	 * @param mixed $casinoId 娛樂城 ID
	 * @param int $debug 除錯模式，預設 0 為非除錯模式
	 *
	 * @return array 娛樂城反水類別與行銷類別對應關係
	 */
	function getCasinoPlatformToMCTMapping($casinoId, $debug = 0)
	{
		// 取得娛樂城反水類別
		$casinoPlatform = $this->getCasinoPlatformByCasinoId($casinoId, $debug);
		// 取得系統反水類別
		$favorables = $this->getFavorableTypes($debug);
		$mapping = array();
		foreach ($favorables as $key => $value) {
			foreach ($value as $k => $v) {
				$mapping[$v] = $key;
			}
		}

		// 組合新對應
		$result = array();
		foreach ($casinoPlatform as $value) {
			$result[$value] = $mapping[$value];
		}

		return $result;
	}


	/**
	 *  取得平台反水分類
	 *
	 * @param int $debug 除錯模式，預設 0 為非除錯模式
	 *
	 * @return array 反水類別與對應值
	 */
	function getFavorableTypes($debug = 0)
	{
		$sql = 'SELECT * FROM root_protalsetting WHERE "name" = \'favorable_types\';';
		$result = runSQLall($sql, $debug);
		$types = [];
		if ($result[0] > 0) {
			$types = json_decode($result[1]->value, true);
		}
		return $types;
	}


	/**
	 *  取得反水分類對應語言檔名稱
	 *
	 * @param int $debug 除錯模式，預設 0 為非除錯模式
	 *
	 * @return array 反水分類與對應名稱
	 */
	function getFavorableTypeToNameArray($debug = 0)
	{
		global $tr;
		$types = array_keys(getFavorableTypes($debug));
		$names = array();
		for ($i = 0; $i < count($types); $i++) {
			$names[$types[$i]] = isset($tr[$types[$i]]) ? $tr[$types[$i]] : $types[$i];
		}
		return $names;
	}


	/**
	 * 用娛樂城 ID 取得娛樂城語系名稱
	 *
	 * @param mixed $casinoId 娛樂城 ID
	 * @param mixed $i18n 目前平台選擇語系
	 * @param int   $debug    除錯模式，0 為非除錯模式
	 *
	 * @return string 娛樂城語系名稱
	 */
	function getCasinoNameByCasinoId($casinoId, $i18n, $debug = 0)
	{
		$sql = 'SELECT * FROM casino_list WHERE casinoid = \''. $casinoId .'\'';
		$result = runSQLall($sql, $debug);
		$casinoDefaultName = '';
		if ($result[0] > 0 and !is_null($result[1]->display_name)) {
			$casinoDefaultName = $this->getCurrentLanguageCasinoName($result[1]->display_name, $i18n);
		}
		return $casinoDefaultName;
	}


	/**
	 *  sign key generator
	 *
	 * @param mixed $data   傳遞的參數陣列，若沒傳遞參數則放空陣列
	 * @param mixed $apiKey 代理商的API KEY
	 *
	 * @return string 加密字串
	 */
	function generateSign($data, $apiKey)
	{
		ksort($data);
		return md5(http_build_query($data) . $apiKey);
	}


	/**
	 *  login Casino through API function
	 *
	 * @param mixed $method      方法名稱
	 * @param int   $debug       除錯模式，預設 0 為關閉，1為開啟
	 * @param mixed $API_data 資料陣列
	 *
	 * @return array|string API回傳資料
	 */
	function getDataByAPI($method, $debug = 0, $API_data)
	{
		// 設定 socket_timeout , http://php.net/manual/en/soapclient.soapclient.php
		ini_set('default_socket_timeout', 5);

		global $API_CONFIG;
		global $config;

		// Setting restful url
		$url = $API_CONFIG['url'];
//	    $apiKey = $config['gpk2_apikey'];
//	    $token = $config['gpk2_token'];
		// gtdemo key and token for develop
		$apiKey = '88248e7dc6fcef8670f322249157462b';
		$token = '011f1baaa42a6013d2461ae21c91e031019b1988ad28d84f32663871c242c808468ee0b8f41a187d533cf860d0c63d70d55004ef288feaf59bb52e0906fd7d32';

		if ($method == 'AddAccount') {
			$url .= '/api/player';
			$apimethod = 'post';
			$API_data['sign'] = $this->generateSign($API_data, $apiKey);
		} elseif ($method == 'Deposit') {
			$url .= '/api/transaction/deposit';
			$apimethod = 'post';
			$API_data['sign'] = $this->generateSign($API_data, $apiKey);
		} elseif ($method == 'Withdrawal') {
			$url .= '/api/transaction/withdraw';
			$apimethod = 'post';
			$API_data['sign'] = $this->generateSign($API_data, $apiKey);
		} elseif ($method == 'GetAccountDetails') {
			$API_data['sign'] = $this->generateSign($API_data, $apiKey);
			$uri = http_build_query($API_data);
			$url .= '/api/player/wallet?' . $uri;
			$apimethod = 'get';
		} elseif ($method == 'CheckUser') {
			$API_data['sign'] = $this->generateSign([], $apiKey);
			$url .= '/api/player/check/' . $API_data['account'] . '?sign=' . $API_data['sign'];
			$apimethod = 'get';
		} elseif ($method == 'KickUser') {
			$url .= '/api/player/logout';
			$apimethod = 'post';
			$API_data['sign'] = $this->generateSign($API_data, $apiKey);
		} elseif ($method == 'GetGameUrl') {
			$API_data['sign'] = $this->generateSign($API_data, $apiKey);
			$uri = http_build_query($API_data);
			$url .= '/api/game/game-link?' . $uri;
			$apimethod = 'get';
		} elseif ($method == 'GameHallLists') {
			$API_data['sign'] = $this->generateSign($API_data, $apiKey);
			$url .= '/api/game/halls';
			$apimethod = 'get';
		} elseif ($method == 'GamenameLists') {
			$API_data['sign'] = $this->generateSign($API_data, $apiKey);
			$uri = http_build_query($API_data);
			$url .= '/api/game/game-list?' . $uri;
			$apimethod = 'get';
		} elseif ($method == 'CheckUserIsGaming') {
			$API_data['sign'] = $this->generateSign($API_data, $apiKey);
			$uri = http_build_query($API_data);
			$url .= '/api/player/is-gaming?' . $uri;
			$apimethod = 'get';
		} elseif ($method == 'GetBetDetail') {
			$API_data['sign'] = $this->generateSign($API_data, $apiKey);
			$uri = http_build_query($API_data);
			$url .= '/api/betlog/playcheck?' . $uri;
			$apimethod = 'get';
		} elseif ($method == 'CheckTransaction') {
			// 出現在 URL 的參數不需要放到簽名裡生成
			$API_data['sign'] = $this->generateSign([], $apiKey);
			$url .= '/api/transaction/' . $API_data['transaction_id'] .'?sign=' . $API_data['sign'];
			$apimethod = 'get';
		} elseif ($method == 'CheckTransactions') {
			$API_data['sign'] = $this->generateSign($API_data, $apiKey);
			$uri = http_build_query($API_data);
			$url .= '/api/transaction/' . $uri;
			$apimethod = 'get';
		}else {
			$ret = 'nan';
		}

		if (isset($API_data)) {
			$ret = array();
			try {
				$headers = ["Content-Type: multipart/form-data", "Authorization: $token"];

				$ch = curl_init($url);
				curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
				curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
				curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
				curl_setopt($ch, CURLOPT_CAINFO, $_SERVER['DOCUMENT_ROOT'] . '/cacert.pem');
				curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
				curl_setopt($ch, CURLOPT_TIMEOUT, 30);
				if ($apimethod == 'post') {
					curl_setopt($ch, CURLOPT_POST, true);
					curl_setopt($ch, CURLOPT_POSTFIELDS, $API_data);
				}

				$response = curl_exec($ch);

				if ($debug == 1) {
					var_dump($url);
					echo $method . "\n";
					echo curl_error($ch);
					var_dump($response);
				}

				if ($response) {
					$body = json_decode($response);

					if ($debug == 1) {
						var_dump($body);
					}
					// 如果 curl 讀取投注紀錄成功的話
					if (isset($body->data) and $body->status->code == 0) {
						// curl 正確
						$ret['curl_status'] = 0;
						// 計算取得的紀錄數量有多少
						$ret['count'] = (is_array($body->data) OR is_object($body->data)) ? count((array)$body->data) : '1';
						// 取得紀錄沒有錯誤
						$ret['errorcode'] = 0;
						// 存下 body
						$ret['Status'] = $body->status->code;
						$ret['Result'] = $body->data;
					} else {
						// curl 正確
						$ret['curl_status'] = 0;
						// 計算取得的紀錄數量有多少
						$ret['count'] = (is_array($body->data) OR is_object($body->data)) ? count((array)$body->data) : '1';
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

		return ($ret);
	}


	/**
	 *  更新娛樂城轉帳紀錄
	 *
	 * @param mixed  $id                  單號
	 * @param mixed  $prefixTransactionId 轉帳紀錄 ID
	 * @param int    $transferStatus      平台與娛樂城轉帳狀態紀錄
	 * @param mixed  $status              Log 狀態
	 * @param string $note Log 敘述
	 * @param int    $debug               除錯模式，預設 0 為關閉，1為開啟
	 *
	 * @return array|int[]
	 */
	function updateCasinoTransferRecord($id, $prefixTransactionId, $transferStatus, $status, $note = '', $debug = 0)
	{
		// 更新轉帳紀錄
		$selectSql = 'SELECT * FROM "root_member_casino_transferrecords" WHERE "id" = '. $id .' AND "transaction_id" = \''. $prefixTransactionId .'\';';
		$selectResult = runSQLall($selectSql, $debug);

		if ($selectResult[0] > 0) {
			if (empty($note)) {
				$note = $selectResult[1]->note;
			}
			$updateSql = 'UPDATE "root_member_casino_transferrecords" SET "status" = \''. $status .'\', "casino_transfer_status" = '. $transferStatus .', "note" = \''. $note .'\' WHERE "id" = '. $id .' AND "transaction_id" = \''. $prefixTransactionId .'\';';
			return [
				'result' => runSQL($updateSql, $debug)
			];
		} else {
			return [
				'result' => 0
			];
		}
	}


	/**
	 *  娛樂城取錢資料庫相關操作
	 *
	 * @param mixed $apiResult API 確認交易資訊
	 * @param mixed $memberId  會員 ID
	 * @param int   $debug     除錯模式，預設 0 為關閉
	 *
	 * @return mixed 交易結果
	 */
	function withdrawDB($apiResult, $memberId, $debug = 0)
	{
		// 代幣出納帳號
		global $gtoken_cashier_account;

		// API 執行結果
		$result = $apiResult['Result'];

		// 檢查會員狀態(是否被鎖定)
		$memberSql = 'SELECT * FROM root_member WHERE "id" = \''. $memberId .'\' AND "status" = \'1\';';
		$memberResult = runSQLall($memberSql, $debug);

		// 取得目前會員錢包資訊
		$walletSql = "SELECT gtoken_balance, gtoken_lock,
              casino_accounts->'" . strtoupper($result->casino) . "'->>'account' as casino_account,
              casino_accounts->'" . strtoupper($result->casino) . "'->>'password' as casino_password,
              casino_accounts->'" . strtoupper($result->casino) . "'->>'balance' as casino_balance 
              FROM root_member_wallets WHERE id = '" . $memberId . "';";
		$walletResult = runSQLall($walletSql, $debug);

		// 取錢
		if (
			$memberResult[0] == 1 AND // 會員未被鎖定
			$walletResult[0] == 1 AND // 有娛樂城錢包
			$walletResult[1]->casino_account != null AND // 有娛樂城帳號
			$walletResult[1]->gtoken_lock == strtoupper($result->casino) // 錢在要取錢的娛樂城
		) {
			// 處理資料庫數據
			$account = $memberResult[1]->account;
			$casinoBalanceDB = round($walletResult[1]->casino_balance, 2);
			$casinoBalanceAPI = $result->amount;
			$payout = round(($casinoBalanceAPI - $casinoBalanceDB), 2); // 派彩

			// 執行資料庫數據修改
			$casinoBalanceResult = $this->retrieveCasinoBalanceDB($account, $memberId,
				$walletResult[1]->casino_account, $gtoken_cashier_account, $casinoBalanceAPI, $payout,
				$casinoBalanceDB, $result->casino, $debug);

			// 資料庫更新狀態 Log
			if ($casinoBalanceResult['ErrorCode'] == 1) {
				$r['code'] = 1;
				$r['messages'] = $casinoBalanceResult['ErrorMessage'];
				$logger = $r['messages'];
				$this->updateCasinoTransferRecord($result->id, $result->transaction_id, 1, 'success', $logger, $debug);
			} else {
				$r['code'] = 523;
				$r['messages'] = $casinoBalanceResult['ErrorMessage'];
				$logger = $r['messages'];
				$this->updateCasinoTransferRecord($result->id, $result->transaction_id, 1, 'fail', $logger, $debug);
			}
		} else {
			$logger = 'DB 帐号资料有问题';
			$r['code'] = 401;
			$r['messages'] = $logger;
			$this->updateCasinoTransferRecord($result->id, $result->transaction_id, 1, 'fail', $logger, $debug);
		}

		return ($r);
	}


	/**
	 *  取回 娛樂城 的餘額 -- 針對 db 的處理函式，只針對此功能有用
	 *
	 * @param mixed $memberaccount          會員帳號
	 * @param mixed $memberid               會員ID
	 * @param mixed $member_casino_account  會員娛樂城帳號
	 * @param mixed $gtoken_cashier_account 統代幣出納帳號
	 * @param mixed $api_balance            API 餘額
	 * @param mixed $payout_balance         派彩
	 * @param mixed $casino_balance_db      資料庫錢包餘額
	 * @param mixed $casinoId 娛樂城 ID
	 * @param int   $debug                  除錯模式，預設 0 為關閉
	 *
	 * @return mixed 處理結果。 <br>
	 *                         0 為其他錯誤 <br>
	 *                         1 為成功 <br>
	 *                        406 為資料庫處理失敗
	 */
	function retrieveCasinoBalanceDB($memberaccount, $memberid, $member_casino_account, $gtoken_cashier_account,
	                                 $api_balance, $payout_balance, $casino_balance_db, $casinoId, $debug = 0)
	{
		global $transaction_category;
		global $auditmode_select;
		global $config;

		$casinoLib = new casino_switch_process_lib();
		$casinoSql = 'SELECT display_name FROM "casino_list" WHERE "casinoid" = \'' . strtoupper($casinoId) . '\'';
		$displayName = runSQLall($casinoSql, $debug)[1]->display_name;
		$defaultCasinoName = $casinoLib->getCurrentLanguageCasinoName($displayName, 'default');

		// 取得來源與目的帳號的 id，$gtoken_cashier_account(此為系統代幣出納帳號 global var.)
		$d['source_transferaccount'] = $gtoken_cashier_account;
		$d['destination_transferaccount'] = $memberaccount;
		$source_id_sql = "SELECT * FROM root_member WHERE account = '" . $d['source_transferaccount'] . "';";
		$source_id_result = runSQLall($source_id_sql);
		$destination_id_sql = "SELECT * FROM root_member WHERE account = '" . $d['destination_transferaccount'] . "';";
		$destination_id_result = runSQLall($destination_id_sql);

		$r['ErrorCode'] = 0;
		$r['ErrorMessage'] = '';

		if ($source_id_result[0] == 1 and $destination_id_result[0] == 1) {
			$d['source_transfer_id'] = $source_id_result[1]->id;
			$d['destination_transfer_id'] = $destination_id_result[1]->id;
		} else {
			$logger = '转帐的来源与目的帐号可能有问题，请稍候再试。';
			$r['ErrorCode'] = 590;
			$r['ErrorMessage'] = $logger;
			echo "<p> $logger </p>";
			die();
		}

		if ($debug == 1) {
			var_dump($payout_balance);
		}

		// 派彩有二種狀態，要有不同的對應 SQL 處理
		if ($payout_balance >= 0) {
			// $payout_balance >= 0; 從娛樂城贏錢 or 沒有輸贏，把 娛樂城 餘額取回

			// 先取得當下的 wallets 變數資料，等等 sql 更新後就會消失了
			$wallets_sql = "SELECT gtoken_balance,casino_accounts->'" . strtoupper($casinoId) . "'->>'balance' as casino_balance FROM root_member_wallets WHERE id = '" . $d['destination_transfer_id'] . "';";
			$wallets_result = runSQLall($wallets_sql);

			// 在剛取出的 wallets 資料庫中 娛樂城 的餘額(支出)
			$gtoken_casino_balance_db = round($wallets_result[1]->casino_balance, 2);
			// 在剛取出的 wallets 資料庫中 gtoken(代幣) 的餘額(支出)
			$gtoken_balance_db = round($wallets_result[1]->gtoken_balance, 2);
			// 派彩 = 娛樂城餘額 - 本地端 支出餘額
			$gtoken_balance = round(($gtoken_balance_db + $gtoken_casino_balance_db + $payout_balance), 2);

			// 交易開始
			$payout_transaction_sql = 'BEGIN;';
			// 存款金額 -- 娛樂城餘額
			$d['deposit'] = $gtoken_balance;
			// 提款金額 -- 本地端支出
			$d['withdrawal'] = $casino_balance_db;
			// 操作者
			$d['member_id'] = $memberid;
			// (說明)娛樂城 + 代幣派彩
			$d['summary'] = strtoupper($casinoId) . $transaction_category['tokenpay'];
			// 稽核方式
			$d['auditmode'] = $auditmode_select[strtolower($casinoId)];
			// 稽核金額 -- 派彩無須稽核
			$d['auditmodeamount'] = 0;
			// 娛樂城 取回的餘額為真錢
			$d['realcash'] = 2;
			// 交易類別 娛樂城 + $transaction_category['tokenpay']
			$d['transaction_category'] = 'tokenpay';
			// 變化的餘額
			$d['balance'] = $payout_balance;

			// 操作 root_member_wallets DB，把 casino_balance 設為 0，把 gtoken_lock = null，把 gtoken_balance = $d['deposit']
			// 錢包存入 餘額 , 把 casino_balance 扣除全部表示支出(投注)
			$payout_transaction_sql = $payout_transaction_sql . "
      			UPDATE root_member_wallets SET changetime = NOW(), gtoken_balance = " . $d['deposit'] . ", gtoken_lock = NULL,casino_accounts= jsonb_set(casino_accounts,'{\"" . strtoupper($casinoId) . "\",\"balance\"}','0') WHERE id = '" . $d['destination_transfer_id'] . "'; ";
			// 目的帳號上的註記
			$d['destination_notes'] = '(會員收到 ' . $defaultCasinoName . ' 派彩' . $d['balance'] . ' by 客服人員 ' . $_SESSION['agent']->account . ')';
			// 針對目的會員的存簿寫入，$payout_balance >= 1 表示贏錢，所以從出納匯款到使用者帳號
			$payout_transaction_sql = $payout_transaction_sql .
				'INSERT INTO "root_member_gtokenpassbook" ("transaction_time", "deposit", "withdrawal", "system_note", "member_id", "currency", "summary", "source_transferaccount", "auditmode", "auditmodeamount", "realcash", "destination_transferaccount", "transaction_category", "balance")' .
				"VALUES ('now()', '" . $d['deposit'] . "', '" . $d['withdrawal'] . "', '" . $d['destination_notes'] . "', '" . $d['member_id'] . "', '" . $config['currency_sign'] . "', '" . $d['summary'] . "', '" . $d['destination_transferaccount'] . "', '" . $d['auditmode'] . "', '" . $d['auditmodeamount'] . "', '" . $d['realcash'] . "', " .
				"'" . $d['destination_transferaccount'] . "','" . $d['transaction_category'] . "', (SELECT gtoken_balance FROM root_member_wallets WHERE id = " . $d['destination_transfer_id'] . ") );";

			// 針對來源出納的存簿寫入
			$payout_transaction_sql = $payout_transaction_sql . "
      			UPDATE root_member_wallets SET changetime = NOW(), gtoken_balance = (gtoken_balance - " . $d['balance'] . ") WHERE id = '" . $d['source_transfer_id'] . "'; ";
			// 來源帳號上的註記
			$d['source_notes'] = '(出納帳號 ' . $d['source_transferaccount'] . ' 幫 ' . $defaultCasinoName . ' 派彩到會員 ' . $d['destination_transferaccount'] . ')';
			$payout_transaction_sql = $payout_transaction_sql .
				'INSERT INTO "root_member_gtokenpassbook" ("transaction_time", "deposit", "withdrawal", "system_note", "member_id", "currency", "summary", "source_transferaccount", "auditmode", "auditmodeamount", "realcash", "destination_transferaccount", "transaction_category", "balance")' .
				"VALUES ('now()', '0', '" . $d['balance'] . "', '" . $d['source_notes'] . "', '" . $d['member_id'] . "', '" . $config['currency_sign'] . "', '" . $d['summary'] . "', '" . $d['source_transferaccount'] . "', '" . $d['auditmode'] . "', '" . $d['auditmodeamount'] . "', '" . $d['realcash'] . "', " .
				"'" . $d['source_transferaccount'] . "','" . $d['transaction_category'] . "', (SELECT gtoken_balance FROM root_member_wallets WHERE id = " . $d['source_transfer_id'] . "));";

			// commit 提交
			$payout_transaction_sql = $payout_transaction_sql . 'COMMIT;';
			if ($debug == 1) {
				echo '<p>SQL=' . $payout_transaction_sql . '</p>';
			}

			// 執行 transaction sql
			$payout_transaction_result = runSQLtransactions($payout_transaction_sql);
			if ($payout_transaction_result) {
				$logger = '从' . $defaultCasinoName . '帐号' . $member_casino_account . '取回余额游戏币，统计后收入=' . $api_balance . '，支出=' . $casino_balance_db . '，共计派彩=' . $payout_balance;
				$r['ErrorCode'] = 1;
				$r['ErrorMessage'] = $logger;
				memberlog2db($memberaccount, 'gpk2game', 'info', "$logger");
			} else {
				// 5.1 ~ 5.3 必須一定要全部成功，才算成功。如果 5.1 成功後，但 5.2、5.3 失敗的話，紀錄為 ERROR LOG，需要通知管理員處理(message system)
				$logger = '从' . $defaultCasinoName . '帐号' . $member_casino_account . '取回余额到游戏币，统计后收入=' . $api_balance . '，支出=' . $casino_balance_db . '，共计派彩=' . $payout_balance;
				$logger = $logger . '但资料库处理错误，请通知客服人员处理。';
				memberlog2db($d['member_id'], 'gpk2_transaction', 'error', "$logger");
				$r['ErrorCode'] = 406;
				$r['ErrorMessage'] = $logger;
				memberlog2db($memberaccount, 'gpk2game', 'error', "$logger");
			}

			if ($debug == 1) {
				var_dump($r);
			}
		} elseif ($payout_balance < 0) {
			// $payout_balance < 0; 從娛樂城輸錢
			// 先取得當下的  wallets 變數資料，等等 sql 更新後，就會消失了
			$wallets_sql = "SELECT gtoken_balance,casino_accounts->'" . strtoupper($casinoId) . "'->>'balance' as casino_balance FROM root_member_wallets WHERE id = '" . $d['destination_transfer_id'] . "';";
			$wallets_result = runSQLall($wallets_sql);

			// 在剛取出的 wallets 資料庫中 娛樂城 的餘額(支出)
			$gtoken_casino_balance_db = round($wallets_result[1]->casino_balance, 2);
			// 在剛取出的 wallets 資料庫中 gtoken(代幣) 的餘額(支出)
			$gtoken_balance_db = round($wallets_result[1]->gtoken_balance, 2);
			// 派彩 = 娛樂城餘額 - 本地端 支出餘額
			$gtoken_balance = round(($gtoken_balance_db + $gtoken_casino_balance_db + $payout_balance), 2);

			// 交易開始
			$payout_transaction_sql = 'BEGIN;';
			// 存款金額 -- 娛樂城餘額
			$d['deposit'] = $gtoken_balance;
			// 提款金額 -- 本地端支出
			$d['withdrawal'] = $casino_balance_db;
			// 操作者
			$d['member_id'] = $memberid;
			// (說明)娛樂城 + 代幣派彩
			$d['summary'] = strtoupper($casinoId) . $transaction_category['tokenpay'];
			// 稽核方式
			$d['auditmode'] = $auditmode_select[strtolower($casinoId)];
			// 稽核金額 -- 派彩無須稽核
			$d['auditmodeamount'] = 0;
			// 娛樂城 取回的餘額為真錢
			$d['realcash'] = 2;
			// 交易類別 娛樂城 + $transaction_category['tokenpay']
			$d['transaction_category'] = 'tokenpay';
			// 變化的餘額
			$d['balance'] = abs($payout_balance);

			// 操作 root_member_wallets DB，把 casino_balance 設為 0，把 gtoken_lock = null，把 gtoken_balance = $d['deposit']
			// 錢包存入 餘額，把 casino_balance 扣除全部表示支出(投注).
			$payout_transaction_sql = $payout_transaction_sql . "
      			UPDATE root_member_wallets SET changetime = NOW(), gtoken_balance = " . $d['deposit'] . ", gtoken_lock = NULL,casino_accounts= jsonb_set(casino_accounts,'{\"" . strtoupper($casinoId) . "\",\"balance\"}','0') WHERE id = '" . $d['destination_transfer_id'] . "'; ";
			// 目的帳號上的註記
			$d['destination_notes'] = '(會員收到 ' . $defaultCasinoName . ' 派彩' . $d['balance'] . ' by 客服人員 ' . $_SESSION['agent']->account . ')';
			// 針對目的會員的存簿寫入
			$payout_transaction_sql = $payout_transaction_sql .
				'INSERT INTO "root_member_gtokenpassbook" ("transaction_time", "deposit", "withdrawal", "system_note", "member_id", "currency", "summary", "source_transferaccount", "auditmode", "auditmodeamount", "realcash", "destination_transferaccount", "transaction_category", "balance")' .
				"VALUES ('now()', '" . $d['deposit'] . "', '" . $d['withdrawal'] . "', '" . $d['destination_notes'] . "', '" . $d['member_id'] . "', '" . $config['currency_sign'] . "', '" . $d['summary'] . "', '" . $d['destination_transferaccount'] . "', '" . $d['auditmode'] . "', '" . $d['auditmodeamount'] . "', '" . $d['realcash'] . "', " .
				"'" . $d['destination_transferaccount'] . "','" . $d['transaction_category'] . "', (SELECT gtoken_balance FROM root_member_wallets WHERE id = " . $d['destination_transfer_id'] . ") );";

			// 針對來源出納的存簿寫入
			$payout_transaction_sql = $payout_transaction_sql . "
      			UPDATE root_member_wallets SET changetime = NOW(), gtoken_balance = (gtoken_balance + " . $d['balance'] . ") WHERE id = '" . $d['source_transfer_id'] . "'; ";
			// 來源帳號上的註記
			$d['source_notes'] = '(出納帳號 ' . $d['source_transferaccount'] . ' 從會員 ' . $d['destination_transferaccount'] . ' 取回派彩餘額)';
			$payout_transaction_sql = $payout_transaction_sql .
				'INSERT INTO "root_member_gtokenpassbook" ("transaction_time", "deposit", "withdrawal", "system_note", "member_id", "currency", "summary", "source_transferaccount", "auditmode", "auditmodeamount", "realcash", "destination_transferaccount", "transaction_category", "balance")' .
				"VALUES ('now()', '" . $d['balance'] . "', '0', '" . $d['source_notes'] . "', '" . $d['member_id'] . "', '" . $config['currency_sign'] . "', '" . $d['summary'] . "', '" . $d['source_transferaccount'] . "', '" . $d['auditmode'] . "', '" . $d['auditmodeamount'] . "', '" . $d['realcash'] . "', " .
				"'" . $d['source_transferaccount'] . "','" . $d['transaction_category'] . "', (SELECT gtoken_balance FROM root_member_wallets WHERE id = " . $d['source_transfer_id'] . "));";

			// commit 提交
			$payout_transaction_sql = $payout_transaction_sql . 'COMMIT;';
			if ($debug == 1) {
				echo '<p>SQL=' . $payout_transaction_sql . '</p>';
			}

			// 執行 transaction sql
			$payout_transaction_result = runSQLtransactions($payout_transaction_sql);
			if ($payout_transaction_result) {
				$logger = '从' . $defaultCasinoName . '帐号' . $member_casino_account . '取回余额到游戏币，统计后收入=' . $api_balance . '，支出=' . $casino_balance_db . '，共计派彩=' . $payout_balance;
				$r['ErrorCode'] = 1;
				$r['ErrorMessage'] = $logger;
				memberlog2db($memberaccount, 'gpk2game', 'info', "$logger");
			} else {
				// 5.1 ~ 5.3 必須一定要全部成功，才算成功。如果 5.1 成功後，但 5.2、5.3 失敗的話，紀錄為 ERROR LOG，需要通知管理員處理(message system)
				$logger = '从' . $defaultCasinoName . '帐号' . $member_casino_account . '取回余额到游戏币，统计后收入=' . $api_balance . '，支出=' . $casino_balance_db . '，共计派彩=' . $payout_balance;
				$logger = $logger . '但资料库处理错误，请通知客服人员处理。';
				$r['ErrorCode'] = 406;
				$r['ErrorMessage'] = $logger;
				memberlog2db($memberaccount, 'gpk2game', 'error', "$logger");
			}
		}

		return ($r);
	}


	/**
	 *  用娛樂城帳號取得會員 ID
	 *
	 * @param mixed $casino 娛樂城 ID
	 * @param mixed $account 娛樂城帳號
	 * @param int $debug 除錯模式，預設 0 為關閉
	 *
	 * @return mixed 會員 ID
	 */
	function getMemberIdByCasinoAccount($casino, $account, $debug = 0)
	{
		$sql = 'SELECT id FROM root_member_wallets WHERE casino_accounts->\''. strtoupper($casino) .'\'->>\'account\' = \''. $account .'\';';
		$result = runSQLall($sql, $debug);
		$id = 0;
		if ($result[0] > 0) {
			$id = $result[1]->id;
		}
		return $id;
	}


	/**
	 *  娛樂城存錢資料庫相關操作
	 *
	 * @param mixed $apiResult API 確認交易資訊
	 * @param mixed $memberId  會員 ID
	 * @param int   $debug     除錯模式，預設 0 為關閉
	 *
	 * @return mixed 交易結果
	 */
	function depositDB($apiResult, $memberId, $debug = 0)
	{
		global $config;
		// API 執行結果
		$result = $apiResult['Result'];

		// 將目前所在的 ID 值驗證並取得帳戶資料
		$member_sql = "SELECT root_member.id,gtoken_balance,account,gtoken_lock,
                casino_accounts->'" . strtoupper($result->casino) . "'->>'account' as casino_account,
                casino_accounts->'" . strtoupper($result->casino) . "'->>'password' as casino_password,
                casino_accounts->'" . strtoupper($result->casino) . "'->>'balance' as casino_balance FROM root_member JOIN root_member_wallets ON root_member.id=root_member_wallets.id WHERE root_member.id = '" . $memberId . "';";
		$r = runSQLall($member_sql, $debug);

		// 娛樂城預設名稱
		$casinoLib = new casino_switch_process_lib();
		$casinoSql = 'SELECT display_name FROM "casino_list" WHERE "casinoid" = \''. strtoupper(strtoupper($result->casino))	.'\'';
		$displayName = runSQLall($casinoSql, $debug)[1]->display_name;
		$defaultCasinoName = $casinoLib->getCurrentLanguageCasinoName($displayName, 'default');

		if ($r[0] == 1 and $config['casino_transfer_mode'] == 1) {
			// 沒有 娛樂城 帳號的話，根本不可以進來。
			if ($r[1]->casino_account == null or $r[1]->casino_account == '') {
				$check_return['messages'] = '你還沒有 ' . $defaultCasinoName . ' 帳號。';
				$check_return['code'] = 12;
			} else {
				$memberId = $r[1]->id;
				$memberaccount = $r[1]->account;
				$casino_balance = round($r[1]->casino_balance, 2);
				$member_casino_account = $r[1]->casino_account;

				// 需要 gtoken_lock 沒有被設定的時候，才可以使用這功能。
				if ($r[1]->gtoken_lock == null or $r[1]->gtoken_lock == strtoupper($result->casino)) {
					// 動作： 將本地端所有的 gtoken 餘額 Deposit 到對應的帳戶
					$accountNumber = $member_casino_account;
					$amount = $r[1]->gtoken_balance;

					$API_result = $apiResult;
					if ($API_result['errorcode'] == 0 and $API_result['Status'] == 0 and $API_result['count'] >= 0) {
						// 本地端 db 的餘額處理
						$casino_balance = $casino_balance + $amount;
						$togtoken_sql = "UPDATE root_member_wallets SET gtoken_lock = '". strtoupper($result->casino) ."'  WHERE id = '$memberId';";
						$togtoken_sql .= 'UPDATE root_member_wallets SET gtoken_balance = gtoken_balance - \''.	$amount .'\',casino_accounts= jsonb_set(casino_accounts,\'{"'. strtoupper($result->casino) .'","balance"}\',\''. $casino_balance .'\') WHERE id = \''. $memberId .'\';';
						$togtoken_sql_result = runSQLtransactions($togtoken_sql, $debug);
						if ($togtoken_sql_result) {
							$check_return['messages'] = '所有GTOKEN余额已经转到' . $defaultCasinoName . '娱乐城。 ' . $defaultCasinoName . '转帐单号 ' . $API_result['Result']->transaction_id . $defaultCasinoName . '帐号' . $accountNumber . $defaultCasinoName . '新增' . $amount;
							$check_return['code'] = 1;
							memberlog2db($memberaccount, 'casino transferout', 'info', $check_return['messages']);
							$this->updateCasinoTransferRecord($result->id, $result->transaction_id, 1, 'success',	$check_return['messages']);
						} else {
							$check_return['messages'] = '余额处理，本地端资料库交易错误。';
							$check_return['code'] = 14;
							memberlog2db($memberaccount, 'casino transferout', 'error', $check_return['messages']);
							$this->updateCasinoTransferRecord($result->id, $result->transaction_id, 1, 'fail', $check_return['messages']);
						}
					}
				} else {
					$check_return['messages'] = '此帐号已经在 ' . $defaultCasinoName . ' 娱乐城活动，请勿重复登入。';
					$check_return['code'] = 11;
					member_casino_transferrecords('lobby', $defaultCasinoName, '0', $check_return['messages'], $memberId, 'warning');
				}
			}
		} else {
			$check_return['messages'] = '无此帐号 ID = ' . $memberId;
			$check_return['code'] = 0;
			member_casino_transferrecords('lobby', $defaultCasinoName, '0', $check_return['messages'], $memberId, 'fail');
		}

		return ($check_return);
	}
}