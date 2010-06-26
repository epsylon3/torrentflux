/*
 *      common.js 6/5/2009
 *      
 *      Copyleft 2009 kanesodi <kanesodi@gmail.com>
 *      
 *      This file is public domain;
 */


// open admin link in a new page
	$(function(){
		$('.overlay').linkControl({overlay:true, padding:5, bgColor:'#eee', borderColor:'#333'});
	});
	
// credits window open when link is clicked
	$(function(){
		$('#credits').hide();
		$('#credit_link').click(function(){
			$('#credits').dialog({
				resizable: false,
				bgiframe: true,
				width: 500,
				height: 300
				});
			});
		$('#credit_tabs').tabs({
			event: 'mouseover'
		});
	});
