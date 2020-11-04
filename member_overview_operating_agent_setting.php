<?php
// ----------------------------------------------------------------------------
// Features:    GCASH後台 -- 人工取款GCASH
// File Name:    member_withdrawalgcash.php
// Author:        Barkley
// Related:
// Permission: 只有站長或是客服才可以執行
// Log:
// 2016.11.20 v0.2
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

// ----------------------------------------------------------------------------
// 只要 session 活著,就要同步紀錄該 agent user account in redis server db 1
Agent_RegSession2RedisDB();
// ----------------------------------------------------------------------------

// ----------------------------------------------------------------------------
// 檢查權限是否合法，允許就會放行。否則中止。
agent_permission();
// ----------------------------------------------------------------------------

// ----------------------------------------------------------------------------
// Main
// ----------------------------------------------------------------------------
// 初始化變數
// 功能標題，放在標題列及meta
$function_title         = '';
// 擴充 head 內的 css or js
$extend_head                = '';
// 放在結尾的 js
$extend_js                    = '';
// body 內的主要內容
$panelbody_content = '';
$indexbody_content = '';
// ----------------------------------------------------------------------------
// 目前所在位置 - 配合選單位置讓使用者可以知道目前所在位置
$menu_breadcrumbs = '
<ol class="breadcrumb">
  <li><a href="home.php">' . $tr['Home'] . '</a></li>
  <li><a href="member_overview.php">' . $tr['member overview'] . '</a></li>
  <li class="active">功能操作</li>
</ol>';
// ----------------------------------------------------------------------------

// ----------------------------------------------------------------------------
// 此程式功能说明：
// 以使用者帐号为主轴 , 管理者可以操作各种动作。
// ----------------------------------------------------------------------------

// 检视特定会员 id 的设定
isset($_GET['a']) OR die('NO ID ERROR!!');
$account_id = isset($_GET['a']) ? filter_var($_GET['a'], FILTER_SANITIZE_NUMBER_INT) : null;
is_numeric($account_id) OR die($logger = $tr['The user ID is error']);

// get use member data
$sql = "SELECT * FROM root_member WHERE root_member.id = :account_id;";
$r = runSQLALL_prepared($sql, $values = ['account_id' => $account_id]);
// 正常只能有一个帐号, 并取得正常的资料。
count($r) == 1 OR die($debug_msg = '资料库系统有问题，请联络开发人员处理。');
$user = $r[0];
$user->feedbackinfo = getMemberFeedbackinfo($user);
// echo '<pre>', var_dump($user->feedbackinfo), '</pre>';  exit();

// 取得 user 直属下线
$sql = "SELECT *, to_char((enrollmentdate AT TIME ZONE '$tzonename'),'YYYY-MM-DD HH24:MI:SS') as enrollmentdate FROM root_member WHERE root_member.parent_id = :account_id;";
$r = runSQLALL_prepared($sql, $values = ['account_id' => $account_id]);
$childs = array_map(function($child) {
  // 只允许初始化自身与下线
  // $init = $child->therole == 'A' && !isset($child->feedbackinfo);
  $child->feedbackinfo = getMemberFeedbackinfo($child);
  return $child;
 }, $r);
//  echo '<pre>', var_dump($childs), '</pre>';  exit();

// 取得祖先与一级代理商
// $ancestors = getAncestors($user->id);
$ancestors = MemberTreeNode::getPredecessorList($user->id);
$ancestors = array_map(function($ancestor) {
  $ancestor->feedbackinfo = getMemberFeedbackinfo($ancestor);
  return $ancestor;
}, $ancestors);
$_1st_agent = array_reverse($ancestors)[1] ?? null;
// echo '<pre>', var_dump($_1st_agent), '</pre>';  exit();

// MemberTreeNode::getSuccessorListByDate();
// 未發展下線： 無 childs 且無邀請碼
$is_agent_tree_locked = count(MemberTreeNode::getSuccessorList($_1st_agent->id)) > 1 || has_spread_linkcode($_1st_agent->account);

// 生成 select 中的 options；特定数值范围
$options = function($selected=null, $min=0, $max=100) {
  $option_null = '<option value>未设定</option>';
  $options_Arr = array_map(function($value) use ($selected) {
    return !is_null($selected) && $value == $selected ? "<option selected=\"selected\" value=\"$value\">$value %</option>" : "<option value=\"$value\">$value %</option>";
  }, range($min, $max));
  return $option_null . implode('', $options_Arr);
}; // end $options

$f_to_100 = 'float_to_percent';

// 身份用途是来展示
//管理员
$theroleicon['R'] = '<span class="glyphicon glyphicon-king" aria-hidden="true"></span>' . $tr['Identity Management Title'] . '';
//代理商
$theroleicon['A'] = '<span class="glyphicon glyphicon-knight" aria-hidden="true"></span>' . $tr['Identity Agent Title'] . '';
//会员
$theroleicon['M'] = '<span class="glyphicon glyphicon-user" aria-hidden="true"></span>' . $tr['Identity Member Title'] . '';
//试用帐号
$theroleicon['T'] = '<span class="glyphicon glyphicon-sunglasses" aria-hidden="true"></span>' . $tr['Identity Trial Account Title'] . '';

// 使用者帐号 身分 上层代理商编号
$listuser_title = '<p>'.$tr['User account'].'<button class="btn btn-link" type="button">' . $user->account . '</button>
'. $tr['identity'] . '&nbsp;
<button class="btn btn-link" type="button">' . $theroleicon[$user->therole] . '</button>
' . $tr['upper agent number'] . '&nbsp;
<button class="btn btn-link upper_agent_btn" type="button" value="' . $user->parent_id . '">' . $user->parent_id . '</button>
</p>
';

$panelbody_content = <<<HTML
<div class="row">
  <div class="col-12 col-md-12">
  $listuser_title
  </div>
</div>
HTML;

$checked = function($user, $type, $attr, $option) {
  $result = '';

  switch ($attr):
    case 'allocable':
      // 要扣掉会员自身反水
      $self_ratio = $user->feedbackinfo->$type->{'1st_agent'}->self_ratio ?? 0;
      // 要扣掉末代保障
      $last_occupied = $user->feedbackinfo->$type->{'1st_agent'}->last_occupied ?? 0;
      $result = $user->feedbackinfo->$type->allocable == max($option - $self_ratio - $last_occupied, 0) ? 'checked' : '';
      break;
    case 'downward_deposit':
      $result = $user->feedbackinfo->$type->{'1st_agent'}->downward_deposit == $option ? 'checked' : '';
    break;
  endswitch;

  return $result;
}; // end $checked

$disable_rebuild_tree = $is_agent_tree_locked && !in_array($_SESSION['agent']->account, $su['superuser']);

$_1st_preferential_modifier_html = $disable_rebuild_tree ? '' : <<<HTML
<div class="form-group row cfg_group_preferential">
    <label class="col-sm-2 col-form-label py-3">修改设置</label>
    <div class="col-sm-7 py-3">
        <div class="input-group form-inline">
            <select class="form-control input-sm" data-action="set_1st_agent_config" data-type="preferential" data-agentid="{$_1st_agent->id}" data-description="预定义的一级代理商设置组">
            </select>
            <button class="ml-2 btn btn-success btn-sm" id="confirm_preferential_config" data-type="preferential" data-agentid="{$_1st_agent->id}" disabled>确认修改</button>
        </div>
        <div class="alert alert-info mt-3 p-2" role="alert" id="preferential_cfg_hint"><small>提供默认的百分比设置，让您不再烦恼！</small></div>
        <div class="form-group row">
			<div class="col-4 font-weight-bold">{$tr['member self reward']}</div>
			<div class="col-8">
                <select class="form-control" data-action="self_ratio" data-type="preferential" data-agentid="{$_1st_agent->id}" data-description="会员自身反水">
                    {$options($f_to_100($_1st_agent->feedbackinfo->preferential->{'1st_agent'}->self_ratio))}
                </select>
			</div>
        </div>
        <div class="form-group row">
			<div class="col-4 pt-1 font-weight-bold">{$tr['each agent keep']}</div>
			<div class="col-8 d-flex">
                <select class="form-control" data-action="child_occupied.min" data-type="preferential" data-agentid="{$_1st_agent->id}" data-description="代理商最少保留" title="代理商最少保留" data-toggle="tooltip">
                    {$options($f_to_100($_1st_agent->feedbackinfo->preferential->{'1st_agent'}->child_occupied->min))}
                </select>
				<span class="input-group-append" style="margin: auto 0.5em">~</span>
				<select class="form-control" data-action="child_occupied.max" data-type="preferential" data-agentid="{$_1st_agent->id}" data-description="代理商最多保留" title="代理商最多保留" data-toggle="tooltip">
                    {$options($f_to_100($_1st_agent->feedbackinfo->preferential->{'1st_agent'}->child_occupied->max))}
                </select>
			</div>
        </div>
        <div class="form-group row">
			<div class="col-4 pt-1 font-weight-bold">{$tr['Guarantee of direct agent']}</div>
			<div class="col-8">
                <select class="form-control" data-action="last_occupied" data-type="preferential" data-agentid="{$_1st_agent->id}" data-description="末代保障">
                    {$options($f_to_100($_1st_agent->feedbackinfo->preferential->{'1st_agent'}->last_occupied))}
                </select>
			</div>
		</div>
    </div>
</div>
HTML;

$_1st_dividend_modifier_html = $disable_rebuild_tree ? '' : <<<HTML
<div class="form-group row cfg_group_dividend">
    <label class="col-sm-2 col-form-label py-3">修改设置</label>
    <div class="col-sm-7 py-3">
        <div class="input-group form-inline">
            <select class="form-control input-sm" data-action="set_1st_agent_config" data-type="dividend" data-agentid="{$_1st_agent->id}" data-description="预定义的一级代理商设置组">
            </select>
            <button class="ml-2 btn btn-success btn-sm" id="confirm_dividend_config" data-type="dividend" data-agentid="{$_1st_agent->id}">确认修改</button>
        </div>
        <div class="alert alert-info mt-2" role="alert" id="dividend_cfg_hint"><small>提供默认的百分比设置，让您不再烦恼！</small></div>
        <div class="form-group row">
            <div class="col-4 font-weight-bold"> {$tr['each agent keep']} </div>
            <div class="col-8 d-flex">
                <select class="form-control" data-action="child_occupied.min" data-type="dividend" data-agentid="{$_1st_agent->id}" data-description="代理商最少保留" title="代理商最少保留" data-toggle="tooltip">
                    {$options($f_to_100($_1st_agent->feedbackinfo->dividend->{'1st_agent'}->child_occupied->min))}
                </select>
                <span class="input-group-append" style="margin: auto 0.5em">~</span>
                <select class="form-control" data-action="child_occupied.max" data-type="dividend" data-agentid="{$_1st_agent->id}" data-description="代理商最多保留" title="代理商最多保留" data-toggle="tooltip">
                    {$options($f_to_100($_1st_agent->feedbackinfo->dividend->{'1st_agent'}->child_occupied->max))}
                </select>
            </div>
        </div>
        <div class="form-group row">
            <div class="col-4 pt-1 font-weight-bold"> {$tr['Guarantee of direct agent']} </div>
            <div class="col-8 input-group">
                <select class="form-control" data-action="last_occupied" data-type="dividend" data-agentid="{$_1st_agent->id}" data-description="末代保障">
                    {$options($f_to_100($_1st_agent->feedbackinfo->dividend->{'1st_agent'}->last_occupied))}
                </select>
            </div>
        </div>
    </div>
</div>
HTML;

if ($user->therole != 'A'):
  $_1st_agent_html_V2 = '查询的帐号并非代理商，无代理商佣金可设定。';
  $agentadmin_html = '';

else:
  $_1st_agent_html_V2 = <<<HTML
        <div class="form-group row mb-0 d-flex telescopic_btn" id="reward_link">
            <label class="col-sm-12 col-form-label py-3 bg-light border d-flex lign-items-center">
              <h6 class="d-flex font-weight-bold mb-0">
                <span class="glyphicon glyphicon-cog mr-2"></span>
                反水占成设定<span class="text-secondary font-weight-normal ml-2">( {$tr['follow as general agent']} {$_1st_agent->account} {$tr['setting']} )</span>
              </h6>	
              <i class="fas fa-angle-up ml-auto"></i>		
            </label>	
        </div>
        <div class="row border border-top-0">
            <div class="col-11 mx-auto py-3" data-text="反水占成设定">
      
                <div class="form-group row">
                    <label class="col-sm-2 col-form-label py-2">代理线向下发放</label>
                    <div class="col-sm-10 py-3 _1st_agent_setting">
                        <label>
                          <input type="radio" value="1" name="p_downward_deposit" data-action="downward_deposit" data-type="preferential" data-agentid="{$_1st_agent->id}" data-description="代理线持有反水占成" data-value="是" {$checked($_1st_agent, 'preferential', 'downward_deposit', 1)}>
                          {$tr['y']}
                        </label>
                        <label>
                          <input type="radio" value="0" name="p_downward_deposit" data-action="downward_deposit" data-type="preferential" data-agentid="{$_1st_agent->id}" data-description="代理线持有反水占成" data-value="否" {$checked($_1st_agent, 'preferential', 'downward_deposit', 0)}>
                          {$tr['n']}
                        </label>
                        <p class="mb-0 text-secondary">此栏位设定为否时，反水保留在一级代理商身上。</p>
                    </div>
                </div>
                <!-- 反水修改區塊 start -->
                {$_1st_preferential_modifier_html}
                <!-- 反水修改區塊 end -->
                <div class="form-group row mb-0">
                    <label class="col-sm-2 col-form-label py-2">当前设置</label>
                    <div class="col-sm-10 py-2 ">
                        <p class="alert alert-info mb-2 text-secondary" role="alert">当前已套用设置值；对象代理商发展下线后，不允许修改；后台高级帐号可重设整条代理线</p>
                        <p class="mb-2"><strong> {$tr['member self reward']}</strong> {$f_to_100($_1st_agent->feedbackinfo->preferential->{'1st_agent'}->self_ratio)} %</p>
                        <p class="mb-2"><strong> {$tr['each agent keep']} </strong>
                            {$f_to_100($_1st_agent->feedbackinfo->preferential->{'1st_agent'}->child_occupied->min)} %
                            ~
                            {$f_to_100($_1st_agent->feedbackinfo->preferential->{'1st_agent'}->child_occupied->max)} %
                        </p>
                        <p class="mb-2"><strong> {$tr['Guarantee of direct agent']} </strong>{$f_to_100($_1st_agent->feedbackinfo->preferential->{'1st_agent'}->last_occupied)} %</p>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="form-group row mt-4 mb-0 d-flex telescopic_btn">
          <label class="col-sm-12 col-form-label py-3 bg-light border d-flex">
            <h6 class="d-flex font-weight-bold mb-0">
              <span class="glyphicon glyphicon-cog mr-2"></span>
              {$tr['proportioned of commission']}<span class="text-secondary font-weight-normal ml-2">( {$tr['follow as general agent']}  {$_1st_agent->account} {$tr['setting']} )</span>
            </h6>	
            <i class="fas fa-angle-up ml-auto"></i>
          </label>
        </div>
        <div class="row border border-top-0">
          <div class="col-11 mx-auto py-3" data-text="佣金占成比例">
            <div class="form-group row">
              <label class="col-sm-2 col-form-label py-2">代理线向下发放</label>
              <div class="col-sm-10 py-3 _1st_agent_setting">
                  <label>
                    <input type="radio" value="1" name="p_downward_deposit" data-action="downward_deposit" data-type="preferential" data-agentid="{$_1st_agent->id}" data-description="代理线持有反水占成" data-value="是" {$checked($_1st_agent, 'preferential', 'downward_deposit', 1)}>
                    {$tr['y']}
                  </label>
                  <label>
                    <input type="radio" value="0" name="p_downward_deposit" data-action="downward_deposit" data-type="preferential" data-agentid="{$_1st_agent->id}" data-description="代理线持有反水占成" data-value="否" {$checked($_1st_agent, 'preferential', 'downward_deposit', 0)}>
                    {$tr['n']}
                  </label>
                  <p class="mb-0 text-secondary">此栏位设定为否时，佣金保留在一级代理商身上。</p>
              </div>
            </div>
            <!-- 反水修改區塊 start -->
              {$_1st_dividend_modifier_html}
            <!-- 反水修改區塊 end -->
            <div class="form-group row mb-0">
              <label class="col-sm-2 col-form-label py-2">当前设置</label>
              <div class="col-sm-10 py-2">
                <p class="mb-2 text-secondary">当前已套用设置值；对象代理商发展下线后，不允许修改；后台高级帐号可重设整条代理线</p>
                <p class="mb-2">
                  <strong>{$tr['each agent keep']} </strong>
                  {$f_to_100($_1st_agent->feedbackinfo->dividend->{'1st_agent'}->child_occupied->min)} %
                    ~
                  {$f_to_100($_1st_agent->feedbackinfo->dividend->{'1st_agent'}->child_occupied->max)} %</p>
                <p class="mb-0"><strong>直属代理商保障 </strong>{$f_to_100($_1st_agent->feedbackinfo->dividend->{'1st_agent'}->last_occupied)} %</p>
              </div>
            </div>
          </div>
        </div>		
HTML;

  // 表格栏位名称
// 下线第1代 身分 入会时间(UTC+8) 帐号状态
$table_colname_html_hint_head = <<<HTML
<tr>
  <th colspan="6" class="border-top-0 border-bottom-0 align-bottom pl-0 pb-3"></th>
  <th colspan="2" rowspan="2" class="well text-center border-top-0" style="vertical-align: middle"> {$tr['agent accounts for setting']} </th>
  <th colspan="4" class="well text-center bg-primary border-top-0"> {$tr['the actual betting commission']} </th>
</tr>
<tr>
  <th colspan="6" class="border-top-0"></th>
  <th colspan="2" class="text-center bg-success">
    直属投注<button type="button" class="btn btn-sm bg-transparent border-0" style="border: 0px;" title="直属投注计算" data-container="body" data-toggle="popover" data-placement="top" data-content="当直属下线投注，{$user->account} 抽成之比例<hr><strong>注</strong>：直属下线被禁用的情境，该禁用帐号的直属下线被视为 {$user->account} 的直属下线" data-html="true"><span class="glyphicon glyphicon-info-sign"></span></button>
  </th>
  <th colspan="2" class="text-center bg-warning">
    非直属投注<button type="button" class="btn btn-sm bg-transparent border-0" style="border: 0px;" title="非直属投注计算" data-container="body" data-toggle="popover" data-placement="right" data-content="当该下线代理商的直属会员投注时，{$user->account} 抽成之比例" data-html="true"><span class="glyphicon glyphicon-info-sign"></span></button>
  </th>
</tr>
HTML;

$p_commission = commission_direct($ancestors, 'preferential');
$d_commission = commission_direct($ancestors, 'dividend');

$table_colname_html = '
<tr>
  <th>ID</th>
  <th>'.$tr['1st downline'] = '下线第一代'.'</th>
  <th class="hidden">转帐</th>
  <th>' . $tr['identity'] . '</th>
  <th>' . $tr['admission time'] . '</th>
  <th>' . $tr['account status'] . '</th>
  <th title="分配给下线" data-toggle="tooltip" class="bg-light">' . $tr['reward'] . '(<'. intval($f_to_100($user->feedbackinfo->preferential->allocable)).'%)</th>
  <th title="分配给下线" data-toggle="tooltip" class="bg-light">' . $tr['commissions'] . '(<'. intval($f_to_100($user->feedbackinfo->dividend->allocable)).'%)</th>
  <th class="bg-success">' . $tr['reward'] . '</th>
  <th class="bg-success">' . $tr['commissions'] . '</th>
  <th class="bg-success">' . $tr['reward'] . '</th>
  <th class="bg-success">' . $tr['commissions'] . '</th>
</tr>
';

$listrow = function($memberinfo) use ($tr, $options, $user, $_1st_agent, $p_commission, $d_commission) {
  $tr['tranfer'] = '转帐';
  $member_href_html = "<a href=\"$_SERVER[SCRIPT_NAME]?a={$memberinfo->id}\">{$memberinfo->account}</a>";
  // 帐号身分；依序为会员、代理商、管理员
  if($memberinfo->therole == 'M') {
    $member_href_html = "$memberinfo->account";
    $therole_html = '<a href="#" data-toggle="tooltip" data-placement="right" title="'.$tr['member']='会员'.'"><span class="glyphicon glyphicon-user"></span></a>';

  } elseif($memberinfo->therole == 'A') $therole_html = '<a href="#" data-toggle="tooltip" data-placement="right" title="'.$tr['agent']='代理商'.'"><span class="glyphicon glyphicon-knight"></span></a>';
  else $therole_html = '<a href="#" data-toggle="tooltip" data-placement="right" title="'.$tr['management']='管理员'.'"><span class="glyphicon glyphicon-king"></span></a>';

  // 帐号状态；依序为正常、钱包冻结、禁用
  if($memberinfo->status == 1) $status_html = '<span class="label label-primary">'.$tr['normal']='正常'.'</span>';
  elseif($memberinfo->status == 2) $status_html = '<span class="label label-warning">'.$tr['wallet frozen']='钱包冻结'.'</span>';
  else $status_html = '<span class="label label-danger">'.$tr['disabled']='禁用'.'</span>';
  $setting_state = $memberinfo->status == 1 ? '' : 'disabled';

  // 反水
  $p_allocable_user = float_to_percent($user->feedbackinfo->preferential->allocable);
  $p_allocable_memberinfo = float_to_percent($memberinfo->feedbackinfo->preferential->allocable);
  $p_allocable_memberinfo_str = is_numeric($p_allocable_memberinfo) ? "$p_allocable_memberinfo %" : 'N/A';
  $options_preferential = $options(
    $p_allocable_memberinfo,
    max(0, $p_allocable_user - float_to_percent($_1st_agent->feedbackinfo->preferential->{'1st_agent'}->child_occupied->max)),
    max(0, $p_allocable_user - float_to_percent($_1st_agent->feedbackinfo->preferential->{'1st_agent'}->child_occupied->min))
  );
  $select_preferential_html = $memberinfo->therole != 'A' ? <<<HTML
    <span data-toggle="tooltip" title="此设定只适用于下线 {$memberinfo->account} 是代理商的情形">-</span>
HTML
: <<<HTML
    <select data-toggle="tooltip" title="{$user->account} 给予下线代理商 {$memberinfo->account} 在整条代理线中占用的占成比例；當前的设定值为 {$p_allocable_memberinfo_str}，若选单错误，请重新设定。" {$setting_state} class="form-control input-sm preferential_dispatch" data-account="{$memberinfo->account}" onchange="select_preferentialratio_chang({$memberinfo->id}, event);">$options_preferential</select>
HTML;
  $p_allocable_diff = !is_null($p_allocable_user) && !is_null($p_allocable_memberinfo) ? $p_allocable_user - $p_allocable_memberinfo : 0;

  // 佣金
  $d_allocable_user = float_to_percent($user->feedbackinfo->dividend->allocable);
  $d_allocable_memberinfo = float_to_percent($memberinfo->feedbackinfo->dividend->allocable);
  $d_allocable_memberinfo_str = is_numeric($d_allocable_memberinfo) ? "$d_allocable_memberinfo %" : 'N/A';
  $options_dividend = $options(
    $d_allocable_memberinfo,
    max(0, $d_allocable_user - float_to_percent($_1st_agent->feedbackinfo->dividend->{'1st_agent'}->child_occupied->max)),
    max(0, $d_allocable_user - float_to_percent($_1st_agent->feedbackinfo->dividend->{'1st_agent'}->child_occupied->min))
  );

  $d_allocable_diff = !is_null($d_allocable_user) && !is_null($d_allocable_memberinfo) ? $d_allocable_user - $d_allocable_memberinfo : 0;

  $p_direct_html = <<<HTML
  <span data-toggle="tooltip" title="当直属 {$memberinfo->account} 投注时，{$user->account} 可抽成之比例">$p_commission %</span>
HTML;
$d_direct_html = <<<HTML
  <span data-toggle="tooltip" title="当直属 {$memberinfo->account} 投注时，{$user->account} 可抽成之比例">$d_commission %</span>
HTML;
$p_indirect_html = <<<HTML
  <span data-toggle="tooltip" title="当直属 {$memberinfo->account} 的下线投注时，{$user->account} 可抽成之比例" data-bind="p_agent_{$memberinfo->id}">$p_allocable_diff %</span>
HTML;
$d_indirect_html = <<<HTML
  <span data-toggle="tooltip" title="当直属 {$memberinfo->account} 的下线投注时，{$user->account} 可抽成之比例" data-bind="d_agent_{$memberinfo->id}">$d_allocable_diff %</span>
HTML;

  if ($memberinfo->therole != 'A') {
    $select_dividend_html = <<<HTML
      <span data-toggle="tooltip" title="此设定只适用于下线 {$memberinfo->account} 是代理商的情形">-</span>
HTML;
    $p_indirect_html = $d_indirect_html = '-';
  } else {
    $select_dividend_html = <<<HTML
      <select data-toggle="tooltip" title="{$user->account} 给予下线代理商 {$memberinfo->account} 在整条代理线中占用的占成比例；當前的设定值为 {$d_allocable_memberinfo_str}，若选单错误，请重新设定。" {$setting_state} class="form-control input-sm dividend_dispatch" data-account="{$memberinfo->account}" onchange="select_dividendratio_chang({$memberinfo->id}, event);">$options_dividend</select>
HTML;
  }

  $intval = 'intval';
  $f_to_100 = 'float_to_percent';

  return $html = <<<HTML
    <tr>
      <td class="text-left" id="member_id">{$memberinfo->id}</td>
      <td class="text-left">$member_href_html</td>
      <td class="text-left hidden"><a href="member_account.php?a={$memberinfo->id}" class="btn btn-success btn-sm" role="button">$tr[tranfer]</a></td>
      <td class="text-left">$therole_html</td>
      <td class="text-left">{$memberinfo->enrollmentdate}</td>
      <td class="text-left">$status_html</td>
      <td class="text-center">$select_preferential_html</td>
      <td class="text-center">$select_dividend_html</td>
      <td class="text-center">$p_direct_html</td>
      <td class="text-center">$d_direct_html</td>
      <td class="text-center">$p_indirect_html</td>
      <td class="text-center">$d_indirect_html</td>
    </tr>
HTML;
};

$show_listrow_html = implode('', array_map($listrow, $childs));

// 提示讯息
$lockedinfo_html = '';

$occupied_list_DOMs = function($memberinfo) use ($user, $_1st_agent, $config) {
  $memberinfo = (object) $memberinfo;
  // 计算法一，对直属有利
  // $occupied = isset($memberinfo->source) && isset($memberinfo->occupied_state) && $memberinfo->occupied_state ? array_sum($memberinfo->source) : 0;
  // 计算法二，对没向下分配的代理商有利
  $occupied = isset($memberinfo->source) ? array_sum($memberinfo->source) : 0;
  switch($memberinfo->role):
    case '#first#':
      $title = !empty($occupied) ? "一级代理商 {$memberinfo->account} 持有 $occupied % 的比例，其中 {$memberinfo->source['occupied']} % 为设定下线代理商后自身保留，{$memberinfo->source['indemnify']} %为代理线结馀归还。" : '';
      break;
    case '#last#':
      $title = !empty($occupied) ? "直属代理商 {$memberinfo->account} 持有 $occupied % 的比例，其中 {$memberinfo->source['occupied']} % 为扣除上层后可取得比例，{$memberinfo->source['indemnify']} % 由直属保障贴补。" : '';
      break;
    default:
      $title = !empty($occupied) ? "代理商 {$memberinfo->account} 持有 $occupied % 的比例" : '';
    break;
  endswitch;
  // if( isset($memberinfo->occupied_state) && $memberinfo->occupied_state == false ):
  if(!$occupied && $memberinfo->id != $config['system_company_id']):
    $title = '没有可分配的比例';
  endif;
  if(in_array($memberinfo->role, [$config['system_company_account']])):
    $occupied_html = '';
  else:
    $occupied_html = "($occupied%)";
  endif;
  $btn_state = !empty($memberinfo->id) && $memberinfo->id != $config['system_company_id'] ? '' : 'disabled';
  if (isset($memberinfo->status) && $memberinfo->status == 0 && $memberinfo->id != $config['system_company_id'] && !empty($memberinfo->id)):
    $btn_style = 'btn-danger';
    $title .= '；此帐号状态为禁用，请联系管理人员';
  elseif ($memberinfo->id == $_1st_agent->id): $btn_style = 'btn-info';
  elseif ($memberinfo->id == $user->id): $btn_style = 'btn-primary';
  else: $btn_style = 'btn-default';
  endif;

  return <<<HTML
    <a class="btn $btn_style btn-sm $btn_state" href="{$_SERVER['SCRIPT_NAME']}?a={$memberinfo->id}" data-toggle="tooltip" data-html="true" title="$title">{$memberinfo->account} $occupied_html</a>
HTML;
};

// 图例：反水占成比例 (上层的 occupied = 上层 allocable - 下层 allocable)
$preferential_occupied_list_DOMs = array_filter(array_map($occupied_list_DOMs, occupied_list($ancestors, 'preferential')));
$preferential_occupied_list = implode('<span class="glyphicon glyphicon-arrow-right" aria-hidden="true"></span>', $preferential_occupied_list_DOMs);

// 图例：佣金占成比例
$dividend_occupied_list_DOMs = array_filter(array_map($occupied_list_DOMs, occupied_list($ancestors, 'dividend')));
$dividend_occupied_list = implode('<span class="glyphicon glyphicon-arrow-right" aria-hidden="true"></span>', $dividend_occupied_list_DOMs);
// echo '<pre>', var_dump( $dividend_occupied_list_DOMs ), '</pre>'; exit();
$intval = 'intval';

$p_hint = <<<HTML
  当 $user->account 的直属下线（假名小华）投注时，小华扮演玩家的角色，$user->account 可获得小华 {$intval($f_to_100($user->feedbackinfo->preferential->allocable))} % 的投注反水抽成；<hr>
  为了保障较下游的代理商，若投注抽成过少的情况，则会补足到末代保障的比例；多馀的部份则返回一级代理商 $_1st_agent->account 身上。<hr>
  当 $user->account 的直属下线（小华）为代理商时，小华拥有 $user->account 所设定代理反水占成比例可供支配<small>（即小华可获得直属下线的投注反水抽成，比例足够的情形下可对下线配比）</small>
HTML;

$d_hint = <<<HTML
  当 $user->account 的直属下线（假名小华）投注时，小华扮演玩家的角色，$user->account 可获得小华 {$intval($f_to_100($user->feedbackinfo->dividend->allocable))} % 的投注佣金抽成；<hr>
  为了保障较下游的代理商，若投注抽成过少的情况，则会补足到末代保障的比例；多馀的部份则返回一级代理商 $_1st_agent->account 身上。<hr>
  当 $user->account 的直属下线（小华）为代理商时，小华拥有 $user->account 所设定代理佣金占成比例可供支配<small>（即小华可获得直属下线的投注佣金抽成，比例足够的情形下可对下线配比）</small>
HTML;

$agentadmin_html = <<<HTML
  <div class="form-group row mt-4 mb-0 d-flex telescopic_btn">
		<label class="col-sm-12 col-form-label py-3 bg-light border d-flex">
			<h6 class="d-flex font-weight-bold mb-0">
				<span class="glyphicon glyphicon-cog mr-2"></span>
				{$tr['Agency organization transfer and accounting setup']}
			</h6>	
			<i class="fas fa-angle-up ml-auto"></i>
		</label>
	</div>
  <div class="row border border-top-0">
		<div class="col-11 mx-auto py-3" data-text="代理商组织转帐及占成设定">
			<div class="form-group row">
				<div class="col-12 font-weight-bold mb-2 d-flex align-items-center">
					<i class="fas fa-exclamation-circle text-secondary mr-1" data-container="body" data-toggle="popover" data-placement="top" data-content="{$p_hint}" data-html="true" data-original-title="反水占成比例"></i>
					{$tr['proportioned of reward']}
					<a href="#reward_link" class="btn btn-outline-secondary btn-xs ml-2 reward_link">設定</a>					
				</div>
				<div class="col-sm-12 mt-2">
          $preferential_occupied_list
				</div>
				<div class="col-sm-12 mt-2">
          代理商 $user->account 从上层 {$ancestors[1]->account} 获得的反水占成比例：<strong class="page_allocable_preferential">{$intval($f_to_100($user->feedbackinfo->preferential->allocable))} %</strong>
				</div>
			</div>
			<div class="form-group row border-top border-bottom py-3">
				<div class="col-sm-2 font-weight-bold mb-2">							
					<i class="fas fa-exclamation-circle text-secondary" data-container="body" data-toggle="popover" data-placement="top" data-content="{$d_hint}" data-html="true" data-original-title="佣金占成比例"></i>
					{$tr['proportioned of commission']}
					<a href="#commission_link" class="btn btn-outline-secondary btn-xs ml-2 commission_link">設定</a>
				</div>
				<div class="col-sm-12">
          $dividend_occupied_list
				</div>
				<div class="col-sm-12 mt-2">
          代理商 $user->account 从上层 {$ancestors[1]->account} 获得的佣金占成比例：<strong class="page_allocable_dividend">{$intval($f_to_100($user->feedbackinfo->dividend->allocable))} %</strong>
				</div>
			</div>
			<div class="form-group row mt-5">
        <table id="transaction_list" class="table table-striped" cellspacing="0" width="100%">
          <thead>
            $table_colname_html_hint_head
            $table_colname_html
          </thead>
          <tbody>
            $show_listrow_html
          </tbody>
        </table>
			</div>
		</div>
	</div>
HTML;
endif;

$panelbody_content .= $_1st_agent_html_V2 . $agentadmin_html;
// --------------------------------------
// jquery post ajax send end.
// --------------------------------------

//load 動畫
$load_animate="<div class='load_datatble_animate'><img src='./ui/loading.gif'></div>";

$indexbody_content = <<<HTML
{$load_animate}
<ul class="nav nav-tabs mt-3" id="memberoverviewTab" role="tablist">
  <li class="nav-item">
    <a href="member_overview_operating_depositgtoken.php?a=$user->id" class="nav-link" id="bethistory-tab" role="tab" aria-controls="bethistory" aria-selected="true">
        {$tr['Manual deposit GTOKEN']}
    </a>
  </li>
  <li class="nav-item">
    <a href="member_overview_operating_withdrawalgtoken.php?a=$user->id" class="nav-link" id="transactionrecord-tab" role="tab" aria-controls="transactionrecord" aria-selected="false">
        {$tr['Manual withdraw GTOKEN']}
    </a>
  </li>
  <li class="nav-item">
    <a href="member_overview_operating_depositgcash.php?a=$user->id" class="nav-link" id="loginhistory-tab" role="tab" aria-controls="loginhistory" aria-selected="false">
		 	{$tr['Manual deposit GCASH']}
    </a>
  </li>
  <li class="nav-item">
    <a href="member_overview_operating_withdrawalgcash.php?a=$user->id" class="nav-link" id="auditrecord-tab" role="tab" aria-controls="auditrecord" aria-selected="false">
			{$tr['Manual withdraw GCASH']}
    </a>
  </li>
  <li class="nav-item">
    <a class="nav-link active" id="agentssetting-tab" href="#" role="tab" aria-controls="agentssetting" aria-selected="false">
			{$tr['agent ratio setting']}
    </a>
  </li>
</ul>

<!-- tab內容 -->
<div class="tab-content tab_p_overview" id="overviewoperating">

        <div class="tab-pane fade" id="bethistory" role="tabpanel" aria-labelledby="bethistory-tab">  
            <!-- 存入遊戲幣 -->
        </div>

        <div class="tab-pane fade" id="transactionrecord" role="tabpanel" aria-labelledby="transactionrecord-tab">
            <!-- 提出遊戲幣 -->	
        </div>

        <div class="tab-pane fade" id="loginhistory" role="tabpanel" aria-labelledby="loginhistory-tab">
            <!--  存入現金 -->
        </div>

        <div class="tab-pane fade" id="auditrecord" role="tabpanel" aria-labelledby="auditrecord-tab">
            <!--  提出現金 -->

        </div>

        <div class="tab-pane fade show active" id="agentssetting" role="tabpanel" aria-labelledby="agentssetting-tab">
            <!--  代理占比設定 -->
            <div class="row my-3">
	            <div class="col-12 mx-auto">
                    {$panelbody_content}
                </div>
            </div>
        </div>
</div>
HTML;

$extend_js = $extend_js.<<<HTML
<!-- 參考使用 datatables 顯示 -->
<!-- https://datatables.net/examples/styling/bootstrap.html -->
<link rel="stylesheet" type="text/css" href="./in/datatables/css/jquery.dataTables.min.css?v=180612">
<script type="text/javascript" language="javascript" src="./in/datatables/js/jquery.dataTables.min.js?v=180612"></script>
<script type="text/javascript" language="javascript" src="./in/datatables/js/dataTables.bootstrap.min.js?v=180612"></script>
<link href="in/datetimepicker/jquery.datetimepicker.css" rel="stylesheet"/>
<script src="in/datetimepicker/jquery.datetimepicker.full.min.js"></script>
<style>
  /* 需要改CSS 名稱以面控制所有 */
  #transaction_list_paginate{
    display: flex;
    margin-top: 10px;
  }
  #transaction_list_paginate .pagination{
    margin-left: auto;			
	}
	#transaction_list_wrapper{
		margin-left: 0;
		margin-right: 0;
		padding-left: 0;
		padding-right: 0;
	}
  /* 清除按鈕 */
  .clear_btn{
	  width: 20%;
	}
	#transaction_list .bg-primary{
		background-color: #e0ecf9b8!important;
	}
  /* 外部Datatable search border-color */
  #search_agents{
	border-color: #ced4da!important;
	padding: .2rem .75rem;
  }
  #transaction_list_filter{
	  position: absolute;
    left: 0px;
    top: 40px;
  }
  #transaction_list_length {
    margin-top: 10px;
    padding-top: 0.25em;
  }
  .tab_p_overview{
		padding: 15px;
  }
</style>
<script>
		$(document).ready(function() {	
			// locationhash http:// #id
			// split  http:// #id
			var locationtab = location.hash;
			var locationsplit = locationtab.split('_');

			var locationhash = locationsplit[0];
			var locationsearch = location.search;

			if( locationhash != '' ){				
				// tab button show from http:// #id 
				$('#memberoverviewTab a[href="'+locationhash+'"]').tab('show');					
			}
			//tab button has show close load animate
			$('#memberoverviewTab a[href="'+locationhash+'"]').on('shown.bs.tab', function (e) {
				$('.load_datatble_animate').fadeOut();
			});
			//if locationhash = null or locationhash = First one tab , close load animate 
			if( locationhash == '' || locationhash == '#bethistory' ){
				$('.load_datatble_animate').hide();
			}

			//if locationtab = reward_link  load animate hide()
			if( locationtab == '#reward_link' ){	
				$('.load_datatble_animate').hide();
			}
			if( locationtab == '#commission_link' ){	
				$('.load_datatble_animate').hide();
			}
				
			$('[data-toggle="popover"]').popover();
			$('[data-toggle="tooltip"]').tooltip();
			//up open box
			$('.telescopic_btn').click(function(){
				var closeheight = $(this).next().hasClass('closeheight');				
				if( closeheight == false ){
					$(this).next().slideUp();
					$(this).next().addClass('closeheight');
				}else {
					$(this).next().slideDown();
					$(this).next().removeClass('closeheight');
				}
			});

			$('#search_agents').keyup(function(){
				tl_tabke.search($(this).val()).draw();
			});			

		});
	</script>
HTML;
// ----------------------------------------------------------------------------
// 準備填入的內容
// ----------------------------------------------------------------------------

// 將內容塞到 html meta 的關鍵字, SEO 加強使用
$tmpl['html_meta_description'] = $tr['host_descript'];
$tmpl['html_meta_author'] = $tr['host_author'];
$tmpl['html_meta_title'] = $tr['member overview'] . '-' . $tr['host_name'];

// // 頁面大標題
// $tmpl['page_title'] = $menu_breadcrumbs;
// 主要內容 -- title
$function_title  = $user->account.'功能操作';
// // 主要內容 -- content
// $tmpl['panelbody_content'] = $indexbody_content;
// // 擴充再 head 的內容 可以是 js or css
// $tmpl['extend_head'] = $extend_head;
// // 擴充於檔案末端的 Javascript
// $tmpl['extend_js'] = $extend_js;
// ----------------------------------------------------------------------------
// 填入內容結束。底下為頁面的樣板。以變數型態塞入變數顯示。
// ----------------------------------------------------------------------------
// include "template/member_tml.php";

$query_userinfo = json_encode($user->feedbackinfo);

return render(
  __DIR__ . '/member_agents_setting.view.php',
  // compact('function_title', 'has_permission', 'is_test_account', 'page_title_1st_agent_html', '_1st_agent_html_V2',
  //   'page_title_html', 'agentadmin_html', 'is_1st_agent', 'loggin_userinfo', '', ''
  compact('function_title', 'menu_breadcrumbs', 'indexbody_content','panelbody_content', 'query_userinfo', 'extend_js')
);

?>
