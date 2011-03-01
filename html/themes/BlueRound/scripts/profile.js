/*
 *      profile.js 16/5/2009
 *      
 *      Copyleft 2009 kanesodi <kanesodi@gmail.com>
 *      
 *      This file is public domain;
 */

// accordion for profile settings
	$(function() {
		$("#accordion").accordion({
			collapsible: false,
			autoHeight: true,
			alwaysOpen: false,
			active: false,
			icons: {
    			header: "ui-icon-circle-arrow-e",
   				headerSelected: "ui-icon-circle-arrow-s"
			}
		});
	});
	
// cookie management dialog
	$(function(){
		$('#cookie').click(function(){
			$('#ShowCookies').dialog({
				modal: true,
				width: 600,
				height: 500
				});
			});
		});
