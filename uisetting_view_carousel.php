<?php
// ----------------------------------------------------------------------------
// Features:  後台 -- ui設定
// File Name:
// Author:     orange
// Related: uisetting_action
// DB Table:
// Log:
// ----------------------------------------------------------------------------
//----------------------------------------------------------------------------------------------
// 目錄
// 4.首頁廣告設定
//-banner
//----------------------------------------------------------------------------------------------
//----------------------------------------------------------------------------------------------
// 首頁元件設定
//----------------------------------------------------------------------------------------------

//------------------首頁幻燈片banner----------------------------
$carousel_tab = "";
$id           = array("carousel_pc", "carousel_m");
for ($i = 0; $i < count($id); $i++) {
    if ($i == 0) {$act = " show active";} else { $act = "";}
    $carousel_tab .= <<<HTML
      <div class="tab-pane fade{$act}" id="{$id[$i]}" role="tabpanel">
          <form class="form-carousel mx-1 mb-2 form-inline">
            <div class="input-group mb-3">
              <label class="mx-1"><span class="glyphicon glyphicon-info-sign mr-2" data-toggle="tooltip" data-placement="top"  title="{$tr['Image file does not exceed 2MB']}"></span>{$tr['images']}：</label>
                <div class="dropdown">
                  <button class="btn btn-secondary dropdown-toggle" type="button" id="dropdownMenuButton" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                    {$tr['options']}
                  </button>
                  <div class="dropdown-menu"  id="v-pills-tab" role="tablist" aria-labelledby="dropdownMenuButton">
                    <div class="nav flex-column nav-pills" id="v-pills-tab" role="tablist" aria-orientation="vertical">
                      <a class="dropdown-item active" data-group="{$id[$i]}" data-toggle="pill" href="#v-pills-{$id[$i]}-upload" role="tab" aria-selected="true">{$tr['upload image']}</a>
                      <a class="dropdown-item" data-group="{$id[$i]}" data-toggle="pill" href="#v-pills-{$id[$i]}-url" role="tab" aria-selected="false">{$tr['image url']}</a>
                    </div>
                  </div>
                </div>

                <div class="tab-content ml-2" id="v-pills-tabContent">
                  <div class="tab-pane fade show active" id="v-pills-{$id[$i]}-upload" role="tabpanel">
                    <input class="mt-1" type="file" accept="image/*" name="file" id="upload_carousel_img" >
                  </div>
                  <div class="tab-pane fade" id="v-pills-{$id[$i]}-url" role="tabpanel">
                    <input type="text" class="form-control mt-1" id="text_carousel_img" placeholder="{$tr['enter image url']}">
                  </div>
                </div>

            </div>
            <div class="input-group mb-3">
              <label class="mx-1">{$tr['link']}：</label><input type="text" class=" form-control" placeholder="" name="carousel-link" required>
            </div>
            <div class="input-group mb-3">
              <label class="mx-1">{$tr['target window']}:</label>
              <select class="carousel-link-target ml-2">
              　<option value="_self">{$tr['self local']}</option>
              　<option value="_blank">{$tr['blank new window']}</option>
              </select>
            </div>
            <div class="input-group mb-3">
              <input type="button" class="btn-new mx-1 btn btn-success btn-sm" value="{$tr['add']}">
            </div>
          </form>

        <table class="t-carousel table table-bordered table-hover table-align-middle">
          <thead class="thead-light">
            <tr>
              <th scope="col">{$tr['images']}</th>
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
$index_setting['carousel'] = <<<HTML
  <div id="carousel" class="card mt-3 mb-4">
    <div class="card-header">
      <h5 class="mb-3"><i class="fas fa-cog mr-2"></i>{$tr['Home Carousel Banner Settings']}</h5>
      <button type="button" data-target="#howtouisetting_carousel_home" data-toggle="modal" class="float-right btn btn-secondary btn-sm rounded-circle mx-2"><i class="fas fa-question fa-xs"></i></button>
        <ul class="nav nav-tabs card-header-tabs" id="myTab" role="tablist">
          <li class="nav-item">
            <a class="nav-link active" data-toggle="tab" href="#carousel_pc" role="tab" aria-controls="desktop" aria-selected="true">{$tr['desktop']}</a>
          </li>
          <li class="nav-item">
            <a class="nav-link" data-toggle="tab" href="#carousel_m" role="tab" aria-controls="mobile" aria-selected="false">{$tr['mobile']}</a>
          </li>
        </ul>
    </div>
  <div class="card-body">
    <div class="tab-content tab-carousel">
      {$carousel_tab}
    </div>
  </div>
</div>

HTML;
$index_setting_js = <<<HTML
<script type="text/javascript">
function sortcarouseldata(tabid,uisetting_data){
  try{
    if(tabid=="carousel_pc")
      return uisetting_data["index_carousel"]["desktop"]["item"];
    else if(tabid=="carousel_m")
      return uisetting_data["index_carousel"]["mobile"]["item"];
    else if(tabid=="lobby_game")
      return uisetting_data["lobby_carousel"]["game"];
    else if(tabid=="lobby_live")
      return uisetting_data["lobby_carousel"]["live"];
    else if(tabid=="lobby_lottery")
      return uisetting_data["lobby_carousel"]["lottery"];
    else if(tabid=="lobby_fishing")
      return uisetting_data["lobby_carousel"]["fishing"];
  }
  catch (e) {
    return [];
  }
}
function init_carousel_table(uisetting_data){
  $("#view_carousel .tab-carousel .tab-pane").each(function(){
    var tabid=$(this).attr("id");
    var uidata = sortcarouseldata(tabid,uisetting_data);
    //console.log(uidata)
    $("#"+tabid+" .t-carousel").DataTable( {
      "bPaginate": false, // 顯示換頁
      "searching": false, // 顯示搜尋
      "info": false, // 顯示資訊
      "fixedHeader": false, // 標題置頂
      "bAutoWidth": false,
      "bSort": false,
      "data": uidata,
        columns: [
            { "mData": "img","mRender": function(data, type, full) {
              return '<img onerror="this.src=\'in/component/common/error.png\'" style="max-width:500px;max-height: 150px;" src="'+data+'">';
            }},
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
$(document).ready(function() {  
  $('a[data-group*=\"carousel\"]').on('show.bs.tab', function (e) {      
    $($(e.relatedTarget).attr('href')).find('input').val(''); // previous active tab
  })

  $("#view_carousel .tab-carousel .tab-pane").each(function(){
    var tabid=$(this).attr("id");

    //新增動作
    $("#"+tabid+" .form-carousel").on('click',".btn-new",function(){
      var form =$("#"+tabid+" .form-carousel").get()[0];
      if($('#'+tabid+' .form-carousel #text_carousel_img').val() != '')
        var upload_img = $('#'+tabid+' .form-carousel #text_carousel_img').val();
      else
        var upload_img = $('#'+tabid+' .form-carousel #upload_carousel_img')[0].files[0];

      if(tabid=='carousel_pc')
        var index =["index_carousel","desktop","item"];
      else if(tabid=="carousel_m")
        var index =["index_carousel","mobile","item"];
      else if(tabid=="lobby_game")
        var index =["lobby_carousel","game"];
      else if(tabid=="lobby_live")
        var index =["lobby_carousel","live"];
      else if(tabid=="lobby_lottery")
        var index =["lobby_carousel","lottery"];
      else if(tabid=="lobby_fishing")
        var index =["lobby_carousel","fishing"];

      var formData = new FormData();
      formData.append('index', JSON.stringify(index));
      formData.append('img', upload_img);
      formData.append('link', form.elements['carousel-link'].value);
      formData.append('target', $(form).find(".carousel-link-target :selected").val());
      //console.log(formData);
      $('body').append('<div id=\"progress_bar\" style=\"width:100%;position: fixed;top: 47%;text-align: center;background-color: rgba(225, 225, 225, 0.3);\"><img width=\"40px\" height=\"40px\" src=\"./ui/loading_hourglass.gif\">{$tr['loading']}</div>');
      $.ajax({
        type: 'POST',
        url : 'uisetting_upload.php?cid={$component_id}&a=crs',
        data : formData,
        cache:false,
        contentType: false,
        processData: false,
        success : function(result){
          $('#progress_bar').remove();
          $('body').append(result);
          $("#"+tabid+' #upload_promote_img').val('');
          form.reset();
          //alert('添加成功!');
          //$("#"+tabid+" .t-carousel").DataTable().ajax.reload();
          json_get(function(data){        
            data = sortcarouseldata(tabid,data);
            $("#"+tabid+" .t-carousel").DataTable().clear();
            $("#"+tabid+" .t-carousel").DataTable().rows.add(data).draw();
          });
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

    //刪除動作
  $("#"+tabid+" .t-carousel").on('click',".btn-delete",function(){
      if(tabid=='carousel_pc')
        var index =["index_carousel","desktop","item",$(this).closest('tr').index()];
      else if(tabid=="carousel_m")
        var index =["index_carousel","mobile","item",$(this).closest('tr').index()];
      else if(tabid=="lobby_game")
        var index =["lobby_carousel","game",$(this).closest('tr').index()];
      else if(tabid=="lobby_live")
        var index =["lobby_carousel","live",$(this).closest('tr').index()];
      else if(tabid=="lobby_lottery")
        var index =["lobby_carousel","lottery",$(this).closest('tr').index()];
      else if(tabid=="lobby_fishing")
        var index =["lobby_carousel","fishing",$(this).closest('tr').index()];

      $('body').append('<div id=\"progress_bar\" style=\"width:100%;position: fixed;top: 47%;text-align: center;background-color: rgba(225, 225, 225, 0.3);\"><img width=\"40px\" height=\"40px\" src=\"./ui/loading_hourglass.gif\">{$tr['loading']}</div>');
      $.ajax({
        type: 'POST',
        url : 'uisetting_action.php?cid={$component_id}&act=delete&cdn=1',
        data : {index:index},
        success : function(result){
          $('#progress_bar').remove();
          //console.log(result);
          alert(result);
          //$("#"+tabid+" .t-carousel").DataTable().ajax.reload();
          json_get(function(data){        
            data = sortcarouseldata(tabid,data);
            $("#"+tabid+" .t-carousel").DataTable().clear();
            $("#"+tabid+" .t-carousel").DataTable().rows.add(data).draw();
          });
        },
        error: function(res){
          $('#progress_bar').remove();
        }
      });
  });

  });
});
</script>
HTML;

//組合各個功能表單
$index_setting = $index_setting['carousel'];

//----------------------------------------------------------------------------------------------
// 首頁元件設定
//----------------------------------------------------------------------------------------------
$lobby_carousel_tab = "";
$id                 = array(
    array("lobby_game", $tr['Electronic entertainment']), // "电子游艺"
    array("lobby_live", $tr['Live video'] ), // "真人视频"
    array("lobby_lottery", $tr['Lottery game']), // "彩票游戏"
    array("lobby_fishing", $tr['Fishing people'] )); // "捕鱼达人"
for ($i = 0; $i < count($id); $i++) {
    if ($i == 0) {$act = " show active";} else { $act = "";}
    $lobby_carousel_tab .= <<<HTML
      <div class="tab-pane fade{$act}" id="{$id[$i][0]}" role="tabpanel">
          <form class="form-carousel mx-1 mb-2 form-inline">
          <div class="input-group mb-3">
            <label class="mx-1"><span class="glyphicon glyphicon-info-sign mr-2" data-toggle="tooltip" data-placement="top"  title="{$tr['Image file does not exceed 2MB']}"></span>{$tr['images']}：</label>
              <div class="dropdown">
                <button class="btn btn-secondary dropdown-toggle" type="button" id="dropdownMenuButton" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                  {$tr['options']}
                </button>
                <div class="dropdown-menu"  id="v-pills-tab" role="tablist" aria-labelledby="dropdownMenuButton">
                  <div class="nav flex-column nav-pills" id="v-pills-tab" role="tablist" aria-orientation="vertical">
                    <a class="dropdown-item active" data-group="carousel-{$id[$i][0]}" data-toggle="pill" href="#v-pills-{$id[$i][0]}-upload" role="tab" aria-selected="true">{$tr['upload image']}</a>
                    <a class="dropdown-item" data-group="carousel-{$id[$i][0]}" data-toggle="pill" href="#v-pills-{$id[$i][0]}-url" role="tab" aria-selected="false">{$tr['image url']}</a>
                  </div>
                </div>
              </div>

              <div class="tab-content ml-2" id="v-pills-tabContent">
                <div class="tab-pane fade show active" id="v-pills-{$id[$i][0]}-upload" role="tabpanel">
                  <input class="mt-1" type="file" accept="image/*" name="file" id="upload_carousel_img" >
                </div>
                <div class="tab-pane fade" id="v-pills-{$id[$i][0]}-url" role="tabpanel">                  
                  <input type="text" class="form-control mt-1" id="text_carousel_img" placeholder="{$tr['enter image url']}">
                </div>
              </div>

          </div>
          <div class="input-group mb-3">
            <label class="mx-1">{$tr['link']}：</label><input type="text" class=" form-control" placeholder="" name="carousel-link" required>
          </div>
          <div class="input-group mb-3">
            <label class="mx-1">{$tr['target window']}:</label>
            <select class="carousel-link-target ml-2">
            　<option value="_self">{$tr['self local']}</option>
            　<option value="_blank">{$tr['blank new window']}</option>
            </select>
          </div>
          <div class="input-group mb-3">
            <input type="button" class="btn-new mx-1 btn btn-success btn-sm" value="{$tr['add']}">
            </div>
          </form>

        <table class="t-carousel table table-bordered table-hover table-align-middle">
          <thead class="thead-light">
            <tr>
              <th scope="col">{$tr['images']}</th>
              <th scope="col">{$tr['link']}</th>
              <th scope="col">{$tr['target window'] }</th>
              <th class="text-center" width="30px" scope="col">{$tr['delete']}</th>
            </tr>
          </thead>
          <tbody>
          </tbody>
        </table>

      </div>
HTML;
}
$lobby_carousel = <<<HTML
  <div id="carousel" class="card mb-4">
    <div class="card-header">
      <h5 class="mb-3"><i class="fas fa-cog mr-2"></i>{$tr['Game lobby banner set']}</h5>
      <button type="button" data-target="#howtouisetting_carousel_lobby" data-toggle="modal" class="float-right btn btn-secondary btn-sm rounded-circle mx-2"><i class="fas fa-question fa-xs"></i></button>
        <ul class="nav nav-tabs card-header-tabs" id="myTab" role="tablist">
HTML;
for ($i = 0; $i < count($id); $i++) {
    if ($i == 0) {$act = " active";} else { $act = "";}
    $lobby_carousel .= <<<HTML
          <li class="nav-item">
            <a class="nav-link{$act}" data-toggle="tab" href="#{$id[$i][0]}" role="tab" aria-controls="{$id[$i][0]}" aria-selected="true">{$id[$i][1]}</a>
          </li>
HTML;
}
$lobby_carousel .= <<<HTML
        </ul>
    </div>
  <div class="card-body">
    <div class="tab-content tab-carousel">
      {$lobby_carousel_tab}
    </div>
  </div>
</div>
HTML;

//------------------------------------------------------------------------------------------
//說明文件
//------------------------------------------------------------------------------------------
$carousel_modal = <<<HTML
<div class="modal fade" id="howtouisetting_carousel_home" tabindex="-1" role="dialog"  aria-labelledby="myLargeModalLabel" aria-hidden="true">
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
        <img class="w-100" style="height: auto;" src="in/component/preview/carousel_home.jpg" alt="">
        </div>
      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="howtouisetting_carousel_lobby" tabindex="-1" role="dialog"  aria-labelledby="myLargeModalLabel" aria-hidden="true">
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
        <img class="w-100" style="height: auto;" src="in/component/preview/carousel_gamelobby.jpg" alt="">
        </div>
      </div>
    </div>
  </div>
</div>
HTML;
$lobby_carousel .= $carousel_modal;

//-------------------------------------------------------------------------------------------
//放入templ
//-------------------------------------------------------------------------------------------
$uisetting_view_carousel_alert =<<<HTML
<div class="alert alert-info mt-2" role="alert">
  {$tr['ui setting carousel alert']}
</div>
HTML;
//將html放入content
$uisetting_view_carousel = '';
$uisetting_view_carousel .= $uisetting_view_carousel_alert.$index_setting . $lobby_carousel;

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
</style>
' . $index_setting_js;
