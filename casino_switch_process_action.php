<?php
// ----------------------------------------------------------------------------
// Features:	後台--娛樂城管理
// File Name:	casino_switch_process_action.php
// Author:		snowiant@gmail.com
// Related:
//    casino_switch_process.php casino_switch_process_cmd.php
//    DB table: casino_list
//    casino_switch_process_action：有收到 casino_switch_process.php 透過ajax 傳來的  _GET 時會將 _GET
//        取得的值進行驗證，之後進行娛樂城狀態變更，如果收到的是啟用娛樂城，直接設定 casino_list 中的 open 為 1，
//        如果是收到停用娛樂城，直接設定 casino_list 中的 open 為 2，並在背景執行 casino_switch_process_cmd.php
//        ，再以 ajax 丟給 casino_switch_process.php 一個 reload 來顯示變更後的狀態，待
//        casino_switch_process_cmd.php 執行完後會自行變更 casino_list 中的 open 為 0。
// Log:
// 2019.03.13 新增娛樂城停用狀態 Letter
//    娛樂城啟用開關 (0=關閉/1=開啟/2=關閉程序處理中/3=緊急維護/4=停用)
// 2019.05.13 後台娛樂城與遊戲上線流程 #1839 Letter
// ----------------------------------------------------------------------------

session_start();

// 主機及資料庫設定
require_once dirname(__FILE__) ."/config.php";
// 支援多國語系
require_once dirname(__FILE__) ."/i18n/language.php";
// 自訂函式庫
require_once dirname(__FILE__) ."/lib.php";
// 遊戲管理列表專用函式庫
require_once dirname(__FILE__) ."/casino_switch_process_lib.php";
// GAPI 函式庫
require_once dirname(__FILE__) . "/gapi_gamelist_management_lib.php";
require_once dirname(__FILE__) . "/gapi_hall.php";

// ----------------------------------------------------------------------------
// 只要 session 活著,就要同步紀錄該 user account in redis server db 1
Agent_RegSession2RedisDB();
// ----------------------------------------------------------------------------

// ----------------------------------------------------------------------------
// 檢查權限是否合法，允許就會放行。否則中止。
agent_permission();
// ----------------------------------------------------------------------------


// datatables
// 程式每次的處理量 -- 當資料量太大時，可以分段處理。 透過 GET 傳遞依序處理。
if(isset($_GET['length']) AND $_GET['length'] != NULL ) {
  $current_per_size = filter_var($_GET['length'],FILTER_VALIDATE_INT);
}else{
  $current_per_size = $page_config['datatables_pagelength'];
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

$debug = 0;
global $tr;
$lib = new casino_switch_process_lib();

// 取得權限
$permission = $lib->getPermissionByAccount($_SESSION['agent']->account);
$isOps = $permission == 'ops';
$isMaster = $permission == 'master';
$isTherole = $permission == 'R';

// ----------------------------------------------------------------------------
// MAIN
// ----------------------------------------------------------------------------

// --------------------------------------------------------------------------
// 取得 GET 傳來的變數
// 判斷娛樂城是要啟用還是停用，如果是停用，則同時設定要背景執行的程序
// --------------------------------------------------------------------------
$lib = new casino_switch_process_lib();
$api = new gapi_gamelist_management_lib();

if(isset($_GET)){

  if(isset($_GET['casinostate']) AND $_GET['casinostate'] == casino::$casinoOn AND isset($_GET['casinoid']) AND isset($_GET['emgsign'])) { // 開啟娛樂城
    $query_casinoid = filter_var($_GET['casinoid'], FILTER_SANITIZE_STRING, FILTER_FLAG_STRIP_HIGH);
    $query_sql = 'UPDATE casino_list SET open = \''. casino::$casinoOn .'\' WHERE casinoid=\'' .$query_casinoid. '\';';
    $json_filename = 'log/'.$query_casinoid.'.json';
    if (file_exists($json_filename)) {
      unlink($json_filename);
    }
  } elseif (isset($_GET['casinostate']) AND ($_GET['casinostate'] != casino::$casinoOn) AND isset($_GET['casinoid']) AND isset($_GET['emgsign'])) {
    // 關閉、緊急維護及停用娛樂城
    if ($_GET['emgsign'] == casino::$maintenanceOn) { // 緊急維護
      $open = filter_var($_GET['casinostate'], FILTER_SANITIZE_STRING, FILTER_FLAG_STRIP_HIGH);
      $query_casinoid = filter_var($_GET['casinoid'], FILTER_SANITIZE_STRING, FILTER_FLAG_STRIP_HIGH);
      $query_sql = 'UPDATE casino_list SET open = \''. $open .'\' WHERE casinoid=\''.$query_casinoid.'\';';
    } else { // 關閉或停用(暫時或永久)
      $casionState = filter_var($_GET['casinostate'], FILTER_SANITIZE_STRING, FILTER_FLAG_STRIP_HIGH);
      $query_casinoid = filter_var($_GET['casinoid'], FILTER_SANITIZE_STRING, FILTER_FLAG_STRIP_HIGH);
      $query_sql = 'UPDATE casino_list SET open = \''. $casionState .'\' WHERE casinoid=\''.$query_casinoid.'\';';
      if ($debug == 1) { // 是否為除錯模式
        $command   = $config['PHPCLI'].' casino_switch_process_cmd.php test '.$query_casinoid.' '. $casionState .' > log/casino_switch_process.log &';
      } else {
        $command   = $config['PHPCLI'].' casino_switch_process_cmd.php run '.$query_casinoid.' '. $casionState .' > log/casino_switch_process.log &';
      }
    }
  } elseif (isset($_GET['a']) AND $_GET['a'] == 'orderchg' AND isset($_GET['casinoid']) AND isset($_GET['order']) AND
      (filter_var($_GET['order'], FILTER_VALIDATE_INT) || filter_var($_GET['order'], FILTER_VALIDATE_INT) === 0)){ // 自訂排序
    $query_casinoid = filter_var($_GET['casinoid'], FILTER_SANITIZE_STRING, FILTER_FLAG_STRIP_HIGH);
    $query_casinoorder = filter_var($_GET['order'], FILTER_VALIDATE_INT);
    $query_sql = 'UPDATE casino_list SET casino_order = \''.$query_casinoorder.'\' WHERE casinoid=\''.$query_casinoid.'\';';

  } elseif (isset($_GET['a']) AND $_GET['a'] == 'status') { // 依選擇娛樂城狀態顯示
    // 取得娛樂城狀態
    $status = filter_var($_GET['casinoStatus'], FILTER_SANITIZE_STRING);

    // 顯示永久關閉娛樂城
    $show = false;
    if (isset($_GET['deprecated'])) {
        $show = true;
    }

    // 取得排序欄位
    $sortIndex = filter_var($_GET['order'][0]['column'], FILTER_SANITIZE_NUMBER_INT);
    $sortFormation = filter_var($_GET['order'][0]['dir'], FILTER_SANITIZE_STRING);
    $sortConfig = getSortTableColumn($sortIndex, $sortFormation, $permission);

    // 取得符合狀態娛樂城
    $casinos = $lib->getCasinosByStatus($status, $show, $sortConfig, $debug);
    $totalRecords = count($casinos);

    // 自訂排序
    if ($isOps and $sortIndex == 5) {
        $noOrderArr = array();
        $orderArr = array();
        for ($i = 0; $i < $totalRecords; $i++) {
            if ($casinos[$i]->getCasinoOrder() == 0) {
                array_push($noOrderArr, $casinos[$i]);
            } else {
                array_push($orderArr, $casinos[$i]);
            }
        }
        $casinos = array();
        if ($sortFormation == 'asc') {
            $casinos = mergeSortedArray($casinos, $orderArr);
            $casinos = mergeSortedArray($casinos, $noOrderArr);
        } elseif ($sortFormation == 'desc') {
            $casinos = mergeSortedArray($casinos, $noOrderArr);
            $casinos = mergeSortedArray($casinos, $orderArr);
        }
    } elseif ($isMaster and $sortIndex == 8) {
        $noOrderArr = array();
        $orderArr = array();
        for ($i = 0; $i < $totalRecords; $i++) {
            if ($casinos[$i]->getNewAlert() == 1) {
                array_push($noOrderArr, $casinos[$i]);
            } else {
                array_push($orderArr, $casinos[$i]);
            }
        }
        $casinos = array();
        if ($sortFormation == 'asc') {
            $casinos = mergeSortedArray($casinos, $orderArr);
            $casinos = mergeSortedArray($casinos, $noOrderArr);
        } elseif ($sortFormation == 'desc') {
            $casinos = mergeSortedArray($casinos, $noOrderArr);
            $casinos = mergeSortedArray($casinos, $orderArr);
        }
    }

    // 轉換日期格式
    for ($i = 0; $i < $totalRecords; $i++) {
        $ndTimestamp = $casinos[$i]->getNotifyDatetime();
        if (!is_null($ndTimestamp)) {
            $nd = new DateTime($ndTimestamp);
            $nd = $nd->format('Y-m-d H:i');
        } else {
            $nd = '';
        }
        $casinos[$i]->setNotifyDatetime($nd);
    }

    // 取得符合頁數娛樂城
    $casinos = array_slice($casinos, $current_page_no, $current_per_size);
    debugMode($debug, $casinos);

    // 組合 Datatable 所需要參數及資料格式
    $dataTable = array(
        "sEcho" => intval($secho),
        'iTotalRecords' => intval($current_per_size),
        'iTotalDisplayRecords' => $totalRecords,
        'data' => $casinos
    );

    echo json_encode($dataTable);

  } elseif (isset($_GET['a']) AND $_GET['a'] == 'sort') { // 排序
      $id = filter_var($_GET['id'], FILTER_SANITIZE_STRING);
      $order = filter_var($_GET['order'], FILTER_SANITIZE_STRING);

      $result = $lib->updateCasinoOrder($id, $order, $debug);
      return $result[0];

  } elseif (isset($_GET['a']) AND $_GET['a'] == 'update') { // 更新欄位數值
      $column = isset($_GET['col']) ? filter_var($_GET['col'], FILTER_SANITIZE_STRING) : '';
      $value = isset($_GET['val']) ? filter_var($_GET['val'], FILTER_SANITIZE_STRING) : '';
      $id = filter_var($_GET['id'], FILTER_SANITIZE_STRING);

      $result = $lib->updateCasinoColumnById($id, $column, $value, $debug);
      echo json_encode(array('result' => $result[0]));

  } elseif (isset($_GET['a']) AND $_GET['a'] == 'recheck') { // 永久關閉再確認
      $password = $_SESSION['agent']->passwd;
      $checkPassword = filter_var($_POST['pw'], FILTER_SANITIZE_STRING);
      if (sha1($checkPassword) == $password) {
          echo json_encode(array('result' => 1));
      } else {
          echo json_encode(array('result' => 0));
      }
  } elseif (isset($_GET['a']) AND $_GET['a'] == 'getCasino') {
      $casinoid = filter_var($_POST['id'], FILTER_SANITIZE_STRING);
      $sql = 'SELECT * FROM casino_list WHERE "casinoid" = \''. $casinoid . '\';';
      $result = runSQLall($sql, $debug);
      if ($result[0] > 0) {
          $casino = new casino(
              $result[1]->id,
              $result[1]->casinoid,
              $lib->getCurrentLanguageCasinoName($result[1]->casino_name, 'default'),
              $result[1]->casino_dbtable,
              $result[1]->note,
              $result[1]->open,
              $result[1]->account_column,
              $result[1]->bettingrecords_tables,
              $result[1]->casino_order,
              json_decode($result[1]->game_flatform_list, true),
              $result[1]->notify_datetime,
              $result[1]->api_update,
              $lib->getCurrentLanguageCasinoName($result[1]->casino_name, $_SESSION['lang']),
              $lib->getNewAlert($result[1]->notify_datetime),
              $lib->getCurrentLanguageCasinoName($result[1]->display_name, 'zh-cn'),
              $lib->getCurrentLanguageCasinoName($result[1]->display_name, 'en-us')
          );
      } else {
          $casino = null;
      }
      return $casino;
  } elseif (isset($_GET['a']) AND $_GET['a'] == 'getLanguageSelector') {
      $casinoid = filter_var($_GET['cid'], FILTER_SANITIZE_STRING);

      $casinoNames = $lib->getAllLanguageCasinoNames($casinoid);

      echo json_encode($casinoNames);
  } elseif (isset($_GET['a']) AND $_GET['a'] == 'updateLanguageCasinoName') {
      $casinoId = filter_var($_POST['casinoId'], FILTER_SANITIZE_STRING);
      $langKey = filter_var($_POST['langKey'], FILTER_SANITIZE_STRING);
      $casinoName = filter_var($_POST['casinoName'], FILTER_SANITIZE_ENCODED);

      echo json_encode($lib->updateCasinoNameByLanguage($casinoId, $langKey, $casinoName, $debug));
  } elseif (isset($_GET['a']) AND $_GET['a'] == 'getCategory') {
      $casinoId = filter_var($_GET['cid'], FILTER_SANITIZE_STRING);
      if (empty($casinoId)) {
          $casinoFlatformList = array();
      } else {
          $casino = $lib->getCasinoByCasinoId($casinoId, $debug);
          $casinoFlatformList = $casino->getGameFlatformList();
      }
      $html = '';
      for ($i = 0; $i < count(casino::$gameFlatform); $i++) {
          if (in_array(casino::$gameFlatform[$i], $casinoFlatformList)) {
              $html .= '<div class="form-check form-check-inline">
		            <input name="gameFlatform" class="form-check-input" id="category'.$i.'" type="checkbox" value="'. casino::$gameFlatform[$i] .'" checked>
		            <label class="form-check-label pr-5" for="category'.$i.'">'. $tr[casino::$gameFlatform[$i]] .'</label>
	            </div>';
          } else {
              $html .= '<div class="form-check form-check-inline">
		            <input name="gameFlatform" class="form-check-input" id="category'.$i.'" type="checkbox" value="'. casino::$gameFlatform[$i] .'">
		            <label class="form-check-label pr-5" for="category'.$i.'">'. $tr[casino::$gameFlatform[$i]] .'</label>
                </div>';
          }
      }
      echo $html;
  } elseif (isset($_GET['a']) AND $_GET['a'] == 'updateCasino') {
      // 取得參數
      $rowIndex = filter_var($_POST['rowIndex'], FILTER_SANITIZE_NUMBER_INT);
      $casinoId = filter_var($_POST['casinoId'], FILTER_SANITIZE_STRING);
      $cnName = filter_var($_POST['cnName'], FILTER_SANITIZE_STRING);
      $enName = filter_var($_POST['enName'], FILTER_SANITIZE_STRING);
      $langKey = filter_var($_POST['langKey'], FILTER_SANITIZE_STRING);
      $displayName = filter_var($_POST['displayName'], FILTER_SANITIZE_STRING);
      $categories = explode(",", filter_var($_POST['categories'], FILTER_SANITIZE_STRING));

      // 取得要更新的娛樂城
      $casino = $lib->getCasinoByCasinoId($casinoId, $debug);
      // 取得所有語系娛樂城名稱
      $allDisplayName = $lib->getAllLanguageCasinoNames($casinoId, $debug);
      // 新簡中娛樂城名稱
      if (!empty($cnName) or is_null($cnName)) {
          $allDisplayName['zh-cn']['name'] = $cnName;
      }
      // 新英文娛樂城名稱
      if (!empty($enName) or is_null($enName)) {
          $allDisplayName['en-us']['name'] = $enName;
      }
      // 新語系娛樂城名稱
      if (!empty($langKey) or is_null($langKey)) {
          $allDisplayName[$langKey]['name'] = $displayName;
      }
      $newDisplayNames = [];
      $allDisplayNameKeys = array_keys($allDisplayName);
      for ($i = 0; $i < count($allDisplayName); $i++) {
          $newDisplayNames[$allDisplayNameKeys[$i]] = $allDisplayName[$allDisplayNameKeys[$i]]['name'];
      }

      // 更新資料
      $casino->setGameFlatformList($categories);
      $casino->setDisplayName($newDisplayNames);

      // 更新娛樂城
      echo json_encode([
          'result' => $lib->updateCasino($casino, $debug),
          'row' => $rowIndex
      ]);

  } elseif (isset($_GET['a']) AND $_GET['a'] == 'getApiCasinos') {
      $returnCasinos = [];
      $apiCasinos = $api->getGameHalls($debug);
      // 組成選項
      $returnHtml = '';
      for ($i = 0; $i < count($apiCasinos); $i++) {
          if (!$lib->existCasino($apiCasinos[$i]->getGamehall())) {
              $returnHtml .= '<option value="'. $apiCasinos[$i]->getGamehall() .'">'. $apiCasinos[$i]->getFullname() .'</option>';
          }
      }

      echo $returnHtml;
  } elseif (isset($_GET['a']) AND $_GET['a'] == 'genCreateCasino') {
      $casinoId = filter_var($_GET['casinoId'], FILTER_SANITIZE_STRING);

      $displayNameStr = '';
      global $supportLang;
      $supportLangKeys = array_keys($supportLang);
      // 取得 API 娛樂城資料
      $apiCasinos = $api->getGameHalls($debug);
      for ($i = 0; $i < count($apiCasinos); $i++) {
          if ($apiCasinos[$i]->getGamehall() == $casinoId) {
              // 處理多語系娛樂城名稱
              for ($j = 0; $j < count($supportLangKeys); $j++) {
                  if ($j == 0) {
                      $displayNameStr .= '{"default": "'. $casinoId .'", ';
                      $displayNameStr .= '"'. $supportLangKeys[$j] .'": "'. $apiCasinos[$i]->getFullname() .'", ';
                  } elseif ($j == (count($supportLangKeys) - 1)) {
                      $displayNameStr .= '"'. $supportLangKeys[$j] .'": "'. $apiCasinos[$i]->getFullname() .'"} ';
                  } else {
                      $displayNameStr .= '"'. $supportLangKeys[$j] .'": "'. $apiCasinos[$i]->getFullname() .'", ';
                  }
              }
              // 建立回傳 casino
              $casino = new casino(
                  0,
                  strtoupper($casinoId),
                  $apiCasinos[$i]->getFullname(),
                  'casino_gameslist',
                  $apiCasinos[$i]->getFullname(),
                  0,
                  strtolower($casinoId) . '_account',
                  strtolower($casinoId) . '_bettingrecords_tables',
                  0,
                  json_encode([]),
                  NULL,
                  NULL,
                  $displayNameStr,
                  NULL,
                  $apiCasinos[$i]->getFullname(),
                  $apiCasinos[$i]->getFullname()
              );
              break;
          }
      }

      echo json_encode($casino);
  } elseif (isset($_GET['a']) AND $_GET['a'] == 'getSupportLanguage') {
      global $supportLang;
      global $tr;
      $supportLangKeys = array_keys($supportLang);
      $returnLangs['default'] = $tr['grade default'];
      for ($i = 0; $i < count($supportLangKeys); $i++) {
          $returnLangs[$supportLangKeys[$i]] = $supportLang[$supportLangKeys[$i]]['display'];
      }
      echo json_encode($returnLangs);
  } elseif (isset($_GET['a']) AND $_GET['a'] == 'createCasino') {
      // 取得參數
      $casinoId = filter_var($_POST['casinoId'], FILTER_SANITIZE_STRING);
      $cnName = filter_var($_POST['cnName'], FILTER_SANITIZE_STRING);
      $enName = filter_var($_POST['enName'], FILTER_SANITIZE_STRING);
      $displayName = filter_var($_POST['displayName'], FILTER_SANITIZE_STRING);
      // 多語系名稱
      $i18nNamesArr = explode(",", $displayName);
      $i18nNames = [];
      for ($i = 0; $i < count($i18nNamesArr); $i++) {
          $key = explode("=", $i18nNamesArr[$i])[0];
          $val = explode("=", $i18nNamesArr[$i])[1];
          $i18nNames[$key] = $val;
      }

      $categories = explode(",", filter_var($_POST['categories'], FILTER_SANITIZE_STRING));
      if (empty($casinoId) or empty($cnName) or empty($enName)) {
          echo json_encode(
              array(
                  'result' => -1
              )
          );
      } else {
          // 處理多語系娛樂城名稱
          global $supportLang;
          $supportLangKeys = array_keys($supportLang);
          // 處理預設、英文、簡中語系名稱
          $i18nNames['default'] = empty($i18nNames['default']) ? $casinoId : $i18nNames['default'];
          $i18nNames['en-us'] = $enName;
          $i18nNames['zh-cn'] = $cnName;
          for ($j = 0; $j < count($supportLangKeys); $j++) {
              $name =  empty($enName) ? $casinoId : $enName;
              if (is_null($i18nNames[$supportLangKeys[$j]]) or empty($i18nNames[$supportLangKeys[$j]])) {
                  $i18nNames[$supportLangKeys[$j]] = $name;
              }
          }

          // 建立 casino 物件
          $newCasino = new casino(
              0,
              strtoupper($casinoId),
              $enName,
              'casino_gameslist',
              $cnName,
              0,
              strtolower($casinoId) . '_account',
              strtolower($casinoId) . '_bettingrecords_tables',
              0,
              json_encode($categories),
              NULL,
              NULL,
              json_encode($i18nNames),
              NULL,
              $cnName,
              $enName
          );

          // 寫入資料庫
          echo json_encode(
              array(
                  'result' => $lib->createCasino($newCasino, $debug)
              )
          );
      }

  } else { // 未傳送參數
    echo '<script>alert("'. $tr['error message params error'].'");</script>';
  }

}



// --------------------------------------------------------------------------
// 將 Get 取得的變數加入一個array變數 $query_sql_array
// --------------------------------------------------------------------------

// -------------------------------------------
// 先進行娛樂城的狀態更新
// -------------------------------------------
// Qeury Sequence
if(isset($query_casinoid)){
  $query_check_sql = 'SELECT * FROM casino_list WHERE casinoid=\''.$query_casinoid.'\';';
  $query_check_result = runSQL($query_check_sql);
  if($query_check_result == '1' AND isset($query_sql)){
    $query_result = runSQL($query_sql);
    if($query_result == '1'){
      // echo '操作成功<script>setTimeout(function(){window.location.href="casino_switch_process.php"},3000);</script>';
      // 判斷是否需要執行背景程式進行回收代幣
      if(isset($command)){
        passthru($command);
      }
      echo json_encode(array('result' => $query_result));
    } else {
      // echo $tr['error message db uppdate error'];
      echo json_encode(array('result' => $query_result));
    }
  }else{
    // echo $tr['error message casino is not found'];
      echo json_encode(array('result' => $query_check_result));
  }

}


/**
 * 取得排序對應欄位
 *
 * @param int $index Datatables 欄位 index
 * @param string $dir 排序方法，asc 為遞增，desc 為遞減
 * @param string $permission 權限
 *
 * @return array 排序欄位與方法
 */
function getSortTableColumn($index, $dir, $permission)
{
    switch ($permission) {
        case 'ops':
            // 欄位 index 資料表欄位對應
            $columnsIndexToName = array(
                0 => 'id',
                1 => 'casinoid',
                2 => 'display_name->>\'zh-cn\'',
                3 => 'display_name->>\'en-us\'',
                4 => 'display_name->>\''. $_SESSION['lang'] .'\'',
                5 => 'casino_order',
                6 => 'notify_datetime',
                7 => 'open',
                8 => 'open',
                9 => 'open',
                10 => 'open'
            );
            $result = array('columnIndex' => $columnsIndexToName[$index], 'sortFormat' => $dir);
            break;
        case 'master':
            // 欄位 index 資料表欄位對應
            $columnsIndexToName = array(
                0 => 'id',
                1 => 'casinoid',
                2 => 'display_name->>\'zh-cn\'',
                3 => 'display_name->>\'en-us\'',
                4 => 'display_name->>\''. $_SESSION['lang'] .'\'',
                5 => 'casino_order',
                6 => 'open',
                7 => 'open',
                8 => 'notify_datetime'
            );
            $result = array('columnIndex' => $columnsIndexToName[$index], 'sortFormat' => $dir);
            break;
        case 'R':
            // 欄位 index 資料表欄位對應
            $columnsIndexToName = array(
                0 => 'id',
                1 => 'display_name->>\'zh-cn\'',
                2 => 'display_name->>\'en-us\'',
                3 => 'display_name->>\''. $_SESSION['lang'] .'\'',
                4 => 'open',
                5 => 'open'
            );
            $result = array('columnIndex' => $columnsIndexToName[$index], 'sortFormat' => $dir);
            break;
        default:
            $result = array();
            break;
  }

  return $result;
}


function mergeSortedArray($baseArr, $sortedArr)
{
    $count = count($sortedArr);
    for ($i = 0; $i < $count; $i++) {
        array_push($baseArr, $sortedArr[$i]);
    }
    return $baseArr;
}

?>
