var jkoutlinemenu={

effectduration: 300, //duration of animation, in milliseconds
outlinemenulabels: [],
outlinemenus: [], //array to contain each block menu instances
zIndexVal: 1000, //starting z-index value for drop down menu
$shimobj: null,

addshim:function(){
	$(document.body).append('<IFRAME id="outlineiframeshim" src="'+(location.protocol=="https:"? 'blank.htm' : 'about:blank')+'" style="display:none; left:0; top:0; z-index:999; position:absolute; filter:progid:DXImageTransform.Microsoft.Alpha(style=0,opacity=0)" frameBorder="0" scrolling="no"></IFRAME>')
	this.$shimobj=$("#outlineiframeshim")
	//alert(this.$shimobj.attr("src"))
},

alignmenu:function(e, outlinemenu_pos){
	var outlinemenu=this.outlinemenus[outlinemenu_pos]
	var $anchor=outlinemenu.$anchorobj
	var $menu=outlinemenu.$menuobj
	var menuleft=($(window).width()-(outlinemenu.offsetx-$(document).scrollLeft())>outlinemenu.actualwidth)? outlinemenu.offsetx : outlinemenu.offsetx-outlinemenu.actualwidth+outlinemenu.anchorwidth //get x coord of menu
	var menutop=($(window).height()-(outlinemenu.offsety-$(document).scrollTop()+outlinemenu.anchorheight)>outlinemenu.actualheight)? outlinemenu.offsety+outlinemenu.anchorheight : outlinemenu.offsety-outlinemenu.actualheight //get y coord of menu
	$menu.css({left:menuleft+"px", top:menutop+"px"})
	this.$shimobj.css({width:outlinemenu.actualwidth+"px", height:outlinemenu.actualheight+"px", left:menuleft+"px", top:menutop+"px", display:"block"})
},

showmenu:function(e, outlinemenu_pos){
	var outlinemenu=this.outlinemenus[outlinemenu_pos]
	var $menu=outlinemenu.$menuobj
	var $menuinner=outlinemenu.$menuinner
	if ($menu.css("display")=="none"){
		this.alignmenu(e, outlinemenu_pos)
		$menu.css("z-index", ++this.zIndexVal)
		$menu.show(this.effectduration, function(){
			$menuinner.css('visibility', 'visible')
		})
	}
	else if ($menu.css("display")=="block" && e.type=="click"){ //if menu is hidden and this is a "click" event (versus "mouseout")
		this.hidemenu(e, outlinemenu_pos)
	}
	return false
},

hidemenu:function(e, outlinemenu_pos){
	var outlinemenu=this.outlinemenus[outlinemenu_pos]
	var $menu=outlinemenu.$menuobj
	var $menuinner=outlinemenu.$menuinner
	$menuinner.css('visibility', 'hidden')
	this.$shimobj.css({display:"none", left:0, top:0})
	$menu.hide(this.effectduration)
},

definemenu:function(anchorid, menuid, revealtype, optwidth, optheight){
	var $=jQuery
	this.outlinemenulabels.push([anchorid, menuid, revealtype, optwidth, optheight])
},

render:function($){
	for (var i=0, labels=this.outlinemenulabels[i]; i<this.outlinemenulabels.length; i++, labels=this.outlinemenulabels[i]){
		this.outlinemenus.push({$anchorobj:$("#"+labels[0]), $menuobj:$("#"+labels[1]), $menuinner:$("#"+labels[1]).children('ul:first-child'), revealtype:labels[2]})
		var outlinemenu=this.outlinemenus[i]	
		outlinemenu.$anchorobj.add(outlinemenu.$menuobj).attr("_outlinemenupos", i+"pos")
		outlinemenu.$menuobj.css(parseInt(labels[3])>10? {width:parseInt(labels[3])+"px"} : {})
		outlinemenu.$menuobj.css(parseInt(labels[4])<outlinemenu.$menuobj.height()? {height:parseInt(labels[4])+"px", overflow:"scroll", overflowX:"hidden"} : {})
		outlinemenu.actualwidth=outlinemenu.$menuobj.outerWidth()
		outlinemenu.actualheight=outlinemenu.$menuobj.outerHeight()
		outlinemenu.offsetx=outlinemenu.$anchorobj.offset().left
		outlinemenu.offsety=outlinemenu.$anchorobj.offset().top
		outlinemenu.anchorwidth=outlinemenu.$anchorobj.outerWidth()
		outlinemenu.anchorheight=outlinemenu.$anchorobj.outerHeight()
		outlinemenu.$menuobj.css("z-index", ++this.zIndexVal).hide()
		outlinemenu.$menuinner.css("visibility", "hidden")
		outlinemenu.$anchorobj.bind(outlinemenu.revealtype=="click"? "click" : "mouseenter", function(e){
				return jkoutlinemenu.showmenu(e, parseInt(this.getAttribute("_outlinemenupos")))
		})
		outlinemenu.$anchorobj.bind("mouseleave", function(e){
				var $menu=jkoutlinemenu.outlinemenus[parseInt(this.getAttribute("_outlinemenupos"))].$menuobj
				if (e.relatedTarget!=$menu.get(0) && $(e.relatedTarget).parents("#"+$menu.get(0).id).length==0){ //check that mouse hasn't moved into menu object
					jkoutlinemenu.hidemenu(e, parseInt(this.getAttribute("_outlinemenupos")))
				}
		})
		outlinemenu.$menuobj.bind("click mouseleave", function(e){
			jkoutlinemenu.hidemenu(e, parseInt(this.getAttribute("_outlinemenupos")))
		})
	} //end for loop
	$(document).bind("click", function(e){
		for (var i=0; i<jkoutlinemenu.outlinemenus.length; i++){
			jkoutlinemenu.hidemenu(e, i)
		}
	}) //end document.click
	$(window).bind("resize", function(){
		for (var i=0; i<jkoutlinemenu.outlinemenus.length; i++){
			var outlinemenu=jkoutlinemenu.outlinemenus[i]	
			outlinemenu.offsetx=outlinemenu.$anchorobj.offset().left
			outlinemenu.offsety=outlinemenu.$anchorobj.offset().top
		}
	})
	jkoutlinemenu.addshim()
}

}

jQuery(document).ready(function($){
	jkoutlinemenu.render($)
})

// credits window open when link is clicked
	$(function(){
		$('#credits').hide();
		$('#credit_link').click(function(){
			$('#credits').dialog({
				resizable: false,
				bgiframe: true,
				width: 500,
				height: 325
				});
			});
		$('#credit_tabs').tabs({
			event: 'mouseover'
		});
	});
