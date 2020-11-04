<?php
// ----------------------------------------------------------------------------
// Features:	API 遊戲清單網頁動作 controller
// File Name:	gapi_gamelist_management_action.php
// Author:		Letter
// Related:     gapi_gamelist_management.php
//              gapi_gamelist_management_lib.php
// Class:       gapi_gamelist.php
//              gapi_hall.php
//              gapi_import_gamelist.php
// Log:
// 2019.02.01 新建 Letter
// 2020.04.06 Feature #3540 【後台】娛樂城、遊戲多語系欄位實作 - 遊戲顯示名稱 Letter
// 2020.05.14 Bug #3955 【CS】VIP站後台，投注記錄查詢 > 進階搜尋 > 体育 > 搜尋不到 - 修改反水類別 Letter
// ----------------------------------------------------------------------------

session_start();

require_once dirname(__FILE__) ."/config.php";
// 支援多國語系
require_once dirname(__FILE__) ."/i18n/language.php";
// 自訂函數
require_once dirname(__FILE__) ."/lib.php";
require_once dirname(__FILE__) . "/lib_proccessing.php";
require_once dirname(__FILE__) . "/lib_file.php";
require_once dirname(__FILE__) . "/gapi_gamelist_management_lib.php";
require_once dirname(__FILE__) . "/gapi_gamelist_params.php";

require_once dirname(__FILE__) . "/statistics_report_lib.php";

require_once dirname(__FILE__) . "/casino_switch_process_lib.php";

// 只要 session 活著,就要同步紀錄該 agent user account in redis server db 1
Agent_RegSession2RedisDB();

// 檢查權限是否合法，允許就會放行。否則中止。
agent_permission();

$debug = 0;
// 同步狀態
$synchronized = 0;
$notSynchronize = 1;

// Url 參數驗證
if(isset($_GET['a'])) {
	$action = filter_var($_GET['a'], FILTER_SANITIZE_STRING);
} else {
	die($tr['Illegal test']);
}

// 初始化函式庫
$tool = new gapi_gamelist_management_lib();
$casinoLib = new casino_switch_process_lib();

// datatables
// 程式每次的處理量 -- 當資料量太大時，可以分段處理。 透過 GET 傳遞依序處理。
if(isset($_GET['length']) AND $_GET['length'] != NULL ) {
	$current_per_size = filter_var($_GET['length'],FILTER_VALIDATE_INT);
}else{
	$current_per_size = $page_config['datatables_pagelength'];
	//$current_per_size = 10;
}

// 起始頁面, 搭配 current_per_size 決定起始點位置
if(isset($_GET['start']) AND $_GET['start'] != NULL ) {
	$current_page_no = filter_var($_GET['start'],FILTER_VALIDATE_INT);
}else{
	$current_page_no = 0;
}

// datatable 回傳驗證用參數，收到後不處理直接跟資料一起回傳給 datatable 做驗證
if(isset($_GET['_'])){
	$secho = $_GET['_'];
}else{
	$secho = '1';
}

if (isset($_GET['update'])) {
	$update = $_GET['update'];
} else {
	$update = 0;
}

// controller action
if ($action == 'game_list') {
	// 取得匯入遊戲清單
	// 取得娛樂城參數, 至匯入遊戲資料表取出對應資料
	$cid = filter_var($_GET['cid'], FILTER_SANITIZE_STRING, FILTER_FLAG_STRIP_HIGH);
	// 每頁顯示多少
	$page['per_size'] = $current_per_size;
	// 目前所在頁數
	$page['no'] = $current_page_no;
	// 搜尋字串
	$search = '';
	if (isset($_GET['search']['value']) AND $_GET['search']['value'] != '') {
		$search = $_GET['search']['value'];
	}

	if ($update == 0) {
		// 同步 api 與 匯入 遊戲清單
		// 取得 api 遊戲清單
		$apiGameList = $tool->getGameListByCasino($cid);
		// 新增匯入遊戲
		$tool->insertNewGamelist($apiGameList);

		// 重新開啟匯入遊戲
		$tool->reopenImportGamelist($apiGameList);

		// 處理 api 已關閉遊戲，匯入遊戲也關閉
		// 取出 api games gamecode (gameid)
		$apiGamecodes = [];
		for ($i = 0; $i < count($apiGameList); $i++) {
			array_push($apiGamecodes, $apiGameList[$i]->getGamecode());
		}

		// 比對 api 與 匯入 遊戲，取出 api 不存在遊戲
		$checkList = $tool->getImportGamesByCasinoId($cid, $debug);
		$closeGames = [];
		for ($i = 0; $i < count($checkList); $i++) {
			if (!in_array($checkList[$i]->getGameid(), $apiGamecodes)) {
				array_push($closeGames, $checkList[$i]->getGameid());
			}
		}
		// 關閉 匯入 遊戲
		$tool->updateImportGamesOpen($cid, $closeGames);
		// 關閉 平台 已同步 遊戲
		$result = $tool->updatePlatformGamesOpen($cid, $closeGames, 0, 0, $debug);

		// 同步 匯入 與 平台 遊戲
		$list = $tool->getImportGamesByCasinoId($cid, $debug);
		for ($i = 0; $i < count($list) - 1; $i++) {
			if ($tool->isExistGame($list[$i]->getGameid(), $cid) > 0 AND $list[$i]->getIsNew() == $notSynchronize) {
				$platformGame = $tool->getGameByCasinoAndGameId($list[$i]->getGameid(), $cid);
				$tool->updateImportGameByPlatformGame($platformGame, $list[$i]);
			}
		}
		$update++;
	}

	// 組成 DataTables server-side 所需參數
	$tableConfig = array(
		'cid' => $cid,
		'pageNo' => $page['no'],
		'pagePerSize' => $page['per_size'],
		'sEcho' => $secho,
		'search' => $tool->translateSpecificChar("'", $search, 0),
		'orderDir' => $_GET['order'][0]['dir'],
		'orderCol' => $_GET['order'][0]['column'],
		'update' => $update
	);

	// 取得顯示資料
	$listDisplay = $tool->getImportGamelistByCasino($tableConfig, $debug);
	echo json_encode($listDisplay);
} elseif ($action == 'hall') {
	// 取得平台娛樂城
	$platformCasinoIds = $casinoLib->getOpenCasinoIds($debug);
	// 至 Gapi 取得娛樂城(遊戲廠商)清單
	global $tr;
	$api_casinos = $tool->getGameHalls();
	$html = '';
	$item = '';
	$btnHtml = '';
	if (count($api_casinos) > 0) {
		$item .= '<option id="all" value="" selected>'. $tr['Select'] . $tr['Casino'] .'</option>';
		for ($i = 0; $i < count($api_casinos); $i++) {
			$id = $api_casinos[$i]->getGamehall();
			$name = $api_casinos[$i]->getFullname();
			$displayName = ' ('. $casinoLib->getCasinoNameByCasinoId(strtoupper($id), $_SESSION['lang'], $debug) .') ';
			if (is_null($tool->getCasinoById($id)) or !in_array(strtoupper($id), $platformCasinoIds)) {
				continue;
			} elseif ($tool->isCloseCasino($id)) {
				$name .= '('. $tr['off'] .')';
			} else {
				$btnHtml .= '<button id="'. $id .'_btn" class="btn btn-success m-2 d-none" value="'. $id .'">'. $tr['update'] .'</button>';
			}
			$item .= '<option value="'. $id .'" >'. $name . $displayName .'</option>';
		}
		$btnHtml .= '<button id="batchSync" class="btn btn-success m-2 d-none">'. $tr['batch'] .
			$tr['synchronize']
			.'</button>';
		$html .= '<label for="'. $casino_query_id .'" class="col-form-label"></label>' .
				'<select class="form-control" id="'. $casino_query_id .'">'. $item .'</select>'. $btnHtml;
	} else {
		$html = $html . '<label for="casino_query">'. $tr['Casino'] .
			'<b>'. $tr['No such information'] .'</b>'.'</label>';
	}
	echo $html;
} elseif ($action == 'first') {
	// 是否為第一次進入平台
	if ($tool->isFirstTime()) {
		$gamelist = $tool->getGameListByCasino();
		$tool->insertNewGamelist($gamelist);
	}
} elseif ($action == 'save_import') {
	// 儲存匯入遊戲
	// 取得 row
	$row = filter_var($_POST['row'], FILTER_VALIDATE_INT);
	// 取得 ID
	$id = filter_var($_POST['gid'], FILTER_VALIDATE_INT);
	$game = $tool->getImportGameById($id);
	if (!is_null($game)) {
		// 取得頁面傳遞參數, 更新取得物件
		// 語系名稱
		$display_arr = $game->getDisplayName();
		$game->setCasinoId(filter_var($_POST['cid'], FILTER_SANITIZE_STRING, FILTER_FLAG_ENCODE_HIGH));
		$game->setGameid(filter_var($_POST['game_id'], FILTER_SANITIZE_STRING, FILTER_FLAG_ENCODE_HIGH));
		$game->setCategory(filter_var($_POST['category'], FILTER_SANITIZE_STRING, FILTER_FLAG_ENCODE_HIGH));
		$game->setSubCategory(filter_var($_POST['sub_category'], FILTER_SANITIZE_STRING, FILTER_FLAG_ENCODE_HIGH));
		$enName = '';
		if (isset($_POST['name'])) $enName = urldecode(filter_var($_POST['name'], FILTER_SANITIZE_ENCODED));
		$game->setGamename($enName);
		$display_arr['en-us'] = $enName;
		$cnName = '';
		if (isset($_POST['cn_name'])) $cnName = preg_replace('/([^A-Za-z0-9\p{Han}])/ui', '',urldecode($_POST['cn_name']));
		$game->setGamenameCn($cnName);
		$display_arr['zh-cn'] = $cnName;
		$game->setGameplatform(filter_var($_POST['platform'], FILTER_SANITIZE_STRING, FILTER_FLAG_ENCODE_HIGH));
		$hot = filter_var($_POST['hot'], FILTER_SANITIZE_STRING, FILTER_FLAG_ENCODE_HIGH);
		$mct = filter_var($_POST['mct'], FILTER_SANITIZE_STRING, FILTER_FLAG_ENCODE_HIGH);
		$tag = filter_var($_POST['tag'], FILTER_SANITIZE_STRING, FILTER_FLAG_ENCODE_HIGH);
		$category2 = filter_var($_POST['category2'], FILTER_SANITIZE_STRING, FILTER_FLAG_ENCODE_HIGH);
		$ms_old = $game->getMarketingStrategy();
		$msArr = array(
			'mct' => $mct,
			'cname' => $ms_old['cname'],
			'ename' => $ms_old['ename'],
			'image' => $ms_old['image'],
			'hotgame' => $hot,
			'freetrial' => $ms_old['freetrial'],
			'category_2nd' => $category2,
			'marketing_tag' => $tag
		);
		$game->setMarketingStrategy($msArr);
		$game->setImagefilename('');
		$game->setFavorableType(filter_var($_POST['favorable_type'], FILTER_SANITIZE_STRING, FILTER_FLAG_ENCODE_HIGH));

		// 多語系
		$i18n = filter_var($_POST['i18n'], FILTER_SANITIZE_STRING, FILTER_FLAG_STRIP_HIGH);
		if (!empty($i18n)) {
			$display_arr[$i18n] = urldecode(filter_var($_POST['display'], FILTER_SANITIZE_ENCODED));
		}
		$game->setDisplayName($display_arr);

		// 主分類名稱
		$categoryCN = $game->getCategoryCn();
		if (is_null($categoryCN) or empty($categoryCN)) {
			$categoryCN = $tool->getGameCategoryName($game->getCategory());
		}
		$game->setCategoryCn($categoryCN);

		// 更新物件
		$result = $tool->updateImportGame($game);
		if ($result > 0) {
			$tool->updateImportGameState($notSynchronize, $game->getId());
		}
		echo json_encode(array('result' => $result, 'row' => $row));
	} else {
		echo json_encode(array('result' => 0, 'row' => $row));
	}
} elseif ($action == 'getGame') {
	// 取得匯入遊戲
	$id = filter_var($_POST['id'], FILTER_VALIDATE_INT);
	$game = $tool->getImportGameById($id);
	echo json_encode($game);
} elseif ($action == 'quickSync') {
	// 快速同步匯入遊戲至平台遊戲
	// 取得 row
	$row = filter_var($_POST['row'], FILTER_VALIDATE_INT);
	// 取得 ID
	$id = filter_var($_POST['id'], FILTER_VALIDATE_INT);

	// 取得反水分類
	$favorableTypes = getFavorableTypes();

	// 取得匯入遊戲
	$game = $tool->getImportGameById($id);
	if (!is_null($game)) {
		// 判斷反水分類是否填寫
		$existFavorableType = false;
		foreach ($favorableTypes as $key => $value) {
			if (in_array($game->getFavorableType(), $favorableTypes[$key])) {
				$existFavorableType = true;
				break;
			}
		}
		$game->setGamenameCn(preg_replace('/([^A-Za-z0-9\p{Han}])/ui', '', urldecode($game->getGamenameCn())));
		$game->setGametype(' ');
		$game->setOpen(0); // 預設同步至平台為關閉遊戲

		// 主分類名稱
		$categoryCN = $game->getCategoryCn();
		if (is_null($categoryCN) or empty($categoryCN)) {
			$categoryCN = $tool->getGameCategoryName($game->getCategory());
		}
		$game->setCategoryCn($categoryCN);

		if ($tool->isMatchedPlatform($game->getGameplatform()) AND
			$tool->isMatchedCategory($game->getCategory()) AND	$existFavorableType){
			// 確認平台是否存在遊戲
			$exist = $tool->isExistGame($game->getGameid(), $game->getCasinoId(), $debug);
			if ($exist > 0) { // 存在更新
				$result = $tool->syncUpdateGame($game, $exist);
			} else { // 不存在新增
				$result = $tool->syncCreateGame($game);
			}

			// 更新匯入遊戲狀態為 0 已同步
			if ($result > 0) {
				$tool->updateImportGameState($synchronized, $id, $debug);
			} else {
				$tool->updateImportGameState($notSynchronize, $id, $debug);
			}
			echo json_encode(array('result' => $result, 'row' => $row, 'exist' => $exist));
		} else {
			// 編輯狀態不符合
			echo json_encode(array('result' => -1, 'row' => $row));
		}
	} else {
		echo json_encode(array('result' => 0, 'row' => $row));
	}
} elseif ($action == 'getGameplatform') {
	$platform = $tool->getGamelistGameplatform();
	$oldPlatform = filter_var($_POST['platform'], FILTER_SANITIZE_STRING, FILTER_FLAG_ENCODE_HIGH);
	$html = '';
	// 比對匯入遊戲遊戲平台是否與平台遊戲遊戲平台相符
	if (!$tool->isMatchedPlatform($oldPlatform)) {
		$html .= '<option value="'. $oldPlatform .'">'. $oldPlatform .'</option>';
	}
	if (count($platform) > 0) {
		for ($i = 1; $i <= count($platform) - 1; $i++) {
			$html .= '<option value="'. $platform[$i] .'">'. $platform[$i] .'</option>';
		}

	}

	echo $html;
} elseif ($action == 'getGameCategory') {
	$categories = $tool->getGamelistCategory();
	$html = '<option value=""></option>';
	if (count($categories) > 0) {
		for ($i = 1; $i <= count($categories) - 1; $i++) {
			$categoryName = '';
			if (isset($tr[$categories[$i]])) {
				$categoryName = $tr[$categories[$i]];
			} else {
				$categoryName = $categories[$i];
			}
			$html .= '<option value="'. $categories[$i] .'">'. $categoryName .'</option>';
		}
	}

	echo $html;
} elseif ($action == 'getCategoryByCasino') {
	$cid = filter_var($_GET['cid'], FILTER_SANITIZE_STRING);
	$categories = $tool->getFavorableToMainCategoryByCasino($cid, $debug);
	$html = '<option value=""></option>';
	if (count($categories) > 0) {
		for ($i = 0; $i < count($categories); $i++) {
			$categoryName = '';
			if (isset($tr[$categories[$i]])) {
				$categoryName = $tr[$categories[$i]];
			} else {
				$categoryName = $categories[$i];
			}
			$html .= '<option value="'. $categories[$i] .'">'. $categoryName .'</option>';
		}
	}

	echo $html;
} elseif ($action == 'batchSync') {
	$result = 0;
	for ($i = 0; $i < count($_POST); $i++) {
		// 取得匯入遊戲
		$game = $tool->getImportGameById($_POST[$i]);
		if (!is_null($game)) {
			$game->setGamenameCn(preg_replace('/([^A-Za-z0-9\p{Han}])/ui', '', urldecode($game->getGamenameCn())));
			$game->setGametype(' ');
			$game->setOpen(0); // 預設同步至平台為關閉遊戲

			// 主分類名稱
			$categoryCN = $game->getCategoryCn();
			if (is_null($categoryCN) or empty($categoryCN)) {
				$categoryCN = $tool->getGameCategoryName($game->getCategory());
			}
			$game->setCategoryCn($categoryCN);

			if ($tool->isMatchedPlatform($game->getGameplatform()) AND
				$tool->isMatchedCategory($game->getCategory()) AND
				$tool->isMatchFavorableType($game->getFavorableType())){
				// 確認平台是否存在遊戲
				$exist = $tool->isExistGame($game->getGameid(), $game->getCasinoId(), $debug);
				if ($exist > 0) { // 存在更新
					$result = $tool->syncUpdateGame($game, $exist);
				} else { // 不存在新增
					$result = $tool->syncCreateGame($game);
				}

				// 更新匯入遊戲狀態為 0 已同步
				if ($result > 0) {
					$tool->updateImportGameState($synchronized, $_POST[$i], $debug);
				} else {
					$tool->updateImportGameState($notSynchronize, $_POST[$i], $debug);
				}
				echo json_encode(array('result' => $result));
				break;
			} else {
				// 編輯狀態不符合
				$result = -1;
				echo json_encode(array('result' => $result));
				break;
			}
		} else {
			$result = 0;
			echo json_encode(array('result' => $result));
			break;
		}
	}

} elseif ($action == 'favorableTypes') {
	// 反水類別選項
	global $tr;
	$cid = filter_var($_GET['cid'], FILTER_SANITIZE_STRING);
	$types = $casinoLib->getCasinoPlatformByCasinoId(strtoupper($cid), $debug);
	$html = '<option value=""></option>';
	for ($i = 0; $i < count($types); $i++) {
		$name = isset($tr[$types[$i]]) ? $tr[$types[$i]] : $types[$i];
		$html .= '<option value="'. $types[$i] .'">'. $name .'</option>';
	}
	echo $html;
} elseif (isset($_GET['a']) AND $_GET['a'] == 'i18nGameNames') { // 取得語系顯示名稱
	$gid = filter_var($_GET['gid'], FILTER_SANITIZE_NUMBER_INT);

	// 取得遊戲所有語系名稱
	$names = $tool->getGameNameLanguage($gid, $debug);

	echo json_encode($names);
} elseif (isset($_GET['a']) AND $_GET['a'] == 'updateName') { // 更新語系名稱
	$gid = filter_var($_POST['id'], FILTER_SANITIZE_NUMBER_INT);
	$i18n = filter_var($_POST['i18n'], FILTER_SANITIZE_STRING);
	$name = '';
	if (isset($_POST['name'])) $name = urldecode(filter_var($_POST['name'], FILTER_SANITIZE_ENCODED));

	echo json_encode(
		array(
			'result' => $tool->updateGameNameByLanguage($gid, $i18n, $name, $debug),
			'gid' => $gid
		)
	);
} elseif (isset($_GET['a']) AND $_GET['a'] == 'mct') { // 取得行銷類別選項
	// 取得行銷類別與反水類別對應關係
	global $gamelobby_setting;
	global $tr;
	$categories = $gamelobby_setting['main_category_info'];
	$mapping = array();
	foreach ($categories as $key => $value) {
		$mapping[$value['flatform']] = $key;
	}

	// 取得娛樂城反水類別
	$cid = filter_var($_GET['cid'], FILTER_SANITIZE_STRING);
	$gamePlatformList = $casinoLib->getCasinoPlatformToMCTMapping(strtoupper($cid), $debug);

	// 對應反水類別與行銷(主要)類別
	$casinoFavorableToMCT = array();
	foreach ($gamePlatformList as $value) {
		$casinoFavorableToMCT[$value] = $mapping[$value];
	}

	$html = '';
	foreach ($casinoFavorableToMCT as $value) {
		$name = isset($tr[$value]) ? $tr[$value] : $value;
		$html .= '<option value="'. $value .'">'. $name .'</option>';
	}

	echo $html;
}
