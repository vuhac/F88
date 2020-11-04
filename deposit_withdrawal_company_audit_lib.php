<?php
// parent
function parent_data($data){

  $combine_parent_data = [];

  $implode = implode(",",$data['parent_id']);

  if(is_array($data['parent_id'])){
    $parent_sql=<<<SQL
        SELECT id,account,therole FROM root_member WHERE id IN ($implode)
SQL;
    $result = runSQLall($parent_sql);

    unset($result[0]);
    $decode_parent = json_decode(json_encode($result),true);
    foreach($decode_parent as $data => $value){
      $combine_parent_data[$value['id']] = $value['account'];
      $combine_parent_data[$value['therole']] = $value['account'];
    }
  }
  return $combine_parent_data;

}
// 入賬資訊
function deposit_way($depositcompanyid){

  $combine_bank_data = [];

  $implode = implode(",",$depositcompanyid['deposit_id']);

  if(is_array($depositcompanyid['deposit_id'])){
    $bank_data_sql = <<<SQL
      SELECT id,type,companyname,accountname
      FROM root_deposit_company
      WHERE id in ($implode)
      AND status = '1'
SQL;
    $result = runSQLall($bank_data_sql);

    unset($result[0]);
    $decode_bank = json_decode(json_encode($result),true);
    foreach($decode_bank as $data => $value){
      $combine_bank_data['company'][$value['id']] = $value['companyname'];
      // $combine_bank_data['account'][$value['id']] = $value['accountname'];
    }
  }
  return $combine_bank_data;
}

// 取list的資料成array
// 公司入款
function to_array($data){
  $all_data = json_decode(json_encode($data),true);
  $go =[];
  $arrange = [];

  unset($all_data[0]);
  foreach($all_data as $v){
    // 取欄位 p_id找上級
    $go['parent_id'][$v['p_id']] = $v['p_id'];

    // 對帳資訊
    $go['deposit_id'][$v['depositcompanyid']] = isset($v['depositcompanyid']) ? $v['depositcompanyid'] : null;
  }

  // 上級資料
  $return_parent = parent_data($go);
  // 對帳資訊
  $return_daposit_way = deposit_way($go);

  foreach($all_data as $value){
    // 上級
    $p_id = $value['p_id'];
    // $value['parent'] = $return_parent[$p_id];
    if($return_parent[$p_id] == 'root'){
      // 隱藏root
      $value['parent'] = '-';
      // $value['parent'] = $return_parent[$p_id];
    }else{
      $value['parent'] = $return_parent[$p_id];
    }

    // 入帳資訊
    $deposit_id = $value['depositcompanyid'];
    $value['deposit_companyname'] = isset($return_daposit_way['company'][$deposit_id]) ? $return_daposit_way['company'][$deposit_id] : null;
    $value['deposit_account_name'] = isset($return_daposit_way['account'][$deposit_id]) ? $return_daposit_way['account'][$deposit_id] : null;

    $arrange[$value['id']] = $value;
  }

  return $arrange;
}
// 遊戲幣取款、現金取款找parent
function only_get_parent($data){
  $all_data = json_decode(json_encode($data),true);
  $go =[];
  $arrange = [];

  unset($all_data[0]);
  foreach($all_data as $v){
    // 上級
    $go['parent_id'][$v['p_id']] = $v['p_id'];
  }

  // 上級資料
  $return_parent = parent_data($go);

  foreach($all_data as $value){
    // 上級
    $p_id = $value['p_id'];
    // $value['parent'] = $return_parent[$p_id];
    if($return_parent[$p_id] == 'root'){
      // 隱藏root
      $value['parent'] = '-';
    }else{
      $value['parent'] = $return_parent[$p_id];
    }

    $arrange[$value['id']] = $value;
  }
  return $arrange;
}

// 搜尋時間
function time_convert(){
  // datepicker
  $min_date = ' 00:00';
  $max_date = ' 23:59';
  $min_date_sec = '00:00:00';
  $max_date_sec = '23:59:59';
  $minus_date = '-01 00:00';

  // 開始時間00:00 結束時間 23:59
  $current = gmdate('Y-m-d',time()+ -4*3600); // 今天(日期)

  $current_time = gmdate('Y-m-d H:i',time() + -4*3600);

  $seven_date = gmdate('Y-m-d',strtotime('- 7 days')); // 7天
  $two_month = gmdate('Y-m-d',strtotime('- 2 month'));

  // 本周
  $thisweekday = date("Y-m-d", strtotime("$current - ".date('w',strtotime($current))."days"));
  // 昨天
  $yesterday = date("Y-m-d", strtotime("$current - 1 days"));

  // 上週
  $lastweekday_s = date("Y-m-d", strtotime("$current - ".intval(date('w',strtotime($current))+7)."days"));
  $lastweekday_e = date("Y-m-d", strtotime("$thisweekday - 1 days"));

  // 本月
  $thismonth = date("Y-m", strtotime($current));

  // 上個月
  $lastmonth = date('Y-m',strtotime(date('Y-m-1').'-1 month'));
  $lastmonth_e = date('Y-m-d',strtotime(date('Y-m-1').'-1 day'));

  $time_convert = [
    'min'=> $min_date,
    'max'=> $max_date,
    'start_sec'=> $min_date_sec,
    'end_sec' => $max_date_sec,
    'minus'=> $minus_date,
    'current' => $current,
    'currenttime'=> $current_time,
    'default_min_date' => $seven_date,
    'two_month'=> $two_month,
    'thisweekday' => $thisweekday,
    'yesterday' => $yesterday,
    'lastweekday_s' => $lastweekday_s,
    'lastweekday_e'=> $lastweekday_e,
    'thismonth'=> $thismonth,
    'lastmonth'=> $lastmonth,
    'lastmonth_e'=> $lastmonth_e
  ];
  return $time_convert;
}

?>