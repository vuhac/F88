<?php
// ----------------------------------------------------------------------------
// Features :	後台 -- 聯營股東損益計算 LIB
// File Name: agent_profitloss_calculation_lib.php
// Author   :
// Related  :
// Log      :
// ----------------------------------------------------------------------------
// 對應資料表
// 相關的檔案
// 功能說明
// 1.透過每日報表資料, 計算統計出每日的個節點營利損益狀態
// 2.依據分用比例, 從上到下分配營利的盈餘, 以每日為單位。
// 3.加總指定區間的資料, 成為個節點的每日損益狀態.
// 4.每月分配股東的損益到獎金分發的表格
// update   : yyyy.mm.dd


// ---------------------------------------------------------------
// check date format
// ---------------------------------------------------------------
// get example: ?current_datepicker=2017-02-03
// ref: http://php.net/manual/en/function.checkdate.php
function validateDate($date, $format = 'Y-m-d H:i:s'){
    $d = DateTime::createFromFormat($format, $date);
    return $d && $d->format($format) == $date;
}

// 撈出日報--每個會員投注相關資料
function statisticsdailyreport_sum_list($sdate,$edate,$casino_game_categories){
  $transfertime_begin = gmdate('Y-m-d H:i:s.u', strtotime($sdate.' 00:00:00 -04') + 8*3600 ).'+08:00';
  $transfertime_end = gmdate('Y-m-d H:i:s.u', strtotime($edate.' 23:59:59 -04') + 8*3600 ).'+08:00';

  $statistics_daily_report_list = [];

  $casino_attributes_sql = '';

  foreach($casino_game_categories as $casino => $categories) {
    $casino_attributes_sql .= "coalesce( SUM( (root_statisticsdailyreport.betlog_detail->>'" . $casino . '_bets' . "') :: numeric(20,2)) , 0) as " . $casino . '_bets' . ",";
    $casino_attributes_sql .= "coalesce( SUM( (root_statisticsdailyreport.betlog_detail->>'" . $casino . '_wins' . "') :: numeric(20,2)) , 0) as " . $casino . '_wins' . ",";
    $casino_attributes_sql .= "coalesce( SUM( (root_statisticsdailyreport.betlog_detail->>'" . $casino . '_profitlost' . "') :: numeric(20,2)) , 0) as " . $casino . '_profitlost' . ",";
    $casino_attributes_sql .= "coalesce( SUM( (root_statisticsdailyreport.betlog_detail->>'" . $casino . '_count' . "') :: numeric(20,2)) , 0) as " . $casino . '_count' . ",";

    foreach ($categories as $category) {
      $casino_category = $casino . '_' . $category;

      $casino_attributes_sql .= "coalesce( SUM( (root_statisticsdailyreport.betlog_detail->>'" . $casino_category . '_bets' . "') :: numeric(20,2)) , 0) as " . $casino_category . '_bets' . ",";
      $casino_attributes_sql .= "coalesce( SUM( (root_statisticsdailyreport.betlog_detail->>'" . $casino_category . '_wins' . "') :: numeric(20,2)) , 0) as " . $casino_category . '_wins' . ",";
      $casino_attributes_sql .= "coalesce( SUM( (root_statisticsdailyreport.betlog_detail->>'" . $casino_category . '_profitlost' . "') :: numeric(20,2)) , 0) as " . $casino_category . '_profitlost' . ",";
      $casino_attributes_sql .= "coalesce( SUM( (root_statisticsdailyreport.betlog_detail->>'" . $casino_category . '_count' . "') :: numeric(20,2)) , 0) as " . $casino_category . '_count' . ",";
    }
  }

    $sql = <<<SQL
      SELECT
        MAX(root_statisticsdailyreport.id) as id,
        MAX(root_statisticsdailyreport.member_id) as member_id,
        MAX(root_statisticsdailyreport.member_therole) as member_therole,
        MAX(root_statisticsdailyreport.member_account) as member_account,
        MAX(root_statisticsdailyreport.member_parent_id) as member_parent_id,
        MAX(root_statisticsdailyreport.dailydate) as dailydate,
        MAX(root_member.account) as parent_account,

        SUM(root_statisticsdailyreport.tokendeposit
        + root_statisticsdailyreport.cashdeposit
        + root_statisticsdailyreport.payonlinedeposit
        + root_statisticsdailyreport.apicashdeposit
        + root_statisticsdailyreport.apitokendeposit
        + root_statisticsdailyreport.company_deposits) as all_deposit,
        $casino_attributes_sql
        SUM(root_statisticsdailyreport.all_bets) as all_bets,
        SUM(root_statisticsdailyreport.all_wins) as all_wins,
        SUM(root_statisticsdailyreport.all_profitlost) as all_profitlost
        FROM root_statisticsdailyreport
        JOIN root_member ON root_statisticsdailyreport.member_parent_id = root_member.id
        WHERE dailydate >= '$sdate'
        AND dailydate <= '$edate'
        GROUP BY root_statisticsdailyreport.member_id
        ORDER BY member_parent_id , member_account
SQL;
// echo($sql);die();
  return runSQLall($sql);
}

// 取得佣金規則
function get_commission_rules(){
    // query commission rules
    $commission_rules_sql        = "SELECT * FROM root_commission WHERE status='1' AND deleted='0' ORDER BY name ASC, downline_effective_bet DESC;";
    // echo $commission_rules_sql;die();
    $commission_rules_sql_result = runSQLall($commission_rules_sql);
    // var_dump($commission_rules_sql_result);die();
    $commission_rules = [];

    // construct commission rules mapping
    foreach ($commission_rules_sql_result as $index => $rule) {
        if ($index == 0) {
            continue;
        }
        $commission_rules[$rule->name][$rule->downline_effective_bet] = [
            'lowest_bet'       => $rule->lowest_bet,
            'lowest_deposit'   => $rule->lowest_deposit,
            'payoff'           => $rule->payoff,
            'effective_member' => $rule->effective_member,
            'commission'       => json_decode($rule->commission, true),
            'downline_deposit' => $rule->downline_deposit,
        ];
    }
    // var_dump($commission_rules);die();

    return $commission_rules;
}



// 算出代理商所有下線的總投注量、總損益
function downline_allbet($sdate, $edate){
    $statistics_daily_report_list = [];

    $sql = <<<SQL
      SELECT
        MAX(root_statisticsdailyreport.member_parent_id) as agent_id,
        MAX(root_statisticsdailyreport.dailydate) as dailydate,
        MAX(root_member.account) as agent_account,
        MAX(root_member.therole) as agent_therole,
        MAX(root_member.commissionrule) as agent_commissionrule,

        SUM(root_statisticsdailyreport.all_bets) as all_bets,
        SUM(root_statisticsdailyreport.all_wins) as all_wins,
        SUM(root_statisticsdailyreport.all_profitlost) as all_profitlost

      FROM root_statisticsdailyreport
      JOIN root_member
        ON root_statisticsdailyreport.member_parent_id = root_member.id
      WHERE dailydate >= '$sdate'
        AND dailydate <= '$edate'
        AND all_bets  > 0
        AND root_member.therole='A'
      GROUP BY root_statisticsdailyreport.member_parent_id
      ORDER BY root_statisticsdailyreport.member_parent_id
SQL;
    // echo($sql);die();
    return runSQLall($sql);
}

// 對映代理商的佣金等級，參數1：代理商下線總投注及佣金名稱，參數2:佣金設定
function map_agent_commission_grade($downline_allbet,$commission_rules){
  $summary_table=$downline_effective_bet=[];
  foreach ($downline_allbet as $agent_value){
    $bet_ok = 0;
    foreach ($commission_rules[$agent_value->agent_commissionrule] as $commission_bet=>$commission_setting ){
      if($agent_value->all_bets >= $commission_bet){
        $bet_ok = 1;
        $downline_effective_bet=[
              'downline_effective_bet'=>$commission_bet,
              'reach_bet_amount'      =>'t',
                                ];
        $summary_table[$agent_value->agent_id]=json_decode(json_encode($agent_value), true)+$commission_setting+$downline_effective_bet;
        break;
      }else{
        continue;
      }
    }
    // do未達打碼量
    if($bet_ok==0){
        $downline_effective_bet = [
              'downline_effective_bet' =>'-1',
              'reach_bet_amount'       =>'f'
                                  ];
        $summary_table[$agent_value->agent_id] = json_decode(json_encode($agent_value), true) + $commission_setting + $downline_effective_bet;
    }
  }
  // var_dump($summary_table);die();
  return $summary_table;
}

// 計算佣金明細及總表
function calculate_total_commission($statistics_daily_report_list,$map_agent_commission_grade,$casino_game_categories){
  // var_dump($casino_game_categories);die();
  $valid_memeber='';
  $summary_tbl=$detail_tbl=$bet_detail_tmp=$return_tbl = [];

  unset($statistics_daily_report_list[0]);

  // 每位會員，開始跑佣金流程
  foreach($statistics_daily_report_list as $s_key => $s_value){
    // 若有佣金設定資料，才做以下。會員的上層代理商A，A的下線總投注為0，則不會有A的佣金設定資料。
    if(array_key_exists($s_value->member_parent_id,$map_agent_commission_grade)){
      // 判斷有效會員
      $valid_memeber=judging_valid_members($s_value->all_bets,$s_value->all_deposit,$map_agent_commission_grade[$s_value->member_parent_id]);
      // 假如為有效會員，則開始計算佣金
      if($valid_memeber=='1'){
        // 佣金加總欄位：有效會員進行運算時，先將佣金加總欄位歸零
        $valid_bet_comsion_sum=$member_commission=0;
        // 索引號碼判別
        $i = 0;
        if(isset($detail_tbl[$s_value->member_parent_id]) AND count($detail_tbl[$s_value->member_parent_id])>=1){
          $i=count($detail_tbl[$s_value->member_parent_id]);
        }

        // 寫入明細表
        // 會員基本資訊
        $detail_tbl[$s_value->member_parent_id][$i]['member_id']              = $s_value->member_id;
        $detail_tbl[$s_value->member_parent_id][$i]['member_therole']         = $s_value->member_therole;
        $detail_tbl[$s_value->member_parent_id][$i]['member_account']         = $s_value->member_account;
        $detail_tbl[$s_value->member_parent_id][$i]['parent_account']         = $s_value->parent_account;
        $detail_tbl[$s_value->member_parent_id][$i]['parent_id']              = $s_value->member_parent_id;
        $detail_tbl[$s_value->member_parent_id][$i]['parent_therole']         = $map_agent_commission_grade[$s_value->member_parent_id]['agent_therole'];
        $detail_tbl[$s_value->member_parent_id][$i]['agent_commissionrule']   = $map_agent_commission_grade[$s_value->member_parent_id]['agent_commissionrule'];
        $detail_tbl[$s_value->member_parent_id][$i]['downline_effective_bet'] = $map_agent_commission_grade[$s_value->member_parent_id]['downline_effective_bet'];
        $detail_tbl[$s_value->member_parent_id][$i]['reach_bet_amount']       = $map_agent_commission_grade[$s_value->member_parent_id]['reach_bet_amount'];

        // 有效投注、損益
        $detail_tbl[$s_value->member_parent_id][$i]['member_bets']       = $s_value->all_bets;
        $detail_tbl[$s_value->member_parent_id][$i]['member_profitlost'] = $s_value->all_profitlost;

        // 存款、存款佣金退佣比
        $detail_tbl[$s_value->member_parent_id][$i]['all_deposit']         = $s_value->all_deposit;
        $detail_tbl[$s_value->member_parent_id][$i]['deposit_comsion_set'] = $map_agent_commission_grade[$s_value->member_parent_id]['downline_deposit'];
        // 存款佣金計算
        if($map_agent_commission_grade[$s_value->member_parent_id]["reach_bet_amount"]=='f'){
          $detail_tbl[$s_value->member_parent_id][$i]['deposit_comsion'] = number_format(0, 2, '.', '');
        }else{
          $detail_tbl[$s_value->member_parent_id][$i]['deposit_comsion'] = number_format(($s_value->all_deposit * $map_agent_commission_grade[$s_value->member_parent_id]['downline_deposit']), 2, '.', '');
        }
        // 取出娛樂城之遊戲類別
        foreach($casino_game_categories as $casino_name => $game_cates){
          foreach($game_cates as $game_cate_value){
            $ca_na_ga_ct='';
            $ca_na_ga_ct=$casino_name.'_'.$game_cate_value.'_bets';

            // pt_game_bets，會員遊戲分類投注量
            $member_bet=$s_value->$ca_na_ga_ct;
            // var_dump($s_value);die();

            // 如果佣金設定的反水分類有錯或新遊戲尚未至佣金設定，導致找不到佣金設定值，可開啟以下註解，方便除錯
            // if(!isset($map_agent_commission_grade[$s_value->member_parent_id]['commission'][strtoupper($casino_name)][$game_cate_value ])){
            //     var_dump('使用者：'.$s_value->member_account.'，上層代理商：'.$s_value->parent_account.'，之佣金設定為：'.$map_agent_commission_grade[$s_value->member_parent_id]["agent_commissionrule"].'，下线全有效会员最低投注额：'.$map_agent_commission_grade[$s_value->member_parent_id]["downline_effective_bet"].'，缺少娛樂城：'.$casino_name.'，遊戲分類：'.$game_cate_value.'的佣金設定。');
            // }

            // 不同娛樂城不同遊戲分類之佣金設定
            $member_commission_set=number_format(($map_agent_commission_grade[$s_value->member_parent_id]['commission'][strtoupper($casino_name)][$game_cate_value ]??0)/100,4,'.','');

            // 各分類遊戲佣金
            if ($map_agent_commission_grade[$s_value->member_parent_id]["reach_bet_amount"] == 'f') {
                $member_commission = number_format(0, 2, '.', '');
            } else {
                $member_commission=number_format($member_bet*$member_commission_set,2,'.','');
            }

            // 會員投注、佣金設定、投注所得佣金
            $bet_detail_tmp[$ca_na_ga_ct]                     = $member_bet;
            $bet_detail_tmp[$ca_na_ga_ct . '_commission_set'] = $member_commission_set;
            $bet_detail_tmp[$ca_na_ga_ct . '_commission']     = $member_commission;

            $valid_bet_comsion_sum+=$member_commission;
          }
        }
        $detail_tbl[$s_value->member_parent_id][$i]['valid_bet_comsion_sum'] = $valid_bet_comsion_sum;
        $detail_tbl[$s_value->member_parent_id][$i]['commission_detail']     = json_encode($bet_detail_tmp);
      }
    }else{
      continue;
    }
  }
  // var_dump($detail_tbl);die();

  // -------------------------佣金總表計算開始-----------------------------------------
  foreach($detail_tbl as $parent_id => $all_downline_data){
    // var_dump($all_downline_data);die();
    // 1.投注佣金+   2.存款佣金=           3.總佣金，        4.有效投注加總，5.損益加總，    6.有效會員。   6個變數預設0
    $bet_comsion_sum=$deposit_comsion_sum=$all_comsion_sum=$valid_bet_sum=$profitlost_sum=$valid_member_sum=0;
    foreach($all_downline_data as $single_member_data){
      // 投注佣金加總
      $bet_comsion_sum     += $single_member_data['valid_bet_comsion_sum'];
      // 存款佣金加總
      $deposit_comsion_sum += $single_member_data['deposit_comsion'];
      // 有效投注加總
      $valid_bet_sum       += $single_member_data['member_bets'];
      // 損益加總
      $profitlost_sum      += $single_member_data['member_profitlost'];
      // 有效會員累加
      $valid_member_sum++;

    }
    // 有效會員門檻
    $effective_member_set=$map_agent_commission_grade[$all_downline_data[0]['parent_id']]["effective_member"];
    // 判斷是否通過有效會員門檻
    if($valid_member_sum>=$effective_member_set){
        // 佣金加總=投注佣金+存款佣金
        $all_comsion_sum = $bet_comsion_sum + $deposit_comsion_sum;
        // 有效會員限制通過
        $effective_membership_pass = 't';
    }else{
        // 佣金加總=投注佣金+存款佣金
        $all_comsion_sum = number_format(0, 2, '.', '');
        // 有效會員限制未通過
        $effective_membership_pass = 'f';
    }

    $summary_tbl[]=[
        'agent_id'       => $all_downline_data[0]['parent_id'],
        'agent_account'  => $all_downline_data[0]['parent_account'],
        'agent_therole'  => $all_downline_data[0]['parent_therole'],
        'commission'     => $all_comsion_sum,
        'valid_member'   => $valid_member_sum,
        'valid_bet_sum'  => $valid_bet_sum,
        'profitlost_sum' => $profitlost_sum,
        'agent_commissionrule'      => $single_member_data['agent_commissionrule'],
        'downline_effective_bet'    => $single_member_data['downline_effective_bet'],
        'reach_bet_amount'          => $single_member_data['reach_bet_amount'],
        'effective_member_set'      => $effective_member_set,
        'effective_membership_pass' => $effective_membership_pass,
    ];
  }
  // -------------------------佣金總表計算結束-----------------------------------------
  $return_tbl['detail']=$detail_tbl;
  $return_tbl['summary']=$summary_tbl;
  // var_dump($return_tbl);
  // die();

  return $return_tbl;
}

// 組成佣金明細sql字串
function insert_update_commission_depositbet_detail_sql(array $commission_dailyreport_data){
    // print_r($commission_dailyreport_data);

    $attributes = array_keys($commission_dailyreport_data);
    $values     = array_values($commission_dailyreport_data);

    $attributes_string = implode(',', $attributes);
    $values_string     = "'" . implode("','", $values) . "'";

    $get_set_string_fun = function ($attribute, $value) {
        return "$attribute = '$value'";
    };

    $set_string = implode(',', array_map($get_set_string_fun, $attributes, $values));

    $insert_or_update_sql = <<<SQL
    INSERT INTO root_commission_depositbet_detail ($attributes_string)
      VALUES($values_string)
    ON CONFLICT ON CONSTRAINT root_commission_depositbet_detail_member_id_start_date_end_date
    DO
      UPDATE
      SET $set_string
    ;
SQL;
    // echo ($insert_or_update_sql);die();
    return $insert_or_update_sql;
}

// 組成佣金總表sql字串
function insert_update_commission_depositbet_summary_sql(array $commission_dailyreport_data)
{
    // print_r($commission_dailyreport_data);

    $attributes = array_keys($commission_dailyreport_data);
    $values     = array_values($commission_dailyreport_data);

    $attributes_string = implode(',', $attributes);
    $values_string     = "'" . implode("','", $values) . "'";

    $get_set_string_fun = function ($attribute, $value) {
        return "$attribute = '$value'";
    };

    $set_string = implode(',', array_map($get_set_string_fun, $attributes, $values));

    $insert_or_update_sql = <<<SQL
    INSERT INTO root_commission_depositbet_summary ($attributes_string)
      VALUES($values_string)
    ON CONFLICT ON CONSTRAINT root_commission_depositbet_summary_agent_id_start_date_end_date
    DO
      UPDATE
      SET $set_string
    ;
SQL;

    // echo ($insert_or_update_sql);die();
    return $insert_or_update_sql;
}


// 判斷有效會員
function judging_valid_members($bet,$depodit,$commision_rule){
  // var_dump($bet,$depodit);
  $result='0';
  if($bet>=$commision_rule['lowest_bet'] AND $depodit>=$commision_rule['lowest_deposit']){
    $result='1';
  }
  // var_dump($result);

  return $result;
}


// ---------------------------------------------------------------------------
// 檢查系統資料庫中 table root_statisticsbonusprofit 表格(放射線組織獎金計算-營運利潤獎金)有多少資料被生成了, 建立索引檔及提供可以更新的資訊
// 搭配 indexmenu_stats_switch 使用
// Usage: menu_profit_list_html()
// ---------------------------------------------------------------------------
function menu_profit_list_html() {
  global $tr;

  $max_show_date = gmdate('Y-m-d',strtotime('- 2 month'));

  // 列出系統資料統計月份
  $list_sql =<<<SQL
  SELECT
    SUM(valid_bet_sum) as bet,
    SUM(profitlost_sum) as profitlost,
    SUM(commission) as commission,
    COUNT(id) as agent_count,
    start_date ,
    end_date,
    max(to_char((updatetime AT TIME ZONE 'AST'),'YYYY-MM-DD HH24:MI:SS')) AS updatetime_ast,
    transaction_id,
    max(case when is_payout  = 't' then 1 else 0 END) as payout
  FROM root_commission_depositbet_summary

  WHERE start_date >= '{$max_show_date}'

  GROUP BY start_date ,end_date,transaction_id
  ORDER BY start_date DESC , end_date DESC
  LIMIT 20
  ;
SQL;

  $list_result = runSQLall($list_sql);
  // var_dump($list_result);
  $payout_map=[0=>'N',1=>'Y'];
  $list_stats_data = '';
  if($list_result[0] > 0){

    // 把資料 dump 出來 to table
    for($i=1;$i<=$list_result[0];$i++) {

      // 統計區間
      if(empty($list_result[$i]->end_date)) {
        $date_range = $list_result[$i]->start_date;
        $end_date = $list_result[$i]->start_date;
      } else {
        $date_range = $list_result[$i]->start_date . ' ~ ' . $list_result[$i]->end_date;
        $end_date = $list_result[$i]->end_date;
      }

      $get_list_url = 'agent_depositbet_calculation.php?sdate=' . $list_result[$i]->start_date . '&edate=' . $end_date;

      $date_range_html = '<a href="' . $get_list_url . '" title="观看指定区间">'.$date_range.'</a>';
      // 代理商人數
      $agent_count_html = number_format($list_result[$i]->agent_count,0, '.', ',');
      // 佣金總量
      $comm_sum_html =number_format($list_result[$i]->commission, 2, '.', ',');
      // 發送至彩金池
      $payout_html=$payout_map[$list_result[$i]->payout];
      // 更新日期
      // $update_time_html = $list_result[$i]->updatetime_ast;

      // 總投注量(娛樂城投注量)
      // $sum_sum_all_bets_html = number_format($list_result[$i]->bet, 2, '.' ,',');
      // 總損益
      // $sum_sum_all_profitlost_html = number_format($list_result[$i]->profitlost, 2, '.' ,',');

      // table // <td>'.$update_time_html.'</td>

      $list_stats_data = $list_stats_data.'
      <tr>
        <td>'.$date_range_html.'</td>
        <td>'.$agent_count_html.'</td>
        <td>'.$comm_sum_html.'</td>
        <td>'.$payout_html.'</td>
      </tr>
      ';
    }

  }else{
    $list_stats_data = $list_stats_data.'
    <tr>
      <td></td>
      <td></td>
      <td></td>
      <td></td>
    </tr>
    ';
  }


  // 統計資料及索引  // <th>更新时间</th>
  $listdata_html = '
    <table class="table table-bordered small">
      <thead>
        <tr class="active">
          <th>'.$tr['Statistical interval'].'<span class="glyphicon glyphicon-time"></span>(-04)</th>
          <th>'.$tr['Identity Agent'].'</th>
          <th>'.$tr['total commission'].'</th>
          <th>'.$tr['payout'].'</th>
        </tr>
      </thead>
      <tbody style="background-color:rgba(255,255,255,0.4);">
        '.$list_stats_data.'
      </tbody>
    </table>';


  return($listdata_html);
}
// ---------------------------------------------------------------------------
// END -- 檢查系統資料庫中 table root_statisticsbonusprofit 表格(放射線組織獎金計算-營運利潤獎金)有多少資料被生成了, 建立索引檔及提供可以更新的資訊
// ---------------------------------------------------------------------------


// ---------------------------------------------------------------------------
// 加上 on / off開關 JS and CSS
// ---------------------------------------------------------------------------
function indexmenu_stats_switch() {
  global $tr;
  // 历史纪录OFF
  // 历史纪录ON
  // 選單表單
  $indexmenu_list_html = menu_profit_list_html();

  // 加上 on / off開關
  $indexmenu_stats_switch_html = '
  <span style="
  position: fixed;
  top: 5px;
  left: 5px;
  width: 450px;
  height: 20px;
  z-index: 1000;
  ">
  <button class="btn btn-primary btn-xs" style="display: none" id="hide">'.$tr['menu off'].'</button>
  <button class="btn btn-success btn-xs" id="show">'.$tr['menu on'].'</button>
  </span>

  <div id="index_menu" style="display:block;
  background-color: #e6e9ed;
  position: fixed;
  top: 30px;
  left: 5px;
  width: 450px;
  height: 600px;
  overflow: auto;
  z-index: 999;
  -webkit-box-shadow: 0px 8px 35px #333;
  -moz-box-shadow: 0px 8px 35px #333;
  box-shadow: 0px 8px 35px #333;
  background: rgba(221, 221, 221, 1);
  ">
  '.$indexmenu_list_html.'
  </div>
  <script>
  $(document).ready(function(){
      $("#index_menu").fadeOut( "fast" );

      $("#hide").click(function(){
          $("#index_menu").fadeOut( "fast" );
          $("#hide").hide();
          $("#show").show();
      });
      $("#show").click(function(){
          $("#index_menu").fadeIn( "fast" );
          $("#hide").show();
          $("#show").hide();
      });
  });

  </script>
  ';


  return($indexmenu_stats_switch_html);
}
// ---------------------------------------------------------------------------
// 加上 on / off開關 JS and CSS   END
// ---------------------------------------------------------------------------


// 撈出佣金總表，依開始、結束日期，所有人數
function  commission_depositbet_summary_sql($sdate,$edate){
  $userlist_sql_tmp = <<<SQL
    SELECT *,
    to_char((updatetime AT TIME ZONE 'AST'),'YYYY-MM-DD HH24:MI:SS') AS updatetime_ast
    FROM root_commission_depositbet_summary
    WHERE
      start_date = '$sdate'
      AND end_date = '$edate'
SQL;
  return $userlist_sql_tmp;
}

// 撈出佣金明細，依日期區間
function commission_depositbet_detail_sql($sdate,$edate){
  $userlist_sql_tmp = <<<SQL
        SELECT *,
        to_char((updatetime AT TIME ZONE 'AST'),'YYYY-MM-DD HH24:MI:SS') AS updatetime_ast
        FROM root_commission_depositbet_detail
        WHERE
          start_date = '$sdate'
          AND end_date = '$edate'
        ORDER BY parent_account ,member_account
SQL;
  return $userlist_sql_tmp;
}

// number convert letters
function numtoletters(){
  $letters = [];
  $letter = 'A';
  while ($letter !== 'AAA') {
      $letters[] = $letter++;
  }
  return $letters;
}


function single_agent_download_summary($agent_id,$transaction_id){
    $sql=<<<SQL
        SELECT *,
        to_char((updatetime AT TIME ZONE 'AST'),'YYYY-MM-DD HH24:MI:SS') AS updatetime_ast
        FROM "root_commission_depositbet_summary"
        WHERE agent_id='{$agent_id}'
        AND transaction_id='{$transaction_id}'
SQL;
      return runSQLall($sql);
    }

function single_agent_download_detail($agent_id,$transaction_id){
    $sql=<<<SQL
        SELECT *
        FROM "root_commission_depositbet_detail"
        WHERE parent_id='{$agent_id}'
        AND transaction_id='{$transaction_id}'
SQL;
      return runSQLall($sql);
    }


// 要更新佣金資料之前，先刪除舊有佣金資料
function del_commission_data($sdate,$edate){
    $sql=<<<SQL
        DELETE FROM root_commission_depositbet_detail
        WHERE start_date='{$sdate}'
        AND end_date='{$edate}'
SQL;
    runSQLall($sql);

    $sql_summary=<<<SQL
        DELETE FROM root_commission_depositbet_summary
        WHERE start_date='{$sdate}'
        AND end_date='{$edate}'
SQL;
    runSQLall($sql_summary);

    return true;
}


// 發送到彩金池-預計發送佣金量
function sum_commission_html($sdate,$edate){
    $sql=<<<SQL
        SELECT sum(commission) as total_comm,
        max(case when is_payout  = 't' then 1 else 0 END) as payout
        FROM root_commission_depositbet_summary
        WHERE start_date='{$sdate}'
        AND end_date='{$edate}'
SQL;
    return runSQLall($sql);
}

// 2019/11/15
// 取會員端的存款佣金資料
function get_protalsetting(){
  $sql=<<<SQL
    SELECT * FROM root_protalsetting
      WHERE name = 'depositbet_calculation'
  SQL;

  $result = runSQLall($sql);
  unset($result[0]);

  foreach($result as $k => $v){
    if($v->value == 'off'){
      echo <<<HTML
        <script>
          alert('该功能已被关闭，如需使用请先开启！');
          location.replace('protal_setting_deltail.php?sn=default');
        </script>
    HTML;
    exit();
    }
  }
}
?>
