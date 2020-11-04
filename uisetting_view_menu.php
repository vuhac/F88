<?php
// ----------------------------------------------------------------------------
// Features:  後台 -- ui設定
// File Name:
// Author:     orange
// Related: uisetting_action
// DB Table:
// Log:
// ----------------------------------------------------------------------------

// ----------------------------------------------------------------------------
//----------------------------------------------------------------------------------------------
// 目錄
// 1.易記網址
// 2.客製化menu
//----------------------------------------------------------------------------------------------

//----------------------------------------------------------------------------------------------
// 易記網址
//----------------------------------------------------------------------------------------------
$short_url = <<<HTML
<form class="short_url mx-1 mb-2 form-inline">
  <div class="input-group mb-3">
    <label class="mx-1">{$tr['url']}：</label><input type="text" class="short_url_input form-control" placeholder="" name="short_url_input" required>
  </div>
  <div class="input-group mb-3">
    <input type="button" class="mx-1 short_url_save btn btn-success btn-sm" value="{$tr['save setting']}">
  </div>
</form>
HTML;

$short_url_js = <<<HTML
<script type="text/javascript">

$('.short_url').on('click','.short_url_save',function(){
  var getform = $('.short_url').get()[0];
  var index = ["short_url"];
  var data = [getform.elements['short_url_input'].value];
  json_send('update',index,data);
});
</script>
HTML;

//----------------------------------------------------------------------------------------------
// 客製化menu
//----------------------------------------------------------------------------------------------
//----------------------------------------------------------------------------------------------
// 客製化-更多連結
//----------------------------------------------------------------------------------------------
$menu_morelink = "";
$id            = array("header_morelink", "footer_morelink", "mobile_morelink");
$hint          = [$tr['The navigation Bar adds up to 3 links and the title is up to 8 characters.'], $tr['The footer adds up to 5 links and the title is up to 8 characters.'], $tr['The mobile version adds up to 5 links and a title of up to 8 characters.']];
for ($i = 0; $i < count($id); $i++) {
    if ($i == 0) {$act = " show active";} else { $act = "";}
    $menu_morelink .= <<<HTML
      <div class="tab-pane fade{$act}" id="{$id[$i]}" role="tabpanel">
          <div class="alert alert-info" role="alert">
            {$hint[$i]}
          </div>
          <form class="morelink_add mx-1 mb-2 form-inline uisetting_menu" id="menu">
          <div class="input-group mb-3 mr-1">
              <label class="mx-1">{$tr['image title']}：</label>
              <button data-selected="graduation-cap" type="button" class="icp icp-dd btn btn-default dropdown-toggle iconpicker-component" data-toggle="dropdown">
                    <i class="fas fa-star"></i>
                  <span class="caret"></span>
              </button>
              <div class="dropdown-menu"></div>
          </div>
          <div class="input-group mb-3">
            <label class="mx-1">{$tr['title']}：</label><input type="text" class="morelink-title form-control validate[required,maxSize[20]]" maxlength="20" placeholder="({$tr['max']}20{$tr['word']})" name="morelink-title" required>
          </div>
          <div class="input-group mb-3">
            <label class="mx-1">{$tr['link']}：</label><input type="text" class="morelink-link form-control" placeholder="" name="morelink-link" required>
          </div>
          <div class="input-group mb-3">
            <label class="mx-1">{$tr['target window']}:</label>
            <select class="morelink-target ml-2" name="morelink_target">
            　<option value="_self">{$tr['self local']}</option>
            　<option value="_blank">{$tr['blank new window']}</option>
            </select>
          </div>
          <div class="input-group mb-3">
            <input type="button" class="morelink_sent mx-1 btn btn-success btn-sm" value="{$tr['add']}">
            </div>
          </form>

        <table class="t-morelink table table-bordered table-hover table-align-middle">
          <thead class="thead-light">
            <tr>
              <th scope="col">{$tr['image title']}</th>
              <th scope="col">{$tr['title']}</th>
              <th scope="col">{$tr['link']}</th>
              <th scope="col">{$tr['target window']}</th>
              <th class="text-center" width="30px" scope="col">{$tr['delete']}</th>
            </tr>
          </thead>
          <tbody>
          </tbody>
        </table>

      </div>
HTML;
}

//----------------------------------------------------------------------------------------------
// 客製化-頁腳商標
//----------------------------------------------------------------------------------------------
$foocasinohostdir    = dirname(__FILE__) . '/in/component/custom_menu/foo_casino/'; //要读取的文档夹
$foocasinofilesnames = scandir($foocasinohostdir); //得到所有的文档
$foocasinofilesnames = array_splice($foocasinofilesnames, 2);

$foopayhostdir      = dirname(__FILE__) . '/in/component/custom_menu/foo_pay/'; //要读取的文档夹
$foopayfilesnames   = scandir($foopayhostdir); //得到所有的文档
$foopayfilesnames   = array_splice($foopayfilesnames, 2);
$foo_logo["casino"] = '';
$foo_logo["pay"]    = '';

foreach ($foocasinofilesnames as $name) {
    $foo_logo["casino"] .= "<div class='col-2 mb-2 d-flex align-items-center'>
    <input type='checkbox' name='foo_casino' value='" . substr($name, 0, -4) . "'>
    <img class='foologo' style='max-width:100%;max-height:50px;cursor:pointer;' src='in/component/custom_menu/foo_casino/" . $name . "' alt = '" . $name . "'></div>";
}

foreach ($foopayfilesnames as $name) {
    $foo_logo["pay"] .= "<div class='col-2 mb-3 d-flex align-items-center'>
    <input type='checkbox' class='mr-2' name='foo_pay' value='" . substr($name, 0, -4) . "'>
    <img class='foologo' style='max-width:100%;max-height:40px;cursor:pointer;' src='in/component/custom_menu/foo_pay/" . $name . "' alt = '" . $name . "'></div>";
}

$menu_footerlogo = <<<HTML
<h4>{$tr['Casino']}</h4>
<div id="footer_casino_logo" class="foo_control">
  <ul class="row mt-1 mb-3" >{$foo_logo["casino"]}</ul>
  <div class="btn-group" role="group">
    <button id="save" class="btn btn-success btn-sm">{$tr['save setting']}</button>
    <button id="selectall" class="btn btn-primary btn-sm">{$tr['select all']}</button>
    <button id="clear" class="btn btn-primary btn-sm">{$tr['clear all']}</button>
  </div>
</div>
<hr></hr>
<h4>{$tr['payment method']}</h4>
<div id="footer_payment_logo" class="foo_control">
  <ul class="row mt-2 mb-3" >{$foo_logo["pay"]}</ul>
  <div class="btn-group" role="group">
    <button id="save" class="btn btn-success btn-sm">{$tr['save setting']}</button>
    <button id="selectall" class="btn btn-primary btn-sm">{$tr['select all']}</button>
    <button id="clear" class="btn btn-primary btn-sm">{$tr['clear all']}</button>
  </div>
</div>
HTML;

//----------------------------------------------------------------------------------------------
// 客製化-hot 装饰
//----------------------------------------------------------------------------------------------
//var_dump($gamelobby_setting['main_category_info']);
$mct_item_arr = array();
foreach($gamelobby_setting['main_category_info'] as $mctid => $mct_arr){
    if(isset($tr['menu_'.strtolower($mctid)])){
      $mct_name = $tr['menu_'.strtolower($mctid)];
    }elseif(isset($tr[$mct_arr['name']])){
      $mct_name = $tr[$mct_arr['name']];
    }else{
      $mct_name = $mct_arr['name'];
    }
    if($mct_arr['open']=='0'){
      continue;
    }
    $mct_item_arr[$mct_arr['order']] = <<< HTML
    <input type="checkbox" name="{$mctid}"><li class="nav-item navi_{$mctid}"><a class="nav-link" href="javascript:void(0);" target="_self">{$mct_name}</a></li>
HTML;
}
  ksort($mct_item_arr);
  $mct_item = implode("\n",$mct_item_arr);
$menu_preview = <<<HTML
<div class="alert alert-info" role="alert">
        1.{$tr['Choose to emphasize the effect']} <i class="mx-2 fas fa-angle-right"></i> 2. {$tr['Click on the preview screen to decorate the menu']}
</div>
<form class="mx-1 mb-2 form-inline">
  <label>{$tr['cgoose effect']}:</label>
  <select id="hot_style" class="mx-2" name="hot_style">
  　<option value="style1">style1</option>
  　<option value="style2">style2</option>
  　<option value="style3">style3</option>
  　<option value="style4">style4</option>
  </select>
  <li id="hot_img" class="hot style1"></li>
</form>

<div class="mb-3 card">
  <h6 class="card-header"><i class="mr-2 fas fa-magic"></i>{$tr['Desktop version navigation area']} - ↓{$tr['Click the menu to add/remove effects']}</h6>
  <div class="card-body">
    <ul class="pre_cus_menu nav d-flex">
      <nav>
          <ul class="nav d-flex">
            {$mct_item}
            <input type="checkbox" name="promotions"><li class="nav-item navi_promotions"><a class="nav-link" href="javascript:void(0);" target="_self">{$tr['Promotions'] }</a></li>
            <input type="checkbox" name="service"><li class="nav-item navi_service"><a class="nav-link" href="javascript:void(0);" target="_self">{$tr['online service'] }</a></li>
          </ul>
        </nav>
    </ul>
  </div>
</div>

<div class="mb-1 card">
  <h6 class="card-header"><i class="mr-2 fas fa-magic"></i>{$tr['desktop footer']} - ↓{$tr['Click the menu to add/remove effects']}</h6>
  <div class="card-body">
    <ul class="pre_cus_menu nav w-100 d-flex">
        <input type="checkbox" name="f_about"><li class="nav-item navi_f_about"><a href="javascript:void(0);">{$tr['About us']}</a></li>
        <input type="checkbox" name="f_promotions"><li class="nav-item navi_f_promotions"><a href="javascript:void(0);">{$tr['Promotions']}</a></li>
        <input type="checkbox" name="f_partner"><li class="nav-item navi_f_partner"><a href="javascript:void(0);">{$tr['Partner']}</a></li>
        <input type="checkbox" name="f_howtodeposit"><li class="nav-item navi_f_howtodeposit"><a href="javascript:void(0);">{$tr['How to deposit']}</a></li>
        <input type="checkbox" name="f_howtowithdraw"><li class="nav-item navi_f_howtowithdraw"><a href="javascript:void(0);">{$tr['How Withdrawal']}</a></li>
        <input type="checkbox" name="f_contactus"><li class="nav-item navi_f_contactus"><a href="javascript:void(0);">{$tr['Contact US']}</a></li>
        <input type="checkbox" name="f_mobile"><li class="nav-item navi_f_mobile"><a href="javascript:void(0);">{$tr['mobile bet']}</a></li>
    </ul>
  </div>
</div>
<button id="draw_menu" type="button" class="mb-4 btn btn-success btn-sm ml-auto">{$tr['save setting']}</button>
<button id="clear_draw_menu" type="button" class="mb-4 ml-3 btn btn-primary btn-sm ml-auto">{$tr['clear all']}</button>
<div id='menu_response' style='display:none;'></div>

<div id="morelink_card" class="mb-4 card">
    <div class="card-header">
      <h6><i class="mr-2 fas fa-plus"></i>{$tr['add more links']}</h6>
        <ul class="nav nav-tabs card-header-tabs" id="myTab" role="tablist">
          <li class="nav-item">
            <a class="nav-link active" data-toggle="tab" href="#header_morelink" role="tab" aria-controls="header" aria-selected="true">{$tr['Desktop version navigation area']}</a>
          </li>
          <li class="nav-item">
            <a class="nav-link" data-toggle="tab" href="#footer_morelink" role="tab" aria-controls="footer" aria-selected="false">{$tr['desktop footer']}</a>
          </li>
          <li class="nav-item">
            <a class="nav-link" data-toggle="tab" href="#mobile_morelink" role="tab" aria-controls="mobile" aria-selected="false">{$tr['mobile menu'] }</a>
          </li>
        </ul>
    </div>
  <div class="card-body">
    <div class="tab-content" id="nav-tabContent">
      $menu_morelink
    </div>
  </div>
</div>

<div id="footer_logo" class="card">
    <div class="card-header">
      <h6><i class="mr-2 fas fa-plus"></i>{$tr['desktop footer trademark list']}</h6>
    </div>
  <div class="card-body">
    <div class="alert alert-info" role="alert">
          {$tr['Select the trademark to be displayed at the front desk of the table machine']}
    </div>
    $menu_footerlogo
  </div>
</div>
HTML;

$menu_preview_css = <<<HTML
<link rel="stylesheet" type="text/css" href="./in/component/highlight_menu/style.css">
<link rel="stylesheet" type="text/css" href="in/iconpicker/css/fontawesome-iconpicker.min.css">
<script src="in/iconpicker/js/fontawesome-iconpicker.min.js"></script>
<script type="text/javascript">
  $('.icp-dd').iconpicker();
</script>
  <script src="./in/jQuery-Validation-Engine/js/languages/jquery.validationEngine-zh_CN.js" type="text/javascript" charset="utf-8"></script>
  <script src="./in/jQuery-Validation-Engine/js/jquery.validationEngine.js" type="text/javascript" charset="utf-8"></script>
  <link rel="stylesheet" href="./in/jQuery-Validation-Engine/css/validationEngine.jquery.css" type="text/css"/>

  <script type="text/javascript" language="javascript" class="init">
      $(document).ready(function () {
          $(".uisetting_menu").validationEngine();
      });
  </script>
<style type="text/css">
.popover-content{
    width: 230px;
}
.fade.in{
    opacity: 1;
}
</style>
<script type="text/javascript">
function sortmorelinkdata(tabid,uisetting_data){
  if(tabid=="header_morelink")
      return uisetting_data.custom_menu;
    else if(tabid=="footer_morelink")
      return uisetting_data.footer_menu.footer_link;
    else
      return uisetting_data.mobile.mobile_morelink;
}
function init_morelink_table(uisetting_data){
  $("#view_menu #nav-tabContent .tab-pane").each(function(){
    var tabid=$(this).attr("id");    
    var uidata = sortmorelinkdata(tabid,uisetting_data);

    $("#view_menu #"+tabid+" .t-morelink").DataTable( {
      "bPaginate": false, // 顯示換頁
      "searching": false, // 顯示搜尋
      "info": false, // 顯示資訊
      "fixedHeader": false, // 標題置頂
      "bAutoWidth": false,
      "bSort": false,
      "data": uidata,
        columns: [
            { "mData":"icon",
              "mRender": function(data) {
              return '<i class="'+data+'"></i>';
            }},
            { "data": "title" },
            { "data": "link" },
            { "data": "target" },
            { "mRender": function(data, type, full) {
              return '<div class="btn-group btn-group-sm" role="group">\
                <button type="button" class="btn-delete btn btn-danger btn-sm"><i class="fas fa-trash-alt"></i></button>\
              </div>';
            }}
        ]
    });
  });
}
function init_shorturl(uisetting_data){
  $('.short_url_input').val(uisetting_data['short_url'][0]);
}
function init_highlight(uisetting_data){
  if (typeof uisetting_data["highlight_menu"]!="undefined") {
          for (var i = 0; i < uisetting_data["highlight_menu"].length; i++) {
            $(".navi_"+uisetting_data["highlight_menu"][i][0]).addClass(uisetting_data["highlight_menu"][i][1]+" hot");
          }
        }
        $(".pre_cus_menu .nav-item").each(function(){
        if($(this).hasClass("hot")){
           $(this).prev(':checkbox').prop("checked", true);
        }
      });
}
function init_footerlogo(uisetting_data){
  var foo_logo=['footer_casino_logo','footer_payment_logo'];
      foo_logo.forEach(function(item, index){
        for (i=0; i < uisetting_data["footer_menu"][item].length; i++) {
          $("#"+item+" input").each(function(){
            if($(this).attr('value')==uisetting_data["footer_menu"][item][i])
              $(this).prop("checked",true);
          });
        }
     });
}
$(document).ready(function(){
  /*
  $.ajax({
     url: 'uisetting_action.php?act=get&cid={$component_id}',
     success: function(json) {
      //console.log(json)
      var data = JSON.parse(json);
      init_morelink_table(data);
      init_carousel_table(data);

    //short_url
      $('.short_url_input').val(data['short_url'][0]);

    //預覽menu highlight讀取
      if (typeof data["highlight_menu"]!="undefined") {
          for (var i = 0; i < data["highlight_menu"].length; i++) {
            $(".navi_"+data["highlight_menu"][i][0]).addClass(data["highlight_menu"][i][1]+" hot");
          }
        }
        $(".pre_cus_menu .nav-item").each(function(){
        if($(this).hasClass("hot")){
           $(this).prev(':checkbox').prop("checked", true);
        }
      });
    //footer logo
      var foo_logo=['footer_casino_logo','footer_payment_logo'];
      foo_logo.forEach(function(item, index){
        for (i=0; i < data["footer_menu"][item].length; i++) {
          $("#"+item+" input").each(function(){
            if($(this).attr('value')==data["footer_menu"][item][i])
              $(this).prop("checked",true);
          });
        }
     });

     }
  });*/

  //select切換hot樣式
  $('select#hot_style').on('change', function() {
    $("#hot_img").removeClass().addClass("hot "+this.value);
  })

  //預覽框內點擊li之動作
  $(".pre_cus_menu .nav-item").click(function(){
      $(this).toggleClass("hot");
      $(this).removeClass (function (index, className) {
          return (className.match (/(^|\s)style\S+/g) || []).join(' ');
      }).addClass($("#hot_style").find(":selected").val());
      var checkbox = $(this).prev(':checkbox');
      checkbox.prop("checked", !checkbox.prop("checked"));
  });

  //清除highlightmenu
  $("#view_menu").on('click', '#clear_draw_menu', function(){
    $(".pre_cus_menu .nav-item").removeClass (function (index, className) {
          return (className.match (/(^|\s)style\S+/g) || []).join(' ');
      }+" hot").prev(':checkbox').prop("checked", false);
  });

  //儲存highlightmenu
  $("#view_menu").on('click', '#draw_menu', function(){
    var highlight_val=Array();
    $(".pre_cus_menu .nav-item").each(function(){
      if($(this).prev(':checkbox').prop("checked")){
        var val = [$(this).prev(':checkbox').attr('name'),$(this).attr("class").match (/(^|\s)style\S+/g)[0]];
        highlight_val.push(val);
      }
    });
    //console.log(highlight_val);
    var index = "highlight_menu";
    json_send('update',index,highlight_val);
  });

  //morelink新增連結ajax傳遞陣列到php之動作
$("#view_menu #morelink_card .tab-pane").each(function(){
  var morelink_tab = $(this).attr("id");

  function addmorelink(update_index,morelink_val){
    json_send('trigger',update_index,morelink_val,function(){
      //$("#"+morelink_tab+" .t-morelink").DataTable().ajax.reload();
      json_get(function(data){        
        data = sortmorelinkdata(morelink_tab,data);
        $("#"+morelink_tab+" .t-morelink").DataTable().clear();
        $("#"+morelink_tab+" .t-morelink").DataTable().rows.add(data).draw();
      });
    });
  };  

  $(document).on('click', '#'+morelink_tab+' .morelink_sent', function(){
    var add_form = $('#'+morelink_tab+' .morelink_add').get();
    //console.log(add_form);
    var morelink_val={
      "icon":$('#'+morelink_tab+' .morelink_add .icp-dd i').attr("class"),
      "title":add_form[0].elements['morelink-title'].value,
      "link":add_form[0].elements['morelink-link'].value,
      "target":$("#"+morelink_tab+" .morelink-target :selected").val()
    };
    if (morelink_val["title"] == "" || morelink_val["link"] == "") {
        alert("{$tr['Please do not blank the form']}");
      }
    else{
      if(morelink_val["title"].length >8){
          alert("{$tr['title letters limit']}");
        }
      else{
        if(morelink_tab == "header_morelink"){
          if($("#"+morelink_tab+" .t-morelink").DataTable().data().count() >= 3){
            alert("{$tr['header morelinks limit']}");
          }
          else{
            var update_index = "custom_menu";
            add_form[0].reset();
            addmorelink(update_index,morelink_val);
          }
        }
        else if(morelink_tab == "footer_morelink"){
          if($("#"+morelink_tab+" .t-morelink").DataTable().data().count() >= 5){
            alert("{$tr['footer morelinks limit']}");
          }
          else{
            var update_index = ["footer_menu","footer_link"];
            add_form[0].reset();
            addmorelink(update_index,morelink_val);
          }
        }
        else {
          if($("#"+morelink_tab+" .t-morelink").DataTable().data().count() >= 5){
            alert("{$tr['mobile morelinks limit']}");
          }
          else{
            var update_index = ["mobile","mobile_morelink"];
            add_form[0].reset();
            addmorelink(update_index,morelink_val);
          }
        }
      }
    }
  });

  //刪除morelink動作
  $("#"+morelink_tab+" .t-morelink").on('click', '.btn-delete', function() {

     if(morelink_tab == "header_morelink")
        delete_index = ["custom_menu",$(this).closest('tr').index()];
     else if(morelink_tab == "footer_morelink")
        delete_index = ["footer_menu","footer_link",$(this).closest('tr').index()];
     else
        delete_index = ["mobile","mobile_morelink",$(this).closest('tr').index()];
        json_send('delete',delete_index,[],function(){

          //$("#"+morelink_tab+" .t-morelink").DataTable().ajax.reload();
            json_get(function(data){        
              data = sortmorelinkdata(morelink_tab,data);
              $("#"+morelink_tab+" .t-morelink").DataTable().clear();
              $("#"+morelink_tab+" .t-morelink").DataTable().rows.add(data).draw();
            });
        });
  });
});

$('#footer_logo').on('click', '.foologo', function() {
  var checkbox = $(this).prev("input");
  checkbox.prop("checked",!checkbox.prop("checked"));
});
//footerlogo動作
$('#footer_logo .foo_control').each(function(){
  var act_control = $(this).attr("id");
    $(document).on('click','#'+act_control+' button', function() {
      switch ($(this).attr("id")) {
        case 'clear':
          $('#'+act_control+' input').each(function(){
            $(this).prop("checked",false);
          });
          break;
        case 'save':
          var temp = Array();
          $('#'+act_control+' input').each(function(){
            if($(this).prop("checked")==true){
              temp.push($(this).val());
            }
          });
          var index = ["footer_menu",act_control];
          //console.log(temp);
          json_send('update',index,temp);
          break;
        case 'selectall':
          $('#'+act_control+' input').each(function(){
            $(this).prop("checked",true);
          });
          break;
        default:
        break;
      }
  });
});
});
</script>
HTML;
//-------------------------------------------------------------------------------------------
//card區塊分區塊 並放入templ
//-------------------------------------------------------------------------------------------
$card_templ   = '';
$card_content = array(
    0 => array('<i class="mr-2 fas fa-link"></i>'.$tr['easy to remember website'], $short_url),
    1 => array('<i class="mr-2 fas fa-palette"></i>'.$tr['custom menu'].'  <button type="button" data-target="#howtouisetting" data-toggle="modal" class="float-right btn btn-secondary btn-sm rounded-circle mx-2"><i class="fas fa-question fa-xs"></i></button>  ', $menu_preview),
);
for ($i = 0; $i < count($card_content); $i++) {
    $card_templ .= <<<HTML
    <div class="card mt-3 mb-4">
      <h5 class="card-header">
        {$card_content[$i][0]}
      </h5>
      <div class="card-body">
        {$card_content[$i][1]}
      </div>
    </div>
HTML;

}

//將html放入content
$uisetting_view_menu = '';
$uisetting_view_menu .= $card_templ . <<<HTML
<div class="modal fade" id="howtouisetting" tabindex="-1" role="dialog"  aria-labelledby="myLargeModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">{$tr['description']}</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
        <div class="w-100">
        <img class="w-100" style="height: auto;" src="in/component/preview/howtouse_custommenu.jpg" alt="">
        </div>
      </div>
    </div>
  </div>
</div>
HTML;

$extend_js .= '
<style>
.table.dataTable.no-footer{
  border-bottom:0;
}
li.hot{
  position: relative;
}
li.hot a{
  color: #dc3545;
}
li.hot::before{
  position: absolute;
  top: -0.2em;
  right: -0.1em;
  z-index: 2;
}
.pre_cus_menu input{
display: none;
}
li#hot_img{list-style: none;width: 60px;height: 27px}
li#hot_img.hot::before{
  list-style: none;
  top: 0;
  right: calc(50% - 12px);
}
.t-morelink td:nth-child(4) {
    text-align : center;
}
</style>
' . $short_url_js . $menu_preview_css;
