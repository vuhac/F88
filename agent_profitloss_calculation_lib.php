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


require_once __DIR__ ."/lib_member_tree.php";
require_once dirname(__FILE__) . "/lib_file.php";
require_once dirname(__FILE__) ."/casino_switch_process_lib.php";

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

// ---------------------------------------------------------------
// check date format
// ---------------------------------------------------------------
// get example: ?current_datepicker=2017-02-03
// ref: http://php.net/manual/en/function.checkdate.php
function validateDate($date, $format = 'Y-m-d H:i:s'){
    $d = DateTime::createFromFormat($format, $date);
    return $d && $d->format($format) == $date;
} // end validateDate
// -----------------------------------------


Class ProfitlossCalculator {

  public $casino_game_categories;
  public $statistics_daily_report_list;
  public $member_list;

  // attribute used for calculation
  private $current_member_profitloss_rule;
  private $current_base_preferential;
  private $current_distributed_preferential;

  function __construct(&$casino_game_categories, &$statistics_daily_report_list, &$member_list){

    $this->casino_game_categories = $casino_game_categories;
    $this->statistics_daily_report_list = $statistics_daily_report_list;
    $this->member_list = $member_list;

    $this->current_member_preferential_rule = null;
    $this->current_base_preferential = 0;
    $this->current_distributed_preferential = 0;
  } //end __construct

  private function getMaxProfitlossRate($member){

    if($member->isRoot()) {
      return 0;
    }

    $first_agent = null;
    if($member->isFirstLevelAgent()) {
      $first_agent = $member;
    }
    else {
      $first_agent = $this->member_list[ ($member->predecessor_id_list)[1] ];
    }

    if(!empty($first_agent->feedbackinfo)) {
      $feedbackinfo = json_decode($first_agent->feedbackinfo, true);

      if( isset($feedbackinfo['dividend']['1st_agent']['child_occupied']['max']) ){
        return $feedbackinfo['dividend']['1st_agent']['child_occupied']['max'];
      }
    }

    return 0;
  } // end getMaxProfitlossRate

  private function getLastAgentRate($member)
  {
    if($member->isRoot()) {
      return 0;
    }

    $first_agent = null;
    if($member->isFirstLevelAgent()) {
      $first_agent = $member;
    } else {
      $first_agent = $this->member_list[ ($member->predecessor_id_list)[1] ];
    }

    if(!empty($first_agent->feedbackinfo)) {
      $feedbackinfo = json_decode($first_agent->feedbackinfo, true);

      if(isset($feedbackinfo['dividend']['1st_agent']['last_occupied'])) {
        return $feedbackinfo['dividend']['1st_agent']['last_occupied'];
      }
    }

    return 0;
  }

  private function getPredecessorProfitlossRate($member){

    // get allocables
    $predecessor_allocable_list = [];

    foreach( $member->predecessor_id_list as $predecessor_id ){

      $predecessor = $this->member_list[$predecessor_id]; // return $predecessor;

      // skip root and disabled member
      if($predecessor_id == 1 || $predecessor->status != '1') continue;

      // calculate allocable
      $allocable = 0;

      if(!empty($predecessor->feedbackinfo)) {
        $feedbackinfo = json_decode($predecessor->feedbackinfo);
        // $allocable = $feedbackinfo->preferential->allocable;
        $allocable = $feedbackinfo->dividend->allocable;
      }

      $predecessor_allocable_list[] = $allocable;
    } // end foreach
    //  return $predecessor_allocable_list;

    // calculate Profitloss rates
    $max_rate = $this->getMaxProfitlossRate($member);  // 1級代理商的child_occupied max
    $reverse_predecessor_allocable_list = array_reverse($predecessor_allocable_list); // return $reverse_predecessor_allocable_list;
    $predecessor_rate_list = [];

    foreach($reverse_predecessor_allocable_list as $index => $allocable) {
      if(empty($predecessor_rate_list)) {
        $predecessor_rate_list[] = $allocable;
        continue;
      }

      $occupied = $allocable - $reverse_predecessor_allocable_list[$index - 1];

      if($occupied < 0) $occupied = 0;

      array_unshift($predecessor_rate_list, $occupied);
    } // end foreach


    // check last agent rate
    if(count($predecessor_rate_list) > 0) {
      $minRate = $this->getLastAgentRate($member);
      if($predecessor_rate_list[count($predecessor_rate_list) - 1] < $minRate) {
        $predecessor_rate_list[count($predecessor_rate_list) - 1] = $minRate;
      }
    }

    return $predecessor_rate_list;
  } // end getPredecessorProfitlossRate

  private function getProfitlossRule($member){
    if($member->isRoot()) {
      return 0;
    }

    if($member->therole == 'R') return null;

    $first_agent = null;
    if($member->isFirstLevelAgent()){
      $first_agent = $member;
    }
    else{
      $first_agent = (count($member->predecessor_id_list) > 1) ? $this->member_list[ ($member->predecessor_id_list)[1] ] : '';
    }

    if(!empty($first_agent->node_data->commission_rule) && isset(($first_agent->node_data->commission_rule)['commission'])) {
      return ($first_agent->node_data->commission_rule)['commission'];
    }
    return null;
  } // end getProfitlossRule

  // 取各娛樂城的營業日報，依所屬群組取各娛樂城的比例，與各娛樂城的營收計算。
  private function calculateBaseProfitloss($member){

    // get member's statistics daily report 取得該會元的營業日報
    $statistics_daily_report = $this->statistics_daily_report_list[$member->account] ?? [];
    // echo '<pre>',var_dump($statistics_daily_report), '</pre>';  exit();

    if(empty($statistics_daily_report)) return 0;

    $all_profitloss_amount = 0;

    // calculate total preferential
    foreach($this->casino_game_categories as $vendor => $categories) {
      foreach ($categories as $category_name) {
        // casino game data by vendor and category
        $bet_attribute_name = $vendor . '_' . $category_name . '_profitlost';

        $category_preferential = 0;
        $category_bet = $statistics_daily_report->$bet_attribute_name;

        if( isset($this->current_member_preferential_rule[strtoupper($vendor)][$category_name]) ){
          $category_preferential_ratio = $this->current_member_preferential_rule[strtoupper($vendor)][$category_name] / 100;
        }
        else{
          // 反水设定为 0 ,当找不到对应的 favorablerate 时候
          $category_preferential_ratio = 0;
          // die('反水设定 0 的状态');
        }

        $category_preferential = ($category_bet) * ($category_preferential_ratio);  // return $category_preferential; exit();

        $member->node_data->commission_detail->all_bets_amount += $category_bet;

        $member->node_data->commission_detail->setTotalBetsDetail($vendor, $category_name, $category_bet);
        $member->node_data->commission_detail->setCasinoProfitlossrates($vendor, $category_name, $category_preferential_ratio);

        $all_profitloss_amount += $category_preferential;
      } // end inner foreach
    } // end outer foreach

    $member->node_data->commission_detail->setTotalBets($member->node_data->commission_detail->all_bets_amount);
    $member->node_data->commission_detail->setTotalProfitloss($all_profitloss_amount);

    return $all_profitloss_amount; // 回傳所有娛樂城的營業損失總計
  } // end calculateBaseProfitloss


  private function distributeLevelProfitloss($member){

    $level_members_id = $member->predecessor_id_list; // 取出該會員所有上級
    unset($level_members_id[0]); // 去除掉root

    // return $member->node_data->commission_detail;
    if( $member->node_data->commission_detail->getIsBottomUp() ){
      $level_members_id = array_reverse($level_members_id);
    }

    // distribute preferential according level_favorablerate  根據level_favorablerate分配優惠
    $level_favorablerate = $this->getPredecessorProfitlossRate($member);  // 獲得前期利潤率
    // return $level_favorablerate; // Damocles test

    // 有設定之層數
    $config_level_count = count($level_favorablerate);
    $level_counter = 0;

    foreach ($level_members_id as $level_member_id) {
      // 已用完level設定
      if($level_counter == $config_level_count) break;
      // root 無反水
      if($level_member_id == 1) continue;

      $preferential_rate = $level_favorablerate[$level_counter++];
      $level_preferential = $preferential_rate * $this->current_base_preferential;

      $level_member = $this->member_list[$level_member_id];

      if($level_member->status != '1') continue;

      $level_member->node_data->commission_detail->all_profitloss_amount += $level_preferential;
      $level_member->node_data->agent_commission += $level_preferential;

      $level_member
      ->node_data
      ->commission_detail
      ->addProfitlossAmountDetail(
        $member,
        $preferential_rate,
        $level_preferential,
        $this->current_base_preferential,
        $member->node_data->commission_detail->getIsBottomUp()
      );

      $member->node_data->commission_detail->addProfitlossDistribute($level_member, $preferential_rate, $level_preferential);

      // bookkeeping
      $this->current_distributed_preferential += $level_preferential;
    } // end foreach

  } // end distributeLevelProfitloss

  private function distributeRestProfitloss($member){

    $rest_preferential = ($this->current_base_preferential - $this->current_distributed_preferential);
    if(round($rest_preferential, 4) == 0) return;

    $rest_ratio = round($rest_preferential / $this->current_base_preferential, 4);

    $first_agent = $this->member_list[ ($member->predecessor_id_list)[1] ];
    $first_agent->node_data->commission_detail->all_profitloss_amount += $rest_preferential;
    $first_agent->node_data->agent_commission += $rest_preferential;

    // var_dump($first_agent->node_data->commission_detail);

    $first_agent
    ->node_data
    ->commission_detail
    ->addProfitlossAmountDetail(
      $member,
      $rest_ratio,
      $rest_preferential,
      $this->current_base_preferential,
      $member->node_data->commission_detail->getIsBottomUp(),
      true
    );

    $member->node_data->commission_detail->addProfitlossRestDistribute($first_agent, $rest_ratio, $rest_preferential);
  } // end distributeRestProfitloss

  // 計算平台成本 (反水 + 優惠)
  private function calculatePlateformCost($member){

    if( empty($member->node_data->commission_rule) ){
      $favorable_rate = 0;
      $preferential_rate = 0;
    }
    else{
      $favorable_rate = ($member->node_data->commission_rule)['offer'] / 100;
      $preferential_rate = ($member->node_data->commission_rule)['favorable'] / 100;
    }
    // return $member->node_data->commission_rule;

    $favorable_cost = $member->node_data->agent_recursive_favorable * $favorable_rate; // return $favorable_cost;
    $preferential_cost = $member->node_data->agent_recursive_preferential * $preferential_rate; // return $preferential_cost;

    $member->node_data->commission_detail->all_profitloss_amount -= ($favorable_cost + $preferential_cost); // return $member->node_data->commission_detail->all_profitloss_amount;
    $member->node_data->agent_commission -= ($favorable_cost + $preferential_cost); // return $member->node_data->agent_commission;

    // add 1st agent's plateform_cost
    $member->node_data->commission_detail->addPlateformCost(
      'favorable',
      $member->node_data->agent_recursive_favorable,
      $favorable_rate,
      $favorable_cost
    );

    $member->node_data->commission_detail->addPlateformCost(
      'preferential',
      $member->node_data->agent_recursive_preferential,
      $preferential_rate,
      $preferential_cost
    );

    // add root's plateform_cost
    $root = $this->member_list[ ($member->predecessor_id_list)[0] ];

    $root->node_data->commission_detail->addPlateformCost(
      'favorable',
      $member->node_data->agent_recursive_favorable,
      (1 - $favorable_rate),
      ($member->node_data->agent_recursive_favorable - $favorable_cost),
      $member->account
    );

    $root->node_data->commission_detail->addPlateformCost(
      'preferential',
      $member->node_data->agent_recursive_preferential,
      (1 - $preferential_rate),
      ($member->node_data->agent_recursive_preferential - $preferential_cost),
      $member->account
    );

  } // end calculatePlateformCost

  private function calculateAgentCommissionUpper($member){
    $carry = 0;
    if($member->therole == 'A') $carry = $member->node_data->commission_detail->all_profitloss_amount;
    $member->node_data->agent_commission_upper = array_reduce(
      $member->children,
      function($carry, $child) {
        return $carry += $child->node_data->agent_commission_upper;
      },
      $carry
    );
  } // end calculateAgentCommissionUpper

  public function calculate($tree_root){

    // calculate and distribute preferential 計算和分配優惠
    MemberTreeNode::visitBottomUp($tree_root, function($member){
      // get member's preferential rule 獲得會員的優惠規則
      $this->current_member_preferential_rule = $this->getProfitlossRule($member);
      // print_r($this->current_member_preferential_rule);

      // no suitable preferential rule 沒有合適的優惠規則
      if(empty($this->current_member_preferential_rule)) return;

      // calculate total preferential 計算總優惠
    	$all_preferential_amount = $this->calculateBaseProfitloss($member); // 所有娛樂城的營業損失 (各娛樂城營業金額 * 退佣比)
      // echo '<pre>', var_dump($all_preferential_amount), '</pre>';

      // no preferential to distribute 沒有優先分發
      if($all_preferential_amount == 0) return;

      // bookkeeping distributed preferential for using later.
      $this->current_base_preferential = $all_preferential_amount;
    	$this->current_distributed_preferential = 0;

      // if member is root, no distribution is required.
    	if( $member->isRoot() || $member->isFirstLevelAgent() ){

        if( $member->isFirstLevelAgent() ){
          // 計算平台成本 (反水 + 優惠)
          $this->calculatePlateformCost($member); // echo '<pre>', var_dump($this->calculatePlateformCost($member)), '</pre>';  exit();

          // sum agent_commission_upper
          $this->calculateAgentCommissionUpper($member);
        }
    		return;
    	}

      // bottom up or top down
      $this->distributeLevelProfitloss($member); // echo '<pre>', var_dump( $this->distributeLevelProfitloss($member) ), '</pre>';  exit();

      // add rest preferential to first_agent
    	$this->distributeRestProfitloss($member); //  echo '<pre>', var_dump( $this->distributeRestProfitloss($member) ), '</pre>';  exit();

      // round member's preferential
      $member->node_data->commission_detail->all_profitloss_amount = round($member->node_data->commission_detail->all_profitloss_amount, 2);

      // sum agent_commission_upper
      $this->calculateAgentCommissionUpper($member);

      // cleanup
      $this->current_member_preferential_rule = null;
      $this->current_base_preferential = 0;
      $this->current_distributed_preferential = 0;
    }); // end MemberTreeNode::visitBottomUp
  } // end calculate

}




/**
 * [Class description]
 * @var [type]
 */
Class ProfitlossCalculationData {
  public $all_bets_amount;
  public $all_profitloss_amount;
  public $all_profitloss_amount_detail;
  public $profitloss_distribute;

  static $init_data = [
  	'all_bets_amount'              => 0,
  	'all_profitloss_amount'        => 0,
    'all_profitloss_amount_detail' => [
      'level_distribute' => [
        // [
        //   'from_id' => '',
        //   'from_account' => '',
        //   'from_level' => 0,
        //   'from_base_profitloss' => 0,
        //   'from_profitloss_rate' => 0,
        //   'from_profitloss' => 0,
        //   'is_bottom_up' => true,
        //   'is_top_down' => false,
        //   'is_rest' => false,
        // ]
      ],
      'plateform_cost' => [
        // [
        //   'type' => 'preferential',
        //   'cost_base' => 0,
        //   'cost_rate' => 0.0,
        //   'cost' => 0,
        // ],
      ]
    ],
    'profitloss_distribute' => [
      'total_bets' => 0,
      'total_bets_detail' => [],
      'casino_profitlossrates' => [],
      'total_profitloss' => 0,
      'is_bottom_up' => false,
      'is_top_down' => true,
      'level_distribute' => [
        // [
        //   'to_id' => '',
        //   'to_account' => '',
        //   'to_level' => 0,
        //   'to_base_profitloss' => 0,
        //   'to_profitloss_rate' => 0,
        //   'to_profitloss' => 0,
        // ]
      ],
      'rest_distribute' => [
        //   'to_id' => '',
        //   'to_account' => '',
        //   'to_level' => 0,
        //   'to_base_profitloss' => 0,
        //   'to_profitloss_rate' => 0,
        //   'to_profitloss' => 0,
      ]
    ],
  ];

  function __construct()
  {
    foreach (self::$init_data as $attribute => $value) {
      $this->$attribute = $value;
    }
  }

  public function setTotalBets($total_bets)
  {
    $this->profitloss_distribute['total_bets'] = $total_bets;
  }

  public function setTotalBetsDetail($vendor, $category, $bet)
  {
    $this->profitloss_distribute['total_bets_detail'][$vendor][$category] = $bet;
  }

  public function setCasinoProfitlossrates($vendor, $category, $rate)
  {
    $this->profitloss_distribute['casino_profitlossrates'][$vendor][$category] = $rate;
  }

  public function setTotalProfitloss($total_profitloss)
  {
    $this->profitloss_distribute['total_profitloss'] = $total_profitloss;
  }

  public function getTotalProfitloss()
  {
    return $this->profitloss_distribute['total_profitloss'];
  }

  public function setIsBottomUp($is_bottom_up)
  {
    $this->profitloss_distribute['is_bottom_up'] = $is_bottom_up;
    $this->profitloss_distribute['is_top_down'] = ! $is_bottom_up;
  }

  public function getIsBottomUp()
  {
    return $this->profitloss_distribute['is_bottom_up'];
  }

  public function addProfitlossAmountDetail(MemberTreeNode $member, $rate, $profitloss, $base_profitloss, $is_bottom_up, $is_rest = false)
  {
    $this->all_profitloss_amount_detail['level_distribute'][] = [
      'from_id' => $member->id,
      'from_account' => $member->account,
      'from_level' => $member->member_level,
      'base_profitloss' => $base_profitloss,
      'from_profitloss_rate' => $rate,
      'from_profitloss' => $profitloss,
      'is_bottom_up' => $is_bottom_up,
      'is_top_down' => ! $is_bottom_up,
      'is_rest' => $is_rest,
    ];
  }

  public function addPlateformCost($type, $cost_base, $cost_rate, $cost, $from_account = null)
  {
    if (empty($from_account)) {
      $this->all_profitloss_amount_detail['plateform_cost'][] = [
        'type' => $type,
        'cost_base' => $cost_base,
        'cost_rate' => $cost_rate,
        'cost' => $cost,
      ];
    } else {
      $this->all_profitloss_amount_detail['plateform_cost'][] = [
        'type' => $type,
        'cost_base' => $cost_base,
        'cost_rate' => $cost_rate,
        'cost' => $cost,
        'from_account' => $from_account,
      ];
    }
  }

  public function addProfitlossDistribute(MemberTreeNode $member, $rate, $profitloss)
  {
    $this->profitloss_distribute['level_distribute'][] = [
      'to_id' => $member->id,
      'to_account' => $member->account,
      'to_level' => $member->member_level,
      'base_profitloss' => $this->getTotalProfitloss(),
      'to_profitloss_rate' => $rate,
      'to_profitloss' => $profitloss,
    ];
  }

  public function addProfitlossRestDistribute(MemberTreeNode $member, $rate, $profitloss)
  {
    $this->profitloss_distribute['rest_distribute'] = [
      'to_id' => $member->id,
      'to_account' => $member->account,
      'to_level' => $member->member_level,
      'base_profitloss' => $this->getTotalProfitloss(),
      'to_profitloss_rate' => $rate,
      'to_profitloss' => $profitloss,
    ];
  }
}
// end of ProfitlossCalculationData





// calculate_valid_member
function calculate_valid_member($agent, array $rule) {

  // calculate valid member
  MemberTreeNode::visitBottomUp(
    $agent,
    function($member) use ($rule) {

      // agent_valid_member_count
      $member->node_data->agent_valid_member_count = array_reduce(
        $member->children,
        function($carry, $child) use ($rule) {
          if($child->node_data->member_deposit >= $rule['lowest_deposit'] && $child->node_data->member_bets >= $rule['lowest_bet']) {
            return $carry += 1;
          }
          return $carry;
        },
        0
      );

      // agent_valid_member_recursives_count
      $carry = 0;
      if($member->node_data->member_deposit >= $rule['lowest_deposit'] && $member->node_data->member_bets >= $rule['lowest_bet']) $carry = 1;
      $member->node_data->agent_valid_member_recursives_count = array_reduce(
        $member->children,
        function($carry, $child) {
          return $carry += $child->node_data->agent_valid_member_recursives_count;
        },
        $carry
      );

    }
  );

}
// end of calculate_valid_member



function get_statistics_daily_report_list($start_date, $end_date, &$casino_game_categories) {

  $transfertime_begin = gmdate('Y-m-d H:i:s.u', strtotime($start_date.' 00:00:00 -04') + 8*3600 ).'+08:00';
  $transfertime_end = gmdate('Y-m-d H:i:s.u', strtotime($end_date.' 23:59:59 -04') + 8*3600 ).'+08:00';

  /**
  * [$statistics_daily_report_list   key by member_account]
  * @var array
  */
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


  $root_statisticsdailyreport_sql =<<<SQL
  SELECT
    MAX(root_statisticsdailyreport.id) as id,
    MAX(root_statisticsdailyreport.member_id) as member_id,
    MAX(root_statisticsdailyreport.member_therole) as member_therole,
    MAX(root_statisticsdailyreport.member_account) as member_account,
    MAX(root_statisticsdailyreport.member_parent_id) as member_parent_id,
    MAX(root_statisticsdailyreport.dailydate) as dailydate,
    MAX(root_statisticsdailyreport.agency_commission) as agency_commission,
    SUM(root_statisticsdailyreport.tokendeposit) as tokendeposit,
    SUM(root_statisticsdailyreport.tokenfavorable) as tokenfavorable,
    SUM(root_statisticsdailyreport.tokenpreferential) as tokenpreferential,
    SUM(root_statisticsdailyreport.tokenpay) as tokenpay,
    SUM(root_statisticsdailyreport.tokengcash) as tokengcash,
    SUM(root_statisticsdailyreport.tokenrecycling) as tokenrecycling,
    SUM(root_statisticsdailyreport.cashdeposit) as cashdeposit,
    SUM(root_statisticsdailyreport.payonlinedeposit) as payonlinedeposit,
    SUM(root_statisticsdailyreport.cashtransfer) as cashtransfer,
    SUM(root_statisticsdailyreport.cashwithdrawal) as cashwithdrawal,
    SUM(root_statisticsdailyreport.cashgtoken) as cashgtoken,

    SUM(root_statisticsdailyreport.cashadministrationfees) as cashadministrationfees,
    SUM(root_statisticsdailyreport.tokenadministrationfees) as tokenadministrationfees,
    SUM(root_statisticsdailyreport.tokenadministration) as tokenadministration,
    SUM(root_statisticsdailyreport.apicashwithdrawal) as apicashwithdrawal,
    SUM(root_statisticsdailyreport.ec_sales) as ec_sales,
    SUM(root_statisticsdailyreport.ec_cost) as ec_cost,
    SUM(root_statisticsdailyreport.ec_profitlost) as ec_profitlost,
    SUM(root_statisticsdailyreport.ec_count) as ec_count,
    SUM(root_statisticsdailyreport.member_gcash) as member_gcash,
    SUM(root_statisticsdailyreport.member_gtoken) as member_gtoken,
    SUM(root_statisticsdailyreport.member_commission) as member_commission,
    SUM(root_statisticsdailyreport.member_prefer) as member_prefer,
    SUM(root_statisticsdailyreport.member_bonus) as member_bonus,

    SUM(root_statisticsdailyreport.all_bets) as all_bets,
    SUM(root_statisticsdailyreport.all_wins) as all_wins,
    SUM(root_statisticsdailyreport.all_profitlost) as all_profitlost,
    SUM(root_statisticsdailyreport.all_count) as all_count,

    $casino_attributes_sql

    MAX(preferential_sum.preferential) as all_favorablerate_amount,
    MAX(cashfee_sum.cashfee) as all_cashfee

  FROM root_statisticsdailyreport
  LEFT JOIN (
      SELECT
        root_statisticsdailypreferential.member_id,
        SUM(root_statisticsdailypreferential.all_favorablerate_amount) as preferential
      FROM root_statisticsdailypreferential
      WHERE root_statisticsdailypreferential.dailydate >= '$start_date'
        AND root_statisticsdailypreferential.dailydate <= '$end_date'
      GROUP BY root_statisticsdailypreferential.member_id
    ) preferential_sum
    ON preferential_sum.member_id = root_statisticsdailyreport.member_id
  LEFT JOIN (
      SELECT
        root_deposit_onlinepay_summons.account,
        SUM(root_deposit_onlinepay_summons.cashfee_amount) as cashfee
      FROM root_deposit_onlinepay_summons
      WHERE root_deposit_onlinepay_summons.transfertime >= '$transfertime_begin'
        AND root_deposit_onlinepay_summons.transfertime <= '$transfertime_end'
      GROUP BY root_deposit_onlinepay_summons.account
    ) cashfee_sum
    ON cashfee_sum.account = root_statisticsdailyreport.member_account

  WHERE root_statisticsdailyreport.dailydate >= '$start_date'
    AND root_statisticsdailyreport.dailydate <= '$end_date'
  GROUP BY root_statisticsdailyreport.member_id
  ;
SQL;

  $root_statisticsdailyreport_sql_result = runSQLall($root_statisticsdailyreport_sql);


  if($root_statisticsdailyreport_sql_result[0] < 1) {
    return $statistics_daily_report_list;
  }

  // construct $statistics_daily_report_list
  unset($root_statisticsdailyreport_sql_result[0]);
  foreach($root_statisticsdailyreport_sql_result as $report) {
    $statistics_daily_report_list[$report->member_account] = $report;
  }

  // print_r($statistics_daily_report_list);


  return $statistics_daily_report_list;
}

function get_commission_rules() {

  // query commission rules
  $commission_rules_sql = "SELECT * FROM root_commission ORDER BY name ASC, payoff DESC;";
  $commission_rules_sql_result = runSQLall($commission_rules_sql);

  $commission_rules = [];

  // construct commission rules mapping
  foreach ($commission_rules_sql_result as $index => $rule) {
    if($index == 0) continue;

    $commission_rules[$rule->name][$rule->payoff] = [
      'lowest_bet' => $rule->lowest_bet,
      'lowest_deposit' => $rule->lowest_deposit,
      'effective_member' => $rule->effective_member,
      'offer' => $rule->offer,
      'favorable' => $rule->favorable,
      'commission' => json_decode($rule->commission, true),
    ];

  }

  return $commission_rules;
}


function batched_insert(&$sql_buffer, $insert_rest_count, $batch_count = 100) {
  if($insert_rest_count <= $batch_count) {
    $sql = implode($sql_buffer, ';');
    runSQLtransactions($sql);
    $sql_buffer = [];

    return;
  }

  if(count($sql_buffer) >= $batch_count) {
    $sql = implode($sql_buffer, ';');
    runSQLtransactions($sql);
    $sql_buffer = [];
  }
}

function insert_or_update_commission_sql(array $commission_dailyreport_data) {
  // print_r($commission_dailyreport_data);

  $attributes = array_keys($commission_dailyreport_data);
  $values = array_values($commission_dailyreport_data);

  $attributes_string = implode(',', $attributes);
  $values_string = "'" . implode("','", $values) . "'";

  $get_set_string_fun = function($attribute, $value) {
    return "$attribute = '$value'";
  };

  $set_string = implode(',', array_map($get_set_string_fun, $attributes, $values) );

  $insert_or_update_sql =<<<SQL
    INSERT INTO root_commission_dailyreport ($attributes_string)
      VALUES($values_string)
    ON CONFLICT ON CONSTRAINT root_commission_dailyreport_member_account_dailydate
    DO
      UPDATE
      SET $set_string
    ;
SQL;

  return $insert_or_update_sql;
} // end insert_or_update_commission_sql

/**
 * Todo : need to test
 * @param  array  $summary_attributes    [description]
 * @param  array  $summary_data          [description]
 * @param  array  $profitlost_attributes [description]
 * @param  array  $profitlost_data       [description]
 * @return [type]                        [description]
 */
function export_agent_profitlost_to_csv($current_datepicker_start, $current_datepicker_end, array $casino_game_categories, array $agent_profitlost_data,$sum_commission) {
  global $tr;
  $casinoLib = new casino_switch_process_lib();
  $debug = 0;

  if ($agent_profitlost_data[0] >= 1) {
      $j = $v = 1;
      // 總表欄位名稱
      $csv_summary[0][$v++] = $tr['date'];//'日期';
      $csv_summary[0][$v++] = $tr['Number of data'];//資料筆數
      $csv_summary[0][$v++] = $tr['numbers of suppliers'];//代理商总计
      $csv_summary[0][$v++] = $tr['total member'];//'會員總計';
      $csv_summary[0][$v++] = $tr['total betting'];//'總投注量';
      $csv_summary[0][$v++] = $tr['profit and loss of casino'];//'娱乐城损益';
      $csv_summary[0][$v++] = $tr['profit and loss of casino total betting'];//'娱乐城损益 / 总投注量 (%)';
      $csv_summary[0][$v++] = $tr['total commission(only for positive)'] ;//'分佣合計(只計算正值)';
      $csv_summary[0][$v++] = $tr['total accumulated to the next sub-commission (negative value)'];//'累計到下次分佣的總計(負值)';
      $csv_summary[0][$v++] = $tr['total commission (positive + negative)'];//'分佣总计(正 + 负)';
      $csv_summary[0][$v++] = $tr['total commission (positive + negative) / total bet amount (%)'];//'分佣總計(正 + 負) / 總投注量 (%)';
      $csv_summary[0][$v++] = $tr['profit of administrator'].'('.$tr['profit of administrator'].'/'.$tr['total profit and loss'].')';//站长利润(站長利潤/總損益);
      $csv_summary[0][$v++] = $tr['Agent sub-commission total'].'(分佣合計/'.$tr['profit and loss of casino'].')';//'總分佣 / 總投注量';

      // 娛樂城遊戲分類損益欄位名稱
      foreach ($casino_game_categories as $vendor => $categories) {
          foreach ($categories as $category_name) {
              $vendor_category_name = $casinoLib->getCasinoDefaultName(strtoupper($vendor), $debug). ' ' .$tr[$category_name];
              $csv_summary[0][$v++]=$vendor_category_name . $tr['profit and loss'];
          }
      }


      // 站長利潤=娛樂城損益-分佣總計(正+負)
      $webmaster_profit= number_format(($agent_profitlost_data[1]->agent_recursive_sumbetsprofit) - ($sum_commission['sum_member_profitamount_html']), 2, '.', '');
      //站長利潤/娛樂城總損益
      $webmaster_divide_profit = number_format(100 * ($webmaster_profit / $agent_profitlost_data[1]->agent_recursive_sumbetsprofit), 2, '.', '') . '%';
      //(分佣合计/娱乐城损益)
      $bonus_divide_profit = number_format(100 * ($sum_commission['sum_member_profitamount_html'] / $agent_profitlost_data[1]->agent_recursive_sumbetsprofit), 2, '.', '') . '%';

      $v=1;
      $csv_summary[1][$v++] = $current_datepicker_start. ' ~ ' . $current_datepicker_end;//統計區間
      $csv_summary[1][$v++] = count($agent_profitlost_data)-2;//資料筆數，因為要扣除root不能算，所以減2
      $csv_summary[1][$v++] = count($agent_profitlost_data)-2;//代理商總計，因為要扣除root不能算，所以減2
      $csv_summary[1][$v++] = ($agent_profitlost_data[1])->agent_recursives_count;//會員總計
      $csv_summary[1][$v++] = ($agent_profitlost_data[1])->agent_recursive_sumbets;//总投注量
      $csv_summary[1][$v++] = ($agent_profitlost_data[1])->agent_recursive_sumbetsprofit;//娱乐城损益
      $csv_summary[1][$v++] = number_format(100*($agent_profitlost_data[1])->agent_recursive_sumbetsprofit/($agent_profitlost_data[1])->agent_recursive_sumbets, 2, '.', ',') . ' %';  //娱乐城损益 / 总投注量 (%)
      $csv_summary[1][$v++] = $sum_commission['sum_member_profitamount_pos_html'];//分佣合计(只计算正值)
      $csv_summary[1][$v++] = $sum_commission['sum_member_profitamount_negitive_html'];//累计到下次分佣的总计(负值)
      $csv_summary[1][$v++] = $sum_commission['sum_member_profitamount_html'];//分佣总计(正 + 负)
      $csv_summary[1][$v++] = number_format((100*$sum_commission['sum_member_profitamount_html']/($agent_profitlost_data[1]->agent_recursive_sumbets)) , 2, '.', ',').'%';//'分佣總計(正 + 負) / 總投注量 (%)'
      $csv_summary[1][$v++] = $webmaster_profit.'('.$webmaster_divide_profit.')';// 站長利潤=娛樂城損益-分佣總計(正+負) , (站長利潤/娛樂城總損益)
      $csv_summary[1][$v++] = $sum_commission['sum_member_profitamount_html'].'('.$bonus_divide_profit.')';

      // 娛樂城遊戲分類損益欄位值
      foreach ($casino_game_categories as $vendor => $categories) {
          foreach ($categories as $category_name) {
              $profitlost_attribute = $vendor . '_' . $category_name . '_profitlost_recursives_sum';
              $csv_summary[1][$v++] = (isset(($agent_profitlost_data[1])->$profitlost_attribute)) ? ($agent_profitlost_data[1])->$profitlost_attribute :'0';
          }
      }

      // 使用者資料陣列
      $v = 1;
      // $xls_userdata[0][$v++]=$tr['item'];//項次
      $xls_userdata[0][$v++] = $tr['ID']; // 序号

      $xls_userdata[0][$v++]=$tr['Account'];//'帐号',
      $xls_userdata[0][$v++]=$tr['Membership type'];//'会员类型',
      $xls_userdata[0][$v++]=$tr['Members referrer'];//'会员的推荐人',
      $xls_userdata[0][$v++]=$tr['Class'];//'所在阶层',

      $xls_userdata[0][$v++]=$tr['Betting amount for members directly under the agent'];//'代理商直属会员的投注额',
      $xls_userdata[0][$v++]=$tr['Total entertainment gain loss of agent directly affiliated members'];//'代理商直属会员的娱乐城损益合计',
      $xls_userdata[0][$v++]=$tr['Accumulation of betting amount generated by the agent downline'];//'代理商的下线产生的投注额累计',
      $xls_userdata[0][$v++]=$tr['Profit and loss accumulated by agents downline'];//'代理商的下线产生的损益累计',

      $xls_userdata[0][$v++]=$tr['Agent profit and loss'];//'代理商损益合计',
      $xls_userdata[0][$v++]=$tr['Agent downline withdrawal total'];   //'代理商下线提款合计',
      $xls_userdata[0][$v++]=$tr['Agent downline deposit total'];      //'代理商下线存款合计',

      $xls_userdata[0][$v++]=$tr['Agent directly under the number of members betting > 0'];//'代理商直属会员数量(投注>0)',
      $xls_userdata[0][$v++]=$tr['Agent total number of members betting > 0'];    //'代理商累计会员数量(投注>0)',

      $xls_userdata[0][$v++]=$tr['Agents directly under the number of agents'];   //'代理商直属代理商数量',
      $xls_userdata[0][$v++]=$tr['Agents accumulated agents'];                    //'代理商累计代理商数量',

      $xls_userdata[0][$v++]=$tr['Agent directly under the number of members'];   //'代理商直属会员数量',
      $xls_userdata[0][$v++]=$tr['Agent total number of members'];                //'代理商累计会员数量',
      foreach ($casino_game_categories as $vendor => $categories) {
          foreach ($categories as $category_name) {
              $vendor_category_name = $casinoLib->getCasinoDefaultName(strtoupper($vendor), $debug). ' ' .$tr[$category_name];
              $xls_userdata[0][$v++] = $vendor_category_name . ' 代理商累计投注量';
              $xls_userdata[0][$v++] = $vendor_category_name . ' 代理商累计损益';
          }
      }
      // $xls_userdata[0][$v++]=$tr['Commission from on the upline']; //来自上线之佣金
      // $xls_userdata[0][$v++]=$tr['Commission to go downline']; //给下线之佣金
      $xls_userdata[0][$v++]='代理商佣金';


      for ($i = 2; $i <= $agent_profitlost_data[0]; $i++) {
          $v = 1;
          $xls_userdata[$j][$v++] =$j; //項次
          $xls_userdata[$j][$v++] =$agent_profitlost_data[$i]->member_account;
          $xls_userdata[$j][$v++] =$agent_profitlost_data[$i]->member_therole;
          $xls_userdata[$j][$v++] =$agent_profitlost_data[$i]->member_parent_account=='root'?'-':$agent_profitlost_data[$i]->member_parent_account; //会员的推荐人
          $xls_userdata[$j][$v++] =$agent_profitlost_data[$i]->member_level;          //所在阶层

          $xls_userdata[$j][$v++] =$agent_profitlost_data[$i]->agent_bets;            //代理商直属会员的投注额
          $xls_userdata[$j][$v++] =$agent_profitlost_data[$i]->agent_betsprofit;      //代理商直属会员的娱乐城损益合计
          $xls_userdata[$j][$v++] =$agent_profitlost_data[$i]->agent_recursive_sumbets;//代理商的下线产生的投注额累计
          $xls_userdata[$j][$v++] =$agent_profitlost_data[$i]->agent_recursive_sumbetsprofit;//代理商的下线产生的损益累计

          $xls_userdata[$j][$v++] =$agent_profitlost_data[$i]->agent_profitlost;//代理商损益合计
          $xls_userdata[$j][$v++] =$agent_profitlost_data[$i]->agent_sumwithdrawals;//代理商下线提款合计
          $xls_userdata[$j][$v++] =$agent_profitlost_data[$i]->agent_sumdeposit;//代理商下线存款合计

          //代理商直属会员数量(投注>0)
          $xls_userdata[$j][$v++] =$agent_profitlost_data[$i]->agent_memberbet_count==0?'0':$agent_profitlost_data[$i]->agent_memberbet_count;
          //代理商累计会员数量(投注>0)
          $xls_userdata[$j][$v++] =$agent_profitlost_data[$i]->agent_memberrecursivebets_count==0?'0':$agent_profitlost_data[$i]->agent_memberrecursivebets_count;

          //代理商直属代理商数量
          $xls_userdata[$j][$v++] =$agent_profitlost_data[$i]->agent_agent_count==0?'0':$agent_profitlost_data[$i]->agent_agent_count;
          //代理商累计代理商数量
          $xls_userdata[$j][$v++] =$agent_profitlost_data[$i]->agent_recursive_agent_count==0?'0':$agent_profitlost_data[$i]->agent_recursive_agent_count;

          //代理商直属会员数量
          $xls_userdata[$j][$v++] =$agent_profitlost_data[$i]->agent_member_count==0?'0':$agent_profitlost_data[$i]->agent_member_count;
          //代理商累计会员数量
          $xls_userdata[$j][$v++] =$agent_profitlost_data[$i]->agent_recursives_count==0?'0':$agent_profitlost_data[$i]->agent_recursives_count;

          foreach ($casino_game_categories as $vendor => $categories) {
            foreach ($categories as $category_name) {
                $vendor_category_bet        = $vendor . '_' . $category_name.'_bets_recursives_sum';
                $vendor_category_profitlost = $vendor . '_' . $category_name.'_profitlost_recursives_sum';
                // var_dump($vendor_category_prefix);
                $xls_userdata[$j][$v++] = $agent_profitlost_data[$i]->{$vendor_category_bet};
                $xls_userdata[$j][$v++] = $agent_profitlost_data[$i]->{$vendor_category_profitlost};
                // $xls_userdata[$j][$v++] = $agent_profitlost_data[$i]->vendor_category_prefix.'_profitlost_recursives_sum';
            }
          }

          // die();
          // $xls_userdata[$j][$v++] = $agent_profitlost_data[$i]->agent_commission_upper; //来自上线之佣金
          // $xls_userdata[$j][$v++] = $agent_profitlost_data[$i]->agent_commission_lower; //给下线之佣金
          $xls_userdata[$j][$v++] = $agent_profitlost_data[$i]->agent_commission; //代理商佣金

          $j++;
      }
  // print("<pre>" . print_r($xls_userdata, true) . "</pre>");die();



  } else {
      echo '<script>alert("无娱乐城佣金资料!! (error code：190621) ");history.go(-1);</script>';
      die();
  }

  // 清除快取以防亂碼
  ob_end_clean();

  //---------------phpspreadsheet----------------------------
  $spreadsheet = new Spreadsheet();

  // Create a new worksheet called "My Data"
  $myWorkSheet = new \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet($spreadsheet, '娱乐城佣金计算');

  // Attach the "My Data" worksheet as the first worksheet in the Spreadsheet object
  $spreadsheet->addSheet($myWorkSheet, 0);

  // 總表索引標籤開始寫入資料
  $sheet = $spreadsheet->setActiveSheetIndex(0);

  // 寫入總表資料陣列
  $sheet->fromArray($csv_summary, null, 'B1');
  $sheet->fromArray($xls_userdata, null, 'A4');

  // ------------------------------------------------------------------------------------
  // 2020-1-7
  // 值置右:
  // 娛樂城損益/總投注量
  $worksheet = $spreadsheet->getActiveSheet()->getStyle('H2')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_RIGHT);
  // 分佣總計(正+負)/總投注量
  $worksheet = $spreadsheet->getActiveSheet()->getStyle('L2')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_RIGHT);
  // 站長利潤(站長利潤/總損益)
  $worksheet = $spreadsheet->getActiveSheet()->getStyle('M2')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_RIGHT);
  // 代理商分佣總計(分佣合計/娛樂城損益)
  $worksheet = $spreadsheet->getActiveSheet()->getStyle('N2')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_RIGHT);
  // -------------------------------------------------------------------------------------

  // 自動寬度
  $worksheet = $spreadsheet->getActiveSheet();
  $colIndexStr = $worksheet->getHighestColumn();
  $colIndex = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($colIndexStr);
  for ($i = 1; $i < $colIndex; $i++) {
      $worksheet->getColumnDimensionByColumn($i)->setAutoSize(true);
  }


  // xlsx
  $file_name = 'agent_commission_' . date('ymd_His', time());
  // var_dump($file_name);die();
  header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
  header('Content-Disposition: attachment;filename="' . $file_name . '.xlsx"');
  header('Cache-Control: max-age=0');

  // 直接匯出，不存於disk
  $writer = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($spreadsheet, 'Xlsx');
  $writer->save('php://output');


}

function commission_sql($sdate,$edate){
    $sql =<<<SQL
          SELECT SUM(agent_commission) as agent_commission
          FROM root_commission_dailyreport
          WHERE member_therole = 'A'
            AND agent_commission >= 0
            AND dailydate = '{$sdate}'
            AND end_date  = '{$edate}'
          GROUP BY member_therole
          ;
SQL;
    return $sql;
}

function commission_sql_negitive($sdate, $edate)
{
    $sql = <<<SQL
          SELECT SUM(agent_commission) as agent_commission
          FROM root_commission_dailyreport
          WHERE member_therole = 'A'
            AND agent_commission <= 0
            AND dailydate = '{$sdate}'
            AND end_date  = '{$edate}'
          GROUP BY member_therole
          ;
SQL;
    return $sql;
}

// 解析娛樂城遊戲分類，組成撈db的json欄位的sql字串
function parsing_casino_cate_sql($casino_game_categories)
{
    $casino_attributes_sql = '';
    foreach ($casino_game_categories as $casino => $categories) {
        foreach ($categories as $category) {
            $casino_category = $casino . '_' . $category;

            $casino_attributes_sql .= "coalesce( SUM( (casino_bet_detail->>'" . $casino_category . '_bets_recursives_sum' . "') :: numeric(20,2)) , 0) as " . $casino_category . '_bets_recursives_sum' . ",";
            $casino_attributes_sql .= "coalesce( SUM( (casino_profitlost_detail->>'" . $casino_category . '_profitlost_recursives_sum' . "') :: numeric(20,2)) , 0) as " . $casino_category . '_profitlost_recursives_sum' . ",";
        }
    }
    return $casino_attributes_sql;
}


?>
