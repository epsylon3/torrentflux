/**
 * 
 */


$(function(){
	if($('#adminfluxdSettingsRSSadFilterEdit').length){
		
		$('.base_name input').bind('blur',function(){
			construct_generated(this);
		});
		
		$('a.save_filter').bind('click',function(){
			end_value = $(this).parents(0).siblings('.filterResults').find('div').text();
			$('#rssad_filter_entry').val(end_value);
			setTimeout(function(){
				addRssadFilterEntry();	
			},10);
			
			//$('#rssad_filters').append('<option value="'+end_value+'">'+end_value+'</option>');
		});
		
		$('input.add_action').bind('click',function(){
			if($(this).siblings('input.add_filter').val() != ''){
				var the_value = $(this).siblings('input.add_filter').val();
				var found = false;
				$(this).siblings('select').find('option').each(function(){
					op_value = $(this).text();
					if(op_value.toLowerCase() == the_value.toLowerCase()) found = true;
				});
				if(!found){
					$(this).siblings('select').append('<option>'+$(this).siblings('input.add_filter').val()+'</option>')
					$(this).siblings('input.add_filter').val('');
					construct_generated(this);
					$(this).siblings('input.add_filter').focus();
				} else {
					$(this).siblings('input.add_filter').val('')
					$(this).siblings('input.add_filter').focus();
				}
			}
		});
		
		$('a.remove_filter').bind('click',function(e){
			$(e.target).siblings('select').find(':selected').remove();
			construct_generated(e.target);
		});
	}
});

function construct_generated(target){
	container = $(target).parents(0);
	search = clean_name($(container).find('.base_name input').val());
	positive = new Array();
	negative = new Array();
	$(container).find('#positive_filters option').each(function(){
		positive.push($(this).text());
	});
	$(container).find('#negative_filters option').each(function(){
		negative.push($(this).text());
	});
	
	out = $('<div>');
	
	for(i=0;i<negative.length;i=i+1){
		$(out).append('<span class="negative">(?!.*'+negative[i].toLowerCase()+')</span>');
	}
	
	for(i=0;i<positive.length;i=i+1){
		$(out).append('<span class="positive">(?=.*'+positive[i].toLowerCase()+')</span>');
	}
	
	$(out).append('<span class="helper">^</span>');
	$(out).append('<span class="search_value">'+search+'</span>');
	
	if($(container).hasClass('filter-builder-tv')){
		$(out).append('<span class="helper">.*(\\d{1,2}x\\d{1,2}|s\\d{1,2}e\\d{1,2}).*</span>');
	} else if($(container).hasClass('filter-builder-movie')){
		
	}
	
	
	
	$(container).find('.filterResults').find('div').remove();
	$(container).find('.filterResults').prepend(out);
	
	$(container).find('.filterResults textarea').text($(out).text());
	
}
function clean_name(raw){
	var name = raw;
	clean_1 = new RegExp("\ {1,}","g");
	name = name.replace(clean_1,'.');
	name = name.toLowerCase();
	return name;	
}