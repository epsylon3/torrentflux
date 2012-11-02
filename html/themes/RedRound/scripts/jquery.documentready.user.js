/**
 * Top Menu show/hide
 */

jQuery(document).ready(function(){
	$('#header').bind({
		mouseover: function(){
			$(this).stop(true, true).animate({
				marginTop: '0px'
			},"fast");
		},
		mouseleave: function(){
			$(this).stop(true, true).animate({
				marginTop: '-40px'
			},"fast");
		}
	});
});