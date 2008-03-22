/* $Id$ */

addEvent(window, "load", sortables_init);

var SORT_COLUMN_INDEX;

function sortables_init() {
    // Find all tables with class sortable and make them sortable
    if (!document.getElementsByTagName) return;
    tbls = document.getElementsByTagName("table");
    for (ti=0;ti<tbls.length;ti++) {
        thisTbl = tbls[ti];
        if (((' '+thisTbl.className+' ').indexOf("sortable") != -1) && (thisTbl.id)) {
            //initTable(thisTbl.id);
            ts_makeSortable(thisTbl);
        }
    }
}

function ts_makeSortable(table) {
    if (table.rows && table.rows.length > 0) {
        var firstRow = table.rows[0];
    }
    if (!firstRow) return;
    // We have a first row: assume it's the header, and make its contents clickable links
    // b4rt :
    // not first column.. client cant sort on server-file-date
    // not last column.. admin-buttons
    //for (var i=0;i<firstRow.cells.length;i++) {
    for (var i=1;i<firstRow.cells.length-1;i++) {
        var cell = firstRow.cells[i];
        var txt = ts_getInnerText(cell);
        cell.innerHTML = '<a href="#" class="sortheader" '+
        'onclick="ts_resortTable(this, '+i+');return false;">' +
        txt+'<span class="sortarrow">&nbsp;&nbsp;&nbsp;</span></a>';
    }
}

function ts_getInnerText(el) {
  if (typeof el == "string") return el;
  if (typeof el == "undefined") { return el };
  if (el.innerText) return el.innerText;  //Not needed but it is faster
  var str = "";

  var cs = el.childNodes;
  var l = cs.length;
  for (var i = 0; i < l; i++) {
    switch (cs[i].nodeType) {
      case 1: //ELEMENT_NODE
        str += ts_getInnerText(cs[i]);
        break;
      case 3: //TEXT_NODE
        str += cs[i].nodeValue;
        break;
    }
  }
  return str;
}

function ts_resortTable(lnk,clid) {
    // get the span
    var span;
    for (var ci=0;ci<lnk.childNodes.length;ci++) {
        if (lnk.childNodes[ci].tagName && lnk.childNodes[ci].tagName.toLowerCase() == 'span') span = lnk.childNodes[ci];
    }
    var spantext = ts_getInnerText(span);
    var td = lnk.parentNode;
    var column = clid || td.cellIndex;
    var table = getParent(td,'TABLE');

    // Work out a type for the column
    if (table.rows.length <= 1) return;
    for (var ri=1;ri<table.rows.length;ri++) {
      // Find first row with content (some transfers only contain whitespace in some columns).
      var itm = ts_getInnerText(table.rows[ri].cells[column]);
      if (itm.match(/^\s*$/)) continue;
      sortfn = ts_sort_caseinsensitive;
      if (itm.match(/^\d\d[\/-]\d\d[\/-]\d\d\d\d$/)) sortfn = ts_sort_date;
      if (itm.match(/^\d\d[\/-]\d\d[\/-]\d\d$/)) sortfn = ts_sort_date;
      if (itm.match(/^[£$]/)) sortfn = ts_sort_currency;
      if (itm.match(/^[\d\.]+$/)) sortfn = ts_sort_numeric;
      // b4rt : next 3 regexps are copy-pasted from azureus webui
      if (itm.match(/^[\d\.]+( | k| M| G| T)B$/)) sortfn = ts_sort_size;
      if (itm.match(/^[\d\.]+( | k| M| G| T)B\/s$/)) sortfn = ts_sort_speed;
      if (itm.match(/[\d\.]+%/) || itm.match(/N\/A/)) sortfn = ts_sort_percent;
      break;
    }

    SORT_COLUMN_INDEX = column;
    var firstRow = new Array();
    var newRows = new Array();
    for (i=0;i<table.rows[0].length;i++) { firstRow[i] = table.rows[0][i]; }
    for (j=1;j<table.rows.length;j++) { newRows[j-1] = table.rows[j]; }

    newRows.sort(sortfn);

    if (span.getAttribute("sortdir") == 'down') {
        ARROW = '&nbsp;&nbsp;&uarr;';
        newRows.reverse();
        span.setAttribute('sortdir','up');
    } else {
        ARROW = '&nbsp;&nbsp;&darr;';
        span.setAttribute('sortdir','down');
    }

    // We appendChild rows that already exist to the tbody, so it moves them rather than creating new ones
    // don't do sortbottom rows
    for (i=0;i<newRows.length;i++) { if (!newRows[i].className || (newRows[i].className && (newRows[i].className.indexOf('sortbottom') == -1))) table.tBodies[0].appendChild(newRows[i]);}
    // do sortbottom rows only
    for (i=0;i<newRows.length;i++) { if (newRows[i].className && (newRows[i].className.indexOf('sortbottom') != -1)) table.tBodies[0].appendChild(newRows[i]);}

    // Delete any other arrows there may be showing
    var allspans = document.getElementsByTagName("span");
    for (var ci=0;ci<allspans.length;ci++) {
        if (allspans[ci].className == 'sortarrow') {
            if (getParent(allspans[ci],"table") == getParent(lnk,"table")) { // in the same table as us?
                allspans[ci].innerHTML = '&nbsp;&nbsp;&nbsp;';
            }
        }
    }

    span.innerHTML = ARROW;
}

function getParent(el, pTagName) {
  if (el == null) return null;
  else if (el.nodeType == 1 && el.tagName.toLowerCase() == pTagName.toLowerCase())  // Gecko bug, supposed to be uppercase
    return el;
  else
    return getParent(el.parentNode, pTagName);
}
function ts_sort_date(a,b) {
    // y2k notes: two digit years less than 50 are treated as 20XX, greater than 50 are treated as 19XX
    aa = ts_getInnerText(a.cells[SORT_COLUMN_INDEX]);
    bb = ts_getInnerText(b.cells[SORT_COLUMN_INDEX]);
    if (aa.length == 10) {
        dt1 = aa.substr(6,4)+aa.substr(3,2)+aa.substr(0,2);
    } else {
        yr = aa.substr(6,2);
        if (parseInt(yr) < 50) { yr = '20'+yr; } else { yr = '19'+yr; }
        dt1 = yr+aa.substr(3,2)+aa.substr(0,2);
    }
    if (bb.length == 10) {
        dt2 = bb.substr(6,4)+bb.substr(3,2)+bb.substr(0,2);
    } else {
        yr = bb.substr(6,2);
        if (parseInt(yr) < 50) { yr = '20'+yr; } else { yr = '19'+yr; }
        dt2 = yr+bb.substr(3,2)+bb.substr(0,2);
    }
    if (dt1==dt2) return 0;
    if (dt1<dt2) return -1;
    return 1;
}

function ts_sort_currency(a,b) {
    aa = ts_getInnerText(a.cells[SORT_COLUMN_INDEX]).replace(/[^0-9.]/g,'');
    bb = ts_getInnerText(b.cells[SORT_COLUMN_INDEX]).replace(/[^0-9.]/g,'');
    return parseFloat(aa) - parseFloat(bb);
}

function ts_sort_numeric(a,b) {
    aa = parseFloat(ts_getInnerText(a.cells[SORT_COLUMN_INDEX]));
    if (isNaN(aa)) aa = 0;
    bb = parseFloat(ts_getInnerText(b.cells[SORT_COLUMN_INDEX]));
    if (isNaN(bb)) bb = 0;
    return aa-bb;
}

function ts_sort_caseinsensitive(a,b) {
    aa = ts_getInnerText(a.cells[SORT_COLUMN_INDEX]).toLowerCase();
    bb = ts_getInnerText(b.cells[SORT_COLUMN_INDEX]).toLowerCase();
    if (aa==bb) return 0;
    if (aa<bb) return -1;
    return 1;
}

function ts_sort_default(a,b) {
    aa = ts_getInnerText(a.cells[SORT_COLUMN_INDEX]);
    bb = ts_getInnerText(b.cells[SORT_COLUMN_INDEX]);
    if (aa==bb) return 0;
    if (aa<bb) return -1;
    return 1;
}

// b4rt : function copy-pasted from azureus webui and slightly modified
function ts_sort_size(a,b) {
    a_unit = ts_getInnerText(a.cells[SORT_COLUMN_INDEX]);
	b_unit = ts_getInnerText(b.cells[SORT_COLUMN_INDEX]);
    if (a_unit.match(/B$/)) aa = parseFloat(ts_getInnerText(a.cells[SORT_COLUMN_INDEX]).replace(/[^0-9.]/g,''));
    if (a_unit.match(/kB$/)) aa = parseFloat(ts_getInnerText(a.cells[SORT_COLUMN_INDEX]).replace(/[^0-9.]/g,'')) * 1024;
    if (a_unit.match(/MB$/)) aa = parseFloat(ts_getInnerText(a.cells[SORT_COLUMN_INDEX]).replace(/[^0-9.]/g,'')) * 1048576;
    if (a_unit.match(/GB$/)) aa = parseFloat(ts_getInnerText(a.cells[SORT_COLUMN_INDEX]).replace(/[^0-9.]/g,'')) * 1073741824;
    if (a_unit.match(/TB$/)) aa = parseFloat(ts_getInnerText(a.cells[SORT_COLUMN_INDEX]).replace(/[^0-9.]/g,'')) * 1099511627776;
    if (b_unit.match(/B$/)) bb = parseFloat(ts_getInnerText(b.cells[SORT_COLUMN_INDEX]).replace(/[^0-9.]/g,''));
    if (b_unit.match(/kB$/)) bb = parseFloat(ts_getInnerText(b.cells[SORT_COLUMN_INDEX]).replace(/[^0-9.]/g,'')) * 1024;
    if (b_unit.match(/MB$/)) bb = parseFloat(ts_getInnerText(b.cells[SORT_COLUMN_INDEX]).replace(/[^0-9.]/g,'')) * 1048576;
    if (b_unit.match(/GB$/)) bb = parseFloat(ts_getInnerText(b.cells[SORT_COLUMN_INDEX]).replace(/[^0-9.]/g,'')) * 1073741824;
    if (b_unit.match(/TB$/)) bb = parseFloat(ts_getInnerText(b.cells[SORT_COLUMN_INDEX]).replace(/[^0-9.]/g,'')) * 1099511627776;
    return aa-bb;
}

// b4rt : function copy-pasted from azureus webui and slightly modified
function ts_sort_speed(a,b) {
    a_unit = ts_getInnerText(a.cells[SORT_COLUMN_INDEX]);
	b_unit = ts_getInnerText(b.cells[SORT_COLUMN_INDEX]);
    if (a_unit.match(/B\/s$/)) aa = parseFloat(ts_getInnerText(a.cells[SORT_COLUMN_INDEX]).replace(/[^0-9.]/g,''));
    if (a_unit.match(/kB\/s$/)) aa = parseFloat(ts_getInnerText(a.cells[SORT_COLUMN_INDEX]).replace(/[^0-9.]/g,'')) * 1024;
    if (a_unit.match(/MB\/s$/)) aa = parseFloat(ts_getInnerText(a.cells[SORT_COLUMN_INDEX]).replace(/[^0-9.]/g,'')) * 1048576;
    if (a_unit.match(/GB\/s$/)) aa = parseFloat(ts_getInnerText(a.cells[SORT_COLUMN_INDEX]).replace(/[^0-9.]/g,'')) * 1073741824;
    if (a_unit.match(/TB\/s$/)) aa = parseFloat(ts_getInnerText(a.cells[SORT_COLUMN_INDEX]).replace(/[^0-9.]/g,'')) * 1099511627776;
    if (b_unit.match(/B\/s$/)) bb = parseFloat(ts_getInnerText(b.cells[SORT_COLUMN_INDEX]).replace(/[^0-9.]/g,''));
    if (b_unit.match(/kB\/s$/)) bb = parseFloat(ts_getInnerText(b.cells[SORT_COLUMN_INDEX]).replace(/[^0-9.]/g,'')) * 1024;
    if (b_unit.match(/MB\/s$/)) bb = parseFloat(ts_getInnerText(b.cells[SORT_COLUMN_INDEX]).replace(/[^0-9.]/g,'')) * 1048576;
    if (b_unit.match(/GB\/s$/)) bb = parseFloat(ts_getInnerText(b.cells[SORT_COLUMN_INDEX]).replace(/[^0-9.]/g,'')) * 1073741824;
    if (b_unit.match(/TB\/s$/)) bb = parseFloat(ts_getInnerText(b.cells[SORT_COLUMN_INDEX]).replace(/[^0-9.]/g,'')) * 1099511627776;
    return aa-bb;
}

// b4rt : function copy-pasted from azureus webui and slightly modified
function ts_sort_percent(a,b) {
    a_content = ts_getInnerText(a.cells[SORT_COLUMN_INDEX]);
	b_content = ts_getInnerText(b.cells[SORT_COLUMN_INDEX]);
	if (a_content.match(/N\/A/) && b_content.match(/N\/A/)) return 0;
	if (a_content.match(/N\/A/) && !b_content.match(/N\/A/)) return -1;
	if (!a_content.match(/N\/A/) && b_content.match(/N\/A/)) return 1;
    ap = a_content.indexOf('%');
    bp = b_content.indexOf('%');
    if (ap == -1 && bp == -1) return 0;
    if (ap == -1 && bp != -1) return -1;
    if (ap != -1 && bp == -1) return 1;
    a1 = a_content.substring(0,ap);
    b1 = b_content.substring(0,bp);
    aa = parseFloat(a1.replace(/[^0-9.]/g,''));
    bb = parseFloat(b1.replace(/[^0-9.]/g,''));
	return aa-bb;
}

function addEvent(elm, evType, fn, useCapture)
// addEvent and removeEvent
// cross-browser event handling for IE5+,  NS6 and Mozilla
// By Scott Andrew
{
  if (elm.addEventListener){
    elm.addEventListener(evType, fn, useCapture);
    return true;
  } else if (elm.attachEvent){
    var r = elm.attachEvent("on"+evType, fn);
    return r;
  } else {
    alert("Handler could not be removed");
  }
}