/*
 *      common.js 6/5/2009
 *      
 *      Copyleft 2009 kanesodi <kanesodi@gmail.com>
 *      
 *      This file is public domain;
 */

jQuery(document).ready(function(){

	// open admin link in a new page
	jQuery(function(){
		jQuery('.overlay').linkControl({overlay:true, padding:5, bgColor:'#eee', borderColor:'#333'});
	});
	
	// credits window open when link is clicked
	jQuery(function(){
		jQuery('#credits').hide();
		jQuery('#credit_link').click(function(){
			jQuery(document).scrollTop(0);
			jQuery('#credits').dialog({
				resizable: false,
				width: 500,
				height: 300
			});
		});
	});

});