<?php
// ----------------------------------------------------------------------------
// Features :	後台 -- 查詢統計報表功能 LIB
// File Name: statistics_report_lib.php
// Author   : yaoyuan
// Related  : statistics_report、statistics_report_action
// db       : root_statisticsbetting、root_statisticsdailyreport
// Log      :
// 20190712 昨日以前，抓日報，今日抓十分鐘報表
// 20200514 Bug #3857 【CS】VIP站後台，各式報表 > 查询统计报表；
//          全站投注此處體育類型判斷有問題，SABA、THREESING 娛樂城營運類別無法選擇體育 (DEMO站可以)
//          - 新增預設反水分類
// 2020.05.14 Bug #3955 【CS】VIP站後台，投注記錄查詢 > 進階搜尋 > 体育 > 搜尋不到 - 修改除錯模式參數 Letter
// ----------------------------------------------------------------------------
// 主機及資料庫設定
require_once dirname(__FILE__) . "/config.php";
// 投注紀錄檔 DB config 及 runSQLall_DB2 lib -- 搭配日結報表函式庫使用
// require_once dirname(__FILE__) ."/config_betlog.php";
// 支援多國語系
require_once dirname(__FILE__) . "/i18n/language.php";
// 自訂函式庫
require_once dirname(__FILE__) . "/lib.php";



// ---------------------------------------------------------------
// check date format
// ---------------------------------------------------------------
// get example: ?current_datepicker=2017-02-03
// ref: http://php.net/manual/en/function.checkdate.php
function validateDate($date, $format = 'Y-m-d H:i:s'){
    $d = DateTime::createFromFormat($format, $date);
    return $d && $d->format($format) == $date;
}

function  casionlist(){
    $menu_casinolist_item_sql = 'SELECT * FROM casino_list WHERE "open" = 1 ORDER BY id;';
    $menu_casinolist_item_result = runSQLall($menu_casinolist_item_sql);
    return $menu_casinolist_item_result;
}

// 娛樂城對映娛樂城帳號 MG->mg_account
function casino2gameaccount($casino='all'){
    if($casino!='all'){
      $casinolist_sql = 'SELECT account_column,casinoid FROM casino_list WHERE casinoid IN (\''.implode('\',\'',$casino).'\') ORDER BY id;';
    }else{
      $casinolist_sql = 'SELECT account_column,casinoid FROM casino_list ORDER BY id;';
    }

    $casinolist_result = runSQLall($casinolist_sql);
    if($casinolist_result[0] >= 1) {
        // 資料庫依據不同的條件變換資料庫檔案
        for($i=1;$i<=$casinolist_result[0];$i++){
          // $query_sql_array['gameaccount_type'][$casinolist_result[$i]->casinoid] = $casinolist_result[$i]->account_column;
          $query_sql_array['casino_query'][] = $casinolist_result[$i]->casinoid;
        }
        return $query_sql_array;
    }else{
        $output = array('logger' => '娱乐城资料错误，错误代码：190716092833。');
        die(json_encode($output));
    }
}

// 產生日報、十分鐘所需日期格式
function dateconvertsqldate($date){
    // 現在時間(今日結束時間)
    $today_e =  gmdate('Y-m-d',time() + -4*3600);
    
    // 今日開始時間
    $today_s= date("Y-m-d", strtotime( "$today_e")).' 00:00:00';

    // $today_e =  gmdate('Y-m-d',time() + -4*3600);
    // $today_s= gmdate("Y-m-d",time()).' 00:00:00';



    // 判斷開始日期，是否有在昨日。有：日報，沒有：十分鐘。
    /*if(strtotime($date['query_date_start_datepicker'])>strtotime($date['query_date_end_datepicker'])){
        // $output = array('logger' => '开始日期不得大于结束日期，错误代码：1907171024253。');
        $output = array('logger' => '开始日期不得大于结束日期。');
        die(json_encode($output));
    }*/

    if(strtotime($date['query_date_start_datepicker'])<strtotime($today_s) AND strtotime($date['query_date_end_datepicker'])<strtotime($today_s)) {
        $re_data['have_daily']='1';
        $re_data['have_ten_mins']='0';

        // 原版
        $re_data['daily_s']=date("Y-m-d",strtotime($date['query_date_start_datepicker']));
        $re_data['daily_e']=date("Y-m-d",strtotime($date['query_date_end_datepicker']));

        // $re_data['daily_s']=date("Y-m-d H:i",strtotime($date['query_date_start_datepicker']));
        // $re_data['daily_e']=date("Y-m-d H:i",strtotime($date['query_date_end_datepicker']));

        $re_data['ten_min_s']='';
        $re_data['ten_min_e']='';
        $re_data['title']    =$re_data['daily_s'].' ～ '.$re_data['daily_e'];
    }elseif(strtotime($date['query_date_start_datepicker'])<strtotime($today_s) AND strtotime($date['query_date_end_datepicker'])>strtotime($today_s)) {
        $re_data['have_daily']='1';
        $re_data['have_ten_mins']='1';

        // 原版
        $re_data['daily_s']=date("Y-m-d",strtotime($date['query_date_start_datepicker']));

        // $re_data['daily_s']=date("Y-m-d H:i",strtotime($date['query_date_start_datepicker']));

        // $re_data['daily_e']=date("Y-m-d",strtotime("$today_s -1 day"));
        $re_data['daily_e']=date("Y-m-d",strtotime($today_e));

        $re_data['ten_min_s']=date("Y-m-d H:i:s",strtotime($today_s));

        // 原版
        $re_data['ten_min_e']=date("Y-m-d H:i:s",strtotime($date['query_date_end_datepicker']));

        // $re_data['ten_min_e']=date("Y-m-d H:i",strtotime($date['query_date_end_datepicker']));

        $re_data['title']    =$re_data['daily_s'].' ～ '.$re_data['ten_min_e'];
    }elseif(strtotime($date['query_date_start_datepicker'])>=strtotime($today_s) AND strtotime($date['query_date_end_datepicker'])>strtotime($today_s)){
        $re_data['have_daily']='0';
        $re_data['have_ten_mins']='1';
        $re_data['daily_s']='';
        $re_data['daily_e']='';

        $re_data['ten_min_s']=date("Y-m-d H:i:s",strtotime($date['query_date_start_datepicker']));
        $re_data['ten_min_e']=date("Y-m-d H:i:s",strtotime($date['query_date_end_datepicker']));

        $re_data['title']    =$re_data['ten_min_s'].' ～ '.$re_data['ten_min_e'];
    }
    return $re_data;
}


// 列出所有遊戲類別
function qcselall(){
  $cateary=[];
  $sql =<<<SQL
    SELECT  DISTINCT 
    json_array_elements_text(game_flatform_list) cate
    FROM casino_list
    ORDER BY cate;
SQL;
  $result = runSQLall($sql);
  unset($result[0]);
  foreach ($result as  $v){
    $cateary[]=($v->cate);
  }
  return $cateary;
}

// 產生使用者所選之娛樂城及對映分類
function getcasinogamecate($casino,$gamecate){
    $casino_game_sql =<<<SQL
    SELECT
      casinoid,
      game_flatform_list
    FROM casino_list
    ORDER BY id
    ;
SQL;
    $casino_game_category_result = runSQLall($casino_game_sql);
    $casino_game_categories = $del_casino_game_cate=[];

    foreach($casino_game_category_result as $index => $casino_category) {
      if($index == 0 OR !in_array($casino_category->casinoid,$casino)) continue;
      $casino_game_categories[strtolower($casino_category->casinoid)] = json_decode($casino_category->game_flatform_list, true);
    }

    foreach($casino_game_categories as $casino_name=>$val_casino_category) {
        foreach($val_casino_category as $val_category) {
            if(!in_array($val_category,$gamecate)) continue;
            $del_casino_game_cate[$casino_name][]=$val_category;
        }
    }
    return $del_casino_game_cate;

}

// 產生十分鐘表sql 條件
function tenmin_query_str($query,$sqldate){
    $query_top = 0;
    $where_sql = '';
    if(isset($sqldate['ten_min_s']) AND $sqldate['ten_min_s'] != NULL ) {
      if($query_top == 1){
        $where_sql = $where_sql.' AND ';
      }
      $where_sql = $where_sql.'start_datetime >= \''.$sqldate['ten_min_s'].'\'';
      $query_top = 1;
    }

    if(isset($sqldate['ten_min_e']) AND $sqldate['ten_min_e'] != NULL ) {
      if($query_top == 1){
        $where_sql = $where_sql.' AND ';
      }
      $where_sql = $where_sql.'end_datetime <= \''.$sqldate['ten_min_e'].'\'';
      $query_top = 1;
    }

    if(isset($query['casino_query']) AND $query['casino_query'] != '' ) {
      if($query_top == 1){
        $where_sql = $where_sql.' AND ';
      }
      $where_sql = $where_sql.'casino_id IN (\''.implode('\',\'',$query['casino_query']).'\')';
      $query_top = 1;
    }

    if(isset($query['gc_query']) AND $query['gc_query'] != '' ) {
      if($query_top == 1){
        $where_sql = $where_sql.' AND ';
      }
      $where_sql = $where_sql.'betfavor IN (\''.implode('\',\'',$query['gc_query']).'\')';
      $query_top = 1;
    }
    
    // 檢查帳號
    if(isset($query['account_query']) AND $query['account_query'] != '' ) {
      $query_sql = 'SELECT account FROM root_member
      WHERE account = \''.$query['account_query'].'\' OR parent_id=(SELECT id FROM root_member WHERE account = \''.$query['account_query'].'\') ORDER BY account;';
      $query_result = runSQLall($query_sql);
      // var_dump($query_result);
      if(isset($query_result) AND $query_result[0] >= 1){
        $account_querry_arr_str = '';
        $account_querry_count = 0;
        for($i=1;$i<=$query_result[0];$i++){
          if($account_querry_count == 1) {$account_querry_arr_str = $account_querry_arr_str.',';}
          $account_querry_arr_str = $account_querry_arr_str.'\''.$query_result[$i]->account.'\'';
          $account_querry_count = 1;
        }
        $where_sql = $where_sql.' AND member_account IN ('.$account_querry_arr_str.')';
        $query_top = 1;
      }else{
        $logger = '无此帐号，错误代码：1907221501754。';
      }
    }


    if($query_top == 1 AND !isset($logger)){
      $return_sql = ' WHERE '.$where_sql.' GROUP BY member_account ORDER BY member_account';
    }elseif(isset($logger)){
      $return_sql['logger'] = $logger;
    }else{
      $return_sql = '';
    }
    return $return_sql;
}

// 取得十分鐘資料
function ten_min_data($query_str){
  $sql=<<< SQL
      select 
          member_account,
          sum(bet_count) bet_count , 
          sum(betvalid) betvalid,
          sum(betprofit) betprofit,
          max(updatetime) updatetime
      from (
            select
                CONCAT(dailydate,' ', dailytime_start) as start_datetime,
                case
                    when dailytime_start='23:50:00' then CONCAT(dailydate +1,' ', dailytime_end)
                    else CONCAT(dailydate,' ', dailytime_end)
                end as end_datetime ,
                account_betting as bet_count,
                betfavor,
                betvalid,
                betprofit,
                member_account,
                member_id,
                updatetime,
                casino_id
            from root_statisticsbetting
                ,jsonb_to_recordset(favorable_category) as b(betfavor text,betvalid numeric, betprofit numeric)
            ) as a
SQL;
    $sql.=$query_str;
    // echo($sql);die();
    $sql_result = runSQLall($sql,0);
    // var_dump($sql_result);die();
    return $sql_result;
}


// 產生日報sql搜尋條件
function daily_query_str($query,$sqldate){
    $where_sql = '';
    $query_top = 0;

    if(isset($sqldate['daily_s']) AND $sqldate['daily_s'] != NULL ) {
      if($query_top == 1){$where_sql .= ' AND ';}
      $where_sql = $where_sql.' dailydate >= \''.$sqldate['daily_s'].'\'';
      $query_top = 1;
    }

    if(isset($sqldate['daily_e']) AND $sqldate['daily_e'] != NULL ) {
      if($query_top == 1){$where_sql .= ' AND ';}
      $where_sql = $where_sql.' dailydate <= \''.$sqldate['daily_e'].'\'';
      $query_top = 1;
    }
    
    // 檢查帳號
    if(isset($query['account_query']) AND $query['account_query'] != '' ) {
      $query_sql = 'SELECT account FROM root_member
      WHERE account = \''.$query['account_query'].'\' OR parent_id=(SELECT id FROM root_member WHERE account = \''.$query['account_query'].'\') ORDER BY account;';
      // var_dump($query_sql);die();
      $query_result = runSQLall($query_sql);
      if(isset($query_result) AND $query_result[0] >= 1){
        $account_querry_arr_str = '';
        $account_querry_count = 0;
        for($i=1;$i<=$query_result[0];$i++){
          if($account_querry_count == 1) {$account_querry_arr_str = $account_querry_arr_str.',';}
          $account_querry_arr_str = $account_querry_arr_str.'\''.$query_result[$i]->account.'\'';
          $account_querry_count = 1;
        }
        $where_sql = $where_sql.' AND member_account IN ('.$account_querry_arr_str.')';
        $query_top = 1;
      }else{
        $logger = '无此帐号，错误代码：1907221501754。';
      }
    }

    if($query_top == 1 AND !isset($logger)){
      $return_sql = ' WHERE '.$where_sql.' GROUP BY member_account ORDER BY member_account';
    }elseif(isset($logger)){
      $return_sql['logger'] = $logger;
    }else{
      $return_sql = '';
    }
    return $return_sql;
}


// 取得日報資料
function daily_data($query_str,$casinocates){
  $casino_attributes_sql =[];

  // 組合sql陣列,投注損益注單加總。
  foreach($casinocates as $casino => $gamecates) {
      foreach ($gamecates as $gamecate) {
        $casino_category =  $casino. '_' . $gamecate;

        $casino_attributes_sql['betvalid'][] = "coalesce( SUM( (root_statisticsdailyreport.betlog_detail->>'" . $casino_category . '_bets' . "') :: numeric(20,2)) , 0) ";
        $casino_attributes_sql['betprofit'][] = "coalesce( SUM( (root_statisticsdailyreport.betlog_detail->>'" . $casino_category . '_profitlost' . "') :: numeric(20,2)) , 0) ";
        $casino_attributes_sql['bet_count'][]= "coalesce( SUM( (root_statisticsdailyreport.betlog_detail->>'" . $casino_category . '_count' . "') :: numeric(20,2)) , 0) ";
      }

      $casino_attributes_sql['betvalid'][] = "coalesce( SUM( (root_statisticsdailyreport.betlog_detail->>'" . $casino . '_other_bets' . "') :: numeric(20,2)) , 0) ";
      $casino_attributes_sql['betprofit'][] = "coalesce( SUM( (root_statisticsdailyreport.betlog_detail->>'" . $casino . '_other_profitlost' . "') :: numeric(20,2)) , 0) ";
      $casino_attributes_sql['bet_count'][]= "coalesce( SUM( (root_statisticsdailyreport.betlog_detail->>'" . $casino . '_other_count' . "') :: numeric(20,2)) , 0) ";
      
  }




  // 將組合sql arry轉成字串，代入投注、損益、注單量 陣列
  $sql_bet_profit_count=[];
  foreach ($casino_attributes_sql as  $key=>$val){
    $sql_bet_profit_count[$key]='('.implode(' + ',$val).') as '.$key;
  }
  // 將投注、損益、注單量 陣列，轉成字串
  $sql_final_betprofitcount=implode(' , ',$sql_bet_profit_count);
  
  $sql=<<< SQL
          SELECT 
              member_account,
              MAX(updatetime) updatetime,
              {$sql_final_betprofitcount}
          FROM root_statisticsdailyreport
          {$query_str}
SQL;

$sql_countmorezero=<<<SQL
  SELECT * FROM (
    {$sql}
  ) AS day_report
  WHERE day_report.bet_count >'0'
SQL;
  return $sql_countmorezero;
    // echo($sql_countmorezero);die();
    // $sql_result = runSQLall($sql_countmorezero,0);
    // return $sql_result;
    

}

// 以帳號為索引
function account2index($data_ary){
  unset($data_ary[0]);
  $result=[];
  foreach($data_ary as $usr_vals){
    $result[$usr_vals->member_account]['updatetime']=$usr_vals->updatetime;
    $result[$usr_vals->member_account]['betvalid']=$usr_vals->betvalid;
    $result[$usr_vals->member_account]['betprofit']=$usr_vals->betprofit;
    $result[$usr_vals->member_account]['bet_count']=$usr_vals->bet_count;
  }
  return $result;
}

// 加總使用者資料
function combine_single_user_data($data){
    $result_ary=$temp=[];
    $cal_sum_count_profit=['betvalid','betprofit','bet_count'];
    foreach ($data as $acc=>$acc_datas){
      // 更新日期有二個以上，為陣列
      if(is_array($acc_datas['updatetime'])){
          foreach($acc_datas['updatetime'] as $value ){
              if(!isset($temp[$acc]['updatetime'])){
                $temp[$acc]['updatetime']=$value;
              }else{
                if(strtotime($temp[$acc]['updatetime'])<strtotime($value)){
                  $temp[$acc]['updatetime']=$value;
                }
              }
          }
      }else{
        $temp[$acc]['updatetime']=$acc_datas['updatetime'];
      }

      // 之後另外安排函數，轉成美東時間
      $result_ary[$acc]['updatetime']=$temp[$acc]['updatetime']; 

      foreach($cal_sum_count_profit as $val_sup){
        if(is_array($acc_datas[$val_sup])){
            foreach($acc_datas[$val_sup] as $value ){
                if(!isset($temp[$acc][$val_sup])){
                    $temp[$acc][$val_sup]=$value;
                }else{
                    $temp[$acc][$val_sup]+=$value;
                }
            }
            $result_ary[$acc][$val_sup]=(float)$temp[$acc][$val_sup];
        }else{
            $result_ary[$acc][$val_sup]=(float)$acc_datas[$val_sup];
        }
      }
    }
    return $result_ary;
}

// 轉成美東時間
function date_convert_est($data){
  foreach ($data as $acc=>$acc_datas){
    $data[$acc]['updatetime']=gmdate('Y-m-d H:i:s',strtotime($data[$acc]['updatetime']) + -4*3600); 
    // var_dump($data);die();
  }
  return $data;
}


// 加總為娛樂城資料
function combine_casino_data($data){
    $temp=[];$i=0;

    if (count($data)==0){
        $temp['updatetime']='';
        $temp['betvalid']=(float)0;
        $temp['betprofit']=(float)0;
        $temp['bet_count']=(float)0;
        $temp['people']=(int)0;
    }else{
        $cal_sum_count_profit=['betvalid','betprofit','bet_count'];
        foreach($data as $name=>$user_data){
          if(!isset($temp['updatetime'])){
              $temp['updatetime']=$user_data['updatetime'];
          }else{
              if(strtotime($temp['updatetime']) <strtotime($user_data['updatetime'])){
                $temp['updatetime']=$user_data['updatetime'];
              }
          }

          foreach($cal_sum_count_profit as $val_sup){
              if(!isset($temp[$val_sup])){
                $temp[$val_sup]=$user_data[$val_sup];
              }else{
                $temp[$val_sup]+=$user_data[$val_sup];
              }
          }
          $i++;
        }
        // 公司損益為個人派彩相反
        $temp['betprofit']=$temp['betprofit']*(-1);
        $temp['people']=$i;//人數
    }
    
    return $temp;
}

// 將使用者資料轉成一筆筆陣列格式，方便匯出
function userdata_convert_record($data){
    $result=[];
    $i=1;
    $seqs=['bet_count','betvalid','betprofit','updatetime'];
    foreach ($data as $acc => $user_datas){
      $result[$i][]=$i;
      $result[$i][]=$acc;

      foreach ($seqs as $seq){
          $result[$i][]=$user_datas[$seq];
      }
      $i++;
    }
    return  $result;
}


// 原始投注資料_十分鐘表 sql 條件
function bet_tenmin_query_str($query,$sqldate){
    $query_top = 0;
    $where_sql = '';
    if(isset($sqldate['ten_min_s']) AND $sqldate['ten_min_s'] != NULL ) {
      if($query_top == 1){
        $where_sql = $where_sql.' AND ';
      }
      $where_sql = $where_sql.'start_datetime >= \''.$sqldate['ten_min_s'].'\'';
      $query_top = 1;
    }

    if(isset($sqldate['ten_min_e']) AND $sqldate['ten_min_e'] != NULL ) {
      if($query_top == 1){
        $where_sql = $where_sql.' AND ';
      }
      $where_sql = $where_sql.'end_datetime <= \''.$sqldate['ten_min_e'].'\'';
      $query_top = 1;
    }

    if(isset($query['casino_query']) AND $query['casino_query'] != '' ) {
      if($query_top == 1){
        $where_sql = $where_sql.' AND ';
      }
      $where_sql = $where_sql.'casino_id IN (\''.implode('\',\'',$query['casino_query']).'\')';
      $query_top = 1;
    }

    if(isset($query['gc_query']) AND $query['gc_query'] != '' ) {
      if($query_top == 1){
        $where_sql = $where_sql.' AND ';
      }
      $where_sql = $where_sql.'betfavor IN (\''.implode('\',\'',$query['gc_query']).'\')';
      $query_top = 1;
    }
    
    // 檢查帳號
    if(isset($query['account_query']) AND $query['account_query'] != '' ) {
      $query_sql = 'SELECT account FROM root_member
      WHERE account = \''.$query['account_query'].'\' OR parent_id=(SELECT id FROM root_member WHERE account = \''.$query['account_query'].'\') ORDER BY account;';
      $query_result = runSQLall($query_sql);
      // var_dump($query_result);
      if(isset($query_result) AND $query_result[0] >= 1){
        $account_querry_arr_str = '';
        $account_querry_count = 0;
        for($i=1;$i<=$query_result[0];$i++){
          if($account_querry_count == 1) {$account_querry_arr_str = $account_querry_arr_str.',';}
          $account_querry_arr_str = $account_querry_arr_str.'\''.$query_result[$i]->account.'\'';
          $account_querry_count = 1;
        }
        $where_sql = $where_sql.' AND member_account IN ('.$account_querry_arr_str.')';
        $query_top = 1;
      }else{
        $logger = '无此帐号，错误代码：1911181418215。';
      }
    }


    if($query_top == 1 AND !isset($logger)){
      $return_sql = ' WHERE '.$where_sql.' ORDER BY member_account ,start_datetime';
    }elseif(isset($logger)){
      $return_sql['logger'] = $logger;
    }else{
      $return_sql = '';
    }
    return $return_sql;
}


// 原始投注資料_十分鐘資料
function bet_ten_min_data($query_str){
  $sql=<<< SQL
      select *
      from (
            select
                CONCAT(dailydate,' ', dailytime_start) as start_datetime,
                case
                    when dailytime_start='23:50:00' then CONCAT(dailydate +1,' ', dailytime_end)
                    else CONCAT(dailydate,' ', dailytime_end)
                end as end_datetime ,
                account_betting as bet_count,
                betfavor,
                betvalid,
                betprofit,
                member_account,
                updatetime,
                casino_id
            from root_statisticsbetting
                ,jsonb_to_recordset(favorable_category) as b(betfavor text,betvalid numeric, betprofit numeric)
            ) as a
SQL;
    $sql.=$query_str;
    // echo($sql);die();
    $sql_result = runSQLall($sql,0);

    return $sql_result;
}


// 原始十分鐘投注明細轉成xlsx格式陣列
function bet_ten_min_xlsx($data){
    global $tr;
    $result=[];
    unset($data[0]);
    $i=1;

    $result[0][]=$tr['ID'];
    $result[0][]=$tr['Account'];
    $result[0][]=$tr['Casino'];
    $result[0][]=$tr['classification'];
    $result[0][]=$tr['bet slip'];
    $result[0][]=$tr['effective bet amount'];
    $result[0][]=$tr['profit and loss'];
    $result[0][]=$tr['update time'];
    $result[0][]=$tr['Starting time'];
    $result[0][]=$tr['End time'];

    foreach ($data as $acc => $user_datas){
      $result[$i][]=$i;
      $result[$i][]=$user_datas->member_account;
      $result[$i][]=$user_datas->casino_id;
      $result[$i][]=$user_datas->betfavor;
      $result[$i][]=$user_datas->bet_count;
      $result[$i][]=$user_datas->betvalid;
      $result[$i][]=$user_datas->betprofit;
      $result[$i][]=gmdate('Y-m-d H:i:s',strtotime($user_datas->updatetime) + -4*3600);
      $result[$i][]=$user_datas->start_datetime;
      $result[$i][]=$user_datas->end_datetime;
      $i++;
    }
    return  $result;
}


// 產生原始日報資料sql搜尋條件
function bet_daily_query_str($query,$sqldate){
    $where_sql = '';
    $query_top = 0;

    if(isset($sqldate['daily_s']) AND $sqldate['daily_s'] != NULL ) {
      if($query_top == 1){$where_sql .= ' AND ';}
      $where_sql = $where_sql.' dailydate >= \''.$sqldate['daily_s'].'\'';
      $query_top = 1;
    }

    if(isset($sqldate['daily_e']) AND $sqldate['daily_e'] != NULL ) {
      if($query_top == 1){$where_sql .= ' AND ';}
      $where_sql = $where_sql.' dailydate <= \''.$sqldate['daily_e'].'\'';
      $query_top = 1;
    }
    
    // 檢查帳號
    if(isset($query['account_query']) AND $query['account_query'] != '' ) {
      $query_sql = 'SELECT account FROM root_member
      WHERE account = \''.$query['account_query'].'\' OR parent_id=(SELECT id FROM root_member WHERE account = \''.$query['account_query'].'\') ORDER BY account;';

      $query_result = runSQLall($query_sql);
      if(isset($query_result) AND $query_result[0] >= 1){
        $account_querry_arr_str = '';
        $account_querry_count = 0;
        for($i=1;$i<=$query_result[0];$i++){
          if($account_querry_count == 1) {$account_querry_arr_str = $account_querry_arr_str.',';}
          $account_querry_arr_str = $account_querry_arr_str.'\''.$query_result[$i]->account.'\'';
          $account_querry_count = 1;
        }
        $where_sql = $where_sql.' AND member_account IN ('.$account_querry_arr_str.')';
        $query_top = 1;
      }else{
        $logger = '无此帐号，错误代码：1911181651964。';
      }
    }

    if($query_top == 1 AND !isset($logger)){
      $return_sql = ' WHERE '.$where_sql.' ORDER BY dailydate DESC , member_account';
    }elseif(isset($logger)){
      $return_sql['logger'] = $logger;
    }else{
      $return_sql = '';
    }
    return $return_sql;
}


// 原始日報投注資料
function bet_daily_data($query_str,$casinocates){
  $casino_attributes_sql =$casino_bet_sum=[];

  // 組合sql陣列,投注損益注單加總。
  foreach($casinocates as $casino => $gamecates) {
      foreach ($gamecates as $gamecate) {
        $casino_category =  $casino. '_' . $gamecate;
        $casino_attributes_sql[]  = "coalesce((betlog_detail->>'". $casino_category ."_bets')::numeric(20,2),0) as ".$casino_category ."_bets";
        $casino_attributes_sql[]  = "coalesce((betlog_detail->>'". $casino_category ."_profitlost')::numeric(20,2),0) as ".$casino_category ."_profitlost";
        $casino_attributes_sql[]  = "coalesce((betlog_detail->>'". $casino_category ."_count')::numeric(20,0),0) as ".$casino_category ."_count";
        $casino_bet_sum['bet_count'][]= "coalesce((root_statisticsdailyreport.betlog_detail->>'" . $casino_category . '_count' . "') :: numeric(20,0) , 0)";
      }
      $casino_attributes_sql[] = "coalesce((betlog_detail->>'" . $casino . '_other_bets' . "') :: numeric(20,2) , 0) as ".$casino ."_other_bets";
      $casino_attributes_sql[] = "coalesce((betlog_detail->>'" . $casino . '_other_profitlost' . "') :: numeric(20,2) , 0) ".$casino ."_other_profitlost";
      $casino_attributes_sql[]=  "coalesce((betlog_detail->>'" . $casino . '_other_count' . "') :: numeric(20,2) , 0) ".$casino ."_other_count";
      $casino_bet_sum['bet_count'][]=  "coalesce((root_statisticsdailyreport.betlog_detail->>'" . $casino . '_other_count' . "') :: numeric(20,2) , 0)";
  }


  // 將組合sql arry轉成字串，代入注單量 
  $sql_bet_profit_count='';
  foreach ($casino_bet_sum as  $key=>$val){
    $sql_bet_profit_count='('.implode(' + ',$val).') as '.$key;
  }
  
  // print("<pre>" . print_r($casino_bet_sum, true) . "</pre>");die();

  // 將投注、損益、注單量 陣列，轉成字串
  $sql_final_betprofitcount=implode(' , ',$casino_attributes_sql);
  
  
  $sql=<<< SQL
          SELECT 
              member_account,dailydate ,
              to_char((updatetime at TIME zone 'AST'),'YYYY-MM-DD HH24:MI:SS') as updatetime,
              {$sql_final_betprofitcount},
              {$sql_bet_profit_count}
          FROM root_statisticsdailyreport
          {$query_str}
SQL;

$sql_countmorezero=<<<SQL
  SELECT * FROM (
    {$sql}
  ) AS day_report
  WHERE day_report.bet_count >'0';
SQL;

    // echo($sql_countmorezero);die();
    $sql_result = runSQLall($sql_countmorezero,0);
    // var_dump($sql_result);die();
    return $sql_result;
}



// 原始日報資料轉成xlsx格式陣列
function bet_daily_data_xlsx($data){
    global $tr;
    $result=[];
    unset($data[0]);
    $i=1;

    
    $result[0][]=$tr['ID'];
    foreach ($data[1] as $key=> $val){
      $result[0][]=$key;
    }
    
    foreach ($data as $user_datas){
      $result[$i][]=$i;
      foreach ($user_datas as $final_val){
        $result[$i][]=$final_val;
      }
      $i++;
    }

    return  $result;
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
?>
