/**
 * Top Menu show/hide
 */

jQuery(document).ready(function(){
	$('#header').bind({
		mouseover: function(){
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