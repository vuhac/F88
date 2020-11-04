#!/usr/bin/php70
<?php

/**
*  Features:	後台-- 放射線組織股利計算 -- 獨立排程執行
*  File Name:	bonus_commission_dividendreference_cmd.php
*  Author:		Ian
*  Modifier：Damocles
*  Last Modified：2019/11/21
*  Related:
*  Desc: 從 root_statisticsdailyreport 收集統計所有代理商會員的投注資料，並計算會員各層
*        級資料後, 存入 root_dividendreference 以供股利發放試算用。
*
*  Log:
*  ----------------------------------------------------------------------------
*  How to run ?
*  usage command line :
*  /usr/bin/php70 bonus_commission_dividendreference_cmd.php starttime endtime
*
*  root_bonusupdatelog目前已棄用，先不用理它
*/

$stats_showdata_count = 0;
$stats_insert_count = 0;
$stats_update_count = 0;

require_once dirname(__FILE__) ."/config.php";

// 自訂函式庫
require_once dirname(__FILE__) ."/lib.php";

// set memory limit
ini_set('memory_limit', '200M');

// 確保這個 script 執行不會因為 user abort 而中斷!!
ignore_user_abort(true);

// disable php time limit , 60*60 = 3600sec = 1hr
set_time_limit(7200);

// 程式 debug 開關 (0:OFF/1:ON)
$debug = 0;

// API 每次送出的最大數據筆數，用於進行帳號批次 LOCK 時使用
$api_limit = 1000;

// ----------------------------------
// 本程式使用的 function
// ----------------------------------

// -------------------------------------------------------------------------
// 1.1 以節點找出使用者的資料 -- from root_member , 搭配 find_parent_node 使用
// -------------------------------------------------------------------------
function find_member_node( $member_id, $tree_level, $find_parent=0 ){
    // 加上 memcached 加速,宣告使用 memcached 物件 , 連接看看. 不能連就停止.

    $memcache = new Memcached();
    $memcache->addServer('localhost', 11211) or die ("Could not connect memcache server !! ");
    // 把 query 存成一個 key in memcache
    $key = 'find_member_node_level'.$member_id;
    $key_alive_show = sha1($key);

    // 取得看看記憶體中，是否有這個 key 存在, 有的話直接抓 key
    $getfrom_memcache_result = $memcache->get($key_alive_show);

    if( !$getfrom_memcache_result ){
        $tree_level = $tree_level;

        if( $find_parent==1 ){
            // 現在時間在計算周期間，尋找上一代會員資料
            // 在尋找會員的上一代時，如果遇到原上代已停用帳號，則要再向上找到非停用的帳號做為其上一代
            $status = 0;
            $search_member_id = $member_id;
            while( $status==0 ){
                // echo '<pre>', var_dump($search_member_id), '</pre>';
                // 先把會員資料取出來，再判斷是否為停用（status = 0）
                // 如果是停用帳號，再找出此帳號的parant，一直到那帳號不是停用的帳號
                $member_sql = <<<SQL
                    SELECT id,
                           account,
                           parent_id,
                           therole,
                           status
                    FROM root_member
                    WHERE (id = {$search_member_id});
                SQL; // var_dump($member_sql);
                $member_result = runSQLall($member_sql);// echo '<pre>', var_dump($member_result), '</pre>';
                if( $member_result[0]==1 ){
                    $status = $member_result[1]->status;
                    $search_member_id = $member_result[1]->parent_id;
                }
            } // end while
        }
        else{
            //$tree_level = 1; 不論 status 為何, 都要找出來. 否則會 lost data 問題.
            $member_sql = <<<SQL
                SELECT id,
                       account,
                       parent_id,
                       therole
                FROM root_member
                WHERE (id = '{$member_id}');
            SQL; // echo '<pre>', var_dump($member_sql), '</pre>';  exit();
            $member_result = runSQLall($member_sql); // echo '<pre>', var_dump($member_result), '</pre>';  exit();
        }

        if( $member_result[0]==1 ){
            $tree = $member_result[1];
            $tree->level = $tree_level;
        }
        else{
            $logger ="ID = $member_id 資料遺失, 請聯絡客服人員處理.";
            die($logger);
        }

        // save to memcached ref:http://php.net/manual/en/memcached.set.php
        $memcached_timeout = 120;
        $memcache->set($key_alive_show, $tree, time()+$memcached_timeout) or die ("Failed to save data at the memcache server");
        //echo "Store data in the cache (data will expire in $memcached_timeout seconds)<br/>\n";
    }
    else{
        // 資料有存在記憶體中，直接取得 get from memcached
        $tree = $getfrom_memcache_result;
    }

    // echo '<pre>', var_dump($tree), '</pre>';  exit();
    return($tree);
} // end find_member_node
// -------------------------------------------------------------------------


// -------------------------------------------------------------------------
// 1.2 找出上層節點的所有會員，直到 root -- from root_member
// -------------------------------------------------------------------------
function find_parent_node( $member_id ){

    // 最大層數 100 代
    $tree_level_max = 100;

    $tree_level = 0;
    // treemap 為正常的組織階層
    $treemap[$member_id][$tree_level] = find_member_node( $member_id, $tree_level, 0 ); // var_dump( $treemap ); exit();

    // $treemap_performance 唯有達標的組織階層
    // var_dump( $tree_level<=$tree_level_max ); exit();
    while( $tree_level<=$tree_level_max ){
        $m_id = $treemap[$member_id][$tree_level]->parent_id;
        $m_account = $treemap[$member_id][$tree_level]->account;
        $tree_level = $tree_level+1;

        // 如果到了 root 的話跳離迴圈。表示已經到了最上層的會員了。
        if($m_id == 1){ // $treemap內不會有root
            break;
        }
        else{
            // var_dump( $member_id, $tree_level ); exit();
            // var_dump( $m_id, $tree_level ); exit();
            $treemap[$member_id][$tree_level] = find_member_node($m_id, $tree_level, 1);
            // var_dump( $treemap ); exit();
        }
    } // end while

    return($treemap);
} // end find_parent_node
// -------------------------------------------------------------------------


// -------------------------------------------------------------------------
// 計算該層下線代理商的總投注量 -- from root_statisticsdailyreport
// 尋找指定帳號的每個下線代理商，並且統計指定日期區間內的總投注量
// -------------------------------------------------------------------------
function cal_agent_all_bets( $member_id, $current_datepicker_start, $current_datepicker, $debug=0 ){
    $current_datepicker_gmt = gmdate('Y-m-d H:i:s.u',strtotime($current_datepicker.'23:59:59 -04')+8*3600).'+08:00';

    // 會員的下線代理商有多少人
    $agent_sql = <<<SQL
        SELECT account
        FROM root_member
        WHERE (parent_id = '{$member_id}') AND
              (therole = 'A') AND
              (enrollmentdate <= '{$current_datepicker_gmt}');
    SQL;
    $agent_r = runSQLall($agent_sql);

    if($debug == 1) {
        echo '<pre>', var_dump($agent_sql), '</pre>';
        echo '<pre>', var_dump($agent_r), '</pre>';
    }

    $agent_all_bets = 0;
    // 判斷 root_member count 數量大於 1
    if( $agent_r[0]>=1 ) {
        // 以會員為主要 key 依序列出每個會員的貢獻金額
        for( $i=1; $i<=$agent_r[0]; $i++ ){
            $dailyreport_stat_sql = <<<SQL
                SELECT  member_account,
                        sum(all_bets) as sum_all_bets ,
                        sum(all_profitlost) as sum_all_profitlost,
                        sum(all_count) as sum_all_count
                FROM root_statisticsdailyreport
                WHERE member_account = '{$agent_r[$i]->account}'
                    AND dailydate >= '{$current_datepicker_start}'
                    AND dailydate <= '{$current_datepicker}'
                GROUP BY member_account;
            SQL;
            $dailyreport_stat_result = runSQLall($dailyreport_stat_sql);
            if( $debug==1 ) {
                echo '<pre>', var_dump($dailyreport_stat_sql), '</pre>';
                echo '<pre>', var_dump($dailyreport_stat_result), '</pre>';
            }
            if($dailyreport_stat_result[0] >= 1){
                $agent_all_bets = $agent_all_bets + $dailyreport_stat_result[1]->sum_all_bets;
            }
        } // end for
    }
    if($debug == 1) {
        echo '<pre>', var_dump($agent_all_bets), '</pre>';
    }
    return($agent_all_bets);
} // end cal_agent_all_bets
// -------------------------------------------------------------------------

// get example: ?current_datepicker=2017-02-03
// ref: http://php.net/manual/en/function.checkdate.php
function validateDate( $date, $format='Y-m-d H:i:s' ){
    $d = DateTime::createFromFormat( $format, $date );
    return $d && $d->format($format) == $date;
} // end validateDate
// -------------------------------------------------------------------------

// ----------------------------------
// 本程式使用的 function END
// ----------------------------------


// -----------------------------------------------------------------
// 安全控管, 如果是 web 執行就立即中斷, 只允許 command 執行此程式。
// -----------------------------------------------------------------
// 如果 HTTP_USER_AGENT OR SERVER_NAME 存在, 表示是直接透過網頁呼叫程式, 拒絕這樣的呼叫
if( isset($_SERVER['HTTP_USER_AGENT']) || isset($_SERVER['SERVER_NAME']) ){
    die('禁止使用網頁呼叫，來源錯誤，請使用命令列執行。');
}


// -----------------------------------------------------------------
// 命令列參數解析
// -----------------------------------------------------------------
// 取得今天的日期，轉換為美東的時間 date
$date = date_create(date('Y-m-d H:i:sP'), timezone_open('America/St_Thomas'));
date_timezone_set($date, timezone_open('America/St_Thomas'));
$current_date = date_format($date, 'Y-m-d');
// var_dump( $date, $current_date ); exit();

// 指令、開始時間、結束時間
if( isset($argv[1]) && ($argv[1]=='test' || $argv[1]=='run') ){
    // 開始時間必須早於現在當前時間，且時間格式需為Y-m-d
    if( isset($argv[2]) && validateDate($argv[2], 'Y-m-d') ){
            if( $argv[2]<=$current_date ){
                $current_datepicker_start = $argv[2];
            }
            else{
                // command 動作 時間
                echo "command [test|run] startdate(YYYY-MM-DD) enddate(YYYY-MM-DD) dividendreference_setting_id \n";
                die('startdate(YYYY-MM-DD) error');
            }
    }
    else{
        // command 動作 時間
        echo "command [test|run] startdate(YYYY-MM-DD) enddate(YYYY-MM-DD) dividendreference_setting_id \n";
        die('no startdate(YYYY-MM-DD)');
    }

    // 結束時間必須早於現在當前時間，且時間格式需為Y-m-d
    if( isset($argv[3]) && validateDate($argv[3], 'Y-m-d') ){
        if( $argv[3]<=$current_date ){
            $current_datepicker = $argv[3];
        }
        else{
            // command 動作 時間
            echo "command [test|run] startdate(YYYY-MM-DD) enddate(YYYY-MM-DD) dividendreference_setting_id \n";
            die('enddate(YYYY-MM-DD) error');
        }
    }
    else{
        // command 動作 開始時間 結束時間 設定資料ID
        echo "command [test|run] startdate(YYYY-MM-DD) enddate(YYYY-MM-DD) dividendreference_setting_id \n";
        die('no enddate(YYYY-MM-DD)');
    }

    $argv_check = $argv[1];
    $current_datepicker_gmt = gmdate('Y-m-d H:i:s.u',strtotime($current_datepicker.'23:59:59 -04')+8*3600).'+08:00';
}
else{
    // command 動作 時間
    echo "command [test|run] startdate(YYYY-MM-DD) enddate(YYYY-MM-DD) dividendreference_setting_id \n";
    die('no test and run');
}

// 放射線組織獎金計算-年度股利分級報表-設定 (root_dividendreference_setting id)
if( isset($argv[4]) ){
	$dividendreference_setting = filter_var($argv[4],FILTER_VALIDATE_INT);
}
else{
    // command 動作 時間
    echo "command [test|run] startdate(YYYY-MM-DD) enddate(YYYY-MM-DD) dividendreference_setting_id \n";
    die('no dividendreference_setting_id');
}

if( isset($argv[5]) && ($argv[5]=='web') ){
	$web_check = 1;
    $output_html = <<<HTML
        <h5 align="center">會員資料更新中，請勿關閉本視窗...
            <img src="ui/loading.gif" />
        </h5>
        <script>
            setTimeout(function(){
                location.reload()
            },1000);
        </script>
    HTML;
	$file_key = sha1('dividend'.$dividendreference_setting);
	$reload_file = dirname(__FILE__).'/tmp_dl/dividend_'.$file_key.'.tmp';
    file_put_contents($reload_file, $output_html);
}
else if( isset($argv[5]) && ($argv[5]=='sql') ){
	if( isset($argv[6]) && filter_var($argv[6], FILTER_VALIDATE_INT) ){
		$web_check = 2;
		$updatelog_id = filter_var($argv[6], FILTER_VALIDATE_INT);
        $updatelog_sql = <<<SQL
            SELECT *
            FROM root_bonusupdatelog
            WHERE (id ='{$updatelog_id}');
        SQL;
		$updatelog_result = runSQL($updatelog_sql);
		if($updatelog_result == 0){
			die('No root_bonusupdatelog ID');
		}
    }
    else{
		die('No root_bonusupdatelog ID');
	}
}
else{
	$web_check = 0;
}

$logger ='';


// ----------------------------------------------------------------------------
// MAIN
// ----------------------------------------------------------------------------
// ----------------------------------------------------------------------------
// round 1. 新增或更新會員資料
// ----------------------------------------------------------------------------

if( $web_check==1 ){ // web
    $output_html = <<<HTML
        <p align="center">round 1. 新增或更新會員資料 - 更新中...
            <img src="ui/loading.gif" />
        </p>
        <script>
            setTimeout(function(){
                location.reload()
            },1000);
        </script>
    HTML;
	file_put_contents($reload_file, $output_html);
}
else if( $web_check==2 ){ // sql
	$updatlog_note = 'round 1. 新增或更新會員資料 - 更新中';
    $updatelog_sql = <<<SQL
        UPDATE root_bonusupdatelog
        SET bonus_status = '0',
            note = '{$updatlog_note}'
        WHERE (id = '{$updatelog_id}');
    SQL;
	if( $argv_check=='test' ){
		echo $updatelog_sql;
    }
    else if( $argv_check=='run' ){
		$updatelog_result = runSQLall($updatelog_sql);
	}
}
else{
	echo "round 1. 新增或更新會員資料 - 開始\n";
}

// -------------------------------------
// 列出所有的會員資料及人數 SQL
// -------------------------------------
// 算 root_member 人數
$userlist_sql = <<<SQL
    SELECT *
    FROM root_member
    WHERE (therole = 'A') AND
          (
              (enrollmentdate <= '{$current_datepicker_gmt}') OR
              (enrollmentdate IS NULL)
          )
    ORDER BY id ASC;
SQL;
$userlist_count = runSQL($userlist_sql); // var_dump($userlist_count);  exit(); // result：733

// 取出 root_member 資料
$userlist_sql = <<<SQL
    WITH memberlists AS (
        SELECT *, to_char((enrollmentdate AT TIME ZONE 'AST'), 'YYYY-MM-DD') AS enrollmentdate_edt
        FROM root_member
        WHERE therole = 'A'
    )
    SELECT *
    FROM memberlists
    WHERE (enrollmentdate_edt <= '{$current_datepicker}') OR
          (enrollmentdate_edt IS NULL)
    ORDER BY id ASC ;
SQL;
$userlist = runSQLall($userlist_sql); // var_dump($userlist);  exit(); // result：658


$percentage_current = 0; // 顯示紀錄進度(%)

// 判斷 root_member count 數量大於 1
if( $userlist[0]>=1 ){

    // 以會員為主要 key 依序列出每個會員的貢獻金額
    for( $i=1; $i<=$userlist[0]; $i++ ){
        // 查詢會員是否已在 root_dividendreference 中有資料，如沒有則建立，如有則更新
        $dividendreference_check_sql = <<<SQL
            SELECT *
            FROM root_dividendreference
            WHERE (memberid = '{$userlist[$i]->id}') AND
                  (dividendreference_setting_id = '{$dividendreference_setting}');
        SQL;
        $dividendreference_check_result = runSQL($dividendreference_check_sql); // var_dump($dividendreference_check_result);  exit();

		if( $dividendreference_check_result==0 ){
            // 從每日報表, 統計整理出需要的資料
            $dailyreport_stat_sql = <<<SQL
                SELECT member_account,
                       sum(all_bets) as sum_all_bets,             /* [收入]娛樂城總投注 */
                       sum(all_profitlost) as sum_all_profitlost, /* 娛樂城總損益(娛樂城全部加總) */
                       sum(all_count) as sum_all_count            /* [娛樂城]注單量[總] */
                FROM root_statisticsdailyreport
                WHERE (member_account = '{$userlist[$i]->account}') AND
                      (dailydate >= '{$current_datepicker_start}') AND
                      (dailydate <= '{$current_datepicker}')
                GROUP BY member_account;
            SQL;
            $dailyreport_stat_result = runSQLall($dailyreport_stat_sql); // var_dump($dailyreport_stat_result);  exit();

            $b = [
                'member_id' => $userlist[$i]->id,
                'member_account' => $userlist[$i]->account,
                'member_therole' => $userlist[$i]->therole,
                'member_parent_id' => $userlist[$i]->parent_id
            ]; // var_dump( $b ); exit();

            // 會員的所在層數
            unset($member_tree);
            $member_tree = find_parent_node($userlist[$i]->id); // var_dump($member_tree); exit();
            $b['member_level'] = count($member_tree[$userlist[$i]->id])-1; // var_dump( $b ); exit();

            // 上 1 ~ 4 層
            for( $j=1; $j<5; $j++ ){
                if( isset($member_tree[$userlist[$i]->id][$j]->account) ){
                    $b["member_level_{$j}"] = $member_tree[$userlist[$i]->id][$j]->account;
                }
                else{
                    $b["member_level_{$j}"] = 'N/A';
                }
            }// end for

            // 會員的下線代理商有多少人
            $agent_sql = <<<SQL
                SELECT count(id) as agent_count
                FROM root_member
                WHERE (parent_id = '{$userlist[$i]->id}') AND
                      (therole = 'A') AND
                      (enrollmentdate <= '{$current_datepicker_gmt}');
            SQL;

            $agent_r = runSQLall($agent_sql);
            $b['member_1_agent_count'] = $agent_r[1]->agent_count;

            // 會員的下線代理商區間累計投注量
            $b['member_1_agent_all_bets'] = cal_agent_all_bets($userlist[$i]->id,$current_datepicker_start,$current_datepicker);

            // 會員的下線會員有多少人
            $membercount_sql = <<<SQL
                SELECT count(id) as member_count
                FROM root_member
                WHERE (parent_id = '{$userlist[$i]->id}') AND
                      (therole = 'M') AND
                      (enrollmentdate <= '{$current_datepicker_gmt}');
            SQL;

            $member_r = runSQLall($membercount_sql);
            $b['member_1_member_count'] = $member_r[1]->member_count;

            if( $dailyreport_stat_result[0] == 1 ){
                // 本年度投注量
                $b['sum_all_bets'] = $dailyreport_stat_result[1]->sum_all_bets;

                // 本年度盈虧
                $b['sum_all_profitlost'] = $dailyreport_stat_result[1]->sum_all_profitlost;

                // 本年度注單量
                $b['sum_all_count'] = $dailyreport_stat_result[1]->sum_all_count;
            }
            else{
                // 本年度投注量
                $b['sum_all_bets'] = '0';

                // 本年度盈虧
                $b['sum_all_profitlost'] = '0';

                // 本年度注單量
                $b['sum_all_count'] = '0';
            }

            // 顯示的表格資料內容
            $dividendreference_data_sql = <<<SQL
                INSERT INTO "root_dividendreference"(
                    "dividendreference_setting_id",
                    "memberid",
                    "member_account",
                    "member_therole",
                    "member_parentid",
                    "member_level",
                    "member_level1",
                    "member_level2",
                    "member_level3",
                    "member_level4",
                    "member_l1_agentcount",
                    "member_l1_agentsum_allbets",
                    "member_l1_membercount",
                    "member_sum_all_bets",
                    "member_sum_all_profitlost",
                    "member_sum_all_count",
                    "member_dividend_level",
                    "member_dividend_assigned",
                    "updatetime",
                    "note"
                ) VALUES (
                    '{$dividendreference_setting}',
                    '{$b["member_id"]}',
                    '{$b["member_account"]}',
                    '{$b["member_therole"]}',
                    '{$b["member_parent_id"]}',
                    '{$b["member_level"]}',
                    '{$b["member_level_1"]}',
                    '{$b["member_level_2"]}',
                    '{$b["member_level_3"]}',
                    '{$b["member_level_4"]}',
                    '{$b["member_1_agent_count"]}',
                    '{$b["member_1_agent_all_bets"]}',
                    '{$b["member_1_member_count"]}',
                    '{$b["sum_all_bets"]}',
                    '{$b["sum_all_profitlost"]}',
                    '{$b["sum_all_count"]}',
                    'N/A',
                    0,
                    now(),
                    ''
                );
            SQL;

            if($debug == 1) {
                var_dump($dailyreport_stat_sql);
                var_dump($dailyreport_stat_result);
                var_dump($agent_sql);
                var_dump($membercount_sql);
                echo $dividendreference_data_sql;
            }
            $stats_insert_count++;

            // ------- bonus update log ------------------------
            // 顯示目前的處理紀錄進度，及花費的時間。 換算進度 %
            $percentage_html     = round(($i/$userlist[0]), 2)*100;
            $process_record_html = "$i/$userlist[0]";
            $process_times_html  = round((microtime(true) - $program_start_time), 3);
            $counting_r = ($percentage_html % 10);

            if( ($web_check == 1) && ($counting_r == 0) ){
                $output_sub_html  = $output_html.'<p align="center">'.$percentage_html.'%</p>';
                file_put_contents($reload_file,$output_sub_html);
            }
            else if( ($web_check == 2) && ($counting_r == 0) ){
                $updatelog_sql = <<<SQL
                    UPDATE root_bonusupdatelog SET
                        bonus_status = '{$percentage_html}',
                        note = '{$updatlog_note}'
                    WHERE id = '{$updatelog_id}';
                SQL;
                if($argv_check == 'test'){
                    echo $updatelog_sql;
                }
                elseif($argv_check == 'run'){
                    $updatelog_result = runSQLall($updatelog_sql);
                }
            }
            else if( $web_check == 0 ){
                if( $percentage_html != $percentage_current ){
                    if( $counting_r == 0 ){
                        echo "\n目前處理 $current_datepicker 紀錄: $process_record_html ,執行進度: $percentage_html% ,花費時間: ".$process_times_html."秒\n";
                    }
                    else{
                        echo $percentage_html.'% ';
                    }
                    $percentage_current = $percentage_html;
                }
            }
            // -------------------------------------------------

        }
        else{
            // 從每日報表, 統計整理出需要的資料
            $dailyreport_stat_sql = <<<SQL
                SELECT member_account,
                       sum(all_bets) as sum_all_bets,
                       sum(all_profitlost) as sum_all_profitlost,
                       sum(all_count) as sum_all_count
                FROM root_statisticsdailyreport
                WHERE member_account = '{$userlist[$i]->account}'
                GROUP BY member_account;
            SQL;
            $dailyreport_stat_result = runSQLall($dailyreport_stat_sql);
            $b['member_id'] = $userlist[$i]->id;

            // 會員的下線代理商有多少人
            $agent_sql = <<<SQL
                SELECT count(id) as agent_count
                FROM root_member
                WHERE parent_id = '{$userlist[$i]->id}'
                    AND therole = 'A'
                    AND enrollmentdate <= '{$current_datepicker_gmt}';
            SQL;
            $agent_r = runSQLall($agent_sql);
            $b['member_1_agent_count'] = $agent_r[1]->agent_count;

            // 會員的下線代理商區間累計投注量
            $b['member_1_agent_all_bets'] = cal_agent_all_bets( $userlist[$i]->id, $current_datepicker_start, $current_datepicker );

            // 會員的下線會員有多少人
            $membercount_sql = <<<SQL
                SELECT count(id) as member_count
                FROM root_member
                WHERE parent_id = '{$userlist[$i]->id}'
                    AND therole = 'M'
                    AND enrollmentdate <= '{$current_datepicker_gmt}';
            SQL;
            $member_r = runSQLall($membercount_sql);
            $b['member_1_member_count'] = $member_r[1]->member_count;

            if( $dailyreport_stat_result[0]==1 ){
                // 本年度投注量
                $b['sum_all_bets'] = $dailyreport_stat_result[1]->sum_all_bets;
                // 本年度盈虧
                $b['sum_all_profitlost'] = $dailyreport_stat_result[1]->sum_all_profitlost;
                // 本年度注單量
                $b['sum_all_count'] = $dailyreport_stat_result[1]->sum_all_count;
            }
            else{
                // 本年度投注量
                $b['sum_all_bets'] = '0';
                // 本年度盈虧
                $b['sum_all_profitlost'] = '0';
                // 本年度注單量
                $b['sum_all_count'] = '0';
            }

            // 顯示的表格資料內容
            $dividendreference_data_sql = <<<SQL
                UPDATE "root_dividendreference" SET "updatetime" = {NOW()},
                    "member_l1_agentcount" = '{$b['member_1_agent_count']}',
                    "member_l1_agentsum_allbets" = '{$b['member_1_agent_all_bets']}',
                    "member_l1_membercount" = '{$b['member_1_member_count']}',
                    "member_sum_all_bets" = '{$b['sum_all_bets']}',
                    "member_sum_all_profitlost" = '{$b['sum_all_profitlost']}',
                    "member_sum_all_count" = '{$b['sum_all_count']}',
                    "member_dividend_level" = 'N/A',
                    "member_dividend_assigned" = '0',
                    "note" = ''
                WHERE "memberid" = '{$b['member_id']}' AND dividendreference_setting_id = '{$dividendreference_setting}';
            SQL;

            if($debug == 1) {
                var_dump($dailyreport_stat_sql);
                var_dump($dailyreport_stat_result);
                var_dump($agent_sql);
                var_dump($membercount_sql);
                echo $dividendreference_data_sql;
            }
            $stats_update_count++;

            // ------- bonus update log ------------------------
            // 顯示目前的處理紀錄進度，及花費的時間。 換算進度 %
            $percentage_html = round( ($i/$userlist[0]), 2 )*100;
            $process_record_html = "$i/$userlist[0]";
            $process_times_html  = round((microtime(true) - $program_start_time),3);
            $counting_r = $percentage_html%10;

            if( ($web_check==1) && ($counting_r==0) ){
                $output_sub_html  = <<<HTML
                    {$output_html}<p align="center">{$percentage_html}%</p>
                HTML;
                file_put_contents($reload_file,$output_sub_html);
            }
            else if( ($web_check==2) && ($counting_r==0) ){
                $updatelog_sql = <<<SQL
                    UPDATE root_bonusupdatelog SET
                        bonus_status = '{$percentage_html}',
                        note = '{$updatlog_note}'
                    WHERE id = '{$updatelog_id}';
                SQL;
                if( $argv_check=='test' ){
                    echo $updatelog_sql;
                }
                else if( $argv_check=='run' ){
                    $updatelog_result = runSQLall($updatelog_sql);
                }
            }
            else if( $web_check==0 ){
                if( $percentage_html!=$percentage_current ){
                    if( $counting_r==0 ){
                        echo "\n目前處理 $current_datepicker 紀錄: $process_record_html ,執行進度: $percentage_html% ,花費時間: ".$process_times_html."秒\n";
                    }
                    else{
                        echo $percentage_html.'% ';
                    }
                    $percentage_current = $percentage_html;
                }
            }
            // -------------------------------------------------
        }

		if( $argv_check=='test' ){
			echo $dividendreference_data_sql;
			$dividendreference_data_result = 1;
			//print_r($update_sql);
        }
        else if( $argv_check=='run' ){
			$dividendreference_data_result = runSQL($dividendreference_data_sql);
		}
		//var_dump($bns_update_result);
	} // end for
}


// 更新 更新時間 到 root_dividendreference_setting 上
$update_updatetime_to_dividendreference_setting_sql = 'UPDATE "root_dividendreference_setting" SET "updatetime" = NOW() WHERE id = \''.$dividendreference_setting.'\';';
$update_updatetime_to_dividendreference_setting_result = runSQL($update_updatetime_to_dividendreference_setting_sql);

$run_report_result = "
  統計顯示的資料 =  $stats_showdata_count ,\n
  統計此時間區間插入(Insert)的會員資料 =  $stats_insert_count ,\n
  統計此時間區間更新(Update)的會員資料 =  $stats_update_count";

// 算累積花費時間
$program_end_time =  microtime(true);
$program_time = $program_end_time-$program_start_time;
$logger = $run_report_result."\n累積花費時間: ".$program_time ." \n";
if($web_check == 1){

	$dellogfile_js = '
	<script src="in/jquery/jquery.min.js"></script>
	<script type="text/javascript" language="javascript" class="init">
	function dellogfile(){
		$.get("bonus_commission_dividendreference_action.php?a=dividendbonus_del&k='.$file_key.'",
		function(result){
			window.close();
		});
	}
	</script>';

	$logger_html = nl2br($logger).'<br><br><p align="center"><button type="button" onclick="dellogfile();">關閉視窗</button></p><script>alert(\'已完成資料更新！\');</script>'.$dellogfile_js;
	file_put_contents($reload_file,$logger_html);
}
else if($web_check == 2){
	$updatlog_note = nl2br($logger);
    $updatelog_sql = <<<SQL
        UPDATE root_bonusupdatelog SET
            bonus_status = '1000',
            note = '{$updatlog_note}'
        WHERE id = '{$updatelog_id}';
    SQL;
	if( $argv_check == 'test' ){
		echo $updatelog_sql;
    }
    else if( $argv_check == 'run' ){
		$updatelog_result = runSQLall($updatelog_sql);
	}
}
else{
	echo $logger;
}


// --------------------------------------------
// MAIN END
// --------------------------------------------
?>
