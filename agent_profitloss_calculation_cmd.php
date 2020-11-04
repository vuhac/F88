#!/usr/bin/php70
<?php
// ----------------------------------------------------------------------------
// Features :	後台 -- 聯營股東損益計算 command 模式
// File Name: agent_profitloss_calculation_cmd.php
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

session_start();

// 主機及資料庫設定
require_once dirname(__FILE__) ."/config.php";
// 支援多國語系
require_once dirname(__FILE__) ."/i18n/language.php";
// 自訂函式庫
require_once dirname(__FILE__) ."/lib.php";
// 此計算程式所使用的 LIB
require_once dirname(__FILE__) ."/agent_profitloss_calculation_lib.php";

// set memory limit
ini_set('memory_limit', '200M');

// At start of script
$time_start = microtime(true);
$origin_memory_usage = memory_get_usage();

// 確保這個 script 執行不會因為 user abort 而中斷!!
// Ignore user aborts and allow the script to run forever
ignore_user_abort(true);
// disable php time limit , 60*60 = 3600sec = 1hr
set_time_limit(7200);


/**
 * [$member_node_data_attributes_default description]
 * @var array $attribute_name => $default_value
 */
$member_node_data_attributes_default = [
  'member_id' => '',
  'member_account' => '',
  'member_therole' => '',
  'member_parent_id' => '',
  'commissionrule' => '',
  'member_level' => 0,
  'member_skipinfo' => '',
  'member_betsprofit' => 0,
  'member_bets' => 0,
  'member_platformcost' => 0,
  'member_marketingcost' => 0,
  'member_cashcost' => 0,
  'member_withdrawals' => 0,
  'member_deposit' => 0,
  'member_profitlost' => 0,

  // round 1 bottom-up data
  'agent_bets' => 0,
  'agent_betsprofit' => 0,
  'agent_recursive_sumbets' => 0,
  'agent_recursive_sumbetsprofit' => 0,

  'agent_profitlost' => 0,
  'agent_sumwithdrawals' => 0,
  'agent_sumdeposit' => 0,

  'agent_memberbet_count' => 0,
  'agent_memberrecursivebets_count' => 0,

  'agent_agent_count' => 0,
  'agent_recursive_agent_count' => 0,

  'agent_member_count' => 0,
  'agent_recursives_count' => 0,

  'agent_valid_member_count' => 0,
  'agent_valid_member_recursives_count' => 0,

  // round 2 top-down data
  'agent_platformcost' => 0,
  'agent_marketingcost' => 0,
  'agent_cashcost' => 0,

  'agent_commission' => 0,
  'agent_commission_denominator' => 0,
  'agent_commission_molecular' => 0,
  'agent_commission_upper' => 0,
  'agent_commission_lower' => 0,
];

$casino_game_categories = get_casino_game_categories();

/**
 * [init_member_node_data description]
 * @param  [type] $member [description]
 * @return [type]         [description]
 */
function init_member_node_data($member) {
  global $member_node_data_attributes_default;
  global $casino_game_categories;

  // init node_data
  $member->node_data = new stdClass;

  $member->node_data->commission_detail = new ProfitlossCalculationData;

  foreach($member_node_data_attributes_default as $attribute_name => $default) {
    $member->node_data->$attribute_name = $default;
  }

  $member->node_data->member_id = $member->id;
  $member->node_data->member_account = $member->account;
  $member->node_data->member_therole = $member->therole;
  $member->node_data->member_parent_id = $member->parent_id;
  $member->node_data->commissionrule = $member->commissionrule;
  $member->node_data->member_level = $member->member_level;

  foreach($casino_game_categories as $vendor => $categories) {
    foreach ($categories as $category_name) {
      $bets_attribute_name       = $vendor . '_' . $category_name . '_bets';
      $profitlost_attribute_name = $vendor . '_' . $category_name . '_profitlost';

      $bets_sum_attribute_name       = $vendor . '_' . $category_name . '_bets_sum';
      $profitlost_sum_attribute_name = $vendor . '_' . $category_name . '_profitlost_sum';

      $bets_recursives_sum_attribute_name       = $vendor . '_' . $category_name . '_bets_recursives_sum';
      $profitlost_recursives_sum_attribute_name = $vendor . '_' . $category_name . '_profitlost_recursives_sum';

      $member->node_data->$bets_attribute_name = 0;
      $member->node_data->$profitlost_attribute_name = 0;

      $member->node_data->$bets_sum_attribute_name = 0;
      $member->node_data->$profitlost_sum_attribute_name = 0;

      $member->node_data->$bets_recursives_sum_attribute_name = 0;
      $member->node_data->$profitlost_recursives_sum_attribute_name = 0;
    }
  }

}


/**
 * [fill_statistics_daily_report_to_node description]
 * @param  [type] $member [description]
 * @return [type]         [description]
 */
function fill_statistics_daily_report_to_node($member) {
  global $statistics_daily_report_list;
  global $casino_game_categories;

  // init node_data
  $member->node_data->member_id = $member->id;
  $member->node_data->member_account = $member->account;
  $member->node_data->member_therole = $member->therole;
  $member->node_data->member_parent_id = $member->parent_id;
  $member->node_data->commissionrule = $member->commissionrule;
  $member->node_data->member_level = $member->member_level;
  $member->node_data->member_betsprofit = 0;
  $member->node_data->member_bets = 0;
  $member->node_data->member_marketingcost = 0;
  $member->node_data->member_withdrawals = 0;
  $member->node_data->member_deposit = 0;
  $member->node_data->member_cashcost = 0;

  $member->node_data->member_all_preferential = 0;
  $member->node_data->member_all_favorable = 0;

  $member->node_data->member_profitlost = 0;

  // casino game data by vendor and category
  foreach($casino_game_categories as $vendor => $categories) {
    foreach ($categories as $category_name) {
      $bets_attribute_name = $vendor . '_' . $category_name . '_bets';
      $profitlost_attribute_name = $vendor . '_' . $category_name . '_profitlost';

      $member->node_data->$bets_attribute_name = 0;
      $member->node_data->$profitlost_attribute_name = 0;
    }
  }

  if(!isset($statistics_daily_report_list[($member->account)])) {
    return;
  }

  // fill daily report related data
  $daily_report = $statistics_daily_report_list[($member->account)];

  $member->node_data->member_betsprofit = $daily_report->all_profitlost;
  $member->node_data->member_bets = $daily_report->all_bets;
  $member->node_data->member_marketingcost = $daily_report->tokenfavorable + $daily_report->tokenpreferential;
  $member->node_data->member_withdrawals = $daily_report->cashwithdrawal;
  $member->node_data->member_deposit = $daily_report->cashdeposit;
  if(!empty($daily_report->all_cashfee)) {
    $member->node_data->member_cashcost = $daily_report->all_cashfee;
  }

  $member->node_data->member_all_preferential = $daily_report->all_favorablerate_amount;
  $member->node_data->member_all_favorable = $daily_report->tokenfavorable;

  $member->node_data->member_profitlost = ($daily_report->all_profitlost) - ( $member->node_data->member_platformcost + $member->node_data->member_marketingcost + $member->node_data->member_cashcost );

  // casino game data by vendor and category
  foreach($casino_game_categories as $vendor => $categories) {
    foreach ($categories as $category_name) {
      $bets_attribute_name = $vendor . '_' . $category_name . '_bets';
      $profitlost_attribute_name = $vendor . '_' . $category_name . '_profitlost';

      $member->node_data->$bets_attribute_name = $daily_report->$bets_attribute_name;
      $member->node_data->$profitlost_attribute_name = $daily_report->$profitlost_attribute_name;
    }
  }

}


/**
* [$member_node_data_attributes_default description]
* @var array attribute => [$sum_attribute, $carry_attribute]
*/
$member_node_data_sum_attributes = [
  // round 1 bottom-up data
  'agent_bets' => ['member_bets', 0],
  'agent_betsprofit' => ['member_betsprofit', 0],
  'agent_recursive_sumbets' => ['agent_recursive_sumbets', 'member_bets'],
  'agent_recursive_sumbetsprofit' => ['agent_recursive_sumbetsprofit', 'member_betsprofit'],

  'agent_profitlost' => ['agent_profitlost', 'member_profitlost'],
  'agent_sumwithdrawals' => ['agent_sumwithdrawals', 'member_withdrawals'],
  'agent_sumdeposit' => ['agent_sumdeposit', 'member_deposit'],

  'agent_recursive_preferential' => ['agent_recursive_preferential', 'member_all_preferential'],
  'agent_recursive_favorable' => ['agent_recursive_favorable', 'member_all_favorable'],

  // 'agent_memberbet_count' => ['', 0],
  // 'agent_memberrecursivebets_count' => ['', 0],

  // 'agent_agent_count' => ['', 0],
  // 'agent_recursive_agent_count' => ['', 0],

  // 'agent_member_count' => ['', 0],
  'agent_recursives_count' => ['agent_recursives_count', 1],
];

// add casino_game_categories related sum
foreach($casino_game_categories as $vendor => $categories) {
  foreach ($categories as $category_name) {
    $vendor_category_prefix = $vendor . '_' . $category_name;

    $member_node_data_sum_attributes[$vendor_category_prefix . '_bets_sum'] =
    [$vendor_category_prefix . '_bets', 0];

    $member_node_data_sum_attributes[$vendor_category_prefix . '_profitlost_sum'] =
    [$vendor_category_prefix . '_profitlost', 0];

    $member_node_data_sum_attributes[$vendor_category_prefix . '_bets_recursives_sum'] =
    [$vendor_category_prefix . '_bets_recursives_sum', $vendor_category_prefix . '_bets'];

    $member_node_data_sum_attributes[$vendor_category_prefix . '_profitlost_recursives_sum'] =
    [$vendor_category_prefix . '_profitlost_recursives_sum', $vendor_category_prefix . '_profitlost'];
  }
}

// tree  callbacks

// Round 1 callback
$round1_bottom_up_callback = function($member) use ($member_node_data_sum_attributes) {

  // init node_data
  init_member_node_data($member);

  // fill basic node_data
  fill_statistics_daily_report_to_node($member);

  //sum
  foreach($member_node_data_sum_attributes as $attribute => $rule) {
    $sum_attribute = $rule[0];
    $carry_attribute = $rule[1];

    if(is_string($carry_attribute)) {
      $carry_value = $member->node_data->$carry_attribute;
    } else {
      $carry_value = $carry_attribute;
    }

    // calculate sum
    $member->node_data->$attribute = array_reduce(
      $member->children,
      function($carry, $child) use ($sum_attribute) {
        return $carry += $child->node_data->$sum_attribute;
      },
      $carry_value
    );
  }

  // agent_memberbet_count
  $member->node_data->agent_memberbet_count = array_reduce(
    $member->children,
    function($carry, $child) {
      if($child->node_data->member_bets > 0) {
        return $carry += 1;
      }
      return $carry;
    },
    0
  );

  // agent_memberrecursivebets_count
  $carry = 0;
  if($member->node_data->member_bets > 0) $carry = 1;
  $member->node_data->agent_memberrecursivebets_count = array_reduce(
    $member->children,
    function($carry, $child) {
      return $carry += $child->node_data->agent_memberrecursivebets_count;
    },
    $carry
  );

  // agent_agent_count
  $member->node_data->agent_agent_count = array_reduce(
    $member->children,
    function($carry, $child) {
      if($child->therole == 'A') {
        return $carry += 1;
      }
      return $carry;
    },
    0
  );

  // agent_recursive_agent_count
  $carry = 0;
  if($member->therole == 'A') $carry = 1;
  $member->node_data->agent_recursive_agent_count = array_reduce(
    $member->children,
    function($carry, $child) {
      return $carry += $child->node_data->agent_recursive_agent_count;
    },
    $carry
  );



  // agent_member_count
  $member->node_data->agent_member_count = count($member->children);

};
// end of Round 1 callback






// Main

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

// var_dump($argv);

// 取得今天的日期
// 轉換為美東的時間 date
$date = date_create(date('Y-m-d H:i:sP'), timezone_open('America/St_Thomas'));
date_timezone_set($date, timezone_open('America/St_Thomas'));
$current_date = date_format($date, 'Y-m-d');
$end_date = date_format($date, 'Y-m-d');

if(isset($argv[1]) AND ($argv[1] == 'test' OR $argv[1] == 'run') ){
  if(isset($argv[2]) AND validateDate($argv[2], 'Y-m-d') ){
		if($argv[2] <= $current_date){
	    //如果有的話且格式正確, 取得日期. 沒有的話中止
			$current_datepicker = $argv[2];
      $end_datepicker = $argv[2];

      if(isset($argv[3]) AND validateDate($argv[3], 'Y-m-d') ) {
        $end_datepicker = $argv[3];
      }
		}else{
		  // command 動作 時間
		  echo "command [test|run] YYYY-MM-DD \n";
		  die('no test and run');
		}
  }else{
		$current_datepicker = $current_date;
    $end_datepicker = $end_date;
		// $current_datepicker = date('Y-m-d');
  }
  $argv_check = $argv[1];
	$current_datepicker_gmt = gmdate('Y-m-d H:i:s.u',strtotime($current_datepicker.'23:59:59 -04')+8*3600).'+08:00';
}else{
  // command 動作 時間
  echo "command [test|run] YYYY-MM-DD \n";
  die('no test and run');
}

if(isset($argv[4]) AND $argv[4] == 'web' ){
	$web_check = 1;
	// $output_html  = '<p align="center">更新中...<img src="ui/loading.gif" /></p><script>setTimeout(function(){location.reload()},1000);</script>';
	// $file_key = sha1('agentbonus'.$current_datepicker);
	// $reload_file = dirname(__FILE__) .'/tmp_dl/agentbonus_'.$file_key.'.tmp';
	// file_put_contents($reload_file,$output_html);
} else {
	$web_check = 0;
}

$logger = '';





/**
 * [$statistics_daily_report_list   key by member_account]
 * @var array
 */
$statistics_daily_report_list = get_statistics_daily_report_list($current_datepicker, $end_datepicker, $casino_game_categories);

if(count($statistics_daily_report_list) < 1) {
  echo "No root_statisticsdailyreport\n";
  die();
}



/**
 * [$commission_rules description]
 * @var array
 */
$commission_rules = get_commission_rules();


/**
* [$member_list   key is member id]
* @var array
*/
$member_list = MemberTreeNode::getMemberList();

// build tree
$tree_root = $member_list[1];
MemberTreeNode::buildMemberTree($tree_root, $member_list, []);


// round 1: init agent info and calculate casino statistics
MemberTreeNode::visitBottomUp($tree_root, $round1_bottom_up_callback);


// get agent's commission rule
foreach ($member_list as $id => $agent) {
  if($agent->therole != 'A') continue;
  if($agent->node_data->member_level > 2) continue;

  $agent->node_data->commission_rule = null;

  foreach($commission_rules[$agent->commissionrule] as $payoff => $rule) {
    if($agent->node_data->agent_recursive_sumbets < $payoff) continue;

    calculate_valid_member($agent, $rule);

    if($agent->node_data->agent_valid_member_recursives_count >= $rule['effective_member']) {
      $agent->node_data->commission_rule = $rule;
      break;
    }

  }
}


// round 2: calculate agent commission
$profitlossCalculator = new ProfitlossCalculator(
  $casino_game_categories,
  $statistics_daily_report_list,
  $member_list
);
$profitlossCalculator->calculate($tree_root);



$insert_buffer = [];

$insert_count = 0;
$member_count = count($member_list);
// insert or update commission_dailyreport
foreach($member_list as $account => $member) {

  if($member->therole !== 'A' &&  $member->id != 1) {
    $insert_count++;
    continue;
  }

  // echo $account . "\n";
  // print_r(array ($member->node_data));

  $casino_bet_detail_data = [];
  $casino_profitlost_detail_data = [];

  foreach($casino_game_categories as $vendor => $categories) {
    foreach ($categories as $category_name) {

      $bets_attribute_name = $vendor . '_' . $category_name . '_bets_recursives_sum';
      $profitlost_attribute_name = $vendor . '_' . $category_name . '_profitlost_recursives_sum';


      $casino_bet_detail_data[$bets_attribute_name] = $member->node_data->$bets_attribute_name;
      $casino_profitlost_detail_data[$profitlost_attribute_name] = $member->node_data->$profitlost_attribute_name;
    }
  }

  $insert_buffer[] = insert_or_update_commission_sql([
    'dailydate' => $current_datepicker,
    'end_date' => $end_datepicker,
    'member_id' => $member->node_data->member_id,
    'member_account' => $member->node_data->member_account,
    'member_therole' => $member->node_data->member_therole,
    'member_parent_id' => $member->node_data->member_parent_id,
    'commissionrule' => $member->node_data->commissionrule,
    'member_level' => $member->node_data->member_level,
    'member_skipinfo' => '',
    'member_betsprofit' => $member->node_data->member_betsprofit,
    'member_bets' => $member->node_data->member_bets,
    'member_platformcost' => $member->node_data->member_platformcost,
    'member_marketingcost' => $member->node_data->member_marketingcost,
    'member_cashcost' => $member->node_data->member_cashcost,
    'member_withdrawals' => $member->node_data->member_withdrawals,
    'member_deposit' => $member->node_data->member_deposit,
    'member_profitlost' => $member->node_data->member_profitlost,

    // round 1 bottom-up data
    'agent_bets' => $member->node_data->agent_bets,
    'agent_betsprofit' => $member->node_data->agent_betsprofit,
    'agent_recursive_sumbets' => $member->node_data->agent_recursive_sumbets,
    'agent_recursive_sumbetsprofit' => $member->node_data->agent_recursive_sumbetsprofit,

    'agent_profitlost' => $member->node_data->agent_profitlost,
    'agent_sumwithdrawals' => $member->node_data->agent_sumwithdrawals,
    'agent_sumdeposit' => $member->node_data->agent_sumdeposit,

    'agent_memberbet_count' => $member->node_data->agent_memberbet_count,
    'agent_memberrecursivebets_count' => $member->node_data->agent_memberrecursivebets_count,

    'agent_agent_count' => $member->node_data->agent_agent_count,
    'agent_recursive_agent_count' => $member->node_data->agent_recursive_agent_count,

    'agent_member_count' => $member->node_data->agent_member_count,
    'agent_recursives_count' => $member->node_data->agent_recursives_count,

    'agent_valid_member_count' => $member->node_data->agent_valid_member_count,
    'agent_valid_member_recursives_count' => $member->node_data->agent_valid_member_recursives_count,


    'agent_platformcost' => $member->node_data->agent_platformcost,
    'agent_marketingcost' => $member->node_data->agent_marketingcost,
    'agent_cashcost' => $member->node_data->agent_cashcost,

    'agent_commission' => $member->node_data->agent_commission,
    'agent_commission_denominator' => $member->node_data->agent_commission_denominator,
    'agent_commission_molecular' => $member->node_data->agent_commission_molecular,
    'agent_commission_upper' => $member->node_data->agent_commission_upper,
    'agent_commission_lower' => $member->node_data->agent_commission_lower,

    'casino_bet_detail' => json_encode($casino_bet_detail_data),
    'casino_profitlost_detail' => json_encode($casino_profitlost_detail_data),

    'commission_detail' => json_encode($member->node_data->commission_detail),

    'updatetime' => (new \DateTime())->format('Y-m-d H:i:s'),
  ]);

  batched_insert($insert_buffer, $member_count - $insert_count);

  $insert_count++;
}


// var_dump($tree_root->node_data);
// echo "\n";
// print_r(($member_list[1037])->node_data);
// print_r(($member_list[17])->predecessor_id_list);
// print_r( array( ($member_list[1037])->node_data->commission_rule) );
// print_r( array( ($member_list[17])->node_data->commission_detail) );

// print_r( (array)( $statistics_daily_report_list['kkk'] ) );

echo 'Total execution time in seconds: ' . round( (microtime(true) - $time_start), 3) . " sec\n";
echo 'memmory usage: ' . round( (memory_get_usage() - $origin_memory_usage) / (1024 * 1024), 3) . " MB.\n";

?>
