/* $Id$ */

/**
 * validateSettings
 */
function validateSettings(section) {
	var msg = "";
	switch (section) {
		case 'dir':
			break;
		case 'fluxd':
			if (isUnsignedNumber(document.theForm.fluxd_Qmgr_interval.value) == false ) {
				msg = msg + "* Qmgr Interval must be a valid number.\n";
				document.theForm.fluxd_Qmgr_interval.focus();
			}
			if (isUnsignedNumber(document.theForm.fluxd_Qmgr_maxTotalTransfers.value) == false) {
				msg = msg + "* Max Total Transfers must be a valid number.\n";
				document.theForm.fluxd_Qmgr_maxTotalTransfers.focus();
			}
			if (isUnsignedNumber(document.theForm.fluxd_Qmgr_maxUserTransfers.value) == false) {
				msg = msg + "* Max User Transfers must be a valid number.\n";
				document.theForm.fluxd_Qmgr_maxUserTransfers.focus();
			}
			if (isUnsignedNumber(document.theForm.fluxd_Fluxinet_port.value) == false ) {
				msg = msg + "* Fluxinet port must be a valid number.\n";
				document.theForm.fluxd_Fluxinet_port.focus();
			}
			if (isUnsignedNumber(document.theForm.fluxd_Watch_interval.value) == false ) {
				msg = msg + "* Watch Interval must be a valid number.\n";
				document.theForm.fluxd_Watch_interval.focus();
			}
			if (isUnsignedNumber(document.theForm.fluxd_Maintenance_interval.value) == false) {
				msg = msg + "* Maintenance Interval must be a valid number.\n";
				document.theForm.fluxd_Maintenance_interval.focus();
			}
			if (isUnsignedNumber(document.theForm.fluxd_Trigger_interval.value) == false ) {
				msg = msg + "* Trigger Interval must be a valid number.\n";
				document.theForm.fluxd_Trigger_interval.focus();
			}
			break;
		case 'fluxd_Rssad_filter_new':
			if (document.theForm.filtername.value.length < 1) {
				msg = msg + "* Enter a Filtername.\n";
				document.theForm.filtername.focus();
			}
			break;
		case 'fluxd_Rssad_filter_add':
			if (document.theForm.filtername.value.length < 1) {
				msg = msg + "* Enter a Filtername.\n";
				document.theForm.filtername.focus();
			}
		case 'fluxd_Rssad_filter_edit':
			if (document.theForm.rssad_filters.options.length < 1) {
				msg = msg + "* Enter at least one Filter.\n";
				document.theForm.rssad_filter_entry.focus();
			}
			break;
		case 'fluxd_Rssad_job':
			if (document.theForm.savedir.value.length < 1) {
				msg = msg + "* Enter a savedir.\n";
				document.theForm.savedir.focus();
			} else {
				if (document.theForm.savedir.value.indexOf('/') != 0) {
					msg = msg + "* savedir must be an absolute path.\n";
				}
			}
			if (document.theForm.url.value.length < 1) {
				msg = msg + "* Enter a URL.\n";
				document.theForm.url.focus();
			}
			break;
		case 'fluxd_Watch_job':
			if (document.theForm.watchdir.value.length < 1) {
				msg = msg + "* Enter a watchdir.\n";
				document.theForm.watchdir.focus();
			} else {
				if (document.theForm.watchdir.value.indexOf('/') != 0) {
					msg = msg + "* watchdir must be an absolute path.\n";
				}
			}
			break;
		case 'index':
			if (isUnsignedNumber(document.theForm.page_refresh.value) == false ) {
				msg = msg + "* Page Refresh Interval must be a valid number.\n";
				document.theForm.page_refresh.focus();
			}
			if (isUnsignedNumber(document.theForm.index_ajax_update.value) == false ) {
				msg = msg + "* AJAX Update Interval must be a valid number.\n";
				document.theForm.index_ajax_update.focus();
			}
			if (isUnsignedNumber(document.theForm.hack_multiupload_rows.value) == false ) {
				msg = msg + "* multi-upload rows must be a valid number.\n";
				document.theForm.hack_multiupload_rows.focus();
			}
			if (isUnsignedNumber(document.theForm.bandwidth_up.value) == false ) {
				msg = msg + "* Bandwidth Up must be a valid number.\n";
				document.theForm.bandwidth_up.focus();
			}
			if (isUnsignedNumber(document.theForm.bandwidth_down.value) == false ) {
				msg = msg + "* Bandwidth Down must be a valid number.\n";
				document.theForm.bandwidth_down.focus();
			}
			break;
		case 'server':
			bwd = document.theForm.path_incoming.value.Trim();
			//if (bwd.indexOf('/') == 0) {
			//	msg = msg + "* Incoming-PATH cannot be a absolute path. Incoming is a sub-dir of PATH.\n";
			if (bwd.indexOf('/') == 0 || bwd.indexOf('../') != -1) {
				msg = msg + "* Incoming-dir can only be a subdir of PATH. (specify relative Path).\n";
				document.theForm.path_incoming.focus();
			}
			break;
		case 'control':
			if (isUnsignedNumber(document.theForm.maxdepth.value) == false) {
				msg = msg + "* Max Depth must be a valid number.\n" ;
			}
			break;
		case 'stats':
			break;
		case 'transfer':
			if (isNumber(document.theForm.max_upload_rate.value) == false) {
				msg = msg + "* Max Upload Rate must be a valid number.\n";
				document.theForm.max_upload_rate.focus();
			}
			if (isNumber(document.theForm.max_download_rate.value) == false) {
				msg = msg + "* Max Download Rate must be a valid number.\n";
				document.theForm.max_download_rate.focus();
			}
			if (isNumber(document.theForm.max_uploads.value) == false) {
				msg = msg + "* Max # Uploads must be a valid number.\n";
				document.theForm.max_uploads.focus();
			}
			if (isNumber(document.theForm.maxcons.value) == false) {
				msg = msg + "* Max Cons must be a valid number.\n" ;
			}
			if ((isNumber(document.theForm.minport.value) == false) || (isNumber(document.theForm.maxport.value) == false)) {
				msg = msg + "* Port Range must have valid numbers.\n";
				document.theForm.minport.focus();
			}
			if ((document.theForm.maxport.value > 65535) || (document.theForm.minport.value > 65535)) {
				msg = msg + "* Port can not be higher than 65535.\n";
				document.theForm.minport.focus();
			}
			if ((document.theForm.maxport.value < 0) || (document.theForm.minport.value < 0)) {
				msg = msg + "* Can not have a negative number for port value.\n";
				document.theForm.minport.focus();
			}
			if (document.theForm.maxport.value < document.theForm.minport.value) {
				msg = msg + "* Port Range is not valid.\n";
				document.theForm.minport.focus();
			}
			if (isNumber(document.theForm.rerequest_interval.value) == false) {
				msg = msg + "* Rerequest Interval must have a valid number.\n";
				document.theForm.rerequest_interval.focus();
			}
			if (document.theForm.rerequest_interval.value < 10) {
				msg = msg + "* Rerequest Interval must be 10 or greater.\n";
				document.theForm.rerequest_interval.focus();
			}
			if (isNumber(document.theForm.sharekill.value) == false) {
				msg = msg + "* Keep seeding until Sharing % must be a valid number.\n";
				document.theForm.sharekill.focus();
			}
			if (isNumber(document.theForm.wget_limit_rate.value) == false) {
				msg = msg + "* wget Download Rate must be a valid number.\n";
				document.theForm.wget_limit_rate.focus();
			}
			if (isNumber(document.theForm.wget_limit_retries.value) == false) {
				msg = msg + "* wget Limit Number of Retries must be a valid number.\n";
				document.theForm.wget_limit_retries.focus();
			}
			if (isNumber(document.theForm.nzbperl_rate.value) == false) {
				msg = msg + "* nzbperl Download Rate must be a valid number.\n";
				document.theForm.nzbperl_rate.focus();
			}
			if (isNumber(document.theForm.nzbperl_conn.value) == false) {
				msg = msg + "* nzbperl Connections must be a valid number.\n";
				document.theForm.nzbperl_conn.focus();
			}
			if (isNumber(document.theForm.nzbperl_threads.value) == false) {
				msg = msg + "* nzbperl Threads must be a valid number.\n";
				document.theForm.nzbperl_threads.focus();
			}
			break;
		case 'fluazu':
			if (document.theForm.fluazu_host.value.length < 1) {
				msg = msg + "* Host cannot be empty.\n";
				document.theForm.fluazu_host.focus();
			}
			if (isUnsignedNumber(document.theForm.fluazu_port.value) == false) {
				msg = msg + "* Port must be a valid number.\n";
				document.theForm.fluazu_port.focus();
			}
			break;
		case 'azureus':
			if (isUnsignedNumber(document.azuForm.CORE_PARAM_INT_MAX_DOWNLOAD_SPEED_KBYTES_PER_SEC.value) == false) {
				msg = msg + "* Max Download Speed KBs must be a valid number.\n";
				document.azuForm.CORE_PARAM_INT_MAX_DOWNLOAD_SPEED_KBYTES_PER_SEC.focus();
			}
			if (isUnsignedNumber(document.azuForm.CORE_PARAM_INT_MAX_UPLOAD_SPEED_KBYTES_PER_SEC.value) == false) {
				msg = msg + "* Max Upload Speed KBs must be a valid number.\n";
				document.azuForm.CORE_PARAM_INT_MAX_UPLOAD_SPEED_KBYTES_PER_SEC.focus();
			}
			break;
		case 'webapp':
			if (isUnsignedNumber(document.theForm.transferStatsUpdate.value) == false) {
				msg = msg + "* Download-Details Update Interval must be a valid number.\n";
				document.theForm.transferStatsUpdate.focus();
			}
			if (isUnsignedNumber(document.theForm.servermon_update.value) == false) {
				msg = msg + "* Server Monitor Update Interval must be a valid number.\n";
				document.theForm.servermon_update.focus();
			}
			if (isUnsignedNumber(document.theForm.days_to_keep.value) == false) {
				msg = msg + "* Days to keep Audit Actions must be a valid number.\n";
				document.theForm.days_to_keep.focus();
			}
			if (isUnsignedNumber(document.theForm.minutes_to_keep.value) == false) {
				msg = msg + "* Minutes to keep user online must be a valid number.\n";
				document.theForm.minutes_to_keep.focus();
			}
			if (isUnsignedNumber(document.theForm.rss_cache_min.value) == false) {
				msg = msg + "* Minutes to Cache RSS Feeds must be a valid number.\n";
				document.theForm.rss_cache_min.focus();
			}
			break;
		case 'xfer':
			if (isUnsignedNumber(document.theForm.xfer_total.value) == false) {
				msg = msg + "* xfer total must be a valid number.\n";
				document.theForm.xfer_total.focus();
			}
			if (isUnsignedNumber(document.theForm.xfer_month.value) == false) {
				msg = msg + "* xfer month must be a valid number.\n";
				document.theForm.xfer_month.focus();
			}
			if (isUnsignedNumber(document.theForm.xfer_week.value) == false) {
				msg = msg + "* xfer week must be a valid number.\n";
				document.theForm.xfer_week.focus();
			}
			if (isUnsignedNumber(document.theForm.xfer_day.value) == false) {
				msg = msg + "* xfer day must be a valid number.\n";
				document.theForm.xfer_day.focus();
			}
			break;
	}
	if (msg != "") {
		alert("Please check the following:\n\n" + msg);
		return false;
	} else {
		return true;
	}
}

/**
 * performRefresh
 *
 * Refresh theForm without executing its onsubmit code (used when a select
 * has changed and the form must be reloaded to show new values).
 */
function performRefresh() {
  var f = document.theForm;
  f.refresh.value = '1';
  f.submit();
}
