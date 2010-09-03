/*
 *      index.js 5/5/2009
 *      Copyleft 2009 kanesodi <kanesodi@gmail.com>
 *      
 *      Fixed by epsylon3@gmail.com for tfb git
 *      
 *      This file is public domain;
 */

// turn unordened list into tabs
jQuery(document).ready(function(){
	

	jQuery("#tabs").tabs({ panelTemplate: '<li></li>' });

	jQuery("input:submit").button();
	jQuery('select#searchEngine').droplist({width:100})
		.css('display','inline-block')
		.css('width','130px')
		.css('vertical-align','bottom');
	jQuery('select').droplist({autoresize:true,slide:false,height:150});
 
});