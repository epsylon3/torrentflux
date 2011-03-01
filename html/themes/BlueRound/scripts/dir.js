/*
 *      dir.js 6/5/2009
 *      
 *      Copyleft 2009 kanesodi <kanesodi@gmail.com>
 *      
 *      This file is public domain;
 */


// jQuery modal message warning about storage usage
	$(function() {
		$("#dialog").dialog({
			bgiframe: true,
			modal: true,
			buttons: {
				Ok: function() {
					$(this).dialog('close');
				}
			}
		});
	});
