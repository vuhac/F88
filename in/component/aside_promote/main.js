function aside_promote($aside_promote,$div="body"){
  var aside_promote_html='';
  var aside_promote_style='';
  for (var i = 0; i < $aside_promote.length; i++) {        
          aside_promote_style +='#'+$aside_promote[i]["id"]+' .aside-promote-content{background-image: url(in/component/aside_promote/'+$aside_promote[i]["style"]+'/content.png);}';
          aside_promote_html+='\
          <aside id="'+$aside_promote[i]["id"]+'" class="aside-promote '+$aside_promote[i]["style"]+'">\
            <ul>\
              <li class="aside-promote-title"><img src="in/component/aside_promote/'+$aside_promote[i]["style"]+'/title.png" alt="title"></li>';

        for (var k = 0; k < $aside_promote[i]["content"].length; k++) {
          aside_promote_html+='\
              <a href="'+$aside_promote[i]["content"][k]["link"]+'" target="'+$aside_promote[i]["content"][k]["target"]+'">\
                <li class="aside-promote-content">\
                  <div class="content-title">'+$aside_promote[i]["content"][k]["title"]+'</div>\
                  <div class="content-txt">'+$aside_promote[i]["content"][k]["txt"]+'</div>\
                </li>\
              </a>';  
        }
        aside_promote_html+='\
              <li class="aside-promote-foot"><img src="in/component/aside_promote/'+$aside_promote[i]["style"]+'/foot.png" alt="foot"></li>';

        if($aside_promote[i]["closeable"]==true){
          aside_promote_html+='\
          <li class="aside-promote-close" onclick="document.getElementById(\''+ $aside_promote[i]["id"] +'\').style.display=\'none\';"><img src="in/component/aside_promote/'+$aside_promote[i]["style"]+'/close.png" alt="close"></li>\
            </ul>';
        }
        aside_promote_html+='</ul></aside>';
  }

  $("head").append('<style>'+aside_promote_style+'</style>');
  $($div).append(aside_promote_html);

    var $win = $(window),
      $ad = [],
      _width = [],
      _height = [], 
      _diffY = [], _diffX = 20, // 距離右及下方邊距
      _moveSpeed = 800; // 移動的速度

  $(window).load(function(){
      for (var i = 0; i < $aside_promote.length; i++) {
        var ad_temp = $('#'+$aside_promote[i]["id"]).css('opacity', 0).show();
        $ad.push($(ad_temp));  // 讓廣告區塊變透明且顯示出來
        _width.push($ad[i].width());
        _height.push($ad[i].height());
        _diffY.push($aside_promote[i]["position-y"]);
        
        // 先移動到定點
        $ad[i].css({
          top: $(document).height(),
          left: $win.width() - _width[i] - _diffX[i],
          opacity: 1
        });
        //console.log(data["aside-promote"][i]["id"]);
      }
         
      // 幫網頁加上 scroll 及 resize 事件
      $win.bind('scroll resize', function(){
        var $this = $(this);
        for (var i = 0; i < $aside_promote.length; i++) {    
          // 控制 #abgne_float_promote 的移動
          if($aside_promote[i]["position-x"]=="right"){
            $ad[i].stop().animate({
              top: $this.scrollTop() + window.innerHeight - _height[i] - _diffY[i],
              left: $this.scrollLeft() + window.innerWidth - _width[i] - _diffX
            }, _moveSpeed); 
          }
          else{
            $ad[i].stop().animate({
              top: $this.scrollTop() + window.innerHeight - _height[i] - _diffY[i],
              right: $this.scrollLeft() + window.innerWidth - _width[i] - _diffX
            }, _moveSpeed); 
          }
         }
      }).scroll();  // 觸發一次 scroll()
  });
}