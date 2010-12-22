/**
 * Top Menu show/hide
 */

jQuery(document).ready(function(){
	$('#header').css({marginTop: '-40px'});
	$('#header').bind({
		mouseenter: function(){
			$(this).animate({
				marginTop: '0px'
			},"fast");
		},
		mouseleave: function(){
			$(this).animate({
				marginTop: '-40px'
			},"fast");
		}
	});
});