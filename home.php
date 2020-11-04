<?php
// ----------------------------------------------------------------------------
// Features:  後台--系統首頁
// File Name:  home.php
// Author:    mtchang.tw@gmail.com
// Related:    index.php
// Log:
// ----------------------------------------------------------------------------

session_start();

// 主機及資料庫設定
require_once dirname(__FILE__) . "/config.php";
// 支援多國語系
require_once dirname(__FILE__) . "/i18n/language.php";
// 自訂函式庫
require_once dirname(__FILE__) . "/lib.php";

// ----------------------------------------------------------------------------
// 只要 session 活著,就要同步紀錄該 user account in redis server db 1
Agent_RegSession2RedisDB();
// ----------------------------------------------------------------------------

// ----------------------------------------------------------------------------
// 檢查權限是否合法，允許就會放行。否則中止。
agent_permission();
// ----------------------------------------------------------------------------

class StatisticsSite extends ADataAccess {
  protected $table = 'root_statisticssite';
}

/**
 * 取得某區間的統計資訊
 *
 * 只要 new 一次，一次就是取 24H 的資料 (因為用途是當日[24H內]即時)；以 $hours 最大值為準
 *
 */
class DashboardHome {
  private $timestamp;
  private $nowString;
  private $dailydate;
  private $dailytime_start;
  private $dailytime_end;
  private $timezone = 'America/St_Thomas';
  private $dateTime = null;
  private $intervalSecond = 600;
  private $hours = [1, 2, 6, 12, 24];
  private $bettingStatistics = null;
  private $siteStatistics = null;

  public function __construct($timeString = null) {
    $timeString = $timeString ?? 'now';
    $this->setCurrentTime($timeString);
    $this->setBettingStatistics();
    $this->setSiteStatistics();
  }

  private function setCurrentTime($timeString) {
    $this->dateTime = new DateTime($timeString);
    $this->dateTime->setTimezone(new DateTimeZone($this->timezone));
    $this->timestamp = $this->dateTime->getTimestamp();
    $this->nowString = $this->dateTime->format('Y-m-d H:i:s');

    // 計算區間
    $mod = ($this->timestamp % $this->intervalSecond);
    $this->dailydate = $this->dateTime->format('Y-m-d');
    $this->dateTime->setTimestamp($this->timestamp - $mod);
    $this->dailytime_start = $this->dateTime->format('H:i:s');
    $this->dateTime->setTimestamp($this->dateTime->getTimestamp() + $this->intervalSecond);
    $this->dailytime_end = $this->dateTime->format('H:i:s');
  }

  private function setSiteStatistics() {
    global $transaction_category;
    $maxHour = max($this->hours);
    $dailydate = $this->dateTime->setTimestamp($this->timestamp - $maxHour * 3600)->format('Y-m-d');

    $sql = <<<SQL
        -- 交易細節 JSON
            SELECT
            transaction_detail,
            -- '{"tokenpay": {"gtoken": {"realcash": 0, "not_realcash": 0}}, "tokengcash": {"gtoken": {"realcash": 5000, "not_realcash": 0}}, "apitokendeposit": {"gtoken": {"realcash": -0.01, "not_realcash": 0}}, "company_deposits": {"gtoken": {"realcash": -160, "not_realcash": 0}}, "tokenadministrationfees": {"gtoken": {"realcash": 50, "not_realcash": 0}}}'::jsonb AS transaction_detail,
            -- 其他的站台統計資訊
            new_member_count AS gpk_new_member,
            first_depositmember_count AS gpk_first_depositmember,
            first_depositamount_sum AS gpk_first_deposit_amount,
            new_agent_count AS gpk_new_agent,
            new_agent_amount AS gpk_new_agent_amount,
            dailydate,
            dailytime_start,
            dailytime_end
            FROM
            root_statisticssite
            WHERE
                dailydate::date >= '$dailydate'::date AND
                dailydate::date <= '$this->dailydate'::date
            ORDER BY dailydate DESC, dailytime_start DESC

SQL;
    $laststats = array_slice(runSQLall($sql), 1);
    // var_dump($laststats);
    // echo "<pre>$sql</pre>";

    array_walk($laststats, function (&$val) use ($transaction_category) {
      $val->transaction_detail = new TransactionDetail($val->transaction_detail, $transaction_category);
    });

    $this->siteStatistics = $laststats;
  }

  private function setBettingStatistics() {
    $maxHour = max($this->hours);
    $dailydate = $this->dateTime->setTimestamp($this->timestamp - $maxHour * 3600)->format('Y-m-d');

    // 撈的時候就先預算一次
    $sql = "SELECT
                dailydate,
                dailytime_start,
                dailytime_end,
                -- 線上玩家數量
                count(DISTINCT (member_account)) AS count_member_account,
                -- 注單額
                sum(account_betting) AS casino_betting,
                -- 有效投注額
                sum(account_betvalid) AS casino_betvalid,
                -- 損益
                sum(account_profit) AS casino_profit,
                -- 派彩
                -sum(CASE WHEN account_profit < 0 THEN account_profit ELSE 0 END) AS casino_payout,
                -- casino_id,
                CASE
                    WHEN sum(account_betvalid) = 0 THEN 0
                    ELSE round(sum(account_profit) /sum(account_betvalid)*100, 2)
                END AS casino_lossrate
                -- sum(total_deposit) AS gpk_deposit,
                -- sum(total_withdrawal) AS gpk_withdrawal,
                FROM root_statisticsbetting
                WHERE
                    dailydate::date >= '$dailydate'::date AND
                    dailydate::date <= '$this->dailydate'::date
                GROUP BY dailydate, dailytime_start, dailytime_end
                ORDER BY dailydate DESC, dailytime_start DESC";

    $this->bettingStatistics = runSQLall_prepared($sql, [], '', 0, 'r');
  }

  /** 計算所有預期中，最近幾個小時的統計 in $this->hours */
  public function getBettingStatisticsByHour() {
    foreach ($this->hours as $hour) {
      $hourStatistic = [
        'casino_betting' => 0,
        'casino_betvalid' => 0,
        'casino_profit' => 0,
        'casino_lossrate' => 0,
        'count_member_account' => 0,
        'time_interval_betting' => [],
      ];
      $limit = $hour * 3600 / 600;
      $count = 0;
      $minStart = strtotime("$this->dailydate $this->dailytime_end - $hour hour");
      foreach ($this->bettingStatistics as $bettingStatisticInfo) {
        $count++;
        if ($count > $limit) {
          break;
        }
        if (strtotime("$bettingStatisticInfo->dailydate $bettingStatisticInfo->dailytime_start") >= $minStart) {
          $hourStatistic['casino_betting'] += $bettingStatisticInfo->casino_betting;
          $hourStatistic['casino_betvalid'] += $bettingStatisticInfo->casino_betvalid;
          $hourStatistic['casino_profit'] += $bettingStatisticInfo->casino_profit;
          $hourStatistic['casino_lossrate'] += $bettingStatisticInfo->casino_lossrate;
          $hourStatistic['count_member_account'] += $bettingStatisticInfo->count_member_account;
          $hourStatistic['time_interval_betting'][] = "$bettingStatisticInfo->dailydate $bettingStatisticInfo->dailytime_start";
        }
      }
      $hourStatistic['time_interval_betting'] = implode(', ', $hourStatistic['time_interval_betting']);
      $result[$hour] = $hourStatistic;
    }

    return $result;
  }

  public function getBettingStatsticsBy10min() {
    return $this->bettingStatistics;
  }

  public function getSiteStatisticsByHour() {
    foreach ($this->hours as $hour) {
      $limit = $hour * 3600 / 600;
      $count = 0;
      $hourStatistic = [
        'gpk_new_member' => 0,
        'gpk_first_depositmember' => 0,
        'gpk_first_deposit_amount' => 0,
        'gpk_new_agent' => 0,
        'gpk_new_agent_amount' => 0,
        'time_interval_site' => [],
      ];
      $minStart = strtotime("$this->dailydate $this->dailytime_end - $hour hour");
      foreach ($this->siteStatistics as $siteStatisticInfo) {
        $count++;
        if ($count > $limit) {
          break;
        }
        if (strtotime("$siteStatisticInfo->dailydate $siteStatisticInfo->dailytime_start") >= $minStart) {
          $hourStatistic['gpk_new_member'] += $siteStatisticInfo->gpk_new_member;
          $hourStatistic['gpk_first_depositmember'] += $siteStatisticInfo->gpk_first_depositmember;
          $hourStatistic['gpk_first_deposit_amount'] += $siteStatisticInfo->gpk_first_deposit_amount;
          $hourStatistic['gpk_new_agent'] += $siteStatisticInfo->gpk_new_agent;
          $hourStatistic['gpk_new_agent_amount'] += $siteStatisticInfo->gpk_new_agent_amount;
          $hourStatistic['time_interval_site'][] = "$siteStatisticInfo->dailydate $siteStatisticInfo->dailytime_start";

          // 這邊計算幣別x入、出、差款
          $hourStatistic['depositSubtotal'] = $hourStatistic['depositSubtotal'] ?? ['gcash' => 0, 'gtoken' => 0];
          $hourStatistic['withdrawalSubtotal'] = $hourStatistic['withdrawalSubtotal'] ?? ['gcash' => 0, 'gtoken' => 0];
          $hourStatistic['depositTotal'] = $hourStatistic['depositTotal'] ?? 0;
          $hourStatistic['withdrawalTotal'] = $hourStatistic['withdrawalTotal'] ?? 0;

          $hourStatistic['depositSubtotal']['gcash'] += $siteStatisticInfo->transaction_detail->siteDeposit['gcash'];
          $hourStatistic['depositSubtotal']['gtoken'] += $siteStatisticInfo->transaction_detail->siteDeposit['gtoken'];
          $hourStatistic['withdrawalSubtotal']['gcash'] += abs($siteStatisticInfo->transaction_detail->siteWithdrawal['gcash']);
          $hourStatistic['withdrawalSubtotal']['gtoken'] += abs($siteStatisticInfo->transaction_detail->siteWithdrawal['gtoken']);

          // 以下的貨幣總和，應該要能和上述做驗算
          $hourStatistic['depositTotal'] += $siteStatisticInfo->transaction_detail->getTotalDeposit();
          $hourStatistic['withdrawalTotal'] += abs($siteStatisticInfo->transaction_detail->getTotalWithdrawal());
        }
      }
      $hourStatistic['time_interval_site'] = implode(', ', $hourStatistic['time_interval_site']);
      $result[$hour] = $hourStatistic;
    }

    return $result;
  }

  public function getSiteStatisticsBy10min() {
    return $this->siteStatistics;
  }

  public function getHomeStatisticsByHour() {
    $bettingResult = $this->getBettingStatisticsByHour();
    $siteResult = $this->getSiteStatisticsByHour();
    $homeResult = [];

    foreach ($this->hours as $hour) {
      $homeResult[$hour] = array_merge($bettingResult[$hour], $siteResult[$hour]);
    }

    return $homeResult;
  }

  public function getHomeStatisticsBy10min($hours = 2) {
    $limit = ceil(3600 / $this->intervalSecond) * $hours;
    $homeResult = [];

    foreach ($this->siteStatistics as $index => $siteStatisticInfo) {
      if ($index > $limit) {
        break;
      }

      $homeSiteStatistic = new stdClass;

      $homeSiteStatistic->dailydate = $siteStatisticInfo->dailydate;
      $homeSiteStatistic->dailytime_start = $siteStatisticInfo->dailytime_start;
      $homeSiteStatistic->dailytime_end = $siteStatisticInfo->dailytime_end;
      $homeSiteStatistic->gpk_new_member = $siteStatisticInfo->gpk_new_member;
      $homeSiteStatistic->gpk_first_depositmember = $siteStatisticInfo->gpk_first_depositmember;
      $homeSiteStatistic->gpk_first_deposit_amount = $siteStatisticInfo->gpk_first_deposit_amount;
      $homeSiteStatistic->gpk_new_agent = $siteStatisticInfo->gpk_new_agent;
      $homeSiteStatistic->gpk_new_agent_amount = $siteStatisticInfo->gpk_new_agent_amount;
      $homeSiteStatistic->time_interval_site = "$siteStatisticInfo->dailydate $siteStatisticInfo->dailytime_start";
      $homeSiteStatistic->transaction_detail = $siteStatisticInfo->transaction_detail;

      // 這邊計算幣別x入、出、差款
      $homeSiteStatistic->depositSubtotal['gcash'] = $siteStatisticInfo->transaction_detail->siteDeposit['gcash'];
      $homeSiteStatistic->depositSubtotal['gtoken'] = $siteStatisticInfo->transaction_detail->siteDeposit['gtoken'];
      $homeSiteStatistic->withdrawalSubtotal['gcash'] = $siteStatisticInfo->transaction_detail->siteWithdrawal['gcash'];
      $homeSiteStatistic->withdrawalSubtotal['gtoken'] = $siteStatisticInfo->transaction_detail->siteWithdrawal['gtoken'];

      // 以下的貨幣總和，應該要能和上述做驗算
      $homeSiteStatistic->depositTotal = $siteStatisticInfo->transaction_detail->getTotalDeposit();
      $homeSiteStatistic->withdrawalTotal = $siteStatisticInfo->transaction_detail->getTotalWithdrawal();

      $homeResult[] = $homeSiteStatistic;
    }

    foreach ($homeResult as &$homeSiteStatistic) {
      $homeSiteStatistic->casino_betting = 0;
      $homeSiteStatistic->casino_betvalid = 0;
      $homeSiteStatistic->casino_profit = 0;
      $homeSiteStatistic->casino_lossrate = 0;
      $homeSiteStatistic->count_member_account = 0;
      $homeSiteStatistic->time_interval_betting = "(debug info) 这段时间没有投注纪录!!";

      foreach ($this->bettingStatistics as $bettingStatisticInfo) {
        if ("$bettingStatisticInfo->dailydate $bettingStatisticInfo->dailytime_start" == $homeSiteStatistic->time_interval_site) {
          $homeSiteStatistic->casino_betting = $bettingStatisticInfo->casino_betting;
          $homeSiteStatistic->casino_betvalid = $bettingStatisticInfo->casino_betvalid;
          $homeSiteStatistic->casino_profit = $bettingStatisticInfo->casino_profit;
          $homeSiteStatistic->casino_lossrate = $bettingStatisticInfo->casino_lossrate;
          $homeSiteStatistic->count_member_account = $bettingStatisticInfo->count_member_account;
          $homeSiteStatistic->time_interval_betting = "$bettingStatisticInfo->dailydate $bettingStatisticInfo->dailytime_start";
          break;
        }
      }
    }

    return $homeResult;
  }
}

function get_global_currency_type(): string {
  $values = [
    'setting_name' => 'default',
    'setting_attr' => 'member_deposit_currency',
  ];
  $setting_query_res = runSQLall_prepared("SELECT value FROM root_protalsetting WHERE setttingname = :setting_name AND name = :setting_attr", $values)[0];

  return $setting_query_res->value;
}

// -------------------------------------------------------------------------
// GET / POST 傳值處理
// -------------------------------------------------------------------------

if (isset($_GET['a']) AND filter_var($_GET['a'], FILTER_VALIDATE_INT)) {
  $time_range = filter_var($_GET['a'], FILTER_VALIDATE_INT);
} else {
  $time_range = 2;
}

// -------------------------------------------------------------------------
// GET / POST 傳值處理 END
// -------------------------------------------------------------------------

// -------------------------------------------------------------------------
// custom function
// -------------------------------------------------------------------------
// 對陣列中所有鍵值做 abs
function array_abs(array $Arr) {
  return array_map(function ($val) {return abs($val);}, $Arr);
}

// ----------------------------------------------------------------------------
// Main
// ----------------------------------------------------------------------------
// 初始化變數
// 功能標題，放在標題列及meta
$function_title = $tr['system_dashboard'];
// 擴充 head 內的 css or js
$extend_head = '';
// 放在結尾的 js
$extend_js = '';
// body 內的主要內容
$indexbody_content = '';
// 目前所在位置 - 配合選單位置讓使用者可以知道目前所在位置
$menu_breadcrumbs = '
<ol class="breadcrumb">
  <li><a href="home.php">' . $tr['Home'] . '</a></li>
  <li class="active">' . $function_title . '</li>
</ol>';
// 型別轉換工具 in lib_common.php
$converter = new TypeConverter;

// -----------------------------------------------------------------------------
// 將資料以表格方式呈現10分鐘報表 -- from root_statisticsbetting
// -----------------------------------------------------------------------------
// 目前时间 -05 timezone
$current_datepicker = gmdate('Y-m-d', time()+-4 * 3600);
$current_timepicker = gmdate('H:i:s', time()+-4 * 3600);

$record_limit = 6 * $time_range;

// $home_dashboard = new DashboardHome('2018-06-10 13:48:50');
$home_dashboard = new DashboardHome('now');
$stats_hrs_summary = $home_dashboard->getHomeStatisticsByHour();
$stats_10mins_summary = $home_dashboard->getHomeStatisticsBy10min($hours = $time_range);

$count_of_10min_data = count($stats_10mins_summary);
array_unshift($stats_10mins_summary, $count_of_10min_data);
$show_laststats_result = $stats_10mins_summary;
// var_dump($show_laststats_result);

// 陣列宣告
$statistics_pivot = [];
$stats_laststats_array = [];
$stats_laststats_data = '';

if ($show_laststats_result[0] > 0) {
  $statistics = array_reverse(array_slice($show_laststats_result, 1));

  // 準備放入 json 的資料
  array_walk($statistics, function ($row, $key) use (&$statistics_pivot, &$stats_laststats_array, $converter) {
    // var_dump($row); die;
    $statistics_pivot['lastdailytime_start_array'][] = $row->dailytime_start;
    $statistics_pivot['count_accountnumber'][] = (int) $row->count_member_account;
    $statistics_pivot['sum_betting'][] = (int) $row->casino_betting;
    $statistics_pivot['sum_totalwager'][] = (float) $row->casino_betvalid;
    $statistics_pivot['sum_profit'][] = (float) $row->casino_profit;
    $statistics_pivot['loss_rate_array'][] = (float) $row->casino_lossrate;
    $statistics_pivot['cashdeposit_array'][] = (float) $row->transaction_detail->siteDeposit['gcash'];
    $statistics_pivot['cashgtoken_array'][] = (float) ($row->transaction_detail->data->cashgtoken->realcash ?? 0);
    $statistics_pivot['tokendeposit_array'][] = (float) $row->transaction_detail->siteDeposit['gtoken'];
    $statistics_pivot['cashwithdrawal_array'][] = (float) $row->transaction_detail->siteWithdrawal['gcash'];
    $statistics_pivot['tokengcash_array'][] = (float) ($row->transaction_detail->data->tokengcash->realcash ?? 0);
    $statistics_pivot['new_member_count_array'][] = (int) $row->gpk_new_member;
    $statistics_pivot['first_depositmember_count_array'][] = (int) $row->gpk_first_depositmember;

    $stringtofloat = function ($number, $decimals = 2) {return number_format($number, $decimals);};
    // 日期, 时间, MG games 有多少人投注 count_accountnumber, 投注量 sum_betting 多少, 投注額 sum_totalwager 多少 , 損益 sum_profit 多少？
    $stats_laststats_array[] = <<<HTML
        <tr>
            <td>$row->dailydate</td>
            <td>$row->dailytime_start</td>
            <td>$row->dailytime_end</td>
            <td>{$converter->add($row->gpk_new_member)->numberFormat(0,'.',',')->commit()}</td>
            <td>{$converter->add($row->gpk_first_depositmember)->numberFormat(0,'.',',')->commit()}</td>
            <td>{$converter->add($row->count_member_account)->numberFormat(0,'.',',')->commit()}</td>
            <td>{$converter->add($row->casino_betting)->numberFormat(0,'.',',')->commit()}</td>
            <td>{$converter->add($row->casino_betvalid)->numberFormat()->commit()}</td>
            <td>{$converter->add($row->casino_profit)->numberFormat()->commit()}</td>
            <td>{$converter->add($row->casino_lossrate)->numberFormat()->commit()}</td>
            <td>{$converter->add($row->transaction_detail->siteDeposit['gcash'])->numberFormat()->commit()}</td>
            <td>{$converter->add($row->transaction_detail->siteWithdrawal['gcash'])->numberFormat()->commit()}</td>
            <td>{$converter->add($row->transaction_detail->cashgtoken)->numberFormat()->commit()}</td>
            <td>{$converter->add($row->transaction_detail->siteDeposit['gtoken'])->numberFormat()->commit()}</td>
            <td>{$converter->add($row->transaction_detail->siteWithdrawal['gtoken'])->numberFormat()->commit()}</td>
        </tr>
HTML;
  });

  $stats_laststats_data = implode('', array_reverse($stats_laststats_array));
  // 數字呈現的統計表格
  $table_laststats_html = <<<HTML
    <hr>
    <h3>{$tr['10-minute interval statistics']}</h3>
    <table class="table table-hover" width="98%">
        <thead>
            <tr>
                <th rowspan="2" class="text-center">{$tr['date']}</th>
                <th rowspan="2" class="text-center">{$tr['Starting time']}</th>
                <th rowspan="2" class="text-center">{$tr['End time']}</th>
                <th rowspan="2" class="text-center">{$tr['Number of new members']}</th>
                <th rowspan="2" class="text-center">{$tr['First deposit member']}</th>
                <th colspan="5" class="text-center well">{$tr['Online betting information']}</th>
                <th colspan="3" class="text-center well">{$tr['cash data']}</th>
                <th colspan="2" class="text-center well">{$tr['gtoken data']}</th>
            </tr>
            <tr>
                <!-- 投注 -->
                <th class="text-center">{$tr['Number of online bets']}</th>
                <th class="text-center">{$tr['bet slip']}</th>
                <th class="text-center">{$tr['bet amount']}</th>
                <th class="text-center">{$tr['Interval profit and loss']}</th>
                <th class="text-center border-right">{$tr['Probability of winning']}</th>
                <!-- 現金  -->
                <th class="text-center">{$tr['Deposit']}</th>
                <th class="text-center">{$tr['Withdrawal']}</th>
                <th class="text-center border-right">{$tr['Turn the gtoken']}</th>
                <!-- 遊戲幣 -->
                <th class="text-center">{$tr['Deposit']}</th>
                <th class="text-center border-right">{$tr['Withdrawal']}</th>
            </tr>
        </thead>
        <tbody>$stats_laststats_data</tbody>
    </table>
HTML;

  $table_summary_data_row = function ($data, $hr) use ($converter) {
    global $tr;
    $data = (object) $data;
    return @$value = <<<HTML
        <tr>
            <th>{$tr['latest']} $hr {$tr['hours']}</th>
            <td>{$converter->add($data->gpk_new_agent)->numberFormat(0,'.',',')->commit()}</td>
            <td>{$converter->add($data->gpk_new_agent_amount)->numberFormat()->commit()}</td>
            <td>{$converter->add($data->gpk_new_member)->numberFormat(0,'.',',')->commit()}</td>
            <td>{$converter->add($data->gpk_first_depositmember)->numberFormat(0,'.',',')->commit()}</td>
            <td>{$converter->add($data->gpk_first_deposit_amount)->numberFormat()->commit()}</td>
            <td>{$converter->add($data->depositTotal)->numberFormat()->commit()}</td>
            <td>{$converter->add($data->withdrawalTotal)->numberFormat()->commit()}</td>
            <td>{$converter->add($data->depositTotal-$data->withdrawalTotal)->numberFormat()->commit()}</td>
            <td>{$converter->add($data->casino_betvalid)->numberFormat()->commit()}</td>
            <td>{$converter->add($data->casino_profit)->numberFormat()->commit()}</td>
        </tr>
HTML;
  };

  $table_summary_data_arary = array_map($table_summary_data_row, $stats_hrs_summary, array_keys($stats_hrs_summary));
  $table_summary_data = implode('', $table_summary_data_arary);

  $table_summary_data_row_gcash = function ($data, $hr) use ($converter) {
    global $tr;
    $data = (object) $data;

    $is_gcash = get_global_currency_type() == 'gcash';
    $first_deposit_count = $is_gcash ? $converter->add($data->gpk_first_depositmember)->numberFormat(0, '.', ',')->commit() : 0;
    $first_deposit_amount = $is_gcash ? $converter->add($data->gpk_first_deposit_amount)->numberFormat()->commit() : '0.00';

    return @$value = <<<HTML
        <tr>
            <th>{$tr['latest']} $hr {$tr['hours']}</th>
            <td>$first_deposit_count</td>
            <td>$first_deposit_amount</td>
            <td>{$converter->add($data->depositSubtotal['gcash'])->numberFormat()->commit()}</td>
            <td>{$converter->add($data->withdrawalSubtotal['gcash'])->numberFormat()->commit()}</td>
            <td>{$converter->add($data->depositSubtotal['gcash']-$data->withdrawalSubtotal['gcash'])->numberFormat()->commit()}</td>
        </tr>
HTML;
  };
  $table_summary_data_arary_gcash = array_map($table_summary_data_row_gcash, $stats_hrs_summary, array_keys($stats_hrs_summary));
  $table_summary_data_gcash = implode('', $table_summary_data_arary_gcash);

  $table_summary_data_row_gtoken = function ($data, $hr) use ($converter) {
    global $tr;
    $data = (object) $data;

    $is_gtoken = get_global_currency_type() == 'gtoken';
    $first_deposit_count = $is_gtoken ? $converter->add($data->gpk_first_depositmember)->numberFormat(0, '.', ',')->commit() : 0;
    $first_deposit_amount = $is_gtoken ? $converter->add($data->gpk_first_deposit_amount)->numberFormat()->commit() : '0.00';

    return @$value = <<<HTML
        <tr>
            <th>{$tr['latest']} $hr {$tr['hours']}</th>
            <td>{$first_deposit_count}</td>
            <td>{$first_deposit_amount}</td>
            <td>{$converter->add($data->depositSubtotal['gtoken'])->numberFormat()->commit()}</td>
            <td>{$converter->add($data->withdrawalSubtotal['gtoken'])->numberFormat()->commit()}</td>
            <td>{$converter->add($data->depositSubtotal['gtoken']-$data->withdrawalSubtotal['gtoken'])->numberFormat()->commit()}</td>
            <td>{$converter->add($data->casino_betvalid)->numberFormat()->commit()}</td>
            <td>{$converter->add($data->casino_profit)->numberFormat()->commit()}</td>
        </tr>
HTML;
  };
  $table_summary_data_arary_gtoken = array_map($table_summary_data_row_gtoken, $stats_hrs_summary, array_keys($stats_hrs_summary));
  $table_summary_data_gtoken = implode('', $table_summary_data_arary_gtoken);

// 加入轉帳到 token 的判斷
  // table: root_protalsetting
  switch (get_global_currency_type()):
case 'gcash':
  $first_deposit_review_sql = <<<SQL
        -- 首存的帳號、時間、金額
            SELECT source_transferaccount, deposit, transaction_time AS first_deposit_time FROM "root_member_gcashpassbook" WHERE transaction_time IN (
                SELECT MIN(transaction_time) FROM "root_member_gcashpassbook" GROUP BY source_transferaccount
            ) AND source_transferaccount != '{$gcash_cashier_account}'
SQL;
  break;
case 'gtoken':
  $first_deposit_review_sql = <<<SQL
            -- 首存的帳號、時間、金額
            SELECT source_transferaccount, deposit, transaction_time AS first_deposit_time FROM "root_member_gtokenpassbook" WHERE transaction_time IN (
                SELECT MIN(transaction_time) FROM "root_member_gtokenpassbook" GROUP BY source_transferaccount
            ) AND source_transferaccount != '{$gtoken_cashier_account}'
SQL;
  break;
default:
  die('沒有適合的貨幣類別!');
  break;
  endswitch;

  $table_summary_data_user = function () use ($current_datepicker, $gcash_cashier_account, $first_deposit_review_sql) {
    $stmt = <<<SQL
    WITH "realtime_new_member_count" AS (
        SELECT count(account) AS new_member_count FROM root_member WHERE enrollmentdate >= '{$current_datepicker} -04' AND status = '1'
    ), "first_deposit_review" AS (
        {$first_deposit_review_sql}
    ), "realtime_first_deposit" AS (
        SELECT count(*) AS first_depositmember_count, sum(deposit) AS first_depositamount_sum FROM (
            SELECT root_member.id, root_member.account, first_deposit_review.deposit, first_deposit_review.first_deposit_time FROM root_member
            LEFT JOIN first_deposit_review ON root_member.account = first_deposit_review.source_transferaccount
            -- 當天註冊的會員
            WHERE root_member.enrollmentdate >= '{$current_datepicker} -04' AND
            root_member.status = '1' AND
            -- 在时间間隔內首存
            first_deposit_review.first_deposit_time >= '{$current_datepicker} -04'
        ) fd_count_by_acc
    )

    SELECT *, CASE WHEN first_depositamount_sum IS NULL THEN 0.00 ELSE first_depositamount_sum END AS first_depositamount_sum
    FROM realtime_new_member_count, realtime_first_deposit
SQL;
    return runSQLall($stmt)[1];
  };

  $table_summary_html = <<<HTML
    <hr>
    <h3><span class="glyphicon glyphicon-info-sign"></span>{$tr['information']}</h3>
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
    <div class="panel-group">
        <div class="panel panel-default">
            <div class="panel-heading">
                <h3 class="panel-title text-center">
                    <a href="#collapse-user" data-toggle="collapse">{$tr['user data']}</a>
                </h3>
            </div>
            <div id="collapse-user" class="panel-collapse collapse show">
                <div class="panel-body">
                    <table class="table table-hover" width="98%" id="table_user">
                        <thead>
                            <tr>
                                <th title="范例时间 2017-12-25 00:00:00+08 至 2017-12-25 17:10:00+08">{$tr['time']}</th>
                                <th title="本日注册的新进会员人数">{$tr['New members today']}</th>
                                <th title="在今日执行了自注册以来的第一次存款人数">{$tr['Registered first depositors today']}</th>
                                <th title="在今日执行了自注册以来的第一次存款总额">{$tr['Register the first deposit amount today']}</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>$current_datepicker 00:00:00<br>{$tr['to']}<br>$current_datepicker $current_timepicker</td>
                                <td>{$converter->add($table_summary_data_user()->new_member_count)->numberFormat(0,'.',',')->commit()}</td>
                                <td>{$converter->add($table_summary_data_user()->first_depositmember_count)->numberFormat(0,'.',',')->commit()}</td>
                                <td>{$converter->add($table_summary_data_user()->first_depositamount_sum)->numberFormat()->commit()}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <!-- <div class="panel-group">
        <div class="panel panel-default">
            <div class="panel-heading">
                <h3 class="panel-title text-center">
                    <a href="#collapse-operation" data-toggle="collapse">充提數據</a>
                </h3>
            </div>
            <div id="collapse-operation" class="panel-collapse collapse">
                <div class="panel-body">
                    <table class="table table-hover" width="98%" id="table_amount">
                        <thead>
                            <tr>
                                <th colspan="2">入款筆數</th>
                                <th colspan="2">入款人數</th>
                                <th colspan="2">總入款額</th>
                                <th colspan="2">取款筆數</th>
                                <th colspan="2">取款人數</th>
                                <th colspan="2">總取款額</th>
                            </tr>
                            <tr>
                                <th>公司入款</th>
                                <th>線上支付</th>
                                <th>公司入款</th>
                                <th>線上支付</th>
                                <th>公司入款</th>
                                <th>線上支付</th>
                                <th>preserve</th>
                                <th>preserve</th>
                                <th>preserve</th>
                                <th>preserve</th>
                                <th>preserve</th>
                                <th>preserve</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>null</td>
                                <td>null</td>
                                <td>null</td>
                                <td>null</td>
                                <td>null</td>
                                <td>null</td>
                                <td>null</td>
                                <td>null</td>
                                <td>null</td>
                                <td>null</td>
                                <td>null</td>
                                <td>null</td>
                            </tr>
                            <tr>
                                <td colspan="2">null 筆</td>
                                <td colspan="2">null 人</td>
                                <td colspan="2">null 元</td>
                                <td colspan="2">null 筆</td>
                                <td colspan="2">null 人</td>
                                <td colspan="2">null 元</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div> -->
    <!-- 現金數據 -->
    <div class="panel-group">
        <div class="panel panel-default">
            <div class="panel-heading">
                <h3 class="panel-title text-center">
                    <a href="#collapse-summary-gcash" data-toggle="collapse">{$tr['cash data']}</a>
                </h3>
            </div>
            <div id="collapse-summary-gcash" class="panel-collapse collapse">
                <div class="panel-body">
                    <table class="table table-hover" width="98%" id="table_summary_gcash">
                        <thead>
                            <tr>
                                <th></th>
                                <th title="新进会员的累积首存人数">{$tr['Number of first depositors']}</th>
                                <th title="新进会员的累积首存总额">{$tr['First deposit amount']}</th>
                                <th title="现金存款与电子支付存款，存款到現金">{$tr['Total amount of money']}</th>
                                <th title="现金取款总额">{$tr['Total withdrawal']}</th>
                                <th title=" = 现金的存款与取款的差额">{$tr['deposit and withdrawal balance']}</th>
                            </tr>
                        </thead>
                        <tbody>
                            $table_summary_data_gcash
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <!-- 遊戲幣數據 -->
    <div class="panel-group">
        <div class="panel panel-default">
            <div class="panel-heading">
                <h3 class="panel-title text-center">
                    <a href="#collapse-summary-gtoken" data-toggle="collapse">{$tr['gtoken data']}</a>
                </h3>
            </div>
            <div id="collapse-summary-gtoken" class="panel-collapse collapse">
                <div class="panel-body">
                    <table class="table table-hover" width="98%" id="table_summary_gtoken">
                        <thead>
                            <tr>
                                <th></th>
                                <th title="{$tr['Number of new members']}">{$tr['Number of first depositors']}</th>
                                <th title="{$tr['Total accumulated deposits of new members']}">{$tr['First deposit amount']}</th>
                                <th title="{$tr['Cash deposits and electronic payments to the gtoken']}">{$tr['Total amount of money']}</th>
                                <th title="{$tr['Total gtoken withdrawal']}">{$tr['Total withdrawal']}</th>
                                <th title="{$tr['The difference between the deposit and withdrawal of the gtoken']}">{$tr['deposit and withdrawal balance']}</th>
                                <th>{$tr['effective bet amount']}</th>
                                <th>{$tr['profit and loss']}</th>
                            </tr>
                        </thead>
                        <tbody>
                            $table_summary_data_gtoken
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <!-- 綜合數據 -->
    <div class="panel-group">
        <div class="panel panel-default">
            <div class="panel-heading">
                <h3 class="panel-title text-center">
                    <a href="#collapse-summary" data-toggle="collapse" title="{$tr['Comprehensive statistics on cash and gtoken']}">{$tr['Integrated data']}</a>
                </h3>
            </div>
            <div id="collapse-summary" class="panel-collapse collapse show">
                <div class="panel-body">
                    <table class="table table-hover" width="98%" id="table_summary_complex">
                        <thead>
                            <tr>
                                <th></th>
                                <th>{$tr['agent']}({$tr['people']})</th>
                                <th>{$tr['Agent Franchise Fee']}</th>
                                <th title="{$tr['Number of new members']}">{$tr['Number of new members']}</th>
                                <th title="{$tr['The cumulative number of first deposits of new members']}">{$tr['Number of first depositors']}</th>
                                <th title="{$tr['Total accumulated deposits of new members']}">{$tr['First deposit amount']}</th>
                                <th title="{$tr['Cash deposits and gtoken deposits']}">{$tr['Total amount of money']}</th>
                                <th title="{$tr['Cash withdrawals and gtoken withdrawals']}">{$tr['Total withdrawal']}</th>
                                <th title="{$tr['The difference between the total amount of the deposit and the total amount of the withdrawal']}">{$tr['deposit and withdrawal balance']}</th>
                                <th>{$tr['effective bet amount']}</th>
                                <th>{$tr['profit and loss']}</th>
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

} else {
  // 沒資料
  $table_laststats_html = $table_summary_html = '';
  // 沒資料設定為日期空
  $dailydate = NULL;
}

// -----------------------------------------------------------------------------
// END -- 將資料以表格方式呈現 -- from root_statisticsbetting
// -----------------------------------------------------------------------------

// -----------------------------------------------------------------------------
// 以視覺化方式呈現10分鐘數據 -- chart 1 线上投注人数
// -----------------------------------------------------------------------------
// 取得 time 的基本資訊
$lastdailytime_start_array_json = json_encode($statistics_pivot['lastdailytime_start_array'] ?? []);
// 取得每個時段的人數
$count_accountnumber_json = json_encode($statistics_pivot['count_accountnumber'] ?? []);

$laststatcharts_container_title = $tr['Number of online bets'];
$laststatcharts_container_y_desc = $tr['Number of online bets'];
$laststatcharts_container_point_desc = $tr['people'];
$laststatcharts_container_1 = "
Highcharts.chart('laststatcharts_container_1', {
        chart: {
                type: 'column'
        },
        title: {
                text: '" . $laststatcharts_container_title . "'
        },
        xAxis: {
                categories: " . $lastdailytime_start_array_json . ",
                crosshair: true
        },
        yAxis: {
                min: 0,
                title: {
                        text: '" . $laststatcharts_container_y_desc . "'
                }
        },
        tooltip: {
                headerFormat: '<span style=\"font-size:8px\">{point.key}</span><table>',
                pointFormat: '<tr><td style=\"color:{series.color};padding:0\">{series.name}: </td>' +
                        '<td style=\"padding:0\"><b>{point.y:.1f}" . $laststatcharts_container_point_desc . "</b></td></tr>',
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
                name: '" . $laststatcharts_container_y_desc . "',
                data: " . $count_accountnumber_json . "

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

// -----------------------------------------------------------------------------
// 以視覺化方式呈現10分鐘數據 -- chart 2 損益量
// -----------------------------------------------------------------------------
$sum_profit_json = json_encode($statistics_pivot['sum_profit'] ?? []);

$laststatcharts_container_title = $tr['profit and loss'];
$laststatcharts_container_y_desc = $tr['amount of profit and loss'];
$laststatcharts_container_point_desc = $config['currency_sign'];
$laststatcharts_container_2 = "
Highcharts.chart('laststatcharts_container_2', {
        chart: {
                type: 'column'
        },
        title: {
                text: '" . $laststatcharts_container_title . "'
        },
        xAxis: {
                categories: " . $lastdailytime_start_array_json . ",
                crosshair: true
        },
        yAxis: {
                min: 0,
                title: {
                        text: '" . $laststatcharts_container_y_desc . "'
                }
        },
        tooltip: {
                headerFormat: '<span style=\"font-size:8px\">{point.key}</span><table>',
                pointFormat: '<tr><td style=\"color:{series.color};padding:0\">{series.name}: </td>' +
                        '<td style=\"padding:0\"><b>{point.y:.1f}" . $laststatcharts_container_point_desc . "</b></td></tr>',
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
                name: '" . $laststatcharts_container_y_desc . "',
                data: " . $sum_profit_json . "

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

// -----------------------------------------------------------------------------
// 以視覺化方式呈現10分鐘數據 -- chart 3 投注量
// -----------------------------------------------------------------------------
$sum_betting_json = json_encode($statistics_pivot['sum_betting'] ?? []);

$laststatcharts_container_title = $tr['bet slip'];
$laststatcharts_container_y_desc = $tr['bet slip'];
$laststatcharts_container_point_desc = $tr['Count'];
$laststatcharts_container_3 = "
Highcharts.chart('laststatcharts_container_3', {
        chart: {
                type: 'column'
        },
        title: {
                text: '" . $laststatcharts_container_title . "'
        },
        xAxis: {
                categories: " . $lastdailytime_start_array_json . ",
                crosshair: true
        },
        yAxis: {
                min: 0,
                title: {
                        text: '" . $laststatcharts_container_y_desc . "'
                }
        },
        tooltip: {
                headerFormat: '<span style=\"font-size:8px\">{point.key}</span><table>',
                pointFormat: '<tr><td style=\"color:{series.color};padding:0\">{series.name}: </td>' +
                        '<td style=\"padding:0\"><b>{point.y:.1f}" . $laststatcharts_container_point_desc . "</b></td></tr>',
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
                name: '" . $laststatcharts_container_y_desc . "',
                data: " . $sum_betting_json . "

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

// -----------------------------------------------------------------------------
// 以視覺化方式呈現10分鐘數據 -- chart 4 投注額
// -----------------------------------------------------------------------------
$sum_totalwager_json = json_encode($statistics_pivot['sum_totalwager'] ?? []);

$laststatcharts_container_title = $tr['effective bet amount'];
$laststatcharts_container_y_desc = $tr['Betting amount_1'];
$laststatcharts_container_point_desc = $config['currency_sign'];
$laststatcharts_container_4 = "
Highcharts.chart('laststatcharts_container_4', {
        chart: {
                type: 'column'
        },
        title: {
                text: '" . $laststatcharts_container_title . "'
        },
        xAxis: {
                categories: " . $lastdailytime_start_array_json . ",
                crosshair: true
        },
        yAxis: {
                min: 0,
                title: {
                        text: '" . $laststatcharts_container_y_desc . "'
                }
        },
        tooltip: {
                headerFormat: '<span style=\"font-size:8px\">{point.key}</span><table>',
                pointFormat: '<tr><td style=\"color:{series.color};padding:0\">{series.name}: </td>' +
                        '<td style=\"padding:0\"><b>{point.y:.1f}" . $laststatcharts_container_point_desc . "</b></td></tr>',
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
                name: '" . $laststatcharts_container_y_desc . "',
                data: " . $sum_totalwager_json . "

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

// -----------------------------------------------------------------------------
// 以視覺化方式呈現10分鐘數據 -- chart 5 咬度
// -----------------------------------------------------------------------------
// 取得每個時段的咬度
$loss_rate_json = json_encode($statistics_pivot['loss_rate_array'] ?? []);

$laststatcharts_container_title = $tr['Probability of winning'];
$laststatcharts_container_y_desc = $tr['Probability of winning'];
$laststatcharts_container_point_desc = '%';
$laststatcharts_container_5 = "
Highcharts.chart('laststatcharts_container_5', {
        chart: {
                type: 'column'
        },
        title: {
                text: '" . $laststatcharts_container_title . "'
        },
        xAxis: {
                categories: " . $lastdailytime_start_array_json . ",
                crosshair: true
        },
        yAxis: {
                min: 0,
                title: {
                        text: '" . $laststatcharts_container_y_desc . "'
                }
        },
        tooltip: {
                headerFormat: '<span style=\"font-size:8px\">{point.key}</span><table>',
                pointFormat: '<tr><td style=\"color:{series.color};padding:0\">{series.name}: </td>' +
                        '<td style=\"padding:0\"><b>{point.y:.1f}" . $laststatcharts_container_point_desc . "</b></td></tr>',
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
                name: '" . $laststatcharts_container_y_desc . "',
                data: " . $loss_rate_json . "

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

// -----------------------------------------------------------------------------
// 以視覺化方式呈現10分鐘數據 -- chart 6 總入款量
// -----------------------------------------------------------------------------
// 取得每個時段的總入款量
// $total_deposit_json = json_encode($statistics_pivot['total_deposit_array'] ?? []);

// $laststatcharts_container_title = '總入款量';
// $laststatcharts_container_y_desc = '總入款量';
// $laststatcharts_container_point_desc = $config['currency_sign'];
// $laststatcharts_container_6 = "
// Highcharts.chart('laststatcharts_container_6', {
//     chart: {
//         type: 'column'
//     },
//     title: {
//         text: '".$laststatcharts_container_title."'
//     },
//     xAxis: {
//         categories: ".$lastdailytime_start_array_json.",
//         crosshair: true
//     },
//     yAxis: {
//         min: 0,
//         title: {
//             text: '".$laststatcharts_container_y_desc."'
//         }
//     },
//     tooltip: {
//         headerFormat: '<span style=\"font-size:8px\">{point.key}</span><table>',
//         pointFormat: '<tr><td style=\"color:{series.color};padding:0\">{series.name}: </td>' +
//             '<td style=\"padding:0\"><b>{point.y:.1f}".$laststatcharts_container_point_desc."</b></td></tr>',
//         footerFormat: '</table>',
//         shared: true,
//         useHTML: true
//     },
//     plotOptions: {
//         column: {
//             pointPadding: 0.2,
//             borderWidth: 0
//         }
//     },
//     series: [{
//         name: '".$laststatcharts_container_y_desc."',
//         data: ".$total_deposit_json."

//     }],
//     credits: {
//       enabled: false
//     },
//     exporting: {
//       buttons: [
//         {  enabled: false }
//       ]
//     }
// });
// ";

// -----------------------------------------------------------------------------
// 以視覺化方式呈現10分鐘數據 -- chart 7 總取款量
// -----------------------------------------------------------------------------
// 取得每個時段的總取款量
// $total_withdrawal_json = json_encode($statistics_pivot['total_withdrawal_array'] ?? []);

// $laststatcharts_container_title = '總取款量';
// $laststatcharts_container_y_desc = '總取款量';
// $laststatcharts_container_point_desc = $config['currency_sign'];
// $laststatcharts_container_7 = "
// Highcharts.chart('laststatcharts_container_7', {
//     chart: {
//         type: 'column'
//     },
//     title: {
//         text: '".$laststatcharts_container_title."'
//     },
//     xAxis: {
//         categories: ".$lastdailytime_start_array_json.",
//         crosshair: true
//     },
//     yAxis: {
//         min: 0,
//         title: {
//             text: '".$laststatcharts_container_y_desc."'
//         }
//     },
//     tooltip: {
//         headerFormat: '<span style=\"font-size:8px\">{point.key}</span><table>',
//         pointFormat: '<tr><td style=\"color:{series.color};padding:0\">{series.name}: </td>' +
//             '<td style=\"padding:0\"><b>{point.y:.1f}".$laststatcharts_container_point_desc."</b></td></tr>',
//         footerFormat: '</table>',
//         shared: true,
//         useHTML: true
//     },
//     plotOptions: {
//         column: {
//             pointPadding: 0.2,
//             borderWidth: 0
//         }
//     },
//     series: [{
//         name: '".$laststatcharts_container_y_desc."',
//         data: ".$total_withdrawal_json."

//     }],
//     credits: {
//       enabled: false
//     },
//     exporting: {
//       buttons: [
//         {  enabled: false }
//       ]
//     }
// });
// ";

// -----------------------------------------------------------------------------
// 以視覺化方式呈現10分鐘數據 -- chart 8 新进会员数
// -----------------------------------------------------------------------------
// 取得每個時段的新进会员数
$new_member_count_json = json_encode($statistics_pivot['new_member_count_array'] ?? []);

$laststatcharts_container_title = $tr['Number of new members'];
$laststatcharts_container_y_desc = $tr['Number of new members'];
$laststatcharts_container_point_desc = $tr['people'];
$laststatcharts_container_8 = "
Highcharts.chart('laststatcharts_container_8', {
        chart: {
                type: 'column'
        },
        title: {
                text: '" . $laststatcharts_container_title . "'
        },
        xAxis: {
                categories: " . $lastdailytime_start_array_json . ",
                crosshair: true
        },
        yAxis: {
                min: 0,
                title: {
                        text: '" . $laststatcharts_container_y_desc . "'
                }
        },
        tooltip: {
                headerFormat: '<span style=\"font-size:8px\">{point.key}</span><table>',
                pointFormat: '<tr><td style=\"color:{series.color};padding:0\">{series.name}: </td>' +
                        '<td style=\"padding:0\"><b>{point.y:.1f}" . $laststatcharts_container_point_desc . "</b></td></tr>',
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
                name: '" . $laststatcharts_container_y_desc . "',
                data: " . $new_member_count_json . "

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

// -----------------------------------------------------------------------------
// 以視覺化方式呈現10分鐘數據 -- chart 9 首存会员数
// -----------------------------------------------------------------------------
// 取得每個時段的首存会员数
$first_depositmember_count_json = json_encode($statistics_pivot['first_depositmember_count_array'] ?? []);

$laststatcharts_container_title = $tr['First deposit member'];
$laststatcharts_container_y_desc = $tr['First deposit member'];
$laststatcharts_container_point_desc = $tr['people'];
$laststatcharts_container_9 = "
Highcharts.chart('laststatcharts_container_9', {
        chart: {
                type: 'column'
        },
        title: {
                text: '" . $laststatcharts_container_title . "'
        },
        xAxis: {
                categories: " . $lastdailytime_start_array_json . ",
                crosshair: true
        },
        yAxis: {
                min: 0,
                title: {
                        text: '" . $laststatcharts_container_y_desc . "'
                }
        },
        tooltip: {
                headerFormat: '<span style=\"font-size:8px\">{point.key}</span><table>',
                pointFormat: '<tr><td style=\"color:{series.color};padding:0\">{series.name}: </td>' +
                        '<td style=\"padding:0\"><b>{point.y:.1f}" . $laststatcharts_container_point_desc . "</b></td></tr>',
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
                name: '" . $laststatcharts_container_y_desc . "',
                data: " . $first_depositmember_count_json . "

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

// -----------------------------------------------------------------------------
// 以視覺化方式呈現10分鐘數據 -- chart 10 现金入款
// -----------------------------------------------------------------------------
// 取得每個時段的首存会员数
// $cashdeposit_json = json_encode($statistics_pivot['cashdeposit_array'] ?? []);

// $laststatcharts_container_title = '现金入款';
// $laststatcharts_container_y_desc = '入款金額';
// $laststatcharts_container_point_desc = $config['currency_sign'];
// $laststatcharts_container_10 = "
// Highcharts.chart('laststatcharts_container_10', {
//     chart: {
//         type: 'column'
//     },
//     title: {
//         text: '".$laststatcharts_container_title."'
//     },
//     xAxis: {
//         categories: ".$lastdailytime_start_array_json.",
//         crosshair: true
//     },
//     yAxis: {
//         min: 0,
//         title: {
//             text: '".$laststatcharts_container_y_desc."'
//         }
//     },
//     tooltip: {
//         headerFormat: '<span style=\"font-size:8px\">{point.key}</span><table>',
//         pointFormat: '<tr><td style=\"color:{series.color};padding:0\">{series.name}: </td>' +
//             '<td style=\"padding:0\"><b>{point.y:.1f}".$laststatcharts_container_point_desc."</b></td></tr>',
//         footerFormat: '</table>',
//         shared: true,
//         useHTML: true
//     },
//     plotOptions: {
//         column: {
//             pointPadding: 0.2,
//             borderWidth: 0
//         }
//     },
//     series: [{
//         name: '".$laststatcharts_container_y_desc."',
//         data: ".$cashdeposit_json."

//     }],
//     credits: {
//       enabled: false
//     },
//     exporting: {
//       buttons: [
//         {  enabled: false }
//       ]
//     }
// });
// ";

// -----------------------------------------------------------------------------
// 以視覺化方式呈現10分鐘數據 -- chart 11 现金取款
// -----------------------------------------------------------------------------
// 取得每個時段的现金数据
$cashdeposit_json = json_encode($statistics_pivot['cashdeposit_array'] ?? []);
$cashwithdrawal_json = json_encode(array_abs($statistics_pivot['cashwithdrawal_array'] ?? []));

$laststatcharts_container_title = $tr['cash deposit withdrawal'];
$laststatcharts_container_y_desc = $tr['amount'] . '(' . $config['currency_sign'] . ')';
$laststatcharts_container_point_desc = $config['currency_sign'];
$laststatcharts_container_11 = "
Highcharts.chart('laststatcharts_container_11', {
        chart: {
                type: 'column'
        },
        title: {
                text: '" . $laststatcharts_container_title . "'
        },
        xAxis: {
                categories: " . $lastdailytime_start_array_json . ",
                crosshair: true
        },
        yAxis: {
                min: 0,
                title: {
                        text: '" . $laststatcharts_container_y_desc . "'
                }
        },
        tooltip: {
                headerFormat: '<span style=\"font-size:8px\">{point.key}</span><table>',
                pointFormat: '<tr><td style=\"color:{series.color};padding:0\">{series.name}: </td>' +
                        '<td style=\"padding:0\"><b>{point.y:.1f}" . $laststatcharts_container_point_desc . "</b></td></tr>',
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
                name: '" . $tr['cash deposit'] . "',
                data: " . $cashdeposit_json . "
            },
            {
                name: '" . $tr['cash withdrawal'] . "',
                data: " . $cashwithdrawal_json . "
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

// -----------------------------------------------------------------------------
// 以視覺化方式呈現10分鐘數據 -- chart 12 现金转游戏币
// -----------------------------------------------------------------------------
// 取得每個時段的首存会员数
$cashgtoken_json = json_encode($statistics_pivot['cashgtoken_array'] ?? []);

$laststatcharts_container_title = $tr['Franchise'] . $tr['Turn the gtoken'];
$laststatcharts_container_y_desc = $tr['amount'];
$laststatcharts_container_point_desc = $config['currency_sign'];
$laststatcharts_container_12 = "
Highcharts.chart('laststatcharts_container_12', {
        chart: {
                type: 'column'
        },
        title: {
                text: '" . $laststatcharts_container_title . "'
        },
        xAxis: {
                categories: " . $lastdailytime_start_array_json . ",
                crosshair: true
        },
        yAxis: {
                min: 0,
                title: {
                        text: '" . $laststatcharts_container_y_desc . "'
                }
        },
        tooltip: {
                headerFormat: '<span style=\"font-size:8px\">{point.key}</span><table>',
                pointFormat: '<tr><td style=\"color:{series.color};padding:0\">{series.name}: </td>' +
                        '<td style=\"padding:0\"><b>{point.y:.1f}" . $laststatcharts_container_point_desc . "</b></td></tr>',
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
                name: '" . $laststatcharts_container_y_desc . "',
                data: " . $cashgtoken_json . "

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

// -----------------------------------------------------------------------------
// 以視覺化方式呈現10分鐘數據 -- chart 13 游戏币取款
// -----------------------------------------------------------------------------
// 取得每個時段的游戏币数据
$tokengcash_json = json_encode(array_abs($statistics_pivot['tokengcash_array'] ?? []));
$tokendeposit_json = json_encode($statistics_pivot['tokendeposit_array'] ?? []);

$laststatcharts_container_title = $tr['gtoken deposit withdrawal'];
$laststatcharts_container_y_desc = $tr['amount'] . '(' . $config['currency_sign'] . ')';
$laststatcharts_container_point_desc = $config['currency_sign'];
$laststatcharts_container_13 = "
Highcharts.chart('laststatcharts_container_13', {
        chart: {
                type: 'column'
        },
        title: {
                text: '" . $laststatcharts_container_title . "'
        },
        xAxis: {
                categories: " . $lastdailytime_start_array_json . ",
                crosshair: true
        },
        yAxis: {
                min: 0,
                title: {
                        text: '" . $laststatcharts_container_y_desc . "'
                }
        },
        tooltip: {
                headerFormat: '<span style=\"font-size:8px\">{point.key}</span><table>',
                pointFormat: '<tr><td style=\"color:{series.color};padding:0\">{series.name}: </td>' +
                        '<td style=\"padding:0\"><b>{point.y:.1f}" . $laststatcharts_container_point_desc . "</b></td></tr>',
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
                name: '" . $tr['gotken deposit'] . "',
                data: " . $tokendeposit_json . "
            }, {
                name: '" . $tr['gtoken withdrawal'] . "',
                data: " . $tokengcash_json . "
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

// =============================================================================
//
//
//
// =============================================================================

// -----------------------------------------------------------------------------
// 以視覺化方式呈現 -- chart 2
// -----------------------------------------------------------------------------

// ref: http://api.highcharts.com/highcharts/credits.position
$chart_js = <<<HTML
<script>
$(document).ready ( function(){
    $laststatcharts_container_1
    $laststatcharts_container_2
    $laststatcharts_container_3
    $laststatcharts_container_4
    $laststatcharts_container_5
    // \$laststatcharts_container_6
    // \$laststatcharts_container_7
    $laststatcharts_container_8
    $laststatcharts_container_9
    // \$laststatcharts_container_10
    $laststatcharts_container_11
    $laststatcharts_container_12
    $laststatcharts_container_13
});
</script>
HTML;

// 加入圖的 data 到 head
$extend_head = $extend_head . $chart_js;

// 10 分鐘統計圖, chart1
$container_width = 520;
$container_height = 260;
$chart_laststats_html_1 = '<span id="laststatcharts_container_1" style="min-width: ' . $container_width . 'px; height: ' . $container_height . 'px; margin: 0 auto"></span>';
$chart_laststats_html_2 = '<span id="laststatcharts_container_2" style="min-width: ' . $container_width . 'px; height: ' . $container_height . 'px; margin: 0 auto"></span>';
$chart_laststats_html_3 = '<span id="laststatcharts_container_3" style="min-width: ' . $container_width . 'px; height: ' . $container_height . 'px; margin: 0 auto"></span>';
$chart_laststats_html_4 = '<span id="laststatcharts_container_4" style="min-width: ' . $container_width . 'px; height: ' . $container_height . 'px; margin: 0 auto"></span>';
$chart_laststats_html_5 = '<span id="laststatcharts_container_5" style="min-width: ' . $container_width . 'px; height: ' . $container_height . 'px; margin: 0 auto"></span>';
$chart_laststats_html_6 = '<span id="laststatcharts_container_6" style="min-width: ' . $container_width . 'px; height: ' . $container_height . 'px; margin: 0 auto"></span>';
$chart_laststats_html_7 = '<span id="laststatcharts_container_7" style="min-width: ' . $container_width . 'px; height: ' . $container_height . 'px; margin: 0 auto"></span>';
$chart_laststats_html_8 = '<span id="laststatcharts_container_8" style="min-width: ' . $container_width . 'px; height: ' . $container_height . 'px; margin: 0 auto"></span>';
$chart_laststats_html_9 = '<span id="laststatcharts_container_9" style="min-width: ' . $container_width . 'px; height: ' . $container_height . 'px; margin: 0 auto"></span>';
$chart_laststats_html_10 = '<span id="laststatcharts_container_10" style="min-width: ' . $container_width . 'px; height: ' . $container_height . 'px; margin: 0 auto"></span>';
$chart_laststats_html_11 = '<span id="laststatcharts_container_11" style="min-width: ' . $container_width . 'px; height: ' . $container_height . 'px; margin: 0 auto"></span>';
$chart_laststats_html_12 = '<span id="laststatcharts_container_12" style="min-width: ' . $container_width . 'px; height: ' . $container_height . 'px; margin: 0 auto"></span>';
$chart_laststats_html_13 = '<span id="laststatcharts_container_13" style="min-width: ' . $container_width . 'px; height: ' . $container_height . 'px; margin: 0 auto"></span>';
// ref: http://www.highcharts.com/demo/column-basic

// script
$extend_head = $extend_head . '
<script src="in/highcharts/highcharts.js"></script>
<script src="in/highcharts/modules/exporting.js"></script>
';

$timerange_option = '';
for ($i = 2; $i <= 24; $i = $i + 2) {
  if ($i == $time_range) {
    $timerange_option = $timerange_option . '<option value="' . $i . '" selected>' . $i . '</option>';
  } else {
    $timerange_option = $timerange_option . '<option value="' . $i . '">' . $i . '</option>';
  }
}

$indexbody_content = $indexbody_content . '
        <div id="button_area" class="row col-12 col-md-12">
          <div class="col-12">
            <div style="float: left;"><h5>' . $tr['past'] . '
                <select id="time_range_select" onchange="change_timerange();">
                ' . $timerange_option . '
                </select>
                ' . $tr['Hourly instant information'] . '</h5>
            </div>
            <div style="float: right;">
                <!-- <button class="btn btn-info" onclick="realtime_update(2);">' . $tr['update last 2 hours data'] . '</button> -->
                <!-- <button class="btn btn-info" onclick="realtime_update(12);">' . $tr['update last 12 hours data'] . '</button> -->
                <a href="home_daily.php" class="btn btn-success" >' . $tr['daily report data'] . '</a>
            </div>
          </div>
        </div><br>';

// 即時計算更新資料用 FUNCTION
$extend_head = $extend_head . '
<script type="text/javascript" language="javascript" class="init">
function change_timerange(){
    var time_range = $("#time_range_select").val();
    var updatingcodeurl=\'home.php?a=\'+time_range;
  window.location.href = updatingcodeurl;
}
function realtime_update(time_range){
    if(!time_range){
        var updatingcodeurl=\'statistics_daily_betting_update_action.php?a=update_realtime\';
    }else{
        var updatingcodeurl=\'statistics_daily_betting_update_action.php?a=update_realtime&updatetime_range=\'+time_range;
    }

    blockscreengotoindex();

    $.get(updatingcodeurl,
    function(result){
      alert(result);
      location.reload();
  });
}
</script>
';

// 排板輸出 with bootstrap tab
$indexbody_content .= <<<HTML
    <div id="home" class="row">
        <div class="col-12 col-md-6">$chart_laststats_html_1</div>
        <div class="col-12 col-md-6">$chart_laststats_html_2</div>
        <div class="col-12 col-md-6">$chart_laststats_html_3</div>
        <div class="col-12 col-md-6">$chart_laststats_html_4</div>
        <div class="col-12 col-md-6">$chart_laststats_html_5</div>
        <div class="col-12 col-md-6">$chart_laststats_html_11</div>
        <div class="col-12 col-md-6">$chart_laststats_html_12</div>
        <div class="col-12 col-md-6">$chart_laststats_html_13</div>
        <div class="col-12 col-md-6">$chart_laststats_html_8</div>
        <div class="col-12 col-md-6">$chart_laststats_html_9</div>
        <div class="col-12 col-md-12">$table_summary_html</div>
        <div class="col-12 col-md-12">$table_laststats_html</div>
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
$tmpl['extend_js'] = $extend_js . '<script>console.log("memory used: ' . memory_use_now() . '")</script>';
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
