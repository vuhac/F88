<?php function_exists('use_layout') or die() ?>
<?php use_layout("template/beadmin.tmpl.php") ?>

<!-- 页首 CSS 与 JS -->
<?php begin_section('extend_head') ?>
<link rel="stylesheet" type="text/css" href="./in/datatables/css/jquery.dataTables.min.css?v=180612">
<script type="text/javascript" language="javascript" src="./in/datatables/js/jquery.dataTables.min.js?v=180612"></script>
<script type="text/javascript" language="javascript" src="./in/datatables/js/dataTables.bootstrap.min.js?v=180612"></script>
<script>
  var configSet = <?php echo json_encode($api_config, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)?>;
  var state_map = ['启用', '关闭', '维护中'];

  // ref: https://www.oschina.net/translate/easy-two-way-data-binding-in-javascript
  function DataBinder( object_id ) {
    // Use a jQuery object as simple PubSub
    var pubSub = jQuery({});

    // We expect a `data` element specifying the binding
    // in the form: data-bind-<object_id>="<property_name>"
    var data_attr = "bind-" + object_id,
        message = object_id + ":change";

    // Listen to change events on elements with the data-binding attribute and proxy
    // them to the PubSub, so that the change is "broadcasted" to all connected objects
    jQuery( document ).on( "change", "[data-" + data_attr + "]", function( evt ) {
      var $input = jQuery( this );

      pubSub.trigger( message, [ $input.data( data_attr ), $input.val() ] );
    });

    // PubSub propagates changes to all bound elements, setting value of
    // input tags or HTML content of other tags
    pubSub.on( message, function( evt, prop_name, new_val ) {
      jQuery( "[data-" + data_attr + "=" + prop_name + "]" ).each( function() {
        var $bound = jQuery( this );

        if ( $bound.is("input, textarea, select") ) {
          $bound.val( new_val );
        } else {
          $bound.html( new_val );
        }
      });
    });

    return pubSub;
  }

  function _Config( cid ) {
    var binder = new DataBinder( cid ),
        config = {
          attributes: {},
          // The attribute setter publish changes using the DataBinder PubSub
          set: function( attr_name, val ) {
            this.attributes[ attr_name ] = val;
            binder.trigger( cid + ":change", [ attr_name, val, this ] );
          },

          get: function( attr_name ) {
            return this.attributes[ attr_name ];
          },

          _binder: binder
        };

    // Subscribe to the PubSub
    binder.on( cid + ":change", function( evt, attr_name, new_val, initiator ) {
      if ( initiator !== config ) {
        config.set( attr_name, new_val );
      }
    });

    return config;
  }

  // constructor
  function Config( cid ) {
    var _this = this
    var binder = new DataBinder( cid )

    _this.cid = cid
    _this._binder = binder

    // Subscribe to the PubSub
    this._binder.on( cid + ":change", function( evt, attr_name, new_val, initiator ) {
      if ( initiator !== _this ) {
        _this.set( attr_name, new_val );
      }
    });
  }

  Config.prototype = {
    constructor: Config,
    attributes: {},
    cid: null,
    gateway: location.href,
    set: function( attr_name, val ) {
      var _this = this
      this.attributes[ attr_name ] = val;
      this._binder.trigger( this.cid + ":change", [ attr_name, val, this ] );
    },
    get: function( attr_name ) {
      return this.attributes[ attr_name ];
    },
    postTo: function (url) {
      // ajax with post method
      var settings = {
        "async": true,
        "crossDomain": false,
        "url": url || this.gateay,
        "method": "POST",
        "headers": {
          "content-type": "application/json",
          "cache-control": "no-cache",
        },
        "data": JSON.stringify(this.attributes),
        "dataType": "json"
      }

      $.ajax(settings)

      .done(function (response) {
        if (response.success == "Y") {
          alert("成功！确认后跳转回列表页")
          location.href = location.origin + location.pathname
        } else {
          alert("失敗！讯息为 " + response.description)
        }
      })
      .fail(function (err) {
        console.log(err);
      })
    },
    patchTo: function (url) {
      // ajax with patch method
      var settings = {
        "async": true,
        "crossDomain": false,
        "url": url || this.gateay,
        "method": "PATCH",
        "headers": {
          "content-type": "application/json",
          "cache-control": "no-cache",
        },
        "data": JSON.stringify(this.attributes),
        "dataType": "json"
      }

      $.ajax(settings)

      .done(function (response) {
        if (response.success == "Y") {
          alert("成功！确认后跳转回列表页")
          location.href = location.origin + location.pathname
        } else {
          alert("失敗！讯息为 " + response.description)
        }
      })
      .fail(function (err) {
        console.log(err);
      })
    },
    deleteTo: function (url) {
      // ajax with delete method
    },
    redirectTo: function() {
      location.href = location.origin + location.pathname
    }
  }

  $(function() {
    // 多選欄位的綁定事件
    $('select[multiple]>option').mousedown(function(e) {
      e.preventDefault()
      var originalScrollTop = $(this).parent().scrollTop()
      $(this).prop('selected', !$(this).prop('selected'))

      var self = this;
      $(this).parent().focus()
      setTimeout(function() {
        $(self).parent().scrollTop(originalScrollTop);
      }, 0);
    })

    // config 綁定
    window.api_config = new Config("conf")
    for (attr in configSet) {
      api_config.set(attr, configSet[attr])
    }

    // render 可用會員群組
    // $('#available_member_grade').val(api_config.get('available_member_grade').split(','))
    // $('#available_member_grade').on('mousedown', update_available_member_grade)

    // function update_available_member_grade() {
    //   var gradelist = $('#available_member_grade').val()
    //   api_config.set('available_member_grade', gradelist.join(','))
    // }
    $available_member_grade_group = $("input[name^=available_member_grade]")
    $available_member_grade_group.each(function(idx, dom) {

      var $this = $(dom), checked = false
      var _available_member_grade_checked = api_config.get("available_member_grade").split(',')
      if ( _available_member_grade_checked ) {
        checked = api_config.get("available_member_grade").indexOf( $this.val() ) > -1
      }
      $this.prop("checked", checked)
    })

    // 事件綁定
    $available_member_grade_group.on("click", update_available_member_grade)

    function update_available_member_grade() {
      $available_member_grade_group = $("input[name^=available_member_grade]:checked")
      var new_available = []

      $available_member_grade_group.each(function(idx, dom) {
        var grade = $(dom).val()
          new_available.push( grade )
      })

      api_config.set("available_member_grade", new_available.toString())
    }

    // render 白名單
    $ip_tmpl = $("<input/>", {
      name: "ip_white_list[]",
      value: "",
      class: "form-control",
      style: "margin-top: 1px"
    })

    $("#ip_wlist_group").html("");

    if ( Array.isArray(api_config.get("ip_white_list")) ) {
      api_config.get("ip_white_list").forEach(function (ip) {
        $("#ip_wlist_group").append($ip_tmpl.clone().val(ip))
      });
    }

    $("#ip_wlist_group").on('keyup', update_ip_white_list)

    // 增加 IP 白名單
    $("#add_ip_white_list").on('click', function() {
      $("#ip_wlist_group").append($ip_tmpl.clone());
      update_ip_white_list();
    });

    // update and rebind
    function update_ip_white_list () {
      $_ip_group = $("input[name='ip_white_list[]']")
      $_ip_group.off().on('keyup, change', function () {
        var new_list = [];
        $_ip_group.each(function(idx, dom) {
          new_list.push( $(dom).val() )
        });

        api_config.set("ip_white_list", new_list)
      })
    }

    // render 可用服務
    $available_services_group = $("input[name^=available_services]")
    $available_services_group.each(function(idx, dom) {
      var $this = $(dom), checked = false
      if ( Array.isArray(api_config.get("available_services")) ) {
        checked = api_config.get("available_services").indexOf( $this.val() ) > -1
      }
      $this.prop("checked", checked)
    })

    // 事件綁定
    $available_services_group.on("click", update_available_services)

    function update_available_services() {
      $available_services_group = $("input[name^=available_services]:checked")
      var new_available = []

      $available_services_group.each(function(idx, dom) {
        var service = $(dom).val()
          new_available.push( service )
      })

      api_config.set("available_services", new_available)
    }
  });

  function check_required() {
    var is_pass = true
    $("*:required").each((idx, dom) =>
      {
        var $dom = $(dom)
        if( $(dom).val() == '' || $(dom).val() == null) {
          alert("请填入必填的栏位！")
          $(dom).trigger('focus')
          is_pass = false
          return false
        }
      }
    )
    return is_pass
  }
</script>

<?php end_section() ?>

<!-- position info -->
<?php begin_section('page_title') ?>
<ol class="breadcrumb">
  <li><a href="home.php"><?php echo $tr['homepage']?></a></li>
  <li><a href="#"><?php echo $tr['System Management']?></a></li>
  <li><a href="#"><?php echo $tr['Site Api Account Management'] ?? '站台接口帐户管理'?></a></li>
  <li class="active"><?php echo $tr['Site Api Account Maintenance'] ?? '站台接口帐户维护'?></li>
</ol>
<?php end_section() ?>

<!-- main title -->
<?php begin_section('paneltitle_content')  ?>
<span class="glyphicon glyphicon-bookmark" aria-hidden="true"></span><?php echo $tr['Site Api Account Maintenance'] ?? '站台接口帐户维护'?>
<?php end_section() ?>

<!-- main content -->
<?php begin_section('panelbody_content') ?>
<?php // echo $panelbody_content ?>
<div class="row">
  <div class="col-12 col-md-12">
  <div class="tab-content col-12 col-md-12">
  <br>
    <div role="tabpanel" class="tab-pane active col-12 col-md-12" id="inbox_View">
      <table id="inbox_transaction_list" class="table table-bordered" cellspacing="0" width="100%">
        <tbody>
          <div class="form-horizontal">
            <button type="button" class="btn btn-danger"><?php echo $tr['required field'];?></button>
            <hr>
            <div class="row form-group">
              <label class="col-sm-2 control-label"><?php echo $tr['set account name'];?></label>
              <div class="col-sm-10">
                <input type="text" class="form-control" name="account_name" placeholder="<?php echo $tr['Set the name, the suggested format '];?>" data-bind-conf="account_name" required>
              </div>
            </div>
            <div class="row form-group">
              <label class="col-sm-2 control-label"><?php echo $tr['api account'];?></label>
              <div class="col-sm-10">
                <input type="text" class="form-control" placeholder="<?php echo $tr['api account'];?>" data-bind-conf="api_account" disabled>
                <div class="text-right"><small><?php echo $tr['This field is automatically generated by the program.'];?></small></div>
              </div>
            </div>
            <?php if ($payment_flow_control_check): ?>
            <div class="row form-group">
              <label class="col-sm-2 control-label"><?php echo $tr['site api key'];?></label>
              <div class="col-sm-10">
                <textarea
                  class="form-control"
                  placeholder="<?php echo $tr['site api key'];?>"
                  data-bind-conf="api_key"
                  disabled
                ></textarea>
                <div class="text-right"><small><?php echo $tr['This field is automatically generated by the program.'];?></small></div>
              </div>
            </div>
            <?php endif;?>
            <div class="row form-group">
              <label class="col-sm-2 control-label"><?php echo $tr['available services'];?></label>
              <div class="col-sm-10" id="available_services_group">
                  <label class="d-none">
                    <input type="checkbox" class="form-check-input" name="available_services[]" value="gcash" disabled readonly><?php echo $tr['Franchise'];?>
                  </label>
                  <label class="checkbox-inline">
                    <input type="checkbox" class="form-check-input" name="available_services[]" value="gtoken" readonly selected><?php echo $tr['Gtoken'];?>
                  </label>
              </div>
            </div>
            <div class="row form-group">
              <label class="col-sm-2 control-label"><?php echo $tr['Member Level'] ?></label>
              <div class="col-sm-10" id="available_member_grade_group">
                <!-- <select class="form-control" id="available_member_grade" required multiple>
                    <?php foreach ($member_grade_rows as $id => $value) : ?>
                    <option value="<?php echo $id ?>"><?php echo $value->gradename ?></option>
                    <?php endforeach ?>
                </select> -->
                <?php foreach ($member_grade_rows as $id => $value) : ?>
                  <label class="checkbox-inline">
                    <input
                      type="checkbox"
                      class="form-check-input"
                      name="available_member_grade[]"
                      value="<?=$id?>"
                    ><?=$value->gradename?>
                  </label>
                <?php endforeach ?>
              </div>
            </div>
            <div class="row form-group">
              <label class="col-sm-2 control-label"><?php echo $tr['cash flow fee'];?></label>
              <div class="col-sm-10">
                <input type="text" class="form-control" placeholder="手续费(%)" data-bind-conf="fee_rate">
                <div class="text-right"><small><?php echo $tr['The fee will be calculated when calculating the agency commission.'];?></small></div>
              </div>
            </div>
            <div class="row form-group">
              <label class="col-sm-2 control-label"><?php echo $tr['status'] ?? '状态'?></label>
              <div class="col-sm-10">
                <select class="form-control" id="edit_status" data-bind-conf="status">
                  <option value="1"><?php echo $tr['off'];?></option>
                  <option value="0"><?php echo $tr['Enabled'];?></option>
                  <option value="2"><?php echo $tr['Maintenance'];?></option>
                </select>
              </div>
            </div>
            <div class="row form-group">
              <label class="col-sm-2 control-label"><?php echo $tr['API Account Category'] ?? $tr['site api account category'];?></label>
              <div class="col-sm-10">
                <select class="form-control" data-bind-conf="transaction_category" required>
                  <option value="deposit"><?php echo $tr['for deposit'];?></option>
                  <!-- <option value="withdrawal">出款用</option> -->
                </select>
              </div>
            </div>

            <button type="button" class="btn btn-warning"><?php echo $tr['Optional field'];?></button>
            <hr>
            <div class="row form-group">
              <div class="col-sm-2 font-weight-bold control-label"><?php echo $tr['ip whitelisting'];?>
                <button class="btn btn-success btn-sm" id="add_ip_white_list"><span class="glyphicon glyphicon-plus" aria-hidden="true"></span></button>
                <br>(<?php echo $tr['Leave all sources allowed without filling'];?>)
              </div>
              <div class="col-sm-10" id="ip_wlist_group">
                <input type="text" class="form-control" name="ip_white_list[]" placeholder="白名單" value="127.0.0.1">
              </div>
            </div>
            <div class="row form-group">
              <label class="col-sm-2 control-label"><?php echo $tr['Single deposit limit'];?><br>(<?php echo $tr['No fill, no limit'];?>)</label>
              <div class="col-sm-10">
                <input type="text" class="form-control" placeholder="<?php echo $tr['Single deposit limit'];?>" data-bind-conf="per_transaction_limit">
              </div>
            </div>
            <div class="row form-group">
              <label class="col-sm-2 control-label"><?php echo $tr['today deposit limit'];?><br>(<?php echo $tr['No fill, no limit'];?>)</label>
              <div class="col-sm-10">
                <input type="text" class="form-control" placeholder="<?php echo $tr['today deposit limit'];?>" data-bind-conf="daily_transaction_limit">
              </div>
            </div>
            <div class="row form-group">
              <label class="col-sm-2 control-label"><?php echo $tr['this month deposit limit'];?><br>(<?php echo $tr['No fill, no limit'];?>)</label>
              <div class="col-sm-10">
                <input type="text" class="form-control" placeholder="<?php echo $tr['this month deposit limit'];?>" data-bind-conf="monthly_transaction_limit">
              </div>
            </div>
            <div class="row form-group">
              <label class="col-sm-2 control-label"><?php echo $tr['Transaction effective seconds'];?><br>(<?php echo $tr['Preset 0 seconds is not limited'];?>)</label>
              <div class="col-sm-10">
                <input type="text" class="form-control" placeholder="<?php echo $tr['Transaction effective seconds'];?>" data-bind-conf="transaction_timeout">
              </div>
            </div>
            <div class="row form-group">
              <label class="col-sm-2 control-label"><?php echo $tr['Other online payment information'];?></label>
              <div class="col-sm-10">

                <textarea class="form-control" placeholder="<?php echo $tr['Other online payment information'];?>" data-bind-conf="notes"></textarea>
              </div>
            </div>
            <div class="row form-group">
              <div class="col-sm-offset-2 col-sm-10">
                <?php if ($action == 'add') : ?>
                  <button class="btn btn-primary" onclick="if(check_required()) api_config.postTo()"><?php echo $tr['add'];?></button>
                <?php elseif ($action == 'edit' && $payment_flow_control_check) : ?>
                  <button class="btn btn-primary" onclick="if(check_required()) api_config.patchTo()"><?php echo $tr['update'];?></button>
                <?php endif?>
                <button onclick="api_config.redirectTo()" class="btn btn-default"><?php echo $tr['return'];?></button>
              </div>
            </div>
            <div class="row form-group">
              <div class="col-sm-offset-2 col-sm-10">
                <div id="edit_show_result"></div>
              </div>
            </div>
          </div>
        </tbody>
      </table>
    </div>
  </div>

  </div>
</div>
<br>
<div class="row">
  <div id="preview_result"></div>
</div>

<?php end_section() ?>

<!-- main content -->
<?php begin_section('extend_js') ?>
<script>
</script>
<?php end_section() ?>
