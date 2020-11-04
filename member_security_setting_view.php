<?php use_layout("template/beadmin.tmpl.php"); ?>

<!-- begin of extend_head -->
<?php begin_section('extend_head'); ?>
<script src="in/jquery-ui.js"></script>
<link rel="stylesheet"  href="in/jquery-ui.css" >
<!-- Jquery blockUI js  -->
<script src="./in/jquery.blockUI.js"></script>
<!-- Jquery Validation Engine -->
<script src="./in/jQuery-Validation-Engine/js/languages/jquery.validationEngine-zh_CN.js" type="text/javascript" charset="utf-8"></script>
<script src="./in/jQuery-Validation-Engine/js/jquery.validationEngine.js" type="text/javascript" charset="utf-8"></script>
<link rel="stylesheet" href="./in/jQuery-Validation-Engine/css/validationEngine.jquery.css" type="text/css"/>

<!-- 自訂css -->
<link rel="stylesheet" type="text/css" href="ui/style_seting.css">
<style type="text/css">
            
.material-switch > input[type="checkbox"] {
  visibility:hidden;
}
.material-switch > .label-success {
  cursor: pointer;
  height: 0px;
  position: relative;
  width: 40px;
}

.material-switch > .label-success::before {
  background: rgb(0, 0, 0);
  box-shadow: inset 0px 0px 10px rgba(0, 0, 0, 0.5);
  border-radius: 8px;
  content: '';
  height: 16px;
  margin-top: -8px;
  margin-left: -18px;
  position:absolute;
  opacity: 0.3;
  transition: all 0.4s ease-in-out;
  width: 30px;
}
.material-switch > .label-success::after {
  background: rgb(255, 255, 255);
  border-radius: 16px;
  box-shadow: 0px 0px 5px rgba(0, 0, 0, 0.3);
  content: '';
  height: 16px;
  left: -4px;
  margin-top: -8px;
  margin-left: -18px;
  position: absolute;
  top: 0px;
  transition: all 0.3s ease-in-out;
  width: 16px;
}
.material-switch > input[type="checkbox"]:checked + .label-success::before {
  background: inherit;
  opacity: 0.5;
}
.material-switch > input[type="checkbox"]:checked + .label-success::after {
  background: inherit;
  left: 20px;
}
.material-switch > .label-success {
  margin-bottom:3px;
}


</style>
<?php end_section(); ?>

<!-- begin of page_title -->
<?php begin_section('page_title'); ?>
<ol class="breadcrumb">
  <li><a href="home.php"><?php echo $tr['Home']; ?></a></li>
  <li class="active"><?php echo $function_title; ?></li>
</ol>
<?php end_section(); ?>
<!-- end of page_title -->


<!-- 內容 -->
<!-- begin of panelbody_content -->
<?php begin_section('panelbody_content'); ?>
<div id="accordion">
      <h3><?php echo $tr['two-factor authentication']; ?> &emsp;&emsp;&emsp;<?php echo $two_fa_status; ?></h3>
      <div class="row">
          <div class="col-auto col-md-1.5"><h5 class="text-right"><?php echo $tr['Enabled or not']; ?></h5></div>
          <div class="col-auto col-md-5 material-switch pull-left">
              <input id="twofa_status" name="twofa_status" class="checkbox_switch" value="1" type="checkbox" <?php echo $twofa_checked; ?>>
              <label for="twofa_status" class="label-success"></label>
          </div>
          <div class="col-12 col-md-12">
              <br>
              <p><?php echo $tr['complete the steps below'];?></p>
              <p><?php echo $tr['install on the mobile device'];?>
                <p><?php echo $tr['for iPhone users'];?>
                <a href="https://itunes.apple.com/tw/app/google-authenticator/id388497605?mt=8"><?php echo $tr['itunes'];?></a>
                </p>
                <p><?php echo $tr['for android users'];?>
                <a target="_blank" href="https://android.myapp.com/myapp/detail.htm?apkName=com.google.android.apps.authenticator2"><?php echo $tr['Application treasure'];?></a>
                <a href="https://play.google.com/store/apps/details?id=com.google.android.apps.authenticator2&hl=zh_TW"><?php echo $tr['google'];?></a></p>
              <p><?php echo $tr['Open the APP']; ?></p> 
              <p><?php echo $tr['add an account by scaning'];?></p> 
              <p><?php echo $tr['If you have used it'];?></p>
          </div>
          
          <!-- 2FA停用 到 啟用 -->
          <div class="modal fade" id="twofa_modal_enable" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true">
            <div class="modal-dialog" role="document">
              <div class="modal-content">
                <div class="modal-header">
                  <h3 class="modal-title" id="exampleModalLabel"><?php echo $tr['Enabled'];echo $tr['two-factor authentication'];?></h3>
                  <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                  </button>
                </div>
                <div class="modal-body">
                  <div class="row">
                      <div class="offset-1 col-2"><h5><strong><?php echo $tr['step one'];?>：</strong></h5></div><div class="col-9"><h5><strong><?php echo $tr['scan qrcode'];?></strong></h5></div>
                      <div class="offset-3 col-9"><h5><?php echo $tr['Verification key'];?>：<strong id="secret_id"><?php echo $twofa_generate_data['secret']; ?></strong></h5></div>
                      <div class="offset-4 col-8"><?php echo "<img id='qrcode_id' src=".$twofa_generate_data['qrCodeUrl'].">"; ?></div>
                  </div>
                  <br>
                  <div class="row">
                      <div class="offset-3 col-6 text-center"><button type="button" id="qr_code_refresh" class="btn btn-secondary btn-lg btn-block"><?php echo $tr['regenerate'];?></button></div><div class="offset-3"></div>
                  </div>
                  <br>
                  <div class="row">
                      <div class="offset-1 col-2"><h5><strong><?php echo $tr['step two']; ?>：</strong></h5></div><div class="col-9"><h5><strong><?php echo $tr['set the answer when turn off the verification']; ?></strong></h5></div>
                      <div class="offset-3 col-9"><h5><span style="color:red" class="glyphicon glyphicon-star"></span><?php echo $tr['please choose a question'];?></h5></div>
                      <div class="offset-3 col-9"><h5>
                          <select name="twofa_question" size="1">
                              <?php foreach($disable_question as $q_key => $q_value): ?> 
                                  <option value="<?php echo $q_key;?>"><?php echo $q_value;?></option>
                               <?php endforeach;?>
                          </select></h5>
                      </div>
                      <div class="offset-3 col-9"><h5><span style="color:red" class="glyphicon glyphicon-star"></span><?php echo $tr['please filled the answer'];?></h5></div>
                      <div class="offset-3 col-8">
                        <input type="text" name="twofa_ans" class="form-control form-control-lg">
                      </div>
                  </div>
                  <br>
                  <div class="row">
                      <div class="offset-1 col-2"><h5><strong><?php echo $tr['step three']; ?>：</strong></h5></div><div class="col-9"><h5><strong><?php echo $tr['Enter the confirmation code'];?></strong></h5></div>
                      <div class="offset-3 col-9"><h5>
                          <span style="color:red" class="glyphicon glyphicon-star"></span><?php echo $tr['Please enter the verification code provided by the mobile device'];?></h5>
                      </div>
                      <div class="offset-3 col-8">
                        <input type="text"  name="verify_code" class="form-control form-control-lg">
                      </div>
                  </div>
                  <br>

                </div>
                <div class="modal-footer">
                  <button type="button" class="btn btn-secondary" data-dismiss="modal"><?php echo $tr['Cancel'];?></button>
                  <button type="button" id="id_two_fa_enable" class="btn btn-primary"><?php echo $tr['Enabled'];?></button>
                </div>
              </div>
            </div>
          </div>

          <!-- 2FA啟用 到 停用 -->
          <div class="modal fade" id="twofa_modal_disable" tabindex="-1" role="dialog" aria-labelledby="disable_ModalLabel" aria-hidden="true">
            <div class="modal-dialog" role="document">
              <div class="modal-content">
                <div class="modal-header">
                  <h3 class="modal-title" id="disable_ModalLabel"><?php echo $tr['disabled']; echo $tr['two-factor authentication'];?></h3>
                  <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                  </button>
                </div>
                <div class="modal-body">
                  <div class="row">
                      <div class="offset-2 col-9"><h5><strong><?php echo $tr['To disable 2FA verification, please answer the following questions to confirm that you are the same person'];?></strong></h5></div>
                      <br><br>
                      <div class="offset-1 col-3"><h5 class="text-right"><?php echo $tr['questions'];?>：</h5></div><br><br>
                      <div class="col-8"><h5><?php echo ($disable_question[json_decode($member_auth_result[1]->two_fa_question,true)]??$disable_question['1']); ?></h5></div>
                      <div class="col-4"><h5 class="text-right"><span style="color:red" class="glyphicon glyphicon-star"></span><?php echo $tr['please filled the answer']; ?>：</h5></div>
                      <div class="col-7">
                        <input type="text" name="twofa_disable_ans" class="form-control form-control-lg">
                      </div>
                  </div>
                  <br>
                </div>
                <div class="modal-footer">
                  <button type="button" class="btn btn-secondary" data-dismiss="modal"><?php echo $tr['Cancel'];?></button>
                  <button type="button" class="btn btn-primary" id="id_two_fa_disable"><?php echo $tr['disabled'];?></button>
                </div>
              </div>
            </div>
          </div>
          <div class="ml-auto">
              <button id="cancel" type="button" class="btn btn-secondary ml-3 btn-sm " onclick="history.go(-1);"><?php echo $tr['back to previous page'];?></button>
          </div>
      </div>

      <h3><?php echo $tr['IP address whitelist'];?> &emsp;<?php echo $whitelis_status; ?></h3>
      <form id="white_list_form" class="form-horizontal">
          <div class="row">
              <div class="col-auto col-md-2"><h5 class="alert text-right"><?php echo $tr['Enabled or not']; ?></h5></div>
              <div class="col-auto col-md-2 material-switch pull-left alert">
                  <input id="whitelist_status" name="whitelist_status" class="checkbox_switch" value="1" type="checkbox"<?php echo $whitelist_checked; ?>>
                  <label for="whitelist_status" class="label-success"></label>
              </div>
              <div class="col-auto col-md-8"></div>
              <br>
              <div class="col-auto col-md-2"><h5 class="alert text-right"><?php echo $tr['example']; ?>：</h5></div>
              <div class="col-auto col-md-6"><h5 class="alert alert-success">(<?php echo $tr['ip address'];?>) 192.168.1.1 、 (<?php echo $tr['ip address with subnet']; ?>) 192.168.1.0/24</h5></div>
              <div class="col-auto col-md-4"></div>
              <br><br>
              <div class="col-auto col-md-2"><h5 class="alert text-right"><?php echo $tr['IP address whitelist'];?></h5></div>
              <div id="file_list" class="col-auto col-9">
                  <?php echo $whitelist_content; ?>
              </div>
                    

              <div class="mt-1 col-1">
                  <button type="button" class="btn btn-success" id="add_more" onclick="addItem()" title="<?php echo $tr['add']; ?>">
                      <span class="glyphicon glyphicon-plus" aria-hidden="true"></span>
                  </button>
              </div>


              <div class="col-12 mt-3">
                  <div class="float-right">
                      <button id="submit_white_list_save" type="button" class="btn btn-success btn-sm">
                          <span class="glyphicon glyphicon-floppy-saved" aria-hidden="true"></span><?php echo $tr['Save']; ?>
                      </button>
                      <button id="cancel" type="button" class="btn btn-secondary ml-3 btn-sm " onclick="history.go(-1);"><?php echo $tr['back to previous page'];?></button>
                  </div>
              </div>
          </div>
      </form>
</div>

<div class="row">
  <div id="preview_result"></div>
</div>

<?php end_section(); ?>
<!-- end of panelbody_content -->


<!-- begin of extend_js -->
<?php begin_section('extend_js'); ?>
<script type="text/javascript" language="javascript" class="init">
function paginateScroll() { // auto scroll to top of page
  $("html, body").animate({
     scrollTop: 0
  }, 100);
}

// 新增ip白名單
function addItem(){
  // <input class="form-control white_listip validate[required,custom[ipv4]]"  placeholder="ex.192.168.1.1" value="" >
   var tmpl = `
    <div class="d-flex mt-1 input-group">
        <input class="form-control white_listip validate[required,custom[ipv4]]"  placeholder="ex.192.168.1.1" value="" >
        <div class="input-group-append ml-2">
          <button type="button" class="btn btn-danger delete_btn" title="刪除">
            <span class="glyphicon glyphicon-trash" aria-hidden="true"></span>
          </button>
        </div>
    </div>
   `;
  $(tmpl).fadeIn().appendTo("#file_list");
};

    // $('.white_listip').each(function(){
// 取得白名單欄位值
function get_whitelist_val(){

    if($("#whitelist_status").prop('checked')) {
        var whitelist_status = 1; 
    }else{
        var whitelist_status = 0; 
    }

    var white_listip=[];
    $("input[class*='white_listip']").each(function(){
        white_listip.push({
          name:'ip',
          value:$(this).val()
        });
    });
  
    // var white_submask=[];
    // $(".white_submask").each(function(){
    //     white_submask.push({
    //       name:'submask',
    //       value:$(this).val()
    //     });
    // });
    var data = {
          'whitelist_status':whitelist_status,
          'white_listip':white_listip,
          // 'white_submask':white_submask
          };

    // console.log(data);
    // return JSON.stringify(data);
    return data;
}

// 2FA的狀態啟用->取消時，只有取消成功會更改狀態，否則回復啟用。 取消->只有啟用成功會更改狀態，否則回復取消。
$(function() {
  var csrftoken = '<?php echo $csrftoken; ?>';

  // 啟動白名單欄位驗證
  $("#white_list_form").validationEngine();
  
  // 手風琴啟動
  $( "#accordion" ).accordion({
    collapsible: true,
    active:false,
    heightStyle:"content"
  });

  if($(twofa_status).prop('checked')){　　//啟用狀態
      var show_html='#twofa_modal_disable';//停用要開的網頁
      var originala_st=true;  //原本狀態
  }else{  //停用狀態
      var show_html='#twofa_modal_enable';//啟用要開的網頁
      var originala_st=false;
  }
  
  // 點選2FA狀態按鈕
  $('#twofa_status').click(function(){
      // 停用到啟用
      if($(this).prop('checked')) {
        $(show_html).modal('show');
        $(document).keydown(function(e) {
          switch(e.which) {
            case 13: // 啟用鍵
                $("#id_two_fa_enable").trigger("click");
            break;
          }
        });
      } else {　//啟用到停用
        $(show_html).modal('show');
        $(document).keydown(function(e) {
		      switch(e.which) {
            case 13: // 停用鍵
                $("#id_two_fa_disable").trigger("click");
            break;
		      }
        });
      }
  });

  // 當顯示modal視窗，而關閉時，並非成功更改狀態，則將checkbox 改為未選取
  $(show_html).on('hidden.bs.modal', function (e) {
        $('#twofa_status').prop('checked',originala_st);
        $("input[name='twofa_disable_ans']").val("");
        $("input[name='verify_code']").val("");
        $("input[name='twofa_ans']").val("");
  });

  // 當按下重新產生時，產生qr code 及金鑰
  $('#qr_code_refresh').click(function(){
    $.ajax ({
      url: 'member_security_setting.php',
      type: 'POST',
      dataType: 'json',
      data: ({
        behavior:'refresh',
        i:'<?php echo $_SESSION['agent']->id?>'
      }),
      success: function(response){
        // console.log(response.secret);
        // console.log(response.qrCodeUrl);
        $("#qrcode_id").attr("src", response.qrCodeUrl);
        $('#secret_id').text(response.secret); 
      },
      error: function (error) {
        // console.log('error555454');
        // $('#preview_result').html(error);
      }
    });

  });

  // 2fa 按下啟用按鈕 
  $('#id_two_fa_enable').click(function(){
      var twofa_question = $("select[name='twofa_question']").val();
      var twofa_ans      = $("input[name='twofa_ans']").val().trim();
      var verify_code    = $("input[name='verify_code']").val();
      var secret_id      = $("#secret_id").text();

      $.ajax ({
          url: 'member_security_setting.php',
          type: 'POST',
          dataType: 'json',
          data: ({
              behavior:'twofa_enable',
              i:'<?php echo $_SESSION['agent']->id?>',
              twofa_question:twofa_question,
              twofa_ans:twofa_ans,
              verify_code:verify_code,
              secret_id:secret_id
          }),
          success: function(response){
                if(!response.logger){
                    alert(response.success);
                    location.reload();
                }else{
                    alert(response.logger);
                }
                // console.log(response.qrCodeUrl);
          },
          error: function (error) {
                alert('启用状态传送资料发生错误！');
                // console.log(error);
                // $('#preview_result').html(error);
          }
      });
  });
  
  // 2fa　按下停用按鈕
  $('#id_two_fa_disable').click(function(){
    var twofa_disable_ans = $("input[name='twofa_disable_ans']").val();

    $.ajax ({
        url: 'member_security_setting.php',
        type: 'POST',
        dataType: 'json',
        data: ({
            behavior:'twofa_disable',
            i:'<?php echo $_SESSION['agent']->id?>',
            twofa_disable_ans:twofa_disable_ans
        }),
        success: function(response){
            if(!response.logger){
                alert(response.success);
                location.reload();
            }else{
                alert(response.logger);
            }
            // console.log(response.qrCodeUrl);
        },
        error: function (error) {
            alert('停用状态传送资料发生错误！');
            // $('#preview_result').html(error);
        }
    });
  });

  // 刪除ip白名單
  $("#file_list").on('click','.delete_btn',function(e){
      var select = $(e.target).parents(".input-group").remove();
      //console.log(select);
  });


  // 按下白名單儲存按鈕
  $("#submit_white_list_save").click(function(e){
      //檢查ip欄位是否有填寫正確，未符合格式則彈出不會進行啟用,jQuery-Validation-Engine
      if( $("#white_list_form").validationEngine('validate') ){
          var whitelist_val=get_whitelist_val();
          var ip_list =  $("input[class*='white_listip']").val();

          // 檢查是否啟用
          if(whitelist_val.whitelist_status==0){
            alert("请启用白名单功能!");
            return false; 
          }else if(whitelist_val.white_listip == ''){
            alert('请填写IP位址。');
            return false;
          }else if(ip_list == ''){
            alert('请填写IP位址。');
            return false;
          }

          // 資料量大要傳送，要用json，否則會有資料遺失   
          var json_whitelist_val=JSON.stringify(whitelist_val);

          $.ajax ({
              url: 'member_security_setting.php',
              type: 'POST',
              dataType: 'json',
              data: ({
                  'csrftoken': csrftoken,
                  'behavior':'white_list_save',
                  'i':'<?php echo $_SESSION['agent']->id?>',
                  'whitelist_data':json_whitelist_val
              }),
              success: function(response){
                  if(!response.logger){
                      alert(response.success);
                      location.reload();
                  }else{
                      alert(response.logger);
                  }
                  // console.log(response.qrCodeUrl);
              },
              error: function (error) {
                  alert('执行白名单设定，发生错误!');
                  // $('#preview_result').html(error);
              }
           });
      };
  });

  // 按下白名單狀態按鈕
  $('#whitelist_status').click(function(){

    // 啟用到停用
    if($(this).prop('checked')==false){
        if (confirm('你确定要停用白名单功能吗?')) {
            $.ajax ({
                url: 'member_security_setting.php',
                type: 'POST',
                dataType: 'json',
                data: ({
                    'csrftoken': csrftoken,
                    'behavior':'white_list_disabled',
                    'i':'<?php echo $_SESSION['agent']->id?>'
                }),
                success: function(response){
                    if(!response.logger){
                        alert(response.success);
                        location.reload();
                    }else{
                        alert(response.logger);
                    }
                    // console.log(response.qrCodeUrl);
                },
                error: function (error) {
                    alert('白名单位址停用设定，发生错误！');
                    // $('#preview_result').html(error);
                }
            });

        }else{
            $('#whitelist_status').prop('checked',true);
        }


    };
  });


});












$(function() {
  // ------------------------------------
  // 新增角色
  // -------------------------------------
  $("#submit_to_inquiry").click(function(e){
      e.preventDefault();

      // account id
      var a_id = $("#account_query_id").val();
      // account name
      var aq = $("#account_query").val();
      // 權限描述
      var per_desc = $("#account_permission_desc").val();

      //功能群組
      var group = $("#group").val();
     
      // 新增檔名
      var file_name = $(".file_name").val();
    
      // status
      if($("#status_open").prop('checked')){
        var status_open = 1 ; //open
      } else{
        var status_open = 0; // close
      }

      var formData = new FormData($('form')[0]);

      //console.log(formData);

      $.ajax({
        url: 'actor_management_operate_action.php?a=add',
        type: 'POST',
        processData: false,
        contentType: false,
        data: formData, 
        success: function(response){
          $('#preview_result').html(response);
        },
        error: function(error){
           $("#preview_result").html(error);
        }
      })
    })
});

</script>
<?php end_section(); ?>