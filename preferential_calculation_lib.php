<?php
// ----------------------------------------------------------------------------
// Features :	後台 --  反水計算 LIB
// File Name: preferential_calculation_lib.php
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

require_once dirname(__FILE__) ."/lib_member_tree.php";


// ---------------------------------------------------------------
// check date format
// ---------------------------------------------------------------
// get example: ?current_datepicker=2017-02-03
// ref: http://php.net/manual/en/function.checkdate.php
function validateDate($date, $format = 'Y-m-d H:i:s')
{
    $d = DateTime::createFromFormat($format, $date);
    return $d && $d->format($format) == $date;
}
// -----------------------------------------


class PreferentialCalculator {

  use PreferentialCalculatorInitTrait;

  public $casino_game_categories;
  public $preferential_rule_list;
  public $statistics_daily_report_list;
  public $member_list;
  public $member_tree_root;

  // attribute used for calculation
  private $current_member_preferential_rule;
  private $current_base_preferential;
  private $current_distributed_preferential;

  function __construct($date)
  {
    $this->casino_game_categories = $this->getCasinoGameCategories();
    $this->preferential_rule_list = $this->getFavorableRules();
    $this->statistics_daily_report_list = $this->getStatisticsDailyReportList($date);

    $this->current_member_preferential_rule = null;
    $this->current_base_preferential = null;
    $this->current_distributed_preferential = null;
  }

  public function setMemberList(&$member_list, $root_id = null)
  {
    $this->member_list = $member_list;

    if(empty($root_id)) {
      $this->member_tree_root = $member_list[1];
    } else {
      $this->member_tree_root = $member_list[$root_id];
    }
  }

  public function settingCheck()
  {
    $check_result = (object) [
      'passed' => true,
      'member_with_config_error' => [],
    ];

    // calculate and distribute preferential
    MemberTreeNode::visitBottomUp($this->member_tree_root, function($member) use($check_result) {
      // get member's preferential rule
      $preferential_rule = $this->getPreferentialRule($member);

      if(empty($preferential_rule)) return;

      $rate_sum = $preferential_rule['self_favorablerate'] + $preferential_rule['first_agent_favorablerate'];

      foreach($preferential_rule['level_favorablerate'] as $rate) {
        $rate_sum += $rate;
      }

      if( $rate_sum > 1 ) {
        $check_result->passed = false;
        $check_result->member_with_config_error[] = [
          'id' => $member->id,
          'account' => $member->account,
        ];
      }

    });

    return $check_result;
  }

  private function getMaxPreferentialRate($member)
  {
    if($member->isRoot()) {
      return 0;
    }

    $first_agent = null;
    if($member->isFirstLevelAgent()) {
      $first_agent = $member;
    } else {
      $first_agent = $this->member_list[ ($member->predecessor_id_list)[0] ];
    }

    if(!empty($first_agent->feedbackinfo)) {
      $feedbackinfo = json_decode($first_agent->feedbackinfo, true);

      if(isset($feedbackinfo['preferential']['1st_agent']['child_occupied']['max'])) {
        return $feedbackinfo['preferential']['1st_agent']['child_occupied']['max'];
      }
    }

    return 0;
  }

  private function getSelfPreferentialRate($member)
  {
    if($member->isRoot()) {
      return 0;
    }

    $first_agent = null;
    if($member->isFirstLevelAgent()) {
      $first_agent = $member;
    } else {
      $first_agent = $this->member_list[ ($member->predecessor_id_list)[0] ];
    }

    if(!empty($first_agent->feedbackinfo)) {
      $feedbackinfo = json_decode($first_agent->feedbackinfo, true);

      if(isset($feedbackinfo['preferential']['1st_agent']['self_ratio'])) {
        return $feedbackinfo['preferential']['1st_agent']['self_ratio'];
      }
    }

    return 0;
  }

  private function getLastAgentRate($member)
  {
    if($member->isRoot()) {
      return 0;
    }

    $first_agent = null;
    if($member->isFirstLevelAgent()) {
      $first_agent = $member;
    } else {
      $first_agent = $this->member_list[ ($member->predecessor_id_list)[0] ];
    }

    if(!empty($first_agent->feedbackinfo)) {
      $feedbackinfo = json_decode($first_agent->feedbackinfo, true);

      if(isset($feedbackinfo['preferential']['1st_agent']['last_occupied'])) {
        return $feedbackinfo['preferential']['1st_agent']['last_occupied'];
      }
    }

    return 0;
  }

  private function getPredecessorPreferentialRate($member)
  {

    // get allocables
    $predecessor_allocable_list = [];

    foreach ($member->predecessor_id_list as $predecessor_id) {

      $predecessor = $this->member_list[$predecessor_id];

      // skip root and disabled member
      if($predecessor_id == 1 || $predecessor->status != '1') continue;

      // calculate allocable
      $allocable = 0;

      if(!empty($predecessor->feedbackinfo)) {
        $feedbackinfo = json_decode($predecessor->feedbackinfo);
        $allocable = $feedbackinfo->preferential->allocable;
      }

      $predecessor_allocable_list[] = $allocable;
    }

    // calculate preferential rates
    $max_rate = $this->getMaxPreferentialRate($member);
    $reverse_predecessor_allocable_list = array_reverse($predecessor_allocable_list);
    $predecessor_rate_list = [];

    foreach($reverse_predecessor_allocable_list as $index => $allocable) {
      if(empty($predecessor_rate_list)) {
        $predecessor_rate_list[] = $allocable;
        continue;
      }

      $occupied = $allocable - $reverse_predecessor_allocable_list[$index - 1];

      if($occupied < 0) $occupied = 0;

      array_unshift($predecessor_rate_list, $occupied);
    }


    // check last agent rate
    if(count($predecessor_rate_list) > 0) {
      $minRate = $this->getLastAgentRate($member);

      // if(!isset($predecessor_rate_list[count($predecessor_rate_list) - 1])) {
      //   print_r($predecessor_rate_list);
      // }

      if($predecessor_rate_list[count($predecessor_rate_list) - 1] < $minRate) {
        $predecessor_rate_list[count($predecessor_rate_list) - 1] = $minRate;
      }
    }

    return $predecessor_rate_list;
  }

  private function getPreferentialRule($member)
  {
    // get member's statistics daily report
    $statistics_daily_report = $this->statistics_daily_report_list[$member->account];

    if(!isset($this->preferential_rule_list[$member->favorablerule])) return null;

    // get member's preferential rule
    $preferential_rule = null;

    foreach($this->preferential_rule_list[$member->favorablerule] as $wagger => $rule) {
      if($statistics_daily_report->all_bets < $wagger) continue;

      $preferential_rule = $rule;
      $member->node_data->favorable_audit = $rule['audit'];

      $preferential_rule['self_favorablerate'] = $this->getSelfPreferentialRate($member);

      $predecessor_rate_list = $this->getPredecessorPreferentialRate($member);

      if(! isset($predecessor_rate_list[0])) break;

      $preferential_rule['first_agent_favorablerate'] = $predecessor_rate_list[0];

      unset($predecessor_rate_list[0]);

      $preferential_rule['level_favorablerate'] = array_values($predecessor_rate_list);

      break;
    }

    return $preferential_rule;
  }

  private function calculateBasePreferential($member)
  {
    // get member's statistics daily report
    $statistics_daily_report = $this->statistics_daily_report_list[$member->account];

    $all_preferential_amount = 0;

    // calculate total preferential
    foreach($this->casino_game_categories as $vendor => $categories) {
      foreach ($categories as $category_name) {
        // casino game data by vendor and category
        $bet_attribute_name = $vendor . '_' . $category_name . '_bets';

        $category_preferential = 0;
        $category_bet = ( json_decode($statistics_daily_report->betlog_detail, true) )[$bet_attribute_name] ?? 0;

        if(isset($this->current_member_preferential_rule['favorablerate'][strtoupper($vendor)][$category_name])) {
          $category_preferential_ratio = $this->current_member_preferential_rule['favorablerate'][strtoupper($vendor)][$category_name] / 100;
        }else{
          // 反水设定为 0 ,当找不到对应的 favorablerate 时候
          $category_preferential_ratio = 0;
        }
        $category_preferential = ($category_bet) * ($category_preferential_ratio);

        $member->node_data->all_bets_amount += $category_bet;

        $member->node_data->setTotalBetsDetail($vendor, $category_name, $category_bet);
        $member->node_data->setCasinoFavorablerates($vendor, $category_name, $category_preferential_ratio);

        $all_preferential_amount += $category_preferential;
      }
    }

    $member->node_data->setTotalBets($member->node_data->all_bets_amount);
    $member->node_data->setTotalFavorable($all_preferential_amount);

    return $all_preferential_amount;
  }

  private function distributeSelfPreferential($member)
  {
    $self_favorablerate = $this->getSelfPreferentialRate($member);
    $self_preferential = $self_favorablerate * $this->current_base_preferential;
    $member->node_data->all_favorablerate_amount += $self_preferential;
    $member->node_data->setSelfFavorable(round($self_preferential, 2));
    $member->node_data->setSelfFavorableRate($self_favorablerate);

    // bookkeeping
    $this->current_distributed_preferential += $self_preferential;
  }

  private function distributeFirstAgentPreferential($member)
  {
    $first_agent_ratio = $this->current_member_preferential_rule['first_agent_favorablerate'];
    $first_agent_preferential = $first_agent_ratio * $this->current_base_preferential;
    $first_agent = $this->member_list[ ($member->predecessor_id_list)[0] ];
    $first_agent->node_data->all_favorablerate_amount += $first_agent_preferential;

    $first_agent
    ->node_data
    ->addFavorableAmountDetail(
      $member,
      $first_agent_ratio,
      $first_agent_preferential,
      $this->current_base_preferential,
      $this->current_member_preferential_rule['is_bottom_up']
    );

    $member->node_data->addFavorableDistribute($first_agent, $first_agent_ratio, $first_agent_preferential);

    // bookkeeping
    $this->current_distributed_preferential += $first_agent_preferential;
  }

  private function distributeLevelPreferential($member)
  {
    $level_members_id = $member->predecessor_id_list;

    // skip first agent
    unset($level_members_id[0]);

    if($this->current_member_preferential_rule['is_bottom_up']) {
      $level_members_id = array_reverse($level_members_id);
    }

    // distribute preferential according level_favorablerate
    $level_favorablerate = $this->current_member_preferential_rule['level_favorablerate'];
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

      // skip disabled member
      if($level_member->status != '1') continue;

      $level_member->node_data->all_favorablerate_amount += $level_preferential;

      $level_member
      ->node_data
      ->addFavorableAmountDetail(
        $member,
        $preferential_rate,
        $level_preferential,
        $this->current_base_preferential,
        $this->current_member_preferential_rule['is_bottom_up']
      );

      $member->node_data->addFavorableDistribute($level_member, $preferential_rate, $level_preferential);

      // bookkeeping
      $this->current_distributed_preferential += $level_preferential;
    }
  }

  private function distributeRestPreferential($member)
  {
    $rest_preferential = ($this->current_base_preferential - $this->current_distributed_preferential);
    if(round($rest_preferential, 4) == 0) return;

    $rest_ratio = round($rest_preferential / $this->current_base_preferential, 4);

    $first_agent = $this->member_list[ ($member->predecessor_id_list)[0] ];
    $first_agent->node_data->all_favorablerate_amount += $rest_preferential;

    $first_agent
    ->node_data
    ->addFavorableAmountDetail(
      $member,
      $rest_ratio,
      $rest_preferential,
      $this->current_base_preferential,
      $this->current_member_preferential_rule['is_bottom_up'],
      true
    );

    $member->node_data->addFavorableRestDistribute($first_agent, $rest_ratio, $rest_preferential);
  }

  public function calculate()
  {

    // calculate and distribute preferential
    MemberTreeNode::visitBottomUp($this->member_tree_root, function($member) {

    	// get member's statistics daily report
    	$statistics_daily_report = $this->statistics_daily_report_list[$member->account];

      $member->node_data->all_bets_amount;

      // get member's preferential rule
      $this->current_member_preferential_rule = $this->getPreferentialRule($member);

      // calculate total preferential
    	$all_preferential_amount = $this->calculateBasePreferential($member);

      // no suitable preferential rule
      if(empty($this->current_member_preferential_rule)) return;

    	// no preferential to distribute
    	if($all_preferential_amount <= 0) return;

    	// bookkeeping distributed preferential for using later.
      $this->current_base_preferential = $all_preferential_amount;
    	$this->current_distributed_preferential = 0;

    	// if member is first agent or root, no distribution is required.
    	if($member->isRoot() || $member->isFirstLevelAgent()) {
    		$member->node_data->all_favorablerate_amount += $all_preferential_amount;
    		$member->node_data->all_favorablerate_amount = round($member->node_data->all_favorablerate_amount, 2);

        $member->node_data->setSelfFavorable(round($all_preferential_amount, 2));
        $member->node_data->setSelfFavorableRate(1);
    		return;
    	}

    	// 自身反水
      $this->distributeSelfPreferential($member);

    	// 一级代理反水
      $this->distributeFirstAgentPreferential($member);

    	// bottom up or top down 反水
      $this->distributeLevelPreferential($member);

    	// add rest preferential to first_agent
    	$this->distributeRestPreferential($member);

    	// round member's preferential
      $member->node_data->all_favorablerate_amount = round($member->node_data->all_favorablerate_amount, 2);

      // cleanup
      $this->current_member_preferential_rule = null;
      $this->current_base_preferential = 0;
      $this->current_distributed_preferential = 0;
    });
    // end of calculate and distribute preferential

  }

}



/**
 * PreferentialCalculatorInitTrait
 */
trait PreferentialCalculatorInitTrait
{

  private function getStatisticsDailyReportList($date)
  {
    /**
    * [$statistics_daily_report_list   key by member_account]
    * @var array
    */
    $statistics_daily_report_list = [];

    $root_statisticsdailyreport_sql = "
    SELECT * FROM root_statisticsdailyreport
    LEFT JOIN root_statisticsdailyreport_detail ON root_statisticsdailyreport_detail.id=root_statisticsdailyreport.id
    WHERE root_statisticsdailyreport.dailydate ='" . $date . "'
      AND root_statisticsdailyreport.member_therole != 'R'
      AND root_statisticsdailyreport.member_account != 'root'
    ;
    ";

    $root_statisticsdailyreport_sql_result = runSQLall($root_statisticsdailyreport_sql);

    if($root_statisticsdailyreport_sql_result[0] < 1) {
      return $statistics_daily_report_list;
    }

    // construct $statistics_daily_report_list
    unset($root_statisticsdailyreport_sql_result[0]);
    foreach($root_statisticsdailyreport_sql_result as $report) {
      $statistics_daily_report_list[$report->member_account] = $report;
    }

    return $statistics_daily_report_list;
  }

  // 反水等級設定
  private function getFavorableRules()
  {

    // query commission rules
    $favorable_rules_sql = "SELECT * FROM root_favorable ORDER BY name ASC, wager DESC;";
    $favorable_rules_sql_result = runSQLall($favorable_rules_sql);

    $favorable_rules = [];

    // construct commission rules mapping
    foreach ($favorable_rules_sql_result as $index => $rule) {
      if($index == 0) continue;

      $favorable_rules[$rule->name][$rule->wager] = [
        'upperlimit' => $rule->upperlimit,
        'audit' => $rule->audit,
        'favorablerate' => json_decode($rule->favorablerate, true),
        'self_favorablerate' => 0,
        'first_agent_favorablerate' => 0,
        'level_favorablerate' => [],
        'is_bottom_up' => false,
        'is_top_down' => true,
      ];

    }

    return $favorable_rules;
  }

  // get casino game categories
  private function getCasinoGameCategories()
  {
    $casino_game_sql =<<<SQL
    SELECT
    casinoid,
    game_flatform_list
    FROM casino_list
    ORDER BY id
    ;
SQL;

    $casino_game_category_result = runSQLall($casino_game_sql);
    $casino_game_categories = [];

    foreach($casino_game_category_result as $index => $casino_category) {
      if($index == 0) continue;

      $casino_game_categories[strtolower($casino_category->casinoid)] = json_decode($casino_category->game_flatform_list, true);
    }

    return $casino_game_categories;
  }
}
// end of PreferentialCalculatorInitTrait



/**
 * [Class description]
 * @var [type]
 */
Class PreferentialCalculationData {
  public $all_bets_amount;
  public $all_favorablerate_amount;
  public $favorable_limit;
  public $favorable_audit;
  public $all_favorablerate_amount_detail;
  public $favorable_distribute;

  static $init_data = [
  	'all_bets_amount'                 => 0,
  	'all_favorablerate_amount'        => 0,
  	'favorable_limit'                 => 0,
  	'favorable_audit'                 => 0,
    'all_favorablerate_amount_detail' => [
      'self_favorable' => 0,
      'self_favorablerate' => 0,
      'level_distribute' => [
        // [
        //   'from_id' => '',
        //   'from_account' => '',
        //   'from_level' => 0,
        //   'from_base_favorable' => 0,
        //   'from_favorable_rate' => 0,
        //   'from_favorable' => 0,
        //   'is_bottom_up' => true,
        //   'is_top_down' => false,
        //   'is_rest' => false,
        // ]
      ],
    ],
    'favorable_distribute' => [
      'total_bets' => 0,
      'total_bets_detail' => [],
      'casino_favorablerates' => [],
      'total_favorable' => 0,
      'is_bottom_up' => true,
      'is_top_down' => false,
      'level_distribute' => [
        // [
        //   'to_id' => '',
        //   'to_account' => '',
        //   'to_level' => 0,
        //   'to_base_favorable' => 0,
        //   'to_favorable_rate' => 0,
        //   'to_favorable' => 0,
        // ]
      ],
      'rest_distribute' => [
        //   'to_id' => '',
        //   'to_account' => '',
        //   'to_level' => 0,
        //   'to_base_favorable' => 0,
        //   'to_favorable_rate' => 0,
        //   'to_favorable' => 0,
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
    $this->favorable_distribute['total_bets'] = $total_bets;
  }

  public function setTotalBetsDetail($vendor, $category, $bet)
  {
    $this->favorable_distribute['total_bets_detail'][$vendor][$category] = $bet;
  }

  public function setCasinoFavorablerates($vendor, $category, $rate)
  {
    $this->favorable_distribute['casino_favorablerates'][$vendor][$category] = $rate;
  }

  public function setTotalFavorable($total_favorable)
  {
    $this->favorable_distribute['total_favorable'] = $total_favorable;
  }

  public function getTotalFavorable()
  {
    return $this->favorable_distribute['total_favorable'];
  }

  public function setIsBottomUp($is_bottom_up)
  {
    $this->favorable_distribute['is_bottom_up'] = $is_bottom_up;
    $this->favorable_distribute['is_top_down'] = ! $is_bottom_up;
  }

  public function getIsBottomUp()
  {
    return $this->favorable_distribute['is_bottom_up'];
  }


  public function setSelfFavorable($self_favorable)
  {
    $this->all_favorablerate_amount_detail['self_favorable'] = $self_favorable;

  }

  public function setSelfFavorableRate($self_favorablerate)
  {
    $this->all_favorablerate_amount_detail['self_favorablerate'] = $self_favorablerate;

  }

  public function addFavorableAmountDetail(MemberTreeNode $member, $rate, $favorable, $base_favorable, $is_bottom_up, $is_rest = false)
  {
    $this->all_favorablerate_amount_detail['level_distribute'][] = [
      'from_id' => $member->id,
      'from_account' => $member->account,
      'from_level' => $member->member_level,
      'base_favorable' => $base_favorable,
      'from_favorable_rate' => $rate,
      'from_favorable' => $favorable,
      'is_bottom_up' => $is_bottom_up,
      'is_top_down' => ! $is_bottom_up,
      'is_rest' => $is_rest,
    ];
  }

  public function addFavorableDistribute(MemberTreeNode $member, $rate, $favorable)
  {
    $this->favorable_distribute['level_distribute'][] = [
      'to_id' => $member->id,
      'to_account' => $member->account,
      'to_level' => $member->member_level,
      'base_favorable' => $this->getTotalFavorable(),
      'to_favorable_rate' => $rate,
      'to_favorable' => $favorable,
    ];
  }

  public function addFavorableRestDistribute(MemberTreeNode $member, $rate, $favorable)
  {
    $this->favorable_distribute['rest_distribute'] = [
      'to_id' => $member->id,
      'to_account' => $member->account,
      'to_level' => $member->member_level,
      'base_favorable' => $this->getTotalFavorable(),
      'to_favorable_rate' => $rate,
      'to_favorable' => $favorable,
    ];
  }
}
// end of PreferentialCalculationData


function insert_or_update_preferential_sql(array $preferential_dailyreport_data) {

  $attributes = array_keys($preferential_dailyreport_data);
  $values = array_values($preferential_dailyreport_data);

  $attributes_string = implode(',', $attributes);
  $values_string = "'" . implode("','", $values) . "'";

  $get_set_string_fun = function($attribute, $value) {
    return "$attribute = '$value'";
  };

  $set_string = implode(',', array_map($get_set_string_fun, $attributes, $values) );

  $self_favorable = 0;
  $amount_detail_data = json_decode($preferential_dailyreport_data['all_favorablerate_amount_detail'], true);
  if(isset($amount_detail_data['self_favorable'])) {
    $self_favorable = $amount_detail_data['self_favorable'];
  }

  $insert_or_update_sql =<<<SQL
    INSERT INTO root_statisticsdailypreferential (
      $attributes_string,
      all_favorablerate_beensent_amount,
      all_favorablerate_difference_amount,
      self_favorable_beensent_amount,
      self_favorable_difference_amount
    ) VALUES (
      $values_string,
      0,
      {$preferential_dailyreport_data['all_favorablerate_amount']},
      0,
      {$self_favorable}
    )
    ON CONFLICT ON CONSTRAINT root_statisticsdailypreferential_dailydate_member_account
    DO
      UPDATE
      SET $set_string,
      all_favorablerate_difference_amount = EXCLUDED.all_favorablerate_amount - root_statisticsdailypreferential.all_favorablerate_beensent_amount,
      self_favorable_difference_amount = (CAST (EXCLUDED.all_favorablerate_amount_detail->>'self_favorable' AS NUMERIC)) - root_statisticsdailypreferential.self_favorable_beensent_amount
    ;
SQL;

  // echo $insert_or_update_sql . "\n";
  return $insert_or_update_sql;
}

/*
  *抓取預設反水設定資訊
*/
function default_favorable_setting(){
  $sql = "SELECT * FROM root_favorable WHERE id=(SELECT min(id) FROM root_favorable WHERE name='預設反水設定');";
  $result = runSQLall($sql);

  return $result[1];
}
?>
