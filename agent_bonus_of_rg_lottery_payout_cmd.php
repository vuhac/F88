#!/usr/bin/php70
<?php
// ----------------------------------------------------------------------------
// Features:	後台-- RG彩票彩金計算 -- 個人分紅傭金發放 -- 獨立排程執行
// File Name:	agent_bonus_of_rg_lottery_payout_cmd.php
// Author:		Letter
// Related:	bonus_commission_agent.php
// 			bonus_commission_agent_action.php
// DB table:  root_receivemoney 彩金發放
// Log:
// ----------------------------------------------------------------------------
// How to run ?
// usage command line :
// Regular /usr/bin/php70 agent_bonus_of_rg_lottery_payout_cmd.php test/run
// Fill up /usr/bin/php70 agent_bonus_of_rg_lottery_payout_cmd.php test/run start_datetime(20190101123000) end_datetime(20190101143000)
// ----------------------------------------------------------------------------

// ----------------------------------------------------------------------------
// session_start();

$stats_showdata_count = 0;
$stats_insert_count = 0;
$stats_update_count = 0;

// ----------------------------------------------------------------------------

require_once dirname(__FILE__) ."/config.php";
require_once dirname(__FILE__) ."/config_betlog.php";
// 自訂函式庫
require_once dirname(__FILE__) ."/lib.php";

// 確保這個 script 執行不會因為 user abort 而中斷!!
// Ignore user aborts and allow the script to run forever
ignore_user_abort(true);
// disable php time limit , 60*60 = 3600sec = 1hr
set_time_limit(7200);

// 程式 debug 開關, 0 = off , 1= on
$debug = 0;
$casinoId = 'RG';
$root_id = 1;
$projectId = $config['projectid'];
global $RGAPI_CONFIG;

/**
 * 隨意字串生成器
 *
 * @param int $count 需要字串長度
 *
 * @return string 生成字串
 */
function randomStrGenerator($count = 5)
{
	$seed = str_split('abcdefghijklmnopqrstuvwxyz'
		. 'ABCDEFGHIJKLMNOPQRSTUVWXYZ'
		. '0123456789_');
	shuffle($seed);
	$rand = '';
	foreach (array_rand($seed, $count) as $k) $rand .= $seed[$k];
	return $rand;
}


/**
 * 生成API Key
 *
 * @param string $secret API演算key
 * @param array  $params 參數
 *
 * @return string API Key
 */
function genApiKey(string $secret, array $params = []): string
{
	$head = randomStrGenerator(5);
	$footer = randomStrGenerator(5);
	$middle = '';
	foreach ($params as $key => $value) {
		if ($key == 'memberBranch') {
			$middle = $middle . $key . '=' . json_encode($value) . '&';
		} else {
			$middle = $middle . $key . '=' . $value . '&';
		}
	}
	return $head . md5($middle . 'Key=' . $secret) . $footer;
}


/**
 * 驗證日期格式
 * get example: ?current_datepicker=2017-02-03
 * ref: http://php.net/manual/en/function.checkdate.php
 *
 * @param mixed $date 日期
 * @param string $format 日期格式
 *
 * @return bool 是否符合
 */
function validateDate($date, $format = 'Y-m-d H:i:s')
{
		$d = DateTime::createFromFormat($format, $date);
		return $d && $d->format($format) == $date;
}


/**
 * 驗證時間順序
 *
 * @param string $startDatetime 開始日期時間
 * @param string $endDatetime 結束日期時間
 *
 * @return bool 時間順序是否正確,  true 表結束時間晚於開始時間
 */
function validateChronological(string $startDatetime, string $endDatetime)
{
    return strtotime($endDatetime) > strtotime($startDatetime);
}


/**
 * 轉換 RG 娛樂城帳號為平台會員 ID
 *
 * @param string $account   娛樂城帳號
 * @param string $projectId 平台 ID
 *
 * @return string 會員 ID
 */
function transRGAccount2MemberId(string $account, string $projectId)
{
    $id = substr($account, strlen($projectId), strlen($account) - strlen($projectId));
    $count = strlen($id);
	if ($count == 10) {
        $memberId = (int)$id - 2000000000;
	} else {
		$memberId = (int)$id - 20000000000;
    }
    return $memberId;
}


/**
 * 由 ID 取得 會員帳號
 *
 * @param mixed $id 會員 ID
 *
 * @return mixed 會員帳號
 */
function getAccountById($id)
{
    $account_sql = 'SELECT "account" FROM "root_member" WHERE id = \''. $id .'\'';
	$results = runSQLall($account_sql);
    return $results[1]->account;
}


/**
 * 取得遊戲名稱
 *
 * @param mixed $gameId 遊戲ID
 * @param mixed $casinoId 娛樂城ID
 *
 * @return string 遊戲名稱
 */
function getGameCNNameByGameIdAndCasino($gameId, $casinoId)
{
    $gameNameSql = 'SELECT "gamename_cn" FROM "casino_gameslist" WHERE "casino_id" = \''. $casinoId .'\' AND "gameid" = \''. $gameId .'\'';
    $results = runSQLall($gameNameSql);
    return $results[0] > 0 ? $results[1]->gamename_cn : '';
}


/**
 * 生成彩票返點資訊，資訊切割矩陣如下
 * 0 => 玩家:
 * 1 => kt120000010018
 * 2 => 遊戲代號:
 * 3 => 1107
 * 4 => 局號:
 * 5 => 6429119499678100
 * 6 => 下注:
 * 7 => 10
 * 8 => 反佣金額:0.14
 * 9 => 您的返佣級數:
 * 10 => 2.9
 * 11 => 下層返佣級數:
 * 12 => 1.5
 * 13 => 返佣%:
 * 14 => 1.4%
 *
 * @param mixed $result       彩票注單
 * @param string $record       彩票返點資訊
 * @param string $projectId    平台 ID
 * @param float  $money        返佣金額
 * @param string $casinoId     娛樂城 ID
 * @param  mixed $RGAPI_CONFIG     RG API 設定
 *
 * @return string 摘要文字
 */
function genRGFSSummary($result, string $record, string $projectId, float $money, string
$casinoId, $RGAPI_CONFIG)
{
    $summary = '彩票返点: ';
    $tempStr = json_decode($record);
    $tempStrArr = explode(' ', $tempStr->fs_note);
	for ($i = 0; $i < count($tempStrArr); $i++) {
		if ($i == 1) {
            $account = getAccountById(transRGAccount2MemberId($tempStrArr[$i], $projectId));
			$summary = $summary . '来自会员 ' . $account . ' 投注 ';
		} elseif ($i == 14) {
			$RG_API_data = array(
				'masterId' => $result->masterId,
				'memberId' => $result->memberId,
				'gameId' => $result->gameId,
				'fs' => $tempStrArr[$i]
			);
			$gameName = getGameCNNameByGameIdAndCasino($result->gameId, $casinoId);
			$url = $RGAPI_CONFIG['api_url'] . $RGAPI_CONFIG['sub_url']['LotteryMasterOddsTable'];
			$key = genApiKey($RGAPI_CONFIG['apikey'], $RG_API_data);
			$apiUrl = $url . 'Key=' . $key . '&masterId=' . $RG_API_data['masterId'] . '&memberId=' .
				$RG_API_data['memberId'] . '&gameId=' . $RG_API_data['gameId'] . '&fs=' . $RG_API_data['fs'];
			$linkWithName = <<<HTML
<a href="$apiUrl" target="_blank" rel="noopener noreferrer">{$gameName}</a>  
HTML;
			$summary = $summary . $linkWithName .' 彩票 (注单号 '. $result->wagersId .') '. $tempStrArr[7] .' 元, 得到 '.
                $tempStrArr[$i] . ' 彩金 ' . $money . ' 元';
		}
	}
    return $summary;
}


/**
 * 顯示測試模式下訊息, 若非測試模式則不會顯示
 *
 * @param string $mode 模式
 * @param string $msg 訊息
 */
function showTestModeMessage(string $mode, string $msg)
{
	if ($mode == 'test') {
        var_dump($msg);
	}
}


/**
 * 確認彩金池內時間區間內存在 ID
 *
 * @param int $id ID
 * @param  string $start 開始日期
 * @param  string $end 結束日期
 * @param  int $debug 是否為除錯模式
 *
 * @return bool True 表示 ID 存在
 */
function isIdExist(int $id, $start, $end, $debug)
{
    $checkSql = 'SELECT "system_note" FROM "root_receivemoney" WHERE system_note = \'{"rg_id":"'. $id .'"}\' 
        AND CAST("givemoneytime" AS TIMESTAMP) BETWEEN to_timestamp(\''. $start .'\', \'YYYYMMDDHH24MISS\') 
        AND to_timestamp(\''. $end .'\', \'YYYYMMDDHH24MISS\')';
    $result = runSQLall($checkSql, $debug);
    return $result[0] > 0;
}


/**
 * 以無條件捨去法取得給予位數的彩金
 *
 * @param float $money 彩金
 * @param int   $precision 小數位數
 *
 * @return float|int 捨去給予小數位數後彩金
 */
function getReceiveMoneyOfGivenPrecision(float $money, int $precision)
{
    $trans = pow(10, $precision);
    return floor($money * $trans) / $trans;
}


/**
 * 取得代理商剩餘彩金
 *
 * @param int $memberId 會員ID
 * @param int $debug 是否為除錯模式, 0為非除錯模式
 *
 * @return mixed 代理商剩餘彩金
 */
function getAgentLeftMoney(int $memberId, int $debug)
{
    $sql = 'SELECT "recivemoney_count"->>\'RG\' FROM "root_member_wallets" WHERE "id" = '. $memberId .';';
    $result = runSQLall($sql, $debug);
    return is_null(get_object_vars($result[1])['?column?']) ? (float)0.0000 : number_format(get_object_vars
    ($result[1])['?column?'], 4);
}


/**
 * 更新代理商剩餘彩金
 *
 * @param float  $left     剩餘彩金更新值
 * @param int    $memberId 會員ID
 * @param int    $debug    是否為除錯模式, 0為非除錯模式
 * @param string $argv_check 命令列執行模式
 *
 */
function updateAgentReceiveMoneyInWallet(float $left, int $memberId, int $debug, string $argv_check)
{
	if ($left >= 0) {
		$record = array('RG' => number_format($left, 4));
		$sql = 'UPDATE "root_member_wallets" SET "recivemoney_count" = \''. json_encode($record) .'\' WHERE "id" = '.
			$memberId .';';
		if ($argv_check == 'test') {
			var_dump($sql);
		} elseif ($argv_check == 'run') {
			runSQLall($sql, $debug);
		}
		echo "Add {$left} left bonus to member wallet \n";
	} else {
		echo "No left bonus need add to member wallet \n";
    }

}


/**
 * 剩餘彩金寫入彩金池
 *
 * @param int $id 會員ID
 * @param float $left 剩餘彩金
 * @param string $date 日期
 * @param int $rootId Root ID
 * @param int $debug 是否為除錯模式, 0為非除錯模式
 * @param string $argv_check 命令列執行模式
 */
function insertLeftMoney($id, $left, $date, $rootId, $debug, $argv_check)
{
	$money['member_id'] = $id;
	$money['member_account'] = getAccountById($money['member_id']);
	$money['gcash_balance'] = 0;
	$money['gtoken_balance'] = $left;
	$money['givemoneytime'] = $date;
	$money['receivedeadlinetime'] = date("Y-m-d H:i:sP", strtotime('+1 month', strtotime($date)));
	$money['prizecategories'] = '彩票反奌累积满额';
	$money['auditmode'] = 'freeaudit';
	$money['auditmodeamount'] = 0;
	$money['summary'] = '彩票反奌累积回馈 '. $left .' 元';
	$money['transaction_category'] = 'tokenpreferential';
	$money['system_note'] = '';
	$money['givemoney_member_account'] = getAccountById($rootId); // root account
	$money['status'] = 1;

	// 寫入彩金資料表
	$money_sql = <<<SQL
      INSERT INTO root_receivemoney (
			member_id,
			member_account,
			gcash_balance,
			gtoken_balance,
			givemoneytime,
			receivedeadlinetime,
			prizecategories,
			auditmode,
			auditmodeamount,
			summary,
			transaction_category,
			system_note,
			givemoney_member_account,
			status
		) VALUES (
			'{$money['member_id']}',
			'{$money['member_account']}',
			'{$money['gcash_balance']}',
			'{$money['gtoken_balance']}',
			'{$money['givemoneytime']}',
			'{$money['receivedeadlinetime']}',
			'{$money['prizecategories']}',
			'{$money['auditmode']}',
			'{$money['auditmodeamount']}',
			'{$money['summary']}',
			'{$money['transaction_category']}',
			'{$money['system_note']}',
			'{$money['givemoney_member_account']}',
			'{$money['status']}'
		);
SQL;

	if ($argv_check == 'test') {
		showTestModeMessage($argv_check, $money_sql);
	} elseif ($argv_check == 'run') {
		$money_results = runSQLall($money_sql, $debug);
	}
	echo "Pay out {$left} to receivemoney table\n";
}


/**
 * 確認會員錢包剩餘彩金是否可以發放, 如可發放打入彩金池, 更新會員錢包餘額
 *
 * @param int    $precision    取到小數幾位
 * @param array  $idList 需檢查 ID 清單
 * @param int    $debug        是否為除錯模式, 0為非除錯模式
 * @param string $current_date 今日日期
 * @param int    $root_id      root ID
 * @param string $argv_check   是否為測試模式
 */
function checkMemberWalletReceiveMoney(int $precision, array $idList, int $debug, $current_date, int $root_id,
                                       $argv_check)
{
    $checkList = array_unique($idList);
    if ($debug ==1 or $argv_check == 'test') var_dump($checkList);
    $counts = 0;
	foreach ($checkList as $value) {
	    echo "Check {$value} left bonus... \n";
		$leftMoney = getAgentLeftMoney($value, $debug);
		$checkLeft = getReceiveMoneyOfGivenPrecision($leftMoney, $precision);
		$updateBonus = $leftMoney - $checkLeft;
		if ($checkLeft > 0) {
			insertLeftMoney($value, $checkLeft, $current_date, $root_id, $debug, $argv_check);
			updateAgentReceiveMoneyInWallet($updateBonus, $value, $debug, $argv_check);
			echo "Pay out bonus {$checkLeft}, left bonus {$updateBonus} \n";
			$counts++;
		}
	}
	echo "回饋 {$counts} 筆調整彩金 \n";
}


// -----------------------------------------------------------------
// 安全控管, 如果是 web 執行就立即中斷, 只允許 command 執行此程式。
// -----------------------------------------------------------------
// var_dump($_SERVER);
// 如果 HTTP_USER_AGENT OR SERVER_NAME 存在, 表示是直接透過網頁呼叫程式, 拒絕這樣的呼叫
if(isset($_SERVER['HTTP_USER_AGENT']) OR isset($_SERVER['SERVER_NAME'])) {
  die('禁止使用網頁呼叫，來源錯誤，請使用命令列執行。');
}
//if(isset($_SERVER['USER']) AND $_SERVER['USER'] == 'nginx' ) {
//  die('不允許使用網頁執行，請確認你的呼叫來源。');
//}
// -----------------------------------------------------------------
// 命令列參數解析
// -----------------------------------------------------------------

// 取得今天的日期
// 轉換為美東的時間 date
$date = date_create(date('Y-m-d H:i:sP'), timezone_open('Asia/Taipei'));
date_timezone_set($date, timezone_open('Asia/Taipei'));
$current_date = date_format($date, 'Y-m-d H:i:sP');

echo "Start grab RG lottery bonus data...... \n";
// 命令列解析
if(isset($argv[1]) AND ($argv[1] == 'test' OR $argv[1] == 'run') ){
	if (isset($argv[2]) and isset($argv[3])) {
		if(isset($argv[2])){
			//如果有的話且格式正確, 取得日期. 沒有的話中止
			if (validateDate($argv[2], 'YmdHis')) {
				echo "Start datetime is {$argv[2]}\n";
				$start_datetime = $argv[2];
			} else {
				// command 動作 時間
				echo "command [test|run] startdatetime(YYYYmmddHHiiss) enddatetime(YYYYmmddHHiiss)\n";
				die('Start datetime format is wrong');
			}
		}
		if(isset($argv[3])){
			//如果有的話且格式正確, 取得日期. 沒有的話中止
			if (validateDate($argv[3], 'YmdHis')) {
				echo "End datetime is {$argv[3]}\n";
				$end_datetime = $argv[3];
			} else {
				// command 動作 時間
				echo "command [test|run] startdatetime(YYYYmmddHHiiss) enddatetime(YYYYmmddHHiiss)\n";
				die('End datetime format is wrong');
			}
		}
		if (!validateChronological($argv[2], $argv[3])) {
			echo "command [test|run] startdatetime(YYYYmmddHHiiss) enddatetime(YYYYmmddHHiiss)\n";
			die('Datetime Chronological is wrong');
		}
	} elseif(isset($argv[2]) or isset($argv[3])) {
		// command 動作 時間
		echo "command [test|run] startdatetime(YYYYmmddHHiiss) enddatetime(YYYYmmddHHiiss)\n";
		die('Need both start and end datetime');
    } elseif (!isset($argv[2]) and !isset($argv[3])) {
		echo "Regular command process\n";
	}
  $argv_check = $argv[1];
} else{
  // command 動作
  echo "command [test|run] \n";
  die('No command execute');
}


if (isset($start_datetime) and isset($end_datetime)) {
	// 0.補單
    // 0.1 取得補單時間區間 rg_betrecords table 資料
    $check_sql = 'SELECT * FROM "rg_betrecords" WHERE CAST ("catchTime" AS TIMESTAMP ) 
        BETWEEN to_timestamp(\''. $start_datetime .'\', \'YYYYMMDDHH24MISS\') 
        AND to_timestamp(\''. $end_datetime .'\', \'YYYYMMDDHH24MISS\') AND "type" = 10 AND "site" = \'' . $projectId .'\';';
    showTestModeMessage($argv_check, $check_sql);
    $check_results = runSQLall_betlog($check_sql, $debug, $casinoId);

    // 若沒有資料就結束
	if ($check_results[0] == 0) {
        echo "\n There are no data to add \n";
        return;
	}
    // 0.2 寫入 receivemoney table
    $total = $check_results[0];
	$limit = 1000; // 每次處理量
	$insertCount = 0;
	$bonusIdList = array();
	for ($i = 0; $i < $total; ($total-$i) < $limit ? $i=$i+($total-$i) : $i=$i+$limit) {
	    // 分批取出資料
        $check_sql = 'SELECT * FROM "rg_betrecords" WHERE CAST ("catchTime" AS TIMESTAMP ) 
            BETWEEN to_timestamp(\''. $start_datetime .'\', \'YYYYMMDDHH24MISS\') 
            AND to_timestamp(\''. $end_datetime .'\', \'YYYYMMDDHH24MISS\') AND "type" = 10 
            AND "site" = \''. $projectId .'\' ORDER BY "id" OFFSET '. $i .' LIMIT '. $limit .';';
		showTestModeMessage($argv_check, $check_sql);
        $check_results = runSQLall_betlog($check_sql, $debug, $casinoId);

		for ($j = 1; $j <= $check_results[0]; $j++) {
			if (isIdExist($check_results[$j]->id, $start_datetime, $end_datetime, $debug)) {
                echo "Statement ID {$check_results[$j]->id} is exist... \n";
                continue;
			}
		    $tempLastId = $check_results[$j]->id;
			// 對應欄位
			$id = transRGAccount2MemberId($check_results[$j]->memberId, $config['projectid']);
			if ($id == $root_id) continue;
			$money['member_id'] = $id;
			$money['member_account'] = getAccountById($money['member_id']);
			$money['gcash_balance'] = 0;

			// 處理遊戲與平台小位位數不同問題
			$payout = getReceiveMoneyOfGivenPrecision($check_results[$j]->winloseAmount, 2);
			$left = $check_results[$j]->winloseAmount - $payout;
			$wallet = getAgentLeftMoney($id, $debug);
			$left = $left + $wallet;
			updateAgentReceiveMoneyInWallet($left, $id, $debug, $argv_check);
			if ($payout > 0) { // 以平台位數取值大於 0, 繼續彩金發放
				$money['gtoken_balance'] = $payout;
			} else { // 取值後無法發放彩金, 換下一筆
			    continue;
            }

			$money['givemoneytime'] = $current_date;
			$money['receivedeadlinetime'] = date("Y-m-d H:i:sP", strtotime('+1 month', strtotime($current_date)));
			$money['prizecategories'] = str_replace('-', '', substr($check_results[$j]->betTime, 0, 10)) . 'lottery_RG';
			$money['auditmode'] = 'freeaudit';
			$money['auditmodeamount'] = 0;
			$money['summary'] = genRGFSSummary($check_results[$j], $check_results[$j]->gameRecord, $projectId,
                $money['gtoken_balance'], $casinoId, $RGAPI_CONFIG);
			$money['transaction_category'] = 'tokenpreferential';
			$money['system_note'] = '{"rg_id":"'. $check_results[$j]->id .'"}';
			$money['givemoney_member_account'] = getAccountById($root_id); // root account
			$money['status'] = 1;

            // 寫入彩金資料表
			$money_sql = <<<SQL
      INSERT INTO root_receivemoney (
			member_id,
			member_account,
			gcash_balance,
			gtoken_balance,
			givemoneytime,
			receivedeadlinetime,
			prizecategories,
			auditmode,
			auditmodeamount,
			summary,
			transaction_category,
			system_note,
			givemoney_member_account,
			status
		) VALUES (
			'{$money['member_id']}',
			'{$money['member_account']}',
			'{$money['gcash_balance']}',
			'{$money['gtoken_balance']}',
			'{$money['givemoneytime']}',
			'{$money['receivedeadlinetime']}',
			'{$money['prizecategories']}',
			'{$money['auditmode']}',
			'{$money['auditmodeamount']}',
			'{$money['summary']}',
			'{$money['transaction_category']}',
			'{$money['system_note']}',
			'{$money['givemoney_member_account']}',
			'{$money['status']}'
		);
SQL;

			if ($argv_check == 'test') {
				showTestModeMessage($argv_check, $money_sql);
			} elseif ($argv_check == 'run') {
				$money_results = runSQLall($money_sql, $debug);
			}
			echo "Insert Statement ID {$check_results[$j]->id} bonus {$money['gtoken_balance']} to DB \n";
			array_push($bonusIdList, $id);
			$insertCount++;
		}
	}
	echo "\n共補 {$insertCount} 筆彩金資料\n";
	// 檢查累積剩餘彩金是否可以發放
	checkMemberWalletReceiveMoney(2, $bonusIdList, $debug, $current_date, $root_id, $argv_check);
} else {
	// 1.從注單原始資料取得各級代理商反水
    // 1.1 從 receivemoney table 取得最後一筆處理資料的 versionKey
    // 取得 system_note 欄位不為 null 的最新資料
	$lastId = 0;
	$lastId_sql = 'SELECT system_note FROM "root_receivemoney" WHERE system_note LIKE \'%rg_id%\' ORDER BY id DESC LIMIT 1;';
	showTestModeMessage($argv_check, $lastId_sql);
	$lastId_result = runSQLall($lastId_sql);

    // 確認取回資料是否有值
	if ($lastId_result[0] > 0) {
		$rgId = json_decode($lastId_result[1]->system_note, true)['rg_id'];
		if (isset($rgId) && $rgId > 0) {
			$lastId = $rgId;
			echo "Last ID is {$lastId} \n";
		}
	}

    // 1.2 從 rg_betrecords table 取得 versionKey 大於 1.1 versionKey 資料
	$check_sql = 'SELECT * FROM "rg_betrecords" WHERE "id" > '.$lastId.' AND "type" = 10 AND "site" = \''
		. $projectId .'\';';
	showTestModeMessage($argv_check, $check_sql);
	$check_results = runSQLall_betlog($check_sql, $debug, $casinoId);

    // 2.轉換原始資料裡 gameRecord 欄位反水資訊
    // 將原始資料直接紀錄在彩金的 summary 欄位
	if ($check_results[0] == 0) { // 沒有新的反水單
		echo "\n There are no data to add... \n";
		return;
	}

	$bonusIdList = array();
    $insertCount = 0;
    // 3.寫入彩金 table
	for ($i = 1; $i <= $check_results[0]; $i++) {
		// 3.1 對應 rg_betrecords 與 receivemoney 欄位，整理資料
		$id = transRGAccount2MemberId($check_results[$i]->memberId, $config['projectid']);
		if ($id == $root_id) {
			echo "\n We do not add root data to DB... \n";
			continue;
		}
		$money['member_id'] = $id;
		$money['member_account'] = getAccountById($money['member_id']);
		$money['gcash_balance'] = 0;

		// 處理遊戲與平台小位位數不同問題
		$payout = getReceiveMoneyOfGivenPrecision($check_results[$i]->winloseAmount, 2);
		$left = $check_results[$i]->winloseAmount - $payout;
		$wallet = getAgentLeftMoney($id, $debug);
		$left = $left + $wallet;
		updateAgentReceiveMoneyInWallet($left, $id, $debug, $argv_check);
		if ($payout > 0) { // 以平台位數取值大於 0, 繼續彩金發放
			$money['gtoken_balance'] = $payout;
		} else { // 取值後無法發放彩金, 換下一筆
			continue;
		}

		$money['givemoneytime'] = $current_date;
		$money['receivedeadlinetime'] = date("Y-m-d H:i:sP", strtotime('+1 month', strtotime($current_date)));
		$money['prizecategories'] = str_replace('-', '', substr($check_results[$i]->betTime, 0, 10)) . '彩票反奌_RG';
		$money['auditmode'] = 'freeaudit';
		$money['auditmodeamount'] = 0;
		$money['summary'] = genRGFSSummary($check_results[$i], $check_results[$i]->gameRecord, $projectId, $money['gtoken_balance'], $casinoId, $RGAPI_CONFIG);
		$money['transaction_category'] = 'tokenpreferential';
		$vkeyArr = array('rg_versionKey' => $check_results[$i]->versionKey);
		$money['system_note'] = '{"rg_id":"'. $check_results[$i]->id .'"}';
		$money['givemoney_member_account'] = getAccountById($root_id); // root account
		$money['status'] = 1;

		// 3.2 寫入 receivemoney table
		$money_sql = <<<SQL
      INSERT INTO root_receivemoney (
			member_id,
			member_account,
			gcash_balance,
			gtoken_balance,
			givemoneytime,
			receivedeadlinetime,
			prizecategories,
			auditmode,
			auditmodeamount,
			summary,
			transaction_category,
			system_note,
			givemoney_member_account,
			status
		) VALUES (
			'{$money['member_id']}',
			'{$money['member_account']}',
			'{$money['gcash_balance']}',
			'{$money['gtoken_balance']}',
			'{$money['givemoneytime']}',
			'{$money['receivedeadlinetime']}',
			'{$money['prizecategories']}',
			'{$money['auditmode']}',
			'{$money['auditmodeamount']}',
			'{$money['summary']}',
			'{$money['transaction_category']}',
			'{$money['system_note']}',
			'{$money['givemoney_member_account']}',
			'{$money['status']}'
		);
SQL;

		if ($argv_check == 'test') {
			var_dump($money_sql);
		} elseif ($argv_check == 'run') {
			$money_results = runSQLall($money_sql, $debug);
		}
		echo "Insert statement ID {$check_results[$i]->id} bonus {$money['gtoken_balance']} to DB \n";
		array_push($bonusIdList, $id);
		$insertCount++;
	}
	echo "\n共收入 {$insertCount} 筆彩金資料\n";

	// 檢查累積剩餘彩金是否可以發放
	checkMemberWalletReceiveMoney(2, $bonusIdList, $debug, $current_date, $root_id, $argv_check);
}

?>
