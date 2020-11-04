<?php
// ----------------------------------------------------------------------------
// Features:  后台 -- ui设置
// File Name:
// Author:     orange
// Related: uisetting_action
// DB Table:
// Log:
// ----------------------------------------------------------------------------
//----------------------------------------------------------------------------------------------
// 目录
// 3.浮动广告设置

//----------------------------------------------------------------------------------------------

//----------------------------------------------------------------------------------------------
// 浮动广告设置
//----------------------------------------------------------------------------------------------

//----------------------------------------------------------------------------------------------
// 7个按钮
//----------------------------------------------------------------------------------------------
function component_btn_set($component_tab)
{
  global $view_mode;
  global $ui_data;
  global $tr;    
  global $maindomain_data;
  $have     = 'far fa-check-square';
  $none     = 'far fa-square';
  $isswitch = array(
      ($ui_data[$component_tab]['float-promote']['lt'][0]['switch']) ? $have : $none,
      ($ui_data[$component_tab]['float-promote']['rt'][0]['switch']) ? $have : $none,
      ($ui_data[$component_tab]['aside-promote']['left'][0]['switch']) ? $have : $none,
      ($ui_data[$component_tab]['popup-promote'][0]['switch']) ? $have : $none,
      ($ui_data[$component_tab]['aside-promote']['right'][0]['switch']) ? $have : $none,
      ($ui_data[$component_tab]['float-promote']['lb'][0]['switch']) ? $have : $none,
      ($ui_data[$component_tab]['float-promote']['rb'][0]['switch']) ? $have : $none,
  );
  $lock = 'ui-locked';
  $unlock = '';
  $maindomain_isswitch = array(
      0=>(isset($maindomain_data[$component_tab]['float-promote']['lt'][0])) ? $lock : $unlock,
      1=>(isset($maindomain_data[$component_tab]['float-promote']['rt'][0])) ? $lock : $unlock,
      2=>(isset($maindomain_data[$component_tab]['aside-promote']['left'][0])) ? $lock : $unlock,
      3=>(isset($maindomain_data[$component_tab]['popup-promote'][0])) ? $lock : $unlock,
      4=>(isset($maindomain_data[$component_tab]['aside-promote']['right'][0])) ? $lock : $unlock,
      5=>(isset($maindomain_data[$component_tab]['float-promote']['lb'][0])) ? $lock : $unlock,
      6=>(isset($maindomain_data[$component_tab]['float-promote']['rb'][0])) ? $lock : $unlock,
  );
  // var_dump($maindomain_isswitch);
  // die();

if($view_mode=='maindomain'){
$templ_body = <<<HTML
<div id="templ_body" class="card">
  <div class="card-header">
    {$tr['front page']}
  </div>
  <div class="card-body">
    <div class="row">
      <div class="col-4 block">
        <button type="button" id="float-promote" name="float-promote" value="lt" class="btn btn-sense btn-lg"><i class="{$isswitch[0]} fa-3x"></i></button>
      </div>
      <div class="col-4 ml-auto d-flex align-items-start justify-content-end">
        <button type="button" id="float-promote" name="float-promote" value="rt" class="btn btn-sense btn-lg"><i class="{$isswitch[1]} fa-3x"></i></button>
      </div>
    </div>
    <div class="row">
      <div class="col-2 d-flex align-items-center">
        <button type="button" id="aside-promote" value="left" class="btn btn-sense btn-lg"><i class="{$isswitch[2]} fa-3x"></i></button>
      </div>
      <div class="col-8 popup d-flex align-items-center justify-content-center">
        <button type="button" id="popup-promote" class="btn btn-sense btn-lg"><i class="{$isswitch[3]} fa-3x"></i></button>
      </div>
      <div class="col-2 d-flex justify-content-end align-items-center">
        <button type="button" id="aside-promote" value="right" class="btn btn-sense btn-lg"><i class="{$isswitch[4]} fa-3x"></i></button>
      </div>
    </div>
    <div class="row">
      <div class="col-4 d-flex align-items-end">
        <button type="button" id="float-promote" name="float-promote" value="lb" class="btn btn-sense btn-lg"><i class="{$isswitch[5]} fa-3x"></i></button>
      </div>
      <div class="col-4 ml-auto block  d-flex justify-content-end align-items-end">
        <button type="button" id="float-promote" name="float-promote" value="rb" class="btn btn-sense btn-lg"><i class="{$isswitch[6]} fa-3x"></i></button>
      </div>
    </div>
  </div>
</div>
HTML;

}else{
    $templ_body = <<<HTML
<div id="templ_body" class="card">
  <div class="card-header">
    {$tr['front page']}
  </div>
  <div class="card-body">
    <div class="row">
      <div class="col-4 block">
        <button type="button" id="float-promote" name="float-promote" value="lt" class="btn btn-sense btn-lg {$maindomain_isswitch[0]}"><i class="{$isswitch[0]} fa-3x" data-locked="{$maindomain_isswitch[0]}"></i></button>
      </div>
      <div class="col-4 ml-auto d-flex align-items-start justify-content-end">
        <button type="button" id="float-promote" name="float-promote" value="rt" class="btn btn-sense btn-lg {$maindomain_isswitch[1]}"><i class="{$isswitch[1]} fa-3x" data-locked="{$maindomain_isswitch[1]}"></i></button>
      </div>
    </div>
    <div class="row">
      <div class="col-2 d-flex align-items-center">
        <button type="button" id="aside-promote" value="left" class="btn btn-sense btn-lg {$maindomain_isswitch[2]}"><i class="{$isswitch[2]} fa-3x" data-locked="{$maindomain_isswitch[2]}"></i></button>
      </div>
      <div class="col-8 popup d-flex align-items-center justify-content-center">
        <button type="button" id="popup-promote" class="btn btn-sense btn-lg {$maindomain_isswitch[3]}"><i class="{$isswitch[3]} fa-3x" data-locked="{$maindomain_isswitch[3]}"></i></button>
      </div>
      <div class="col-2 d-flex justify-content-end align-items-center">
        <button type="button" id="aside-promote" value="right" class="btn btn-sense btn-lg {$maindomain_isswitch[4]}"><i class="{$isswitch[4]} fa-3x" data-locked="{$maindomain_isswitch[4]}"></i></button>
      </div>
    </div>
    <div class="row">
      <div class="col-4 d-flex align-items-end">
        <button type="button" id="float-promote" name="float-promote" value="lb" class="btn btn-sense btn-lg {$maindomain_isswitch[5]}"><i class="{$isswitch[5]} fa-3x" data-locked="{$maindomain_isswitch[5]}"></i></button>
      </div>
      <div class="col-4 ml-auto block  d-flex justify-content-end align-items-end">
        <button type="button" id="float-promote" name="float-promote" value="rb" class="btn btn-sense btn-lg {$maindomain_isswitch[6]}"><i class="{$isswitch[6]} fa-3x" data-locked="{$maindomain_isswitch[6]}"></i></button>
      </div>
    </div>
  </div>
</div>
HTML;
}
    return $templ_body;
}
//----------------------------------------------------------------------------------------------
// 表单部分
//----------------------------------------------------------------------------------------------
function promoteform($tab)
{
  global $tr;
  global $view_mode;
  $promoteform = '';
  $aside_form = '';

  //aside 表单(子网域前台管理才有)
$aside_form=<<<HTML
<!--aside-slide 表单-->
<form class="aside_slide_form pb-2 mb-4 border-bottom w-100">
  <div class="input-group mb-3">
    <label class="mx-1">{$tr['animation type']}：</label>
    <button type="button" value="float" class="mx-1 btn btn-secondary btn-sm btn-change-type">{$tr['float animation']}</button>
    <button type="button" class="mx-1 btn btn-light btn-sm disabled"><i class=" mr-2 fa fa-check"></i>{$tr['slide animation']}</button>
  </div>
  <div class="input-group mb-3">
    <label class="mx-1">{$tr['slide title']}：</label><input type="text" class="form-control" placeholder="" name="aside-slide-link-title" required>
    <input type="button" class="aside-slide-save mx-1 btn btn-success btn-sm" value="{$tr['save setting']}">
  </div>
  <div class="input-group mb-3">
    <label class="mx-1">{$tr['Theme style']}：</label><button type="button" class="mx-1 btn btn-primary btn-sm" onclick="$('.tab-pane-promote.active').find('.aside-slide-modal').modal('show');">{$tr['select style']}</button>
  </div>
</form>

<!--aside-float 表单-->
<form class="aside_float_form pb-2 mb-4 border-bottom w-100">
  <div class="input-group mb-3">
    <label class="mx-1">{$tr['animation type']}：</label>
    <button type="button" class="mx-1 btn btn-light btn-sm disabled"><i class=" mr-2 fa fa-check"></i>{$tr['float animation']}</button>
    <button type="button" value="slide" class="mx-1 btn btn-secondary btn-sm btn-change-type">{$tr['slide animation']}</button>
  </div>
  <div class="input-group mb-3">
    <label class="mx-1">{$tr['Theme style']}：</label><button type="button" class="mx-1 btn btn-primary btn-sm" onclick="$('.tab-pane-promote.active').find('.aside-float-modal').modal('show');">{$tr['select style']}</button>
  </div>
  <div class="input-group align-items-center mb-3">
    <label class="mx-1 mr-2">{$tr['closeable']}：</label><input class="closeable" type="checkbox"/>
  </div>
</form>
HTML;

    $promoteform .= <<<HTML
<!--popup 表单-->
<form name="popup_pro_form" class="popup_pro_form w-100">
  <div class="input-group mb-3  pb-4 border-bottom">
    <label class="mx-1">{$tr['Theme style']}：</label><button type="button" class="mx-1 btn btn-primary btn-sm" onclick="$('.tab-pane-promote.active').find('.popup-modal').modal('show');">{$tr['select style']}</button>
  </div>
  <div class="input-group mb-3">
    <label class="mx-1">{$tr['main title']}：</label><input type="text" class="form-control" placeholder="" name="popup-link-title" required>
  </div>
  <div class="input-group mb-3">
      <label class="mx-1">
      <span class="glyphicon glyphicon-info-sign mr-2" data-toggle="tooltip" data-placement="top"  title="{$tr['Image file does not exceed 2MB']}"></span>
      {$tr['images']}：</label>
      <div class="dropdown">
        <button class="btn btn-secondary dropdown-toggle" type="button" id="dropdownMenuButton" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
          {$tr['select style']}
        </button>
        <div class="dropdown-menu"  id="v-pills-tab" role="tablist" aria-labelledby="dropdownMenuButton">
          <div class="nav flex-column nav-pills" id="v-pills-tab" role="tablist" aria-orientation="vertical">
            <a class="dropdown-item active" data-group="img" data-toggle="pill" href="#v-pills-{$tab}-promte-upload" role="tab" aria-selected="true">{$tr['upload image']}</a>
            <a class="dropdown-item" data-group="img" data-toggle="pill" href="#v-pills-{$tab}-promte-url" role="tab" aria-selected="false">{$tr['image url']}</a>
          </div>
        </div>
      </div>
      <div class="tab-content ml-2" id="v-pills-tabContent">
        <div class="tab-pane fade show active" id="v-pills-{$tab}-promte-upload" role="tabpanel">
          <input class="mt-1" type="file" accept="image/*" name="file" id="upload_promote_img" >
        </div>
        <div class="tab-pane fade" id="v-pills-{$tab}-promte-url" role="tabpanel">
          <input type="text" class="form-control mt-1" id="text_promote_img" placeholder="{$tr['Enter image URL']}">
        </div>
      </div>

    </div>
    <div class="input-group mb-3">
      <label class="mx-1">{$tr['link']}：</label><input type="text" class="form-control" placeholder="" name="promote_link" required>
    </div>
    <div class="input-group mb-3">
      <label class="mx-1">{$tr['target window']}:</label>
      <select class="popup_promote_target ml-2" name="popup_promote_target">
      　<option value="_self">{$tr['self local']}</option>
      　<option value="_blank">{$tr['blank new window']}</option>
      </select>
    </div>
    <div class="input-group mb-3">
      <input type="button" class="popup-save mx-1 btn btn-success btn-sm" value="{$tr['save setting']}">
      </div>
</form>

<!--float 表单-->
<div class="w-100 form-float">
  <form name="float_pro_form" class="float_pro_form mx-1 mb-2">
    <div class="input-group mb-3">
      <label class="mx-1">
      <span class="glyphicon glyphicon-info-sign mr-2" data-toggle="tooltip" data-placement="top"  title="{$tr['float ad tips']}"></span>
      {$tr['images']}：</label>

      <div class="dropdown">
        <button class="btn btn-secondary dropdown-toggle" type="button" id="dropdownMenuButton" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
          {$tr['options']}
        </button>
        <div class="dropdown-menu"  id="v-pills-tab" role="tablist" aria-labelledby="dropdownMenuButton">
          <div class="nav flex-column nav-pills" id="v-pills-tab" role="tablist" aria-orientation="vertical">
            <a class="dropdown-item active" data-group="img" data-toggle="pill" href="#v-pills-{$tab}-float-upload" role="tab" aria-selected="true">{$tr['upload image']}</a>
            <a class="dropdown-item" data-group="img" data-toggle="pill" href="#v-pills-{$tab}-float-url" role="tab" aria-selected="false">{$tr['image url']}</a>
          </div>
        </div>
      </div>
      <div class="tab-content ml-2" id="v-pills-tabContent">
        <div class="tab-pane fade show active" id="v-pills-{$tab}-float-upload" role="tabpanel">
          <input class="mt-1" type="file" accept="image/*" name="file" id="upload_promote_img" >
        </div>
        <div class="tab-pane fade" id="v-pills-{$tab}-float-url" role="tabpanel">
          <input type="text" class="form-control mt-1" id="text_promote_img" placeholder="{$tr['enter image url']}">
        </div>
      </div>
      
    </div>
    <div class="input-group mb-3">
      <label class="mx-1">{$tr['link']}：</label><input type="text" class="form-control" placeholder="" name="promote_link" required>
    </div>
    <div class="input-group mb-3">
      <label class="mx-1">{$tr['target window']}:</label>
      <select class="promote_target ml-2" name="promote_target">
      　<option value="_self">{$tr['self local']}</option>
      　<option value="_blank">{$tr['blank new window']}</option>
      </select>
    </div>
    <div class="input-group mb-3">
      <input type="button" class="float-save mx-1 btn btn-success btn-sm" value="{$tr['save setting']}">
      </div>
  </form>
</div>

{$aside_form}

<!-- 添加区块-->
<div class="form-aside">
  <form class="aside-form mx-1 mb-2 ad_form" id="ad_form">
    <span class="glyphicon glyphicon-info-sign mr-2 float-left" data-toggle="tooltip" data-placement="top"  title="{$tr['Do not use HTML syntax']}"></span>
    <h4>
   {$tr['add link block']}
    </h4>
    <div class="input-group mb-3">
      <label class="mx-1">{$tr['title']}：</label><input type="text" class="form-control validate[maxSize[20]]" placeholder="({$tr['max']}20{$tr['word']})" maxlength="20" name="aside-link-title" required>
      <label class="mx-1">{$tr['content']}：</label><input type="text" class="form-control validate[maxSize[50]]" placeholder="({$tr['max']}50{$tr['word']})" maxlength="50" name="aside-link-txt" required>
    </div>
    <div class="input-group mb-3">
      <label class="mx-1">{$tr['link']}：</label><input type="text" class="form-control" placeholder="" name="aside-link-url" required>
      <label class="mx-1">{$tr['target window']}:</label>
      <select class="ml-2 aside-link-target" name="aside-link-target">
      　<option value="_self">_self</option>
      　<option value="_blank">_blank</option>
      </select>
    </div>

    <div class="input-group mb-3">
      <input type="button" class="mx-1 btn btn-success btn-sm aside-add" value="{$tr['add']}">
    </div>
  </form>

  <table class="t-aside-pro table table-bordered table-hover table-align-middle">
    <thead class="thead-light">
      <tr>
        <th scope="col">{$tr['title']}</th>
        <th scope="col">{$tr['content']}</th>
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
    return $promoteform;
}

function style_modal($value)
{
  global $view_mode;
  global $tr;
  $style_preview_img = array(
    "popup-modal"       => "",
    "aside-float-modal" => "",
    "aside-slide-modal" => "",
);

  $floathostdir    = dirname(__FILE__) . '/in/component/preview/float/'; //要读取的文档夹
  $floatfilesnames = scandir($floathostdir); //得到所有的文档
  $floatfilesnames = array_splice($floatfilesnames, 2);

  $slidehostdir    = dirname(__FILE__) . '/in/component/preview/slide/'; //要读取的文档夹
  $slidefilesnames = scandir($slidehostdir); //得到所有的文档
  $slidefilesnames = array_splice($slidefilesnames, 2);

  foreach ($floatfilesnames as $name) {
      $style_preview_img["aside-float-modal"] .= "<label class='col-2 mb-4'>
    <div class='w-100 mb-2 text-center'><input type='radio' name='" . $value . "_float_style' value='" . substr($name, 0, -4) . "'> " . substr($name, 0, -4) . "</div>
    <div class='w-100 text-center'><img class='w-100' src='in/component/preview/float/" . $name . "' alt = '" . $name . "'></div></label>";
  }
  foreach ($slidefilesnames as $name) {
      $style_preview_img["aside-slide-modal"] .= "<label class='col-3 mb-4'>
    <div class='w-100 mb-2 text-center'><input type='radio' name='" . $value . "_slide_style' value='" . substr($name, 0, -4) . "'> " . substr($name, 0, -4) . "</div>
    <div class='w-100 text-center'><img class='w-100' src='in/component/preview/slide/" . $name . "' alt = '" . $name . "'></div></label>";
  }

  $style_modal_list = array("popup-modal", "aside-float-modal", "aside-slide-modal");
  $save_list        = array("popup-modal" => "popup-save", "aside-float-modal" => "aside-float-save", "aside-slide-modal" => "aside-slide-save");

    $popuphostdir    = dirname(__FILE__) . '/in/component/preview/popup/'; //要读取的文档夹
    $popupfilesnames = scandir($popuphostdir); //得到所有的文档
    $popupfilesnames = array_splice($popupfilesnames, 2);
    
    foreach ($popupfilesnames as $name) {
        $style_preview_img["popup-modal"] .= "<label class='col-6 mb-4'>
      <div class='w-100 mb-2 text-center'><input type='radio' name='" . $value . "_popup_style' value='" . substr($name, 0, -4) . "'> " . substr($name, 0, -4) . "</div>
      <div class='w-100 text-center'><img class='w-100' src='in/component/preview/popup/" . $name . "' alt = '" . $name . "'></div></label>";
    }
    
    $style_modal      = '';
    foreach ($style_modal_list as $value) {
        $style_modal .= <<<HTML
  <!--{$value}-->
  <div class="modal fade {$value}" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="exampleModalLabel">{$tr['select style']}</h5>
          <button type="button" class="close" data-dismiss="modal" aria-label="Close">
            <span aria-hidden="true">&times;</span>
          </button>
        </div>
        <div class="modal-body">
          <div class="row">
            {$style_preview_img["{$value}"]}
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-success {$save_list["{$value}"]}">{$tr['save setting']}</button>
        </div>
      </div>
    </div>
  </div>
HTML;
    }
    return $style_modal;
}
//----------------------------------------------------------------------------------------------
// 导览列部分
//----------------------------------------------------------------------------------------------
$data_ui_key             = array("home", "gamelobby", "static");
$promotion_setting_table = ""; //初始化table

foreach ($data_ui_key as $value) {
    $modal_set = style_modal($value);
//将数据放入tab分页-----------------------------------
    if ($value == "home") {
        $tab_active = " active show";
    } else {
        $tab_active = "";
    }
    $promoteform=promoteform($value);
    $templ_body = component_btn_set($value);
    $promotion_setting_table .= <<<HTML
    <div class="tab-pane-promote tab-pane fade $tab_active" id="$value" role="tabpanel">
      <div class="row">
        <div class="col-12 col-xl-4 mb-3"> $templ_body</div>
        <div class="col-12 col-xl-8">
            <div class="row">
              <div class="col promotereviewform">
                  <div class="mb-3">
                  <div class="alert alert-danger lock-alert" role="alert">
                    {$tr['ui lock alert']}
                  </div>
                    <span class="mr-1">{$tr['Enabled state']}：</span>
                    <span class="material-switch pull-left">
                        <input class="promote_top_form_switch checkbox_switch" type="checkbox"/>
                        <label for="promote_top_form_switch" class="label-success"></label>
                    </span>
                    <button class="btn btn-secondary btn-sm ml-5" type="button" id="dropdownMenuButton" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                        {$tr['copy this to']}<i class="ml-1 fas fa-caret-down"></i>
                      </button>
                      <div class="dropdown-menu" aria-labelledby="dropdownMenuButton">
                        <a class="dropdown-item promote_copy" value="home" href="javascript: void(0)">{$tr['Home']}</a>
                        <a class="dropdown-item promote_copy" value="gamelobby" href="javascript: void(0)">{$tr['Navigation field']}</a>
                        <a class="dropdown-item promote_copy" value="static" href="javascript: void(0)">{$tr['Bottom Copywriting']}</a>
                      </div>
                  </div>

                  <div class="promote-formdiv row p-2">
                    $promoteform
                    $modal_set
                  </div>

              </div>
              <div class="col-auto promotereviewdiv p-4"></div>
            </div>

          </div>
        </div>
      </div>
HTML;

}
//<button type="button" class="float-right btn btn-primary btn-sm"><i class="mr-1 fas fa-table"></i>显示模式</button>
//tab导览列----------------------------------------
$promotion_setting = <<<HTML
<div class="card mt-3 mb-5">
  <div class="card-header">
    <h5 class="mb-3"><i class="mr-2 fas fa-cog"></i>{$tr['Floating ad settings']}</h5>
    <button type="button" data-target="#howtouisetting_component" data-toggle="modal" class="float-right btn btn-secondary btn-sm rounded-circle mx-2"><i class="fas fa-question fa-xs"></i></button>
      <ul class="nav nav-tabs card-header-tabs" id="myTab" role="tablist">
        <li class="nav-item">
          <a class="nav-link active" data-toggle="tab" href="#home" role="tab" aria-controls="home" aria-selected="true">{$tr['Home']}</a>
        </li>
        <li class="nav-item">
          <a class="nav-link" data-toggle="tab" href="#gamelobby" role="tab" aria-controls="gamelobby" aria-selected="false">{$tr['Navigation field']}</a>
        </li>
        <li class="nav-item">
          <a class="nav-link" data-toggle="tab" href="#static" role="tab" aria-controls="static" aria-selected="false">{$tr['Bottom Copywriting']}</a>
        </li>
      </ul>
  </div>

  <div class="card-body">
    <div class="tab-content px-2" id="myTabContent">      
      $promotion_setting_table
    </div>
  </div>
</div>
HTML;

$promotion_setting_table_data = <<<HTML
<script type="text/javascript">
//初始化链接区块表单
$(".promotereviewform").hide();
$(".t-aside-pro").DataTable( {
      "bPaginate": false, // 显示换页
      "searching": false, // 显示搜索
      "info": false, // 显示信息
      "fixedHeader": false, // 标题置顶
      "bAutoWidth": false,
      "bSort": false,
      "data": [],
        columns: [
            { "data": "title",
              "mRender": function(data, type, full) {
                return '<textarea disabled="true" style="border: none;background-color:transparent;">'+data+'</textarea>';
              }
             },
            { "data": "txt",
              "mRender": function(data, type, full) {
                return '<textarea disabled="true" style="border: none;background-color:transparent;">'+data+'</textarea>';
              }
             },
            { "data": "link" },
            { "data": "target" },
            { "mRender": function(data, type, full) {
              return '<div class="btn-group btn-group-sm" role="group">\
                <button type="button" class="btn-delete btn btn-danger btn-sm"><i class="fas fa-trash-alt"></i></button>\
              </div>';
            }}
        ]
});
//该广告之设置表格(显示与送出)
function promote_form(){
  //广告表格预览
  this.show = function(data,tab_act_id,act_btn_id,locked){
    $("#"+tab_act_id+' #upload_promote_img').val('');
    $('#'+tab_act_id+' .form-float').hide();
    $('#'+tab_act_id+' .form-aside').hide();
    $('#'+tab_act_id+' .aside_float_form').hide();
    $('#'+tab_act_id+' .aside_slide_form').hide();
    $('#'+tab_act_id+' .popup_pro_form').hide();
    $('#'+tab_act_id+' .lock-alert').hide();

    if(locked){
      $('#'+tab_act_id+' .lock-alert').show();
    }
    //显示各个表单，部分有自动填入
    $('#'+tab_act_id+' .promote_top_form_switch').prop("checked",data["switch"]);
    //$('#'+tab_act_id+' .promote_switch').html(switch_html);
    switch (act_btn_id) {
      case 'float-promote':
        $('#'+tab_act_id+' .form-float').show();
        var fform =$('#'+tab_act_id+' .float_pro_form').get()[0];
        data['img']=data['img']||'';
        data['link']=data['link']||'';
        $('#'+tab_act_id+" select[name='promote_target']").val(data["target"]||'_self');
        fform.elements['promote_link'].value=data['link'];
      break;
      case 'popup-promote':
        $('#'+tab_act_id+' .popup_pro_form').show();
        var fform =$('#'+tab_act_id+' .popup_pro_form').get()[0];
        data['img']=data['img']||'';
        data['link']=data['link']||'';
        fform.elements['promote_link'].value=data['link'];
        $('#'+tab_act_id+" input[name='popup-link-title']").val(data["title"]);
        $('#'+tab_act_id+" select[name='popup_promote_target']").val(data["target"]||'_self');
        $('#'+tab_act_id+" .popup-modal input[value='"+data["style"]+"']").prop("checked",true);
      break;
      case 'aside-promote':
        if(data["type"] == 'float'){
          $('#'+tab_act_id+' .aside_float_form').show();
          $('#'+tab_act_id+' .aside_float_form .closeable').prop("checked",data["closeable"]);
          $('#'+tab_act_id+" .aside-float-modal input[value='"+data["style"]+"']").prop("checked",true);
        }
        else{
          $('#'+tab_act_id+' .aside_slide_form').show();
          $('#'+tab_act_id+" input[name='aside-slide-link-title']").val(data["title"]);
          $('#'+tab_act_id+" .aside-slide-modal input[value='"+data["style"]+"']").prop("checked",true);
        }
        $('#'+tab_act_id+' .form-aside').show();
        $("#"+tab_act_id+" .t-aside-pro").DataTable().clear().rows.add(data["content"]).draw();
      break;
      default:
        break;
    }
  };
  //发送表单数据，并利用callback调用预览广告组件function(component_preview())刷新预览
  this.act_send =function(index,data,callback){
    json_send('trigger',index,data,callback);
  };
};

//广告组件预览
function component_preview(){
  //预览当下所选择的广告组件并利用callback 调用表单function让表单显示
  this.show = function(tab_act_id,locked,callback){
    var act_btn = $("#"+tab_act_id+" .active").get()[0];
    json_get(function(data){
      $("#"+tab_act_id+" .promotereviewdiv").empty();
            if(typeof act_btn =="undefined"){return false};//如果没有任何按钮被选择 则跳出function
            switch (act_btn.id) {
                case 'float-promote':
                   var dataSet=[];
                      for (var i = 0; i < data[tab_act_id]["float-promote"][act_btn.value].length; i++) {
                        dataSet.push(data[tab_act_id]["float-promote"][act_btn.value][i]);
                        var pre_float =new Float_promote(dataSet[0],act_btn.value,"dev");
                        pre_float.show("#"+tab_act_id+" .promotereviewdiv");
                      }
                     break;

                case 'aside-promote':
                   var dataSet=[];
                      for (var i = 0; i < data[tab_act_id]["aside-promote"][act_btn.value].length; i++) {
                          dataSet.push(data[tab_act_id]["aside-promote"][act_btn.value][i]);
                          if (dataSet[0]["type"]=="float")
                              var pre_aside =new Aside_promote(dataSet[0],act_btn.value,"dev");
                          else
                              var pre_aside =new Slide_aside(dataSet[0],act_btn.value,"dev");
                          pre_aside.show("#"+tab_act_id+" .promotereviewdiv");
                      }
                     break;

                case 'popup-promote':
                   var dataSet=[];
                      for (var i = 0; i < data[tab_act_id]["popup-promote"].length; i++){
                          dataSet.push(data[tab_act_id]["popup-promote"][i]);
                          var pre_popup =new Popup_promote(dataSet[0],"dev");
                          $("#"+tab_act_id+" .promotereviewdiv").append('<button type="button" class="btn btn-info btn-lg pre-popup-btn">{$tr['preview popup ad']}</button>');
                          pre_popup.show("#"+tab_act_id+" .promotereviewdiv");
                          $(document).on('click', "#"+tab_act_id+' .pre-popup-btn', function(){
                            $("#"+tab_act_id+' #'+dataSet[0]["id"]).modal('show');
                          });
                      }

                     break;
                 default:
                   var dataSet=[];
                   break;
              }
              $("#"+tab_act_id+" .promotereviewform").show();
              //console.log(dataSet[0]);
              callback(dataSet[0],tab_act_id,act_btn.id,locked);
    });
  };
};

$("#view_component").ready(function() {
  $('a[data-group*=\"img\"]').on('show.bs.tab', function (e) {      
    $($(e.relatedTarget).attr('href')).find('input').val(''); // previous active tab
  })
  //启用按钮、预览窗口 初始化
  var tp_form = new promote_form();
  var prev = new component_preview();

//按钮component预览
$('.tab-pane-promote').each(function() {
    var tab_act = $(this);
    var tab_act_id = $(this).attr("id");

    //刷新页面function 会重制表单与组件预览
    function refresh(locked){
      $('.modal').modal('hide');
      prev.show(tab_act_id,locked,tp_form.show);
    };

    //7按钮按下 动作
    $(this).find('#templ_body button').each(function() {
      //按下 动作
        $(this).click(function(e) {
          tab_act.find('#templ_body button').removeClass("active");
          $(this).addClass("active");
          $("#"+tab_act_id+" .promotereviewdiv").empty();
          refresh(!!e.target.dataset.locked);
        });
    });

  function ifswitchon(btn_class,btn_switch){
    //console.log(btn_class);
    // $(btn_class).html('<i class="far fa-square fa-3x"></i>');
    if(btn_switch == 0)
      $(btn_class).children("i").attr('class','far fa-square fa-3x');      
    else
      $(btn_class).children("i").attr('class','far fa-check-square fa-3x');      
  }

  //启用开关动作
  $("#view_component").on('click', "#"+tab_act_id+' .material-switch', function(){
    var checked =$(this).find(".promote_top_form_switch").prop("checked");
    $(this).find(".promote_top_form_switch").prop("checked",!checked);
    var data = { "switch" : checked ? 0 : 1};
    var activebtn = $("#"+tab_act_id+" .active").get()[0];
    ifswitchon("#"+tab_act_id+" #templ_body .active",data['switch']);
    if(activebtn.id=='popup-promote')
      index=[tab_act_id,activebtn.id,0];
    else
      index=[tab_act_id,activebtn.id,activebtn.value,0];
    tp_form.act_send(index,data,function(){
      refresh($(activebtn).hasClass('ui-locked'))
    });
  });



  //复制该组件之设置至其他页面
  $("#view_component").on('click', "#"+tab_act_id+' .promote_copy', function(){
  var target =$(this).attr('value');
  var r=confirm("{$tr['save so setting alert']}")
  if (r==true)
    {
      json_get(function(data){
        var act_btn = $("#"+tab_act_id+" .active").get()[0];
            switch (act_btn.id) {
                  case 'popup-promote':
                    var temp_data = data[tab_act_id]["popup-promote"][0];
                    var index=[target,act_btn.id,0];
                    ifswitchon("#"+target+" #"+act_btn.id,temp_data['switch']);
                    break;
                  default:
                    var temp_data = data[tab_act_id][act_btn.id][act_btn.value][0];
                    var index=[target,act_btn.id,act_btn.value,0];
                    //console.log(temp_data);
                    ifswitchon("#"+target+" #"+act_btn.id+"[value="+act_btn.value+"]",temp_data['switch']);
                    //console.log(temp_data['switch']);
                    break;
            }
            tp_form.act_send(index,temp_data,function(){prev.show(target,tp_form.show);});

            //alert("修改成功！");
      });
    }
  });

  //float 四角浮动广告 save
  $("#view_component").on('click', "#"+tab_act_id+' .float-save', function(){
    var act_btn = $("#"+tab_act_id+" .active").get()[0];
    var fform =$('#'+tab_act_id+' .float_pro_form').get()[0];
    //var upload_img = $("#"+tab_act_id+' .float_pro_form #upload_promote_img')[0].files[0];

    if($("#"+tab_act_id+' .float_pro_form #text_promote_img').val() != '')
      var upload_img = $("#"+tab_act_id+' .float_pro_form #text_promote_img').val();
    else
      var upload_img = $("#"+tab_act_id+' .float_pro_form #upload_promote_img')[0].files[0];

    var index = [tab_act_id,act_btn.id,act_btn.value,0];
    var formData = new FormData();
    formData.append('index', JSON.stringify(index));
    formData.append('img', upload_img);
    formData.append('link', fform.elements['promote_link'].value);
    formData.append('target', $("#"+tab_act_id+" .promote_target :selected").val());
    //console.log(formData);
    $('body').append('<div id=\"progress_bar\" style=\"width:100%;position: fixed;top: 47%;text-align: center;background-color: rgba(225, 225, 225, 0.3);\"><img width=\"40px\" height=\"40px\" src=\"./ui/loading_hourglass.gif\">{$tr['loading']}</div>');
    $.ajax({
      type: 'POST',
      url : 'uisetting_upload.php?cid={$component_id}&a=cpn',
      data : formData,
      cache:false,
      contentType: false,
      processData: false,
      success : function(result){
        $('#progress_bar').remove();
        $('body').append(result);
        $("#"+tab_act_id+' #upload_promote_img').val('');
        //alert('修改成功!');
        refresh();
      },
      error: function(res){
        $('#progress_bar').remove();
        if(res.status == 413) {
          alert('{$tr['The file is too large (more than 2MB)']}');
        }else{
          alert('{$tr['There was an error uploading the file. Please try again later.']}');
        }
      }
    });
  });
  //popup 弹出式广告 save
  $("#view_component").on('click', "#"+tab_act_id+' .popup-save', function(){
    var act_btn = $("#"+tab_act_id+" .active").get()[0];
    var fform =$('#'+tab_act_id+' .popup_pro_form').get()[0];
    //var upload_img = $('#'+tab_act_id+' .popup_pro_form #upload_promote_img')[0].files[0];

    if($("#"+tab_act_id+' .popup_pro_form #text_promote_img').val() != '')
      var upload_img = $("#"+tab_act_id+' .popup_pro_form #text_promote_img').val();
    else
      var upload_img = $('#'+tab_act_id+' .popup_pro_form #upload_promote_img')[0].files[0];

    var index = [tab_act_id,act_btn.id,0];
    var formData = new FormData();
    formData.append('index', JSON.stringify(index));
    formData.append('title', fform.elements['popup-link-title'].value);
    formData.append('img', upload_img);
    formData.append('link', fform.elements['promote_link'].value);
    formData.append('target', $("#"+tab_act_id+" .popup_promote_target :selected").val());
    formData.append('style', $("#"+tab_act_id+" .popup-modal input:checked").val());
    //console.log(formData);
    $('body').append('<div id=\"progress_bar\" style=\"width:100%;position: fixed;top: 47%;text-align: center;background-color: rgba(225, 225, 225, 0.3);\"><img width=\"40px\" height=\"40px\" src=\"./ui/loading_hourglass.gif\">{$tr['loading']}</div>');
    $.ajax({
      type: 'POST',
      url : 'uisetting_upload.php?cid={$component_id}&a=cpn',
      data : formData,
      cache:false,
      contentType: false,
      processData: false,
      success : function(result){
        $('#progress_bar').remove();
        $('body').append(result);
        $("#"+tab_act_id+' #upload_promote_img').val('');
        //alert('修改成功!');
        refresh();
      },
      error: function(res){
        $('#progress_bar').remove();
        if(res.status == 413) {
          alert('{$tr['The file is too large (more than 2MB)']}');
        }else{
          alert('{$tr['There was an error uploading the file. Please try again later.']}');
        }
      }
    });
  });

  //aside 左右侧边广告 改变动画型态 change type
  $("#"+tab_act_id).on('click', '.btn-change-type', function() {
    var act_btn = $("#"+tab_act_id+" .active").get()[0];
    var data = {"type":$(this).val(),
    "style":"style1"
  };
    var index =[tab_act_id,act_btn.id,act_btn.value,0];
    tp_form.act_send(index,data,refresh);
  });

  //float-aside  浮动型
  $("#"+tab_act_id).on('click', '.aside-float-save', function() {
    var act_btn = $("#"+tab_act_id+" .active").get()[0];
    var data = {"style":$("#"+tab_act_id+" .aside-float-modal input:checked").val()};
    var index =[tab_act_id,act_btn.id,act_btn.value,0];
    tp_form.act_send(index,data,refresh);
  });
  $("#"+tab_act_id).on('click', '.closeable', function() {
    var act_btn = $("#"+tab_act_id+" .active").get()[0];
    var data = {"closeable":$(this).prop("checked") ? 1 : 0 };
    var index =[tab_act_id,act_btn.id,act_btn.value,0];
    tp_form.act_send(index,data,refresh);
  });

  //slide-aside 侧边滑出型
  $("#"+tab_act_id).on('click', '.aside-slide-save', function() {
    var fform =$('#'+tab_act_id+' .aside_slide_form').get()[0];
    var act_btn = $("#"+tab_act_id+" .active").get()[0];
    var data = {
      "style":$("#"+tab_act_id+" .aside-slide-modal input:checked").val(),
      "title" : fform.elements['aside-slide-link-title'].value
    };
    var index =[tab_act_id,act_btn.id,act_btn.value,0];
    tp_form.act_send(index,data,refresh);
  });


  //aside 区块 add
  $("#"+tab_act_id).on('click', '.aside-add', function() {
    var act_btn = $("#"+tab_act_id+" .active").get()[0];
    var fform =$('#'+tab_act_id+' .aside-form').get()[0];
    var data ={
      "title" : fform.elements['aside-link-title'].value,
      "txt" : fform.elements['aside-link-txt'].value,
      "link": fform.elements['aside-link-url'].value,
      "target" : $("#"+tab_act_id+" .aside-form .aside-link-target :selected").val()
    }
    var index = [tab_act_id,act_btn.id,act_btn.value,0,"content"];
    tp_form.act_send(index,data,refresh);
    fform.reset();
  });

  //删除aside 区块
  $("#"+tab_act_id+" .t-aside-pro").on('click', '.btn-delete', function() {
    var act_btn = $("#"+tab_act_id+" .active").get()[0];
    var delete_index = [tab_act_id,act_btn.id,act_btn.value,0,"content",$(this).closest('tr').index()];
    json_send('delete',delete_index,[],refresh);
  });
  });
});

</script>
HTML;

//-------------------------------------------------------------------------------------------
//card区块分区块 并放入templ
//-------------------------------------------------------------------------------------------
$card_templ   = '';
$card_content = array(
    0 => array('<i class="mr-2 fas fa-cog"></i>'.$tr['ad component setting'], $promotion_setting),
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

//将html放入content
$uisetting_view_component = '';
$uisetting_view_component .= $promotion_setting . <<<HTML
<div class="modal fade" id="howtouisetting_component" tabindex="-1" role="dialog"  aria-labelledby="myLargeModalLabel" aria-hidden="true">
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
        <img class="w-100" style="height: auto;" src="in/component/preview/howtouisetting.jpg" alt="">
        </div>
        <div class="alert alert-info">{$tr['ad component preview alert']}</div>
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
.card-header-tabs {
    margin-right: -.625rem;
    margin-bottom: -.75rem;
    margin-left: -.625rem;
    border-bottom: 0;
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
.block{
  height: 80px;
}
.popup{
  height: 120px;
}
.btn-sense{
  position:relative;
  color: #fff;
  background-color: #17a2b8;
  border-color: #17a2b8;
  opacity: 0.3;
  transition: all 0.2s ease-out;
}
.btn-sense:hover{
  opacity: 1;
}
.btn-sense.active{
  opacity: 1;
}
.ui-locked:before{
  font-family: "Font Awesome 5 Free";  
  content: "\f023";
  font-weight: 900;
  position: absolute;
  width: 1.5em;
  height: 1.5em;
  background: #dc3545;
  border-radius: 0.25em;
  left: -5px;
  top: -5px;
}
.promotereviewdiv{
position:relative;
}
.form-aside textarea {
  border : 0;
  overflow-y : auto; /* IE */
  resize : none; /* Firefox, Chrome */
}

.form-aside textarea:focus {
  outline : 0; /* Chrome */
}
</style>
' . $promotion_setting_table_data;
