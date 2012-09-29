/*	jquery.droplist v1.7git by Tanguy Pruvot Rev: $Rev$ $Id$

	29 September 2012 - http://github.com/tpruvot/jquery.droplist

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

	var DropList = function (element, _settings, callback) {

		var me = this,
			isInsideForm = false,
			callTriggers = false,
			wx_opt, wx_drp, wx_lst, wx_border,
			onchange,

			// SETTINGS
			// ==============================================================================

			defaults = {
				'direction': 'auto',
				'customScroll': true,
				'autoresize' : false,
				'showkeys': true,
				'slide': true,
				'width': null,
				'height': 200,
				'selected': null,
				'namespaces': {
					droplist: 'droplist',
					clickout: 'droplistClickout'
				}
			},

			settings = jQuery.extend({}, defaults, _settings || {});

		if (settings.width !== null) {
			settings.autoresize = false;
		}


		// PRIVATE METHODS
		// ==============================================================================

		var setText = function (str) {
			me.option.html(text2html(str));
		};

		var customScroll = function () {
			var h1 = settings.height,
				h2 = me.dropdown.height();
			if (h2 > h1) {
				me.list.css('height', h1 + 'px').jScrollPane({
					showArrows:false
				});
			}
		};

		var layoutController = function () {
			//dropdown visible at this state, we can get padding widths;
			wx_lst = me.dropdown.outerWidth() - me.dropdown.innerWidth();
			wx_opt = me.option.outerWidth() - me.option.width();
			wx_drp = me.dropbtn.outerWidth() - me.dropbtn.innerWidth();
			//wx_drp = me.dropbtn.outerWidth() - me.dropbtn.width();
			//wx_lst = me.dropdown.outerWidth() - me.dropdown.width();
			wx_border = parseInt(me.wrapper.css('borderRightWidth')) * 2;
			me.dropdown.width(settings.width - wx_lst);

			//set container div like a button
			me.wrapper.css({ 
				'display':'inline-block',
				'vertical-align':'bottom'
			});

			me.dropbtn.css({
				'position':'absolute',
				'right':'0px'
			});

			me.wrapper.css('clear','both');
			if (!settings.autoresize) {
				me.option.css({
					'display':'inline-block'
				});
				me.option.width(settings.width - me.dropbtn.width() - wx_opt - wx_drp);
				me.wrapper.width(settings.width);
			}
		};

		var displayKeys = function (keys) {
			if (keys == '') return;
			me.spankeys.html(keys.toLowerCase()+'<span style="text-decoration: blink;">|</span>');
			me.spankeys.css({
				'position':'absolute',
				'top':'3px',
				'left':'3px',
				'line-height':'16px',
				'color':'blue',
				'background-color':'white',
				'opacity':'0.95'
			});
			me.spankeys.show();
			clearTimeout(me.showDelay);
			me.showDelay = setTimeout(function () {
				me.spankeys.hide();
			}, 1200);
		};
		
		var text2html = function (data) {
			//fix incorrect chars in possible values
			return data.replace("<","&lt;").replace(">","&gt;");
		};

		var text2js = function (data) {
			//fix incorrect quote in possible values
			data = (data !== null) ? data : '';
			return data.replace("'","\'");
		};

		var text2attr = function (data) {
			//fix incorrect dbquote in attr value
			data = (data !== null) ? data : '';
			return data.replace('"','\"');
		};

		var options2list = function (data) {
			var output = '<ul>';
			data.each(function () {
				var selected = jQuery(this).attr('selected') ? 'selected' : '';
				selected += ' ' + (jQuery(this).attr('class') || '');
				if (!!jQuery(this).attr('disabled'))
					output += '<li class="'+selected.trim()+'"><span class="disabled">' + text2html(jQuery(this).text()) + '<span></li>\t';
				else
					output += '<li class="'+selected.trim()+'"><a href="' + jQuery(this).val() +'">' + text2html(jQuery(this).text()) + '</a></li>\t';
			});
			output += '</ul>';
			return output;
		};

		var setInitialTitle = function () {
			if (me.obj.title !== '') {
				setText(me.obj.title);
			}
		};

		var setInitialSelected = function () {
			var selectedItem = me.list.find('.selected');
			if (selectedItem.length === 1)
				me.set(selectedItem);
			else
				me.set(me.list.find('li a').closest('li').first());
		};


		// PUBLIC METHODS
		// ==============================================================================

		me.setValue = function (val, trigger) {
			var item = me.listItems.find(">a[href='"+text2js(val)+"']").closest('li');
			if (item.length <= 1) {
				me.callTriggers = (trigger && me.get() != val);
				me.set(item);
				me.callTriggers = true;
			}
		}

		me.open = function () {

			// just show
			if (settings.slide)
				me.dropdown.slideDown(60);
			else
				me.dropdown.show();

			// close other opened lists
			var opened = jQuery(document).find('.droplist-active');
			if (opened !== null && opened.length > 0) {
				opened.data('droplist').close();
			}

			me.wrapper.addClass('droplist-active');

			// auto direction
			if (settings.direction === 'auto') {
				var distanceFromBottom = (me.wrapper.offset().top - jQuery(document).scrollTop() - jQuery(window).height()) * -1,
					objHeight = me.dropdown.height();
				if (distanceFromBottom < objHeight || distanceFromBottom < 100) {
					me.wrapper.addClass('droplist-up');
				} else {
					me.wrapper.removeClass('droplist-up');
				}
			} else if (settings.direction === 'up') {
				me.wrapper.addClass('droplist-up');
			}

			// focus selected item (auto scroll)
			if (settings.customScroll)
				me.listItems.filter('.selected').focus();

			// events (clickout / type-ahead)
			me.typedKeys = '';

			jQuery(document).bind('click.' + settings.namespaces.clickout, function (e) {

				// clickout
				if (jQuery(e.target).closest('.droplist').length === 0 || jQuery(e.target).hasClass('droplist-value')) {
					me.close();
				}

			}).bind('keydown.' + settings.namespaces.clickout, function (e) {

				var curSel = me.listItems.filter('.focused').first();
				if (!curSel.length) curSel = me.listItems.filter('.selected').first();
				var curPos = me.listItems.index(curSel);
				var nextSelection=null;

				// get keycode
				var keycode = null;
				if (e === null) { // old ie
					e = event;
					keycode = e.keyCode;
				} else { // moz, webkit, ie8
					keycode = e.which;
				}

				// esc/tab
				if (keycode === 27 || keycode === 9) {
					me.close();
					e.preventDefault();
					return true;
				}

				//enter,space : selection
				else if (keycode === 13 || keycode === 32) {
					if (curPos >= 0) {
						me.set(me.listItems.filter('.focused').first());
					} else {
						var focused = me.list.filter('a:focus'),
							current = (focused.parent().is('li')) ? focused.parent() : me.listItems.first();
						me.set(current);
					}
					e.preventDefault();
					return true;
				}

				// type-ahead support
				else if (keycode >= 0x30 && keycode <= 0x7a) {

					var key = '' + String.fromCharCode(keycode);
					var searchFrom = 0;
					if (me.typedKeys != key) {
						curPos = -1;
						me.typedKeys += key;
					} else {
						//same key repeated, next element
						if (curPos >= 0) {
							searchFrom = curPos+1;
							curPos = -1;
							me.typedKeys = key;
							clearTimeout(me.typeDelay);
						}
					}
					if (curPos == -1) {
						clearTimeout(me.typeDelay);
						me.typeDelay = setTimeout(function () {
							me.typedKeys = '';
						}, 1200);
						me.listItems.slice(searchFrom).each(function () {
							var link = jQuery(this).find('>a');
							if (link.text().toUpperCase().indexOf(me.typedKeys) === 0) {
								nextSelection = jQuery(this);
								return false; //exit each() only, not func.
							}
						});
					}

				} else {

					me.typedKeys = '';

					//down arrow
					if (keycode == 40) {
						if (curPos >= 0)
							nextSelection = me.listItems.eq(curPos+1);
						if (nextSelection === null || nextSelection.length === 0)
							nextSelection = me.listItems.last();
					}
					//up arrow
					else if (keycode == 38) {
						if (curPos > 0)
							nextSelection = me.listItems.eq(curPos-1);
						if (nextSelection === null || nextSelection.length === 0)
							nextSelection = me.listItems.first();
					}
					//page down
					else if (keycode == 34) {
						if (curPos >= 0)
							nextSelection = me.listItems.eq(curPos+10);
						if (nextSelection === null || nextSelection.length === 0)
							nextSelection = me.listItems.last();
					}
					//page up
					else if (keycode == 33) {
						if (curPos >= 10)
							nextSelection = me.listItems.eq(curPos-10);
						else
							nextSelection = me.listItems.first();
					}
					//home key
					else if (keycode == 36) {
						nextSelection = me.listItems.first();
					}
					//end key
					else if (keycode == 35) {
						nextSelection = me.listItems.last();
					}

				}

				if (settings.showkeys)
					displayKeys(me.typedKeys);

				if (nextSelection !== null) {
					me.listItems.removeClass('focused');
					nextSelection.addClass('focused').focus();
					e.preventDefault();
					return false;
				}

			});

			me.obj.trigger('open.' + settings.namespaces.droplist, me);

		};

		me.close = function (fast) {
			me.wrapper.removeClass('droplist-active');
			jQuery(document).unbind('.' + settings.namespaces.clickout);
			if (settings.slide && !fast)
				me.dropdown.slideUp(40);
			else
				me.dropdown.hide();

			me.obj.trigger('close.' + settings.namespaces.droplist, me);
		};

		me.set = function (el) {
			var str,val;
			me.listItems.removeClass('selected');
			if (jQuery(el).length == 0) {
				str = me.obj.title;
				val = "";
			} else {
				str = jQuery(el).find('>a').text();
				val = jQuery(el).find('>a').attr('href');
				jQuery(el).addClass('selected');
			}

			setText(str);
			if (me.originalSelect.length > 0) {
				me.originalSelect.find("option:selected").removeAttr('selected');
				me.originalSelect.find("option[value='" + val + "']").attr('selected', 'selected');
				if (me.originalSelect.find("option:selected").length == 0) {
					me.originalSelect.find("option").each(function() {
						if (this.value==val)
							jQuery(this).attr('selected', 'selected');
					});
				}
				//me.originalSelect.find("option[value$='" + val + "']").attr('selected', 'selected');
			}

			me.close(1);

			//set container width to div + dropdown bt width
			if (settings.autoresize) {
				me.option.css('width','');
				//me.select.css('width','');
				me.select.css('overflow-x','');
				/*
				if (me.option.width() > settings.maxwidth - me.dropbtn.width() - wx_opt - wx_drp) {
					//max width to settings
					me.select.css('overflow-x','hidden');
					me.option.width(settings.maxwidth - me.dropbtn.width() - wx_opt - wx_drp);
					me.select.width(settings.maxwidth - wx_opt);
					me.wrapper.width(settings.maxwidth);
				}
				me.select.width(me.wrapper.width() + me.dropbtn.width() + wx_lst + 1);
				*/

				//me.wrapper.css('display','inline-block');
				me.wrapper.width(me.option.width() + me.dropbtn.width() + wx_opt + wx_drp);
			} else {
				me.option.width(settings.width - me.dropbtn.width() - wx_opt - wx_drp);
			}

			// set list minwidth to object width
			if (me.dropdown.width() - wx_border < me.wrapper.width()) {
				// fixme: check with different styles
				me.dropdown.width(me.wrapper.width() - wx_opt + wx_lst - 1);
			}

			if (me.callTriggers) {
				if (me.onchange) {
					//set "this.value"
					me.obj.val(val); //firefox, chrome
					//me.obj.html( //IE8 doesnt want a value without selected <option>
					//	jQuery('<option selected="selected"></option>').val(val).html('')
					//);
					me.obj.trigger('onchange');
				} else {
					me.obj.trigger('change.' + settings.namespaces.droplist, me);
				}
			}
		};

		me.get = function () {
			return me.list.find('.selected:first a').attr('href');
		};

		// HELPERS
		// ==============================================================================

		me.tabs = function () {
			var that = this;
			that.list.find('li').click( function (e) {
				that.set(this);
				that.close();
				var id = jQuery(this).find('a').attr('href');
				jQuery(id).removeClass('hide').show().siblings().hide();
				e.preventDefault();
				return false;
			});
		};

		// CONTROLLER
		// ==============================================================================

		me.obj = jQuery(element);
		me.obj.css('border','none');
		me.obj.id = me.obj.attr('id');
		me.obj.classname = me.obj.attr('class') || '';
		me.obj.name = me.obj.attr('name');
		me.obj.title = me.obj.attr('title') || '';
		me.obj.width = me.obj.attr('width') ? (0 + me.obj.attr('width')) : me.obj.outerWidth();
		settings.width = settings.width || me.obj.width;
		me.onchange = me.obj[0].getAttribute('onchange');

		var isDisabled = (me.obj.attr('disabled') == true);
		if (isDisabled) {
			me.obj.classname += ' droplist-disabled';
		}

		// insert wrapper
		var wrapperHtml = '<div class="' + me.obj.classname + ' droplist"><div class="droplist-list"></div></div>';

		// get elements
		me.wrapper = me.obj.removeAttr('class').wrap(wrapperHtml).parent().parent();
		if (me.obj.id) me.wrapper.attr('id',me.obj.id+'_div');
		me.dropdown = me.wrapper.find('.droplist-list:first');
		me.list = me.dropdown.find('ul:first');

		// case it's a SELECT tag, not a UL
		if (me.list.length === 0) {
			isInsideForm = true;
			var htmOpts = '',
				select = me.dropdown.find('select:first'),
				optgroups = select.find('optgroup'),
				options;

			if (optgroups.length > 0) {
				htmOpts += '<ul>';
				optgroups.each(function () {
					options = jQuery(this).find('option');
					htmOpts += '<li><strong>' + jQuery(this).attr('label') + '</strong>' + options2list(options) + '</li>';
				});
				htmOpts += '</ul>';
			} else {
				options = me.dropdown.find('select:first option');
				htmOpts += options2list(options);
			}

			// like append() bug in IE8
			me.dropdown.get(0).innerHTML += htmOpts;

			// override list
			me.list = me.dropdown.find('ul:first');

		}

		// insert HTML into the wrapper
		me.wrapper.prepend('<div class="droplist-value"><span style="display:none;"></span><a class="nogo" href="javascript:void(0);"></a><div></div></div>');

		// GET ELEMENTS
		me.listItems = me.list.find('li a').closest('li');
		me.select = me.wrapper.find('.droplist-value:first');
		me.zone   = me.select.find('div,a');
		me.option = me.select.find('div:first');
		me.dropbtn = me.select.find('a:first');
		me.spankeys = me.select.find('span:last');
		
		me.originalSelect = me.wrapper.find('select:first');
		me.originalSelect.hide(); //in css but..

		/*
		if (isInsideForm) {
			//we need to find a way to detect external change of select value via javascript
			if (me.originalSelect[0].addEventListener)
			me.originalSelect[0].addEventListener('DOMAttrModified', function (e) {
				if (callTriggers && e.attrName == 'value') {
					//working only in Mozilla
					window.alert(e.attrName);
				}
			}, false);
		}
		*/

		// EVENTS
		// ==============================================================================

		jQuery.event.copy(me.obj,me.wrapper);

		// null function to prevent browser default events
		function preventDefault (e) {
			e.preventDefault();
			return true;
		}

		// clicking on selected value or dropdown button
		if (!me.wrapper.hasClass("droplist-disabled")) {
			me.zone.mousedown( function (e) {
				if (me.dropdown.is(':hidden')) {
					me.open();
				} else {
					me.close();
				}
				return preventDefault(e);
			});
		}
		// cancel href #nogo jump
		//me.dropbtn.click(preventDefault);

		// clicking on an option inside a form
		me.list.find('li a').closest('li').click( function (e) {
			me.set(jQuery(this));
			return preventDefault(e);
		});
		// cancel href links
		me.list.find('li a').click(preventDefault);


		// label correlation
		if (me.obj.id) {
			me.wrapper.parents('form').find('label[for="' + me.obj.id + '"]').click( function () {
				me.dropbtn.focus();
			});
		}

		// initial state
		setInitialTitle();

		// adjust layout (WIDTHS)
		layoutController();

		// custom scroll
		if (settings.customScroll) {
			customScroll();
		}

		me.close(1);

		// set selected item
		if (settings.selected !== null) {
			me.setValue(settings.selected);
		}
		else if (! me.obj.title) {
			setInitialSelected();
		}

		// callback
		if (typeof callback == 'function') { callback.apply(me); }

		//enable triggers
		me.callTriggers = true;

		me.wrapper.data('instanced', true);
		return me;

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

	// INSTANCES MANAGER
	// ==============================================================================

	jQuery.fn.droplist = function (settings, callback) {
		var res=jQuery();
		this.each(function (){
			var obj = jQuery(this),
				instance = null,
				made = obj.data('droplist');

			if (made) {
				// return early if this obj already has a plugin instance
				if (made !== true) {
					// return plugin instance $('.droplist').droplist().setValue(xxx)
					res = made;
					return false;
				}
				//continue to next object to find/create
				return true;
			}
			instance = new DropList(this, settings, callback);
			obj.data('droplist', true);

			//external data access, ex: $('.droplist').data('droplist').setValue(xxx);
			instance.wrapper.data('droplist', instance);

			jQuery.merge(res,instance.wrapper);
		});
		//substitute select/ul by new div(s)
		return res;
	};

})(jQuery);
