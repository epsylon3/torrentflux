<?php

/* $Id$ */

/*************************************************************
*  TorrentFlux - PHP Torrent Manager
*  www.torrentflux.com
**************************************************************/
/*
    This file is part of TorrentFlux.

    TorrentFlux is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation; either version 2 of the License, or
    (at your option) any later version.

    TorrentFlux is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with TorrentFlux; if not, write to the Free Software
    Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
*/

// -------------------------------------------------------------------
// FetchHTMLNoWaitNoFollow() method to get data from URL
// uses timeout and user agent
// -------------------------------------------------------------------
function FetchHTMLNoWaitNoFollow( $url, $referer = "" )
{
    global $cfg, $db;
    ini_set("allow_url_fopen", "1");
    ini_set("user_agent", $cfg['user_agent']);

    $url = tfb_cleanURL( $url );
    $domain = parse_url( $url );
    $getcmd  = $domain["path"];

    if(!array_key_exists("query", $domain))
    {
        $domain["query"] = "";
    }

    $getcmd .= ( !empty( $domain["query"] ) ) ? "?" . $domain["query"] : "";

    $cookie = "";
    $rtnValue = "";

    // If the url already doesn't contain a passkey, then check
    // to see if it has cookies set to the domain name.
    if( ( strpos( $domain["query"], "passkey=" ) ) === false )
    {
        $sql = "SELECT c.data AS data FROM tf_cookies AS c LEFT JOIN tf_users AS u ON ( u.uid = c.uid ) WHERE u.user_id = ".$db->qstr($cfg["user"])." AND c.host = ".$db->qstr($domain['host']);
        $cookie = $db->GetOne( $sql );
    }


    if( !array_key_exists("port", $domain) )
    {
        $domain["port"] = 80;
    }

    if (($rtnValue == "" && function_exists("curl_init")) || /*(strpos($rtnValue, "HTTP/1.1 302") > 0 &&*/ function_exists("curl_init"))//)
    {
        // Give CURL a Try
        $curl = curl_init();
        if ($cookie != "")
            curl_setopt($curl, CURLOPT_COOKIE, $cookie);
        curl_setopt($curl, CURLOPT_PORT, $domain["port"]);
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_VERBOSE, FALSE);
        curl_setopt($curl, CURLOPT_HEADER, TRUE);
        curl_setopt($curl, CURLOPT_USERAGENT, $cfg['user_agent']);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, TRUE);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, FALSE);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($curl, CURLOPT_FOLLOWLOCATION, FALSE);
        curl_setopt($curl, CURLOPT_TIMEOUT, 5);
        $response = curl_exec($curl);
        curl_close($curl);
        $rtnValue = substr($response, strpos($response, "d8:"));
        $rtnValue = rtrim($rtnValue, "\r\n");
    }
    return $rtnValue;
}

?>