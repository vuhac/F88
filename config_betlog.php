<?php
// ----------------------------------------
// Features:	前台 -- 投注紀錄系統設定檔
// File Name:	config_betlog.php
// Author:		Barkley
// Related:   抓投注單的專用資料庫用 SQL lib 及參數
// Log:
// -----------------------------------------------------------------------------

// -----------------------------------------------------------------------------
// 切換系統模式及資料庫
// -----------------------------------------------------------------------------
// 請在根目錄下建立一個檔案 version.txt 檔案內容放置 release or developer , 依據此變數自動判斷目前所在開發環境
$version_url = dirname(__FILE__).'/version.txt';
if(file_exists($version_url)) {

	// casino betlog dbname
	$betlogpdo['dbname']['MG']	= "sm_betlogmg";
	$betlogpdo['dbname']['MEGA']	= "sm_betlogmega";
	$betlogpdo['dbname']['PT']	= "sm_betlogpt";
	$betlogpdo['dbname']['EC']	= "sm_betlogec";
	$betlogpdo['dbname']['IGHKT']	= "sm_betlogighkt";
	$betlogpdo['dbname']['IGSST']	= "sm_betlogigsst";
	$betlogpdo['dbname']['RG']	= "betlogrg";
	$betlogpdo['dbname']['remix']	= (isset($betlogpdo['dbname']['remix']))? $betlogpdo['dbname']['remix'] : $config['projectid']."_betlog";


	// -------------------------------------------------------------------------
	// 前台 + 後台 -- 統計報表用的變數
	// 相關檔案名稱： web\gpk2\statistics_daily_report_lib.php , web\gpk2\betrecord_deltail.php
	// 相關檔案名稱： web\begpk2\token_auditorial.php,
	// -------------------------------------------------------------------------


	// 投注單資料庫，專用資料表。
	// 通用的betlog sql 函式
	function runSQLall_betlog($sql="SET NAMES 'utf8';", $debug="0", $casinoid='remix')
		{
			// ref:http://php.net/manual/en/book.pdo.php
			// db 帳號密碼變數 global
			global  $betlogpdo;

			// 建立 DB 連線
			try {
				$dbh_string = $betlogpdo['db'].':dbname='.$betlogpdo['dbname'][$casinoid].';host='.$betlogpdo['host'];
				$dbh = new PDO("$dbh_string", $betlogpdo['user'], $betlogpdo['password'] );
			} catch (PDOException $e) {
				print "DB connect Error!: " . $e->getMessage() . "<br/>";
				die();
			}

			// 切換 schema
			//$default_schema = 'SET search_path TO gpk;';
			//$sql = $default_schema.$sql;

			// sql 執行
			$sth = $dbh->prepare("$sql");
			// var_dump($sql);
			// echo "\nsql=$sql";
			$db_dump_result_all = NULL;
			// 如果執行成功, 就把資料以 FETCH_OBJ 方式拿出來
			if($sth->execute()) {
				// 放置紀錄數量
				$db_dump_result_all[0] = $sth->rowCount();
				// 所有資料取出, 會花費時間儲存變數
				$i=1;
				while($db_dump_result = $sth->fetch(PDO::FETCH_OBJ)) {
					$db_dump_result_all[$i] = $db_dump_result;
					$i++;
				}
			}else{
				// 請參考 postgresql error code 對應表 https://www.postgresql.org/docs/9.4/static/errcodes-appendix.html
				$debug_message = "runSQLall_betlog ERROR: ["
					. "\nerrorCode:".$sth->errorCode()
					. "\ninfo:".$sth->errorInfo()[2]
					. "\n]\n";
				if($debug == 1) {
					var_dump($sql);
					echo "\nsql=$sql";
				}
				error_log( date("Y-m-d H:i:s").' '.$debug_message.' SQL:'.$sql);
				$db_dump_result_all = FALSE;
				echo "$debug_message";
				die();
			}

			// 顯示除錯資訊
			if($debug == 1) {
				var_dump($sql);
				echo "sql=$sql";
			}

			return($db_dump_result_all);
	}



	// ---------------------------------------------------------------------
	// run SQL command then return $result
	// $result --> 資料數量, 如果為 0 表示沒有變動的列
	//
	// 使用方式 example:
	// $result = runSQL($sql);
	// var_dump($result);
	//
	// $debug --> 除錯資訊顯示 1 , 不顯示 0
	// $cache --> 使用 memcache = 1, 不使用 memcache = 0 --> todo
	// $cache_timeout --> 時間 timeout = 600 sec  --> todo
	// 給 MG 專用的 sql 函式
	// ---------------------------------------------------------------------
	function runSQL_betlog($sql="SET NAMES 'utf8';", $debug="0", $casinoid='remix')
		{
			// ref:http://php.net/manual/en/book.pdo.php
			// db 帳號密碼變數 global
			global $betlogpdo;

			// 建立 DB 連線
			try {
				$dbh_string = $betlogpdo['db'].':dbname='.$betlogpdo['dbname'][$casinoid].';host='.$betlogpdo['host'];
				$dbh = new PDO("$dbh_string", $betlogpdo['user'], $betlogpdo['password'] );
			} catch (PDOException $e) {
				print "DB connect Error!: " . $e->getMessage() . "<br/>";
				die();
			}

			// 切換 schema
			//$default_schema = 'SET search_path TO gpk;';
			//$sql = $default_schema.$sql;

			$sth = $dbh->prepare("$sql");
			$db_dump_result_num = NULL;
			// 如果執行成功, 就把資料以 FETCH_OBJ 方式拿出來
			if($sth->execute()) {
				// 放置紀錄數量
				$db_dump_result_num = $sth->rowCount();
			}else{
				// 請參考 postgresql error code 對應表 https://www.postgresql.org/docs/9.4/static/errcodes-appendix.html
				$debug_message = "runSQL_betlog ERROR: ["
					. "\nerrorCode:".$sth->errorCode()
					. "\ninfo:".$sth->errorInfo()[2]
					. "\n]\n";

				if($debug == 1) {
					var_dump($sql);
				}
				error_log( date("Y-m-d H:i:s").' '.$debug_message.' SQL:'.$sql);
				$db_dump_result_num = FALSE;
				echo $debug_message;
				die();
			}

			// 顯示除錯資訊
			if($debug == 1) {
				var_dump($sql);
				var_dump($db_dump_result_num);
			}

			// 回傳受影響的列
			return($db_dump_result_num);
	}
	// ---------------------------------------------------------------------
	// var_dump(runSQLall($sql));
	// -----------------------------------------------------------------------------
}else{
	// 沒有設定 STOP
	die('betlog system mode set error.');
}
// -----------------------------------------------------------------------------




// -----------------------------------------------------------------------------
// END
