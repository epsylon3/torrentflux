 /*
 * Copyright (c) 2008 John McMullen (http://www.smple.com)
 * This is licensed under GPL (http://www.opensource.org/licenses/gpl-license.php) licenses.
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * For a copy of the GNU General Public License, see <http://www.gnu.org/licenses/>.
 *
*/ 
 
 (function($){
	$.fn.linkControl = function(options){
	
		var defaults = {
			padding: 5,
			bgColor: '#fff',
			borderColor: '#333',
			inline: false,
			overlay: false
		};
		
		var options = $.extend(defaults, options);

		var linky = '';
		var currentText = '';

		var element = this;
		
		return this.each(function(){
			
			if(options.inline === true){
				$(this).hover(
					function(over){
						linky = $(this).attr('href');
						currentText = $(this).text();
						$(this).removeAttr('href');			
						
						$(this).html('<a href="' + linky + '">' + currentText + '</a> - Open link in <a alt=\"'+ currentText +'\" href=\"' + linky + '" target=\"blank\" class=\"optionsOver\"> New Window</a>');
					},
								   
					function(out){
						$(this).html(currentText).attr('href',linky);
					}
				); // end this.hover
			} // end options.inline
			
			if(options.overlay === true){
				$(this).hover(
					function(over){
						linky = $(this).attr('href');
						currentText = $(this).text();
						$(this).removeAttr('href');
						var w = $(this).width();
						
						$(this).css('position','relative');
						$(this).html(currentText + '<div id="link-text" style="position:absolute; display:block; border-right:none; z-index:10; background:' + options.bgColor + '; border:' + options.borderColor + ' 1px solid; border-right:none;"><a href="' + linky + '">' + currentText + '</a></div><span style="width:120px; position:absolute; top:-' + (options.padding+1) + 'px; left: 45px; padding:' + options.padding + 'px; background:' + options.bgColor + '; border:' + options.borderColor + ' 1px solid; z-index:9;"><a alt=\"'+ currentText +'\" href=\"' + linky + '" target="blank"> Open in New Window</a></span>');
						
						$('#link-text').css({
							top: '-' + (options.padding+1) + 'px',
							left: '-' + (options.padding+1) + 'px',
							padding: options.padding,
							paddingRight: options.padding+1,
							width: (w+options.padding)
						});
					},
								   
					function(out){
						$(this).html(currentText).attr('href',linky);
					}
				);
			} // options.overlay
			
		}); // end this.each
	
	}; // end fn.linkControl
	
})(jQuery);
