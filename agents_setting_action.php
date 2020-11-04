<?php
// ----------------------------------------------------------------------------
// Features: 接收并处理 agents_setting.php 的请求，调整反水佣金比例
// File Name:	agents_setting_action.php
// Author:		Webb Lu
// Related: agents_setting.php
// Log:
// ----------------------------------------------------------------------------

session_start();

// 主机及资料库设定
require_once dirname(__FILE__) . "/config.php";
// 支援多国语系
require_once dirname(__FILE__) . "/i18n/language.php";
// 自订函式库
require_once dirname(__FILE__) . "/lib.php";
require_once dirname(__FILE__) . "/lib_agents_setting.php";
require_once dirname(__FILE__) . "/lib_member_tree.php";
require_once dirname(__FILE__) . "/lib_view.php";

// var_dump($_SESSION);

// ----------------------------------------------------------------------------
// 只要 session 活着,就要同步纪录该 user account in redis server db 1
Agent_RegSession2RedisDB();
// ----------------------------------------------------------------------------

// ----------------------------------------------------------------------------
// 检查权限是否合法，允许就会放行。否则中止。
agent_permission();
// ----------------------------------------------------------------------------

// 访问请求纪录
$is_valid_request_frequency = is_valid('request_frequency', null);
$is_valid_request_frequency->state OR die(json_response(400, $is_valid_request_frequency->description));

// 检查请求的 action
if(isset($_GET['a'])):
  $action = filter_var($_GET['a'], FILTER_SANITIZE_STRING);
  // 检查产生的 CSRF token 是否存在 , 错误就停止使用. 定义在 lib.php
  $csrftoken_ret = csrf_action_check();
  if($csrftoken_ret['code'] != 1):
    die(json_response(400, $csrftoken_ret['messages']));
  endif;

elseif($_SERVER['REQUEST_METHOD'] == 'PUT'):
  parse_str(file_get_contents('php://input'), $_PUT);
  foreach($_PUT as $key => $value):
    unset($_PUT[$key]);
    $_PUT[str_replace('amp;', '', $key)] = $value;
  endforeach;
else:
  die(' (x)deny to access.');
endif;
// -----------------------------------------------------------------------------


// ----------------------------------
// 动作为会员登入检查 login_check
// ----------------------------------

// 身分验证通过，Main
file_get_contents('php://input');
if(isset($_PUT)):
  $_PUT['u_id'] = isset($_PUT['u_id']) ? filter_var($_PUT['u_id'], FILTER_VALIDATE_INT) : null;
  $_PUT['value'] = isset($_PUT['value']) ? filter_var($_PUT['value'], FILTER_VALIDATE_INT) : null;

  switch ($_PUT['action']):
    case 'update_dividend':
      // 检查请求
      $check_request = is_valid('dividend', $_PUT);
      if(!$check_request->state):
        die(json_response(400, $check_request));
      endif;
      // $user, $parent_of_user, $_1st_agent_of_user
      extract($check_request->params);
      // 更新对象
      updateMemberFeedbackinfo($user);
      // 锁定对象的上线
      updateMemberFeedbackinfo($parent_of_user);
      // 回传结果
      echo json_response(200, $result = ['state' => true, 'description' => '已成功修改佣金占成比例']);
      break;

    case 'update_preferential':
      // 检查请求
      $check_request = is_valid('preferential', $_PUT);
      if(!$check_request->state):
        die(json_response(400, $check_request));
      endif;
      // $user, $parent_of_user, $_1st_agent_of_user
      extract($check_request->params);
      // 更新对象
      updateMemberFeedbackinfo($user);
      // 锁定对象的上线
      updateMemberFeedbackinfo($parent_of_user);
      // 回传结果
      echo json_response(200, $result = ['state' => true, 'description' => '已成功修改反水占成比例']);
      break;

    case 'update_1st_agent_setting':
      // 检查请求
      $check_request = is_valid('_1st_agent_setting', $_PUT);
      if(!$check_request->state):
        die(json_response(400, $check_request));
      endif;
      // $_1st_agent
      extract($check_request->params);
      // 更新对象，对象即一级代理商
      updateMemberFeedbackinfo($_1st_agent);
      // 回传结果
      $result = [
        'state' => true,
        'description' => '已成功修改一级代理商设定',
        'params' => ['self_feedbackinfo' => $check_request->params['_1st_agent']->feedbackinfo]
      ];
      echo json_response(200, $result);
      break;

    case 'update_1st_agent_group_rebuild_tree':
      // 增加判斷一級代理商是否發展下線的邏輯
      $is_agent_tree_locked = count(MemberTreeNode::getSuccessorList($_PUT['u_id'])) > 1;
      $feedbackinfoHelper = new FeedbackInfoHelper([ 'member_id' => $_PUT['u_id'] ]);
      $genesis_feedbackinfo = $feedbackinfoHelper->getFeedbackInfo();

      $disable_rebuild_tree = $is_agent_tree_locked && !in_array($_SESSION['agent']->account, $su['superuser']);

      if ($disable_rebuild_tree) {
        $result = [
          'state' => true,
          'description' => '一级代理商已有下线，不允许变动代理线基本设置',
          'params' => ['genesis_feedbackinfo' => $feedbackinfoHelper->getFeedbackInfo()]
        ];
        echo json_response(200, $result);
        break;
      }

      if ($_PUT['type_of_setting'] == 'dividend') {
        // 分佣
        $genesis_feedbackinfo->dividend->{'1st_agent'}->last_occupied = $_PUT['cfg_group']['last_occupied'] * 0.01;
        $genesis_feedbackinfo->dividend->{'1st_agent'}->allocable = (100 - $_PUT['cfg_group']['last_occupied']) * 0.01;
        $genesis_feedbackinfo->dividend->{'1st_agent'}->child_occupied->min = $_PUT['cfg_group']['child_occupied_min'] * 0.01;
        $genesis_feedbackinfo->dividend->{'1st_agent'}->child_occupied->max = $_PUT['cfg_group']['child_occupied_max'] * 0.01;
        $genesis_feedbackinfo->dividend->allocable = $genesis_feedbackinfo->dividend->{'1st_agent'}->allocable;
      }

      if ($_PUT['type_of_setting'] == 'preferential') {
        // 反水
        $genesis_feedbackinfo->preferential->{'1st_agent'}->last_occupied = $_PUT['cfg_group']['last_occupied'] * 0.01;
        $genesis_feedbackinfo->preferential->{'1st_agent'}->self_ratio = $_PUT['cfg_group']['self_ratio'] * 0.01;
        $genesis_feedbackinfo->preferential->{'1st_agent'}->allocable = (100 - $_PUT['cfg_group']['last_occupied'] - $_PUT['cfg_group']['self_ratio']) * 0.01;
        $genesis_feedbackinfo->preferential->{'1st_agent'}->child_occupied->min = $_PUT['cfg_group']['child_occupied_min'] * 0.01;
        $genesis_feedbackinfo->preferential->{'1st_agent'}->child_occupied->max = $_PUT['cfg_group']['child_occupied_max'] * 0.01;
        $genesis_feedbackinfo->preferential->allocable = $genesis_feedbackinfo->preferential->{'1st_agent'}->allocable;
      }

      $feedbackinfoHelper->setFeedbackInfo($genesis_feedbackinfo);
      $feedbackinfoHelper->save();
      $feedbackinfoHelper->initTreeFeedbackInfo($_PUT['type_of_setting']);

      echo json_response(200, ['state' => true, 'description' => '已成功修改一级代理商设定', 'params' => ['genesis_feedbackinfo' => $feedbackinfoHelper->getFeedbackInfo()]]);
      break;

    default:
      die(json_response(400, ['state' => false, 'Sorry! The system is unabled to carry your request']));
      break;
  endswitch;
endif;

// 验证是否数据是否合法
function is_valid(string $type, $params) {
  global $config;
  $result = ['state' => false, 'description' => '非法测试!', 'params' => NULL];

  // 多个 feedbackinfo 之间的关系
  // 操作对象 $user
  // 操作对象的上层 $parent_of_user->feedbackinfo 更名为 $parent_of_user
  // 操作对象的一级代理商 更名为 $_1st_agent_of_user

  switch ($type) {
    case 'preferential':
      $userid = $params['u_id'];
      $target_value_percent = $params['value'];

      // 取得目标对象上线至自身与对应的 feedbackinfo
      $ancestors = MemberTreeNode::getPredecessorList($userid);
      $ancestors = array_map(function($ancestor) {
        $ancestor->feedbackinfo = getMemberFeedbackinfo($ancestor);
        return $ancestor;
      }, $ancestors);
      // 取得下层
      $r = runSQLALL_prepared("SELECT * FROM root_member WHERE root_member.parent_id = :account_id;", $values = ['account_id' => $userid]);
      $children = [];
      array_walk($r, function($child) use (&$children) {
        $child->feedbackinfo = getMemberFeedbackinfo($child);
        $children[$child->id] = $child;
      });

      if(empty($ancestors)):
        $result['description'] = '目标会员不存在，可能资料库系统有问题';
        break;
      elseif($ancestors[0]->therole != 'A'):
        $result['description'] = '操作的对象身分错误，该对象并非代理商';
        break;
      elseif($ancestors[0]->status != 1):
        $result['description'] = '操作的对象状态错误，该帐号状态为冻结或停用';
        break;
      else:
        // user, parent_of_user, _1st_agent_of_user
        $user = $ancestors[0];
        $parent_of_user = $ancestors[1];
        $_1st_agent_of_user = array_reverse($ancestors)[1];
      endif;

      $is_locked = count($children) > 0 || has_spread_linkcode($user->account);
      if ($is_locked) {
        $result['description'] = '代理商已开始发展下线，不允许变动代理线基本设置';
        break;
      }

      $min_allocable = float_to_percent($_1st_agent_of_user->feedbackinfo->$type->{'1st_agent'}->child_occupied->min ?? null);
      $max_allocable = float_to_percent($_1st_agent_of_user->feedbackinfo->$type->{'1st_agent'}->child_occupied->max ?? null);
      // 验证目标数据的值是否在限定范围内
      // 该对象被调整时，是否造成下一代受影响
      $children_allocable = get_next_allocable($type, $user->id, false);

      // var_dump($children_allocable);
      $min = $max = [];
      foreach($children_allocable as $childid => $allocable):
        $min[$childid] = !is_null($allocable) && ($target_value_percent - float_to_percent($allocable)) < $min_allocable;
        $max[$childid] = !is_null($allocable) && ($target_value_percent - float_to_percent($allocable)) > $max_allocable;
      endforeach;
      $children_flag = compact('min', 'max');

      if($target_value_percent === false):
        $result['description'] = '不允许将 ' . $user->account . ' 的比例还原成未设定';
        break;
      // 验证上线持有比能否再生成下线，以及是否为合法值
      elseif(float_to_percent($parent_of_user->feedbackinfo->$type->allocable) < $target_value_percent):
        $result['description'] = '上线 '.$parent_of_user->account.' 持有反水占成比例不足，不可分配给下线 ' . $user->account;
        break;
      elseif((float_to_percent($parent_of_user->feedbackinfo->$type->allocable) - $target_value_percent) > $max_allocable):
          $result['description'] = '操作失败 ， ' . $parent_of_user->account . '最多能保留 ' . float_to_percent($_1st_agent_of_user->feedbackinfo->$type->{'1st_agent'}->child_occupied->max) .' %';
          break;
      elseif((float_to_percent($parent_of_user->feedbackinfo->$type->allocable) - $target_value_percent) < $min_allocable):
          $result['description'] = '操作失败， ' . $parent_of_user->account . '最少须保留 ' . float_to_percent($_1st_agent_of_user->feedbackinfo->$type->{'1st_agent'}->child_occupied->min) . ' %';
          break;
      // 该对象被调整时，是否造成下一代受影响
      elseif(is_array($children_flag['min']) && (array_sum($children_flag['min']) > 0)):
        $result['description'] = '操作失败，会导致 ' . $user->account . ' 保留过少，原因为 ' . $user->account . ' 已经设定占成比给会员 '
          . implode(',', array_map(function($id) use ($children) { return $children[$id]->account; }, array_keys(array_filter($children_flag['min']))));
        break;
      elseif(is_array($children_flag['max']) && (array_sum($children_flag['max']) > 0)):
        $result['description'] = '操作失败，会导致 ' . $user->account . ' 保留过多，原因为 ' . $user->account . ' 已经设定占成比给会员 '
          . implode(',', array_map(function($id) use ($children) { return $children[$id]->account; }, array_keys(array_filter($children_flag['max']))));
        break;
      endif;

      $result['description'] = '会员分配比例验证通过';

      $user->feedbackinfo->$type->allocable = ($target_value_percent === false) ? null : $target_value_percent * 0.01;

      $result['state'] = true;
      // 返回必要的资讯，如目标会员的 feedbackinfo 设定值
      $result['params'] = ['user' => $user, 'parent_of_user' => $parent_of_user, '_1st_agnet_of_user' => $_1st_agent_of_user];
      break;

    case 'dividend':
      $userid = $params['u_id'];
      $target_value_percent = $params['value'];

      // 取得目标对象上线至自身与对应的 feedbackinfo
      $ancestors = MemberTreeNode::getPredecessorList($userid);
      $ancestors = array_map(function($ancestor) {
        $ancestor->feedbackinfo = getMemberFeedbackinfo($ancestor);
        return $ancestor;
      }, $ancestors);
      // 取得下层
      $r = runSQLALL_prepared("SELECT * FROM root_member WHERE root_member.parent_id = :account_id;", $values = ['account_id' => $userid]);
      $children = [];
      array_walk($r, function($child) use (&$children) {
        $child->feedbackinfo = getMemberFeedbackinfo($child);
        $children[$child->id] = $child;
      });

      if(empty($ancestors)):
        $result['description'] = '目标会员不存在，可能资料库系统有问题';
        break;
      elseif($ancestors[0]->therole != 'A'):
        $result['description'] = '操作的对象身分错误，该对象并非代理商';
        break;
      elseif($ancestors[0]->status != 1):
        $result['description'] = '操作的对象状态错误，该帐号状态为冻结或停用';
        break;
      else:
        // user, parent_of_user, _1st_agent_of_user
        $user = $ancestors[0];
        $parent_of_user = $ancestors[1];
        $_1st_agent_of_user = array_reverse($ancestors)[1];
      endif;

      $is_locked = count($children) > 0 || has_spread_linkcode($user->account);
      if ($is_locked) {
        $result['description'] = '代理商已开始发展下线，不允许变动代理线基本设置';
        break;
      }

      $min_allocable = float_to_percent($_1st_agent_of_user->feedbackinfo->$type->{'1st_agent'}->child_occupied->min ?? null);
      $max_allocable = float_to_percent($_1st_agent_of_user->feedbackinfo->$type->{'1st_agent'}->child_occupied->max ?? null);
      // 验证目标数据的值是否在限定范围内
      // 该对象被调整时，是否造成下一代受影响
      $children_allocable = get_next_allocable($type, $user->id, false);
      // $children_flag = [];
      $min = $max = [];
      foreach($children_allocable as $childid => $allocable):
        $min[$childid] = !is_null($allocable) && ($target_value_percent - float_to_percent($allocable)) < $min_allocable;
        $max[$childid] = !is_null($allocable) && ($target_value_percent - float_to_percent($allocable)) > $max_allocable;
      endforeach;
      $children_flag = compact('min', 'max');

      if($target_value_percent === false):
        $result['description'] = '不允许将 ' . $user->account . ' 的比例还原成未设定';
        break;
      // 验证上线持有比能否再生成下线，以及是否为合法值
      elseif(float_to_percent($parent_of_user->feedbackinfo->$type->allocable) < $target_value_percent):
        $result['description'] = '上线 '.$parent_of_user->account.' 持有佣金占成比例不足，不可分配给下线 ' . $user->account;
        break;
      elseif((float_to_percent($parent_of_user->feedbackinfo->$type->allocable) - $target_value_percent) > $max_allocable):
          $result['description'] = '操作失败 ， ' . $parent_of_user->account . '最多能保留 ' . float_to_percent($_1st_agent_of_user->feedbackinfo->$type->{'1st_agent'}->child_occupied->max) .' %';
          break;
      elseif((float_to_percent($parent_of_user->feedbackinfo->$type->allocable) - $target_value_percent) < $min_allocable):
          $result['description'] = '操作失败， ' . $parent_of_user->account . '最少须保留 ' . float_to_percent($_1st_agent_of_user->feedbackinfo->$type->{'1st_agent'}->child_occupied->min) . ' %';
          break;
      // 该对象被调整时，是否造成下一代受影响
      elseif( array_sum($children_flag['min']) > 0):
        $result['description'] = '操作失败，会导致 ' . $user->account . ' 保留过少，原因为 ' . $user->account . ' 已经设定占成比给会员 '
          . implode(',', array_map(function($id) use ($children) { return $children[$id]->account; }, array_keys(array_filter($children_flag['min']))));
        break;
      elseif( array_sum($children_flag['max']) > 0):
        $result['description'] = '操作失败，会导致 ' . $user->account . ' 保留过多，原因为 ' . $user->account . ' 已经设定占成比给会员 '
          . implode(',', array_map(function($id) use ($children) { return $children[$id]->account; }, array_keys(array_filter($children_flag['max']))));
        break;
      endif;

      $result['description'] = '会员分配比例验证通过';

      $user->feedbackinfo->$type->allocable = ($target_value_percent === false) ? null : $target_value_percent * 0.01;

      $result['state'] = true;
      // 返回必要的资讯，如目标会员的 feedbackinfo 设定值
      $result['params'] = ['user' => $user, 'parent_of_user' => $parent_of_user, '_1st_agnet_of_user' => $_1st_agent_of_user];
      break;

    case 'request_frequency':
      $_SESSION['security'] = $_SESSION['security'] ?? new stdClass;
      $upperbound = 10;
      $second_interval = 10;
      $now = time();
      $_SESSION['security']->agents_setting_action['time'][] = $now;
      foreach($_SESSION['security']->agents_setting_action['time'] as $key => $accesstime) {
        if(abs($accesstime - $now) < $second_interval) break;
        else unset($_SESSION['security']->agents_setting_action['time'][$key]);
      }
      if(count($_SESSION['security']->agents_setting_action['time']) > $upperbound):
        // $_SESSION['security']->agents_setting_action['description'] = '10 秒内访问次数过多';
        $result['description'] = '10 秒内访问次数过多';
      else:
        // $_SESSION['security']->agents_setting_action['description'] = '合法访问';
        $result['state'] = true;
        $result['description'] = '合法访问';
      endif;
      break;

    case '_1st_agent_setting':
      $valid_types_setting = ['preferential' => '反水分佣', 'dividend' => '佣金分佣'];
      $_1st_agentid = $params['u_id'];
      $target_value_percent = $params['value'];
      $type = $params['type_of_setting'];

      // 验证修改对象的类型
      if(!in_array($params['type_of_setting'], array_keys($valid_types_setting))):
        $result['description'] = '修改的设定类型不正确';
        break;
      endif;

      // 验证请求的目标值是否合法
      if($target_value_percent < 0 || $target_value_percent > 100):
        $result['description'] = '要修改的值不合法!';
        break;
      endif;

      $_1st_agent = runSQLall_prepared($sql = 'SELECT * FROM root_member WHERE id = :id', ['id' => $_1st_agentid])[0] ?? null;
      $_1st_agent->feedbackinfo = getMemberFeedbackinfo($_1st_agent);

      // 取得下层
      $r = runSQLALL_prepared("SELECT * FROM root_member WHERE root_member.parent_id = :account_id;", $values = ['account_id' => $_1st_agentid]);
      $children = [];
      array_walk($r, function($child) use (&$children) {
        $child->feedbackinfo = getMemberFeedbackinfo($child);
        $children[$child->id] = $child;
      });

      if(empty($_1st_agent)):
        $result['description'] = '会员编号 ' . $_1st_agentid . '的资料不存在';
        break;
      elseif($_1st_agent->therole != 'A' || $_1st_agent->parent_id != $config['system_company_id']):
        $result['description'] = '操作的对象身分错误，该对象并非一级代理商';
        break;
      endif;

      if($params['attr'] == 'downward_deposit'):
        // pass
      elseif($params['attr'] == 'allocable'):
        // 判断 pass
      elseif($params['attr'] == 'self_ratio'):
        $min_allocable = float_to_percent($_1st_agent->feedbackinfo->$type->{'1st_agent'}->child_occupied->min ?? null);
        $max_allocable = float_to_percent($_1st_agent->feedbackinfo->$type->{'1st_agent'}->child_occupied->max ?? null);
        $last_occupied = float_to_percent($_1st_agent->feedbackinfo->$type->{'1st_agent'}->last_occupied ?? null);
        // 验证目标数据的值是否在限定范围内
        // 该对象被调整时，是否造成下一代受影响
        $children_allocable = get_next_allocable($type, $_1st_agent->id, false);
        // $children_flag = [];
        foreach($children_allocable as $childid => $allocable):
          $children_flag['min'][$childid] = !is_null($allocable) && (100 - $target_value_percent - $last_occupied) < float_to_percent($allocable);
        endforeach;

        if(($target_value_percent + float_to_percent($_1st_agent->feedbackinfo->{$params['type_of_setting']}->{'1st_agent'}->last_occupied ?? 0)) > 100):
          $result['description'] = $valid_types_setting[$params['type_of_setting']].'：会员反水与直属代理商总和不得超过 100 %！';
          break;
        elseif( array_sum($children_flag['min']) > 0):
          $result['description'] = '操作失败，原因为 ' . $_1st_agent->account . ' 已经设定占成比给会员 '
            . implode(',', array_map(function($id) use ($children) { return $children[$id]->account; }, array_keys(array_filter($children_flag['min']))))
            . ' ，请设定为不超过 ' . max(0, (100 - float_to_percent(max($children_allocable)) - $last_occupied - $min_allocable)) . ' % 的百分比';
          break;
        endif;

      elseif($params['attr'] == 'last_occupied'):
        $min_allocable = float_to_percent($_1st_agent->feedbackinfo->$type->{'1st_agent'}->child_occupied->min ?? null);
        $max_allocable = float_to_percent($_1st_agent->feedbackinfo->$type->{'1st_agent'}->child_occupied->max ?? null);
        $self_ratio = float_to_percent($_1st_agent->feedbackinfo->$type->{'1st_agent'}->self_ratio ?? null);
        // 验证目标数据的值是否在限定范围内
        // 该对象被调整时，是否造成下一代受影响
        $children_allocable = get_next_allocable($type, $_1st_agent->id, false);
        // $children_flag = [];
        foreach($children_allocable as $childid => $allocable):
          $children_flag['min'][$childid] = !is_null($allocable) && (100 - $target_value_percent - $self_ratio - float_to_percent($allocable)) < $min_allocable;
          $children_flag['max'][$childid] = !is_null($allocable) && (100 - $target_value_percent - $self_ratio - float_to_percent($allocable)) > $max_allocable;
        endforeach;

        if(($target_value_percent + float_to_percent($_1st_agent->feedbackinfo->{$params['type_of_setting']}->{'1st_agent'}->self_ratio ?? 0)) > 100):
          $result['description'] = $valid_types_setting[$params['type_of_setting']].'：会员反水与直属代理商总和不得超过 100 %！';
          break;
        elseif( array_sum($children_flag['min']) > 0):
          $result['description'] = '操作失败，原因为 ' . $_1st_agent->account . ' 已经设定占成比给会员 '
            . implode(',', array_map(function($id) use ($children) { return $children[$id]->account; }, array_keys(array_filter($children_flag['min']))))
            . ' ，请设定为不超过 ' . max(0, (100 - float_to_percent(max($children_allocable)) - $self_ratio - $min_allocable)) . ' % 的百分比';
          break;
        endif;
      endif;

      // 验证通过，返回必要资讯
      $ATTR = explode('.', $params['attr']);
      if($params['attr'] == 'allocable')
        if($target_value_percent == 1):
          // 要扣掉会员自身反水
          $self_ratio = $_1st_agent->feedbackinfo->{$params['type_of_setting']}->{'1st_agent'}->self_ratio ?? 0;
          // 要扣掉直属代理商保障
          $last_occupied = $_1st_agent->feedbackinfo->{$params['type_of_setting']}->{'1st_agent'}->last_occupied ?? 0;

          $_1st_agent->feedbackinfo->{$params['type_of_setting']}->{$ATTR[0]} = 1 - $self_ratio - $last_occupied;
        elseif($target_value_percent == 0):
          $_1st_agent->feedbackinfo->{$params['type_of_setting']}->{$ATTR[0]} = 0;
        else:
          $result['description'] = '不合法测试!';
          break;
        endif;
      elseif(count($ATTR) == 1)
        $_1st_agent->feedbackinfo->{$params['type_of_setting']}->{'1st_agent'}->{$ATTR[0]} = ($target_value_percent === false) ? null : $target_value_percent * 0.01;
      elseif(count($ATTR) == 2)
        $_1st_agent->feedbackinfo->{$params['type_of_setting']}->{'1st_agent'}->{$ATTR[0]}->{$ATTR[1]} = ($target_value_percent === false) ? null : $target_value_percent * 0.01;

      if($ATTR[0] == 'self_ratio') $_1st_agent->feedbackinfo->{$params['type_of_setting']}->allocable = 1 - $target_value_percent * 0.01 - $_1st_agent->feedbackinfo->{$params['type_of_setting']}->{'1st_agent'}->last_occupied;
      if($ATTR[0] == 'last_occupied') $_1st_agent->feedbackinfo->{$params['type_of_setting']}->allocable = 1 - $target_value_percent * 0.01 - (isset($_1st_agent->feedbackinfo->{$params['type_of_setting']}->{'1st_agent'}->self_ratio) ? $_1st_agent->feedbackinfo->{$params['type_of_setting']}->{'1st_agent'}->self_ratio : 0);
      if($ATTR[0] == 'downward_deposit') {
        $_1st_agent->feedbackinfo->{$params['type_of_setting']}->{'1st_agent'}->{$ATTR[0]} = filter_var($target_value_percent, FILTER_VALIDATE_BOOLEAN);
        $_1st_agent->feedbackinfo->{$params['type_of_setting']}->allocable = 1 - $_1st_agent->feedbackinfo->{$params['type_of_setting']}->{'1st_agent'}->last_occupied - ($_1st_agent->feedbackinfo->{$params['type_of_setting']}->{'1st_agent'}->self_ratio ?? 0);
      }

      $result['state'] = true;
      $result['params'] = ['_1st_agent' => $_1st_agent];
      break;
    }

  return (object) $result;
}

// -----------------------------------------------------------------------------

class AgentSetting {

}

?>
