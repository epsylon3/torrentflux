#!/usr/bin/php
<?php
/*
VUZE xmwebui (0.2.8) RPC interface for PHP
		by Epsylon3 on gmail.com, Nov 2010

Require PHP 5 for public/protected members
*/

require("../../../inc/classes/VuzeRPC.php");

//--------------------------------------------------------
//Test config

//commented to keep default
//$rpc_cfg['vuze_rpc_host']='127.0.0.1';
$rpc_cfg['vuze_rpc_port']='19091';
//$rpc_cfg['vuze_rpc_user']='vuze';
//$rpc_cfg['vuze_rpc_pass']='mypassword';

$v = new VuzeRPC($rpc_cfg);

$v->torrent_get_tf();

$filter = array(
	'running' => 1
);
$torrents = $v->torrent_filter_tf($filter);

echo print_r($torrents,true);

?>