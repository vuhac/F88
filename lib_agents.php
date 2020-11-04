<?php
// ----------------------------------------------------------------------------
// Features :	后台 --  agents setting LIB
// File Name: lib_agents_setting.php
// Author   : Webb Lu
// Related  :
// Log      : 未完成，原預定取代 lib_agents_setting 的代理商分佣設定計算，進版控備考
// ----------------------------------------------------------------------------
// 对应资料表
// 相关的档案
// dependency on
//   config.php
//   lib_member_tree.php
// 功能说明

// require_once __DIR__ . '/../config.php';
// require_once __DIR__ . '/../lib_member_tree.php';

// $agent_id = 1037;
// $member_list = MemberTreeNode::getMemberList($agent_id);
// $tree_root = $member_list[$agent_id];

// MemberTreeNode::buildMemberTree($tree_root, $member_list, function (&$member) {
//   $member->node_data = new MemberFeedbackInfo($member);
// });

// $test = new AgentSettingHelper('dividend');
// $test->setMemberTree($member_list, 1037);

// var_dump(json_encode((array)$member_list[1037]));
// // var_dump($test->getOccupiedById(1038));

// // var_dump($test->isNewAllocableValid(1038));

// $feedbackinfo = <<<JS
// {
//   "dividend": {
//     "locked": false,
//     "1st_agent": {
//       "auto_deposit": false,
//       "last_occupied": null,
//       "child_occupied": {
//         "max": null,
//         "min": null
//       },
//       "downward_deposit": false
//     },
//     "allocable": null
//   },
//   "preferential": {
//     "locked": false,
//     "1st_agent": {
//       "self_ratio": null,
//       "auto_deposit": false,
//       "last_occupied": null,
//       "child_occupied": {
//         "max": null,
//         "min": null
//       },
//       "downward_deposit": false
//     },
//     "allocable": null
//   }
// }
// JS;

// null 是未設定, 0 是設定後的結果

// 輸入目前的會員 id
// 1. 取得整顆代理線設定樹
// 2. 取得多條代理線結果列表
// 計算機
class AgentSettingHelper {
  public $type;
  public $member_list;
  public $member_tree_root;
  public $predecessor_list;
  public $buffer;

  public function __construct($type) {
    // $this->getMemberTree($member_id);
    // 反水 or 佣金
    $this->type = $type;
  }

  public function setMemberTree(&$member_list, $root_id = null) {
    $this->member_list = $member_list;

    if (empty($root_id)) {
      $this->member_tree_root = $member_list[1];
    } else {
      $this->member_tree_root = $member_list[$root_id];
    }
  }

  public function isNewAllocableValid($target_id) {
    // allocable 條件
    // 必須上層擁有 - 自己獲得的，必須在一級代理商的 child_occupied min ~ max 之間
    return $this->getOccupiedById($target_id) >= $this->member_tree_root->getFirstAgentSetting($this->type)->child_occupied->min
    && $this->getOccupiedById($target_id) <= $this->member_tree_root->getFirstAgentSetting($this->type)->child_occupied->max;
  }

  public function getOccupiedById($target_id) {

    var_dump($this->member_list[$target_id]->parent);
    var_dump($this->member_list[$target_id]->parent->node_data->getAllocable($this->type));
    var_dump($this->member_list[$target_id]->node_data->getAllocable($this->type));

    return $this->member_list[$target_id]->parent->node_data->getAllocable($this->type)
     - $this->member_list[$target_id]->node_data->getAllocable($this->type);
  }

  // 取得從一級代理商開始的整棵樹
  public function getMemeberSetting(): array
  {
    // test
    // $this->buffer = $allocable_tree = [];
    // var_dump($this->member_tree_root);

    // MemberTreeNode::visitBottomUp($this->member_tree_root, function($member) use (&$allocable_tree) {
    //   $allocable_tree[$member->id] = (object) [
    //     'id' => $member->id,
    //     'account' => $member->account,
    //     'parent_id' => $member->parent_id,
    //     'allocable' => $member->getAllocable(),
    //   ];
    // });

    // $this->buffer = $allocable_tree;
    // $node = array_slice($allocable_tree, 0, 1)[0];
    // MemberTreeNode::buildMemberTree($allocable_tree[1037], $this->buffer);

    // var_dump(json_encode($this->buffer[1037], 0, 1024));

    return $this->member_list;
  }

  // 取得特定 node 的代理線（設定結果）
  public function getMemberSettingResult($member_id) {

  }

  // 計算特定 node 的代理線設定結果是否合理
  // 如果代理線結果有任一個節點是 false, 儲存就失敗
  public function checkMemberSettingResult() {

  }

  // public function toJson($encode_options=0)
  // {
  //   return json_encode($this->member_list, $encode_options, 512);
  // }
}

class MemberFeedbackInfo {
  // public $id;
  // public $account;
  // public $therole;
  // public $parent_id;
  // public $status;
  public $feedbackinfo;

  function __construct($member) {
    foreach ($member as $property => $value) {
      if (property_exists($this, $property)) {
        $this->{$property} = $value;
      }

      if ($property == 'feedbackinfo') {
        $this->{$property} = json_decode($value);
      }
    }
  }

  public function getAllocable($type): string {
    $allocable_list = [
      'dividend' => isset($this->feedbackinfo) ? (string) $this->feedbackinfo->{'dividend'}->allocable : '',
      'preferential' => isset($this->feedbackinfo) ? (string) $this->feedbackinfo->{'preferential'}->allocable : '',
    ];

    return $allocable_list[$type];
  }

  public function getFirstAgentSetting($type): string {
    $setting_list = [
      'dividend' => isset($this->feedbackinfo) ? (string) $this->feedbackinfo->{'dividend'}->{'1st_agent'} : '',
      'preferential' => isset($this->feedbackinfo) ? (string) $this->feedbackinfo->{'preferential'}->{'1st_agent'} : '',
    ];

    return $setting_list[$type];
  }

  public function isInit(): boolean {
    return is_null($this->feedbackinfo);
  }
}
