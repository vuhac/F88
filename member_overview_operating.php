<?php
// ----------------------------------------------------------------------------
// Features:	後台--會員總覽 - 功能操作
// File Name:	member_overview_operating.php
// Author:		
// Related:   
// Log:
// ----------------------------------------------------------------------------

session_start();

// 主機及資料庫設定
require_once dirname(__FILE__) . "/config.php";
// 支援多國語系
require_once dirname(__FILE__) . "/i18n/language.php";
// 自訂函式庫
require_once dirname(__FILE__) . "/lib.php";

//var_dump($_SESSION);
// var_dump(session_id());

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
// 目前所在位置 - 配合選單位置讓使用者可以知道目前所在位置
// $tr['Home'] = '首頁';
// $tr['Members and Agents'] = '會員與加盟聯營股東';
$menu_breadcrumbs = '
<ol class="breadcrumb">
  <li><a href="home.php">' . $tr['Home'] . '</a></li>
  <li><a href="member_overview.php">' . $tr['member overview'] . '</a></li>
  <li class="active">功能操作</li>
</ol>';
// ----------------------------------------------------------------------------
$extend_head = '';
// 存入遊戲幣
$member_depositgtoken = <<<HTML
<div class="row my-3">
	<div class="col-12 col-md-8 mx-auto">
		<form>
		<div class="form-group row">
			<div class="alert alert-success w-75" role="alert">
			* 人工存入游戏币（GTOKEN）功能：此为管理员或允许的客服人员进行人工存入游戏币的工作管理员可以给与游戏币给任何帐户。<br>
			* 预设是以管理员身份，使用出纳帐号gtokencashier 转帐给指定的帐户。一般帐户之间，不能互转游戏币。<br>
			* 通常可以使用在发放代币优惠，代币反水，或是存款游戏币使用，这些项目取款到现金（GCASH）时需要被稽核。
			</div>
		</div>
		<div class="form-group row">
			<label class="col-sm-2 col-form-label"><span class="text-danger">*</span>游戏币来源帐号</label>
			<div class="col-sm-7">
				<input type="text" class="form-control" placeholder="gpkshiuan" disabled>
			</div>
			<div class="col-sm-3">
				<p class="mb-0">余额 CNY999,988,988,816.15</p>
			</div>
		</div>
		<div class="form-group row">
			<label class="col-sm-2 col-form-label"><span class="text-danger">*</span>存入帐号</label>
			<div class="col-sm-7">
				<input type="text" class="form-control" placeholder="{$tr['Account']}">
			</div>
		</div>
		<div class="form-group row">
			<label class="col-sm-2 col-form-label"><span class="text-danger">*</span>存款金额</label>
			<div class="col-sm-7">
				<input type="number" class="form-control" placeholder="">
			</div>
		</div>
		<div class="form-group row align-items-center">
			<label class="col-sm-2 col-form-label pt-2"><span class="text-danger">*</span>{$tr['Audit method']}</label>
			<div class="col-sm-7">
				<div class="form-check form-check-inline">
					<input class="form-check-input" type="radio" name="inlineRadioOptions" id="inlineRadio1" value="option1" checked>
					<label class="form-check-label" for="inlineRadio1">{$tr['freeaudit']}</label>
				</div>
				<div class="form-check form-check-inline">
					<input class="form-check-input" type="radio" name="inlineRadioOptions" id="inlineRadio2" value="option2">
					<label class="form-check-label" for="inlineRadio2">{$tr['Deposit audit']}</label>
				</div>
				<div class="form-check form-check-inline">
					<input class="form-check-input" type="radio" name="inlineRadioOptions" id="inlineRadio3" value="option3">
					<label class="form-check-label" for="inlineRadio3">{$tr['Preferential deposit audit'] }</label>
				</div>
				<p class="text-secondary my-2"><span class="text-danger">*</span>選擇哪項，请直接输入此笔存款，取款时需要稽核的金额。</p>
				<input type="text" class="form-control" placeholder="0">
			</div>
			<div class="col-sm-3"></div>
		</div>
		<div class="form-group row">
			<label class="col-sm-2 col-form-label"><span class="text-danger">*</span>{$tr['type']}</label>
			<div class="col-sm-7">
				<select class="form-control">
					<option value="tokendeposit">{$transaction_category['tokendeposit']}</option>
					<option value="tokenfavorable">{$transaction_category['tokenfavorable']}</option>
					<option value="tokenpreferential">{$transaction_category['tokenpreferential']}</option>
					<option value="tokenpay">{$transaction_category['tokenpay']}</option>
				</select>
			</div>
			<div class="col-sm-3">
				<div class="form-check">
					<input class="form-check-input" type="checkbox" >
					<label class="form-check-label">
						{$tr['Actual deposit']}
					</label>
				</div>
			</div>
		</div>
		<div class="form-group row">
			<label class="col-sm-2 col-form-label">
				前台摘要
				<i class="fas fa-info-circle text-secondary" data-toggle="tooltip" data-placement="bottom" title="显示于前台会员端的交易记录明細"></i>
			</label>
			<div class="col-sm-7">
				<textarea class="form-control" placeholder="可填入摘要说明"></textarea>
			</div>
		</div>
		<div class="form-group row">
			<label class="col-sm-2 col-form-label">{$tr['Remark'] }</label>
			<div class="col-sm-7">
				<textarea class="form-control" placeholder="备注或是说明"></textarea>
			</div>
		</div>
		<div class="form-group row">
			<label class="col-sm-2 col-form-label"><span class="text-danger">*</span>管理员密码</label>
			<div class="col-sm-7">
				<input type="password" class="form-control" placeholder="">
			</div>
		</div>
		<div class="form-group row">
			<div class="col-sm-2"></div>
			<div class="col-sm-7 d-flex">
				<button class="btn btn-success w-75" type="button">转帐</button>
				<button class="btn bg-light border ml-auto text-muted clear_btn" type="button">{$tr['Cancel']}</button>
			</div>
		</div>
	</form>
	</div>
</div>
HTML;

//提出遊戲幣
$member_withdrawalgtoken = <<<HTML
<div class="row my-3">
	<div class="col-12 col-md-8 mx-auto">
		<form>
			<div class="form-group row">
				<div class="alert alert-success w-75" role="alert">
					* 人工游戏币取款（GTOKEN）功能：此为管理员或允许的客服人员进行人工（GTOKEN）提出的功能<br>
					* 此功能无须进行取款稽核，主要提供为管理员或客服进行反水或优惠额度发送的游戏币回收操作。 <br>
					* 游戏币领出就是会员的游戏币转帐到 gtokencashier 帐号。<br>
					* 如果是「实际存取」，则表示此为真实存取行为，提供管理员注记使用。
				</div>
			</div>
			<div class="form-group row">
				<label class="col-sm-2 col-form-label"><span class="text-danger">*</span>游戏币来源帐号</label>
				<div class="col-sm-7">
					<input type="text" class="form-control" placeholder="gpkshiuan" disabled="">
				</div>
				<div class="col-sm-3">
					<p class="mb-0">{$tr['Balance']} CNY2,466.00</p>
				</div>
			</div>	
			<div class="form-group row">
				<label class="col-sm-2 col-form-label"><span class="text-danger">*</span>取款金额</label>
				<div class="col-sm-7">
					<input type="number" class="form-control" placeholder="">
				</div>
			</div>	
			<div class="form-group row">
				<label class="col-sm-2 col-form-label"><span class="text-danger">*</span>{$tr['type']}</label>
				<div class="col-sm-7">
					<select class="form-control mb-2">
						<option value="tokenrecycling">游戏币回收</option>
						<option value="tokenadministrationfees">游戏币取款行政费</option>
					</select>		
					<p class="mb-0 text-secondary"><span class="text-danger">*</span> 代币回收，只能管理员执行，设计为回收错误无须稽核的优惠代币。</p>
					<p class="mb-0 text-secondary"><span class="text-danger">*</span> 代币取款行政费费用，设计为人工收取行政稽核不通过费用。</p>			
				</div>
				<div class="col-sm-3">
					<div class="form-check">
						<input class="form-check-input" type="checkbox" >
						<label class="form-check-label">
							{$tr['Actual deposit']}
						</label>						
					</div>
				</div>
			</div>
			<div class="form-group row">
				<label class="col-sm-2 col-form-label">
					前台摘要
					<i class="fas fa-info-circle text-secondary" data-toggle="tooltip" data-placement="bottom" title="显示于前台会员端的交易记录明細摘要"></i>
				</label>
				<div class="col-sm-7">
					<textarea class="form-control" placeholder="可填入摘要说明"></textarea>
				</div>
			</div>
			<div class="form-group row">
				<label class="col-sm-2 col-form-label">{$tr['Remark'] }</label>
				<div class="col-sm-7">
					<textarea class="form-control" placeholder="备注或是说明"></textarea>
				</div>
			</div>
			<div class="form-group row">
				<label class="col-sm-2 col-form-label"><span class="text-danger">*</span>来源帐号的密码</label>
				<div class="col-sm-7">
					<input type="password" class="form-control mb-2" placeholder="">
					<p class="mb-0 text-secondary"><span class="text-danger">*</span>管理员可以输入管理员密码</p>
				</div>
			</div>
			<div class="form-group row">
				<div class="col-sm-2"></div>
				<div class="col-sm-7 d-flex">
					<button class="btn btn-success w-75" type="button">{$tr['withdrawals']}</button>
					<button class="btn bg-light border ml-auto text-muted clear_btn" type="button">{$tr['Cancel']}</button>
				</div>
			</div>
		</form>
	</div>
</div>
HTML;

//存入現金
$member_depositgcash = <<<HTML
<div class="row my-3">
	<div class="col-12 col-md-8 mx-auto">
		<form>
			<div class="form-group row">
				<div class="alert alert-success w-75" role="alert">
				* 人工现金存入功能：此為管理員或允許的客服人員進行人工存入現金的工作。管理員可以轉现金給任何帳戶。<br>
				* 人工现金存入預設是以 gcashcashier 出納帳號，轉帳給指定的帳戶。<br>
				* 一般帳戶之間代理商可以轉现金給下線會員，但會員間不可以互轉。<br>
				</div>
			</div>
			<div class="form-group row">
				<label class="col-sm-2 col-form-label"><span class="text-danger">*</span>现金来源帐号</label>
				<div class="col-sm-7">
					<input type="text" class="form-control" placeholder="gpkshiuan">
				</div>
				<div class="col-sm-3">
					<p class="mb-0">{$tr['Balance']} CNY75,059,259.33</p>
				</div>
			</div>
			<div class="form-group row">
				<label class="col-sm-2 col-form-label"><span class="text-danger">*</span>存入帐号</label>
				<div class="col-sm-7">
					<input type="text" class="form-control" placeholder="">
				</div>
			</div>
			<div class="form-group row">
				<label class="col-sm-2 col-form-label"><span class="text-danger">*</span>存款金额</label>
				<div class="col-sm-7">
					<input type="number" class="form-control" placeholder="">
				</div>
				<div class="col-sm-3">
					<p class="mb-0">可存入余额 $</p>
				</div>
			</div>
			<div class="form-group row">
				<label class="col-sm-2 col-form-label"><span class="text-danger">*</span>{$tr['type']}</label>
				<div class="col-sm-7">
					<select class="form-control mb-2">
						<option value="cashdeposit">现金存款</option>
            <option value="payonlinedeposit">电子支付存款</option>
					</select>		
				</div>
				<div class="col-sm-3">
					<div class="form-check">
						<input class="form-check-input" type="checkbox" >
						<label class="form-check-label">
							{$tr['Actual deposit']}
						</label>						
					</div>
				</div>
			</div>
			<div class="form-group row">
				<label class="col-sm-2 col-form-label">
					前台摘要
					<i class="fas fa-info-circle text-secondary" data-toggle="tooltip" data-placement="bottom" title="显示于前台会员端的交易记录明細摘要"></i>
				</label>
				<div class="col-sm-7">
					<textarea class="form-control" placeholder="可填入摘要说明"></textarea>
				</div>
			</div>
			<div class="form-group row">
				<label class="col-sm-2 col-form-label">{$tr['Remark']}</label>
				<div class="col-sm-7">
					<textarea class="form-control" placeholder="备注或是说明"></textarea>
				</div>
			</div>
			<div class="form-group row">
				<label class="col-sm-2 col-form-label"><span class="text-danger">*</span>{$tr['your pwd']}</label>
				<div class="col-sm-7">
					<input type="password" class="form-control mb-2" placeholder="">
				</div>
			</div>
			<div class="form-group row">
				<div class="col-sm-2"></div>
				<div class="col-sm-7 d-flex">
					<button class="btn btn-success w-75" type="button">转帐</button>
					<button class="btn bg-light border ml-auto text-muted clear_btn" type="button">{$tr['Cancel']}</button>
				</div>
			</div>
		</form>
	</div>
</div>
HTML;

//提出現金
$member_withdrawalgcash = <<<HTML
<div class="row my-3">
	<div class="col-12 col-md-8 mx-auto">
		<form>
			<div class="form-group row">
				<div class="alert alert-success w-75" role="alert">
				* 人工取款 GCASH 功能：此為管理員或允許的客服人員進行人工現金提出的的工作。<br>
				* GCASH 領出現金就是會員的 GCASH 轉帳到 gcashcashier 帳號。<br>
				* 如果是「實際存取」，需要將匯款到取款帳戶的銀行帳號內，如果「非實際存取」則無實際提領行為。
				</div>
			</div>
			<div class="form-group row">
				<label class="col-sm-2 col-form-label"><span class="text-danger">*</span>现金來源帳號</label>
				<div class="col-sm-7">
					<input type="text" class="form-control" placeholder="gpkshiuan" disabled="">
				</div>
				<div class="col-sm-3">
					<p class="mb-0">{$tr['Balance']} CNY0.00</p>
				</div>
			</div>
			<div class="form-group row">
				<label class="col-sm-2 col-form-label"><span class="text-danger">*</span>取款金额</label>
				<div class="col-sm-7">
					<input type="number" class="form-control" placeholder="">
				</div>
			</div>
			<div class="form-group row">
				<label class="col-sm-2 col-form-label"><span class="text-danger">*</span>{$tr['type']}</label>
				<div class="col-sm-7">
					<select class="form-control">
						<option value="cashwithdrawal">現金取款</option>
					</select>
				</div>
				<div class="col-sm-3">
					<div class="form-check">
						<input class="form-check-input" type="checkbox" >
						<label class="form-check-label">
							{$tr['Actual deposit']}
						</label>
					</div>
				</div>
			</div>
			<div class="form-group row">
				<label class="col-sm-2 col-form-label">
					前台摘要
					<i class="fas fa-info-circle text-secondary" data-toggle="tooltip" data-placement="bottom" title="显示于前台会员端的交易记录明細摘要"></i>
				</label>
				<div class="col-sm-7">
					<textarea class="form-control" placeholder="可填入摘要说明"></textarea>
				</div>
			</div>
			<div class="form-group row">
				<label class="col-sm-2 col-form-label">{$tr['Remark']}</label>
				<div class="col-sm-7">
					<textarea class="form-control" placeholder="备注或是说明"></textarea>
				</div>
			</div>
			<div class="form-group row">
				<label class="col-sm-2 col-form-label"><span class="text-danger">*</span>来源帐号的密码</label>
				<div class="col-sm-7">
					<input type="password" class="form-control mb-2" placeholder="">
					<p class="mb-0 text-secondary"><span class="text-danger">*</span>管理员可以输入管理员密码</p>
				</div>
			</div>
			<div class="form-group row">
				<div class="col-sm-2"></div>
				<div class="col-sm-7 d-flex">
					<button class="btn btn-success w-75" type="button">转帐</button>
					<button class="btn bg-light border ml-auto text-muted clear_btn" type="button">{$tr['Cancel']}</button>
				</div>
			</div>						
		</form>		
	</div>
</div>
HTML;


//代理占比設定 表格栏位名称
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
    直属投注
		<button type="button" class="btn btn-sm" style="border: 0px;" title="直属投注计算" data-container="body" data-toggle="popover" data-placement="top" data-content="当直属下线投注，抽成之比例<hr><strong>注</strong>：直属下线被禁用的情境，该禁用帐号的直属下线被视为 的直属下线" data-html="true">
			<span class="glyphicon glyphicon-info-sign"></span>
		</button>
  </th>
  <th colspan="2" class="well text-center">
    非直属投注
		<button type="button" class="btn btn-sm" style="border: 0px;" title="非直属投注计算" data-container="body" data-toggle="popover" data-placement="right" data-content="当该下线代理商的直属会员投注时，抽成之比例" data-html="true">
			<span class="glyphicon glyphicon-info-sign"></span>
		</button>
  </th>
</tr>
HTML;
//代理占比設定
$agents_setting = <<<HTML
<div class="row my-3">
	<div class="col-12 mx-auto">
		<form>
			<div class="form-group row mb-0 d-flex telescopic_btn" id="reward_link">
				<label class="col-sm-12 col-form-label py-3 bg-light border d-flex lign-items-center">
					<h6 class="d-flex font-weight-bold mb-0">
						<span class="glyphicon glyphicon-cog mr-2"></span>
						反水占成设定<span class="text-secondary font-weight-normal ml-2">( 跟随一级代理商 bigagent 一般设定 )</span>
					</h6>	
					<i class="fas fa-angle-up ml-auto"></i>		
				</label>				
			</div>
			<div class="row border border-top-0">
				<div class="col-11 mx-auto py-3" data-text="反水占成设定">
				
					<div class="form-group row">
					<label class="col-sm-2 col-form-label py-2">代理线向下发放</label>
					<div class="col-sm-10 py-3">
						<label>
							<input type="radio" value="1" data-action="downward_deposit" data-type="preferential" data-agentid="17" data-description="代理线持有反水占成" data-value="是" checked="">
							启用
						</label>
						<label>
							<input type="radio" value="0" data-action="downward_deposit" data-type="preferential" data-agentid="17" data-description="代理线持有反水占成" data-value="否">
							停用
						</label>
						<p class="mb-0 text-secondary">此栏位设定为否时，反水保留在一级代理商身上。</p>
					</div>
				</div>
				<div class="form-group row">
					<label class="col-sm-2 col-form-label py-3">修改设置</label>
					<div class="col-sm-7 py-3">
						<div class="input-group form-inline">
							<select class="form-control input-sm" data-action="set_1st_agent_config" data-type="preferential" data-agentid="17" data-description="预定义的一级代理商设置组">
								<option value="">自订</option>
								<option value="1">设置 1</option>
								<option value="2">设置 2</option>
								<option value="3">设置 3</option>
							</select>
							<button class="ml-2 btn btn-success btn-sm"  data-type="preferential" data-agentid="17" disabled="">确认修改</button>
						</div>
						<div class="alert alert-info mt-3 p-2">提供默认的百分比设置，让您不再烦恼！</div>		
						<div class="form-group row">
							<div class="col-4 font-weight-bold">会员自身反水</div>
							<div class="col-8">
								<select class="form-control"data-action="self_ratio" data-type="preferential" data-agentid="17" data-description="会员自身反水">
									<option value="">未设定</option>
									<option selected="selected" value="0">0 %</option>
									<option value="1">1 %</option>
									<option value="2">2 %</option>
									<option value="3">3 %</option>
									<option value="4">100 %</option>
								</select>
							</div>
						</div>
						<div class="form-group row">
							<div class="col-4 pt-1 font-weight-bold">各层代理商保留</div>
							<div class="col-8 d-flex">
								<select class="form-control"data-action="self_ratio" data-type="preferential" data-agentid="17" data-description="代理商最少保留">
									<option value="">未设定</option>
									<option selected="selected" value="0">0 %</option>
									<option value="1">1 %</option>
									<option value="2">2 %</option>
									<option value="3">3 %</option>
									<option value="4">100 %</option>
								</select>
								<span class="input-group-append" style="margin: auto 0.5em">~</span>
								<select class="form-control"data-action="self_ratio" data-type="preferential" data-agentid="17" data-description="代理商最多保留">
									<option value="">未设定</option>
									<option selected="selected" value="0">100 %</option>
									<option value="1">99 %</option>
									<option value="2">98 %</option>
									<option value="3">97 %</option>
									<option value="4">1 %</option>
								</select>
							</div>
						</div>
						<div class="form-group row">
							<div class="col-4 pt-1 font-weight-bold">直属代理商保障</div>
							<div class="col-8">
								<select class="form-control"data-action="self_ratio" data-type="preferential" data-agentid="17" data-description="会员自身反水">
									<option value="">未设定</option>
									<option selected="selected" value="0">0 %</option>
									<option value="1">1 %</option>
									<option value="2">2 %</option>
									<option value="3">3 %</option>
									<option value="4">100 %</option>
								</select>
							</div>
						</div>
					</div>
				</div>
				<div class="form-group row mb-0">
					<label class="col-sm-2 col-form-label py-2">当前设置</label>
					<div class="col-sm-10 py-2 ">
						<p class="mb-2 text-secondary">当前已套用设置值；对象代理商发展下线后，不允许修改；后台高级帐号可重设整条代理线</p>
						<p class="mb-2">会员自身反水 0 %</p>
						<p class="mb-2">各层代理商保留 0 % ~ 100 %</p>
						<p class="mb-0">直属代理商保障 0 %</p>
					</div>
				</div>

				</div>
			</div>

			<div class="form-group row mt-4 mb-0 d-flex telescopic_btn" id="commission_link">
				<label class="col-sm-12 col-form-label py-3 bg-light border d-flex">
					<h6 class="d-flex font-weight-bold mb-0">
						<span class="glyphicon glyphicon-cog mr-2"></span>
						佣金占成比例<span class="text-secondary font-weight-normal ml-2">( 跟随一级代理商 bigagent 一般设定 )</span>
					</h6>	
					<i class="fas fa-angle-up ml-auto"></i>
				</label>
			</div>
			<div class="row border border-top-0">
				<div class="col-11 mx-auto py-3" data-text="佣金占成比例">

					<div class="form-group row">
						<label class="col-sm-2 col-form-label py-2">代理线向下发放</label>
						<div class="col-sm-10 py-3">
							<label>
								<input type="radio" value="1" data-action="downward_deposit" data-type="preferential" data-agentid="17" data-description="代理线持有反水占成" data-value="是" checked="">
								启用
							</label>
							<label>
								<input type="radio" value="0" data-action="downward_deposit" data-type="preferential" data-agentid="17" data-description="代理线持有反水占成" data-value="否">
								停用
							</label>
							<p class="mb-0 text-secondary">此栏位设定为否时，佣金保留在一级代理商身上。</p>
						</div>
					</div>
					
					<div class="form-group row">
					<label class="col-sm-2 col-form-label py-3">修改设置</label>
					<div class="col-sm-7 py-3">
						<div class="input-group form-inline">
							<select class="form-control input-sm" data-action="set_1st_agent_config" data-type="preferential" data-agentid="17" data-description="预定义的一级代理商设置组">
								<option value="">自订</option>
								<option value="1">设置 1</option>
								<option value="2">设置 2</option>
								<option value="3">设置 3</option>
							</select>
							<button class="ml-2 btn btn-success btn-sm"  data-type="preferential" data-agentid="17" disabled="">确认修改</button>
						</div>
						<div class="alert alert-info mt-3 p-2">提供默认的百分比设置，让您不再烦恼！</div>
						<div class="form-group row">
							<div class="col-4 font-weight-bold">各层代理商保留</div>
							<div class="col-8 d-flex">
								<select class="form-control"data-action="self_ratio" data-type="preferential" data-agentid="17" data-description="代理商最少保留">
									<option value="">未设定</option>
									<option selected="selected" value="0">0 %</option>
									<option value="1">1 %</option>
									<option value="2">2 %</option>
									<option value="3">3 %</option>
									<option value="4">100 %</option>
								</select>
								<span class="input-group-append" style="margin: auto 0.5em">~</span>
								<select class="form-control"data-action="self_ratio" data-type="preferential" data-agentid="17" data-description="代理商最多保留">
									<option value="">未设定</option>
									<option selected="selected" value="0">100 %</option>
									<option value="1">99 %</option>
									<option value="2">98 %</option>
									<option value="3">97 %</option>
									<option value="4">1 %</option>
								</select>
							</div>
						</div>
						<div class="form-group row">
							<div class="col-4 pt-1 font-weight-bold">直属代理商保障</div>
							<div class="col-8">
								<select class="form-control"data-action="self_ratio" data-type="preferential" data-agentid="17" data-description="直属代理商保障">
									<option value="">未设定</option>
									<option selected="selected" value="0">0 %</option>
									<option value="1">1 %</option>
									<option value="2">2 %</option>
									<option value="3">3 %</option>
									<option value="4">100 %</option>
								</select>
							</div>
						</div>
					</div>
				</div>

				<div class="form-group row mb-0">
					<label class="col-sm-2 col-form-label py-2">当前设置</label>
					<div class="col-sm-10 py-2">
						<p class="mb-2 text-secondary">当前已套用设置值；对象代理商发展下线后，不允许修改；后台高级帐号可重设整条代理线</p>
						<p class="mb-2">会员自身反水 0 %</p>
						<p class="mb-2">各层代理商保留 0 % ~ 100 %</p>
						<p class="mb-0">直属代理商保障 0 %</p>
					</div>
				</div>
				</div>
			</div>	

			<div class="form-group row mt-4 mb-0 d-flex telescopic_btn">
				<label class="col-sm-12 col-form-label py-3 bg-light border d-flex">
					<h6 class="d-flex font-weight-bold mb-0">
						<span class="glyphicon glyphicon-cog mr-2"></span>
						代理商组织转帐及占成设定
					</h6>	
					<i class="fas fa-angle-up ml-auto"></i>
				</label>
			</div>
			<div class="row border border-top-0">
				<div class="col-11 mx-auto py-3" data-text="代理商组织转帐及占成设定">
					<div class="form-group row">
						<div class="col-12 font-weight-bold mb-2 d-flex align-items-center">
							<i class="fas fa-exclamation-circle text-secondary mr-1" data-container="body" data-toggle="popover" data-placement="top" data-content="  当 gpkshiuan 的直属下线（假名小华）投注时，小华扮演玩家的角色，gpkshiuan 可获得小华 0 % 的投注反水抽成；<hr>
							为了保障较下游的代理商，若投注抽成过少的情况，则会补足到末代保障的比例；多馀的部份则返回一级代理商 bigagent 身上。<hr>
							当 gpkshiuan 的直属下线（小华）为代理商时，小华拥有 gpkshiuan 所设定代理反水占成比例可供支配<small>（即小华可获得直属下线的投注反水抽成，比例足够的情形下可对下线配比）</small>" data-html="true" data-original-title="反水占成比例"></i>
							{$tr['proportioned of reward']}
							<a href="#reward_link" class="btn btn-outline-secondary btn-xs ml-2 reward_link">設定</a>					
						</div>
						<div class="col-sm-12 mt-2">
							<a class="btn btn-default btn-sm disabled" href="/begpk2dev/agents_setting.php?a=1" data-toggle="tooltip" data-html="true" title="">公司 </a>
							<span class="glyphicon glyphicon-arrow-right" aria-hidden="true"></span>
							<a class="btn btn-default btn-sm disabled" href="/begpk2dev/agents_setting.php?a=" data-toggle="tooltip" data-html="true" title="没有可分配的比例">会员自身 (0%)</a>
							<span class="glyphicon glyphicon-arrow-right" aria-hidden="true"></span>
							<a class="btn btn-info btn-sm " href="/begpk2dev/agents_setting.php?a=17" data-toggle="tooltip" data-html="true" title="一级代理商 bigagent 持有 100 % 的比例，其中 100 % 为设定下线代理商后自身保留，0 %为代理线结馀归还。">bigagent (100%)</a>
							<span class="glyphicon glyphicon-arrow-right" aria-hidden="true"></span>
							<a class="btn btn-primary btn-sm " href="/begpk2dev/agents_setting.php?a=10041" data-toggle="tooltip" data-html="true" title="没有可分配的比例">gpkshiuan (0%)</a>	
						</div>
						<div class="col-sm-12 mt-2">
							代理商 gpkshiuan 从上层 bigagent 获得的反水占成比例：<strong class="page_allocable_preferential">0 %</strong>
						</div>
					</div>
					<div class="form-group row border-top border-bottom py-3">
						<div class="col-sm-2 font-weight-bold mb-2">							
							<i class="fas fa-exclamation-circle text-secondary" data-container="body" data-toggle="popover" data-placement="top" data-content="  当 gpkshiuan 的直属下线（假名小华）投注时，小华扮演玩家的角色，gpkshiuan 可获得小华 0 % 的投注佣金抽成；<hr>
								为了保障较下游的代理商，若投注抽成过少的情况，则会补足到末代保障的比例；多馀的部份则返回一级代理商 bigagent 身上。<hr>
								当 gpkshiuan 的直属下线（小华）为代理商时，小华拥有 gpkshiuan 所设定代理佣金占成比例可供支配<small>（即小华可获得直属下线的投注佣金抽成，比例足够的情形下可对下线配比）</small>" data-html="true" data-original-title="佣金占成比例"></i>
								{$tr['proportioned of commission']}
								<a href="#commission_link" class="btn btn-outline-secondary btn-xs ml-2 commission_link">設定</a>
						</div>
						<div class="col-sm-12">
							<a class="btn btn-default btn-sm disabled" href="/begpk2dev/agents_setting.php?a=1" data-toggle="tooltip" data-html="true" title="">公司 </a>
							<span class="glyphicon glyphicon-arrow-right" aria-hidden="true"></span>
							<a class="btn btn-info btn-sm " href="/begpk2dev/agents_setting.php?a=17" data-toggle="tooltip" data-html="true" title="一级代理商 bigagent 持有 100 % 的比例，其中 100 % 为设定下线代理商后自身保留，0 %为代理线结馀归还。">bigagent (100%)</a>
							<span class="glyphicon glyphicon-arrow-right" aria-hidden="true"></span>
							<a class="btn btn-primary btn-sm " href="/begpk2dev/agents_setting.php?a=10041" data-toggle="tooltip" data-html="true" title="没有可分配的比例">gpkshiuan (0%)</a>
						</div>
						<div class="col-sm-12 mt-2">
							代理商 gpkshiuan 从上层 bigagent 获得的佣金占成比例：<strong class="page_allocable_dividend">0 %</strong>
						</div>
					</div>
					<div class="form-group row mt-5">
						<table id="transaction_list" class="table table-striped" cellspacing="0" width="100%">
							<thead>	
							<tr>
								<th colspan="5" rowspan="2" class="border-top-0 align-bottom pl-0 pb-3">
								<form>
									<label class="mr-2">Search :</label>
									<input type="search" id="search_agents" class="border rounded">
								</form>
								</th>
								<th colspan="2" rowspan="2" class="text-center bg-light align-middle border-top-0">{$tr['agent accounts for setting']}</th>
								<th colspan="4" class="text-center bg-primary border-top-0">{$tr['the actual betting commission']}</th>
							</tr>		
							<tr>
								<th colspan="2" class="text-center bg-success">
									直属投注
									<button type="button" class="btn btn-sm bg-transparent border-0" title="" data-container="body" data-toggle="popover" data-placement="top" data-content="当直属下线投注，gpkshiuan 抽成之比例<hr><strong>注</strong>：直属下线被禁用的情境，该禁用帐号的直属下线被视为 gpkshiuan 的直属下线" data-html="true" data-original-title="直属投注计算"><span class="glyphicon glyphicon-info-sign"></span></button>
								</th>
								<th colspan="2" class="text-center bg-warning">
									非直属投注
									<button type="button" class="btn btn-sm bg-transparent border-0" title="" data-container="body" data-toggle="popover" data-placement="right" data-content="当该下线代理商的直属会员投注时，gpkshiuan 抽成之比例" data-html="true" data-original-title="非直属投注计算"><span class="glyphicon glyphicon-info-sign"></span></button>
								</th>
							</tr>					
							<tr>
								<th>ID</th>
								<th>下线第一代</th>
								<th>{$tr['identity']}</th>
								<th>{$tr['enrollmentdate']}</th>
								<th>{$tr['account status']}</th>
								<th title="{$tr['assign to subordinate']}" data-toggle="tooltip" class="bg-light"> {$tr['reward']}(<0%) </th>
								<th title="{$tr['assign to subordinate']}" data-toggle="tooltip" class="bg-light"> {$tr['commissions']}(<0%) </th>
								<th class="bg-success">{$tr['reward']}</th>
								<th class="bg-success">{$tr['commissions']}</th>
								<th class="bg-warning">{$tr['reward']}</th>
								<th class="bg-warning">{$tr['commissions']}</th>
							</tr>
							</thead>
						</table>
					</div>

				</div>
			</div>

		</form>
	</div>
</div>
HTML;

//load 動畫
$load_animate="<div class='load_datatble_animate'><img src='./ui/loading.gif'></div>";

$indexbody_content = <<<HTML
{$load_animate}
<ul class="nav nav-tabs mt-3" id="memberoverviewTab" role="tablist">
  <li class="nav-item">
    <a class="nav-link active" id="bethistory-tab" data-toggle="tab" href="#bethistory" role="tab" aria-controls="bethistory" aria-selected="true">
        {$tr['Manual deposit GTOKEN']}
    </a>
  </li>
  <li class="nav-item">
    <a class="nav-link" id="transactionrecord-tab" data-toggle="tab" href="#transactionrecord" role="tab" aria-controls="transactionrecord" aria-selected="false">
        {$tr['Manual withdraw GTOKEN']}
    </a>
  </li>
  <li class="nav-item">
    <a class="nav-link" id="loginhistory-tab" data-toggle="tab" href="#loginhistory" role="tab" aria-controls="loginhistory" aria-selected="false">
		 	{$tr['Manual deposit GCASH']}
    </a>
  </li>
  <li class="nav-item">
    <a class="nav-link" id="auditrecord-tab" data-toggle="tab" href="#auditrecord" role="tab" aria-controls="auditrecord" aria-selected="false">
			{$tr['Manual withdraw GCASH']}
    </a>
  </li>
  <li class="nav-item">
    <a class="nav-link" id="agentssetting-tab" data-toggle="tab" href="#agentssetting" role="tab" aria-controls="agentssetting" aria-selected="false">
			{$tr['agent ratio setting']}
    </a>
  </li>
</ul>

<!-- tab內容 -->
<div class="tab-content tab_p_overview" id="overviewoperating">
  <div class="tab-pane fade show active" id="bethistory" role="tabpanel" aria-labelledby="bethistory-tab">  
    <!-- 存入遊戲幣 -->
		{$member_depositgtoken}
  </div>

  <div class="tab-pane fade" id="transactionrecord" role="tabpanel" aria-labelledby="transactionrecord-tab">
    <!-- 提出遊戲幣 -->	
		{$member_withdrawalgtoken}	
  </div>

  <div class="tab-pane fade" id="loginhistory" role="tabpanel" aria-labelledby="loginhistory-tab">
  <!--  存入現金 -->
	 {$member_depositgcash}
  </div>

  <div class="tab-pane fade" id="auditrecord" role="tabpanel" aria-labelledby="auditrecord-tab">
  <!--  提出現金 -->
	{$member_withdrawalgcash}
  </div>

	<div class="tab-pane fade" id="agentssetting" role="tabpanel" aria-labelledby="agentssetting-tab">
  <!--  代理占比設定 -->
	{$agents_setting}
  </div>

</div>
HTML;

$extend_js = <<<HTML
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
	  display:none;
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
			// if has locationhash but click other tab button
			$('#memberoverviewTab').on('click',function(e){
				if( locationhash != '' || locationhash != undefined ){
					var id = e.target.hash;
					window.location.href = "member_overview_operating.php"+locationsearch+id+"_tab";				
				}
			});	

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
					
		var tl_tabke =	$("#transaction_list").DataTable( {
				// "paging":   true,
				// "ordering": true,
				// "info":     true,
				// "order": [[ 1, "asc" ]],
				// "pageLength": 30,
				// 假資料
				"dom": '<ftlip>',
				"ajax": "https://shiuanlin.jutainet.com/json/historyfour.php",
				"columns": [
					{ "data": "id"},
					{ "data": "account",
						"fnCreatedCell": function (nTd, sData, oData, iRow, iCol) {
							var html = '<a href="member_account.php?a=10041" target="_blank">'+ oData.account +'</a>';
							$(nTd).html(html);
						}
					},
					{ "data": "therole","class": "text-center",
						"fnCreatedCell": function (nTd, sData, oData, iRow, iCol) {
							var data = oData.therole;
							var member_text = "{$tr['member']}";
							var agent_text = "{$tr['Identity Agent']}";
							if ( data == member_text ) {
							var html = '<span class="glyphicon glyphicon-user text-primary" title="'+ member_text +'"></span>';
							}else if ( data == agent_text ) {
							var html = '<span class="glyphicon glyphicon-knight text-primary" title="'+ agent_text +'"></span>';
							}else{
							var html = data;
							}
							$(nTd).html(html);
						}
					},
					{ "data": "enrollmentdate"},
					{ "data": "status"},
					{ "data": null,
						"fnCreatedCell": function (nTd, sData, oData, iRow, iCol) {
							var html = '-';
							$(nTd).html(html);
						}
					},
					{ "data": null,
						"fnCreatedCell": function (nTd, sData, oData, iRow, iCol) {
							var html = '-';
							$(nTd).html(html);
						}
					},
					{ "data": null,
						"fnCreatedCell": function (nTd, sData, oData, iRow, iCol) {
							var html = '-';
							$(nTd).html(html);
						}
					},
					{ "data": null,
						"fnCreatedCell": function (nTd, sData, oData, iRow, iCol) {
							var html = '-';
							$(nTd).html(html);
						}
					},
					{ "data": null,
						"fnCreatedCell": function (nTd, sData, oData, iRow, iCol) {
							var html = '-';
							$(nTd).html(html);
						}
					},
					{ "data": null,
						"fnCreatedCell": function (nTd, sData, oData, iRow, iCol) {
							var html = '-';
							$(nTd).html(html);
						}
					}
				]
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

// 頁面大標題
$tmpl['page_title'] = $menu_breadcrumbs;
// 主要內容 -- title
$tmpl['paneltitle_content'] = 'gpkshiuan 功能操作';
// 主要內容 -- content
$tmpl['panelbody_content'] = $indexbody_content;
// 擴充再 head 的內容 可以是 js or css
$tmpl['extend_head'] = $extend_head;
// 擴充於檔案末端的 Javascript
$tmpl['extend_js'] = $extend_js;
// ----------------------------------------------------------------------------
// 填入內容結束。底下為頁面的樣板。以變數型態塞入變數顯示。
// ----------------------------------------------------------------------------
include "template/member_tml.php";

?>
