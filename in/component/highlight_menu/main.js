function highlight_menu($highlight_menu){
	for (var i = 0; i < $highlight_menu.length; i++) {
  		$(".navi_"+$highlight_menu[i][0]).addClass($highlight_menu[i][1]+" hot");
	}
} 