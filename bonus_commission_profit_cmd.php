#!/usr/bin/php70
<?php
// ----------------------------------------------------------------------------
// Features:	後台-- 放射線組織加盟金計算 -- 營運利潤獎金 -- 獨立排程執行
// File Name:	bonus_commission_profit_cmd.php
// Author:		Bakley Fix By Ian
// Modifier：Damocles
// Related:
// DB table: root_statisticsdailyreport
// DB table: root_statisticsbonusprofit  營運利潤獎金
// Desc: 將營運日報的資料，整理成為會員獎金分紅的報表，並且輸出成為資料表存放。
// Log:
//
// ----------------------------------------------------------------------------
// How to run ?
// usage command line : /usr/bin/php70 bonus_commission_profit_cmd.php run/test time
// ----------------------------------------------------------------------------

// ----------------------------------------------------------------------------
// session_start();

// 2019/09/18 Damocle新增
$debug = true;// false // 開發階段關閉 "限制命令提示操作"
if( $debug ){
    $argv = [
        1=>'test',
        '2019-09-01',
        'web'
    ];
}

$stats_showdata_count = 0;
$stats_insert_count = 0;
$stats_update_count = 0;
$stats_bonusamount_count = 0;

// ----------------------------------------------------------------------------

require_once dirname(__FILE__) ."/config.php";
// 自訂函式庫
require_once dirname(__FILE__) ."/lib.php";
// betlog 專用的 DB lib
require_once dirname(__FILE__) ."/config_betlog.php";

// set memory limit
ini_set('memory_limit', '200M');

// 確保這個 script 執行不會因為 user abort 而中斷!!
ignore_user_abort(true);

// disable php time limit , 60*60 = 3600sec = 1hr
set_time_limit(7200);

// API 每次送出的最大數據筆數
// 用於進行帳號批次 LOCK 時使用
$api_limit = 1000;

// ----------------------------------
// 本程式使用的 function
// ----------------------------------

// -------------------------------------------------------------------------
// 尋找符合業績達成的上層, 共 n 代. 直到最上層 root 會員。
// 再以計算出來的代數 account 判斷，哪些代數符合達成業績標準的會員。
// -------------------------------------------------------------------------

// -------------------------------------------------------------------------
// 1.1 以節點找出使用者的資料 -- from root_member or root_statisticsbonusprofit
// 取得指定啟用會員的投注總額與投注量
// -------------------------------------------------------------------------
function find_member_node( $member_id, $tree_level, $current_datepicker_start, $current_datepicker_end, $find_parent ){
    global $config;
    global $timeover;

    // 加上 memcached 加速,宣告使用 memcached 物件 , 連接看看. 不能連就停止.
    $memcache = new Memcached();
    $memcache->addServer('localhost', 11211) or die ("Could not connect memcache server !! ");
    // 把 query 存成一個 key in memcache
    $key = $member_id.$current_datepicker_start.$current_datepicker_end;
    $key_alive_show = sha1($key);

    // 取得看看記憶體中，是否有這個 key 存在, 有的話直接抓 key
    $getfrom_memcache_result = $memcache->get($key_alive_show);

      // 沒有指定資料在記憶體中
    if( !$getfrom_memcache_result ){
        if( ($find_parent == 1) && ($timeover == 0) ){
            // 現在時間在計算周期間，尋找上一代會員資料
            // 在尋找會員的上一代時，如果遇到原上代已停用帳號，則要再向上找到非停用的帳號做為其上一代
            $status = 0;
            $search_member_id = $member_id;
            while($status == 0){ // 找到不是停用帳號的時候會跳出while
                //echo $search_member_id."\n";
                // 先把會員資料取出來，再判斷是否為停用（status = 0）
                // 如果是停用帳號，再找出此帳號的parant，一直到那帳號不是停用的帳號
                $member_sql = <<<SQL
                    SELECT id,
                           account,
                           parent_id,
                           therole,
                           status
                FROM root_member
                WHERE id = '{$search_member_id}';
                SQL; //var_dump($member_sql);
                $member_result = runSQLall($member_sql);
                if( $member_result[0] == 1 ){
                    $status = $member_result[1]->status;
                    $search_member_id = $member_result[1]->parent_id;
                }
            } // end while
        }
        else if( ($find_parent == 1) && ($timeover == 1) ){
            // 現在時間已超過計算周期的最後一天時，尋找上一代會員資料
            //$tree_level = 1; 不論 status 為何, 都要找出來. 否則會 lost data 問題.
            $member_sql = <<<SQL
                SELECT member_id as id,
                       member_account as account,
                       member_parent_id as parent_id,
                       member_therole as therole
                FROM root_statisticsbonusprofit
                WHERE member_id = '{$member_id}' AND
                      dailydate_start = '{$current_datepicker_start}' AND
                      dailydate_end = '{$current_datepicker_end}';
            SQL; // var_dump($member_sql);
            $member_result = runSQLall($member_sql);
        }
        else{
            //$tree_level = 1; 不論 status 為何, 都要找出來. 否則會 lost data 問題.
            $member_sql = <<<SQL
                SELECT id,
                       account,
                       parent_id,
                       therole
                FROM root_member
                WHERE id = '{$member_id}';
            SQL; // var_dump($member_sql);
            $member_result = runSQLall($member_sql);
        }
        // echo '<pre>', var_dump($member_result), '</pre>';

        if( $member_result[0] == 1 ){
            $tree = $member_result[1];
            $tree->level = $tree_level;
            // 統計區間的數值總和, 所以日期需要 >= <=
            if( isset($config['website_type']) && ($config['website_type'] == 'casino') ){
                // 判断网站类型，如為娱乐城，总投注以所有娱乐城的投注量计算
                $psql = <<<SQL
                    SELECT sum(all_bets) as sum_all_bets,
                           count(all_bets) as count_all_bets
                    FROM root_statisticsdailyreport
                    WHERE dailydate >= '{$current_datepicker_start}' AND
                          dailydate <= '{$current_datepicker_end}' AND
                          member_id = '{$member_id}';
                SQL;
            }
            else if( isset($config['website_type']) && ($config['website_type'] == 'ecshop') ){
                // 判断网站类型，如為商城，总投注以ECSHOP的下单量计算
                $psql = <<<SQL
                    SELECT sum(ec_sales) as sum_all_bets,
                           count(ec_sales) as count_all_bets
                    FROM root_statisticsdailyreport
                    WHERE dailydate >= '{$current_datepicker_start}' AND
                          dailydate <= '{$current_datepicker_end}' AND
                          member_id = '{$member_id}';
                SQL;
            }
            else{
                die('系统参数错误！');
            }
            $psql_result = runSQLall($psql);

            if($psql_result[0] == 1) {
                // 將總和即時間資訊寫入節點
                $tree->sum_date_start = $current_datepicker_start;
                $tree->sum_date_end = $current_datepicker_end;
                $tree->sum_all_bets = $psql_result[1]->sum_all_bets;
                $tree->count_all_bets = $psql_result[1]->count_all_bets;
            }
            else{
                $logger = "日報表資料 ID = $member_id 會員資料遺失, 請聯絡客服人員處理.";
                die($logger);
            }
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

    // '<pre>', var_dump($tree), '</pre>';
    return($tree);
} // end find_member_node

// -------------------------------------------------------------------------
// 1.2 找出上層節點的所有會員，直到 root -- from root_member
// -------------------------------------------------------------------------
function find_parent_node( $member_id, $current_datepicker_start, $current_datepicker_end ){

    // 最大層數
    $tree_level_max = 120;

    $tree_level = 0;
    // treemap 為正常的組織階層
    $treemap[$member_id][$tree_level] = find_member_node($member_id, $tree_level, $current_datepicker_start, $current_datepicker_end, 0);

    // 超出最大層數就會跳出while
    while( $tree_level <= $tree_level_max ){
        $m_id = $treemap[$member_id][$tree_level]->parent_id;
        $m_account = $treemap[$member_id][$tree_level]->account;
        $tree_level = ($tree_level + 1);
        // 如果到了 root 的話跳離迴圈。表示已經到了最上層的會員了。
        if( $m_account == 'root' ){
            break;
        }
        else{
            $treemap[$member_id][$tree_level] = find_member_node($m_id, $tree_level, $current_datepicker_start, $current_datepicker_end,1);
        }
    } // end while

    // '<pre>', var_dump($treemap), '</pre>';
    return($treemap);
} // end find_parent_node
// -------------------------------------------------------------------------
// END treemap
// -------------------------------------------------------------------------

// ----------------------------------------------------
// Usages: bonus_commission_profit_data($userlist, $current_datepicker_start, $current_datepicker_end)
// 計算出會員的貢獻營利額度及達標的會員
// $userlist 陣列 member data
// $current_datepicker_start  開始日期
// $current_datepicker_end  結束日期
// ----------------------------------------------------
function bonus_commission_profit_data( $userlist, $current_datepicker_start, $current_datepicker_end ){
    global $rule;
    global $config;

    // var_dump($userlist);
    // -------------------------------------------
    // 會員的資料資訊
    // -------------------------------------------
    $b['member_id']              = $userlist->id;
    $b['member_account']         = $userlist->account;
    $b['member_parent_id']       = $userlist->parent_id;
    $b['member_therole']         = $userlist->therole;

    $b['dailydate_start']        = $current_datepicker_start;
    $b['dailydate_end']          = $current_datepicker_end;

    // 更新時把餘額清空，避免使用者誤判已經處理。(第二次更新資料庫時的欄位, 需要清空)
    // 會員個人的分潤合計
    $b['member_profitamount']          = NULL;

    // 會員個人的分潤付款(負數帳號為下次扣除)
    $b['member_profitamount_paid']     = NULL;

    // 會員個人的分潤付款時間
    $b['member_profitamount_paidtime'] = NULL;

    // 由多少 account 紀錄累積而來的
    $b['member_profitamount_count']    = NULL;

    // 會員個人上月留抵負債(下月計算時扣除)
    $b['lasttime_stayindebt']          = NULL;

    // 備註 -- 第一次 insert 生成後就不可以刪除或是清空
    $b['notes']                        = NULL;

    // -------------------------------------------
    // 找出會員所在的 tree 直到 root
    // -------------------------------------------
    $tree = find_parent_node( $userlist->id, $current_datepicker_start, $current_datepicker_end ); // echo '<pre>', var_dump($tree), '</pre>'; die('測試斷點');

    // -------------------------------------------
    // 將原始的 $tree 轉換為--> 已經達標的 $ptree
    // -------------------------------------------
    $skip_agent_tree_list = null;
    $skip_agent_tree = null;
    $level = 0;
    $plevel = 0; // 可以分紅的會員數量
    $ptree[$userlist->id][$plevel] = $tree[$userlist->id][$level]; // 可以分紅的會員資料
    $plevel++;

    // 此 node member 有幾階層 , array 數量 -1 , 因為 0 開始
    $member_tree_level_number = count($tree[$userlist->id])-1;
    $b['member_level'] = $member_tree_level_number;
    for( $level=1; $level<=$member_tree_level_number; $level++ ){
        // root 就跳出了, 表示到頂了!!
        if( $tree[$userlist->id][$level]->account == 'root' ){
            break;
        }
        else{
            // 當 sum_all_bets 條件符合月結門檻 $rule['amountperformance_month'] 時，才可以列為分紅代
            if( $tree[$userlist->id][$level]->sum_all_bets >= $rule['amountperformance_month'] ){
                $ptree[$userlist->id][$plevel] = $tree[$userlist->id][$level];
                $plevel++;
            }
            else{
                // 沒有達標 被跳過的代理商，以及再哪一個會員，那一代。
                // 在哪一個會員
                //var_dump($userlist[$i]->id);
                //var_dump($userlist[$i]->account);
                // 在哪一個會員的那一代
                //var_dump($level);
                //var_dump($tree[$userlist[$i]->id][$level]);
                // 在哪一個會員的那一代跳過了那個代理商
                //var_dump($tree[$userlist[$i]->id][$level]->id);
                //var_dump($tree[$userlist[$i]->id][$level]->account);
                // 完整的樹狀資訊
                //var_dump($tree[$userlist[$i]->id]);
                // 將被跳過得代理商資訊存下來，以作為行銷的回饋。
                $skip_agent_tree[$userlist->id][$tree[$userlist->id][$level]->id]['agentid'] = $tree[$userlist->id][$level]->id;
                $skip_agent_tree[$userlist->id][$tree[$userlist->id][$level]->id]['agentaccount'] = $tree[$userlist->id][$level]->account;
                $skip_agent_tree[$userlist->id][$tree[$userlist->id][$level]->id]['memberid'] = $userlist->id;
                $skip_agent_tree[$userlist->id][$tree[$userlist->id][$level]->id]['memberaccount'] = $userlist->account;
                $skip_agent_tree[$userlist->id][$tree[$userlist->id][$level]->id]['level'] = $level;
                $skip_agent_tree[$userlist->id][$tree[$userlist->id][$level]->id]['sum_all_bets'] = $tree[$userlist->id][$level]->sum_all_bets;
                // 被跳過的 agent , 簡易描述資料文字，預計 save in DB
                // 代理商:會員:層級:代理商投注量
                if( $level == 1 ) {
                    $skip_agent_tree_list = $userlist->account.':'.$tree[$userlist->id][$level]->account.':'.$level.':'.$tree[$userlist->id][$level]->sum_all_bets;
                }
                else{
                    $skip_agent_tree_list = $skip_agent_tree_list.','.$userlist->account.':'.$tree[$userlist->id][$level]->account.':'.$level.':'.$tree[$userlist->id][$level]->sum_all_bets;
                }
            }
        }
    } // end for

    // -------------------------------------------
    // 被跳過的代理商, 那個代理商在哪一個會員的那一代,金額是
    // var_dump($skip_agent_tree);
    // -------------------------------------------
    // 被跳過的代理商 count
    // -------------------------------------------
    $skip_agent_tree_count = ( is_null($skip_agent_tree[$userlist->id]) ? 0 : count($skip_agent_tree[$userlist->id]) );
    $b['skip_agent_tree_count'] = $skip_agent_tree_count;
    $skip_agent_tree_html = '<a href="#" title="'.$skip_agent_tree_list.'" >'.$skip_agent_tree_count.'</a>';
    $b['skip_bonusinfo'] = $skip_agent_tree_count.':'.$skip_agent_tree_list;

    // 達標的代理商
    // var_dump($ptree);
    // -------------------------------------------
    // 達標的會員第1層
    // -------------------------------------------
    $pti = 1;
    if( isset($ptree[$userlist->id][$pti]->account) ) {
        // 達標代數會員帳號
        $ptree_member_html[$pti] = $ptree[$userlist->id][$pti]->account;
        // 達標者身份
        $ptree_member_therole_html[$pti] = $ptree[$userlist->id][$pti]->therole;
    }
    else{
        $ptree_member_html[$pti] = 'n/a';
        $ptree_member_therole_html[$pti] = 'n/a';
    }

    // 達標的會員第2層
    $pti = 2;
    if( isset($ptree[$userlist->id][$pti]->account) ) {
        // 達標代數會員帳號
        $ptree_member_html[$pti] = $ptree[$userlist->id][$pti]->account;
        // 達標者身份
        $ptree_member_therole_html[$pti] = $ptree[$userlist->id][$pti]->therole;
    }
    else{
        $ptree_member_html[$pti] = 'n/a';
        $ptree_member_therole_html[$pti] = 'n/a';
    }

    // 達標的會員第3層
    $pti = 3;
    if(isset($ptree[$userlist->id][$pti]->account)) {
        // 達標代數會員帳號
        $ptree_member_html[$pti] = $ptree[$userlist->id][$pti]->account;
        // 達標者身份
        $ptree_member_therole_html[$pti] = $ptree[$userlist->id][$pti]->therole;
    }
    else{
        $ptree_member_html[$pti] = 'n/a';
    $ptree_member_therole_html[$pti] = 'n/a';
    }

    // 達標的會員第4層
    $pti = 4;
    if(isset($ptree[$userlist->id][$pti]->account)) {
        // 達標代數會員帳號
        $ptree_member_html[$pti] = $ptree[$userlist->id][$pti]->account;
        // 達標者身份
        $ptree_member_therole_html[$pti] = $ptree[$userlist->id][$pti]->therole;
    }
    else{
        $ptree_member_html[$pti] = 'n/a';
        $ptree_member_therole_html[$pti] = 'n/a';
    }

    // 此寫法為了閱讀 array 方便, 第一個 index 使用了 member ID
    // 所以每次進入 loop 需要 free 記憶體, 否則筆數資料一多, 就會記憶體不足。
    unset($tree);
    unset($ptree);
    unset($skip_agent_tree);
    // -------------------------------------------
    // END
    // -------------------------------------------
    $b['profitaccount_1'] = $ptree_member_html[1];
    $b['ptree_member_therole_html_1'] = $ptree_member_therole_html[1];
    $b['profitaccount_2'] = $ptree_member_html[2];
    $b['ptree_member_therole_html_2'] = $ptree_member_therole_html[2];
    $b['profitaccount_3'] = $ptree_member_html[3];
    $b['ptree_member_therole_html_3'] = $ptree_member_therole_html[3];
    $b['profitaccount_4'] = $ptree_member_html[4];
    $b['ptree_member_therole_html_4'] = $ptree_member_therole_html[4];
    // 跳過的代數填入

    // -------------------------------------------
    // 紅利(3) 分潤的個人損益計算公式
    // -------------------------------------------
    // 這個公式等確認後，在寫成函式計算
    // $sum_all_profitlost_amount = sum_all_profitlost_amount($userlist[$i]->id, $current_datepicker_start, $current_datepicker_end);
    // 把所有時間範圍內的統計資料都撈出來.
    if( isset($config['website_type']) && ($config['website_type'] == 'casino') ){
        // 判断网站类型，如為娱乐城，总投注以所有娱乐城的投注量计算
        $profit_sql = <<<SQL
            SELECT sum(tokenfavorable) as sum_tokenfavorable,
                   sum(tokenpreferential) as sum_tokenpreferential,
                   sum(all_bets) as sum_all_bets,
                   sum(all_wins) as sum_all_wins,
                   sum(all_profitlost) as sum_all_profitlost,
                   count(all_profitlost) as days_count,
                   sum(all_count) as sum_all_count
            FROM root_statisticsdailyreport
            WHERE dailydate >= '{$current_datepicker_start}' AND
                  dailydate <= '{$current_datepicker_end}' AND
                  member_id = '{$userlist->id}';
        SQL;
    }
    else if( isset($config['website_type']) && ($config['website_type'] == 'ecshop') ){
        // 判断网站类型，如為商城，总投注以ECSHOP的下单量计算
        $profit_sql = <<<SQL
            SELECT sum(tokenfavorable) as sum_tokenfavorable,
                   sum(tokenpreferential) as sum_tokenpreferential,
                   sum(ec_sales) as sum_all_bets,
                   sum(ec_cost) as sum_all_wins,
                   sum(ec_profitlost) as sum_all_profitlost,
                   count(ec_profitlost) as days_count,
                   sum(ec_count) as sum_all_count
            FROM root_statisticsdailyreport
            WHERE dailydate >= '$current_datepicker_start' AND dailydate <= '$current_datepicker_end' AND member_id = '".$userlist->id."';
        SQL;
    }
    else{
        die('系统参数错误！');
    }
    // echo '<pre>', var_dump($profit_sql), '</pre>';
    $profit_result = runSQLall($profit_sql); // echo '<pre>', var_dump($profit_result), '</pre>';

    // 所有的損益狀況內容
    if( $profit_result[0] == 1 ){
        $r['result'] = $profit_result[1];

        // 金流成本比例 0.8 ~ 2%
        $cashcost_rate = ($rule['cashcost_rate'] / 100);

        // 金流成本 = (提款成本 + 出款成本) --- todo , 目前先不計算金流成本
        $member_profitlost_cashcost = 0;
        $b['member_profitlost_cashcost'] = $member_profitlost_cashcost;

        // 優惠成本
        $b['sum_tokenfavorable'] = round($r['result']->sum_tokenfavorable, 2);

        // 反水成本
        $b['sum_tokenpreferential'] = round($r['result']->sum_tokenpreferential, 2);

        // 行銷成本 = (優惠金額 + 反水金額)
        $member_profitlost_marketingcost = round(($r['result']->sum_tokenfavorable + $r['result']->sum_tokenpreferential),2);
        $b['member_profitlost_marketingcost'] = $member_profitlost_marketingcost;

        // 平台成本比例 5% ~ 17%, 以 12% 當平台固定成本
        //$platformcost_rate = $rule['platformcost_rate']/100;
        // 20171103 決定暫不計算平台成本
        $platformcost_rate = '0';
        // 平台成本 = 個人娛樂城損益 * 平台成本比例 (原本要分娛樂城因為避免計算困難, 拆帳不易在股利分配時再發放)
        // sum_all_profitlost 投注損益為負值, 則平台成本為 0
        if( $r['result']->sum_all_profitlost < 0 ) {
            $member_profitlost_platformcost = 0;
        }
        else{
            $member_profitlost_platformcost = round( ($r['result']->sum_all_profitlost * $platformcost_rate), 2 ); // 0 (因為platformcost_rate為0)
        }
        $b['member_profitlost_platformcost'] = $member_profitlost_platformcost;

        // 個人貢獻 = 個人娛樂城虧損
        // 個人貢獻平台的損益 = 個人娛樂城損益 - 平台成本 - 行銷成本 - 金流成本
        $member_profitlost_amount = round( ($r['result']->sum_all_profitlost - $member_profitlost_platformcost - $member_profitlost_marketingcost - $member_profitlost_cashcost), 2 );
        $b['profit_amount'] = $member_profitlost_amount;

        // 此紀錄累積統計的天數(日報表資料筆數) , 注意如果 null 在插入 sql 的時候 number 型態會不允許.
        $b['days_count'] = round( $r['result']->days_count, 2 );

        // 會員注單量
        $b['sum_all_count'] = round($r['result']->sum_all_count, 2);

        // 全部的投注金額
        $b['sum_all_bets'] = round($r['result']->sum_all_bets, 2);

        // 全部的派彩金額
        $b['sum_all_wins'] = round($r['result']->sum_all_wins, 2);

        // 全部的損益金額(未扣成本)
        $b['sum_all_profitlost'] = round($r['result']->sum_all_profitlost, 2);

    }else{
        $logger = '會員'.$userlist->id.'資料('.$current_datepicker_start.'~'.$current_datepicker_end.')讀取錯誤，請聯絡開發人員處理。';
        die($logger);
    }

    // -------------------------------------------
    // 紅利(3) 分潤的個人損益計算公式 END
    // -------------------------------------------

    // -------------------------------------------
    // 營利獎金分紅額度, 依據損益 $member_profitlost_amount 四層分紅
    // -------------------------------------------
    //var_dump($member_profitlost_amount);
    // echo '<pre>', var_dump($rule), '</pre>'; exit();
    // 營業獎金分紅額度 - 第1代
    $profit_bonus_rate_amount_1 = round( ($member_profitlost_amount*$rule['commission_1_rate']/100), 2 );
    $b['profit_amount_1'] = $profit_bonus_rate_amount_1; // 20

    // 營業獎金分紅額度 - 第2代
    $profit_bonus_rate_amount_2 = round( ($member_profitlost_amount*$rule['commission_2_rate']/100), 2 );
    $b['profit_amount_2'] = $profit_bonus_rate_amount_2; // 20

    // 營業獎金分紅額度 - 第3代
    $profit_bonus_rate_amount_3 = round( ($member_profitlost_amount*$rule['commission_3_rate']/100), 2 );
    $b['profit_amount_3'] = $profit_bonus_rate_amount_3; // 20

    // 營業獎金分紅額度 - 第4代
    $profit_bonus_rate_amount_4 = round( ($member_profitlost_amount*$rule['commission_4_rate']/100), 2 );
    $b['profit_amount_4'] = $profit_bonus_rate_amount_4; // 20
    // ----------------------------------------------------

    // 第二次更新的資訊, 在第一次更新時, 顯示 n/a 表示不適用。
    // 上月留底 , 撈上個月的負值來處理。
    //$b['lasttime_stayindebt'] = NULL;
    // 第二次運算
    $b['member_profitamount_1'] = 'n/a';
    $b['member_profitamount_count_1'] = 'n/a';
    $b['member_profitamount_2'] = 'n/a';
    $b['member_profitamount_count_2'] = 'n/a';
    $b['member_profitamount_3'] = 'n/a';
    $b['member_profitamount_count_3'] = 'n/a';
    $b['member_profitamount_4'] = 'n/a';
    $b['member_profitamount_count_4'] = 'n/a';
    $b['member_profitamount'] = 'n/a';
    $b['member_profitamount_count'] = 'n/a';

    return($b);
} // end bonus_commission_profit_data

// -------------------------------------------------------------------------
// 輸出目前系統的資料日期的表單, 以作為後續更新管理的參考
// -------------------------------------------------------------------------

// -------------------------------------------------------------------------
// 取得目前系統的上一個月份的時間, 用來計算上期的欠帳. 如果沒有上一期帳單的話, 回傳為 false
// Usage: pre_dailyrange($dailydate_start,$dailydate_end)
// -------------------------------------------------------------------------
function pre_dailyrange( $dailydate_start, $dailydate_end ){
    // 列出系統資料統計月份
    $list_sql = <<<SQL
        SELECT dailydate_start,
               dailydate_end,
               MIN(updatetime) as min,
               MAX(updatetime) as max,
               count(member_account) as member_account_count,
               sum(sum_all_profitlost) as sum_sum_all_profitlost,
               sum(profit_amount) as sum_profit_amount,
               sum(sum_all_bets) as sum_sum_all_bets,
               sum(sum_all_count) as sum_sum_all_count
        FROM root_statisticsbonusprofit
        GROUP BY dailydate_end,dailydate_start
        ORDER BY dailydate_start DESC;
    SQL;

    $list_result = runSQLall($list_sql); // '<pre>', var_dump($list_result), '</pre>';

    $pre_dailydate_start = NULL;
    $pre_dailydate_end = NULL;

    // 預設為失敗 , 如果沒有更新的話
    $r = false;

    if( $list_result[0] > 0 ){
        // 把資料 dump 出來 to table
        for( $i=1; $i<=$list_result[0]; $i++ ){
            // 取得上一個月計算週期的時間
            if( ($list_result[$i]->dailydate_start == $dailydate_start) && ($list_result[$i]->dailydate_end == $dailydate_end) ){
                $j = $i+1;
                if( isset($list_result[$j]->dailydate_start) && isset($list_result[$j]->dailydate_end) ){
                    $r['pre_dailydate_start'] = $list_result[$j]->dailydate_start;
                    $r['pre_dailydate_end']   = $list_result[$j]->dailydate_end;
                }
            }
        }
    }

    return($r);
} // end pre_dailyrange
// ---------------------------------------------------------------------------
// END 取得目前系統的上一個月份的時間, 用來計算上期的欠帳
// ---------------------------------------------------------------------------

// -------------------------------------------------------------------------
// 取得日期 - 決定開始用份的範圍日期
// -------------------------------------------------------------------------
// get example: ?current_datepicker=2017-02-03
// ref: http://php.net/manual/en/function.checkdate.php
function validateDate( $date, $format = 'Y-m-d H:i:s' ){
		$d = DateTime::createFromFormat($format, $date);
		return $d && $d->format($format) == $date;
} // end validateDate

// ----------------------------------
// 本程式使用的 function END
// ----------------------------------

// -----------------------------------------------------------------
// 安全控管, 如果是 web 執行就立即中斷, 只允許 command 執行此程式。
// -----------------------------------------------------------------
if( !$debug ){ // 如果有開啟debug模式，則關閉 "限制使用命令列執行"
    // 如果 HTTP_USER_AGENT OR SERVER_NAME 存在, 表示是直接透過網頁呼叫程式, 拒絕這樣的呼叫
    if( isset($_SERVER['HTTP_USER_AGENT']) || isset($_SERVER['SERVER_NAME']) ) {
        die('禁止使用網頁呼叫，來源錯誤，請使用命令列執行。');
    }
}

// -----------------------------------------------------------------
// 命令列參數解析
// -----------------------------------------------------------------

// 取得今天的日期
// 轉換為美東的時間 date
$date = date_create( date('Y-m-d H:i:sP'), timezone_open('America/St_Thomas') );
date_timezone_set( $date, timezone_open('America/St_Thomas') );
$current_date = date_format($date, 'Y-m-d');
$current_date_timestamp = strtotime($current_date);

//
if( isset($argv[1]) && ( ($argv[1] == 'test') || ($argv[1] == 'run') ) ){
    if( isset($argv[2]) && validateDate($argv[2], 'Y-m-d') ){
            if( $argv[2]<=$current_date ){
                //如果有的話且格式正確, 取得日期. 沒有的話中止
                $current_datepicker = $argv[2];
            }
            else{
                $current_datepicker = $current_date;
            }
    }
    else{
        $current_datepicker = $current_date;
    }

    $argv_check = $argv[1];
	$current_datepicker_gmt = gmdate('Y-m-d H:i:s.u',strtotime($current_datepicker.'23:59:59 -04')+8*3600).'+08:00';
}
else{
    // command 動作 時間
    echo "command [test|run] YYYY-MM-DD \n";
    die('no test and run');
}

if( isset($argv[3]) && ($argv[3] == 'web') ){
    $web_check = 1;
    // loading圖示
    $output_html = <<<HTML
        <p align="center">更新中...
            <img src="ui/loading.gif" />
        </p>
        <script>
            setTimeout(function(){location.reload()},1000);
        </script>
    HTML;
	$file_key = sha1('profit_update'.$argv[2]);
	$reload_file = dirname(__FILE__) .'/tmp_dl/profit_'.$file_key.'.tmp';
	file_put_contents($reload_file,$output_html);
}
else if( isset($argv[3]) && ($argv[3] == 'sql') ){
	if( isset($argv[4]) && filter_var($argv[4], FILTER_VALIDATE_INT) ){
		$web_check = 2;
		$updatelog_id = filter_var($argv[4], FILTER_VALIDATE_INT);
        $updatelog_sql = <<<SQL
            SELECT * FROM root_bonusupdatelog WHERE id ='{$updatelog_id}';
        SQL;
		$updatelog_result = runSQL($updatelog_sql);
		if( $updatelog_result==0 ){
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
// 如果選擇的日期, 大於設定的月結日期，就以下個月顯示. 如果不是的話就是上個月顯示
$current_date_d = date( "d", strtotime("$current_datepicker") ); // echo '<pre>', var_dump($current_date_d), '</pre>'; // 01
$current_date_m = date( "m", strtotime("$current_datepicker") ); // echo '<pre>', var_dump($current_date_m), '</pre>'; // 09
$current_date_Y = date( "Y", strtotime("$current_datepicker") ); // echo '<pre>', var_dump($current_date_Y), '</pre>'; // 2019
 //echo '<pre>', var_dump($rule['stats_profit_day']), '</pre>'; // 30
// die('測試斷點');
// 選擇的日期大於設定的月結日期 (以下個月顯示)
if( $current_date_d > $rule['stats_profit_day'] ){
    $date_fmt = 'Y-m-'.$rule['stats_profit_day'];
    $current_date_m++;
    $current_datepicker = $current_date_Y.'-'.$current_date_m.'-'.$rule['stats_profit_day'];

    // 取得當月的最後一天，以免因設定造成日期取超出當月最後一天而無法自DB取資料
    $lastdayofmonth = date("Y-m-t", strtotime($current_date_Y.'-'.$current_date_m.'-1'));
    // 判斷是否大於當月的最後一天，如是，則以當月最後一天計算，
    // 此判斷主要作用於當$rule['stats_profit_day'] > 28 時，以免因設定造成日期取超出當月最後一天而無法自DB取資料
    if( $current_datepicker > $lastdayofmonth ){
        $current_datepicker_end = $lastdayofmonth;
    }
    else{
        $current_datepicker_end = $current_datepicker;
    }
    //var_dump($current_datepicker_end);

    // 計算前一輪的計算日
    $current_date_m--;
    $dayofcurrentstart = $rule['stats_profit_day'] + 1;
    $current_datepicker_start = $current_date_Y.'-'.$current_date_m.'-'.$dayofcurrentstart; // var_dump($current_datepicker_start);

    // 取得當月的最後一天，以免因設定造成日期取超出當月最後一天而無法自DB取資料
    $lastdayofmonth_lastcycle = date("Y-m-t", strtotime($current_date_Y.'-'.$current_date_m.'-1'));
    // 判斷是否大於當月的最後一天，如是，則以當月最後一天計算，
    // 此判斷主要作用於當$rule['stats_profit_day'] > 28 時，以免因設定造成日期取超出當月最後一天而無法自DB取資料
    if( ($current_datepicker_start > $lastdayofmonth_lastcycle) && ($current_date_m == date( "m", strtotime($current_datepicker_start) )) ){
        if( $current_date_m == 2 ){
            $current_date_m++;
            $current_datepicker_start = $current_date_Y.'-'.$current_date_m.'-1';
        }
        else{
            $current_datepicker_start = $lastdayofmonth_lastcycle;
        }
    }
}
// 選擇的日期小於設定的月結日期 (以這個月顯示)
else{
    $date_fmt = 'Y-m-'.$rule['stats_profit_day'];
    $current_datepicker = $current_date_Y.'-'.$current_date_m.'-'.$rule['stats_profit_day'];

    // 取得當月的最後一天，以免因設定造成日期取超出當月最後一天而無法自DB取資料
    $lastdayofmonth = date("Y-m-t", strtotime($current_date_Y.'-'.$current_date_m.'-1'));

    // 判斷是否大於當月的最後一天，如是，則以當月最後一天計算，
    // 此判斷主要作用於當$rule['stats_profit_day'] > 28 時，以免因設定造成日期取超出當月最後一天而無法自DB取資料
    if( $current_datepicker > $lastdayofmonth ){
        $current_datepicker_end = $lastdayofmonth;
    }
    else{
        $current_datepicker_end = $current_datepicker;
    }

    // 計算前一輪的計算日
    $current_date_m--;
    $dayofcurrentstart = $rule['stats_profit_day'] + 1;
    $current_datepicker_start = date("Y-m-d", strtotime($current_date_Y.'-'.$current_date_m.'-'.$dayofcurrentstart)); //var_dump($current_datepicker_start);

    // 取得當月的最後一天，以免因設定造成日期取超出當月最後一天而無法自DB取資料
    $lastdayofmonth_lastcycle = date("Y-m-t", strtotime($current_date_Y.'-'.$current_date_m.'-1'));

    // 判斷是否大於當月的最後一天，如是，則以當月最後一天計算，
    // 此判斷主要作用於當$rule['stats_profit_day'] > 28 時，以免因設定造成日期取超出當月最後一天而無法自DB取資料
    if( ($current_datepicker_start > $lastdayofmonth_lastcycle) && ($current_date_m == date( "m", strtotime( $current_datepicker_start) )) ){
        if( $current_date_m == 2 ){
            $current_date_m++;
            $current_datepicker_start = $current_date_Y.'-'.$current_date_m.'-1';
        }
        else{
            $current_datepicker_start = $lastdayofmonth_lastcycle;
        }
    }
}
// die('測試斷點');
$current_datepicker_end_gmt = gmdate('Y-m-d H:i:s.u',strtotime($current_datepicker_end.'23:59:59 -04')+8*3600).'+08:00';

// var_dump($date_fmt);
// var_dump($current_datepicker);
// var_dump($current_datepicker_end);
// var_dump($current_datepicker_start);
// 本月的結束日 = $current_datepicker_end
// ----------------------------------------------------------------------------

// ----------------------------------------------------------------------------
// MAIN
// ----------------------------------------------------------------------------

// ----------------------------------------------------------------------------
// round 1. 新增或更新會員資料
// ----------------------------------------------------------------------------

// echo '<pre>', var_dump($web_check), '</pre>';
if( $web_check == 1 ){
    $output_html  = <<<HTML
        <p align="center">round 1. 新增或更新會員資料 - 更新中...
            <img src="ui/loading.gif" />
        </p>
        <script>
            setTimeout(function(){location.reload()},1000);
        </script>
    HTML;
	file_put_contents($reload_file,$output_html);
}
else if( $web_check == 2 ){
	$updatlog_note = 'round 1. 新增或更新會員資料 - 更新中';
    $updatelog_sql = <<<SQL
        UPDATE root_bonusupdatelog SET
            bonus_status = '0',
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
	echo "round 1. 新增或更新會員資料 - 開始\n";
}
// die('測試斷點');

// 列出所有的會員資料及人數 SQL
// ----------------------------------------------------------------------------
// 判斷重算時間是否已超過統計區間
// 如果超過，會員組織就由原TABLE內的記錄來計算，不更新會員組織
$bonus_days_end_timestamp = strtotime($current_datepicker);
/* echo '<pre>', var_dump(
    $current_datepicker,
    $current_date_timestamp,
    $bonus_days_end_timestamp,
    ( $current_date_timestamp <= $bonus_days_end_timestamp )
) ,'</pre>';
die('測試斷點'); */
// 取出 root_member 資料
if( $current_date_timestamp <= $bonus_days_end_timestamp ){
    $userlist_sql = <<<SQL
        SELECT id,
               account,
               parent_id,
               therole
        FROM root_member
        WHERE enrollmentdate <= '{$current_datepicker_end_gmt}' OR enrollmentdate IS NULL
        ORDER BY id ASC;
    SQL;
	$timeover = 0;
	$userlist = runSQLall($userlist_sql);
}
else{
    // 放射線組織獎金計算-營運利潤獎金
    $userlist_sql = <<<SQL
        SELECT member_id as id,
               member_account as account,
               member_parent_id as parent_id,
               member_therole as therole
        FROM root_statisticsbonusprofit
        WHERE dailydate_start='{$current_datepicker_start}' AND dailydate_end='{$current_datepicker}'
    SQL;
	$timeover = 1;
    $userlist = runSQLall($userlist_sql);

    // 沒有的話回去抓會員資料
	if( $userlist[0] == 0 ){
        $userlist_sql = <<<SQL
            SELECT id,
                   account,
                   parent_id,
                   therole
            FROM root_member
            WHERE enrollmentdate <= '{$current_datepicker_end_gmt}' OR enrollmentdate IS NULL
            ORDER BY id ASC;
        SQL;
		$timeover = 0;
		$userlist = runSQLall($userlist_sql);
	}
}

// echo '<pre>', var_dump( $userlist ) ,'</pre>';
// die('測試斷點');

$userlist_count = $userlist[0];

// 先取得上個月的日期
$pre_dailyrange = pre_dailyrange($current_datepicker_start, $current_datepicker_end); //var_dump($pre_dailyrange);

// 處理進度 % , 用來顯示紀錄進度。
$percentage_current = 0;
// 判斷 root_member count 數量大於 1
if( $userlist[0] >= 1 ){
    // 會員有資料，且存在數量為 $userlist_count
    // 以會員為主要 key 依序列出每個會員的貢獻金額
    for( $i = 1; $i <= $userlist_count; $i++ ){
        // var_dump($userlist[$i]);

        $b['dailydate_start'] = $current_datepicker_start;
        $b['dailydate_end'] = $current_datepicker_end;

        // ----------------------------------------------------
        // 會員帳號基本資訊, 無論是否有資料都會呈現。
        // ----------------------------------------------------
        // 會員ID
        $member_id_html = <<<HTML
            <a href="member_treemap.php?id={$userlist[$i]->id}" target="_blank" title="會員的組織結構狀態">{$userlist[$i]->id}</a>
        HTML;

        // 上一代的資訊
        $member_parent_html = <<<HTML
            <a href="member_account.php?a={$userlist[$i]->parent_id}" target="_blank"  title="會員上一代資訊">{$userlist[$i]->parent_id}</a>
        HTML;

        // 會員身份(角色)
        $member_therole_html = <<<HTML
            <a href="#" title="會員身份 R=管理員 A=代理商 M=會員">{$userlist[$i]->therole}</a>
        HTML;

        // 會員帳號
        $member_account_html = <<<HTML
            <a href="member_account.php?a={$userlist[$i]->id}" target="_blank" title="檢查會員的詳細資料">{$userlist[$i]->account}</a>
        HTML;
        // ---------------------------------------------------
        // 預設的四個欄位, 由 member 取得資訊
        $b['member_id'] = $userlist[$i]->id;
        $b['member_parent_id'] = $userlist[$i]->parent_id;
        $b['member_therole'] = $userlist[$i]->therole;
        $b['member_account'] = $userlist[$i]->account;

        // ----------------------------------------------------
        // 檢查資料是否在 root_statisticsbonusprofit DB 中 , 如果存在的話應該是已經生成了.
        // 如果 $update_bonusprofit_option_status = true , 就使用 update sql 更新 更新營利獎金的投注量資訊 資料
        // 如果不存在的話, 使用 insert 插入資料到系統內.
        // ----------------------------------------------------
        $check_data_alive_sql = <<<SQL
            SELECT *
            FROM root_statisticsbonusprofit
            WHERE dailydate_start = '{$current_datepicker_start}' AND
                  dailydate_end = '{$current_datepicker_end}' AND
                  member_account = '{$userlist[$i]->account}';
        SQL; // echo '<pre>', var_dump($check_data_alive_sql) ,'</pre>';
        $check_data_alive_result = runSQLall($check_data_alive_sql); // echo '<pre>', var_dump($check_data_alive_result) ,'</pre>';
        // echo '檢查時間範圍內會員是否有資料';

        if( $check_data_alive_result[0] == 1 ){
            // 是否強至更新月結注單紀錄, 這個更新需要重新計算本月的分紅。
            // 取得指定使用者的資料
            $b = bonus_commission_profit_data($userlist[$i], $current_datepicker_start, $current_datepicker_end); // echo '<pre>', var_dump($b) ,'</pre>';

            // 資料已經存在 update date , 只更新從每日報表的資料源
            $update_sql = <<<SQL
                UPDATE root_statisticsbonusprofit SET
                       member_parent_id = '{$b['member_parent_id']}',
                       member_therole = '{$b['member_therole']}',
                       updatetime = now(),
                       member_level = '{$b['member_level']}',
                       skip_bonusinfo = '{$b['skip_bonusinfo']}',
                       profitaccount_1 = '{$b['profitaccount_1']}',
                       profitaccount_2 = '{$b['profitaccount_2']}',
                       profitaccount_3 = '{$b['profitaccount_3']}',
                       profitaccount_4 = '{$b['profitaccount_4']}',
                       profit_amount = '{$b['profit_amount']}',
                       profit_amount_1 = '{$b['profit_amount_1']}',
                       profit_amount_2 = '{$b['profit_amount_2']}',
                       profit_amount_3 = '{$b['profit_amount_3']}',
                       profit_amount_4 = '{$b['profit_amount_4']}',
                       member_profitlost_cashcost = '{$b['member_profitlost_cashcost']}',
                       member_profitlost_marketingcost = '{$b['member_profitlost_marketingcost']}',
                       sum_tokenfavorable = '{$b['sum_tokenfavorable']}',
                       sum_tokenpreferential = '{$b['sum_tokenpreferential']}',
                       member_profitlost_platformcost = '{$b['member_profitlost_platformcost']}',
                       days_count = '{$b['days_count']}',
                       sum_all_count = '{$b['sum_all_count']}',
                       sum_all_bets = '{$b['sum_all_bets']}',
                       sum_all_wins = '{$b['sum_all_wins']}',
                       sum_all_profitlost = '{$b['sum_all_profitlost']}'
                WHERE member_account = '{$b['member_account']}' AND
                      dailydate_start = '{$b['dailydate_start']}' AND
                      dailydate_end = '{$b['dailydate_end']}';
            SQL;

            // 執行更新結果
            if( $argv_check == 'test' ){
                echo $update_sql;
                $update_result = 1;
            }
            else if( $argv_check == 'run' ){
                $update_result = runSQL($update_sql);
            }
            $stats_update_count++;

            // ------- bonus update log ------------------------
            // 顯示目前的處理紀錄進度，及花費的時間。 換算進度 %
            $percentage_html = (round( ($i/$userlist[0]), 2 ) * 100);
            $process_record_html = ("$i/$userlist[0]");
            $process_times_html  = round( (microtime(true) - $program_start_time), 3 );
            $counting_r = ($percentage_html % 10);

            if( ($web_check == 1) && ($counting_r == 0) ){
                $output_sub_html  = $output_html.'<p align="center">'.$percentage_html.'%</p>';
                file_put_contents($reload_file, $output_sub_html);
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
                else if( $argv_check == 'run' ){
                    $updatelog_result = runSQLall($updatelog_sql);
                }
            }
            else if( $web_check == 0 ){
                if( $percentage_html != $percentage_current ){
                    if( $counting_r == 0 ){
                        echo "\n目前處理 $current_datepicker_end 紀錄: $process_record_html ,執行進度: $percentage_html% ,花費時間: ".$process_times_html."秒\n";
                    }
                    else{
                        echo $percentage_html.'% ';
                    }
                    $percentage_current = $percentage_html;
                }
            }
            // -------------------------------------------------
            if( $update_result == 1 ){
                $logger = '更新月結投注紀錄'.$b['member_id'].'Update Success, member account is '.$b['member_account'].',date start:'.$current_datepicker_start.',date end:'.$current_datepicker_end;
                // echo $logger;
            }
            else{
                $logger = '更新月結投注紀錄'.$b['member_id'].'Update Fail, member account is '.$b['member_account'].',date start:'.$current_datepicker_start.',date end:'.$current_datepicker_end;
                die($logger);
            }

        }
        else{
            // 沒有資料的處理  do insert sql data
            // 計算撈取時間範圍內的資料, 把資料插入資料庫中
            $b = bonus_commission_profit_data($userlist[$i], $current_datepicker_start, $current_datepicker_end); // echo '<pre>', var_dump($b) ,'</pre>';

            // 插入 insert SQL
            // ----------------------------------------------------
            $insert_sql = <<<SQL
                INSERT INTO "root_statisticsbonusprofit" (
                            "member_id",
                            "member_account",
                            "member_parent_id",
                            "member_therole",
                            "updatetime",
                            "dailydate_start",
                            "dailydate_end",
                            "member_level",
                            "skip_bonusinfo",
                            "profitaccount_1",
                            "profitaccount_2",
                            "profitaccount_3",
                            "profitaccount_4",
                            "profit_amount",
                            "profit_amount_1",
                            "profit_amount_2",
                            "profit_amount_3",
                            "profit_amount_4",
                            "member_profitamount",
                            "member_profitamount_paid",
                            "member_profitamount_paidtime",
                            "notes",
                            "lasttime_stayindebt",
                            "member_profitlost_cashcost",
                            "member_profitlost_marketingcost",
                            "sum_tokenfavorable",
                            "sum_tokenpreferential",
                            "member_profitlost_platformcost",
                            "days_count",
                            "sum_all_count",
                            "sum_all_bets",
                            "sum_all_wins",
                            "sum_all_profitlost"
                )
                VALUES (
                    '{$b['member_id']}',
                    '{$b['member_account']}',
                    '{$b['member_parent_id']}',
                    '{$b['member_therole']}',
                    now(),
                    '{$b['dailydate_start']}',
                    '{$b['dailydate_end']}',
                    '{$b['member_level']}',
                    '{$b['skip_bonusinfo']}',
                    '{$b['profitaccount_1']}',
                    '{$b['profitaccount_2']}',
                    '{$b['profitaccount_3']}',
                    '{$b['profitaccount_4']}',
                    '{$b['profit_amount']}',
                    '{$b['profit_amount_1']}',
                    '{$b['profit_amount_2']}',
                    '{$b['profit_amount_3']}',
                    '{$b['profit_amount_4']}',
                    NULL,
                    NULL,
                    NULL,
                    NULL,
                    NULL,
                    '{$b['member_profitlost_cashcost']}',
                    '{$b['member_profitlost_marketingcost']}',
                    '{$b['sum_tokenfavorable']}',
                    '{$b['sum_tokenpreferential']}',
                    '{$b['member_profitlost_platformcost']}',
                    '{$b['days_count']}',
                    '{$b['sum_all_count']}',
                    '{$b['sum_all_bets']}',
                    '{$b['sum_all_wins']}',
                    '{$b['sum_all_profitlost']}'
                );
            SQL; // echo '<pre>', var_dump($insert_sql) ,'</pre>';

            if( $argv_check == 'test' ){
                echo $insert_sql;
                $insert_result = 1;
            }
            else if( $argv_check == 'run' ){
                $insert_result = runSQL($insert_sql);
            }
            $stats_insert_count++;

            // ------- bonus update log ------------------------
            // 顯示目前的處理紀錄進度，及花費的時間。 換算進度 %
            $percentage_html = ( round(($i/$userlist[0]), 2) * 100 );
            $process_record_html = ("$i/$userlist[0]");
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
                    WHERE id = '{$updatelog_id}'
                SQL;
                if( $argv_check == 'test' ){
                    echo $updatelog_sql;
                }
                else if( $argv_check == 'run' ){
                    $updatelog_result = runSQLall($updatelog_sql);
                }
            }
            else if( $web_check == 0 ){
                if( $percentage_html != $percentage_current ){
                    if( $counting_r == 0 ){
                        echo "\n目前處理 $current_datepicker_end 紀錄: $process_record_html ,執行進度: $percentage_html% ,花費時間: ".$process_times_html."秒\n";
                    }
                    else{
                        echo $percentage_html.'% ';
                    }
                    $percentage_current = $percentage_html;
                }
            }
            // -------------------------------------------------
            if( $insert_result == 1 ){
                $logger = '紀錄插入紀錄成功'.$b['member_id'].'Member account is '.$b['member_account'].',date start:'.$current_datepicker_start.',date end:'.$current_datepicker_end;
                //echo $logger;
            }
            else{
                $logger = '紀錄插入紀錄失敗'.$b['member_id'].'Member account is '.$b['member_account'].',date start:'.$current_datepicker_start.',date end:'.$current_datepicker_end;
                die($logger);
            }
        }
    } // end for
}

// ----------------------------------------------------------------------------
// round 2. 更新會員营运利润奖金資料
// ----------------------------------------------------------------------------
if( $web_check == 1 ){
    $output_html  = <<<HTML
        <p align="center">round 2. 更新會員營運利潤獎金資料 - 更新中...
            <img src="ui/loading.gif" />
        </p>
        <script>
            setTimeout(function(){location.reload()},1000);
        </script>
    HTML;
	file_put_contents($reload_file,$output_html);
}
else if( $web_check == 2 ){
	$updatlog_note = 'round 2. 更新會員營運利潤獎金資料 - 更新中';
    $updatelog_sql = <<<SQL
        UPDATE root_bonusupdatelog SET
            bonus_status = '0',
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
	echo "round 2. 更新會員營運利潤獎金資料 - 開始\n";
}
// -------------------------------------
// 列出所有的會員資料及人數 SQL
// -------------------------------------
// 取出 root_member 資料
$userlist_sql = <<<SQL
    SELECT member_id as id,
           member_account as account,
           member_parent_id as parent_id,
           member_therole as therole
    FROM root_statisticsbonusprofit
    WHERE dailydate_start ='{$current_datepicker_start}' AND
          dailydate_end ='{$current_datepicker_end}';
SQL;
$userlist       = runSQLall($userlist_sql);
$userlist_count = $userlist[0];

// 先取得上個月的日期
$pre_dailyrange = pre_dailyrange($current_datepicker_start,$current_datepicker_end);
//var_dump($pre_dailyrange);

// 處理進度 % , 用來顯示紀錄進度。
$percentage_current = 0;
// 判斷 root_member count 數量大於 1
if($userlist[0] >= 1) {
  // 會員有資料，且存在數量為 $userlist_count
  // 以會員為主要 key 依序列出每個會員的貢獻金額
  for($i = 1 ; $i <= $userlist_count ; $i++){
    // var_dump($userlist[$i]);

    $b['dailydate_start'] = $current_datepicker_start;
    $b['dailydate_end'] = $current_datepicker_end;

    // ----------------------------------------------------
    // 會員帳號基本資訊, 無論是否有資料都會呈現。
    // ----------------------------------------------------
    // 會員ID
    $member_id_html = '<a href="member_treemap.php?id='.$userlist[$i]->id.'" target="_BLANK" title="會員的組織結構狀態">'.$userlist[$i]->id.'</a>';

    // 上一代的資訊
    $member_parent_html = '<a href="member_account.php?a='.$userlist[$i]->parent_id.'" target="_BLANK"  title="會員上一代資訊">'.$userlist[$i]->parent_id.'</a>';

    // 會員身份
    $member_therole_html = '<a href="#" title="會員身份 R=管理員 A=代理商 M=會員">'.$userlist[$i]->therole.'</a>';

    // 會員帳號
    $member_account_html = '<a href="member_account.php?a='.$userlist[$i]->id.'" target="_BLANK" title="檢查會員的詳細資料">'.$userlist[$i]->account.'</a>';
    // ---------------------------------------------------
    // 預設的四個欄位, 由 member 取得資訊
    $b['member_id'] = $userlist[$i]->id;
    $b['member_parent_id'] = $userlist[$i]->parent_id;
    $b['member_therole'] = $userlist[$i]->therole;
    $b['member_account'] = $userlist[$i]->account;




    // ----------------------------------------------------
    // 檢查資料是否在 root_statisticsbonusprofit DB 中 , 如果存在的話應該是已經生成了.
    // 如果 $update_bonusprofit_option_status = true , 就使用 update sql 更新 更新營利獎金的投注量資訊 資料
    // 如果不存在的話, 使用 insert 插入資料到系統內.
    // ----------------------------------------------------
    $check_data_alive_sql = "SELECT * FROM root_statisticsbonusprofit
    WHERE dailydate_start = '$current_datepicker_start' AND dailydate_end = '$current_datepicker_end' AND member_account = '".$userlist[$i]->account."';";
    //var_dump($check_data_alive_sql);
    $check_data_alive_result = runSQLall($check_data_alive_sql);
    //echo '檢查時間範圍內會員是否有資料';
    //var_dump($check_data_alive_result);
    if($check_data_alive_result[0] == 1) {
      // 是否強至更新月結注單紀錄, 這個更新需要重新計算本月的分紅。(二次更新)
      // 檢查時間範圍內會員是否有資料 , 因為有資料所以把資料從 DB 取出來處理.
      // 不更新到 DB 內, 可以快速的讀取資料庫內容.

      // 這個排列會影響 CSV 輸出的順序, 要注意一下.
      // data id
      $b['id'] = $check_data_alive_result[1]->id;
      $b['updatetime'] = $check_data_alive_result[1]->updatetime;

      // 預設有會員的 ID , Account, Role
      $b['member_level']  = $check_data_alive_result[1]->member_level;
      $b['skip_bonusinfo']  = $check_data_alive_result[1]->skip_bonusinfo;
      $skip_bonusinfo_count     = explode(":",$b['skip_bonusinfo']);
      //var_dump($skip_bonusinfo_count);  取得第一個字串，為跳過的代數
      $b['skip_agent_tree_count'] = $skip_bonusinfo_count[0];
      $b['profitaccount_1']  = $check_data_alive_result[1]->profitaccount_1;
      $b['profitaccount_2']  = $check_data_alive_result[1]->profitaccount_2;
      $b['profitaccount_3']  = $check_data_alive_result[1]->profitaccount_3;
      $b['profitaccount_4']  = $check_data_alive_result[1]->profitaccount_4;
      $b['profit_amount']  = $check_data_alive_result[1]->profit_amount;
      $b['profit_amount_1']  = $check_data_alive_result[1]->profit_amount_1;
      $b['profit_amount_2']  = $check_data_alive_result[1]->profit_amount_2;
      $b['profit_amount_3']  = $check_data_alive_result[1]->profit_amount_3;
      $b['profit_amount_4']  = $check_data_alive_result[1]->profit_amount_4;

      // 統計的欄位
      $b['member_profitlost_cashcost']  = $check_data_alive_result[1]->member_profitlost_cashcost;
      $b['member_profitlost_marketingcost']  = $check_data_alive_result[1]->member_profitlost_marketingcost;
      $b['sum_tokenfavorable']  = $check_data_alive_result[1]->sum_tokenfavorable;
      $b['sum_tokenpreferential']  = $check_data_alive_result[1]->sum_tokenpreferential;
      $b['member_profitlost_platformcost']  = $check_data_alive_result[1]->member_profitlost_platformcost;
      $b['days_count']  = $check_data_alive_result[1]->days_count;
      $b['sum_all_count']  = $check_data_alive_result[1]->sum_all_count;
      $b['sum_all_bets']  = $check_data_alive_result[1]->sum_all_bets;
      $b['sum_all_wins']  = $check_data_alive_result[1]->sum_all_wins;
      $b['sum_all_profitlost']  = $check_data_alive_result[1]->sum_all_profitlost;

      // 代理商本月分潤
      $b['member_profitamount_1']       = $check_data_alive_result[1]->member_profitamount_1;
      $b['member_profitamount_count_1'] = $check_data_alive_result[1]->member_profitamount_count_1;
      $b['member_profitamount_2']       = $check_data_alive_result[1]->member_profitamount_2;
      $b['member_profitamount_count_2'] = $check_data_alive_result[1]->member_profitamount_count_2;
      $b['member_profitamount_3']       = $check_data_alive_result[1]->member_profitamount_3;
      $b['member_profitamount_count_3'] = $check_data_alive_result[1]->member_profitamount_count_3;
      $b['member_profitamount_4']       = $check_data_alive_result[1]->member_profitamount_4;
      $b['member_profitamount_count_4'] = $check_data_alive_result[1]->member_profitamount_count_4;
      $b['member_profitamount_count']   = $check_data_alive_result[1]->member_profitamount_count;
      $b['member_profitamount']  = $check_data_alive_result[1]->member_profitamount;
      $b['member_profitamount_paid']  = $check_data_alive_result[1]->member_profitamount_paid;
      $b['member_profitamount_paidtime']  = $check_data_alive_result[1]->member_profitamount_paidtime;

      // 上月留抵
      $b['lasttime_stayindebt']  = $check_data_alive_result[1]->lasttime_stayindebt;
      // 備註
      $b['notes']  = $check_data_alive_result[1]->notes;

      // $logger = '不更新投注紀錄'.$b['member_id'].'Member account is '.$b['member_account'].',date start:'.$current_datepicker_start.',date end:'.$current_datepicker_end;
      // echo $logger;

      //var_dump($check_data_alive_result);


      // -------------------------------------------------------------------
      // 當資料庫的資料 都已經建立完成後, 重新加總這個資料
      // 第二次加總計算 -- 個人的的營利統計加總
      // -------------------------------------------------------------------

      // 如果變數 $update_bonusprofit_person_status 設定為 true (from $_GET) , 將個人的的營利統計加總.
      // 分潤第1代
      $member_profitamount_sql_1 = "SELECT sum(profit_amount_1) as sum_profit_amount, count(profit_amount_1) as count_profit_amount
      FROM root_statisticsbonusprofit WHERE dailydate_start >= '$current_datepicker_start' AND dailydate_end <= '$current_datepicker_end' AND profitaccount_1= '".$b['member_account']."' ;";
      if($argv_check == 'test') {
        print_r($member_profitamount_sql_1);
      }

      $member_profitamount_result_1 = runSQLall($member_profitamount_sql_1);
      //var_dump($member_profitamount_result_1);
      if($member_profitamount_result_1[0] == 1) {
        if($member_profitamount_result_1[1]->sum_profit_amount == NULL) {
          $b['member_profitamount_1'] = 0;
        }else{
          $b['member_profitamount_1'] = $member_profitamount_result_1[1]->sum_profit_amount;
        }
        $b['member_profitamount_count_1'] =$member_profitamount_result_1[1]->count_profit_amount;
        //var_dump($member_profitamount_sql_1);
        //var_dump($member_profitamount_result_1);
      }else{
        $logger ='[BE4001]資料庫存取錯誤, 請聯絡管理人員處理. in '.$b['member_account'].'date:'.$current_datepicker_start.'~'.$current_datepicker_end;
        var_dump($logger);
        memberlog2db($_SESSION['agent']->account, 'bonus profit', 'error', "$logger");
      }

      // 分潤第2代
      $member_profitamount_sql_2 = "SELECT sum(profit_amount_2) as sum_profit_amount, count(profit_amount_2) as count_profit_amount
      FROM root_statisticsbonusprofit WHERE dailydate_start >= '$current_datepicker_start' AND dailydate_end <= '$current_datepicker_end' AND profitaccount_2= '".$b['member_account']."' ;";
      if($argv_check == 'test') {
        print_r($member_profitamount_sql_2);
      }

      $member_profitamount_result_2 = runSQLall($member_profitamount_sql_2);
      // var_dump($member_profitamount_result_2);
      if($member_profitamount_result_2[0] == 1) {
        if($member_profitamount_result_2[1]->sum_profit_amount == NULL) {
          $b['member_profitamount_2'] = 0;
        }else{
          $b['member_profitamount_2'] = $member_profitamount_result_2[1]->sum_profit_amount;
        }
        $b['member_profitamount_count_2'] =$member_profitamount_result_2[1]->count_profit_amount;
        //var_dump($member_profitamount_sql_2);
        //var_dump($member_profitamount_result_2);
      }else{
        $logger ='[BE4002]資料庫存取錯誤, 請聯絡管理人員處理. in '.$b['member_account'].'date:'.$current_datepicker_start.'~'.$current_datepicker_end;
        var_dump($logger);
        memberlog2db($_SESSION['agent']->account, 'bonus profit', 'error', "$logger");
      }

      // 分潤第3代
      $member_profitamount_sql_3 = "SELECT sum(profit_amount_3) as sum_profit_amount, count(profit_amount_3) as count_profit_amount
      FROM root_statisticsbonusprofit WHERE dailydate_start >= '$current_datepicker_start' AND dailydate_end <= '$current_datepicker_end' AND profitaccount_3= '".$b['member_account']."' ;";
      if($argv_check == 'test') {
        print_r($member_profitamount_sql_3);
      }

      $member_profitamount_result_3 = runSQLall($member_profitamount_sql_3);
      if($member_profitamount_result_3[0] == 1) {
        if($member_profitamount_result_3[1]->sum_profit_amount == NULL) {
          $b['member_profitamount_3'] = 0;
        }else{
          $b['member_profitamount_3'] = $member_profitamount_result_3[1]->sum_profit_amount;
        }
        $b['member_profitamount_count_3'] =$member_profitamount_result_3[1]->count_profit_amount;
        //var_dump($member_profitamount_sql_3);
        // var_dump($member_profitamount_result_3);
      }else{
        $logger ='[BE4003]資料庫存取錯誤, 請聯絡管理人員處理. in '.$b['member_account'].'date:'.$current_datepicker_start.'~'.$current_datepicker_end;
        var_dump($logger);
        memberlog2db($_SESSION['agent']->account, 'bonus profit', 'error', "$logger");
      }

      // 分潤第4代
      $member_profitamount_sql_4 = "SELECT sum(profit_amount_4) as sum_profit_amount, count(profit_amount_4) as count_profit_amount
      FROM root_statisticsbonusprofit WHERE dailydate_start >= '$current_datepicker_start' AND dailydate_end <= '$current_datepicker_end' AND profitaccount_4= '".$b['member_account']."' ;";
      if($argv_check == 'test') {
        print_r($member_profitamount_sql_4);
      }

      $member_profitamount_result_4 = runSQLall($member_profitamount_sql_4);
      //var_dump($member_profitamount_result_4);
      if($member_profitamount_result_4[0] == 1) {
        if($member_profitamount_result_4[1]->sum_profit_amount == NULL) {
          $b['member_profitamount_4'] = 0;
        }else{
          $b['member_profitamount_4'] = $member_profitamount_result_4[1]->sum_profit_amount;
        }
        $b['member_profitamount_count_4'] =$member_profitamount_result_4[1]->count_profit_amount;
        //var_dump($member_profitamount_sql_4);
        //var_dump($member_profitamount_result_4);
      }else{
        $logger ='[BE4004]資料庫存取錯誤, 請聯絡管理人員處理. in '.$b['member_account'].'date:'.$current_datepicker_start.'~'.$current_datepicker_end;
        var_dump($logger);
        memberlog2db($_SESSION['agent']->account, 'bonus profit', 'error', "$logger");
      }

      // 分潤總和
      $b['member_profitamount'] = $b['member_profitamount_1'] + $b['member_profitamount_2'] + $b['member_profitamount_3'] + $b['member_profitamount_4'];

      // 分潤總筆數
      $b['member_profitamount_count'] = $b['member_profitamount_count_1'] + $b['member_profitamount_count_2'] + $b['member_profitamount_count_3'] + $b['member_profitamount_count_4'];

      // 如果沒有資料的話, 日期為空. 上個月沒有分潤資料則為空值也不會友日期, 全部給預設值 0
      if($pre_dailyrange != false) {
        // 上個月留抵 -- 搜尋上個月付款的資料, 如果付款為負, 紀錄在本月的此欄位上面. 當要手工付款時, 檢查 總分潤 - 上月留抵 是否大於0 , 如果大於 0 才發放現金
        $lasttime_member_profitamount_sql = "SELECT * FROM root_statisticsbonusprofit
        WHERE dailydate_start = '".$pre_dailyrange['pre_dailydate_start']."' AND dailydate_end = '".$pre_dailyrange['pre_dailydate_end']."' AND member_profitamount_paid < '0' AND member_account = '".$b['member_account']."';";
        if($argv_check == 'test') {
          var_dump($lasttime_member_profitamount_sql);
        }
        // 取出上各月的分潤資料
        $lasttime_member_profitamount_result = runSQLall($lasttime_member_profitamount_sql);
        // 有資料, 上月付款額小於 0 的話
        if($lasttime_member_profitamount_result[0] > 0) {
          $b['lasttime_stayindebt'] = $lasttime_member_profitamount_result[1]->member_profitamount_paid;
          //var_dump($lasttime_member_profitamount_sql);
          //var_dump($lasttime_member_profitamount_result);
        }else{
          $b['lasttime_stayindebt'] = 0;
        }
        // 這個值不異變動, 每次 2 次統計時重新寫入.
      }else{
        // 上個沒有存在, 設為 0
        $b['lasttime_stayindebt'] = 0;
      }


      // 付款金額, 如果本月的結算 member_profitamount 為負值的話, 記帳在 member_profitamount_paid 欄位上面. 表達為負值(因為不轉帳,也不扣帳)
      // 把上月留抵 + 本月分潤 , 如果還是 < 0 的時候, 寫入 $b['member_profitamount_paid']
      if(($b['lasttime_stayindebt'] + $b['member_profitamount']) < 0) {
        $b['member_profitamount_paid'] = ($b['lasttime_stayindebt'] + $b['member_profitamount']);
        $member_profitamount_paid_sql = "member_profitamount_paid   = '".$b['member_profitamount_paid']."', ";
      }else{
        // 如果沒有小於 0  , 就啥也不動作. 不更新該欄位. 因為可能會有手動的紀錄產生.
        $member_profitamount_paid_sql = '';
      }
      //var_dump($member_profitamount_paid_sql);


      // 將第二次運算後的資料  update to sql
      $update_profit_sql = "UPDATE root_statisticsbonusprofit SET
      updatetime = now(),
      member_profitamount_1 = '".$b['member_profitamount_1']."',
      member_profitamount_2 = '".$b['member_profitamount_2']."',
      member_profitamount_3 = '".$b['member_profitamount_3']."',
      member_profitamount_4 = '".$b['member_profitamount_4']."',
      member_profitamount   = '".$b['member_profitamount']."',
      member_profitamount_count_1 = '".$b['member_profitamount_count_1']."',
      member_profitamount_count_2 = '".$b['member_profitamount_count_2']."',
      member_profitamount_count_3 = '".$b['member_profitamount_count_3']."',
      member_profitamount_count_4 = '".$b['member_profitamount_count_4']."',
      member_profitamount_count   = '".$b['member_profitamount_count']."',
      ".$member_profitamount_paid_sql."
      lasttime_stayindebt         = '".$b['lasttime_stayindebt']."'
      WHERE member_account = '".$b['member_account']."' AND dailydate_start = '".$b['dailydate_start']."' AND dailydate_end = '".$b['dailydate_end']."';
      ";

      if($argv_check == 'test'){
        echo $update_profit_sql;
				$update_profit_result[0] = 1;
      }elseif($argv_check == 'run'){
        $update_profit_result = runSQLall($update_profit_sql);
      }
      $stats_bonusamount_count++;

			// ------- bonus update log ------------------------
			// 顯示目前的處理紀錄進度，及花費的時間。 換算進度 %
			$percentage_html     = round(($i/$userlist[0]),2)*100;
			$process_record_html = "$i/$userlist[0]";
			$process_times_html  = round((microtime(true) - $program_start_time),3);
			$counting_r = $percentage_html%10;

			if($web_check == 1 AND $counting_r == 0){
				$output_sub_html  = $output_html.'<p align="center">'.$percentage_html.'%</p>';
				file_put_contents($reload_file,$output_sub_html);
			}elseif($web_check == 2 AND $counting_r == 0){
			  $updatelog_sql = 'UPDATE root_bonusupdatelog SET bonus_status = \''.$percentage_html.'\', note = \''.$updatlog_note.'\' WHERE id = \''.$updatelog_id.'\';';
			  if($argv_check == 'test'){
			    echo $updatelog_sql;
			  }elseif($argv_check == 'run'){
			    $updatelog_result = runSQLall($updatelog_sql);
			  }
			}elseif($web_check == 0){
				if($percentage_html != $percentage_current) {
					if($counting_r == 0) {
						echo "\n目前處理 $current_datepicker_end 紀錄: $process_record_html ,執行進度: $percentage_html% ,花費時間: ".$process_times_html."秒\n";
					}else{
						echo $percentage_html.'% ';
					}
					$percentage_current = $percentage_html;
				}
			}
			// -------------------------------------------------
      if($update_profit_result[0] == 1) {
        $logger = '更新第二次運算後的資料成功'.$b['member_account'].','.$b['dailydate_start'].','.$b['dailydate_end'];
        // echo $logger;
      }else{
        $logger = '更新第二次運算後的資料失敗'.$b['member_account'].','.$b['dailydate_start'].','.$b['dailydate_end'];
        // echo $logger;
      }

      // -------------------------------------------------------------------
      // 第二次加總計算 END
      // -------------------------------------------------------------------

      // 沒有加總計算才產生 CSV 內容

    }
  }
}

// ----------------------------------------------------------------------------
// round 3. 輸出 CSV 檔
// ----------------------------------------------------------------------------
if($web_check == 1){
	$output_html  = '<p align="center">round 3. 輸出 CSV 檔 - 更新中...<img src="ui/loading.gif" /></p><script>setTimeout(function(){location.reload()},1000);</script>';
	file_put_contents($reload_file,$output_html);
}elseif($web_check == 2){
	$updatlog_note = 'round 3. 輸出 CSV 檔 - 更新中';
	$updatelog_sql = 'UPDATE root_bonusupdatelog SET bonus_status = \'0\', note = \''.$updatlog_note.'\' WHERE id = \''.$updatelog_id.'\';';
	if($argv_check == 'test'){
		echo $updatelog_sql;
	}elseif($argv_check == 'run'){
		$updatelog_result = runSQLall($updatelog_sql);
	}
}else{
	echo "round 3. 輸出 CSV 檔 - 開始\n";
}
// -----------------------------------------------------------------------
// 列出所有的會員資料及人數 SQL
// -----------------------------------------------------------------------
// 算 root_member 人數
$userlist_sql = "SELECT * FROM root_statisticsbonusprofit
WHERE dailydate_start = '$current_datepicker_start' AND dailydate_end = '$current_datepicker_end' ORDER BY member_id ASC;";
// var_dump($userlist_sql);
$userlist = runSQLall($userlist_sql);
$userlist_count = $userlist[0];

// 處理進度 % , 用來顯示紀錄進度。
$percentage_current = 0;
// 判斷 root_member count 數量大於 1
if($userlist[0] >= 1) {
  // 以會員為主要 key 依序列出每個會員的貢獻金額
  for($i = 1 ; $i <= $userlist[0]; $i++){
    // 存成 csv data
    $j = 0;
    $csv_data['data'][$i][$j++] = $current_datepicker_start;
    $csv_data['data'][$i][$j++] = $current_datepicker_end;
    $csv_data['data'][$i][$j++] = $userlist[$i]->member_id;
    $csv_data['data'][$i][$j++] = $userlist[$i]->member_parent_id;
    $csv_data['data'][$i][$j++] = $userlist[$i]->member_therole;
    $csv_data['data'][$i][$j++] = $userlist[$i]->member_account;
    $csv_data['data'][$i][$j++] = $userlist[$i]->id;
    $csv_data['data'][$i][$j++] = $userlist[$i]->updatetime;
    $csv_data['data'][$i][$j++] = $userlist[$i]->member_level;

    $csv_data['data'][$i][$j++] = $userlist[$i]->skip_bonusinfo;
    $skip_bonusinfo_count     = explode(":",$b['skip_bonusinfo']);
    $csv_data['data'][$i][$j++] = $skip_bonusinfo_count[0];
    $csv_data['data'][$i][$j++] = $userlist[$i]->profitaccount_1;
    $csv_data['data'][$i][$j++] = $userlist[$i]->profitaccount_2;
    $csv_data['data'][$i][$j++] = $userlist[$i]->profitaccount_3;
    $csv_data['data'][$i][$j++] = $userlist[$i]->profitaccount_4;
    $csv_data['data'][$i][$j++] = $userlist[$i]->profit_amount;
    $csv_data['data'][$i][$j++] = $userlist[$i]->profit_amount_1;
    $csv_data['data'][$i][$j++] = $userlist[$i]->profit_amount_2;
    $csv_data['data'][$i][$j++] = $userlist[$i]->profit_amount_3;
    $csv_data['data'][$i][$j++] = $userlist[$i]->profit_amount_4;

    $csv_data['data'][$i][$j++] = $userlist[$i]->member_profitlost_cashcost;
    $csv_data['data'][$i][$j++] = $userlist[$i]->member_profitlost_marketingcost;
    $csv_data['data'][$i][$j++] = $userlist[$i]->sum_tokenfavorable;
    $csv_data['data'][$i][$j++] = $userlist[$i]->sum_tokenpreferential;
    $csv_data['data'][$i][$j++] = $userlist[$i]->member_profitlost_platformcost;
    $csv_data['data'][$i][$j++] = $userlist[$i]->days_count;
    $csv_data['data'][$i][$j++] = $userlist[$i]->sum_all_count;
    $csv_data['data'][$i][$j++] = $userlist[$i]->sum_all_bets;
    $csv_data['data'][$i][$j++] = $userlist[$i]->sum_all_wins;
    $csv_data['data'][$i][$j++] = $userlist[$i]->sum_all_profitlost;

    $csv_data['data'][$i][$j++] = $userlist[$i]->member_profitamount_count_1;
    $csv_data['data'][$i][$j++] = $userlist[$i]->member_profitamount_1;
    $csv_data['data'][$i][$j++] = $userlist[$i]->member_profitamount_count_2;
    $csv_data['data'][$i][$j++] = $userlist[$i]->member_profitamount_2;
    $csv_data['data'][$i][$j++] = $userlist[$i]->member_profitamount_count_3;
    $csv_data['data'][$i][$j++] = $userlist[$i]->member_profitamount_3;
    $csv_data['data'][$i][$j++] = $userlist[$i]->member_profitamount_count_4;
    $csv_data['data'][$i][$j++] = $userlist[$i]->member_profitamount_4;
    $csv_data['data'][$i][$j++] = $userlist[$i]->member_profitamount_count;
    $csv_data['data'][$i][$j++] = $userlist[$i]->member_profitamount;
    $csv_data['data'][$i][$j++] = $userlist[$i]->member_profitamount_paid;
    $csv_data['data'][$i][$j++] = $userlist[$i]->member_profitamount_paidtime;
    $csv_data['data'][$i][$j++] = $userlist[$i]->lasttime_stayindebt;
    $csv_data['data'][$i][$j++] = $userlist[$i]->notes;


		// ------- bonus update log ------------------------
		// 顯示目前的處理紀錄進度，及花費的時間。 換算進度 %
		$percentage_html     = round(($i/$userlist[0]),2)*100;
		$process_record_html = "$i/$userlist[0]";
		$process_times_html  = round((microtime(true) - $program_start_time),3);
		$counting_r = $percentage_html%10;

		if($web_check == 1 AND $counting_r == 0){
			$output_sub_html  = $output_html.'<p align="center">'.$percentage_html.'%</p>';
			file_put_contents($reload_file,$output_sub_html);
		}elseif($web_check == 2 AND $counting_r == 0){
		  $updatelog_sql = 'UPDATE root_bonusupdatelog SET bonus_status = \''.$percentage_html.'\', note = \''.$updatlog_note.'\' WHERE id = \''.$updatelog_id.'\';';
		  if($argv_check == 'test'){
		    echo $updatelog_sql;
		  }elseif($argv_check == 'run'){
		    $updatelog_result = runSQLall($updatelog_sql);
		  }
		}elseif($web_check == 0){
			if($percentage_html != $percentage_current) {
				if($counting_r == 0) {
					echo "\n目前處理 $current_datepicker_end 紀錄: $process_record_html ,執行進度: $percentage_html% ,花費時間: ".$process_times_html."秒\n";
				}else{
					echo $percentage_html.'% ';
				}
				$percentage_current = $percentage_html;
			}
		}
		// -------------------------------------------------
  }
  // -------------------------------------------
  // 寫入 CSV 檔案的抬頭 - -和實際的 table 並沒有完全的對應
  // -------------------------------------------

  $j = 0;
  $csv_data['table_colname'][$j++] = '統計開始時間';
  $csv_data['table_colname'][$j++] = '統計結束時間';
  $csv_data['table_colname'][$j++] = '會員ID';
  $csv_data['table_colname'][$j++] = '會員上層ID';
  $csv_data['table_colname'][$j++] = '會員身份';
  $csv_data['table_colname'][$j++] = '會員帳號';
  $csv_data['table_colname'][$j++] = 'ID_PK';
  $csv_data['table_colname'][$j++] = '最後更新時間';
  $csv_data['table_colname'][$j++] = '所在層數';

  $csv_data['table_colname'][$j++] = '被跳過得代理資訊';
  $csv_data['table_colname'][$j++] = '被跳過得代理商數量';
  $csv_data['table_colname'][$j++] = '達成業績第1代';
  $csv_data['table_colname'][$j++] = '達成業績第2代';
  $csv_data['table_colname'][$j++] = '達成業績第3代';
  $csv_data['table_colname'][$j++] = '達成業績第4代';
  $csv_data['table_colname'][$j++] = '個人貢獻平台的損益';
  $csv_data['table_colname'][$j++] = '第1代分紅';
  $csv_data['table_colname'][$j++] = '第2代分紅';
  $csv_data['table_colname'][$j++] = '第3代分紅';
  $csv_data['table_colname'][$j++] = '第4代分紅';

  $csv_data['table_colname'][$j++] = '金流成本';
  $csv_data['table_colname'][$j++] = '行銷成本 = (優惠金額 + 反水金額)';
  $csv_data['table_colname'][$j++] = '優惠成本';
  $csv_data['table_colname'][$j++] = '反水成本';
  $csv_data['table_colname'][$j++] = '平台成本';
  $csv_data['table_colname'][$j++] = '此紀錄累積統計的天數(日報表資料筆數)';
  $csv_data['table_colname'][$j++] = '會員注單量';
  $csv_data['table_colname'][$j++] = '全部的投注金額';
  $csv_data['table_colname'][$j++] = '全部的派彩金額';
  $csv_data['table_colname'][$j++] = '全部的損益金額(未扣成本)';

  $csv_data['table_colname'][$j++] = '(2nd)代理商本月第1代分潤來源筆數';
  $csv_data['table_colname'][$j++] = '(2nd)代理商本月第1代分潤';
  $csv_data['table_colname'][$j++] = '(2nd)代理商本月第2代分潤來源筆數';
  $csv_data['table_colname'][$j++] = '(2nd)代理商本月第2代分潤';
  $csv_data['table_colname'][$j++] = '(2nd)代理商本月第3代分潤來源筆數';
  $csv_data['table_colname'][$j++] = '(2nd)代理商本月第3代分潤';
  $csv_data['table_colname'][$j++] = '(2nd)代理商本月第4代分潤來源筆數';
  $csv_data['table_colname'][$j++] = '(2nd)代理商本月第4代分潤';
  $csv_data['table_colname'][$j++] = '(2nd)代理商本月分潤來源總筆數';
  $csv_data['table_colname'][$j++] = '(2nd)代理商本月分潤總和';
  $csv_data['table_colname'][$j++] = '(2nd)代理商的分潤付款(負值帳號為留抵下次扣除)';
  $csv_data['table_colname'][$j++] = '(2nd)代理商本月分潤付款時間';
  $csv_data['table_colname'][$j++] = '(2nd)代理商會員個人上月留抵負債(下月計算時扣除)';
  $csv_data['table_colname'][$j++] = '備註';
  // var_dump($csv_data);

  //var_dump($csv_data['table_colname']);
  //var_dump($csv_data['data'][1]);
  //var_dump($csv_data['data'][2]);

  // -------------------------------------------
  // 將內容輸出到 檔案 , csv format
  // -------------------------------------------

  // 有資料才執行 csv 輸出, 避免 insert or update or stats 生成同時也執行 csv 輸出
  if(isset($csv_data['data'])) {
    $filename      = "bonusprofit_result_".$current_datepicker_start.'_'.$current_datepicker_end.'.csv';
    $absfilename   = dirname(__FILE__) ."/tmp_dl/$filename";
    $filehandle    = fopen("$absfilename","w");
    // Windows下使用BOM来标记文本文件的编码方式, 否則 EXCEL 開啟這個檔案會是亂碼
    fwrite($filehandle,chr(0xEF).chr(0xBB).chr(0xBF));
    // -------------------------------------------
    // 將資料輸出到檔案 -- Summary
    //fputcsv($filehandle, $csv_data['summary_title']);
    //fputcsv($filehandle, $csv_data['summary']);

    // 將資料輸出到檔案 -- Title
    fputcsv($filehandle, $csv_data['table_colname']);

    // 將資料輸出到檔案 -- data
		foreach ($csv_data['data'] as $wline) {
			fputcsv($filehandle, $wline);
		}

    fclose($filehandle);
  }
}

// --------------------------------------------
// MAIN END
// --------------------------------------------

// ----------------------------------------------------------------------------
// 統計結果
// ----------------------------------------------------------------------------
$run_report_result = "
  統計顯示的資料 =  $stats_showdata_count ,\n
  統計此時間區間插入(Insert)的資料 =  $stats_insert_count ,\n
  統計營運利潤獎金投注量資料更新(Update)   =  $stats_update_count ,\n
  統計個人營運利潤獎金更新(Update) =  $stats_bonusamount_count";

// 算累積花費時間
$program_end_time =  microtime(true);
$program_time = $program_end_time-$program_start_time;
$logger = $run_report_result."\n累積花費時間: ".$program_time ." \n";
if($web_check == 1){

	$dellogfile_js = '
	<script src="in/jquery/jquery.min.js"></script>
	<script type="text/javascript" language="javascript" class="init">
	function dellogfile(){
		$.get("bonus_commission_profit_action.php?a=profitbonus_del&k='.$file_key.'",
		function(result){
			window.close();
		});
	}
	</script>';

	$logger_html = nl2br($logger).'<br><br><p align="center"><button type="button" onclick="dellogfile();">關閉視窗</button></p><script>alert(\'已完成資料更新！\');</script>'.$dellogfile_js;
	file_put_contents($reload_file,$logger_html);
}elseif($web_check == 2){
	$updatlog_note = nl2br($logger);
	$updatelog_sql = 'UPDATE root_bonusupdatelog SET bonus_status = \'1000\', note = \''.$updatlog_note.'\' WHERE id = \''.$updatelog_id.'\';';
	if($argv_check == 'test'){
		echo $updatelog_sql;
	}elseif($argv_check == 'run'){
		$updatelog_result = runSQLall($updatelog_sql);
	}
}else{
	echo $logger;
}
// --------------------------------------------
// 統計結果 END
// --------------------------------------------

?>
