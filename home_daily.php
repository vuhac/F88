<?php
// ----------------------------------------------------------------------------
// Features:  後台--系統首頁
// File Name:  home_daily.php
// Author:    mtchang.tw@gmail.com
// Related:    index.php
// Log: 使用highcharts
// ----------------------------------------------------------------------------

session_start();

// 主機及資料庫設定
require_once dirname(__FILE__) . "/config.php";
// 支援多國語系
require_once dirname(__FILE__) . "/i18n/language.php";
// 自訂函式庫
require_once dirname(__FILE__) . "/lib.php";

use TransactionDetail as TxDetail;

// var_dump($_SESSION);

// var_dump(session_id());
// ----------------------------------------------------------------------------
// 只要 session 活著,就要同步紀錄該 user account in redis server db 1
Agent_RegSession2RedisDB();
// ----------------------------------------------------------------------------

// ----------------------------------------------------------------------------
// 檢查權限是否合法，允許就會放行。否則中止。
agent_permission();
// ----------------------------------------------------------------------------

class StatisticsDailyReport extends ADataAccess {
  protected $table = 'root_statisticsdailyreport';
}

class BetlogDetail {
  protected $jsonRaw = '';
  protected $data;

  public function __construct($jsonRaw) {
    $this->jsonRaw = $jsonRaw;
    $this->data = json_decode($jsonRaw);

    foreach ($this->data as $property => $value) {
      $this->$property = $value;
    }
  }

  public function __get($property) {
    if (property_exists($this, $property)) {
      return $this->$property;
    } else {
      return null;
    }
  }
}

class DashboardHomeDaily {
  private $timestamp;
  private $nowString;
  private $dailydate;
  private $timezone = 'America/St_Thomas';
  private $dateTime = null;
  // 今日(即時、變動)、昨日； 本周(包含今日、變動)、上周； 本月(包含今日、變動)、上月
  private $days;
  private $dailyReportStatistics = null;
  private $dailyFirstDepositStatistics = null;
  protected $systemTranscationCategories = [];
  private $isWeekStartAtSaturday = true;

  public function __construct($timeString = null) {
    global $transaction_category;
    $this->systemTranscationCategories = $transaction_category;
    $timeString = $timeString ?? 'now';
    $this->setCurrentTime($timeString);
    $this->setQueryDays();
    $this->setDailyReportStatistics();
    $this->setDailyFirstDepositStatistics();
  }

  public function __get($property) {
    if (property_exists($this, $property)) {
      return $this->$property;
    } else {
      return null;
    }
  }

  private function setCurrentTime($timeString) {
    $this->dateTime = new DateTime($timeString);
    $this->dateTime->setTimezone(new DateTimeZone($this->timezone));
    $this->timestamp = $this->dateTime->getTimestamp();
    $this->nowString = $this->dateTime->format('Y-m-d H:i:s');
    $this->dailydate = $this->dateTime->format('Y-m-d');
  }

  /**
   * 初始化要查詢的區間
   * 搭配 setDailyReportStatistics() 使用
   * 區間越長的擺在陣列後方，會將最長區間內的資料一次撈出
   *
   * @return void
   */
  private function setQueryDays() {
    $this->days = [
      // 'today' => [
      //     'start' => $this->dailydate,
      //     'end' => $this->dailydate,
      //     'description' => '访问日期当下的日期',
      //     'title' => '今日'
      // ],
      'yesterday' => [
        'start' => $this->dateTime->modify("$this->dailydate -1 day")->format('Y-m-d'),
        'end' => $this->dateTime->modify("$this->dailydate -1 day")->format('Y-m-d'),
        'description' => '昨日',
        'title' => '昨日',
      ],
      'thisWeek' => $this->isWeekStartAtSaturday
      ? ['start' => $this->dateTime->modify("$this->dailydate last day this week")->format('Y-m-d'),
        'end' => $this->dateTime->modify("$this->dailydate last day this week +6 day")->format('Y-m-d'),
        'description' => '本周（起始日为星期日）',
        'title' => '本周']
      : ['start' => $this->dateTime->modify("$this->dailydate this week")->format('Y-m-d'),
        'end' => $this->dateTime->modify("$this->dailydate this week +6 day")->format('Y-m-d'),
        'description' => '本周（起始日为星期一）',
        'title' => '本周'],

      'lastWeek' => $this->isWeekStartAtSaturday
      ? ['start' => $this->dateTime->modify("$this->dailydate last day last week")->format('Y-m-d'),
        'end' => $this->dateTime->modify("$this->dailydate last day last week +6 day")->format('Y-m-d'),
        'description' => '上周（起始日为星期日）',
        'title' => '上周']
      : ['start' => $this->dateTime->modify("$this->dailydate last week")->format('Y-m-d'),
        'end' => $this->dateTime->modify("$this->dailydate last week +6 day")->format('Y-m-d'),
        'description' => '上周（起始日为星期一）',
        'title' => '上周'],

      'thisMonth' => [
        'start' => $tmp = $this->dateTime->modify("$this->dailydate")->format('Y-m-01'),
        'end' => $this->dateTime->modify("$tmp +1 month -1 day")->format('Y-m-d'),
        'description' => '本月一日到最后一日',
        'title' => '本月',
      ],

      'lastMonth' => [
        'start' => $tmp = $this->dateTime->modify("$this->dailydate -1 month")->format('Y-m-01'),
        'end' => $this->dateTime->modify("$tmp +1 month -1 day")->format('Y-m-d'),
        'description' => '上月一日到最后一日',
        'title' => '上月',
      ],
    ];
  }

  /**
   * 日報不關心每個會員，所以撈的時候可以先作加總
   * 到上個月頂多是 62 天的資料
   *
   * @return void
   */
  private function setDailyReportStatistics() {
    global $gcash_cashier_account, $gtoken_cashier_account;
    // $dailydate_start = '2018-05-01';
    // $dailydate_end = '2018-06-19';
    $dailydate_start = end($this->days)['start'];
    $dailydate_end = $this->days['thisMonth']['end'];

    $sql = <<<SQL
        SELECT
        dailydate,
        count(dailydate) AS count_dailydate,
        count(dailydate) - LEAD(count(dailydate)) OVER (ORDER BY dailydate DESC) AS count_dailydate_inc,
        -- 新進代理商
        sum(CASE WHEN agency_commission > 0 then 1 ELSE NULL END) AS agency_count,
        sum(agency_commission) AS agency_commission,
        -- 投注資料
        sum( (betlog_detail->>'casino_all_profitlost')::float ) AS sum_all_profitlost,
        sum( (betlog_detail->>'casino_all_wins')::float ) AS sum_all_wins,
        sum( (betlog_detail->>'casino_all_bets')::float ) AS sum_all_bets,
        sum( (betlog_detail->>'casino_all_count')::float ) AS sum_all_count,
        -- 現金入取款，遊戲幣入取款、現金轉遊戲幣
        {$this->getTransactionDetailColmunString('deposit',['gcash'])} AS sum_cashdeposit,
        {$this->getTransactionDetailColmunString('withdrawal',['gcash'])} AS sum_cashwithdrawal,
        sum(cashgtoken) AS sum_cashgtoken,
        {$this->getTransactionDetailColmunString('deposit',['gtoken'])} AS sum_tokendeposit,
        {$this->getTransactionDetailColmunString('withdrawal',['gtoken'])} AS sum_tokenwithdrawal
        FROM root_statisticsdailyreport
        WHERE
            dailydate >= '$dailydate_start' AND
            dailydate <= '$dailydate_end' AND
            member_account NOT IN ('$gcash_cashier_account', '$gtoken_cashier_account') AND
            member_therole != 'R'
        GROUP BY dailydate
        ORDER BY dailydate DESC
SQL;

// var_dump($sql);

    // echo __LINE__,  ": $sql";
    $this->dailyReportStatistics = runSQLall_prepared($sql, [], '', 0, 'r');
  }

  private function setDailyFirstDepositStatistics() {
    global $gcash_cashier_account, $gtoken_cashier_account;
    $dailydate_start = end($this->days)['start'];
    $dailydate_end = $this->days['thisMonth']['end'];

    // 生成計算首存的SQL
    try {
      $values = ['setting_name' => 'default',
        'setting_attr' => 'member_deposit_currency'];
      $setting_query_res = runSQLall_prepared(
        "SELECT value FROM root_protalsetting WHERE setttingname = :setting_name AND name = :setting_attr",
        $values
      )[0];

      switch ($setting_query_res->value) {
      case 'gcash':
        $first_deposit_review_sql = <<<SQL
                        WITH "member1stDepositRecordId" AS (
                            SELECT
                                DISTINCT ON (source_transferaccount) id,
                                MIN(transaction_time) AS transaction_time
                            FROM "root_member_gcashpassbook"
                            WHERE source_transferaccount != '$gcash_cashier_account' AND
                                transaction_category LIKE '%deposit%'
                            GROUP BY id, transaction_time
                            ), "member1stDepositRecord" AS (
                            -- 首存的帳號、時間、金額等詳細資料
                            SELECT transaction_time::date AS dailydate, *
                            FROM "root_member_gcashpassbook" WHERE id IN (SELECT id FROM "member1stDepositRecordId")
                        )
SQL;
        break;
      case 'gtoken':
        $first_deposit_review_sql = <<<SQL
                        WITH "member1stDepositRecordId" AS (
                            SELECT
                                DISTINCT ON (source_transferaccount) id,
                                MIN(transaction_time) AS transaction_time
                            FROM "root_member_gtokenpassbook"
                            WHERE source_transferaccount != '$gtoken_cashier_account' AND
                                transaction_category LIKE '%deposit%'
                            GROUP BY id, transaction_time
                            ), "member1stDepositRecord" AS (
                            -- 首存的帳號、時間、金額等詳細資料
                            SELECT transaction_time::date AS dailydate, *
                            FROM "root_member_gtokenpassbook" WHERE id IN (SELECT id FROM "member1stDepositRecordId")
                        )
SQL;
        break;
      default:
        throw new Exception('沒有適合的貨幣類別!!');
        break;
      }
    } catch (\Exception $e) {
      die($e->getMessage());
    }

    $first_deposit_review_sql .= <<<SQL
            SELECT
                count(*) AS first_deposit_count,
                sum(deposit - withdrawal) AS first_deposit_amount,
                dailydate
            FROM "member1stDepositRecord"
            WHERE
                dailydate >= '$dailydate_start' AND
                dailydate <= '$dailydate_end'
            GROUP BY dailydate
SQL;
    $this->dailyFirstDepositStatistics = runSQLall_prepared($first_deposit_review_sql, [], '', 0, 'r');
  }

  /**
   * 組成對應 json 欄位的查詢字串
   *
   * @param string $cur_types ['gcash', 'gtoken'] 取 1
   * @param string $tx_type deposit | withdrawal
   * @param array $realcash_cols ['realcash', 'not_realcash'] 取 1
   * @return string $string
   */
  private function getTransactionDetailColmunString($tx_type, $cur_types = ['gcash', 'gtoken'], $realcash_cols = ['realcash', 'not_realcash']): String {
    $jsonColumn = [];
    $tx_types = [
      'deposit' => TxDetail::DEPOSIT_CATS + ['reject_company_deposits'],
      'withdrawal' => TxDetail::WITHDRAWAL_CATS + ['reject_cashwithdrawal'],
    ];

    foreach ($cur_types as $cur_type) {
      foreach ($realcash_cols as $realcash_col) {
        foreach ($tx_types[$tx_type] as $tx_cat) {
          $jsonColumn[] = sprintf(
            "coalesce(sum((transaction_detail->'$tx_cat'->'%s'->>'%s')::float), 0)",
            $cur_type,
            $realcash_col
          );
        }
      }
    }

    $string = implode(' + ', $jsonColumn);
    return $string;
  }

  public function getHomeDailyStatistics() {
    $result = [];
    foreach ($this->dailyReportStatistics as $dailyReportInfo) {
      $buffer = $dailyReportInfo;
      foreach ($this->dailyFirstDepositStatistics as $dailyFirstDepositInfo) {
        if (strtotime($dailyFirstDepositInfo->dailydate) == strtotime($buffer->dailydate)) {
          $buffer->first_deposit_count = $dailyFirstDepositInfo->first_deposit_count;
          $buffer->first_deposit_amount = $dailyFirstDepositInfo->first_deposit_amount;
          break;
        } else {
          $buffer->first_deposit_count = $buffer->first_deposit_count ?? 0;
          $buffer->first_deposit_amount = $buffer->first_deposit_amount ?? 0;
        }
      }
      $result[] = $buffer;
    }
    return $result;
  }

  public function getHomeDailyStatisticsByGroup() {
    $result = new stdClass;
    foreach ($this->days as $group => $groupInterval) {
      $result->$group = new stdClass;
      $result->$group->raw = array_filter($this->getHomeDailyStatistics(), function ($value) use ($groupInterval) {
        return strtotime($value->dailydate) >= strtotime($groupInterval['start'])
        && strtotime($value->dailydate) <= strtotime($groupInterval['end']);
      });
      $result->$group->summary = $this->summarizeDailyStatisticsByGroup($result->$group->raw);
      $result->$group->information = (object) $groupInterval;
    }

    return $result;
  }

  private function summarizeDailyStatisticsByGroup($groupData) {
    $columns = [
      'count_dailydate_inc' => 0,
      'agency_count' => 0,
      'agency_commission' => 0,
      'first_deposit_count' => 0,
      'first_deposit_amount' => 0,
      'sum_all_profitlost' => 0,
      'sum_all_wins' => 0,
      'sum_all_bets' => 0,
      'sum_all_count' => 0,
      'sum_cashdeposit' => 0,
      'sum_cashwithdrawal' => 0,
      'sum_cashgtoken' => 0,
      'sum_tokendeposit' => 0,
      'sum_tokenwithdrawal' => 0,
    ];

    $result = new ArrayObject($columns, ArrayObject::ARRAY_AS_PROPS);

    foreach ($result as $column => $value) {
      foreach ($groupData as $dataRow) {
        $result->$column += $dataRow->$column ?? 0;
      }
    }

    // 入款、取款總額；顯示正值，入取差額才有正負號
    $result->sum_cashwithdrawal = -$result->sum_cashwithdrawal;
    $result->sum_tokenwithdrawal = -$result->sum_tokenwithdrawal;

    return $result;
  }
}

// ----------------------------------------------------------------------------
// Main
// ----------------------------------------------------------------------------
// 初始化變數
// 功能標題，放在標題列及meta
$function_title = '系統仪表盘-日报表';
// 擴充 head 內的 css or js
$extend_head = '';
// 放在結尾的 js
$extend_js = <<<HTML
<script>
$(function() {
    $('[data-toggle="tooltip"]').tooltip();
});
</script>
HTML;

// body 內的主要內容
$indexbody_content = '';
// 目前所在位置 - 配合選單位置讓使用者可以知道目前所在位置
$menu_breadcrumbs = '
<ol class="breadcrumb">
  <li><a href="home.php">首頁</a></li>
  <li class="active">' . $function_title . '</li>
</ol>';
// ----------------------------------------------------------------------------
// ----------------------------------------------------------------------------
// MAIN
// ----------------------------------------------------------------------------

$converter = new TypeConverter;
$home_daily_dashboard = new DashboardHomeDaily('now');
// var_dump($home_daily_dashboard->days);
// var_dump($home_daily_dashboard->dailyReportStatistics);
// var_dump($home_daily_dashboard->getHomeDailyStatisticsByGroup());
// var_dump($home_daily_dashboard->getHomeDailyStatisticsByGroup()->today);
// var_dump($home_daily_dashboard->getHomeDailyStatisticsByGroup()->yesterday);

// -----------------------------------------------------------------------------
// 將資料以表格方式呈現10分鐘報表 -- from root_statisticsbetting
// -----------------------------------------------------------------------------
// 目前時間 -05 timezone
$current_datepicker = gmdate('Y-m-d', time()+-5 * 3600);
$current_timepicker = gmdate('H:i:s', time()+-5 * 3600);

// -----------------------------------------------------------------------------
// 將資料以表格方式呈現 -- from root_statisticsdailyreport -- 每日資料
// -----------------------------------------------------------------------------
// -- 每日 損益, 派採,投注,單量 + 每日现金入款, 现金取款, 游戏币取款
$stats_table_data = '';
$table_summary_html = '';

$stats_daily_summary = $home_daily_dashboard->dailyReportStatistics;
$count_of_daily_data = count($stats_daily_summary);
array_unshift($stats_daily_summary, $count_of_daily_data);

$casino_dailydate_result = $stats_daily_summary;
// header('Content-Type: application/json');
// die(json_encode($casino_dailydate_result));

// 有資料才執行
if ($casino_dailydate_result[0] >= 1) {
  // 每日
  $daily_array = array();
  // 会员人数
  $count_dailydate = array();
  // 会员增加数量
  $count_dailydate_inc = [];
  // 損益
  $sum_all_profitlost = array();
  // 派彩
  $sum_all_wins = array();
  // 投注
  $sum_all_bets = array();
  // 注單
  $sum_all_count = array();

  // 每日现金入款, 现金取款, 游戏币取款
  $sum_tokengcash = array();
  $sum_cashdeposit = array();
  $sum_cashwithdrawal = array();
  $sum_cashgtoken = $sum_tokendeposit = [];

  $count_show_records = min($casino_dailydate_result[0], 60);

  for ($i = $casino_dailydate_result[0]; $i > 0; $i--) {

    // 娛樂城
    array_push($daily_array, $casino_dailydate_result[$i]->dailydate);
    array_push($count_dailydate, $casino_dailydate_result[$i]->count_dailydate);
    array_push($count_dailydate_inc, $casino_dailydate_result[$i]->count_dailydate_inc);
    array_push($sum_all_profitlost, round($casino_dailydate_result[$i]->sum_all_profitlost, 2));
    array_push($sum_all_wins, round($casino_dailydate_result[$i]->sum_all_wins, 2));
    array_push($sum_all_bets, round($casino_dailydate_result[$i]->sum_all_bets, 2));
    array_push($sum_all_count, round($casino_dailydate_result[$i]->sum_all_count, 2));

    //  每日现金入款, 现金取款, 现金转游戏币, 游戏币入款, 游戏币取款
    array_push($sum_cashdeposit, round($casino_dailydate_result[$i]->sum_cashdeposit, 2));
    array_push($sum_cashwithdrawal, round(-$casino_dailydate_result[$i]->sum_cashwithdrawal, 2));
    array_push($sum_cashgtoken, round($casino_dailydate_result[$i]->sum_cashgtoken, 2));
    array_push($sum_tokendeposit, round($casino_dailydate_result[$i]->sum_tokendeposit, 2));
    array_push($sum_tokengcash, round(-$casino_dailydate_result[$i]->sum_tokenwithdrawal, 2));
  }

  for ($i = 1; $i <= $count_show_records; $i++) {
    $stats_table_data .= '
        <tr>
        <td>' . $casino_dailydate_result[$i]->dailydate . '</td>
        <td>' . $casino_dailydate_result[$i]->count_dailydate . '</td>
        <td>' . $converter->add($casino_dailydate_result[$i]->count_dailydate_inc)->numberFormat(0, '.', ',')->commit() . '</td>
        <td>' . $converter->add($casino_dailydate_result[$i]->sum_all_profitlost)->numberFormat()->commit() . '</td>
        <td>' . $converter->add($casino_dailydate_result[$i]->sum_all_wins)->numberFormat()->commit() . '</td>
        <td>' . $converter->add($casino_dailydate_result[$i]->sum_all_bets)->numberFormat()->commit() . '</td>
        <td>' . $converter->add($casino_dailydate_result[$i]->sum_all_count)->numberFormat(0, '.', ',')->commit() . '</td>
        <td>' . $converter->add($casino_dailydate_result[$i]->sum_cashdeposit)->numberFormat()->commit() . '</td>
        <td>' . sprintf('%.2f', -$casino_dailydate_result[$i]->sum_cashwithdrawal) . '</td>
        <td>' . $converter->add($casino_dailydate_result[$i]->sum_cashgtoken)->numberFormat()->commit() . '</td>
        <td>' . $converter->add($casino_dailydate_result[$i]->sum_tokendeposit)->numberFormat()->commit() . '</td>
        <td>' . $converter->add($casino_dailydate_result[$i]->sum_tokenwithdrawal)->numberFormat()->commit() . '</td>
        </tr>
        ';
  }
  $logger = 'Success, 取得資料成功, 並且將資料塞入陣列。';

  // json 將報表資料轉成 json 格式
  $daily_array_json = json_encode($daily_array);
  $count_dailydate_json = json_encode($count_dailydate);
  $count_dailydate_inc_json = json_encode($count_dailydate_inc);
  $sum_all_profitlost_json = json_encode($sum_all_profitlost);
  $sum_all_wins_json = json_encode($sum_all_wins);
  $sum_all_bets_json = json_encode($sum_all_bets);
  $sum_all_count_json = json_encode($sum_all_count);

  $sum_tokengcash_json = json_encode($sum_tokengcash);
  $sum_cashdeposit_json = json_encode($sum_cashdeposit);
  $sum_cashwithdrawal_json = json_encode($sum_cashwithdrawal);
  $sum_tokendeposit_json = json_encode($sum_tokendeposit);
  $sum_cashgtoken_json = json_encode($sum_cashgtoken);

  // 数字呈现的统计表格
  $stats_table_html = '
    <hr>
    <h3>数字呈现的统计表格</h3>
    <table class="table table-hover" width="98%">
        <thead>
            <tr>
                <th class="text-center" rowspan="2">时间</th>
                <th class="text-center" rowspan="2">会员总人数</th>
                <th class="text-center" rowspan="2">会员增加数</th>
                <th class="text-center well" colspan="4">投注资讯</th>
                <th class="text-center well" colspan="3">现金资讯</th>
                <th class="text-center well" colspan="2">游戏币资讯</th>
            </tr>
            <tr>
                <th class="text-center">损益量</th>
                <th class="text-center">派彩量</th>
                <th class="text-center">投注量</th>
                <th class="text-center border-right">注单量</th>
                <th class="text-center">现金存款量</th>
                <th class="text-center">现金取款量</th>
                <th class="text-center border-right">现金转游戏币</th>
                <th class="text-center">游戏币存款量</th>
                <th class="text-center border-right">游戏币取款量</th>
            </tr>
        </thead>
        <tbody>
            ' . $stats_table_data . '
        </tbody>
    </table>
    ';

  // (1)每日投注人数
  $chart_data1 = "
    Highcharts.chart('charts_container_1', {
            chart: {
                    type: 'column'
            },
            title: {
                    text: '每日会员增加'
            },
            xAxis: {
                    categories: " . $daily_array_json . ",
                    crosshair: true
            },
            yAxis: {
                    min: 0,
                    title: {
                            text: '人数'
                    }
            },
            tooltip: {
                    headerFormat: '<span style=\"font-size:10px\">{point.key}</span><table>',
                    pointFormat: '<tr><td style=\"color:{series.color};padding:0\">{series.name}: </td>' +
                            '<td style=\"padding:0\"><b>{point.y:1f} 人数</b></td></tr>',
                    footerFormat: '</table>',
                    shared: true,
                    useHTML: true
            },
            plotOptions: {
                    column: {
                            pointPadding: 0.2,
                            borderWidth: 0
                    }
            },
            series: [{
                    name: '人数',
                    data: " . $count_dailydate_inc_json . "

            }],
            credits: {
                enabled: false
            },
            exporting: {
                buttons: [
                    {	enabled: false }
                ]
            }
    });
    ";

  // 每日投注/派彩金额
  $chart_data2 = "
    Highcharts.chart('charts_container_2', {
            chart: {
                    type: 'column'
            },
            title: {
                    text: '每日投注/派彩'
            },
            xAxis: {
                    categories: " . $daily_array_json . ",
                    crosshair: true
            },
            yAxis: {
                    min: 0,
                    title: {
                            text: '投注/派彩({$config['currency_sign']})'
                    }
            },
            tooltip: {
                    headerFormat: '<span style=\"font-size:10px\">{point.key}</span><table>',
                    pointFormat: '<tr><td style=\"color:{series.color};padding:0\">{series.name}: </td>' +
                            '<td style=\"padding:0\"><b>{point.y:.2f} {$config['currency_sign']}</b></td></tr>',
                    footerFormat: '</table>',
                    shared: true,
                    useHTML: true
            },
            plotOptions: {
                    column: {
                            pointPadding: 0.2,
                            borderWidth: 0
                    }
            },
            series: [
                {
                    name: '投注',
                    data: " . $sum_all_bets_json . "
                },
                {
                    name: '派彩',
                    data: " . $sum_all_wins_json . "
                }
            ],
            credits: {
                enabled: false
            },
            exporting: {
                buttons: [
                    {	enabled: false }
                ]
            }
    });
    ";

  // 每日損益
  $chart_data3 = "
    Highcharts.chart('charts_container_3', {
            chart: {
                    type: 'column'
            },
            title: {
                    text: '每日損益'
            },
            xAxis: {
                    categories: " . $daily_array_json . ",
                    crosshair: true
            },
            yAxis: {
                    title: {
                            text: '損益金额({$config['currency_sign']})'
                    }
            },
            tooltip: {
                    headerFormat: '<span style=\"font-size:10px\">{point.key}</span><table>',
                    pointFormat: '<tr><td style=\"color:{series.color};padding:0\">{series.name}: </td>' +
                            '<td style=\"padding:0\"><b>{point.y:.2f}{$config['currency_sign']}</b></td></tr>',
                    footerFormat: '</table>',
                    shared: true,
                    useHTML: true
            },
            plotOptions: {
                    column: {
                            pointPadding: 0.2,
                            borderWidth: 0
                    }
            },
            series: [{
                    name: '損益',
                    data: " . $sum_all_profitlost_json . "
            }],
            credits: {
                enabled: false
            },
            exporting: {
                buttons: [
                    {	enabled: false }
                ]
            }
    });
    ";

  // 注单量
  $chart_data4 = "
    Highcharts.chart('charts_container_4', {
            chart: {
                    type: 'column'
            },
            title: {
                    text: '注单量'
            },
            xAxis: {
                    categories: " . $daily_array_json . ",
                    crosshair: true
            },
            yAxis: {
                    min: 0,
                    title: {
                            text: '注单量'
                    }
            },
            tooltip: {
                    headerFormat: '<span style=\"font-size:10px\">{point.key}</span><table>',
                    pointFormat: '<tr><td style=\"color:{series.color};padding:0\">{series.name}: </td>' +
                            '<td style=\"padding:0\"><b>{point.y:.2f} Times</b></td></tr>',
                    footerFormat: '</table>',
                    shared: true,
                    useHTML: true
            },
            plotOptions: {
                    column: {
                            pointPadding: 0.2,
                            borderWidth: 0
                    }
            },
            series: [{
                    name: '注单量',
                    data: " . $sum_all_count_json . "
            }],
            credits: {
                enabled: false
            },
            exporting: {
                buttons: [
                    {	enabled: false }
                ]
            }
    });
    ";

  // 现金存提
  $chart_data5 = "
    Highcharts.chart('charts_container_5', {
            chart: {
                    type: 'column'
            },
            title: {
                    text: '每日现金存款/取款'
            },
            xAxis: {
                    categories: " . $daily_array_json . ",
                    crosshair: true
            },
            yAxis: {
                    min: 0,
                    title: {
                            text: '现金存款/取款 ({$config['currency_sign']})'
                    }
            },
            tooltip: {
                    headerFormat: '<span style=\"font-size:10px\">{point.key}</span><table>',
                    pointFormat: '<tr><td style=\"color:{series.color};padding:0\">{series.name}: </td>' +
                            '<td style=\"padding:0\"><b>{point.y:.2f} {$config['currency_sign']}</b></td></tr>',
                    footerFormat: '</table>',
                    shared: true,
                    useHTML: true
            },
            plotOptions: {
                    column: {
                            pointPadding: 0.2,
                            borderWidth: 0
                    }
            },
            series: [{
                    name: '现金存款',
                    data: " . $sum_cashdeposit_json . "
            }, {
                    name: '现金取款',
                    data: " . $sum_cashwithdrawal_json . "
            }
            ],
            credits: {
                enabled: false
            },
            exporting: {
                buttons: [
                    {	enabled: false }
                ]
            }
    });
    ";

  // 游戏币存提
  $chart_data6 = "
    Highcharts.chart('charts_container_6', {
            chart: {
                    type: 'column'
            },
            title: {
                    text: '每日游戏币存款/取款'
            },
            xAxis: {
                    categories: " . $daily_array_json . ",
                    crosshair: true
            },
            yAxis: {
                    min: 0,
                    title: {
                            text: '游戏币存款/取款 ({$config['currency_sign']})'
                    }
            },
            tooltip: {
                    headerFormat: '<span style=\"font-size:10px\">{point.key}</span><table>',
                    pointFormat: '<tr><td style=\"color:{series.color};padding:0\">{series.name}: </td>' +
                            '<td style=\"padding:0\"><b>{point.y:.2f} {$config['currency_sign']}</b></td></tr>',
                    footerFormat: '</table>',
                    shared: true,
                    useHTML: true
            },
            plotOptions: {
                    column: {
                            pointPadding: 0.2,
                            borderWidth: 0
                    }
            },
            series: [{
                    name: '游戏存款',
                    data: " . $sum_tokendeposit_json . "
            },{
                    name: '游戏币取款',
                    data: " . $sum_tokengcash_json . "
            }
            ],
            credits: {
                enabled: false
            },
            exporting: {
                buttons: [
                    {	enabled: false }
                ]
            }
    });
    ";

  // ------------------------------------------------------------------------- 焦點資訊 Start
  $table_summary_data_row = function ($group) use ($converter) {
    return $value = <<<HTML
            <tr>
                <th data-toggle="tooltip" title="{$group->information->description}">{$group->information->title}</th>
                <td>{$converter->add($group->summary->agency_count)->numberFormat(0,'.',',')->commit()}</td>
                <td>{$converter->add($group->summary->agency_commission)->numberFormat()->commit()}</td>
                <td>{$converter->add($group->summary->count_dailydate_inc)->numberFormat(0,'.',',')->commit()}</td>
                <td>{$converter->add($group->summary->first_deposit_count)->numberFormat(0,'.',',')->commit()}</td>
                <td>{$converter->add($group->summary->first_deposit_amount)->numberFormat()->commit()}</td>
                <td>{$converter->add($group->summary->sum_cashdeposit+$group->summary->sum_tokendeposit)->numberFormat()->commit()}</td>
                <td>{$converter->add($group->summary->sum_cashwithdrawal+$group->summary->sum_tokenwithdrawal)->numberFormat()->commit()}</td>
                <td>{$converter->add(
                        $group->summary->sum_cashdeposit+$group->summary->sum_tokendeposit
                        -$group->summary->sum_cashwithdrawal-$group->summary->sum_tokenwithdrawal
                    )->numberFormat()->commit()}</td>
                <td>{$converter->add($group->summary->sum_all_bets)->numberFormat()->commit()}</td>
                <td>{$converter->add($group->summary->sum_all_profitlost)->numberFormat()->commit()}</td>
            </tr>
HTML;
  };

  $table_summary_data_arary = array_map($table_summary_data_row, (array) $home_daily_dashboard->getHomeDailyStatisticsByGroup());
  $table_summary_data = implode('', $table_summary_data_arary);
  // var_dump($table_summary_data);

  // HTML
  $table_summary_html = <<<HTML
    <hr>
    <h3><span class="glyphicon glyphicon-info-sign"></span>焦点资讯</h3>
    <style>
    #table_summary th,td {
        text-align: center;
    }
    #table_user th,td {
        text-align: center;
    }
    #table_amount th,td {
        text-align: center;
    }
    </style>
    <!-- 綜合數據 -->
    <div class="panel-group">
        <div class="panel panel-default">
            <div class="panel-heading">
                <h3 class="panel-title text-center">
                    <a href="#collapse-summary" data-toggle="collapse" data-toggle="tooltip" title="现金与游戏币的综合统计资讯">综合数据</a>
                </h3>
            </div>
            <div id="collapse-summary" class="panel-collapse collapse show">
                <div class="panel-body">
                    <table class="table table-hover" width="98%" id="table_summary">
                        <thead>
                            <tr>
                                <th></th>
                                <th>代理(人)</th>
                                <th>代理加盟现金</th>
                                <th data-toggle="tooltip" title="新进会员累积人数">新进会员</th>
                                <th data-toggle="tooltip" title="新进会员的累积首存人数">首存人数</th>
                                <th data-toggle="tooltip" title="新进会员的累积首存总额">首存金额</th>
                                <th data-toggle="tooltip" title="现金存款与游戏币存款">存款总额</th>
                                <th data-toggle="tooltip" title="现金取款与游戏币取款">取款总额</th>
                                <th data-toggle="tooltip" title=" = 存款总额与取款总额的差额">入取差额</th>
                                <th>有效投注</th>
                                <th>损益</th>
                            </tr>
                        </thead>
                        <tbody>
                            $table_summary_data
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
HTML;
  // ------------------------------------------------------------------------- 焦點資訊 End

} else {
  $logger = 'False , 取得報表資料失敗';
  // echo $logger;
  $stats_table_html = 'NO DATA';
  $chart_data1 = '';
  $chart_data2 = '';
  $chart_data3 = '';
  $chart_data4 = '';
  $chart_data5 = '';
  $chart_data6 = '';
}

// -----------------------------------------------------------------------------
// 將資料以表格方式呈現 end
// -----------------------------------------------------------------------------

// -----------------------------------------------------------------------------
// 以視覺化方式呈現 -- chart 2
// -----------------------------------------------------------------------------

// ref: http://api.highcharts.com/highcharts/credits.position
$chart_js = "
<script>
$(document).ready ( function(){
    " . $chart_data1 . "
    " . $chart_data2 . "
    " . $chart_data3 . "
    " . $chart_data4 . "
    " . $chart_data5 . "
    " . $chart_data6 . "
});
</script>
";

// 加入圖的 data 到 head
$extend_head = $extend_head . $chart_js;

// 10 分鐘統計圖, chart2
// ref: http://www.highcharts.com/demo/column-basic
$container_width = 460;
$container_height = 260;
// 每日統計圖的 html 位置與顯示 , charts 2
$chart_html_1 = '<span id="charts_container_1" style="min-width: ' . $container_width . 'px; height: ' . $container_height . 'px; margin: 0 auto"></span>';
// charts 2
$chart_html_2 = '<span id="charts_container_2" style="min-width: ' . $container_width . 'px; height: ' . $container_height . 'px; margin: 0 auto"></span>';
// charts 3
$chart_html_3 = '<span id="charts_container_3" style="min-width: ' . $container_width . 'px; height: ' . $container_height . 'px; margin: 0 auto"></span>';
// charts 4
$chart_html_4 = '<span id="charts_container_4" style="min-width: ' . $container_width . 'px; height: ' . $container_height . 'px; margin: 0 auto"></span>';
// charts 5
$chart_html_5 = '<span id="charts_container_5" style="min-width: ' . $container_width . 'px; height: ' . $container_height . 'px; margin: 0 auto"></span>';
// charts 6
$chart_html_6 = '<span id="charts_container_6" style="min-width: ' . $container_width . 'px; height: ' . $container_height . 'px; margin: 0 auto"></span>';

// script
$extend_head = $extend_head . '
<script src="in/highcharts/highcharts.js"></script>
<script src="in/highcharts/modules/exporting.js"></script>
';

$indexbody_content = $indexbody_content . '
        <div id="button_area" class="row col-12 d-flex justify-content-end">
            <div style="float: right;">
                <a href="home.php" class="btn btn-success" >即時統計資訊</a>
            </div>
        </div><br>';

// 排板輸出 with bootstrap tab
$indexbody_content .= <<<HTML
    <div id="dailymenu" class="row tab-pane">
        <div class="col-12 col-md-6">$chart_html_1</div>
        <div class="col-12 col-md-6">$chart_html_4</div>
        <div class="col-12 col-md-6">$chart_html_2</div>
        <div class="col-12 col-md-6">$chart_html_3</div>
        <div class="col-12 col-md-6">$chart_html_5</div>
        <div class="col-12 col-md-6">$chart_html_6</div>
        <div class="col-12 col-md-12">$table_summary_html</div>
        <div class="col-12 col-md-12">$stats_table_html</div>
    </div>
HTML;
/*
// 排板輸出
$indexbody_content  = $indexbody_content.'
<div class="row">
<div class="col-12 col-md-6">
'.$chart_laststats_html_1.'
</div>
<div class="col-12 col-md-6">
'.$chart_laststats_html_2.'
</div>
<div class="col-12 col-md-6">
'.$chart_laststats_html_3.'
</div>
<div class="col-12 col-md-6">
'.$chart_laststats_html_4.'
</div>
<div class="col-12 col-md-12">
'.$table_laststats_html.'
</div>
<hr>
<div class="col-12 col-md-6">
'.$chart_html_1.'
</div>
<div class="col-12 col-md-6">
'.$chart_html_2.'
</div>
<div class="col-12 col-md-6">
'.$chart_html_3.'
</div>
<div class="col-12 col-md-6">
'.$chart_html_4.'
</div>
<div class="col-12 col-md-6">
'.$chart_html_5.'
</div>
<div class="col-12 col-md-6">
'.$chart_html_6.'
</div>
<div class="col-12 col-md-12">
'.$stats_table_html.'
</div>
</div>
';
 */

// ----------------------------------------------------------------------------
// 準備填入的內容
// ----------------------------------------------------------------------------

// 將內容塞到 html meta 的關鍵字, SEO 加強使用
$tmpl['html_meta_description'] = $tr['host_descript'];
$tmpl['html_meta_author'] = $tr['host_author'];
$tmpl['html_meta_title'] = $function_title . '-' . $tr['host_name'];

// 頁面大標題
$tmpl['page_title'] = $menu_breadcrumbs;
// 擴充再 head 的內容 可以是 js or css
$tmpl['extend_head'] = $extend_head;
// 擴充於檔案末端的 Javascript
$tmpl['extend_js'] = $extend_js;
// 主要內容 -- title
$tmpl['paneltitle_content'] = '<span class="glyphicon glyphicon-bookmark" aria-hidden="true"></span>' . $function_title;
// 主要內容 -- content
$tmpl['panelbody_content'] = $indexbody_content;

// ----------------------------------------------------------------------------
// 填入內容結束。底下為頁面的樣板。以變數型態塞入變數顯示。
// ----------------------------------------------------------------------------
// include("template/dashboard.tmpl.php");
include "template/beadmin.tmpl.php";

?>
