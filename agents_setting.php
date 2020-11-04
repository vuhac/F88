<?php
// ----------------------------------------------------------------------------
// Features:  后台--设定代理商佣金
// File Name:  agents_setting.php
// Author:    Webb Lu
// Related:    member_account.php
// Log:
// 2018.1.15 init
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

// ----------------------------------------------------------------------------
// Main
// ----------------------------------------------------------------------------
// 初始化变数
// 功能标题，放在标题列及meta
// 设定代理商佣金
$function_title = $tr['Member Details'] = $tr['Set agency commission'];
// 扩充 head 内的 css or js
$extend_head = '';
// 放在结尾的 js
$extend_js = '';
// 主要内容 -- title
$paneltitle_content = '<span class="glyphicon glyphicon-user" aria-hidden="true"></span>' . $function_title;
// body 内的主要内容
$panelbody_content = '';
// ----------------------------------------------------------------------------
// 當前所在位置 - 配合选单位置让使用者可以知道當前所在位置
$menu_breadcrumbs = '
<ol class="breadcrumb">
  <li><a href="home.php">' . $tr['Home'] . '</a></li>
  <li><a href="#">' . $tr['Members and Agents'] . '</a></li>
    <li><a href="member.php">' . $tr['Member inquiry'] . '</a></li>
  <li class="active">' . $function_title . '</li>
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

$sql = <<<SQL
  SELECT root_member.*, root_parent.account AS parent_account FROM root_member
  LEFT JOIN root_member AS root_parent ON root_member.parent_id = root_parent.id
  WHERE root_member.id = :account_id;
SQL;
$r = runSQLALL_prepared($sql, $values = ['account_id' => $account_id]);
count($r) == 1 OR die($debug_msg = '资料库系统有问题，请联络开发人员处理。');
$user = $r[0];
$user->feedbackinfo = getMemberFeedbackinfo($user);

$feedbackinfoHelper = new FeedbackInfoHelper(['member_id' => $account_id]);

// 取得 user 直属下线
$sql = "SELECT *, to_char((enrollmentdate AT TIME ZONE '$tzonename'),'YYYY-MM-DD HH24:MI:SS') as enrollmentdate FROM root_member WHERE root_member.parent_id = :account_id;";
$r = runSQLALL_prepared($sql, $values = ['account_id' => $account_id]);
$childs = array_map(function ($child) use ($feedbackinfoHelper) {
  $child->feedbackinfo = getMemberFeedbackinfo($child);
  $child->children = $feedbackinfoHelper->member_list[$child->id]->children;
  return $child;
}, $r);

// 取得祖先与一级代理商
$ancestors = $feedbackinfoHelper->getPredecessorList();
$_1st_agent = $feedbackinfoHelper->getFirstLevelAgent();
// 未發展下線： 無 childs 且無邀請碼
$is_agent_tree_locked = count(MemberTreeNode::getSuccessorList($_1st_agent->id)) > 1 || has_spread_linkcode($_1st_agent->account);

// 生成 select 中的 options；特定数值范围
$options = function ($selected = null, $min = 0, $max = 100) {
  $option_null = '<option value>未设定</option>';
  $options_Arr = array_map(function ($value) use ($selected) {
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
$panelbody_content = <<<HTML
<div class="row">
  <div class="col-12 col-md-12">
    <p>
      {$tr['User account']}<button class="btn btn-link" type="button">$user->account</button>
      {$tr['identity']}<button class="btn btn-link" type="button">{$theroleicon[$user->therole]}</button>
      {$tr['upper agent number']}
      <button
        class="btn btn-link upper_agent_btn"
        type="button"
        value="{$user->parent_id}"
        click="locah"
      >%s</button>
    </p>
  </div>
</div>
HTML;

$panelbody_content = sprintf($panelbody_content, strcasecmp($user->parent_account, $config['system_company_account']) == 0 ? 'N/A' : $user->parent_account);

$checked = function ($user, $type, $attr, $option) {
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
};

$disable_rebuild_tree = $is_agent_tree_locked && !in_array($_SESSION['agent']->account, $su['superuser']);

$_1st_preferential_modifier_html = $disable_rebuild_tree ? '' : <<<HTML
<tr class="cfg_group_preferential">
  <td class="text-right" width="25%"><strong>修改设置</strong></td>
  <td>
    <div class="input-group">
      <select style="border: 1px solid #ccc;" class="input-sm" data-action="set_1st_agent_config" data-type="preferential" data-agentid="{$_1st_agent->id}" data-description="预定义的一级代理商设置组">
      </select>
      <button class="ml-2 btn btn-success btn-sm" id="confirm_preferential_config" data-type="preferential" data-agentid="{$_1st_agent->id}" disabled>确认修改</button>
    </div>
    <div class="alert alert-info mt-2" role="alert" id="preferential_cfg_hint"><small>提供默认的百分比设置，让您不再烦恼！</small></div>

    <div>
      <div><strong> {$tr['member self reward']} </strong></div>
      <select style="border: 1px solid #ccc;" class="input-sm" data-action="self_ratio" data-type="preferential" data-agentid="{$_1st_agent->id}" data-description="会员自身反水">
        {$options($f_to_100($_1st_agent->feedbackinfo->preferential->{'1st_agent'}->self_ratio))}
      </select>
    </div>

    <div class="input-group">
      <div><strong> {$tr['each agent keep']} </strong></div>
      <div class="input-group">
        <select style="border: 1px solid #ccc;" class="input-sm" data-action="child_occupied.min" data-type="preferential" data-agentid="{$_1st_agent->id}" data-description="代理商最少保留" title="代理商最少保留" data-toggle="tooltip">
          {$options($f_to_100($_1st_agent->feedbackinfo->preferential->{'1st_agent'}->child_occupied->min))}
        </select>
        <span class="input-group-append" style="margin: auto 0.5em">~</span>
        <select style="border: 1px solid #ccc;" class="input-sm" data-action="child_occupied.max" data-type="preferential" data-agentid="{$_1st_agent->id}" data-description="代理商最多保留" title="代理商最多保留" data-toggle="tooltip">
          {$options($f_to_100($_1st_agent->feedbackinfo->preferential->{'1st_agent'}->child_occupied->max))}
        </select>
      </div>
    </div>

    <div>
      <div><strong> {$tr['Guarantee of direct agent']} </strong></div>
      <select style="border: 1px solid #ccc;" class="input-sm" data-action="last_occupied" data-type="preferential" data-agentid="{$_1st_agent->id}" data-description="末代保障">
        {$options($f_to_100($_1st_agent->feedbackinfo->preferential->{'1st_agent'}->last_occupied))}
      </select>
    </div>
  </td>
</tr>
HTML;

$_1st_dividend_modifier_html = $disable_rebuild_tree ? '' : <<<HTML
<tr class="cfg_group_dividend">
  <td class="text-right" width="25%"><strong>修改设置</strong></td>
  <td>
    <div class="input-group">
      <select style="border: 1px solid #ccc;" class="input-sm" data-action="set_1st_agent_config" data-type="dividend" data-agentid="{$_1st_agent->id}" data-description="预定义的一级代理商设置组">
      </select>
      <button class="ml-2 btn btn-success btn-sm" id="confirm_dividend_config" data-type="dividend" data-agentid="{$_1st_agent->id}">确认修改</button>
    </div>
    <div class="alert alert-info mt-2" role="alert" id="dividend_cfg_hint"><small>提供默认的百分比设置，让您不再烦恼！</small></div>
    <div>
      <div><strong> {$tr['each agent keep']} </strong></div>
      <div class="input-group">
        <select style="border: 1px solid #ccc;" class="input-sm" data-action="child_occupied.min" data-type="dividend" data-agentid="{$_1st_agent->id}" data-description="代理商最少保留" title="代理商最少保留" data-toggle="tooltip">
          {$options($f_to_100($_1st_agent->feedbackinfo->dividend->{'1st_agent'}->child_occupied->min))}
        </select>
        <span class="input-group-append" style="margin: auto 0.5em;">~</span>
        <select style="border: 1px solid #ccc;" class="input-sm" data-action="child_occupied.max" data-type="dividend" data-agentid="{$_1st_agent->id}" data-description="代理商最多保留" title="代理商最多保留" data-toggle="tooltip">
          {$options($f_to_100($_1st_agent->feedbackinfo->dividend->{'1st_agent'}->child_occupied->max))}
        </select>
      </div>
    </div>
    <div class="mt-2">
      <div><strong> {$tr['Guarantee of direct agent']} </strong></div>
      <div class="input-group">
        <select style="border: 1px solid #ccc;" class="input-sm" data-action="last_occupied" data-type="dividend" data-agentid="{$_1st_agent->id}" data-description="末代保障">
          {$options($f_to_100($_1st_agent->feedbackinfo->dividend->{'1st_agent'}->last_occupied))}
        </select>
      </div>
    </div>
  </td>
</tr>
HTML;

if ($user->therole != 'A'):
  $_1st_agent_html_V2 = '查询的帐号并非代理商，无代理商佣金可设定。';
  $agentadmin_html = '';

else:
  $_1st_agent_html_V2 = <<<HTML
    <div class="row">
      <div class="col-12 col-md-12">
        <div class="well">
          <div class="row">
            <div class="col-xs-2 col-md-2"><span class="glyphicon glyphicon-cog"><strong>{$tr['reward accounts for setting']}</strong></div>
            <div class="col-xs-10 col-md-10"><small>{$tr['follow as general agent']} {$_1st_agent->account} {$tr['setting']}</small></div>
          </div>
        </div>
        <table class="table table-striped">
          <thead></thead>
          <tbody>
            <tr>
              <td class="text-right" width="25%"><strong>代理线向下发放</strong></td>
              <td class="_1st_agent_setting">
                <span class="radio-inline"><label><input type="radio" value="1" name="p_downward_deposit" data-action="downward_deposit" data-type="preferential" data-agentid="{$_1st_agent->id}" data-description="代理线持有反水占成" data-value="启用" {$checked($_1st_agent,'preferential','downward_deposit',1)}> {$tr['y']} </label></span>
                <span class="radio-inline"><label><input type="radio" value="0" name="p_downward_deposit" data-action="downward_deposit" data-type="preferential" data-agentid="{$_1st_agent->id}" data-description="代理线持有反水占成" data-value="停用" {$checked($_1st_agent,'preferential','downward_deposit',0)}> {$tr['n']} </label></span>
                <div><small>{$tr['When disable, the rebate remains on the general agent']}</small></div>
              </td>
            </tr>
            <!-- <tr>
              <td class="text-right" width="25%"><strong>反水自动发放</strong></td>
              <td>
                <span class="radio-inline"><label><input type="radio" value="1" name="auto_deposit_preferential" disabled>是</label></span>
                <span class="radio-inline"><label><input type="radio" value="0" name="auto_deposit_preferential" disabled checked>否</label></span>
                <div><small>Coming soon...</small></div>
              </td>
            </tr> -->
            <!-- 反水修改區塊 start -->
            {$_1st_preferential_modifier_html}
            <!-- 反水修改區塊 end -->
            <tr>
              <td class="text-right" width="25%"><strong>当前设置</strong></td>
              <td>
                <div class="alert alert-info mt-2" role="alert"><small>当前已套用设置值；对象代理商发展下线后，不允许修改；后台维运帐号可重设整条代理线</small></div>
                <div><strong> {$tr['member self reward']} </strong>{$f_to_100($_1st_agent->feedbackinfo->preferential->{'1st_agent'}->self_ratio)} %</div>
                <div>
                  <div><strong> {$tr['each agent keep']} </strong>
                  {$f_to_100($_1st_agent->feedbackinfo->preferential->{'1st_agent'}->child_occupied->min)} %
                  ~
                  {$f_to_100($_1st_agent->feedbackinfo->preferential->{'1st_agent'}->child_occupied->max)} %
                  </div>
                </div>
                <div>
                  <div><strong> {$tr['Guarantee of direct agent']} </strong>{$f_to_100($_1st_agent->feedbackinfo->preferential->{'1st_agent'}->last_occupied)} %</div>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
    <div class="row">
      <div class="col-12 col-md-12">
        <div class="well">
        <div class="row">
          <div class="col-xs-2 col-md-2"><span class="glyphicon glyphicon-cog"><strong> {$tr['proportioned of commission']} </strong></div>
          <div class="col-xs-10 col-md-10"><small>{$tr['follow as general agent']}  {$_1st_agent->account} {$tr['setting']} </small></div>
        </div>
        </div>
        <table class="table table-striped">
          <thead></thead>
          <tbody>
            <tr>
              <td class="text-right" width="25%"><strong>代理线向下发放</strong></td>
              <td class="_1st_agent_setting">
                <span class="radio-inline"><label><input type="radio" value="1" name="d_downward_deposit" data-action="downward_deposit" data-type="dividend" data-agentid="{$_1st_agent->id}" data-description="代理线持有佣金占成" data-value="启用" {$checked($_1st_agent,'dividend','downward_deposit',1)}> {$tr['y']} </label></span>
                <span class="radio-inline"><label><input type="radio" value="0" name="d_downward_deposit" data-action="downward_deposit" data-type="dividend" data-agentid="{$_1st_agent->id}" data-description="代理线持有佣金占成" data-value="停用" {$checked($_1st_agent,'dividend','downward_deposit',0)}> {$tr['n']} </label></span>
                <div><small>{$tr['When disable, the commission remains on the general agent']}</small></div>
              </td>
            </tr>
            <!-- <tr>
              <td class="text-right" width="25%"><strong>佣金自动发放</strong></td>
              <td>
                <span class="radio-inline"><label><input type="radio" value="1" name="auto_deposit_divide" disabled>是</label></span>
                <span class="radio-inline"><label><input type="radio" value="0" name="auto_deposit_divide" disabled checked>否</label></span>
                <div><small>Coming soon...</small></div>
              </td>
            </tr> -->
            {$_1st_dividend_modifier_html}
            <tr>
              <td class="text-right" width="25%"><strong>当前设置</strong></td>
              <td>
                <div class="alert alert-info mt-2" role="alert"><small>当前已套用设置值；对象代理商发展下线后，不允许修改；后台维运帐号可重设整条代理线</small></div>
                <div>
                  <div><strong> {$tr['each agent keep']} </strong>
                  {$f_to_100($_1st_agent->feedbackinfo->dividend->{'1st_agent'}->child_occupied->min)} %
                  ~
                  {$f_to_100($_1st_agent->feedbackinfo->dividend->{'1st_agent'}->child_occupied->max)} %
                  </div>
                </div>
                <div>
                  <div><strong> {$tr['Guarantee of direct agent']} </strong>{$f_to_100($_1st_agent->feedbackinfo->dividend->{'1st_agent'}->last_occupied)} %</div>
                </div>
              </td>
            </tr>
            <!-- <tr>
              <td class="text-right" width="25%"><strong> {$tr['each agent keep']} </strong></td>
              <td class="input-group">
                <select style="border: 1px solid #ccc;" class="input-sm" data-action="child_occupied.min" data-type="dividend" data-agentid="{$_1st_agent->id}" data-description="代理商最少保留" title="代理商最少保留" data-toggle="tooltip">
                  {$options($f_to_100($_1st_agent->feedbackinfo->dividend->{'1st_agent'}->child_occupied->min))}
                </select>
                <span class="input-group-addon" style="width: auto; border:none; background-color: inherit">~</span>
                <select style="border: 1px solid #ccc;" class="input-sm" data-action="child_occupied.max" data-type="dividend" data-agentid="{$_1st_agent->id}" data-description="代理商最多保留" title="代理商最多保留" data-toggle="tooltip">
                  {$options($f_to_100($_1st_agent->feedbackinfo->dividend->{'1st_agent'}->child_occupied->max))}
                </select>
              </td>
            </tr>
            <tr>
              <td class="text-right" width="25%"><strong> {$tr['Guarantee of direct agent']} </strong></td>
              <td>
                <select style="border: 1px solid #ccc;" class="input-sm" data-action="last_occupied" data-type="dividend" data-agentid="{$_1st_agent->id}" data-description="末代保障">
                  {$options($f_to_100($_1st_agent->feedbackinfo->dividend->{'1st_agent'}->last_occupied))}
                </select>
              </td>
            </tr> -->
          </tbody>
        </table>
      </div>
    </div>
  HTML;

  // 表格栏位名称
  // 下线第1代 身分 註册时间(UTC+8) 帐号状态
  $table_colname_html_hint_head = <<<HTML
    <tr>
      <th colspan="5"></th>
      <th class="hidden"></th>
      <th colspan="2" rowspan="2" class="well text-center" style="vertical-align: middle"> {$tr['agent accounts for setting']} </th>
      <th colspan="4" class="well text-center"> {$tr['the actual betting commission']} </th>
    </tr>
    <tr>
      <th colspan="5"></th>
      <th class="hidden"></th>
      <th colspan="2" class="well text-center">
        直属投注<button type="button" class="btn btn-sm" style="border: 0px;" title="直属投注计算" data-container="body" data-toggle="popover" data-placement="top" data-content="当直属下线投注，{$user->account} 抽成之比例<hr><strong>注</strong>：直属下线被禁用的情境，该禁用帐号的直属下线被视为 {$user->account} 的直属下线" data-html="true"><span class="glyphicon glyphicon-info-sign"></span></button>
      </th>
      <th colspan="2" class="well text-center">
        非直属投注<button type="button" class="btn btn-sm" style="border: 0px;" title="非直属投注计算" data-container="body" data-toggle="popover" data-placement="right" data-content="当该下线代理商的直属会员投注时，{$user->account} 抽成之比例" data-html="true"><span class="glyphicon glyphicon-info-sign"></span></button>
      </th>
    </tr>
  HTML;

  $table_colname_html_hint_foot = <<<HTML
    <tr>
      <th colspan="5"></th>
      <th class="hidden"></th>
      <th colspan="2" rowspan="2" class="well text-center" style="vertical-align: middle"> {$tr['agent accounts for setting']} </th>
      <th colspan="2" class="well text-center">
        直属投注<button type="button" class="btn btn-sm" style="border: 0px;" title="直属投注计算" data-container="body" data-toggle="popover" data-placement="bottom" data-content="当直属下线投注，{$user->account} 抽成之比例<hr><strong>注</strong>：直属下线被禁用的情境，该禁用帐号的直属下线被视为 {$user->account} 的直属下线" data-html="true"><span class="glyphicon glyphicon-info-sign"></span></button>
      </th>
      <th colspan="2" class="well text-center">
        非直属投注<button type="button" class="btn btn-sm" style="border: 0px;" title="非直属投注计算" data-container="body" data-toggle="popover" data-placement="right" data-content="当该下线代理商的直属会员投注时，{$user->account} 抽成之比例" data-html="true"><span class="glyphicon glyphicon-info-sign"></span></button>
      </th>
    </tr>
    <tr>
      <th colspan="5"></th>
      <th class="hidden"></th>
      <th colspan="4" class="well text-center"> {$tr['the actual betting commission']} </th>
    </tr>
  HTML;

  $p_commission = commission_direct($ancestors, 'preferential');
  $d_commission = commission_direct($ancestors, 'dividend');

  $table_colname_html = <<<HTML
    <tr>
      <th class="dt-right">ID</th>
      <th class="dt-center">{$tr['direct downline']}</th>
      <th>{$tr['wallet']}</th>
      <th class="dt-center">{$tr['identity']}</th>
      <th class="dt-center">{$tr['admission time']}</th>
      <th>{$tr['account status']}</th>
      <th class="dt-center" title="{$tr['assign to subordinate']}" data-toggle="tooltip">{$tr['reward']}(<{$f_to_100($user->feedbackinfo->preferential->allocable)}%)</th>
      <th class="dt-center" title="{$tr['assign to subordinate']}" data-toggle="tooltip">{$tr['commissions']}(<{$f_to_100($user->feedbackinfo->dividend->allocable)}%)</th>
      <th class="dt-center">{$tr['reward']}</th>
      <th class="dt-center">{$tr['commissions']}</th>
      <th class="dt-center">{$tr['reward']}</th>
      <th class="dt-center">{$tr['commissions']}</th>
    </tr>
  HTML;

  $listrow = function ($memberinfo) use ($tr, $options, $user, $_1st_agent, $p_commission, $d_commission) {
    $member_href_html = "<a href=\"$_SERVER[SCRIPT_NAME]?a={$memberinfo->id}\">{$memberinfo->account}</a>";
    // 帐号身分；依序为会员、代理商、管理员
    $therole_html = '<a href="javascript:void(0)" data-toggle="tooltip" data-placement="right" title="%s"><span class="glyphicon glyphicon-%s"></span></a>';

    if ($memberinfo->therole == 'M') {
      $member_href_html = "$memberinfo->account";
      $therole_html = sprintf($therole_html, $tr['member'], 'user');
    }
    $memberinfo->therole == 'A' and $therole_html = sprintf($therole_html, $tr['agent'], 'knight');
    $memberinfo->therole == 'R' and $therole_html = sprintf($therole_html, $tr['management'], 'king');

    // 帐号状态；依序为0關閉1正常2钱包冻结3暫時禁用
    $status_html_tmpl = '<span class="label label-%s">%s</span>';
    $status_html = sprintf($status_html_tmpl, 'danger', $tr['disable']);
    $memberinfo->status == 1 and $status_html = sprintf($status_html_tmpl, 'primary', $tr['enable']);
    $memberinfo->status == 2 and $status_html = sprintf($status_html_tmpl, 'warning', $tr['freezing']);
    $memberinfo->status == 3 and $status_html = sprintf($status_html_tmpl, 'danger', $tr['blocked']);
    $setting_state = $memberinfo->status == 1 and empty($memberinfo->children) ? '' : 'disabled';

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
    :<<<HTML
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

    return <<<HTML
      <tr>
        <td class="text-right" id="member_id">{$memberinfo->id}</td>
        <td class="text-center">$member_href_html</td>
        <td class="text-left"><a href="member_account.php?a={$memberinfo->id}" class="btn btn-success btn-sm" role="button">{$tr['Inquiry']}</a></td>
        <td class="text-center">$therole_html</td>
        <td class="text-center">{$memberinfo->enrollmentdate}</td>
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
  $agentadmin_message_html = '<div class="well"><span class="glyphicon glyphicon-cog"><strong> ' . $tr['Agency organization transfer and accounting setup'] . ' </strong></div>';

  $occupied_list_DOMs = function ($memberinfo) use ($user, $_1st_agent, $config) {
    $memberinfo = (object) $memberinfo;
    // 计算法一，对直属有利
    // $occupied = isset($memberinfo->source) && isset($memberinfo->occupied_state) && $memberinfo->occupied_state ? array_sum($memberinfo->source) : 0;
    // 计算法二，对没向下分配的代理商有利
    $occupied = isset($memberinfo->source) ? array_sum($memberinfo->source) : 0;
    switch ($memberinfo->role) :
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
    if (!$occupied && $memberinfo->id != $config['system_company_id']):
      $title = '没有可分配的比例';
    endif;
    if (in_array($memberinfo->role, [$config['system_company_account']])):
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
    else:$btn_style = 'btn-default';
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
  <div class="row"><div class="col-12 col-md-12">$agentadmin_message_html</div></div>
  <div class="col-12 col-md-12">
    <div class="row">
      <div class="col-12 col-md-12">
        <p>
          <span class="glyphicon glyphicon glyphicon-tree-deciduous" aria-hidden="true"> {$tr['proportioned of reward']} </span>
          <button type="button" class="btn btn-sm" title="反水占成比例" data-container="body" data-toggle="popover" data-placement="top" data-content="{$p_hint}" data-html="true"><span class="glyphicon glyphicon-info-sign"></span></button>
        </p>
        $preferential_occupied_list<br>
        代理商 $user->account 从上层 {$_1st_agent->account} 获得的反水占成比例：<strong class="page_allocable_preferential">{$intval($f_to_100($user->feedbackinfo->preferential->allocable))} %</strong>
      </div>
    </div>
    <hr>
  </div>
  <div class="col-12 col-md-12">
    <div class="row">
      <div class="col-12 col-md-12">
        <p>
          <span class="glyphicon glyphicon glyphicon-tree-deciduous" aria-hidden="true"> {$tr['proportioned of commission']} </span>
          <button type="button" class="btn btn-sm" title="佣金占成比例" data-container="body" data-toggle="popover" data-placement="top" data-content="{$d_hint}" data-html="true"><span class="glyphicon glyphicon-info-sign"></span></button>
        </p>
        $dividend_occupied_list<br>
        代理商 $user->account 从上层 {$_1st_agent->account} 获得的佣金占成比例：<strong class="page_allocable_dividend">{$intval($f_to_100($user->feedbackinfo->dividend->allocable))} %</strong>
      </div>
    </div>
    <hr>
  </div>

  <div class="col-12 col-md-12">
    <table id="transaction_list" class="table table-striped" cellspacing="0" width="100%">
    <thead>
    $table_colname_html_hint_head
    $table_colname_html
    </thead>
    <tfoot>
    $table_colname_html
    $table_colname_html_hint_foot
    </tfoot>
    <tbody>
    $show_listrow_html
    </tbody>
    </table>
  </div>
HTML;
endif;

$panelbody_content .= $_1st_agent_html_V2 . $agentadmin_html;

// ----------------------------------------------------------------------------
// 准备填入的内容
// ----------------------------------------------------------------------------

// 将内容塞到 html meta 的关键字, SEO 加强使用
$tmpl['html_meta_description'] = $tr['host_descript'];
$tmpl['html_meta_author'] = $tr['host_author'];
$tmpl['html_meta_title'] = $function_title . '-' . $tr['host_name'];

$query_userinfo = json_encode($user->feedbackinfo);

return render(
  __DIR__ . '/agents_setting.view.php',
  compact('function_title', 'menu_breadcrumbs', 'panelbody_content', 'query_userinfo')
);
