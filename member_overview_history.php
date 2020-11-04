<?php
// ----------------------------------------------------------------------------
// Features:	後台--會員總覽 - 歷程記錄
// File Name:	member_overview_history.php
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
  <li class="active">歷程記錄</li>
</ol>';
// ----------------------------------------------------------------------------
$extend_head = '';
//反水類型 篩選工具
$member_filtercategory = <<<HTML
<div class="btn-group">
    <button type="button" class="btn btn-xs btn-outline-primary" id="select_all_checkbox">全选</button>
    <button type="button" class="btn btn-xs btn-outline-primary" id="cancel_select_all_checkbox">清空</button>
  </div>
  <!--MG PT MEGA IG CQ9 GPK2-->
  <div class="btn-group">
    <button type="button" class="btn btn-xs btn-outline-primary btn_cl_casino_cate" name="name_casino_sel" value="IG">
      IG                    
    </button>
    <button type="button" class="btn btn-xs btn-outline-primary btn_cl_casino_cate" name="name_casino_sel" value="CQ9">
      CQ9                    
    </button>
    <button type="button" class="btn btn-xs btn-outline-primary btn_cl_casino_cate" name="name_casino_sel" value="NWG">
      NWG                    
    </button>
    <button type="button" class="btn btn-xs btn-outline-primary btn_cl_casino_cate" name="name_casino_sel" value="PGSOFT">
      PGSOFT                    
    </button>
    <button type="button" class="btn btn-xs btn-outline-primary btn_cl_casino_cate" name="name_casino_sel" value="JDB">
      JDB                    
    </button>
    <button type="button" class="btn btn-xs btn-outline-primary btn_cl_casino_cate" name="name_casino_sel" value="KG">
      KG                    
    </button>
    <button type="button" class="btn btn-xs btn-outline-primary btn_cl_casino_cate" name="name_casino_sel" value="RG">
      RG                    
    </button>
    <button type="button" class="btn btn-xs btn-outline-primary btn_cl_casino_cate" name="name_casino_sel" value="MGPLUS">
      MGPLUS                    
    </button>
    <button type="button" class="btn btn-xs btn-outline-primary btn_cl_casino_cate" name="name_casino_sel" value="AP">
      AP                    
    </button>
    <button type="button" class="btn btn-xs btn-outline-primary btn_cl_casino_cate" name="name_casino_sel" value="VG">
      VG                    
    </button>
    <button type="button" class="btn btn-xs btn-outline-primary btn_cl_casino_cate" name="name_casino_sel" value="GTI">
      GPK2                    
    </button>
    <button type="button" class="btn btn-xs btn-outline-primary btn_cl_casino_cate" name="name_casino_sel" value="THREESING">
      THREESING                    
    </button>
    <button type="button" class="btn btn-xs btn-outline-primary btn_cl_casino_cate" name="name_casino_sel" value="MEGA">
      MEGA                    
    </button>
  </div>
  <!--電子 棋牌 時時彩 六合彩 h5電子 捕魚 体育 真人-->
  <div class="btn-group">
    <button type="button" class="btn btn-xs btn-outline-primary btn_cl_bonus_cate" id="id_bonus_sel_fish" name="name_bonus_sel" value="fish">捕鱼</button>
    <button type="button" class="btn btn-xs btn-outline-primary btn_cl_bonus_cate" id="id_bonus_sel_lotto" name="name_bonus_sel" value="lotto">六合彩</button>
    <button type="button" class="btn btn-xs btn-outline-primary btn_cl_bonus_cate" id="id_bonus_sel_game" name="name_bonus_sel" value="game">游戏</button>
    <button type="button" class="btn btn-xs btn-outline-primary btn_cl_bonus_cate" id="id_bonus_sel_card" name="name_bonus_sel" value="card">棋牌</button>
    <button type="button" class="btn btn-xs btn-outline-primary btn_cl_bonus_cate" id="id_bonus_sel_html5" name="name_bonus_sel" value="html5">H5电子</button>
    <button type="button" class="btn btn-xs btn-outline-primary btn_cl_bonus_cate" id="id_bonus_sel_lottery" name="name_bonus_sel" value="lottery">时时彩</button>
    <button type="button" class="btn btn-xs btn-outline-primary btn_cl_bonus_cate" id="id_bonus_sel_sports" name="name_bonus_sel" value="sports">体育</button>
    <button type="button" class="btn btn-xs btn-outline-primary btn_cl_bonus_cate" id="id_bonus_sel_live" name="name_bonus_sel" value="live">真人</button>
  </div>
  <table class="table table-bordered">
    <tbody>
      <tr>
        <th class="active">
          <label>
            <input type="checkbox" class="IG_in_cl_bonus_list_parent" checked="" onclick="casino_check_all(this,'IG_in_cl_bonus_list')">
            IG
          </label>
      </th>
      <td>
        <div class="row">
        <!--  □真人 □電子 □H5電子 □捕魚 □時時彩 □體育 □棋牌-->
          <div class="col-2">
            <label class="checkbox-inline">
              <input type="checkbox" name="bns" value="IG_lotto" class="IG_in_cl_bonus_list" checked="">
              六合彩
            </label>
          </div>
          <div class="col-2">
            <label class="checkbox-inline">
              <input type="checkbox" name="bns" value="IG_lottery" class="IG_in_cl_bonus_list" checked="">
              时时彩
            </label>
          </div>
        </div>
      </td>
      </tr>
    <!-- CQ9 -->
      <tr>
        <th class="active">
          <label>
            <input type="checkbox" class="CQ9_in_cl_bonus_list_parent" checked="" onclick="casino_check_all(this,'CQ9_in_cl_bonus_list')">
            CQ9                            
          </label>
        </th>
        <td>
          <div class="row">
            <!--  □真人 □電子 □H5電子 □捕魚 □時時彩 □體育 □棋牌-->
            <div class="col-2">
            <label class="checkbox-inline">
              <input type="checkbox" name="bns" value="CQ9_game" class="CQ9_in_cl_bonus_list" checked="">
              游戏                              
            </label>
            </div>
            <div class="col-2">
              <label class="checkbox-inline">
                <input type="checkbox" name="bns" value="CQ9_fish" class="CQ9_in_cl_bonus_list" checked="">
                捕鱼                              
              </label>
            </div>
          </div>
        </td>
      </tr>
      <!-- NWG  -->
      <tr>
        <th class="active">
          <label>
            <input type="checkbox" class="NWG_in_cl_bonus_list_parent" checked="" onclick="casino_check_all(this,'NWG_in_cl_bonus_list')">
            NWG                            
          </label>
        </th>
        <td>
        <div class="row">
          <!--  □真人 □電子 □H5電子 □捕魚 □時時彩 □體育 □棋牌-->
          <div class="col-2">
            <label class="checkbox-inline">
              <input type="checkbox" name="bns" value="NWG_card" class="NWG_in_cl_bonus_list" checked="">
              棋牌                              
            </label>
          </div>
        </div>
        </td>
      </tr>
      <!-- PGS -->
      <tr>
        <th class="active">
          <label>
            <input type="checkbox" class="PGSOFT_in_cl_bonus_list_parent" checked="" onclick="casino_check_all(this,'PGSOFT_in_cl_bonus_list')">
            PGS
          </label>
        </th>
        <td>
          <div class="row">
            <!--  □真人 □電子 □H5電子 □捕魚 □時時彩 □體育 □棋牌-->
            <div class="col-2">
              <label class="checkbox-inline">
                <input type="checkbox" name="bns" value="PGSOFT_card" class="PGSOFT_in_cl_bonus_list" checked="">
                棋牌
              </label>
            </div>
            <div class="col-2">
              <label class="checkbox-inline">
                <input type="checkbox" name="bns" value="PGSOFT_game" class="PGSOFT_in_cl_bonus_list" checked="">
                游戏                              
              </label>
            </div>
            <div class="col-2">
              <label class="checkbox-inline">
                <input type="checkbox" name="bns" value="PGSOFT_html5" class="PGSOFT_in_cl_bonus_list" checked="">
                H5电子                              
              </label>
            </div>
          </div>
        </td>
      </tr>
      <!-- JDB  -->
      <tr>
        <th class="active">
        <label>
          <input type="checkbox" class="JDB_in_cl_bonus_list_parent" checked="" onclick="casino_check_all(this,'JDB_in_cl_bonus_list')">
          JDB
        </label>
        </th>
        <td>
          <div class="row">
          <!--  □真人 □電子 □H5電子 □捕魚 □時時彩 □體育 □棋牌-->
            <div class="col-2">
              <label class="checkbox-inline">
                <input type="checkbox" name="bns" value="JDB_game" class="JDB_in_cl_bonus_list" checked="">
                游戏                              
              </label>
            </div>
            <div class="col-2">
              <label class="checkbox-inline">
                <input type="checkbox" name="bns" value="JDB_lottery" class="JDB_in_cl_bonus_list" checked="">
                时时彩
              </label>
            </div>
            <div class="col-2">
              <label class="checkbox-inline">
                <input type="checkbox" name="bns" value="JDB_fish" class="JDB_in_cl_bonus_list" checked="">
                捕鱼 
              </label>
            </div>
            <div class="col-2">
              <label class="checkbox-inline">
                <input type="checkbox" name="bns" value="JDB_card" class="JDB_in_cl_bonus_list" checked="">
                棋牌                              
              </label>
            </div>
          </div>
        </td>
      </tr>
      <!-- KG -->
      <tr>
        <th class="active">
          <label>
            <input type="checkbox" class="KG_in_cl_bonus_list_parent" checked="" onclick="casino_check_all(this,'KG_in_cl_bonus_list')">
            KG
          </label>
        </th>
        <td>
          <div class="row">
          <!--  □真人 □電子 □H5電子 □捕魚 □時時彩 □體育 □棋牌-->
            <div class="col-2">
              <label class="checkbox-inline">
                <input type="checkbox" name="bns" value="KG_card" class="KG_in_cl_bonus_list" checked="">
                棋牌
              </label>
            </div>
          </div>
        </td>
      </tr>
      <!-- RG -->
      <tr>
        <th class="active">
          <label>
            <input type="checkbox" class="RG_in_cl_bonus_list_parent" checked="" onclick="casino_check_all(this,'RG_in_cl_bonus_list')">
            RG
          </label>
        </th>
        <td>
          <div class="row">
          <!--  □真人 □電子 □H5電子 □捕魚 □時時彩 □體育 □棋牌-->
            <div class="col-2">
              <label class="checkbox-inline">
                <input type="checkbox" name="bns" value="RG_lottery" class="RG_in_cl_bonus_list" checked="">
                时时彩
              </label>
            </div>
          </div>
        </td>
      </tr>
      <!--  AP -->
      <tr>
        <th class="active">
          <label>
            <input type="checkbox" class="AP_in_cl_bonus_list_parent" checked="" onclick="casino_check_all(this,'AP_in_cl_bonus_list')">
            AP
          </label>
        </th>
        <td>
          <div class="row">
          <!--  □真人 □電子 □H5電子 □捕魚 □時時彩 □體育 □棋牌-->
            <div class="col-2">
              <label class="checkbox-inline">
                <input type="checkbox" name="bns" value="AP_card" class="AP_in_cl_bonus_list" checked="">
                棋牌
              </label>
            </div>
          </div>
        </td>
      </tr>
      <tr>
      <th class="active">
        <label>
          <input type="checkbox" class="VG_in_cl_bonus_list_parent" checked="" onclick="casino_check_all(this,'VG_in_cl_bonus_list')">
          VG
        </label>
      </th>
      <td>
        <div class="row">
        <!--  □真人 □電子 □H5電子 □捕魚 □時時彩 □體育 □棋牌-->
        <div class="col-2">
          <label class="checkbox-inline">
            <input type="checkbox" name="bns" value="VG_card" class="VG_in_cl_bonus_list" checked="">
            棋牌                              
          </label>
        </div>
        <div class="col-2">
          <label class="checkbox-inline">
            <input type="checkbox" name="bns" value="VG_game" class="VG_in_cl_bonus_list" checked="">
            游戏                              
          </label>
        </div>
        <div class="col-2">
          <label class="checkbox-inline">
          <input type="checkbox" name="bns" value="VG_html5" class="VG_in_cl_bonus_list" checked="">
            H5电子                              
          </label>
        </div>
        <div class="col-2">
          <label class="checkbox-inline">
            <input type="checkbox" name="bns" value="VG_fish" class="VG_in_cl_bonus_list" checked="">
            捕鱼                              
          </label>
        </div>
        </div>
      </td>
      </tr>
      <tr>
        <th class="active">
          <label>
            <input type="checkbox" class="GTI_in_cl_bonus_list_parent" checked="" onclick="casino_check_all(this,'GTI_in_cl_bonus_list')">
            GPK2                            
          </label>
        </th>
        <td>
          <div class="row">
            <!--  □真人 □電子 □H5電子 □捕魚 □時時彩 □體育 □棋牌-->
            <div class="col-2">
              <label class="checkbox-inline">
              <input type="checkbox" name="bns" value="GTI_game" class="GTI_in_cl_bonus_list" checked="">
                游戏                              
              </label>
            </div>
            <div class="col-2">
              <label class="checkbox-inline">
                <input type="checkbox" name="bns" value="GTI_html5" class="GTI_in_cl_bonus_list" checked="">
                H5电子                              
              </label>
            </div>
          </div>
        </td>
      </tr>
      <tr>
      <th class="active">
        <label>
          <input type="checkbox" class="THREESING_in_cl_bonus_list_parent" checked="" onclick="casino_check_all(this,'THREESING_in_cl_bonus_list')">
          3 Sing Sport                            
        </label>
      </th>
      <td>
        <div class="row">
        <!--  □真人 □電子 □H5電子 □捕魚 □時時彩 □體育 □棋牌-->
          <div class="col-2">
            <label class="checkbox-inline">
              <input type="checkbox" name="bns" value="THREESING_sports" class="THREESING_in_cl_bonus_list" checked="">
              体育                              
            </label>
          </div>
        </div>
      </td>
      </tr>
      <tr>
        <th class="active">
          <label>
            <input type="checkbox" class="MEGA_in_cl_bonus_list_parent" checked="" onclick="casino_check_all(this,'MEGA_in_cl_bonus_list')">
            Mega Live                            
          </label>
        </th>
        <td>
          <div class="row">
            <!--  □真人 □電子 □H5電子 □捕魚 □時時彩 □體育 □棋牌-->
            <div class="col-2">
              <label class="checkbox-inline">
                <input type="checkbox" name="bns" value="MEGA_live" class="MEGA_in_cl_bonus_list" checked="">
                真人                              
              </label>
            </div>
            <div class="col-2">
              <label class="checkbox-inline">
                <input type="checkbox" name="bns" value="MEGA_game" class="MEGA_in_cl_bonus_list" checked="">
                游戏                              
              </label>
            </div>
            <div class="col-2">
              <label class="checkbox-inline">
                <input type="checkbox" name="bns" value="MEGA_lottery" class="MEGA_in_cl_bonus_list" checked="">
                时时彩                              
              </label>
            </div>
            <div class="col-2">
              <label class="checkbox-inline">
                <input type="checkbox" name="bns" value="MEGA_sports" class="MEGA_in_cl_bonus_list" checked="">
                体育                              
              </label>
            </div>
            <div class="col-2">
              <label class="checkbox-inline">
                <input type="checkbox" name="bns" value="MEGA_fish" class="MEGA_in_cl_bonus_list" checked="">
                捕鱼                              
              </label>
            </div>
            <div class="col-2">
              <label class="checkbox-inline">
                <input type="checkbox" name="bns" value="MEGA_card" class="MEGA_in_cl_bonus_list" checked="">
                棋牌                              
              </label>
            </div>
          </div>
        </td>
      </tr>
    </tbody>
  </table>
HTML;
$modal_html = <<<HTML
  <!-- Modal -->
  <div class="modal fade" id="exampleModal" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="exampleModalLabel"></h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <!-- 一般內容 -->
      <div class="modal-body body_content"></div>
      <!-- 反水類型篩選工具 -->
      <div class="modal-body body_filtercategory d-none">{$member_filtercategory}</div>
      <div class="modal-footer">
      <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>
HTML;
// 投注紀錄 搜尋功能
$member_betlog_filtercategory = <<<HTML
<form class="form-inline search_box">

<div class="row mb-4">
    <div class="col-12"><p class="font-weight-bold">注单号码</p></div>
    <div class="col-12">
      <div class="input-group">
        <input type="text" class="form-control" placeholder="注单号码">
      </div>
    </div>
  </div>

  <div class="row mb-4">
    <div class="col-12"><p class="font-weight-bold">投注時間</p></div>
    <div class="col-12">
      <div class="input-group">
        <div class=" input-group">
          <span class="input-group-addon">开始时间</span>
          <input type="text" class="form-control" placeholder="开始时间"  value="">
        </div>
        <div class=" input-group">
          <span class="input-group-addon">结束时间</span>
          <input type="text" class="form-control" placeholder="结束时间"  value="">
        </div>

        <div class="btn-group btn-group-sm mr-1 my-1" role="group">
          <button type="button" class="btn btn-secondary btn-sm" onclick="settimerange('2020-04-07 00:00', getnowtime())">本日</button>
          <button type="button" class="btn btn-secondary btn-sm" onclick="settimerange('2020-04-06 00:00', '2020-04-06 23:59' );">昨日</button>
        </div>

        <div class="btn-group btn-group-sm mr-1 my-1" role="group">
          <button type="button" class="btn btn-secondary btn-sm" onclick="settimerange('2020-04-05 00:00', getnowtime());">本周</button>
          <button type="button" class="btn btn-secondary btn-sm" onclick="settimerange('2020-04-01 00:00',getnowtime());">本月</button>
          <button type="button" class="btn btn-secondary btn-sm" onclick="settimerange('2020-03-01 00:00','2020-03-31 23:59');">上个月</button>
        </div>

      </div>    
    </div>
  </div>

  <div class="row mb-4">
    <div class="col-12"><p class="font-weight-bold">派彩時間</p></div>
    <div class="col-12">
      <div class="input-group">
        <div class=" input-group">
          <span class="input-group-addon">开始时间</span>
          <input type="text" class="form-control" placeholder="开始时间"  value="">
        </div>
        <div class="input-group">
          <span class="input-group-addon">结束时间</span>
          <input type="text" class="form-control" placeholder="结束时间"  value="">
        </div>

        <div class="btn-group btn-group-sm mr-1 my-1" role="group">
          <button type="button" class="btn btn-secondary btn-sm" onclick="set_profit_range('2020-04-07 00:00', getnowtime())">本日</button>
          <button type="button" class="btn btn-secondary btn-sm" onclick="set_profit_range('2020-04-06 00:00', '2020-04-06 23:59' );">昨日</button>
        </div>

        <div class="btn-group btn-group-sm mr-1 my-1" role="group">
          <button type="button" class="btn btn-secondary btn-sm" onclick="set_profit_range('2020-04-05 00:00', getnowtime());">本周</button>
          <button type="button" class="btn btn-secondary btn-sm" onclick="set_profit_range('2020-04-01 00:00',getnowtime());">本月</button>
          <button type="button" class="btn btn-secondary btn-sm" onclick="set_profit_range('2020-03-01 00:00','2020-03-31 23:59');">上个月</button>
        </div>

      </div>    
    </div>
  </div>

  <div class="row mb-4">
    <div class="col-12"><p class="font-weight-bold">反水类型</p></div>
    <div class="col-12">
        <div class="input-group">
        <div class="form-control-plaintext">
          <span id="casino_category_preview_area">(全选)</span>
          <button type="button" class="btn btn-primary btn-xs ml-1" data-tid="filtercategory" data-toggle="modal" data-target="#exampleModal" data-whatever="选取游戏类型
    ">
            选择
          </button>
        </div>
      </div>        
    </div>
  </div>

  <div class="row mb-4">
    <div class="col-12"><p class="font-weight-bold">游戏名称</p></div>
    <div class="col-12">
      <div class="input-group">
      <input type="text" class="form-control" placeholder="游戏名称">
      </div>
    </div>
  </div>

  <div class="row mb-4">
    <div class="col-12"><p class="font-weight-bold">有效投注</p></div>
    <div class="col-12">
      <div class="input-group">
          <div class=" input-group">
            <input type="text" class="form-control" placeholder="上限"  value="">
            <div class="input-group-append">
			        <span class="input-group-text" id="basic-addon1">~</span>
		        </div>
            <input type="text" class="form-control" placeholder="下限"  value="">
          </div>
        </div>
    </div>
  </div>

  <div class="row mb-4">
    <div class="col-12"><p class="font-weight-bold">派彩</p></div>
    <div class="col-12">
      <div class="input-group">
          <div class=" input-group">
            <input type="text" class="form-control" placeholder="上限"  value="">
            <div class="input-group-append">
			        <span class="input-group-text" id="basic-addon1">~</span>
		        </div>
            <input type="text" class="form-control" placeholder="下限"  value="">
          </div>
        </div>
    </div>
  </div>

  <div class="w-100 border"><h6 class="betlog_h6 text-center">注单状态</h6></div>
  <div class="w-100 border border-top-0 mb-4">
    <div class="ck-button border-right">
      <label>
        <input type="checkbox" id="status_sel_0" name="status_sel" value="0">
        <span class="status_sel_0">未派彩</span>
      </label>
    </div>
    <div class="ck-button border-right">
      <label>
        <input type="checkbox" id="status_sel_1" name="status_sel" value="1">
        <span class="status_sel_1">已派彩</span>
      </label>
    </div>
      <div class="ck-button">
        <label>
          <input type="checkbox" id="status_sel_2" name="status_sel" value="2">
          <span class="status_sel_2">修改過</span>
        </label>
      </div>
  </div>

  <div class="w-100 border-top pt-3 d-flex">
    <button type="button" class="btn btn-success d-inline-block w-75">{$tr['Inquiry']}</button>
    <button type="button" class="btn bg-light d-inline-block ml-auto border text-muted">清除</button>
  </div>
    
</form>
HTML;
// 投注紀錄
$member_betlog = <<<HTML
<div class="row mb-3">
  <div class="col-12">
      <div class="border d-flex">
        <div class="sum_th border-right">总投注笔数</div>
        <div class="sum_th border-right">总投注金额</div>
        <div class="sum_th">总损益结果</div>
      </div>
      <div class="border border-top-0 d-flex">
        <div class="sum_td border-right">0</div>
        <div class="sum_td border-right">$0.00</div>
        <div class="sum_td">$0.00</div>
      </div>
  </div>
</div>

<table id="member_betlog" class="member_g_information display compact dataTable" style="width:100%;">
  <thead>
    <tr>
      <th>注单号码</th>
      <th>投注时间(EDT)</th>
      <th>派彩时间(EDT)</th>
      <th>游戏名称</th>
      <th>游戏类型</th>
      <th>有效投注</th>
      <th>派彩</th>
      <th>娱乐城</th>
      <th>注单状态</th>
      <th>詳細</th>
    </tr>
  </thead>
</table>
HTML;

// 交易紀錄 篩選功能
$transaction_query_filtercategory = <<<HTML
<form class="form-inline">

<div class="row mb-4">
    <div class="col-12"><p class="font-weight-bold">交易单号</p></div>
    <div class="col-12">
      <div class="input-group">
        <input type="text" class="form-control" placeholder="交易单号">
      </div>
    </div>
  </div>

  <div class="row mb-4">
    <div class="col-12"><p class="font-weight-bold">交易時間</p></div>
    <div class="col-12">
      <div class="input-group">
        <div class=" input-group">
          <span class="input-group-addon">开始时间</span>
          <input type="text" class="form-control" placeholder="开始时间"  value="">
        </div>
        <div class=" input-group">
          <span class="input-group-addon">结束时间</span>
          <input type="text" class="form-control" placeholder="结束时间"  value="">
        </div>
      </div>    
    </div>
  </div>

  <div class="row mb-4">
    <div class="col-12"><p class="font-weight-bold">存款金额</p></div>
    <div class="col-12">
      <div class="input-group">
          <div class=" input-group">
            <input type="text" class="form-control" placeholder="上限"  value="">
            <div class="input-group-append">
			        <span class="input-group-text" id="basic-addon1">~</span>
		        </div>
            <input type="text" class="form-control" placeholder="下限"  value="">
          </div>
        </div>
    </div>
  </div>

  <div class="row mb-4">
    <div class="col-12"><p class="font-weight-bold">取款金额</p></div>
    <div class="col-12">
      <div class="input-group">
          <div class=" input-group">
            <input type="text" class="form-control" placeholder="上限"  value="">
            <div class="input-group-append">
			        <span class="input-group-text" id="basic-addon1">~</span>
		        </div>
            <input type="text" class="form-control" placeholder="下限"  value="">
          </div>
        </div>
    </div>
  </div>

  <div class="row mb-4">
    <div class="col-12"><p class="font-weight-bold">实际存取</p></div>
    <div class="col-12">
      <select class="form-control w-100">
        <option value="">請選擇</option>
        <option value="1">启用</option>
        <option value="0">停用</option>
      </select>
    </div>
  </div>

  <div class="row mb-4">
    <div class="col-12"><p class="font-weight-bold">类型</p></div>
    <div class="col-12">
      <div class="form-check d-inline-flex mr-3">
        <input type="checkbox" class="form-check-input" name="transactionType" id="manualDeposit" value="manualDeposit" checked="">
        <label class="d-inline-block" for="manualDeposit">人工存款</label>
      </div>      
      <div class="form-check d-inline-flex mr-3">        
          <input type="checkbox"  class="form-check-input" name="transactionType" id="manualWithdrawal" value="manualWithdrawal" checked="">
          <label class="form-check-label" for="manualWithdrawal">人工取款</label>
      </div>
      <div class="form-check d-inline-flex mr-3">
        <input type="checkbox" class="form-check-input" name="transactionType" id="onlineDeposit" value="onlineDeposit" checked="">
        <label class="form-check-label" for="onlineDeposit">线上存款</label>
      </div>  

      <div class="form-check d-inline-flex mr-3">
        <input type="checkbox" class="form-check-input" name="transactionType" id="onlineWithdrawals" value="onlineWithdrawals" checked="">
        <label class="form-check-label" for="onlineWithdrawals">线上取款</label>
      </div> 

      <div class="form-check d-inline-flex mr-3">
        <input type="checkbox" class="form-check-input" name="transactionType" id="companyDeposits" value="companyDeposits" checked="">
        <label class="form-check-label" for="companyDeposits">公司存款</label>
      </div>

      <div class="form-check d-inline-flex mr-3">
        <input type="checkbox" class="form-check-input" name="transactionType" id="agencyCommission" value="agencyCommission" checked="">
        <label class="form-check-label" for="agencyCommission">代理佣金</label>
      </div>

      <div class="form-check d-inline-flex mr-3">
        <input type="checkbox" class="form-check-input" name="transactionType" id="agencyTransfer" value="agencyTransfer" checked="">
        <label class="form-check-label" for="agencyTransfer">现金转帐</label>
      </div>

      <div class="form-check d-inline-flex mr-3">
        <input type="checkbox" class="form-check-input" name="transactionType" id="walletTransfer" value="walletTransfer" checked="">
        <label class="form-check-label" for="walletTransfer">钱包转帐</label>
      </div>

      <div class="form-check d-inline-flex mr-3">
        <input type="checkbox" class="form-check-input" name="transactionType" id="promotions" value="promotions" checked="">
        <label class="form-check-label" for="promotions">优惠活动</label>
      </div>

      <div class="form-check d-inline-flex mr-3">
        <input type="checkbox" class="form-check-input" name="transactionType" id="payout" value="payout" checked="">
        <label class="form-check-label" for="payout">派彩</label>
      </div>

      <div class="form-check d-inline-flex mr-3">
        <input type="checkbox" class="form-check-input" name="transactionType" id="bouns" value="bouns" checked="">
        <label class="form-check-label" for="bouns">反水</label>
      </div>

      <div class="form-check d-inline-flex mr-3">
        <input type="checkbox" class="form-check-input" name="transactionType" id="other" value="other" checked="">
        <label class="form-check-label" for="other">其它</label>
      </div>

      <div class="form-check d-inline-flex mr-3">
        <input type="checkbox" class="form-check-input" name="transactionType" id="withdrawalAdministrationFee" value="withdrawalAdministrationFee" checked="">
        <label class="form-check-label" for="withdrawalAdministrationFee">取款行政费</label>
      </div>     
    </div>
  </div>


  <div class="w-100 border"><h6 class="betlog_h6 text-center">选择钱包</h6></div>
  <div class="w-100 border border-top-0 mb-4">
    <div class="ck-button money border-right">
      <label>
        <input type="checkbox"  name="status_sel" value="0">
        <span class="">现金</span>
      </label>
    </div>
    <div class="ck-button money">
      <label>
        <input type="checkbox"  name="status_sel" value="1">
        <span class="">游戏币</span>
      </label>
    </div>
  </div>

  <div class="w-100 border-top pt-3 d-flex">
    <button type="button" class="btn btn-success d-inline-block w-75">{$tr['Inquiry']}</button>
    <button type="button" class="btn bg-light d-inline-block ml-auto border text-muted">清除</button>
  </div> 

  
</form>
HTML;
//交易紀錄
$transaction_query = <<<HTML
<div class="row mb-3">
  <div class="col-12">
      <div class="border d-flex">
        <div class="sum_th border-right">总额</div>
        <div class="sum_th border-right">存入</div>
        <div class="sum_th">提出</div>
      </div>
      <div class="border border-top-0 d-flex">
        <div class="sum_td border-right">$10,828.53</div>
        <div class="sum_td border-right">$1,465,638.74</div>
        <div class="sum_td">$1,454,810.21</div>
      </div>
  </div>
</div>

<table id="transaction_query" class="member_g_information display compact dataTable" style="width:100%;">
  <thead>
    <tr>
      <th>序號</th>
      <th>時間</th>
      <th>類別</th>
      <th>存入</th>
      <th>提出</th>
      <th>派彩</th>
      <th>遊戲幣餘額</th>
      <th>現金餘額</th>
      <th>詳細</th>
    </tr>
  </thead>
</table>
HTML;

// 登入紀錄查詢功能
$member_log_filtercategory = <<<HTML
<form class="form-inline">
  <div class="row mb-4">
      <div class="col-12"><p class="font-weight-bold">發生時間</p></div>
      <div class="col-12">
        <div class="input-group">
          <div class=" input-group">
            <span class="input-group-addon">开始时间</span>
            <input type="text" class="form-control" placeholder="开始时间"  value="">
          </div>
          <div class=" input-group">
            <span class="input-group-addon">结束时间</span>
            <input type="text" class="form-control" placeholder="结束时间"  value="">
          </div>
        </div>    
      </div>
    </div>

    <div class="row mb-4">
    <div class="col-12"><p class="font-weight-bold">查询IP</p></div>
    <div class="col-12">
      <div class="input-group">
        <input type="text" class="form-control" placeholder="ex:192.168.100.1">
      </div>
    </div>
  </div>

  <div class="row mb-4">
    <div class="col-12"><p class="font-weight-bold">指纹</p></div>
    <div class="col-12">
      <div class="input-group">
        <input type="text" class="form-control" placeholder="ex:192.168.100.1">
      </div>
    </div>
  </div>

  <div class="form-check d-inline-flex mb-4">
    <input type="checkbox" class="form-check-input" name="transactionType"  value="">
    <label class="form-check-label" for="">查询最近一次登入纪录</label>
  </div>

  <div class="w-100 border-top pt-3 d-flex">
    <button type="button" class="btn btn-success d-inline-block w-75">{$tr['Inquiry']}</button>
    <button type="button" class="btn bg-light d-inline-block ml-auto border text-muted">清除</button>
  </div>

</form>
HTML;

//登入紀錄
$member_log = <<<HTML
<table id="member_log" class="member_g_information display compact dataTable" style="width:100%;">
  <thead>
    <tr>
      <th>序號</th>
      <th>發生時間</th>
      <th>來源IP</th>
      <th>IP區域</th>
      <th>詳細</th>
    </tr>
  </thead>
</table>
HTML;

//稽核紀錄 篩選功能
$auditrecord_filtercategory = <<<HTML
<!-- 不確定是否要使用 -->
<form class="form-inline">
<div class="row mb-4">
  <div class="col-12"><p class="font-weight-bold">单号</p></div>
  <div class="col-12">
    <div class="input-group">
      <input type="text" class="form-control" placeholder="单号">
    </div>
  </div>
</div>

<div class="row mb-4">
    <div class="col-12"><p class="font-weight-bold">稽核時間</p></div>
    <div class="col-12">
      <div class="input-group">
        <div class=" input-group">
          <span class="input-group-addon">开始时间</span>
          <input type="text" class="form-control" placeholder="开始时间"  value="">
        </div>
        <div class=" input-group">
          <span class="input-group-addon">结束时间</span>
          <input type="text" class="form-control" placeholder="结束时间"  value="">
        </div>
      </div>    
    </div>
  </div>

  <div class="row mb-4">
    <div class="col-12"><p class="font-weight-bold">存款金額</p></div>
    <div class="col-12">
      <div class="input-group">
          <div class=" input-group">
            <input type="text" class="form-control" placeholder="上限"  value="">
            <div class="input-group-append">
			        <span class="input-group-text" id="basic-addon1">~</span>
		        </div>
            <input type="text" class="form-control" placeholder="下限"  value="">
          </div>
        </div>
    </div>
  </div>

  <div class="row mb-4">
    <div class="col-12"><p class="font-weight-bold">稽核方式</p></div>
    <div class="col-12">
      <select class="form-control w-100">
        <option value="">請選擇</option>
        <option value="1">存款稽核</option>
      </select>
    </div>
  </div>

  <div class="row mb-4">
    <div class="col-12"><p class="font-weight-bold">稽核金額</p></div>
    <div class="col-12">
      <div class="input-group">
          <div class=" input-group">
            <input type="text" class="form-control" placeholder="上限"  value="">
            <div class="input-group-append">
			        <span class="input-group-text" id="basic-addon1">~</span>
		        </div>
            <input type="text" class="form-control" placeholder="下限"  value="">
          </div>
        </div>
    </div>
  </div>

  <div class="w-100 border-top pt-3 d-flex">
    <button type="button" class="btn btn-success d-inline-block w-75">{$tr['Inquiry']}</button>
    <button type="button" class="btn bg-light d-inline-block ml-auto border text-muted">清除</button>
  </div> 
</form>
HTML;

//稽核紀錄
$token_auditorial = <<<HTML
<div class="row mb-3">
  <div class="col-12">
      <div class="border d-flex">
        <div class="sum_th border-right">最后取款时间</div>
        <div class="sum_th border-right">最后取款金额</div>
        <div class="sum_th">最后取款余额</div>
      </div>
      <div class="border border-top-0 d-flex">
        <div class="sum_td border-right">$10,828.53</div>
        <div class="sum_td border-right">$1,465,638.74</div>
        <div class="sum_td">$1,454,810.21</div>
      </div>
  </div>
</div>
<table id="token_auditorial" class="member_g_information display compact dataTable w-100">
  <thead>
    <tr>
      <th>单号</th>
      <th>时间</th>
      <th>存款金额</th>
      <th>稽核方式</th>
      <th>存款后投注额 / 目标打码量</th>
      <th>稽核金额</th>
    </tr>
  </thead>
</table>
HTML;

//左方選單樣式
$panelbody_filtercategory = <<<HTML
<div id="bethistory_filter" class="filtercategory" data-text="投注紀錄">{$member_betlog_filtercategory}</div>
<div id="transactionrecord_filter" class="filtercategory collapse" data-text="交易紀錄">{$transaction_query_filtercategory}</div>
<div id="loginhistory_filter" class="filtercategory collapse" data-text="登入紀錄">{$member_log_filtercategory}</div>
<div id="auditrecord_filter" class="filtercategory collapse" data-text="稽核紀錄">{$auditrecord_filtercategory}</div>
HTML;

//匯出按鈕
$panelbody_excel = <<<HTML
<a href="#" id="bethistory_excel" data-text="投注紀錄" class="ml-auto btn btn-success btn-sm excel">汇出Excel</a>
<a href="#" id="transactionrecord_excel" data-text="交易紀錄" class="ml-auto btn btn-success btn-sm collapse excel">汇出Excel</a>
<a href="#" id="loginhistory_excel" data-text="登入紀錄" class="ml-auto btn btn-success btn-sm collapse excel">汇出Excel</a>
<a href="#" id="auditrecord_excel" data-text="稽核紀錄" class="ml-auto btn btn-success btn-sm collapse excel">汇出Excel</a>
HTML;

$indexbody_content = <<<HTML
<ul class="nav nav-tabs" id="myTab" role="tablist">
  <li class="nav-item">
    <a class="nav-link active" id="bethistory-tab" data-name="投注紀錄" data-toggle="tab" href="#bethistory" role="tab" aria-controls="bethistory" aria-selected="true">
      投注紀錄
    </a>
  </li>
  <li class="nav-item">
    <a class="nav-link" id="transactionrecord-tab" data-name="交易紀錄" data-toggle="tab" href="#transactionrecord" role="tab" aria-controls="transactionrecord" aria-selected="false">
      交易紀錄
    </a>
  </li>
  <li class="nav-item">
    <a class="nav-link" id="loginhistory-tab" data-name="登入紀錄" data-toggle="tab" href="#loginhistory" role="tab" aria-controls="loginhistory" aria-selected="false">
      登入紀錄
    </a>
  </li>
  <li class="nav-item">
    <a class="nav-link" id="auditrecord-tab" data-toggle="tab" href="#auditrecord" role="tab" aria-controls="auditrecord" aria-selected="false">
      稽核記錄
    </a>
  </li>
</ul>

<!-- tab內容 -->
<div class="tab-content border border-top-0" id="overviewhistory">
  <div class="tab-pane fade show active" id="bethistory" role="tabpanel" aria-labelledby="bethistory-tab">  
    <!-- 投注紀錄 -->
    {$member_betlog}
  </div>

  <div class="tab-pane fade" id="transactionrecord" role="tabpanel" aria-labelledby="transactionrecord-tab">
    <!-- 交易紀錄 -->
    $transaction_query}
  </div>

  <div class="tab-pane fade" id="loginhistory" role="tabpanel" aria-labelledby="loginhistory-tab">
  <!--  登入紀錄 -->
   {$member_log}
  </div>

  <div class="tab-pane fade" id="auditrecord" role="tabpanel" aria-labelledby="auditrecord-tab">
  <!--  稽核記錄 -->
   {$token_auditorial}
  </div>
</div>
{$modal_html}
HTML;
$extend_js = <<<HTML
<link rel="stylesheet" type="text/css" href="./in/datatables/css/jquery.dataTables.min.css?v=180612">
<script type="text/javascript" language="javascript" src="./in/datatables/js/jquery.dataTables.min.js?v=180612"></script>
<script type="text/javascript" language="javascript" src="./in/datatables/js/dataTables.bootstrap.min.js?v=180612"></script>
<link href="in/datetimepicker/jquery.datetimepicker.css" rel="stylesheet"/>
<script src="in/datetimepicker/jquery.datetimepicker.full.min.js"></script>
<style>
  .history_list div > div{
    padding: 10px 10px;
    font-size: 14px;
    text-align: center;
    font-weight: bolder;
  }
  .history_list div > div:last-child{
    text-align: left;
  }
  .history_list div.row:nth-child(odd){
    background: rgba(0,0,0,.05);
  }
  /* 表格一起對齊 */
  #member_log_wrapper,
  #transaction_query_wrapper,
  #member_betlog_wrapper{
    padding-left: 0;  
    padding-right: 0;
  }
  /* 共用 */
  /* 會員總覽列表上方的總紀錄 */
  .sum_th{
    background: rgb(249, 249, 249);
    color: #26282a;
    padding: 10px;
    font-size: 5px;
    margin: 0px;   
    width:33.33%; 
    text-align: center
  }
  .sum_td{
    color: #26282a;
    padding: 10px;
    font-size: 5px;
    margin: 0px;   
    width:33.33%; 
    text-align: center
  }
  /* 左側三攔樣式標題顏色 */
  .betlog_h6{
    background: rgba(224,228,233,.5);
    color: #26282a;
    padding: 10px;
    font-size: 15px;
    width: 100%;
    margin: 0px;    
  }
  /* 需要改CSS 名稱以面控制所有 */
  #token_auditorial_paginate,
  #member_log_paginate,
  #member_betlog_paginate,
  #transaction_query_paginate{
    display: flex;
    margin-top: 10px;
  }
  #token_auditorial_length,
  #member_log_length,
  #member_betlog_length,
  #transaction_query_length{
    margin-top: 10px;
    padding-top: 0.25em;
  }
  #member_betlog_paginate, .pagination,
  #transaction_query_paginate .pagination{
    margin-left: auto;			
  }
  #token_auditorial tr td,
  #member_betlog tr td,
  #transaction_query tr td,
  #member_log tr td{
    height: 40px;
  }
  .submit_btn_box button:nth-of-type(1){
    width: 80%;
  }
  .submit_btn_box button:nth-of-type(2){
    width: 15%;
  }
  /* 注單狀態 */
  .ck-button {
    margin: 0px;
    overflow: auto;
    float: left;
    width: 33.33%;
  }
  .ck-button.money{
    width: 50%;
  }
  .ck-button label {
    float: left;
    width: 100%;
    height: 100%;
    margin-bottom: 0;
    background-color: transparent;
    transition: all 0.2s;
  }
  .ck-button label input {
    position: absolute;
    z-index: -5;
  }
  .ck-button label span {
    text-align: center;
    display: block;
    font-size: 15px;
    line-height: 38px;
  }
  .ck-button:hover {
    border-color: #007bffaa;
    background-color: #007bffaa;
    color: #fff;
  }
  #overviewhistory{
    padding:20px 15px;
  }
  #token_auditorial_wrapper.container-fluid{
    padding-right: 0;
    padding-left: 0;
  }
</style>
<script>

  $(document).ready(function(){    
    //投注紀錄
    var tq = $('#member_betlog').DataTable({
      "dom": '<tflip>',
      "searching": false,
      "language": {
        "decimal": ",",
        "thousands": "."
      },
      // 假資料
      "ajax": "https://shiuanlin.jutainet.com/json/historytwo.php",
      "columns": [
        { "data": "id"},
        { "data": "bettime"},
        { "data": "logintime"},
        { "data": "gamename"},
        { "data": "gamecategory"},
        { "data": "totalwager"},
        { "data": "totalpayout"},
        { "data": "casino"},
        { "data": "bet_status",
          "fnCreatedCell": function (nTd, sData, oData, iRow, iCol) {
            if ( oData.bet_status == '已派彩' ) {
              var classnamebt = 'btn-danger';
            }else if ( oData.bet_status == '未派彩' ) {
              var classnamebt = 'btn-primary';
            }else if ( oData.bet_status == '修改過' ) {
              var classnamebt = 'btn-warning';
            }
            $(nTd).html("<button type=\"button\" class=\"btn "+classnamebt+"\">"+oData.bet_status+"</button>");
          }
        },
        { "data": null,
          "fnCreatedCell": function (nTd, sData, oData, iRow, iCol) {
            var btn = '<button type="button" class="btn btn-primary btn-xs" data-toggle="modal" data-target="#exampleModal"'+
            'data-whatever="投注紀錄"'+
            'data-tid="betlog_modal"'+
            'data-bettime="'+oData.bettime+'"' +
            'data-logintime="'+oData.logintime+'"' +
            'data-gamename="'+oData.gamename+'"' +
            'data-gamecategory="'+oData.gamecategory+'"'+
            'data-totalwager="'+oData.totalwager+'"'+
            'data-totalpayout="'+oData.totalpayout+'"'+
            'data-casino="'+oData.casino+'"'+
            'data-betstatus="'+oData.bet_status+'"'+
            '>'+
            '詳細' +
            '</button>';
            $(nTd).html(btn);
          }        
        },
      ],
      createdRow: function (row, data, dataIndex) {
        if ( data.totalpayout < 0 ) {
          $('td', row).eq(6).css( "color", "green" );
        }else{
          $('td', row).eq(6).css( "color", "red" );
        }
      },
      "fnDrawCallback": function (oSettings) {
      }
    });
    // 交易紀錄
    var tq = $('#transaction_query').DataTable({
      "dom": '<tflip>',
      "searching": false,
      "language": {
        "decimal": ",",
        "thousands": "."
      },
      // 假資料
      "ajax": "https://shiuanlin.jutainet.com/json/history.php",
      "columns": [
        { "data": "id"},
        { "data": "transtime"},
        { "data": "transaction_category"},
        { "data": "deposit", "class": "text-center"},
        { "data": "withdrawal", "class": "text-center"},
        { "data": "payout", "class": "text-center",
          "fnCreatedCell": function (nTd, sData, oData, iRow, iCol) {
            if ( oData.payout == '0' ) {
              $(nTd).html('0.00');
            }
          }
        },
        { "data": "token_balance", "class": "text-center"},
        { "data": "cash_balance", "class": "text-center"},
        { "data": null, 
          "fnCreatedCell": function (nTd, sData, oData, iRow, iCol) {
            var btn = '<button type="button" class="btn btn-primary btn-xs" data-toggle="modal" data-target="#exampleModal"'+
            'data-whatever="交易紀錄"'+
            'data-tid="transaction_modal"'+
            //交易序號
            'data-transid="'+oData.trans_id+'"'+
            //交易單號
            'data-transactionid="'+oData.transaction_id+'"'+
            //交易時間
            'data-transtime="'+oData.transtime+'"' +
            //存款金額
            'data-deposit="'+oData.deposit+'"' +
            //提款金額
            'data-withdrawal="'+oData.withdrawal+'"'+
            //派彩
            'data-payout="'+oData.payout+'"'+
            //當下餘額
            'data-tokenbalance="'+oData.token_balance+'"' +
            //交易類別
            'data-transactioncategory="'+oData.transaction_category+'"' +
            //摘要
            'data-summary="'+oData.summary+'"'+
            //轉入帳號
            'data-destinationtransferaccount="'+oData.destination_transferaccount+'"'+
            //操作人員
            'data-operator="'+oData.operator+'"'+
            //錢包
            'data-type="'+oData.type+'"'+
            '>'+
            '詳細' +
            '</button>';
            $(nTd).html(btn);
          }
        }
      ],
      createdRow: function (row, data, dataIndex) {
        if ( data.payout < 0 ) {
          $('td', row).eq(5).css( "color", "green" );
        }else{
          $('td', row).eq(5).css( "color", "red" );
        }
      }
    });
  
    //登入紀錄
    var ml = $('#member_log').DataTable({
      "dom": '<tflip>',
      "searching": false,
      "language": {
        "decimal": ",",
        "thousands": "."
      },
      // 假資料
      "ajax": "https://shiuanlin.jutainet.com/json/historythree.php",
      "columns": [
        { "data": "id"},
        { "data": "logintime"},
        { "data": "ip"},
        {"data":"ip_location"},
        {"data": null,
          "fnCreatedCell": function (nTd, sData, oData, iRow, iCol) {
            var btn = '<button type="button" class="btn btn-primary btn-xs" data-toggle="modal" data-target="#exampleModal"'+
            'data-whatever="登入紀錄"'+
            'data-tid="memberlog_modal"'+
            //单号
            'data-userlistid="'+oData.userlistid+'"'+
            //发生时间(美东)
            'data-logintime="'+oData.logintime+'"'+
            //操作人员
            'data-logaccount="'+oData.log_account+'"'+
            //主服务类型
            'data-serviceshow="'+oData.service_show+'"'+
            //次服务类型
            'data-subserviceshow="'+oData.sub_service_show+'"'+
            //IP位址
            'data-ip="'+oData.ip+'"'+
            //IP区域
            'data-iplocation="'+oData.ip_location+'"'+
            //过程讯息
            'data-message="'+oData.message+'"'+
            //浏览器指纹
            'data-logfingerprinting="'+oData.log_fingerprinting+'"'+
            //目标使用者
            'data-targetusers="'+oData.target_users+'"'+
            //平台
            'data-platformshow="'+oData.platform_show+'"'+
            '>'+
            '詳細' +
            '</button>';
            $(nTd).html(btn);
          }
        }
      ]
    });

    //稽核紀錄
    var tal = $('#token_auditorial').DataTable({
      "dom": '<tflip>',
      "searching": false,
      "language": {
        "decimal": ",",
        "thousands": "."
      },
      // 假資料
      "ajax": "https://shiuanlin.jutainet.com/json/history_auditorial.php",
      "columns": [
        { "data": "gtoken_id"},
        { "data": "deposit_time",
          "fnCreatedCell": function (nTd, sData, oData, iRow, iCol) {
            var time = '<p class="text-muted mb-0">(3 months ago)</p>';
            $(nTd).html('$ ' + oData.deposit_time + time);
          }
        },
        { "data": "deposit_amount",
          "fnCreatedCell": function (nTd, sData, oData, iRow, iCol) {
            $(nTd).html('$ ' + oData.deposit_amount);
          }
        },
        { "data": "audit_method"},
        {"data":"is_audit_message",
          "fnCreatedCell": function (nTd, sData, oData, iRow, iCol) {
            var number = oData.is_audit_message;
            var danger = '<span class="glyphicon glyphicon-remove text-danger mr-2" aria-hidden="true"></span>';
            var success = '<span class="glyphicon glyphicon-ok text-success mr-2" aria-hidden="true"></span>';
            if ( number == '30 / 100.00' ) {
              $(nTd).html(danger + '(' + oData.is_audit_message + ')');
            }else{
              $(nTd).html(success + '(' + oData.is_audit_message + ')');
            }
          }
        },
        {"data":"audit_amount", "class":"text-center",
          "fnCreatedCell": function (nTd, sData, oData, iRow, iCol) {
            var money = oData.audit_amount;
            if( money == '' ){
              $(nTd).html('无');
            }else{
              $(nTd).html('$ ' + money);
            }
          }
        }
      ]
    });

      $('#exampleModal').on('show.bs.modal', function (event) {
        var button = $(event.relatedTarget);
        var recipient = button.data('whatever');
        var tid = button.data('tid');
        var modal = $(this);
        //將標題送進去 modal
        modal.find('.modal-title').text(recipient);
        modal.find('.body_content').attr('id',tid);

        //投注
        if ( tid == 'betlog_modal') {
          
          // * 先清空html
          modal.find('.body_content').html('');
          // * 隱藏反水類型 篩選工具
          $('.body_filtercategory').addClass('d-none');
          // * 開啟一般內容
          $('.body_content').removeClass('d-none'); 

          //投注時間              
          var bettime = button.data('bettime');
          //派彩時間
          var logintime = button.data('logintime');
          //遊戲名稱
          var gamename = button.data('gamename');
          //遊戲類型
          var gamecategory = button.data('gamecategory');
          //有效投注
          var totalwager = button.data('totalwager');
          //派彩
          var totalpayout = button.data('totalpayout');
          //娛樂城
          var casino = button.data('casino');
          //注單狀態
          var betstatus = button.data('betstatus');

          var html = '<div class="container-fluid history_list">' +
          '<div class="row">' +
          '<div class="col-6">投注時間</div>' +
          '<div class="col-6">'+bettime+'</div>' +
          '</div>' +
          '<div class="row">' +
          '<div class="col-6">派彩時間</div>' +
          '<div class="col-6">'+logintime+'</div>' +
          '</div>' +
          '<div class="row">' +
          '<div class="col-6">遊戲名稱</div>' +
          '<div class="col-6">'+gamename+'</div>' +
          '</div>' +
          '<div class="row">' +
          '<div class="col-6">遊戲類型</div>' +
          '<div class="col-6">'+gamecategory+'</div>' +
          '</div>' +
          '<div class="row">' +
          '<div class="col-6">有效投注</div>' +
          '<div class="col-6">'+totalwager+'</div>' +
          '</div>' +
          '<div class="row">' +
          '<div class="col-6">派彩</div>' +
          '<div class="col-6">'+totalpayout+'</div>' +
          '</div>' +
          '<div class="row">' +
          '<div class="col-6">娛樂城</div>' +
          '<div class="col-6">'+casino+'</div>' +
          '</div>' +
          '<div class="row">' +
          '<div class="col-6">注單狀態</div>' +
          '<div class="col-6">'+betstatus+'</div>' +
          '</div>' +
          '</div>';
          modal.find('.body_content').html(html);
        }else if ( tid == 'transaction_modal' ) {
          // 交易紀錄

          // * 先清空html
          modal.find('.body_content').html('');
          // * 隱藏反水類型 篩選工具
          $('.body_filtercategory').addClass('d-none');
          // * 開啟一般內容
          $('.body_content').removeClass('d-none'); 

          //交易序號           
          var trans_id = button.data('transid');
          //交易單號
          var transaction_id = button.data('transactionid');
          //交易時間
          var transtime = button.data('transtime');
          //存款金額
          var deposit = button.data('deposit');
          //提款金額
          var withdrawal = button.data('withdrawal');
          //派彩
          var payout = button.data('payout');
          //當下餘額
          var token_balance = button.data('tokenbalance');
          //交易類別
          var transaction_category = button.data('transactioncategory');
          //摘要
          var summary = button.data('summary');
          //轉入帳號
          var destination_transferaccount = button.data('destinationtransferaccount');
          //操作人員
          var operator = button.data('operator');
          //錢包
          var type = button.data('type');

          var html = '<div class="container-fluid history_list">' +
          '<div class="row">' +
          '<div class="col-6">交易序号</div>' +
          '<div class="col-6">'+trans_id+'</div>' +
          '</div>' +
          '<div class="row">' +
          '<div class="col-6">交易单号</div>' +
          '<div class="col-6">'+transaction_id+'</div>' +
          '</div>' +
          '<div class="row">' +
          '<div class="col-6">交易时间(美东时间)</div>' +
          '<div class="col-6">'+transtime+'</div>' +
          '</div>' +
          '<div class="row">' +
          '<div class="col-6">存款金额</div>' +
          '<div class="col-6">'+deposit+'</div>' +
          '</div>' +
          '<div class="row">' +
          '<div class="col-6">取款金额</div>' +
          '<div class="col-6">'+withdrawal+'</div>' +
          '</div>' +
          '<div class="row">' +
          '<div class="col-6">派彩</div>' +
          '<div class="col-6">'+payout+'</div>' +
          '</div>' +
          '<div class="row">' +
          '<div class="col-6">当下余额</div>' +
          '<div class="col-6">'+token_balance+'</div>' +
          '</div>' +
          '<div class="row">' +
          '<div class="col-6">交易类别</div>' +
          '<div class="col-6">'+transaction_category+'</div>' +
          '</div>' +
          '<div class="row">' +
          '<div class="col-6">摘要</div>' +
          '<div class="col-6">'+summary+'</div>' +
          '</div>' +
          '<div class="row">' +
          '<div class="col-6">转入帐号</div>' +
          '<div class="col-6">'+destination_transferaccount+'</div>' +
          '</div>' +
          '<div class="row">' +
          '<div class="col-6">操作人员</div>' +
          '<div class="col-6">'+operator+'</div>' +
          '</div>' +
          '<div class="row">' +
          '<div class="col-6">钱包</div>' +
          '<div class="col-6">'+type+'</div>' +
          '</div>' +
          '</div>';
          
          modal.find('.body_content').html(html);
        }else if( tid == 'memberlog_modal' ){
          //登入紀錄

          // * 先清空html
          modal.find('.body_content').html('');
          // * 隱藏反水類型 篩選工具
          $('.body_filtercategory').addClass('d-none');
          // * 開啟一般內容
          $('.body_content').removeClass('d-none'); 

          //单号
          var userlistid = button.data('userlistid');
          //发生时间(美东)
          var logintime = button.data('logintime');
          //操作人员
          var logaccount = button.data('logaccount');
          //主服务类型
          var serviceshow = button.data('serviceshow');
          //次服务类型
          var subserviceshow = button.data('subserviceshow');
          //IP位址
          var ip = button.data('ip');
          //IP区域
          var iplocation = button.data('iplocation');
          //过程讯息
          var message = button.data('message');
          //浏览器指纹
          var logfingerprinting = button.data('logfingerprinting');
          //目标使用者
          var targetusers = button.data('targetusers');
          //平台
          var platformshow = button.data('platformshow');
          var html = '<div class="container-fluid history_list">' +
          '<div class="row">' +
          '<div class="col-6">序號</div>' +
          '<div class="col-6">'+userlistid+'</div>' +
          '</div>' +
          '<div class="row">' +
          '<div class="col-6">发生时间(美东)</div>' +
          '<div class="col-6">'+logintime+'</div>' +
          '</div>' +
          '<div class="row">' +
          '<div class="col-6">操作人员</div>' +
          '<div class="col-6">'+logaccount+'</div>' +
          '</div>' +
          '<div class="row">' +
          '<div class="col-6">主服务类型</div>' +
          '<div class="col-6">'+serviceshow+'</div>' +
          '</div>' +
          '<div class="row">' +
          '<div class="col-6">次服务类型</div>' +
          '<div class="col-6">'+subserviceshow+'</div>' +
          '</div>' +
          '<div class="row">' +
          '<div class="col-6">IP位址</div>' +
          '<div class="col-6">'+ip+'</div>' +
          '</div>' +
          '<div class="row">' +
          '<div class="col-6">IP区域</div>' +
          '<div class="col-6">'+iplocation+'</div>' +
          '</div>' +
          '<div class="row">' +
          '<div class="col-6">过程讯息</div>' +
          '<div class="col-6">'+message+'</div>' +
          '</div>' +
          '<div class="row">' +
          '<div class="col-6">浏览器指纹</div>' +
          '<div class="col-6">'+logfingerprinting+'</div>' +
          '</div>' +
          '<div class="row">' +
          '<div class="col-6">目标使用者</div>' +
          '<div class="col-6">'+targetusers+'</div>' +
          '</div>' +
          '<div class="row">' +
          '<div class="col-6">平台</div>' +
          '<div class="col-6">'+platformshow+'</div>' +
          '</div>';          
          modal.find('.body_content').html(html);
        }else if ( tid == 'filtercategory') {
          //反水類型 篩選工具 開啟
          // 清空一般內容
          modal.find('.body_content').html('');
          // 隱藏一般內容
          $('.body_content').addClass('d-none');
          //開啟反水類型 篩選工具
          $('.body_filtercategory').removeClass('d-none');          
        }else{
          // * 先清空html
          modal.find('.body_content').html('');
          // * 隱藏反水類型 篩選工具
          $('.body_filtercategory').addClass('d-none');
          // * 開啟一般內容
          $('.body_content').removeClass('d-none'); 
        }
      });

    // 反水類型篩選工 功能按鈕
    // 全選
    $('#select_all_checkbox').click(function () {
      fun_select_all();
      //submit_select();
    });

      // 清空
    $('#cancel_select_all_checkbox').click(function () {
      fun_select_all_cancel();
      //submit_select();
    });

    // 全選
    function fun_select_all(){
      $('.modal-body input:checkbox').prop('checked', true);
    }

    //取消全選
    function fun_select_all_cancel(){
      $('.modal-body input:checkbox').prop('checked', false);
    }

    function submit_select() {
      var sel_in_cl_bonus_list = $('[name="bns"]').serialize();
      // console.log(sel_in_cl_bonus_list);
      $.post('member_betlog_action.php?get=select_bonus_list',
      {
        sel_in_cl_bonus_list: sel_in_cl_bonus_list
      },
      function(result) {
        $('#casino_category_preview_area').html(result);
      });
    }

    //查詢條件切換
    $('#myTab li a').on('click', function(e){
      var data = $(this).data('name');
      var iddata = $(this).attr('id').split('-');
      $('.filtercategory').addClass('collapse');
      $('.excel').addClass('collapse');
      var id = iddata[0];
      $('.filtercategory').addClass('collapse');
      $('.excel').addClass('collapse');
      $('#'+ id +'_filter').removeClass('collapse');
      $('#'+ id +'_excel').removeClass('collapse');
      //console.log(id);
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
$tmpl['paneltitle_content'] = 'gpkshiuan 歷程記錄'.'<div class="d-flex ml-auto">'.$panelbody_excel.'</div>';
// 主要內容 -- 左方選單
$tmpl['panelbody_filtercategory'] = $panelbody_filtercategory;
// 主要內容 -- 右方按鈕欄位
$tmpl['panelbody_excel'] = "";
// 主要內容 -- 右方 content
$tmpl['panelbody_content'] = $indexbody_content;
// 擴充再 head 的內容 可以是 js or css
$tmpl['extend_head'] = $extend_head;
// 擴充於檔案末端的 Javascript
$tmpl['extend_js'] = $extend_js;
// ----------------------------------------------------------------------------
// 填入內容結束。底下為頁面的樣板。以變數型態塞入變數顯示。
// ----------------------------------------------------------------------------
include "template/member_fluid.php";

?>
