/*	jquery.droplist v1.1 by Tanguy Pruvot Rev: $Rev$ $Id$

	19 September 2010 - http://github.com/tpruvot/jquery.droplist

	Licensed under the Apache License, Version 2.0 (the "License");
	you may not use this file except in compliance with the License.
	You may obtain a copy of the License at

	http://www.apache.org/licenses/LICENSE-2.0

	Unless required by applicable law or agreed to in writing, software
	distributed under the License is distributed on an "AS IS" BASIS,
	WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
	See the License for the specific language governing permissions and
	limitations under the License.

	forked from alex's v0.3r16 - http://code.google.com/p/droplist/

	jQuery('#mydiv select').droplist();
	jQuery('#mydiv .droplist').droplist().setValue('xxx');

	Files
	JS
		script/jquery.droplist.js
		script/jquery.mousewheel.js (if using customScroll)
		script/jquery.jScrollPane.js (if using customScroll)
  	CSS
		script/jquery.droplist.css
		script/images/jquery.droplist.png

*/
(function ($) {

	var DropList = function (el, options, callback) {

		var self = this;
		var isInsideForm = false;
		var callTriggers = false;
		var wx_opt, wx_drp, wx_lst;
		var onchange;

		// DEFAULT SETTINGS
		var defaults = { 
			'direction': 'auto', 
			'customScroll': true, 
			'autoresize' : false,
			'slide': true,
			'width': null,
			'height': 200,
			'selected': null
		};
		
		var settings = $.extend({}, defaults, options);
		
		if (settings.width !== null) {
			settings.autoresize = false;
		}

		// PRIVATE METHODS

		function setText(str) {
			self.option.html(text2html(str));
		}

		function customScroll() {
			var h1 = settings.height,
				h2 = self.listWrapper.innerHeight();
			if (h2 > h1) {
				self.list.css('height', h1 + 'px').jScrollPane({showArrows:false});
			}
		}

		function layout() {
			//listWrapper visible at this state, we can get padding widths;
			wx_lst = self.listWrapper.outerWidth() - self.listWrapper.width();
			wx_opt = self.option.outerWidth() - self.option.width();
			wx_drp = self.drop.outerWidth() - self.drop.width();
			self.listWrapper.width(settings.width - wx_lst);
			if (!settings.autoresize) {
				self.wrapper.css('clear','both');
				self.option.css('display','block');
				self.option.css('float','left');
				self.drop.css('display','block');
				self.drop.css('float','left');
				self.option.width(settings.width - self.drop.outerWidth() - wx_opt - wx_drp);
			}
		}

		var text2html = function (data) {
			//fix incorrect chars in possible values
			return data.replace("<","&lt;").replace(">","&gt;");
		}
		
		var text2js = function (data) {
			//fix incorrect quote in possible values
			data = (data !== null) ? data : '';
			return data.replace("'","\'");
		}
		
		var text2attr = function (data) {
			//fix incorrect dbquote in attr value
			data = (data !== null) ? data : '';
			return data.replace('"','\"');
		}
		
		var options2list = function (data) {
			var output = '<ul>';
			data.each(function () {
				var selected = $(this).attr('selected') ? 'selected' : '';
				selected += ($(this).attr('class') || '');
				if (!!$(this).attr('disabled'))
					output += '<li class="'+selected+' "><span class="disabled">' + text2html($(this).text()) + '<span></li>\t';
				else
					output += '<li class="'+selected+' "><a href="' + $(this).val() +'">' + text2html($(this).text()) + '</a></li>\t';
			});
			output += '</ul>';
			return output;
		};


		// PUBLIC METHODS

		self.setValue = function (val, trigger) {
			var item = self.listItems.find(">a[href='"+text2js(val)+"']").closest('li');
			if (item.length <= 1) {
				self.callTriggers = (trigger && self.get() != val);
				self.set(item);
				self.callTriggers = true;
			}
		}

		self.open = function () {

			// just show
			if (settings.slide)
				self.listWrapper.slideDown(60);
			else
				self.listWrapper.show();

			// close other opened lists
			var opened = $('html').find('.droplist-active');
			if (opened !== null && opened.length > 0) {
				opened.data('droplist').close();
			}

			self.wrapper.addClass('droplist-active');

			// auto direction
			if (settings.direction === 'auto') {
				var distanceFromBottom = (self.wrapper.offset().top - $(document).scrollTop() - $(window).height()) * -1,
					objHeight = self.listWrapper.height();
				if (distanceFromBottom < objHeight || distanceFromBottom < 100) {
					self.wrapper.addClass('droplist-up');
				} else {
					self.wrapper.removeClass('droplist-up');
				}
			} else if (settings.direction === 'up') {
				self.wrapper.addClass('droplist-up');
			}

			// focus selected item (auto scroll)
			if (settings.customScroll)
				self.listItems.filter('.selected').focus();

			// events (clickout / ESC key / type-ahead)
			self.typedKeys = '';

			$('html').click( function (e) {

				// clickout
				if ($(e.target).closest('.droplist').length === 0 || $(e.target).hasClass('droplist-value')) {
					self.close();
				}

			}).keydown( function (e) {

				var curSel = self.listItems.filter('.focused').first();
				if (!curSel.length) curSel = self.listItems.filter('.selected').first();
				var curPos = self.listItems.index(curSel);
				var nextSelection=null;

				// get keycode
				if (e === null) { // old ie
					e = event;
					keycode = e.keyCode;
				} else { // moz, webkit, ie8
					keycode = e.which;
				}

				// esc/tab
				if (keycode == 27 || keycode == 9) {
					self.close();
					e.preventDefault();
					return true;
				}

				//enter,space : selection
				else if (keycode == 13 || keycode == 32) {
					if (curPos >= 0) {
						self.set(self.listItems.filter('.focused').first());
						e.preventDefault();
					} else {
						//to check...
						var focused = self.list.filter('a:focus'),
						current = (focused.parent().is('li')) ? focused.parent() : self.listItems.first();
						self.set(current);
					}
					return true;
				}

				// type-ahead support
				else if ((keycode >= 0x30 && keycode <= 0x7a)) {

					var newKey = '' + String.fromCharCode(keycode);
					var searchFrom = 0;
					if (self.typedKeys != newKey) {
						curPos = -1;
						self.typedKeys += newKey;
					} else {
						//same key repeated, next element
						if (curPos >= 0) {
							searchFrom = curPos+1;
							curPos = -1;
							self.typedKeys = newKey;
							clearTimeout(self.typeDelay);
						}
					}
					if (curPos == -1) {
						clearTimeout(self.typeDelay);
						self.typeDelay = setTimeout(function () {
							self.typedKeys = '';
						}, 800);
						self.listItems.slice(searchFrom).each(function () {
							if ($(this).find('>a').text().toUpperCase().indexOf(self.typedKeys) === 0) {
								nextSelection = $(this);
								return false; //exit each() only, not func.
							}
						});
					}

				} else {

					self.typedKeys = '';

					//down arrow
					if (keycode == 40) {
						if (curPos >= 0)
							nextSelection = self.listItems.eq(curPos+1);
						if (nextSelection === null || nextSelection.length === 0)
							nextSelection = self.listItems.last();
					}
					//up arrow
					else if (keycode == 38) {
						if (curPos > 0)
							nextSelection = self.listItems.eq(curPos-1);
						if (nextSelection === null || nextSelection.length === 0)
							nextSelection = self.listItems.first();
					}
					//page down
					else if (keycode == 34) {
						if (curPos >= 0)
							nextSelection = self.listItems.eq(curPos+10);
						if (nextSelection === null || nextSelection.length === 0)
							nextSelection = self.listItems.last();
					}
					//page up
					else if (keycode == 33) {
						if (curPos >= 10)
							nextSelection = self.listItems.eq(curPos-10);
						else
							nextSelection = self.listItems.first();
					}
					//home key
					else if (keycode == 36) {
						nextSelection = self.listItems.first();
					}
					//end key
					else if (keycode == 35) {
						nextSelection = self.listItems.last();
					}

				}

				if (nextSelection !== null) {
					self.listItems.removeClass('focused');
					nextSelection.addClass('focused').focus();
					e.preventDefault();
					return false;
				}

			});

		};

		self.close = function (fast) {
			self.wrapper.removeClass('droplist-active');
			$('html').unbind('click').unbind('keydown');
			if (settings.slide && !fast)
				self.listWrapper.slideUp(40);
			else
				self.listWrapper.hide();
		};

		self.set = function (el) {
			var str,val;
			self.listItems.removeClass('selected');
			if ($(el).length == 0) {
				str = self.obj.title;
				val = "";
			} else {
				str = $(el).find('>a').text();
				val = $(el).find('>a').attr('href');
				$(el).addClass('selected');
			}

			setText(str);
			if (self.inputHidden.length) {
				self.inputHidden.attr('value', val);
			}

			self.close(1);

			//set container width to div + dropdown bt width to prevent dropdown br
			if (settings.autoresize) {
				self.option.css('width','');
				self.select.css('display','inline-block');
				//self.select.width(settings.width);
				if (self.option.outerWidth() > settings.width - self.drop.outerWidth()) {
					//max width to settings
					self.option.width(settings.width - self.drop.outerWidth() - wx_opt - wx_drp);
				}
				self.select.width(self.option.outerWidth() + self.drop.outerWidth() + 1);

				self.wrapper.css('display','inline-block');
				self.wrapper.width(self.option.outerWidth() + self.drop.outerWidth());
			} else {
				self.option.width(settings.width - self.drop.outerWidth() - wx_opt - wx_drp);
			}
			
			if (self.callTriggers) {
				if (self.onchange) {
					//set "this.value"
					self.obj.val(val); //firefox, chrome
					self.obj.html( //IE8 doesnt want a value without selected <option>
						$('<option selected="selected"></option>').val(val).html('')
					);
					self.obj.trigger('onchange');
				} else {
					self.obj.trigger('droplistchange', self);
				}
			}
		};

		self.get = function () {
			return self.list.find('.selected:first a').attr('href');
		};

		self.tabs = function () {
			var that = this;
			that.list.find('li').click( function (e) {
				that.set(this);
				var id = $(this).find('a').attr('href');
				jQuery(id).removeClass('hide').show().siblings().hide();
				e.preventDefault();
				return false;
			});
		};


		// CONTROLLER
		self.obj = $(el);
		self.obj.css('border','none');

		self.obj.className = self.obj.attr('class');
		self.obj.name = self.obj.attr('name');
		self.obj.id = self.obj.attr('id');
		self.obj.title = self.obj.attr('title') || '';
		self.obj.width = self.obj.outerWidth();
		settings.width = settings.width || self.obj.width;
		self.onchange = self.obj[0].getAttribute('onchange');

		// insert wrapper
		var wrapperHtml = '<div class="' + self.obj.className + ' droplist"><div class="droplist-list"></div></div>';

		// get elements
		self.wrapper = self.obj.removeAttr('class').wrap(wrapperHtml).parent().parent();
		if (self.obj.id) self.wrapper.attr('id',self.obj.id+'_div');
		self.listWrapper = self.wrapper.find('.droplist-list:first');
		self.list = self.listWrapper.find('ul:first');

		// case it's a SELECT tag, not a UL
		if (self.list.length === 0) {
			isInsideForm = true;
			var html = '',
				optgroups = self.listWrapper.find('select:first optgroup'),
				options;
			if (optgroups.length > 0) {
				html += '<ul>';
				optgroups.each(function () {
					options = $(this).find('option');
					html += '<li><strong>' + $(this).attr('label') + '</strong>' + options2list(options) + '</li>';
				});
				html += '</ul>';
			} else {
				options = self.listWrapper.find('select:first option');
				html += options2list(options);
			}
			self.listWrapper.html(html);
			self.list = self.listWrapper.find('ul:first');
		}

		// insert HTML into the wrapper
		self.wrapper.prepend('<div class="droplist-value"><div></div><a class="nogo" href="#nogo"></a></div>');

		// input hidden
		if (isInsideForm) {
			self.wrapper.append('<input type="hidden" name="" value="" />');//onchange="'+text2attr(self.onchange)+'" />');
		}

		// GET ELEMENTS
		self.listItems = self.list.find('li a').closest('li');
		self.select = self.wrapper.find('.droplist-value:first');
		self.zone   = self.select.find('div,a');
		self.option = self.select.find('div:first');
		self.drop = self.select.find('a:first');
		self.inputHidden = self.wrapper.find('input[type=hidden]:first');

		if (isInsideForm) {
			self.inputHidden.attr('name',self.obj.name);
			if (self.obj.id) self.inputHidden.attr('id',self.obj.id+'_hidden');
			//we need to find a way to detect external change of select value via javascript
			if (self.inputHidden[0].addEventListener)
			self.inputHidden[0].addEventListener('DOMAttrModified', function (e) {
				if (callTriggers && e.attrName == 'value') {
					//working only in Mozilla
					window.alert(e.attrName);
				}
			}, false);
		}

		// EVENTS
		jQuery.event.copy(self.obj,self.wrapper);
		
		// null function to prevent browser default events
		function preventDefault (e) {
			e.preventDefault();
			return true;
		}

		// clicking on selected value or dropdown button
		if (self.wrapper.hasClass("disabled"))
			self.drop.removeAttr('href');
		else
		self.zone.mousedown( function (e) {
			if (self.listWrapper.is(':hidden')) {
				self.open();
			} else {
				self.close();
			}
			return preventDefault(e);
		});
		//cancel href #nogo jump
		self.drop.click(preventDefault);

		//clicking on an option inside a form
		self.list.find('li a').closest('li').click( function (e) {
			self.set($(this));
			return preventDefault(e);
		});
		//cancel href links
		self.list.find('li a').click(preventDefault);

		//title
		if (self.obj.title !== "") { setText(self.obj.title); }

		// ADJUST LAYOUT (WIDTHS)
		layout();

		// CUSTOM SCROLL
		if (settings.customScroll) {
			customScroll();
		}

		// INITIAL STATE
		self.close(1);

		// set selected item
		if (settings.selected !== null) {
			self.setValue(settings.selected);
		}
		else if (! self.obj.title) {
			var selectedItem = self.list.find('.selected');
			if (selectedItem.length === 1)
				self.set(selectedItem);
			else
				self.set(self.list.find('li a').closest('li').first());
		}

		// CALLBACK
		if (typeof callback == 'function') { callback.apply(self); }

		//enable triggers
		self.callTriggers = true;

	};

	/**
	 * Logic for copying events from one jQuery object to another.
	 *
	 * @name jQuery.events.copy
	 * @param jQuery|String|DOM Element jQuery object to copy events from. Only uses the first matched element.
	 * @param jQuery|String|DOM Element jQuery object to copy events to. Copies to all matched elements.
	 * @type undefined
	 * @cat Plugins/copyEvents
	 * @author Brandon Aaron (brandon.aaron@gmail.com || http://brandonaaron.net)
	 */
	jQuery.event.copy = function(from, to) {
		from = (from.jquery) ? from : jQuery(from);
		to   = (to.jquery)   ? to   : jQuery(to);
		
		if (!from.size() || !from[0].events || !to.size()) return;
			
		var events = from[0].events;
		to.each(function() {
			for (var type in events)
				for (var handler in events[type])
					jQuery.event.add(this, type, events[type][handler], events[type][handler].data);
		});
	};

	// extend jQuery
	jQuery.fn.droplist = function (settings, callback) {
		var newDiv=jQuery();
		this.each(function (){
			var sel = $(this);
			var obj = sel.data('droplist');
			if (obj) {
				// return early if this obj already has a plugin instance
				if (obj !== 1) {
					// return plugin instance $('.droplist').droplist().setValue(xxx)
					newDiv = obj;
					return false;
				}
				//continue to next object to find/create
				return true;
			}
			var instance = new DropList(this, settings, callback);
			sel.data('droplist', 1);

			//external data access, ex: $('.droplist').data('droplist').setValue(xxx);
			instance.wrapper.data('droplist', instance);
			//$.extend(instance.wrapper,instance);
			$.merge(newDiv,instance.wrapper);
		});
		//substitute select/ul by new div(s)
		return newDiv;
	};

})(jQuery);