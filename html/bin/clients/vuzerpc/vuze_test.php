#!/usr/bin/php
<?php
/*
VUZE xmwebui (0.2.8) RPC interface for PHP
        by Epsylon3 on gmail.com, Nov 2010
*/

require("../../../inc/classes/VuzeRPC.php");

//--------------------------------------------------------
//Test config

//commented to keep default
//$rpc_cfg['vuze_rpc_host']='mytesthost.com';
$rpc_cfg['vuze_rpc_port']='19091';
//$rpc_cfg['vuze_rpc_user']='vuze';
//$rpc_cfg['vuze_rpc_pass']='blabla';

$v = new VuzeRPC($rpc_cfg);

$v->torrent_get_tf_json();

?>
