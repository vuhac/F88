<?php use_layout("template/".$template_laout.".php"); ?>

<!-- begin of extend_head -->
<?php begin_section('extend_head'); ?>
<!-- Jquery UI js+css  -->
<script src="in/jquery-ui.js"></script>
<link rel="stylesheet"  href="in/jquery-ui.css" >
<!-- Jquery blockUI js  -->
<script src="./in/jquery.blockUI.js"></script>
<!-- jquery datetimepicker js+css -->
<link href="in/datetimepicker/jquery.datetimepicker.css" rel="stylesheet"/>
<script src="in/datetimepicker/jquery.datetimepicker.full.min.js"></script>
<!-- Datatables js+css  -->
<link rel="stylesheet" type="text/css" href="./in/datatables/css/jquery.dataTables.min.css?v=180612">
<script type="text/javascript" language="javascript" src="./in/datatables/js/jquery.dataTables.min.js?v=180612"></script>
<script type="text/javascript" language="javascript" src="./in/datatables/js/dataTables.bootstrap.min.js?v=180612"></script>
<!-- 自訂css -->
<link rel="stylesheet" type="text/css" href="ui/style_seting.css">
<style type="text/css">
.ck-button {
    margin:0px;
    /*border:1px solid #D0D0D0;
    border-right-style: none;
    border-top-style: none;*/
    overflow:auto;
    float:left;
    width: 33.33%;
}

.ck-button:hover {
    border-color: #007bffaa;
    background-color: #007bffaa;
    color: #fff;
}

.ck-button label {
    float:left;
    width: 100%;
    height: 100%;
    margin-bottom:0;
    background-color: transparent;
    transition: all 0.2s;
}

.ck-button label span {
    text-align:center;
    display:block;
    font-size: 15px;
    line-height: 38px;
}

.ck-button label input {
    position:absolute;
    z-index: -5;
    /*top:-20px;*/
}

.ck-button input:checked + span {
    border-color: #007bff;
    background-color: #007bff;
    color: #fff;
}


.ck-button:nth-child(3n+2) label{
    border:1px solid #D0D0D0;
    border-top-style: none;
    border-bottom-style: none;
}

.col-3{
    padding: 0px;
}

.form-control-static {
    min-height: 34px;
    padding-top: 7px;
    padding-bottom: 7px;
    margin-bottom: 0;
}

.labelalign {
    min-height: 34px;
    padding-top: 7px;
    margin-bottom: 0;
    text-align: right;
}

table tbody .checkbox-inline{
    padding-left: 3px;
}

table tbody tr input[type=checkbox]{
    margin-right: 1px;
}

table tbody .col-2{
  padding:0px 8px;
}

.betstatus0 {
  background-color: blue;
  color: white;
}

.betstatus1 {
  background-color: red;
  color: white;
}

.betstatus2 {
  background-color: yellow;
  color: white;
}
</style>
<?php end_section(); ?>
<!-- end of extend_head -->


<!-- begin of page_title -->
<?php begin_section('page_title'); ?>
<ol class="breadcrumb">
  <li><a href="home.php"><?php echo $tr['Home']; ?></a></li>
  <li><a href="#"><?php echo $tr['Various reports']; ?></a></li>
  <li class="active"><?php echo $function_title; ?></li>
</ol>
<?php end_section(); ?>
<!-- end of page_title -->


<!-- 查詢欄（左）title -->
<!-- begin of indextitle_content -->
<?php begin_section('indextitle_content'); ?>
<span class="glyphicon glyphicon-search" aria-hidden="true"></span><?php echo $tr['Search criteria']; ?>
<?php end_section(); ?>
<!-- end of indextitle_content -->


<!-- 結果欄（右）title -->
<!-- begin of paneltitle_content -->
<?php begin_section('paneltitle_content'); ?>
<span class="glyphicon glyphicon-list" aria-hidden="true"></span><?php echo $tr['Query results']; ?>
<div id="csv"  style="float:right;margin-bottom:auto"></div>
<?php end_section(); ?>
<!-- end of paneltitle_content -->

<?php
  require_once dirname(__FILE__) ."/casino_switch_process_lib.php";
  // 搜尋開始時間、結束時間函式
  require_once dirname(__FILE__) ."/deposit_withdrawal_company_audit_lib.php";

  $casinoLib = new casino_switch_process_lib();

  $search_time = time_convert();
  // 2020-2-13
  // $current_date = gmdate('Y-m-d',time() + -4*3600);
  // $default_min_date = gmdate('Y-m-d',strtotime('- 2 month'));//.'00:00:00';
  // $default_enddate = gmdate('Y-m-d H:i:s',time() + -4*3600);

  // $thisweekday = date("Y-m-d", strtotime("$current_date - ".date('w',strtotime($current_date))."days"));
  // $yesterday = date("Y-m-d", strtotime("$current_date - 1 days"));

  // // 上週
  // $lastweekday_s = date("Y-m-d", strtotime("$current_date - ".intval(date('w',strtotime($current_date))+7)."days"));
  // $lastweekday_e = date("Y-m-d", strtotime("$thisweekday - 1 days"));

  // $thismonth = date("Y-m", strtotime($current_date));

  // // 上個月
  // $lastmonth = date('Y-m',strtotime(date('Y-m-1').'-1 month'));
  // $lastmonth_e = date('Y-m-d',strtotime(date('Y-m-1').'-1 day'));

?>


<!-- 查詢欄（左）內容 -->
<!-- begin of indexbody_content -->
<?php begin_section('indexbody_content'); ?>
<div>
  <!-- 查詢條件 - 注單號 -->
  <div class="row">
    <div class="col-12">
      <label for="betting_slip_num">
        <?php echo $tr['bet number'];?>
      </label>
    </div>
    <div class="col-12 form-group">
      <input type="text" class="form-control" name="betting_slip_num"
       id="betting_slip_num" placeholder="">
    </div>
  </div>
  <?php 
if(!$member_overview_mode){
  echo <<<HTML
  <div class="row">
    <div class="col-12">
      <label for="account_query">
        {$tr['Account']}
      </label>
    </div>
    <div class="col-12 form-group">
        <input type="text" class="form-control" name="a"
        id="account_query" placeholder="{$tr['Account']}" value="{$account_query}">
    </div>
  </div>
HTML;
}
?>

  <div class="row">
    <div class="col-12">
    <label for="agent" >
      <?php echo $tr['Affiliated agent'];?>
      <!-- <span class="glyphicon glyphicon-info-sign" title="<?php //echo $tr['Member Account']; ?>"></span> -->
    </label>
    </div>
    <div class="col-12 form-group">
      <div class="input-group">
        <input type="text" class="form-control" id="agent" placeholder="<?php echo $tr['Affiliated agent'];?>">
      </div>
    </div>
  </div>

  <div class="row form-group">
    <div class="col-12 d-flex">
      <label for="query_betdate_start_datepicker"><?php echo $tr['Betting time'];?>
          <!-- <span class="glyphicon glyphicon-info-sign"
              title="<?php echo $tr['Inquiries up to two months in accordance with the payout time query']; ?>">
          </span> -->
      </label>
      
      <div class="btn-group btn-group-sm ml-auto application" role="group" aria-label="Button group with nested dropdown">
          <button type="button" class="btn btn-secondary first"><?=$tr['grade default']?></button>

          <div class="btn-group btn-group-sm" role="group">
            <button id="btnGroupDrop1" type="button" class="btn btn-secondary dropdown-toggle" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
            </button>
            <div class="dropdown-menu" aria-labelledby="btnGroupDrop1">
              <a class="dropdown-item week" onclick="settimerange('<?php echo $search_time['thisweekday'];?> 00:00:00', getnowtime(),'week');"><?=$tr['This week'];?></a>
              <a class="dropdown-item month" onclick="settimerange('<?php echo $search_time['thismonth'];?>-01 00:00:00',getnowtime(),'month');"><?=$tr['this month'];?></a>
              <a class="dropdown-item today" onclick="settimerange('<?php echo $search_time['current'];?> 00:00:00', getnowtime(),'today');"><?=$tr['Today'];?></a>
              <a class="dropdown-item yesterday" onclick="settimerange('<?php echo $search_time['yesterday']; ?> 00:00:00', '<?php echo $search_time['yesterday']; ?> 23:59:59','yesterday');"><?=$tr['yesterday'];?></a>
              <a class="dropdown-item lastmonth" onclick="settimerange('<?php echo $search_time['lastmonth'];?>-01 00:00:00','<?php echo $search_time['lastmonth_e'];?> 23:59:59','lastmonth');"><?=$tr['last month'];?></a>
            </div>
          </div>
        </div>
    </div>
        <!-- <div class="input-group">
          <input type="text" class="form-control"
            name="bet_sdate" id="query_betdate_start_datepicker"
            placeholder="ex:2017-01-20 00:00:00" value=" echo $sdate_query; ?>">
          <span class="input-group-addon" id="basic-addon1">~</span>
          <input type="text" class="form-control"
            name="bet_edate" id="query_betdate_end_datepicker"
            value="  echo $edate_query; ?>">
        </div> -->
        <div class="col-12 rwd_doublerow_time">
        <div class="input-group">
          <div class="input-group-prepend">
            <span class="input-group-text" id="basic-addon1"><?php echo $tr['start']; ?></span>
          </div>
          <input type="text" class="form-control"
            name="bet_sdate" id="query_betdate_start_datepicker"
            placeholder="ex:2017-01-20 00:00:00" value="<?php echo $sdate_query; ?>">
        </div>
        <div class="input-group">
          <div class="input-group-prepend">
            <span class="input-group-text" id="basic-addon1"><?php echo $tr['end']; ?></span>
          </div>
          <input type="text" class="form-control"
            name="bet_edate" id="query_betdate_end_datepicker"
            value="<?php  echo $edate_query; ?>">
        </div>

  </div>
  </div>
</div>
<?php end_section(); ?>
<!-- end of indexbody_content -->

<!-- 查詢欄 - 進階（左） -->
<!-- begin of indexbody_advanced_content -->
<?php begin_section('indexbody_advanced_content'); ?>
  <!-- 查詢條件 - 派彩日期 -->
  <div class="row form-group">
    <div class="d-flex w-100">
    <label for="query_date_start_datepicker"><?php echo $tr['payment date'];?>
        <!-- <span class="glyphicon glyphicon-info-sign"
            title="<?php echo $tr['Inquiries up to two months in accordance with the payout time query']; ?>">
        </span> -->
    </label>
    
    <div class="btn-group btn-group-sm ml-auto applications" role="group" aria-label="Button group with nested dropdown">
        <button type="button" class="btn btn-secondary firsts"><?=$tr['grade default'] ?></button>

        <div class="btn-group btn-group-sm" role="group">
          <button id="btnGroupDrop1" type="button" class="btn btn-secondary dropdown-toggle" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
          </button>
          <div class="dropdown-menu" aria-labelledby="btnGroupDrop1">
            <a class="dropdown-item weeks" onclick="set_profit_range('<?php echo $search_time['thisweekday'];?> 00:00:00', getnowtime(),'weeks');"><?=$tr['This week'];?></a>
            <a class="dropdown-item months" onclick="set_profit_range('<?php echo $search_time['thismonth'];?>-01 00:00:00',getnowtime(),'months');"><?=$tr['this month'];?></a>
            <a class="dropdown-item todays" onclick="set_profit_range('<?php echo $search_time['current'];?> 00:00:00', getnowtime(),'todays')"><?=$tr['Today'];?></a>
            <a class="dropdown-item yesterdays" onclick="set_profit_range('<?php echo $search_time['yesterday']; ?> 00:00:00', '<?php echo $search_time['yesterday']; ?> 23:59:59','yesterdays' );"><?=$tr['yesterday'];?></a>
            <a class="dropdown-item lastmonths" onclick="set_profit_range('<?php echo $search_time['lastmonth'];?>-01 00:00:00','<?php echo $search_time['lastmonth_e'];?> 23:59:59','lastmonths');"><?=$tr['last month'];?></a>
          </div>
        </div>
      </div>
    </div>

        <!-- <div class="input-group">
          <input type="text" class="form-control"
            name="payout_sdate" id="query_date_start_datepicker"
            placeholder="<php echo $tr['Starting time'];?>"
            value="<php //echo $sdate_query; ?>">
          <span class="input-group-addon" id="basic-addon1">~</span>
          <input type="text" class="form-control"
            name="payout_edate" id="query_date_end_datepicker"
            placeholder="<php echo $tr['End time'];?>"
            value="<php //echo $edate_query; ?>">
        </div> -->
        <div class="col-12 rwd_doublerow_time px-0">
        <div class="input-group">
          <div class="input-group-prepend">
            <span class="input-group-text"><?php echo $tr['start'];?></span>
          </div>
          <input type="text" class="form-control"
            name="payout_sdate" id="query_date_start_datepicker"
            placeholder="<?php echo $tr['Starting time'];?>"
            value="<?php //echo $sdate_query; ?>">
        </div>

        <div class="input-group">
          <div class="input-group-prepend">
            <span class="input-group-text"><?php echo $tr['end'];?></span>
          </div>
          <input type="text" class="form-control"
            name="payout_edate" id="query_date_end_datepicker"
            placeholder="<?php echo $tr['End time'];?>"
            value="<?php //echo $edate_query; ?>">
        </div>
        </div>
  </div>

  <div class="row form-group">
    <label for="gc_query"><?php echo $tr['bonus_type'] ;?></label>
    <div class="input-group">
      <div class="form-control-plaintext">
        <span id="casino_category_preview_area" >(<?php echo $tr['select all'];?>)</span>
        <button type="button" class="btn btn-primary btn-xs ml-1" data-toggle="modal" data-target="#gameTypeModal"><?php echo $tr['Select'];?></button>

        <div class="modal fade" id="gameTypeModal" tabindex="-1" role="dialog" aria-labelledby="myModalLabel" data-backdrop="true">
          <div class="modal-dialog" role="document">
            <div class="modal-content">
              <div class="modal-header">
                <h4 class="modal-title" id="myModalLabel"><?php echo $tr['Select game bonus type'];?></h4>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true"></span></button>
              </div>

              <div class="modal-body">
                <!--全選 清空-->
                <div class="btn-group">
                  <button type="button" class="btn btn-xs btn-outline-primary" id="select_all_checkbox"><?php echo $tr['select all']; ?></button>
                  <button type="button" class="btn btn-xs btn-outline-primary" id="cancel_select_all_checkbox"><?php echo $tr['Emptied']; ?></button>
                </div>
                <br><br>
                <!--MG PT MEGA IG CQ9 GPK2-->
                <div class="btn-group">
                  <?php foreach ($menu_casinolist_items as $casino_value ): ?>
                    <button type="button" class="btn btn-xs btn-outline-primary btn_cl_casino_cate" name="name_casino_sel" value="<?php echo $casino_value->casinoid;?>">
                        <?php echo $casinoLib->getCurrentLanguageCasinoName($casino_value->display_name, 'default'); ?>
                    </button>
                  <?php endforeach;?>
                </div>
                <br><br>

                <!--電子 棋牌 時時彩 六合彩 h5電子 捕魚 体育 真人-->
                 <div class="btn-group">
                  <?php foreach ($menu_bonus_cate_item as $menu_bonus_cate_key => $menu_bonus_cate_value ):?>
                    <button type="button" class="btn btn-xs btn-outline-primary btn_cl_bonus_cate" id="id_bonus_sel_<?php echo $menu_bonus_cate_key; ?>" name="name_bonus_sel" value="<?php echo $menu_bonus_cate_key;?>"><?php echo $tr[$menu_bonus_cate_key] ; ?></button>
                  <?php endforeach;?>
                </div>

                <br><br>
                <table class="table table-bordered">
                  <tbody>
                    <!-- □MG
                         □PT
                         □MEGA
                         □IG  -->
                    <?php foreach ($menu_casinolist_items as $for_casinolist_value ):?>
                      <tr>
                        <div class="row">
                        <div class="col-2">

                          <th class="active">
                            <label>
                              <input type="checkbox" class="<?php echo $for_casinolist_value->casinoid;?>_in_cl_bonus_list_parent"
                                     checked onclick="casino_check_all(this,'<?php echo $for_casinolist_value->casinoid;?>_in_cl_bonus_list')">
                                     <?php echo $casinoLib->getCurrentLanguageCasinoName($for_casinolist_value->display_name, 'default'); ?>
                            </label>
                          </th>
                        </div>
                        </div>

                        <td>
                        <div class="row">
                        <!--  □真人 □電子 □H5電子 □捕魚 □時時彩 □體育 □棋牌-->
                        <?php foreach (json_decode($for_casinolist_value->game_flatform_list,true) as $for_game_flatform_list ): ?>
                            <div class="col-2">
                              <label class="checkbox-inline">
                                <input type="checkbox" name="bns"
                                       value=<?php echo $for_casinolist_value->casinoid.'_'.$for_game_flatform_list;?>
                                       class="<?php echo $for_casinolist_value->casinoid;?>_in_cl_bonus_list" checked>
                                       <?php echo $tr[$for_game_flatform_list];?>
                              </label>
                            </div>
                        <?php endforeach;?>
                        </div>
                        </td>
                      </tr>
                    <?php endforeach;?>

                  </tbody>
                </table>
              </div>
                <div class="modal-footer">
                  <button type="button" class="btn btn-default" data-dismiss="modal" id="close_betlog_bonus_cate_btn">Close</button>
                </div>
              </div>
            </div>
          </div>

      </div>
    </div>
  </div>

  <!-- <div class="row">
    <label for="account_query" class="col-3 labelalign">
      <?php echo $tr['Bureau number'];?>
    </label>
    <div class="form-group input-group col-9">
      <input type="text" class="form-control" name="a"
       id="inning_number" placeholder="">
    </div>
  </div> -->
  <div class="row">
    <label for="game_name">
      <?php echo $tr['game name']?>
    </label>
    <div class="form-group input-group">
      <input type="text" class="form-control" name="game_name"
       id="game_name" placeholder="">
    </div>
  </div>

  <!-- <div class="row form-group">
    <label for="betamount_lower">
      <?php echo $tr['bet amount'];?>
    </label>
      <div class="input-group">
        <input type="number" class="form-control" step=".01" placeholder='' id="betamount_lower" name="betamount_lower">
        <span class="input-group-addon" id="basic-addon1">~</span>
        <input type="number" class="form-control" step=".01" placeholder='' id="betamount_upper" name="betamount_upper">
      </div>
  </div> -->

  <div class="row form-group">
    <label for="betvalid_lower">
      <?php echo $tr['effective bet amount'];?>
    </label>
      <div class="input-group">
        <input type="number" class="form-control" step=".01" placeholder='' id="betvalid_lower" name="betvalid_lower">
        <span class="input-group-addon" id="basic-addon1">~</span>
        <input type="number" class="form-control" step=".01" placeholder='' id="betvalid_upper" name="betvalid_upper">
      </div>
  </div>

  <div class="row form-group">
    <label for="receive_lower">
      <?php echo $tr['Payout'];?>
    </label>
      <div class="input-group">
        <input type="number" class="form-control" step=".01" placeholder='' id="receive_lower" name="receive_lower">
        <span class="input-group-addon" id="basic-addon1">~</span>
        <input type="number" class="form-control" step=".01" placeholder='' id="receive_upper" name="receive_upper">
      </div>
  </div>

    <!-- 查詢條件 - 注單狀態 -->
  <div class="row border">
    <h6 class="betlog_h6 text-center"><?php echo $tr['bet status'] ;?></h6>
  </div>
  <div class="row border">
    <?php foreach ($menu_bet_status as $status_key => $status_value): ?>
      <div class="ck-button" >
          <label>
               <input type="checkbox" id="status_sel_<?php echo $status_key;?>" name="status_sel" value="<?php echo $status_key;?>">
               <span class="status_sel_<?php echo $status_key;?>"><?php echo $status_value; ?></span>
          </label>
      </div>
    <?php endforeach;?>
  </div>
<?php end_section(); ?>
<!-- end of indexbody_advanced_content -->

<!-- 查詢欄（左）submit鈕 -->
<!-- begin of indexbody_submit -->
<?php begin_section('indexbody_submit'); ?>
<div class="row">
  <div class="col-12 col-md-12">
  <button id="submit_to_inquiry" class="btn btn-success btn-block" type="submit"><?php echo $tr['Inquiry']; ?></button>
  </div>
</div>
<?php end_section(); ?>
<!-- end of indexbody_submit -->

<!-- 結果欄（右）內容 -->
<!-- begin of panelbody_content -->
<?php begin_section('panelbody_content'); ?>

<div id="inquiry_result_area">
<div id="show_summary">
</div>
<table id="show_list"  class="display" cellspacing="0" width="100%" >
  <thead>
    <tr>
      <th><?php echo $tr['bet number']; ?></th>
      <th><?php echo $tr['Account']; ?> </th>
      <th><?php echo $tr['Betting time']; ?>(EDT)</th>
      <th><?php echo $tr['payment time']; ?>(EDT)</th>

      <th><?php echo $tr['game name']; ?></th>
      <th><?php echo $tr['Game category']; ?></th>
      <th><?php echo $tr['effective bet amount']; ?></th>
      <th><?php echo $tr['Payout']; ?></th>

      <th><?php echo $tr['Casino']; ?></th>
      <th><?php echo $tr['bet status']; ?></th>
      <th><?php echo $tr['detail']; ?></th>
    </tr>
  </thead>
  <tfoot>
    <tr>
      <th><?php echo $tr['ID']; ?></th>
      <th><?php echo $tr['Account']; ?></th>
      <th><?php echo $tr['Betting time']; ?>(EDT)</th>
      <th><?php echo $tr['payment time']; ?>(EDT)</th>

      <th><?php echo $tr['game name']; ?></th>
      <th><?php echo $tr['Game category']; ?></th>
      <th><?php echo $tr['effective bet amount']; ?></th>
      <th><?php echo $tr['Payout']; ?></th>

      <th><?php echo $tr['Casino']; ?></th>
      <th><?php echo $tr['bet status']; ?></th>
      <th><?php echo $tr['detail']; ?></th>
    </tr>
  </tfoot>
</table>
<div class="row">
  <div id="preview_result"></div>
</div>
</div>
<div class="modal fade" id="notify_modal" tabindex="-1" role="dialog" aria-labelledby="myModalLabel" data-backdrop="true">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h2 class="modal-title" id="myModalLabel"><?php echo $tr['betting record detail'];?></h2>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
      </div>

      <div class="modal-body">
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>
<?php end_section(); ?>
<!-- end of panelbody_content -->


<!-- begin of extend_js -->
<?php begin_section('extend_js'); ?>
<script type="text/javascript" language="javascript">

function paginateScroll() { // auto scroll to top of page
  $("html, body").animate({
     scrollTop: 0
  }, 100);
}

function getnowtime(){
  // var NowDate = moment().tz('America/St_Thomas').format('YYYY-MM-DD HH:mm:ss');
  var NowDate = moment().tz('America/St_Thomas').format('YYYY-MM-DD')+' 23:59:59';


  return NowDate;
}
// 本日、昨日、本周、上周、上個月button
// 投注時間
function settimerange(sdate,edate,text){

  // 投注時間
  $("#query_betdate_start_datepicker").val(sdate);
  $("#query_betdate_end_datepicker").val(edate);
		//更換顯示到選單外 20200525新增
    var currentonclick = $('.'+text+'').attr('onclick');
    var currenttext = $('.'+text+'').text();

		//first change
    $('.application .first').removeClass('week month');
    $('.application .first').attr('onclick',currentonclick);
    $('.application .first').text(currenttext); 
  // getquery();
}
// 派彩時間
function set_profit_range(sdate,edate,texts){

  // 派彩日期
  $("#query_date_start_datepicker").val(sdate);
  $("#query_date_end_datepicker").val(edate);
		//更換顯示到選單外 20200525新增
    var currentonclicks = $('.'+texts+'').attr('onclick');
    var currenttexts = $('.'+texts+'').text();

		//first change
    $('.applications .firsts').removeClass('weeks months');
    $('.applications .firsts').attr('onclick',currentonclicks);
    $('.applications .firsts').text(currenttexts); 
}

function summary_tmpl(result){
  return `
      <table id="show_sum_list" class="table" cellspacing="2" width="100%" >
          <thead class="thead-inverse">
              <tr>
                <th style="text-align:center;"><?php echo $tr['Total number of bets during the search']; ?></th>
                <th style="text-align:center;"><?php echo $tr['Total betting amount during the inquiry period']; ?></th>
                <th style="text-align:center;"><?php echo $tr['Total profit and loss during the inquiry']; ?></th>
              </tr>
          </thead>
          <tbody id="show_sum_content">
              <tr>
                <td align="center">${result.member_betlog_result_count}</td>
                <td align="center">${result.member_betlog_betvalidsum}</td>
                <td align="center">${result.betlog_accumulated}</td>
              </tr>
          </tbody>
      </table>
  `
}

function csv_download_tmpl(result){
  var link = '';
  if(result.member_betlog_result_count > 0){
      var link = `
      <a href="${result.download_url}" data-loading-text="下载中..."
        data-filename="${result.csv_filename}" class="js-download-csv btn btn-success btn-sm"
        role="button" aria-pressed="true">
        <?php echo $tr['Export Excel']; ?>
      </a>
      `;
  }
  return link;
}

function query_str(query_state,query_datas) {
  var updating_str = '<h5 align="center"><?php echo $tr['Data query']; ?>...<img width="30px" height="30px" src="ui/loading.gif" /></h5>';
  $("#show_summary").html(updating_str);
  $(":input").not(":checkbox, :submit, select").val("");
  $("select").val("all");
  $("#gc_query").val("all");
  if( query_state == "account"){
    var query_str = "&a="+query_datas;
    $("#account_query").val(query_datas); }

  $.get(
    "member_betlog_action.php?get=query_summary"+query_str,
    function(result) {
      if(result.data == ''){
        $('#show_summary').html(expire_date_no_data(result));

      }else if(!result.logger){
        $("#show_summary").html(summary_tmpl(result));
        $("#csv").html(csv_download_tmpl(result));

      }else{
        $("#show_summary").html('');
        alert(result.logger);
      }

      // if(!result.logger){
      //   $("#show_summary").html(summary_tmpl(result));
      //   $("#csv").html(csv_download_tmpl(result));
      // }else{
      //   $("#show_summary").html('');
      //   alert(result.logger);
      // }
    },
    'json'
  );

  $("#show_list").DataTable()
    .ajax.url("member_betlog_action.php?get=query_log"+query_str)
    .load();

  paginateScroll();
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

// 全選
function fun_select_all(){
  $('.modal-body input:checkbox').prop('checked', true);
}

//取消全選
function fun_select_all_cancel(){
  $('.modal-body input:checkbox').prop('checked', false);
}

// 娛樂城全選.取消全選
function casino_check_all(obj,cName)
{
    var checkboxs = document.getElementsByClassName(cName);
    for(var i=0;i<checkboxs.length;i++){checkboxs[i].checked = obj.checked;}
}


// 娛樂城全選.取消全選
function load_betdetail(modalid,cid,bid)
{
  var modal_block_id = '#'+modalid;
  var iframe_id = '#modal-iframe'+modalid;
  $.get(
    "member_betlog_action.php?get=betdetail&cid="+cid+"&bid="+bid,
    function(result) {
      if(result.url == ''){
        $(notify_modal).find('.modal-body').html(result.logger);
        $(notify_modal).modal('show');
      }else{
        if(result.urlchk == 'https' ){
          $(iframe_id).attr('src',result.url);
          $(modal_block_id).modal('show');
        }else{
          detailhtml = '<a type="button" class="btn btn-info btn-xs pull-right modal-btn" href="'+result.url+'" target="_BLACK" ><?php echo $tr['detail'];?></a>'
          $(notify_modal).find('.modal-body').html(detailhtml);
          $(notify_modal).modal('show');
        }
      }
    },
    'json'
  );
}

$(function() {

  // 投注時間
  // 開始時間
  $("#query_betdate_start_datepicker").datetimepicker({
    showButtonPanel: true,
    timepicker: false,
    format: "Y-m-d H:i:s",
    changeMonth: true,
    changeYear: true,
    step:1,
    // 只能查2個月
    minDate: "<?php echo $search_time['two_month']; ?>",
    maxDate: "<?php echo $search_time['current'].$search_time['end_sec']; ?>"
  });
  // 結束時間
  $("#query_betdate_end_datepicker").datetimepicker({
    showButtonPanel: true,
    timepicker: false,
    format: "Y-m-d H:i:s",
    changeMonth: true,
    changeYear: true,
    step:1,
    // 只能查2個月
    minDate: "<?php echo $search_time['two_month']; ?>",
    maxDate: "<?php echo $search_time['current'].$search_time['end_sec']; ?>"
  });

  // 派彩日期
  // 開始時間
  $( "#query_date_start_datepicker" ).datetimepicker({
    showButtonPanel: true,
    timepicker: false, 
    format: "Y-m-d H:i:s",
    changeMonth: true,
    changeYear: true,
    // defaultTime: "00:00:00",
    step:1,
    // 只能查2個月
    minDate: "<?php echo $search_time['two_month']; ?>",
    maxDate: "<?php echo $search_time['current'].$search_time['end_sec']; ?>"
  });
  // 結束時間
  $( "#query_date_end_datepicker" ).datetimepicker({
    showButtonPanel: true,
    timepicker: false,
    format: "Y-m-d H:i:s",
    changeMonth: true,
    changeYear: true,
    // defaultTime: "23:59:59",
    step:1,
    // 只能查2個月
    minDate: "<?php echo $search_time['two_month']; ?>",
    maxDate: "<?php echo $search_time['current'].$search_time['end_sec']; ?>"
  });

  $("#show_list").DataTable({
      "bProcessing": true,
      "bServerSide": true,
      "bRetrieve": true,
      "searching": false,
      "aaSorting": [[ 2, "desc" ]],
      "oLanguage": {
        "sSearch": "<?php echo $tr['Account'] ;?>", //"会员帐号:",
        "sEmptyTable": "<?php echo $tr['no data'];?>", //"目前没有资料!",
        "sLengthMenu": "<?php echo $tr['each page'];?> _MENU_ <?php echo $tr['Count'];?>", //"每页显示 _MENU_ 笔",
        "sZeroRecords": "<?php echo $tr['no data'];?>", //"目前没有资料",
        "sInfo": "<?php echo $tr['now at'];?> _PAGE_ <?php echo $tr['total'];?> _PAGES_ <?php echo $tr['page'];?>", //"目前在第 _PAGE_ 页，共 _PAGES_ 页",
        "sInfoEmpty": "<?php echo $tr['no data'];?>", //"目前没有资料",
        "sInfoFiltered": "(<?php echo $tr['from'];?> _MAX_ <?php echo $tr['filtering in data'];?>)" //"(从 _MAX_ 笔资料中过滤)"
      },
      "ajax": "member_betlog_action.php?get=query_log<?php echo $query_sql; ?>",
      "columnDefs": [
          { className: "dt-right", "targets": [5,6] },
          { className: "dt-center", "targets": [0,1,3,4,7] }
      ],
      createdRow: function (row, data, dataIndex) {
        if ( data.totalpayout < 0 ) {
          $('td', row).eq(7).css( "color", "green" );
        }else{
          $('td', row).eq(7).css( "color", "red" );
        }

      },
      "columns": [
        { "data": "id"},
        { "data": "account"},
        { "data": "bettime"},
        { "data": "logintime"},
        // { "data": "logintime_html"}, 大約距今-不列
        { "data": "gamename"},
        // { "data": "gamecategory", "fnCreatedCell": function (nTd, sData, oData, iRow, iCol) {
        //     $(nTd).html("<a href=\"javascript:query_str(\'gc\',\'"+oData.gamecategory+"\')\" data-role=\"button\" >"+oData.gamecategory+"</a>");}},
        { "data": "gamecategory"},//遊戲分類暫不做分類搜尋
        { "data": "totalwager"},
        { "data": "totalpayout"},
        // { "data": "favorable_category", "fnCreatedCell": function (nTd, sData, oData, iRow, iCol) {
        // $(nTd).html("<a href=\"javascript:query_str(\'gt\',\'"+oData.favorable_category+"\')\" data-role=\"button\" >"+oData.favorable_category+"</a>");}},
        { "data": "casino"},
        { "data": "bet_status", "fnCreatedCell": function (nTd, sData, oData, iRow, iCol) {
          $(nTd).html("<button type=\"button\" class=\"btn betstatus"+oData.bet_statuscode+"\">"+oData.bet_status+"</button>");}},
        { "data": "detail_trans"}
      ]
  });


  $.getJSON("member_betlog_action.php?get=query_summary<?php echo $query_sql?>", function(result) {

      if(result.data == ''){
        $('#show_summary').html(expire_date_no_data(result));

      }else if(!result.logger){
        $("#show_summary").html(summary_tmpl(result));
        $("#csv").html(csv_download_tmpl(result));

      }else{
        $("#show_summary").html('');
        alert(result.logger);
      }
      // 原版
      // if(!result.logger){
      //     $("#show_summary").html(summary_tmpl(result));
      //     $("#csv").html(csv_download_tmpl(result));
      // }else{
      //     $("#show_summary").html('');
      //     alert(result.logger);
      // }
  });

  // -----------------------
  // summary沒資料
  function expire_date_no_data(param){
    // console.log(param);
    return `
      <table id="show_sum_list" class="table" cellspacing="2" width="100%" >
          <thead class="thead-inverse">
              <tr>
                <th style="text-align:center;"><?php echo $tr['Total number of bets during the search']; ?></th>
                <th style="text-align:center;"><?php echo $tr['Total betting amount during the inquiry period']; ?></th>
                <th style="text-align:center;"><?php echo $tr['Total profit and loss during the inquiry']; ?></th>
              </tr>
          </thead>
          <tbody id="show_sum_content">
              <tr>
                <td align="center">0</td>
                <td align="center">$0.00</td>
                <td align="center">$0.00</td>
              </tr>
          </tbody>
      </table>
  `
  }
  // -----------------------

  // 查詢
  $("#submit_to_inquiry").click(function(){
    // 帳號
    var account_query = <?php 
    if($member_overview_mode) echo "'".$account_query."'" ?? '';
    else echo "$(\"#account_query\").val()";
     ?>;
    // 所屬代理
    var agent_query = $('#agent').val();
    // 投注時間
    var query_betdate_start_datepicker = $("#query_betdate_start_datepicker").val();
    var query_betdate_end_datepicker = $("#query_betdate_end_datepicker").val();
    var betting_slip_num = $('#betting_slip_num').val();
    // var inning_number = $('#inning_number').val();
    var game_name = $('#game_name').val();
    // var betamount_lower = $('#betamount_lower').val();
    // var betamount_upper = $('#betamount_upper').val();
    var betvalid_lower = $('#betvalid_lower').val();
    var betvalid_upper = $('#betvalid_upper').val();
    var receive_lower = $('#receive_lower').val();
    var receive_upper = $('#receive_upper').val();

    // 派彩時間
    var query_date_start_datepicker = $("#query_date_start_datepicker").val();
    var query_date_end_datepicker = $("#query_date_end_datepicker").val();

    var betdate_start = new Date(query_betdate_start_datepicker.replace(/\-/g, "/"));
    var betdate_end = new Date(query_betdate_end_datepicker.replace(/\-/g, "/"));
    var payout_start = new Date(query_date_start_datepicker.replace(/\-/g, "/"));
    var payout_end = new Date(query_date_end_datepicker.replace(/\-/g, "/"));

    var today = new Date();
    // 最小搜尋時間
    var minDateTime = today.getFullYear()+'-'+(today.getMonth()-1)+'-'+today.getDate()+ ' ' +'00:00:00';
    // 最大搜尋時間
    var maxDateTime = today.getFullYear()+'-'+(today.getMonth()+1)+'-'+today.getDate()+ ' ' + '23:59:59';//today.getHours()+':'+today.getMinutes();

    // 投注開始時間<最小搜尋時間
    if((Date.parse(betdate_start)).valueOf() < (Date.parse(minDateTime)).valueOf()){
      alert('投注开始时间错误，请修改查询区间!');
      window.location.reload();
      return false;
    }
    // 投注開始時間>最大搜尋時間
    if((Date.parse(betdate_end)).valueOf() > (Date.parse(maxDateTime)).valueOf()){
      alert('投注结束时间错误，请修改查询区间!');
      window.location.reload();
      return false;
    }
    // 派彩開始時間<最小搜尋時間
    if((Date.parse(payout_start)).valueOf() < (Date.parse(minDateTime)).valueOf()){
      alert('派彩开始时间错误，请修改查询区间!');
      window.location.reload();
      return false;
    }
    // 派彩結束時間>最大搜尋時間
    if((Date.parse(payout_end)).valueOf() > (Date.parse(maxDateTime)).valueOf()){
      alert('派彩结束时间错误，请修改查询区间!');
      window.location.reload();
      return false;
    }

    // var sel_in_cl_bonus_list = $('[name="bns"]').serialize();
    var query_casino_favorable="";
      $("input:checkbox:checked[name=\"bns\"]").each( function()
        {
          query_casino_favorable=query_casino_favorable+"&casino_favorable_qy[]="+$(this).val();
        });
    // console.log(query_casino_favorable);
    // var gc_query = $("#gc_query").val();
    // var casino_query = $("#casino_query").val();
    var updating_str = `
    <h5 align="center">
      <?php echo $tr['Data query']; ?>...<img width="30px" height="30px" src="ui/loading.gif" />
    </h5>
    `;
    // 當全選或全不選注單狀態，查詢條件為空
    if (
       ($('input[name=status_sel]').length - $('input[name=status_sel]:checked').length) == 0 ||
       ($('input[name=status_sel]').length - $('input[name=status_sel]:checked').length) == $('input[name=status_sel]').length )
    {
      var status_query  = "";
    } else {
      var status_query  = "";
      $("input:checkbox:checked[name=\"status_sel\"]").each( function()
        {
          status_query=status_query+"&status_qy[]="+$(this).val();
        });
    }

    // var query_str = "&a="+account_query+"&sdate="+query_date_start_datepicker+"&edate="+query_date_end_datepicker+"&gc="+gc_query+"&casino="+casino_query+status_query;+'&betamount_lower='+betamount_lower+'&betamount_upper='+betamount_upper
    var query_str = "&a="+account_query+"&sdate="+query_date_start_datepicker+"&edate="+query_date_end_datepicker+status_query+query_casino_favorable+'&agent='+agent_query+'&betdate_start='+query_betdate_start_datepicker+'&betdate_end='+query_betdate_end_datepicker+'&betting_slip_num='+betting_slip_num+'&game_name='+game_name+'&betvalid_lower='+betvalid_lower+'&betvalid_upper='+betvalid_upper+'&receive_lower='+receive_lower+'&receive_upper='+receive_upper;

    $('.modal-btn').attr('disabled','disabled');

    $("#show_summary").html(updating_str);
    $.get("member_betlog_action.php?get=query_summary"+query_str,
      // { sel_in_cl_bonus_list:sel_in_cl_bonus_list},
      function(result){

        if(result.data == ''){
          $('#show_summary').html(expire_date_no_data(result));

        }else if(!result.logger){
          $("#show_summary").html(summary_tmpl(result));
          $("#csv").html(csv_download_tmpl(result));

        }else{
          $("#show_summary").html('');
          alert(result.logger);
        }


        // if(!result.logger){
        //   $("#show_summary").html(summary_tmpl(result));
        //   $("#csv").html(csv_download_tmpl(result));
        // }else{
        //   $("#show_summary").html('');
        //   alert(result.logger);
        // }
      },
      'json'
    );

    $("#show_list")
      .DataTable()
      .ajax.url("member_betlog_action.php?get=query_log"+query_str)
      .load();

    paginateScroll();
  });

  $(document).keydown(function(e) {
    if(!$('.modal').hasClass('show')){
     switch(e.which) {
         case 13: // enter key
             $("#submit_to_inquiry").trigger("click");
         break;
     }
   }
  });

  // 全選
  $('#select_all_checkbox').click(function () {
    fun_select_all();
    submit_select();
  });
  // 清空
  $('#cancel_select_all_checkbox').click(function () {
    fun_select_all_cancel();
    submit_select();
  });

  // MG PT按下時
  $('.modal-body .btn_cl_casino_cate').on('click',function(e){
    var casino_cate_btn=$(e.target).val();
    fun_select_all_cancel();
    $('.modal-body input[class^='+casino_cate_btn+']').prop('checked', true);
    submit_select();
  });

  //電子 棋牌等分類按下時
  $('.modal-body .btn_cl_bonus_cate').on('click',function(e){
    var bonus_cat_btn=$(e.target).val();
    fun_select_all_cancel();
    $('.modal-body input[value$='+bonus_cat_btn+']').prop('checked', true);
    submit_select();
  });

  // 子項目全選時，大分類選取;反之取消勾選
  $('.modal-body input:checkbox').on('change', function(e) {
    var parent_checkbox_class =  $(e.target).attr('class') + '_parent';
    var checkbox_class =$(e.target).attr('class');
    // console.log(checkbox_class);
    // console.log($('.'+checkbox_class+':checkbox').filter(':checked').length);
    // console.log($('.'+checkbox_class+':checkbox').length);
    if ($('.'+checkbox_class+':checkbox').length - $('.'+checkbox_class+':checkbox').filter(':checked').length == 0) {
      $('.'+parent_checkbox_class).prop('checked', true);
    } else {
      $('.'+parent_checkbox_class).prop('checked', false);
    }
    submit_select();
  });

});

</script>
<?php end_section(); ?>
<!-- end of extend_js -->