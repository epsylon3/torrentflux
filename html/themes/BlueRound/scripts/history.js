/*
 *      zebra.js 29/4/2009
 *      
 *      Copyleft 2009 kanesodi <kanesodi@gmail.com>
 *      
 *      This file is public domain;
 */

// zebra.js applies any odd row with a alternative color and a hover.
$(function(){
	$('.strip tr:gt(1)')//mouse over & mouse out
		.mouseover(function(){$(this).addClass('over');})
		.mouseout(function(){$(this).removeClass('over');});
	$('.strip tr:gt(0):odd')// zebra stripes
		.addClass('zebra');
});
