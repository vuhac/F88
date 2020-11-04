<?php
// ----------------------------------------------------------------------------
// Features :  后台 --  agents setting LIB
// File Name: lib_agents_setting.php
// Author   : Webb Lu
// Related  :
// Log      :
// ----------------------------------------------------------------------------
// 对应资料表
// 相关的档案
// dependency on
//   config.php
// 功能说明

// 初始化代理商的 feedbackinfo，此數值應提供代理商自定義功能
/**
 * 1. 只有代理商的 feedbackinfo 能夠被自身初始化，此初始化定義應於其他地方允許該代理商修改
 * 2. 子代的 feedbackinfo 只有上層設定時，才會生成
 * 3. 有下線時才有最終取得百分比; 限制每個節點往下分配比例 occupied = allocable - child's allocable
 */
function getMemberFeedbackinfo($memberinfo) {
  global $config;
  $is_first_level_agent = $memberinfo->parent_id == 1 and $memberinfo->therole == 'A';

  $feedbackinfo = json_decode($memberinfo->feedbackinfo, true);
  $feedbackinfo = [
    'preferential' => [
      'allocable' => $feedbackinfo['preferential']['allocable'] ?? null,
      '1st_agent' => $feedbackinfo['preferential']['1st_agent'] ?? null,
    ],
    'dividend' => [
      'allocable' => $feedbackinfo['dividend']['allocable'] ?? null,
      '1st_agent' => $feedbackinfo['dividend']['1st_agent'] ?? null,
    ],
  ];


  // 一級代理商填入預設值
  if ($is_first_level_agent) {
    $feedbackinfo['preferential']['1st_agent'] = $feedbackinfo['preferential']['1st_agent'] ?? [
      'last_occupied' => 0,
      'child_occupied' => ['min' => 0, 'max' => 1],
      'self_ratio' => 0,
      // 代理线向下发放
      'downward_deposit' => false,
      // 系统自动发放给下线
      'auto_deposit' => false,
    ];

    $feedbackinfo['dividend']['1st_agent'] = $feedbackinfo['dividend']['1st_agent'] ?? [
      'last_occupied' => 0,
      'child_occupied' => ['min' => 0, 'max' => 1],
      // 代理线向下发放
      'downward_deposit' => false,
      // 系统自动发放给下线
      'auto_deposit' => false,
    ];

    // 反水与损益
    @$feedbackinfo['preferential']['allocable'] = 1 - $feedbackinfo['preferential']['1st_agent']['self_ratio'] - $feedbackinfo['preferential']['1st_agent']['last_occupied'];
    @$feedbackinfo['dividend']['allocable'] = 1 - $feedbackinfo['dividend']['1st_agent']['last_occupied'];
  } else {
    unset($feedbackinfo['preferential']['1st_agent'], $feedbackinfo['dividend']['1st_agent']);
  }

  return json_decode(json_encode($feedbackinfo));
}

// 更新特定对象的 feedbackinfo
function updateMemberFeedbackinfo($memberinfo) {
  val_to_str($memberinfo->feedbackinfo);

  $update_sql = "UPDATE root_member SET feedbackinfo = :feedbackinfo_json, changetime = 'now()' WHERE id = :member_id";
  runSQLall_prepared($update_sql, ['member_id' => $memberinfo->id, 'feedbackinfo_json' => json_encode($memberinfo->feedbackinfo)]);
}

// 小数转百分比，预设整数百分比，四舍五入
// method 可选 round, floor, ceil;
function float_to_percent($input, $method = '', $fixed = 0) {
  if (!is_numeric($input)):
    $output = null;
  else:
    switch ($method):
  case 'round':
    $output = $method($input * 100, $fixed);
    break;
  case 'ceil':
    $output = $method($input * 100);
    break;
  case 'floor':
    $output = $method($input * 100);
    break;
  default:
    $output = sprintf("%.0f", $input * 100);
    break;
    endswitch;
  endif;

  return $output;
}

// 从下到上一直线的资料中，计算出占用比
function occupied_list(array $ancestors, $type) {
  global $config;

  $_1st_agent = array_values(array_filter($ancestors, function (MemberTreeNode $node) {
    return $node->isFirstLevelAgent();
  }))[0];

  // 公司帐户
  $data_root = ['role' => $config['system_company_account'], 'account' => '公司', 'id' => end($ancestors)->id];
  // 如果有会员占用，目前只有反水有
  $self_ratio = float_to_percent($_1st_agent->feedbackinfo->$type->{'1st_agent'}->self_ratio ?? 0);
  $data_self_member = isset($_1st_agent->feedbackinfo->$type->{'1st_agent'}->self_ratio)
  ? ['role' => 'self_ratio', 'account' => '会员自身', 'id' => '', 'allocable' => null, 'occupied_state' => true, 'source' => ['occupied' => $self_ratio]] : [];
  // 保障末代代理商
  $last_occupied = float_to_percent($_1st_agent->feedbackinfo->$type->{'1st_agent'}->last_occupied);
  // $data_last_agent = isset($_1st_agent->feedbackinfo->$type->{'1st_agent'}->last_occupied) ? ['role' => 'last_occupied', 'account' => '末代保障', 'id' => '', 'allocable' => null, 'occupied' => float_to_percent($_1st_agent->feedbackinfo->$type->{'1st_agent'}->last_occupied)] : [];
  $data = [];
  // 控制显示连结的 flag
  foreach (array_values($ancestors) as $index => $member):
    if ($member->id == $config['system_company_id']) {
      continue;
    }

    $feedbackinfo = $member->feedbackinfo;
    $data[$index]['id'] = $member->id;
    $data[$index]['account'] = $member->account;
    $data[$index]['status'] = $member->status;
    $data[$index]['role'] = $member->account;
    $data[$index]['allocable'] = float_to_percent($feedbackinfo->$type->allocable);
    if ($index > 1):
      $data[$index - 1]['occupied_state'] = !is_null($data[$index]['allocable']);
      $data[$index - 1]['source'] = ['occupied' => ($data[$index - 1]['allocable'] ?? 0) - ($data[$index]['allocable'] ?? 0), 'indemnify' => 0];
    endif;
  endforeach;

  // var_dump($data);
  // 计算到末代时，考虑末代保障的分配，保障末代后多的返回一级代理商

  $from_parent = 100 - $self_ratio - $last_occupied - array_reduce($data, function ($carry, $row) {
    // 计算法一：对直属有利，没分配的部分末代全拿
    // $occupied = isset($row['occupied_state']) && $row['occupied_state'] ? ($row['source']['occupied'] ?? 0) : 0;
    // 计算法二：对代理线最后一个设定的代理商有利，没分配的部分自己全拿
    $occupied = $row['source']['occupied'] ?? 0;
    return $carry += $occupied;
  });
  $from_parent = $from_parent < 0 ? 0 : $from_parent;
  // var_dump($from_parent);
  // 末代保障补助
  $subsidize = $from_parent < $last_occupied ? $last_occupied - $from_parent : 0;
  $last_agent = array_pop($data);
  $last_agent['source'] = ['occupied' => $from_parent, 'indemnify' => $subsidize];
  $last_agent['occupied_state'] = true;
  $last_agent['role'] = '#last#';
  array_push($data, $last_agent);
  // 一級代理商
  $data[1]['role'] = '#first#';
  $data[1]['source']['indemnify'] += $last_occupied - $subsidize;
  array_unshift($data, $data_root, $data_self_member);

  return array_filter($data);
}

// 计算自身实际抽成
function commission_direct($ancestors, $type) {
  $list = occupied_list($ancestors, $type);
  return array_sum(end($list)['source']) ?? 0;
}

/**
 * 取得下层的可分配值
 *
 * @param String $type 设定类型
 * @param Int $member_id 查询对象(本层)的 id
 * @return Array [id => value]
 */
function get_next_allocable($type, $member_id, $is_float = true) {
  $values = ['id' => (int) $member_id];
  $sql = <<<SQL
    WITH "feedbackinfo_records" AS (
      SELECT id, feedbackinfo::json AS records FROM "root_member" WHERE parent_id = :id
    )
    SELECT id, (records->'$type'->>'allocable')::numeric AS allocable FROM "feedbackinfo_records"
SQL;
  foreach (runSQLall_prepared($sql, $values) as $value) {
    $result[$value->id] = $is_float ? (float) $value->allocable : $value->allocable;
  }
  return $result ?? [];
}

// 负责输出 json response
function json_response($code = 200, $message = null) {
  header_remove();
  http_response_code($code);
  // 快取控制
  // header('"Cache-Control: no-transform,public,max-age=300,s-maxage=900');
  header('Content-Type: application/json');
  $status = [
    200 => '200 OK',
    400 => '400 Bad Request',
    500 => '500 Internal Server Error',
  ];

  header('Status: ' . $status[$code]);
  return json_encode([
    'status' => $code < 300, // success or not?
    'message' => $message,
  ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
}

/**
 * 查詢特定會員是否已有推廣連結
 * @param string $member_account
 *
 * @return bool
 */
function has_spread_linkcode($member_account) {
  $sql = "SELECT count(*) FROM root_spreadlink WHERE account = :account AND status = 1";
  $result = runSQLall_prepared($sql, ['account' => $member_account], '', 0, 'r');
  return $result[0]->count > 0;
}

function val_to_str(&$val) {
  if (is_numeric($val)) {
    $val = strval($val);
  } elseif (is_array($val) || is_object($val)) {
    foreach ($val as &$value) {
      val_to_str($value);
    }
  }
}

class FeedbackInfoHelper {
  public $memberinfo = null;
  public $parentinfo = null;
  public $genesisinfo = null;
  public $dividend;
  public $preferential;
  // tree
  public $member_list = [];
  public $availableTypes = ['dividend', 'preferential'];

  public function __construct($data) {
    include_once __DIR__ . '/lib_member_tree.php';
    $this->init($data['member_id']);
  }

  public function getAllocable($type) {
    return $this->memberinfo->feedbackinfo->{$type}->allocable;
  }

  public function setAllocable($type, $value) {
    $this->memberinfo->feedbackinfo->{$type}->allocable = $value;
  }

  private function init($member_id) {
    $this->member_list = MemberTreeNode::getMemberList();
    MemberTreeNode::buildMemberTree(
      $this->member_list[1],
      $this->member_list,
      function ($member) {
        $member->feedbackinfo = getMemberFeedbackinfo($member);
      }
    );
    // 自身
    $this->memberinfo = $this->member_list[$member_id];
    // 一级代理商
    $this->genesisinfo = $this->memberinfo->isFirstLevelAgent() ? $this->memberinfo : $this->member_list[$this->memberinfo->predecessor_id_list[1]];
    // 上層
    $this->parentinfo = $this->memberinfo->parent;
  }

  /**
   * 根據一級代理商的設定，以及上層的 allocable，決定自己 feedbackInfo
   */
  public function initFeedbackInfo($targets = ['dividend', 'preferential']) {
    $types = array_intersect(['dividend', 'preferential'], $targets);
    // 一级代理商
    if ($this->memberinfo->isFirstLevelAgent()) {
      return;
    }

    foreach ($types as $type) {
      // 我可分配 = 上線可分配 - 每代最多保留(需 > 0, <= 0 的情況預設為 0)
      $selfAllocable = max(0, $this->parentinfo->feedbackinfo->{$type}->allocable - $this->genesisinfo->feedbackinfo->{$type}->{'1st_agent'}->child_occupied->max);
      $this->memberinfo->feedbackinfo->{$type}->allocable = $selfAllocable;
    }
  }

  public function getFeedbackInfo() {
    return $this->memberinfo->feedbackinfo;
  }

  public function setFeedbackInfo($feedbackinfo) {
    $this->memberinfo->feedbackinfo = $feedbackinfo;
  }

  public function save() {
    val_to_str($this->memberinfo->feedbackinfo);

    $sql = "UPDATE root_member SET feedbackinfo = :feedbackinfo, changetime = 'now()' WHERE id = :id";
    return runSQLall_prepared(
      $sql, [
        'feedbackinfo' => json_encode($this->memberinfo->feedbackinfo),
        'id' => $this->memberinfo->id,
      ]
    );
  }

  /**
   * 對整個 Tree 的操作
   * 只有 $memberinfo 是一級代理商時才能呼叫此方法
   */
  public function initTreeFeedbackInfo($type = null) {
    if (!$this->memberinfo->isFirstLevelAgent()) {
      return false;
    }

    $types = ['dividend', 'preferential'];
    if ($type) {
      $types = array_filter($types, function ($value) use ($type) {return $value == $type;});
    }

    $callback = function ($member) use ($types) {
      foreach ($types as $type) {
        if ($member->isFirstLevelAgent()) {
          break;
        }

        // 我可分配 = 上線可分配 - 每代最多保留(需 > 0, <= 0 的情況預設為 0)
        $selfAllocable = max(0, $this->member_list[$member->parent_id]->feedbackinfo->{$type}->allocable - $this->genesisinfo->feedbackinfo->{$type}->{'1st_agent'}->child_occupied->max);
        $member->feedbackinfo->{$type}->allocable = $selfAllocable;
        val_to_str($member->feedbackinfo);

        $sql = "UPDATE root_member SET feedbackinfo = :feedbackinfo, changetime = 'now()' WHERE id = :id";
        runSQLall_prepared(
          $sql, [
            'feedbackinfo' => json_encode($member->feedbackinfo),
            'id' => $member->id,
          ]
        );
      }
    };

    MemberTreeNode::visitTopDown($this->genesisinfo, $callback);
  }

  /**
   * 取得預算比例，根據情境有兩種結果 [ 自身是 endnode: 下線(玩家)投注, 自身不是 endnode: 下線(代理商) node 投注 ]
   *
   * @param string $type dividend | preferential
   * @param float $assignValue 介於 0 到 1 之間的兩位小數
   */
  public function getPreAssignation($type, $assigningValue) {
    $allocable = $this->getAllocable($type);

    $parentOfPlayer = max($allocable, $this->genesisinfo->feedbackinfo->{$type}->{'1st_agent'}->last_occupied);

    $occupied = (floor($allocable * 100) - floor($assigningValue * 100)) * 0.01;
    $ancestorOfPlayer = max(0, $occupied);

    return compact('parentOfPlayer', 'ancestorOfPlayer');
  }

  /**
   * 取得新增下線時，可以分配的範圍；這個值根據一級代理商的每層保留計算出來
   */
  public function getNewChildAllocationRange($type) {
    $allocable = $this->getAllocable($type);
    $min = max(0, $allocable - $this->genesisinfo->feedbackinfo->{$type}->{'1st_agent'}->child_occupied->max);
    $max = max(0, $allocable - $this->genesisinfo->feedbackinfo->{$type}->{'1st_agent'}->child_occupied->min);
    return compact('min', 'max');
  }

  /**
   * 檢查要設定的下線值是否合法
   */
  public function isChildSettingValid($child_feedbackinfo) {
    $state = false;
    $message = '';

    try {
      foreach ($this->availableTypes as $type) {
        if (!isset($child_feedbackinfo->{$type}->allocable)) {
          throw new \Exception("未設定 $type 的可分配比");
        }

        if ($child_feedbackinfo->{$type}->allocable < $this->getNewChildAllocationRange($type)['min']) {
          throw new \Exception("$type 的可分配比例不滿足最小值({$this->getNewChildAllocationRange($type)['min']})");
        }

        if ($child_feedbackinfo->{$type}->allocable > $this->getNewChildAllocationRange($type)['max']) {
          throw new \Exception("$type 的可分配比例超出最大值({$this->getNewChildAllocationRange($type)['max']})");
        }
      }
      $state = true;
      $message = 'setting is valid';
    } catch (\Exception $e) {
      $message = $e->getMessage();
    }

    return compact('state', 'message');
  }

  /**
   * 取得可分配比例(一級代理商->對象自身)
   *
   * @param integer $member_id
   * @return array [dividend, preferential]
   */
  public function getPredecessorAllocableList(int $member_id = null) {
    is_null($member_id) and $member_id = $this->memberinfo->id;
    $predecessor_id_list = $this->memberinfo->predecessor_id_list;
    array_push($predecessor_id_list, $member_id);

    foreach ($predecessor_id_list as $nodeid) {
      $nodeinfo = $this->member_list[$nodeid];
      if ($nodeinfo->isRoot()) {
        continue;
      }

      $dividend[$nodeid] = $nodeinfo->feedbackinfo->dividend->allocable;
      $preferential[$nodeid] = $nodeinfo->feedbackinfo->preferential->allocable;
    }

    return compact('dividend', 'preferential');
  }

  public function getPredecessorOccupiedList(int $member_id = null) {
    $allocable_lists = $this->getPredecessorAllocableList($member_id);

    foreach ($allocable_lists as $type => $allocable_list) {
      $occupied_lists[$type] = [];
      foreach ($allocable_list as $nodeid => $allocable) {
        $occupied_lists[$type][$nodeid] = $allocable - next($allocable_list);
      }
    }
    return $occupied_lists;
  }

  public function getChildren(int $member_id = null)
  {
    is_null($member_id) and $member_id = $this->memberinfo->id;
    return $this->member_list[$member_id]->children;
  }

  public function getFirstLevelAgent()
  {
    return $this->genesisinfo;
  }

  public function getPredecessorList(int $member_id = null)
  {
    is_null($member_id) and $member_id = $this->memberinfo->id;
    $predecessor_id_list = $this->memberinfo->predecessor_id_list;
    array_push($predecessor_id_list, $member_id);

    $predecessor = [];

    foreach ($predecessor_id_list as $nodeid) {
      $predecessor[$nodeid] = $this->member_list[$nodeid];
    }

    return $predecessor;
  }
}
?>
