/**
 *
 */

$(document).ready(function(){
	$('#header').animate({marginTop: '-40px'},1000);
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