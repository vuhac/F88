<?php
// ----------------------------------------------------------------------------
// Features :	後台 --  加盟金計算  LIB
// File Name: radiationbonus_organization_lib.php
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

require_once dirname(__FILE__) ."/lib.php";

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


class FranchiseBonusCalculator {

  public $statistics_daily_report_list;
  public $member_list;
  public $member_tree_root;

  function __construct($date)
  {
    $this->statistics_daily_report_list = $this->getStatisticsDailyReportList($date);
  }

  public function setMemberTree(&$member_list, $root_id = null)
  {
    $this->member_list = $member_list;

    if(empty($root_id)) {
      $this->member_tree_root = $member_list[1];
    } else {
      $this->member_tree_root = $member_list[$root_id];
    }
  }

  private function getDailyReport($member)
  {
    return ($this->statistics_daily_report_list)[$member->account];
  }

  private function getFranchiseBonusRule($member)
  {
    // rule in on first level agent
    if($member->isFirstLevelAgent()) {
      $first_agent = $member;
    } else {
      $first_agent = $this->member_list[ ($member->predecessor_id_list)[0] ];
    }
    return $first_agent->node_data->franchise_bonus_rule;
  }

  private function calculateFranchiseBonus($member, FranchiseBonusRuleData $ruleData)
  {

    $level_id_list = $member->predecessor_id_list;

    if($ruleData->is_bottom_up) {
      $level_id_list = array_reverse($member->predecessor_id_list);
    }

    $member_daily_report = $this->getDailyReport($member);

    $member->node_data->franchise_fee = $member_daily_report->agency_commission;

    if($member_daily_report->agency_commission == 0) {
      return;
    }

    $level_counter = 0;


    foreach ($level_id_list as $agent_id) {
      $agent = $this->member_list[$agent_id];

      if($agent->isActive() && isset(($ruleData->level_bonus_proportion)[$level_counter])) {
        $level_counter++;

        $agent_daily_report = $this->getDailyReport($agent);

        if($ruleData->bonus_payout_threshold > $agent_daily_report->all_bets) {
          continue;
        }

        $member->node_data->addFranchiseFeeDistribution(
          $agent,
          $member_daily_report->agency_commission,
          $ruleData->getBonusProportion($level_counter - 1)
        );

        $agent->node_data->addFranchiseBonusSource(
          $member,
          $member_daily_report->agency_commission,
          $ruleData->getBonusProportion($level_counter - 1),
          false
        );

      }
    }

  }

  public function calculate()
  {

    // calculate and distribute preferential
    MemberTreeNode::visitBottomUp($this->member_tree_root, function($member) {

    	// get member's statistics daily report
    	$statistics_daily_report = $this->statistics_daily_report_list[$member->account];

      // get distribute rule
      $franchise_bonus_rule = $this->getFranchiseBonusRule($member);

      // calculate
      $this->calculateFranchiseBonus($member, $franchise_bonus_rule);
    });
    // end of calculate and distribute preferential

  }

  private function getStatisticsDailyReportList($date) {
    /**
    * [$statistics_daily_report_list   key by member_account]
    * @var array
    */
    $statistics_daily_report_list = [];

    $root_statisticsdailyreport_sql = "
    SELECT * FROM root_statisticsdailyreport
    WHERE root_statisticsdailyreport.dailydate ='" . $date . "'
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

}



/**
 * [Class description]
 * @var [type]
 */
Class FranchiseBonusData {

  public $member_account;
  public $member_parent_id;
  public $dailydate_begin;
  public $dailydate_end;
  public $updatetime;

  public $franchise_fee;

  public $franchise_bonus;

  public $franchise_bonus_rule; // object of FranchiseBonusRuleData

  public $franchise_fee_distribution_list;

  public $franchise_bonus_source_list;

  static $init_data = [
  	'franchise_fee'        => 0,
    'franchise_bonus'      => 0,
    'franchise_fee_distribution_list' => [
      // [
      //   'to_id' => '',
      //   'to_account' => '',
      //   'to_level' => 0,
      //   'franchise_fee' => 0,
      //   'to_franchise_bonus_proportion' => 0,
      //   'to_franchise_bonus' => 0,
      // ]
    ],
    'franchise_bonus_source_list' => [
      // [
      //   'from_id' => '',
      //   'from_account' => '',
      //   'from_level' => 0,
      //   'franchise_fee' => 0,
      //   'from_franchise_bonus_proportion' => 0,
      //   'from_franchise_bonus' => 0,
      //   'is_rest' => false,
      // ]
    ],
  ];

  function __construct($member = null, $current_date = null)
  {
    foreach (self::$init_data as $attribute => $value) {
      $this->$attribute = $value;
    }

    if(!empty($member)) {
      $this->member_account = $member->account;
      $this->member_parent_id = $member->parent_id;
      $this->member_therole = $member->therole;
    }

    if(!empty($current_date)) {
      $this->dailydate_begin = $current_date;
      $this->dailydate_end = $current_date;
    }

  }

  public function addFranchiseFeeDistribution(MemberTreeNode $agent, $franchise_fee, $proportion)
  {
    $this->franchise_fee_distribution_list[] = [
      'to_id' => $agent->id,
      'to_account' => $agent->account,
      'to_level' => $agent->member_level,
      'franchise_fee' => $franchise_fee,
      'to_franchise_bonus_proportion' => $proportion,
      'to_franchise_bonus' => round($franchise_fee * $proportion, 2),
    ];
  }

  public function addFranchiseBonusSource(MemberTreeNode $member, $franchise_fee, $proportion, $is_rest)
  {
    $this->franchise_bonus += $franchise_fee * $proportion;

    $this->franchise_bonus_source_list[] = [
      'from_id' => $member->id,
      'from_account' => $member->account,
      'from_level' => $member->member_level,
      'franchise_fee' => $franchise_fee,
      'from_franchise_bonus_proportion' => $proportion,
      'from_franchise_bonus' => round($franchise_fee * $proportion, 2),
      'is_rest' => $is_rest,
    ];
  }

  public function toSql()
  {
    if($this->isEmpty()) return '';

    $this->updatetime = (new \DateTime())->format('Y-m-d H:i:s');

    $data = (array)$this;
    $data['franchise_fee'] = round($this->franchise_fee, 2);
    $data['franchise_bonus'] = round($this->franchise_bonus, 2);
    $data['franchise_bonus_rule'] = json_encode( (array)($this->franchise_bonus_rule) );
    $data['franchise_fee_distribution_list'] = json_encode($this->franchise_fee_distribution_list);
    $data['franchise_bonus_source_list'] = json_encode($this->franchise_bonus_source_list);

    return $this->getInsertOrUpdateSql( $data );
  }

  public function isEmpty()
  {
    return round($this->franchise_fee, 2) == 0
      && round($this->franchise_bonus, 2) == 0;
  }

  private function getInsertOrUpdateSql(array $commission_dailyreport_data) {
    // print_r($commission_dailyreport_data);

    $attributes = array_keys($commission_dailyreport_data);
    $values = array_values($commission_dailyreport_data);

    $attributes_string = implode(',', $attributes);
    $values_string = "'" . implode("','", $values) . "'";

    $get_set_string_fun = function($attribute) {
      return "$attribute = EXCLUDED.$attribute";
    };

    $set_string = implode(',', array_map($get_set_string_fun, $attributes) );

    $insert_or_update_sql =<<<SQL
      INSERT INTO radiationbonus_organization ($attributes_string)
        VALUES($values_string)
      ON CONFLICT ON CONSTRAINT radiationbonus_organization_unique
      DO
        UPDATE
        SET $set_string
      ;
SQL;

  // echo $insert_or_update_sql;

    return $insert_or_update_sql;
  }

}


/**
 * utility class to manipulate 'franchise_bonus_rule' (jsonb) column in 'root_member' table
 */
Class FranchiseBonusRuleData {

  public $bonus_payout_threshold;
  public $level_bonus_proportion;
  public $is_bottom_up;
  public $is_top_down;

  static $init_data = [
    'bonus_payout_threshold' => 0,
    'level_bonus_proportion' => [],
    'is_bottom_up' => true,
    'is_top_down' => false,
  ];
  
  function __construct($member = null)
  {
    global $rule;
    
    $bonus_rule = @json_decode($member->franchise_bonus_rule, false) ?? [];
    
    self::$init_data['level_bonus_proportion'] = [
      $rule['commission_1_rate'] / 100,
      $rule['commission_2_rate'] / 100,
      $rule['commission_3_rate'] / 100,
      $rule['commission_4_rate'] / 100,
    ];

    $bonus_rule = array_merge(self::$init_data, $bonus_rule);

    foreach ($bonus_rule as $attribute => $value) {
      $this->$attribute = $value;
    }

  }

  function getBonusProportion($level)
  {
    return ($this->level_bonus_proportion)[$level];
  }
}

?>
