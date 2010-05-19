/******************************************************************************
 * $Id$
 *
 * Copyright (c) 2005-2006 Transmission authors and contributors
 *
 * Permission is hereby granted, free of charge, to any person obtaining a
 * copy of this software and associated documentation files (the "Software"),
 * to deal in the Software without restriction, including without limitation
 * the rights to use, copy, modify, merge, publish, distribute, sublicense,
 * and/or sell copies of the Software, and to permit persons to whom the
 * Software is furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING
 * FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER
 * DEALINGS IN THE SOFTWARE.
 *****************************************************************************/

#include <stdio.h>
#include <stdlib.h>
#include <string.h>
#include <unistd.h>
#include <signal.h>

#include <libtransmission/transmission.h>
#include <libtransmission/bencode.h>
#include <libtransmission/makemeta.h>
#include <libtransmission/tr-getopt.h>
#include <libtransmission/utils.h> /* tr_wait */
#include <libtransmission/web.h> /* tr_webRun */

#define LINEWIDTH 80
#define MY_NAME "transmission"

//Torrentflux
#define TOF_DISPLAY_INTERVAL                5
#define TOF_DISPLAY_INTERVAL_STR            "5"
#define TOF_DIEWHENDONE                     0
#define TOF_DIEWHENDONE_STR                 "0"
#define TOF_CMDFILE_MAXLEN 65536
//END

static tr_bool showInfo         = 0;
static tr_bool showScrape       = 0;
static tr_bool isPrivate        = 0;
static tr_bool verify           = 0;
static sig_atomic_t gotsig           = 0;
static sig_atomic_t manualUpdate     = 0;

static const char * torrentPath  = NULL;
static const char * finishCall   = NULL;
static const char * announce     = NULL;
static const char * sourceFile   = NULL;
static const char * comment      = NULL;

/* Torrentflux -START- */
//static volatile char tf_shutdown = 0;
static int           TOF_dieWhenDone     = TOF_DIEWHENDONE;
static int           TOF_seedLimit       = 0;
static int           TOF_displayInterval = TOF_DISPLAY_INTERVAL;
static int           TOF_checkCmd        = 0;

static const char          * TOF_owner    = NULL;
static char          * TOF_statFile = NULL;
static FILE          * TOF_statFp   = NULL;
static char          * TOF_cmdFile  = NULL;
static FILE          * TOF_cmdFp    = NULL;
static char            TOF_message[512];
/* -END- */


static const struct tr_option options[] =
{
    { 'a', "announce",             "Set the new torrent's announce URL", "a",  1, "<url>"     },
    { 'b', "blocklist",            "Enable peer blocklists", "b",  0, NULL        },
    { 'B', "no-blocklist",         "Disable peer blocklists", "B",  0, NULL        },
    { 'c', "comment",              "Set the new torrent's comment", "c",  1, "<comment>" },
    { 'd', "downlimit",            "Set max download speed in KB/s", "d",  1, "<speed>"   },
    { 'D', "no-downlimit",         "Don't limit the download speed", "D",  0, NULL        },
    { 910, "encryption-required",  "Encrypt all peer connections", "er", 0, NULL        },
    { 911, "encryption-preferred", "Prefer encrypted peer connections", "ep", 0, NULL        },
    { 912, "encryption-tolerated", "Prefer unencrypted peer connections", "et", 0, NULL        },
    { 'f', "finish",               "Run a script when the torrent finishes", "f", 1, "<script>" },
    { 'g', "config-dir",           "Where to find configuration files", "g", 1, "<path>" },
    { 'i', "info",                 "Show torrent details and exit", "i",  0, NULL        },
    { 'm', "portmap",              "Enable portmapping via NAT-PMP or UPnP", "m",  0, NULL        },
    { 'M', "no-portmap",           "Disable portmapping", "M",  0, NULL        },
    { 'n', "new",                  "Create a new torrent", "n", 1, "<source>" },
    { 'p', "port", "Port for incoming peers (Default: " TR_DEFAULT_PEER_PORT_STR ")", "p", 1, "<port>" },
    { 'r', "private",              "Set the new torrent's 'private' flag", "r",  0, NULL        },
    { 's', "scrape",               "Scrape the torrent and exit", "s",  0, NULL        },
    { 't', "tos", "Peer socket TOS (0 to 255, default=" TR_DEFAULT_PEER_SOCKET_TOS_STR ")", "t", 1, "<tos>" },
    { 'u', "uplimit",              "Set max upload speed in KB/s", "u",  1, "<speed>"   },
    { 'U', "no-uplimit",           "Don't limit the upload speed", "U",  0, NULL        },
    { 'v', "verify",               "Verify the specified torrent", "v",  0, NULL        },
    { 'w', "download-dir",         "Where to save downloaded data", "w",  1, "<path>"    },
//Torrentflux Commands:
    { 'E', "display-interval","Time between updates of stat-file (default = "TOF_DISPLAY_INTERVAL_STR")","E",1,"<int>"},
    { 'L', "seedlimit","Seed-Limit (Percent) to reach before shutdown","L",1,"<int>"},
    { 'O', "owner","Name of the owner (default = 'n/a')","O",1,"<string>"},
    { 'W', "die-when-done", "Auto-Shutdown when done (0 = Off, 1 = On, default = "TOF_DIEWHENDONE_STR")","W",1,NULL},
//END
    { 0, NULL, NULL, NULL, 0, NULL }
};

static const char *
getUsage( void )
{
    return "A fast and easy BitTorrent client\n"
           "\n"
           "Usage: " MY_NAME " [options] <torrent-filename>";
}

static int parseCommandLine( tr_benc*, int argc, const char ** argv );

static void         sigHandler( int signal );

/* Torrentflux -START- */
static int TOF_processCommands(tr_session *h);
static int TOF_execCommand(tr_session *h, char *s);
static void TOF_print ( char *printmsg );
static void TOF_free ( void );
static int TOF_initStatus ( void );
static void TOF_writeStatus ( const tr_stat *s, const tr_info *info, 
const int state, const char *status );
static int TOF_initCommand ( void );
static int TOF_writePID ( void );
static void TOF_deletePID ( void );
static int  TOF_writeAllowed ( void );
/* -END- */


static char*
tr_strlratio( char * buf,
              double ratio,
              size_t buflen )
{
    if( (int)ratio == TR_RATIO_NA )
        tr_strlcpy( buf, _( "None" ), buflen );
    else if( (int)ratio == TR_RATIO_INF )
        tr_strlcpy( buf, "Inf", buflen );
    else if( ratio < 10.0 )
        tr_snprintf( buf, buflen, "%.2f", ratio );
    else if( ratio < 100.0 )
        tr_snprintf( buf, buflen, "%.1f", ratio );
    else
        tr_snprintf( buf, buflen, "%.0f", ratio );
    return buf;
}

static int
is_rfc2396_alnum( char ch )
{
    return ( '0' <= ch && ch <= '9' )
           || ( 'A' <= ch && ch <= 'Z' )
           || ( 'a' <= ch && ch <= 'z' );
}

static void
escape( char *          out,
        const uint8_t * in,
        int             in_len )                     /* rfc2396 */
{
    const uint8_t *end = in + in_len;

    while( in != end )
        if( is_rfc2396_alnum( *in ) )
            *out++ = (char) *in++;
        else
            out += tr_snprintf( out, 4, "%%%02X", (unsigned int)*in++ );

    *out = '\0';
}

static void
torrentCompletenessChanged( tr_torrent       * torrent       UNUSED,
                            tr_completeness    completeness  UNUSED,
                            void             * user_data     UNUSED )
{
    system( finishCall );
}

static int leftToScrape = 0;

static void
scrapeDoneFunc( tr_session   * session UNUSED,
                long           response_code,
                const void   * response,
                size_t         response_byte_count,
                void         * host )
{
    tr_benc top, *files;

    if( !tr_bencLoad( response, response_byte_count, &top, NULL )
      && tr_bencDictFindDict( &top, "files", &files )
      && files->val.l.count >= 2 )
    {
        int64_t   complete = -1, incomplete = -1, downloaded = -1;
        tr_benc * hash = &files->val.l.vals[1];
        tr_bencDictFindInt( hash, "complete", &complete );
        tr_bencDictFindInt( hash, "incomplete", &incomplete );
        tr_bencDictFindInt( hash, "downloaded", &downloaded );
        printf( "%4d seeders, %4d leechers, %5d downloads at %s\n",
                (int)complete, (int)incomplete, (int)downloaded,
                (char*)host );
        tr_bencFree( &top );
    }
    else
        fprintf( stderr, "Unable to parse response (http code %lu) at %s",
                 response_code,
                 (char*)host );

    --leftToScrape;

    tr_free( host );
}

static void
dumpInfo( FILE *          out,
          const tr_info * inf )
{
    int             i;
    int             prevTier = -1;
    tr_file_index_t ff;

    fprintf( out, "hash:\t" );
    for( i = 0; i < SHA_DIGEST_LENGTH; ++i )
        fprintf( out, "%02x", inf->hash[i] );
    fprintf( out, "\n" );

    fprintf( out, "name:\t%s\n", inf->name );

    for( i = 0; i < inf->trackerCount; ++i )
    {
        if( prevTier != inf->trackers[i].tier )
        {
            prevTier = inf->trackers[i].tier;
            fprintf( out, "\ntracker tier #%d:\n", ( prevTier + 1 ) );
        }
        fprintf( out, "\tannounce:\t%s\n", inf->trackers[i].announce );
    }

    fprintf( out, "size:\t%" PRIu64 " (%" PRIu64 " * %d + %" PRIu64 ")\n",
             inf->totalSize, inf->totalSize / inf->pieceSize,
             inf->pieceSize, inf->totalSize % inf->pieceSize );

    if( inf->comment && *inf->comment )
        fprintf( out, "comment:\t%s\n", inf->comment );
    if( inf->creator && *inf->creator )
        fprintf( out, "creator:\t%s\n", inf->creator );
    if( inf->isPrivate )
        fprintf( out, "private flag set\n" );

    fprintf( out, "file(s):\n" );
    for( ff = 0; ff < inf->fileCount; ++ff )
        fprintf( out, "\t%s (%" PRIu64 ")\n", inf->files[ff].name,
                 inf->files[ff].length );
}

static void
getStatusStr( const tr_stat * st, const tr_info *information )
{
	char TOF_eta[50];
    if( st->activity & TR_STATUS_CHECK_WAIT )
    {
        TOF_writeStatus(st, information, 1, "Waiting to verify local files" );
    }
    else if( st->activity & TR_STATUS_CHECK )
    {
	/*
        tr_snprintf( buf, buflen,
                     "Verifying local files (%.2f%%, %.2f%% valid)",
                     100 * st->recheckProgress,
                     100.0 * st->percentDone );
	*/
		sprintf(TOF_eta, "%.2f%% Verifying local files",100 * st->recheckProgress);
		TOF_writeStatus(st, information, 1, TOF_eta );
    }
    else if( st->activity & TR_STATUS_DOWNLOAD )
    {
        if( TOF_writeAllowed() )
        {
            strcpy(TOF_eta,"");
            if ( st->eta > 0 )
            {
                if ( st->eta < 604800 ) // 7 days
                {
                    if ( st->eta >= 86400 ) // 1 day
                        sprintf(TOF_eta, "%d:",
                            st->eta / 86400);

                    if ( st->eta >= 3600 ) // 1 hour
                        sprintf(TOF_eta, "%s%02d:",
                            TOF_eta,((st->eta % 86400) / 3600));

                    if ( st->eta >= 60 ) // 1 Minute
                        sprintf(TOF_eta, "%s%02d:",
                            TOF_eta,((st->eta % 3600) / 60));

                    sprintf(TOF_eta, "%s%02d",
                        TOF_eta,(st->eta % 60));
                }
                else
                    sprintf(TOF_eta, "-");
            }

            if ((st->seeders < -1) && (st->peersConnected == 0))
                sprintf(TOF_eta, "Connecting to Peers");

            TOF_writeStatus(st, information, 1, TOF_eta );
        }
    }
    else if( st->activity & TR_STATUS_SEED )
    {
        if (TOF_dieWhenDone == 1)
        {
            TOF_print( "Die-when-done set, setting shutdown-flag...\n" );
            gotsig = 1;
        }
        else
        {
            if (TOF_seedLimit == -1)
            {
                TOF_print( "Sharekill set to -1, setting shutdown-flag...\n" );
                gotsig = 1;
            }
            else if ( ( TOF_seedLimit > 0 ) && ( ( st->ratio * 100.0 ) > (float)TOF_seedLimit ) )
            {
                sprintf( TOF_message, "Seed-limit %d%% reached, setting shutdown-flag...\n", TOF_seedLimit );
                TOF_print( TOF_message );
                gotsig = 1;
            }
        }
        TOF_writeStatus(st, information, 1, "Download Succeeded" );
    }
    if( st->error )
    {
        sprintf( TOF_message, "error: %s\n", st->errorString );
        TOF_print( TOF_message );
    }
}

static const char*
getConfigDir( int argc, const char ** argv )
{
    int c;
    const char * configDir = NULL;
    const char * optarg;
    const int ind = tr_optind;

    while(( c = tr_getopt( getUsage( ), argc, argv, options, &optarg ))) {
        if( c == 'g' ) {
            configDir = optarg;
            break;
        }
    }

    tr_optind = ind;

    if( configDir == NULL )
        configDir = tr_getDefaultConfigDir( MY_NAME );

    return configDir;
}

int
main( int     argc,
      char ** argv )
{
    int          i, error;
    tr_session  * h;
    tr_ctor     * ctor;
    tr_torrent  * tor = NULL;
    tr_benc settings;
    const tr_info  *information;
    const char * configDir;
    char cwd[1024];

    printf( "Transmission %s - http://www.transmissionbt.com/ - modified for Torrentflux-b4rt\n",
            LONG_VERSION_STRING );

    /* user needs to pass in at least one argument */
    if( argc < 2 ) {
        tr_getopt_usage( MY_NAME, getUsage( ), options );
        return EXIT_FAILURE;
    }

    /* load the defaults from config file + libtransmission defaults */
    tr_bencInitDict( &settings, 0 );
    configDir = getConfigDir( argc, (const char**)argv );
    //tr_sessionLoadSettings( &settings, configDir, MY_NAME );

    /* the command line overrides defaults */
    if( parseCommandLine( &settings, argc, (const char**)argv ) )
    {
        printf("Invalid commandline option given\n");
        return EXIT_FAILURE;
    }

    /* Check the options for validity */
    if( !torrentPath ) {
        fprintf( stderr, "No torrent specified!\n" );
        return EXIT_FAILURE;
    }
    tr_bencDictRemove( &settings, TR_PREFS_KEY_DOWNLOAD_DIR );
    getcwd( cwd, sizeof( cwd ) );
    tr_bencDictAddStr( &settings, TR_PREFS_KEY_DOWNLOAD_DIR, cwd );

    /* don't bind the port if we're just running the CLI
       to get metainfo or to create a torrent */
    if( showInfo || showScrape || ( sourceFile != NULL ) )
        tr_bencDictAddInt( &settings, TR_PREFS_KEY_PEER_PORT, -1 );

    h = tr_sessionInit( "cli", configDir, FALSE, &settings );

    if( sourceFile && *sourceFile ) /* creating a torrent */
    {
        int                   err;
        tr_metainfo_builder * b = tr_metaInfoBuilderCreate( h, sourceFile );
        tr_tracker_info       ti;
        ti.tier = 0;
        ti.announce = (char*) announce;
        tr_makeMetaInfo( b, torrentPath, &ti, 1, comment, isPrivate );
        while( !b->isDone )
        {
            tr_wait( 1000 );
            printf( "." );
        }

        err = b->result;
        tr_metaInfoBuilderFree( b );
        return err;
    }

    ctor = tr_ctorNew( h );
    tr_ctorSetMetainfoFromFile( ctor, torrentPath );
    tr_ctorSetPaused( ctor, TR_FORCE, showScrape );
    tr_ctorSetDownloadDir( ctor, TR_FORCE, cwd );
    tr_ctorSetDownloadDir( ctor, TR_FALLBACK, cwd );
    
    if( showScrape )
    {
        tr_info info;

        if( !tr_torrentParse( h, ctor, &info ) )
        {
            int          i;
            const time_t start = time( NULL );
            for( i = 0; i < info.trackerCount; ++i )
            {
                if( info.trackers[i].scrape )
                {
                    const char * scrape = info.trackers[i].scrape;
                    char         escaped[SHA_DIGEST_LENGTH * 3 + 1];
                    char *       url, *host;
                    escape( escaped, info.hash, SHA_DIGEST_LENGTH );
                    url = tr_strdup_printf( "%s%cinfo_hash=%s",
                                            scrape,
                                            strchr( scrape,
                                                    '?' ) ? '&' : '?',
                                            escaped );
                    tr_httpParseURL( scrape, -1, &host, NULL, NULL );
                    ++leftToScrape;
                    tr_webRun( h, url, NULL, scrapeDoneFunc, host );
                    tr_free( url );
                }
            }

            fprintf( stderr, "scraping %d trackers:\n", leftToScrape );

            while( leftToScrape > 0 && ( ( time( NULL ) - start ) < 20 ) )
                tr_wait( 250 );
        }
        goto cleanup;
    }

    //* Torrentflux -START- */
    if (TOF_owner == NULL)
    {
        sprintf( TOF_message, "No owner supplied, using 'n/a'.\n" );
        TOF_print( TOF_message );
        TOF_owner = malloc((4) * sizeof(char));
        if (TOF_owner == NULL)
        {
            sprintf( TOF_message, "Error : not enough mem for malloc\n" );
            TOF_print( TOF_message );
            goto failed;
        }
    }

    // Output for log
    sprintf( TOF_message, "transmission %s starting up :\n", LONG_VERSION_STRING );
    TOF_print( TOF_message );
    sprintf( TOF_message, " - torrent : %s\n", torrentPath );
    TOF_print( TOF_message );
    sprintf( TOF_message, " - owner : %s\n", TOF_owner );
    TOF_print( TOF_message );
    sprintf( TOF_message, " - dieWhenDone : %d\n", TOF_dieWhenDone );
    TOF_print( TOF_message );
    sprintf( TOF_message, " - seedLimit : %d\n", TOF_seedLimit );
    TOF_print( TOF_message );
    sprintf( TOF_message, " - bindPort : %d\n", tr_sessionGetPeerPort(h) );
    TOF_print( TOF_message );
    sprintf( TOF_message, " - uploadLimit : %d\n", tr_sessionGetSpeedLimit(h, TR_UP ) );
    TOF_print( TOF_message );
    sprintf( TOF_message, " - downloadLimit : %d\n", tr_sessionGetSpeedLimit(h, TR_DOWN ) );
    TOF_print( TOF_message );
    sprintf( TOF_message, " - natTraversal : %d\n", tr_sessionIsPortForwardingEnabled(h) );
    TOF_print( TOF_message );
    sprintf( TOF_message, " - displayInterval : %d\n", TOF_displayInterval );
    TOF_print( TOF_message );
    sprintf( TOF_message, " - downloadDir : %s\n", tr_sessionGetDownloadDir(h) );
    TOF_print( TOF_message );
    
    if (finishCall != NULL)
    {
        sprintf( TOF_message, " - finishCall : %s\n", finishCall );
        TOF_print( TOF_message );
    }
    /* -END- */


    if( showInfo )
    {
        tr_info info;

        if( !tr_torrentParse( h, ctor, &info ) )
        {
            dumpInfo( stdout, &info );
            tr_metainfoFree( &info );
        }

        tr_ctorFree( ctor );
        goto cleanup;
    }

    tor = tr_torrentNew( h, ctor, &error );
    
    sprintf( TOF_message, " - downloadDir from torrent object, usually loaded from resume data : %s\n", tr_torrentGetDownloadDir( tor ) );
    TOF_print( TOF_message );
    
    
    tr_ctorFree( ctor );
    if( !tor )
    {
        //fprintf( stderr, "Failed opening torrent file `%s'\n", torrentPath );
    
	sprintf( TOF_message, "Failed opening torrent file %s'\n", torrentPath );
	TOF_print( TOF_message );
	
        tr_sessionClose( h );
        return EXIT_FAILURE;
    }

    signal( SIGINT, sigHandler );
#ifndef WIN32
    signal( SIGHUP, sigHandler );
#endif
    tr_torrentSetCompletenessCallback( tor, torrentCompletenessChanged, NULL );
    tr_torrentStart( tor );

    if( verify )
    {
        verify = 0;
        tr_torrentVerify( tor );
    }

    /* Torrentflux -START */

    // initialize status-facility
    if (TOF_initStatus() == 0)
    {
        sprintf( TOF_message, "Failed to init status-facility. exit transmission.\n" );
        TOF_print( TOF_message );
        goto failed;
    }

    // initialize command-facility
    if (TOF_initCommand() == 0)
    {
        sprintf( TOF_message, "Failed to init command-facility. exit transmission.\n" );
        TOF_print( TOF_message );
        goto failed;
    }

    // write pid
    if (TOF_writePID() == 0)
    {
        sprintf( TOF_message, "Failed to write pid-file. exit transmission.\n" );
        TOF_print( TOF_message );
        goto failed;
    }

    sprintf( TOF_message, "Transmission up and running.\n" );

    information = tr_torrentInfo( tor );
    /* -END- */


    for( ; ; )
    {
        //char            line[LINEWIDTH];
        const tr_stat * st;

        /* Torrentflux -START */
        TOF_checkCmd++;

        if( TOF_checkCmd == TOF_displayInterval)
        {
            TOF_checkCmd = 1;
            /* If Torrentflux wants us to shutdown */
            if (TOF_processCommands(h))
                gotsig = 1;
        }
        /* -END- */

        tr_wait( 200 );

        if( gotsig )
        {
            gotsig = 0;
            //printf( "\nStopping torrent...\n" );
            tr_torrentStop( tor );
        }

        if( manualUpdate )
        {
            manualUpdate = 0;
            if( !tr_torrentCanManualUpdate( tor ) )
            {
                fprintf(
                    stderr,
                    "\nReceived SIGHUP, but can't send a manual update now\n" );
            }
            else
            {
                fprintf( stderr,
                         "\nReceived SIGHUP: manual update scheduled\n" );
                tr_torrentManualUpdate( tor );
            }
        }

        st = tr_torrentStat( tor );
        if( st->activity & TR_STATUS_STOPPED )
        {
            break;
        }
        getStatusStr( st, information);
        //printf( "\r%-*s", LINEWIDTH, line );
		
        if( st->error )
            fprintf( stderr, "\n%s\n", st->errorString );
    }
	
    
    {
         const tr_stat * st;
         st = tr_torrentStat( tor );
          
         TOF_print("Transmission shutting down...\n");
           
         /* Try for 5 seconds to delete any port mappings for nat traversal */
         tr_sessionSetPortForwardingEnabled( h, 0 );
         for( i = 0; i < 10; i++ )
         {
    		if( TR_PORT_UNMAPPED == tr_sessionIsPortForwardingEnabled( h ) )
    		{
    		    /* Port mappings were deleted */
    		    break;
    		}
    		tr_wait( 500 );
         }
         if (st->percentDone >= 1)
            TOF_writeStatus(st, information, 0, "Download Succeeded" );
         else
            TOF_writeStatus(st, information, 0, "Torrent Stopped" );

         TOF_deletePID();

         TOF_print("Transmission exit.\n");

         TOF_free();
    }

cleanup:

//    tr_sessionSaveSettings( h, configDir, &settings );

    printf( "\n" );
    tr_bencFree( &settings );
    tr_sessionClose( h );
    return EXIT_SUCCESS;

failed:
    TOF_free();
    tr_torrentFree( tor );
    tr_sessionClose( h );
    return EXIT_FAILURE;

}

/***
****
****
****
***/

static int
parseCommandLine( tr_benc * d, int argc, const char ** argv )
{
    int64_t downloadLimit, uploadLimit;
    int          c;
    const char * optarg;

    while(( c = tr_getopt( getUsage( ), argc, argv, options, &optarg )))
    {
        switch( c )
        {
            case 'a':
                announce = optarg; break;

            case 'b': tr_bencDictAddInt( d, TR_PREFS_KEY_BLOCKLIST_ENABLED, 1 );
                      break;
            case 'B': tr_bencDictAddInt( d, TR_PREFS_KEY_BLOCKLIST_ENABLED, 0 );
                      break;
            case 'c': comment = optarg;
                      break;
            case 'd': downloadLimit=atoi( optarg );            
                      switch (downloadLimit) {
                          case 0:
                              downloadLimit = -1;
                              break;
                          case -2:
                              downloadLimit = 0;
                              break;
                      }
                      if (downloadLimit>=0)
                      {
                          tr_bencDictAddInt( d, TR_PREFS_KEY_DSPEED, downloadLimit );
                          tr_bencDictAddInt( d, TR_PREFS_KEY_DSPEED_ENABLED, 1 );
                      }
                      else
                          tr_bencDictAddInt( d, TR_PREFS_KEY_DSPEED_ENABLED, 0 );
                      break;
            case 'D': tr_bencDictAddInt( d, TR_PREFS_KEY_DSPEED_ENABLED, 0 );
                      break;
            case 'f': finishCall = optarg;
                      break;
            case 'g': /* handled above */
                      break;
            case 'i': showInfo = 1;
                      break;
            case 'm': tr_bencDictAddInt( d, TR_PREFS_KEY_PORT_FORWARDING, 1 );
                      break;
            case 'M': tr_bencDictAddInt( d, TR_PREFS_KEY_PORT_FORWARDING, 0 );
                      break;
            case 'n': sourceFile = optarg; break;
            case 'p': tr_bencDictAddInt( d, TR_PREFS_KEY_PEER_PORT, atoi( optarg ) );
                      break;
            case 'r': isPrivate = 1;
                      break;
            case 's': showScrape = 1;
                      break;
            case 't': tr_bencDictAddInt( d, TR_PREFS_KEY_PEER_SOCKET_TOS, atoi( optarg ) );
                      break;
            case 'u': 
                      uploadLimit=atoi( optarg );
                      switch (uploadLimit) {
                          case 0:
                              uploadLimit = -1;
                              break;
                          case -2:
                              uploadLimit = 0;
                              break;
                      }
                      if (uploadLimit>=0)
                      {
                          tr_bencDictAddInt( d, TR_PREFS_KEY_USPEED, uploadLimit );
                          tr_bencDictAddInt( d, TR_PREFS_KEY_USPEED_ENABLED, 1 );
                      }
                      else
                          tr_bencDictAddInt( d, TR_PREFS_KEY_USPEED_ENABLED, 0 );
                      break;
            case 'U': tr_bencDictAddInt( d, TR_PREFS_KEY_USPEED_ENABLED, 0 );
                      break;
            case 'v': verify = 1;
                      break;
            case 'w': tr_bencDictAddStr( d, TR_PREFS_KEY_DOWNLOAD_DIR, optarg );
                      break;
            case 'E':
                TOF_displayInterval = atoi( optarg );
                break;
            case 'L':
                TOF_seedLimit = atoi( optarg );
                break;
            case 'O':
                TOF_owner = optarg;
                break;
            case 'W':
                TOF_dieWhenDone = atoi( optarg );
                break;
            case 910: tr_bencDictAddInt( d, TR_PREFS_KEY_ENCRYPTION, TR_ENCRYPTION_REQUIRED );
                      break;
            case 911: tr_bencDictAddInt( d, TR_PREFS_KEY_ENCRYPTION, TR_ENCRYPTION_PREFERRED );
                      break;
            case 912: tr_bencDictAddInt( d, TR_PREFS_KEY_ENCRYPTION, TR_CLEAR_PREFERRED );
                      break;
            case TR_OPT_UNK:
                      torrentPath = optarg;
                      break;
            default: return 1;
        }
    }

    return 0;
}

static void
sigHandler( int signal )
{
    switch( signal )
    {
        case SIGINT:
            gotsig = 1; break;

#ifndef WIN32
        case SIGHUP:
            manualUpdate = 1; break;

#endif
        default:
            break;
    }
}


/* Torrentflux -START- */
static void TOF_print( char *printmsg )
{
    time_t rawtime;
    struct tm * timeinfo;
    time(&rawtime);
    timeinfo = localtime(&rawtime);

    fprintf(stderr, "[%4d/%02d/%02d - %02d:%02d:%02d] %s",
        timeinfo->tm_year + 1900,
        timeinfo->tm_mon + 1,
        timeinfo->tm_mday,
        timeinfo->tm_hour,
        timeinfo->tm_min,
        timeinfo->tm_sec,
        ((printmsg != NULL) && (strlen(printmsg) > 0)) ? printmsg : ""
    );
}

static int TOF_initStatus( void )
{
    int len = strlen(torrentPath) + 5;
    TOF_statFile = malloc((len + 1) * sizeof(char));
    if (TOF_statFile == NULL) {
        TOF_print(  "Error : TOF_initStatus: not enough mem for malloc\n" );
        return 0;
    }

    sprintf( TOF_statFile, "%s.stat", torrentPath );

    sprintf( TOF_message, "Initialized status-facility. (%s)\n", TOF_statFile );
    TOF_print( TOF_message );
    return 1;
}

static int TOF_initCommand( void )
{
    int len = strlen(torrentPath) + 4;
    TOF_cmdFile = malloc((len + 1) * sizeof(char));
    if (TOF_cmdFile == NULL) {
        TOF_print(  "Error : TOF_initCommand: not enough mem for malloc\n" );
        return 0;
    }
   sprintf( TOF_cmdFile, "%s.cmd", torrentPath );

    sprintf( TOF_message, "Initialized command-facility. (%s)\n", TOF_cmdFile );
    TOF_print( TOF_message );

    // remove command-file if exists
    TOF_cmdFp = NULL;
    TOF_cmdFp = fopen(TOF_cmdFile, "r");
    if (TOF_cmdFp != NULL)
    {
        fclose(TOF_cmdFp);
        sprintf( TOF_message, "Removing command-file. (%s)\n", TOF_cmdFile );
        TOF_print( TOF_message );
        remove(TOF_cmdFile);
        TOF_cmdFp = NULL;
    }
    return 1;
}

static int TOF_writePID( void )
{
    FILE * TOF_pidFp;
    char TOF_pidFile[strlen(torrentPath) + 4];

    sprintf(TOF_pidFile,"%s.pid",torrentPath);

    TOF_pidFp = fopen(TOF_pidFile, "w+");
    if (TOF_pidFp != NULL)
    {
        fprintf(TOF_pidFp, "%d", getpid());
        fclose(TOF_pidFp);
        sprintf( TOF_message, "Wrote pid-file: %s (%d)\n",
            TOF_pidFile , getpid() );
        TOF_print( TOF_message );
        return 1;
    }
    else
    {
        sprintf( TOF_message, "Error opening pid-file for writting: %s (%d)\n",
            TOF_pidFile , getpid() );
        TOF_print( TOF_message );
        return 0;
    }
}

static void TOF_deletePID( void )
{
    char TOF_pidFile[strlen(torrentPath) + 4];

    sprintf(TOF_pidFile,"%s.pid",torrentPath);

    sprintf( TOF_message, "Removing pid-file: %s (%d)\n", TOF_pidFile , getpid() );
    TOF_print( TOF_message );

    remove(TOF_pidFile);
}

static void TOF_writeStatus( const tr_stat *s, const tr_info *info, const int state, const char *status )
{
    if( !TOF_writeAllowed() && state != 0 ) return;

    TOF_statFp = fopen(TOF_statFile, "w+");
    if (TOF_statFp != NULL)
    {
        float TOF_pd,TOF_ratio;
        int TOF_seeders,TOF_leechers;

        TOF_seeders  = ( s->seeders < 0 )  ? 0 : s->seeders;
        TOF_leechers = ( s->leechers < 0 ) ? 0 : s->leechers;

        if (state == 0 && s->percentDone < 1)
            TOF_pd = ( -100.0 * s->percentDone ) - 100;
        else
            TOF_pd = 100.0 * s->percentDone;

        TOF_ratio = s->ratio < 0 ? 0 : s->ratio;

        fprintf(TOF_statFp,
            "%d\n%.1f\n%s\n%.1f kB/s\n%.1f kB/s\n%s\n%d (%d)\n%d (%d)\n%.1f\n%d\n%" PRIu64 "\n%" PRIu64 "\n%" PRIu64,
            state,                                       /* State            */
            TOF_pd,                                     /* Progress         */
            status,                                    /* Status text      */
            s->pieceDownloadSpeed,                          /* Download speed   */    // versus rawDownloadSpeed
            s->pieceUploadSpeed,                           /* Upload speed     */   // versus rawUploadSpeed
            TOF_owner,                              /* Owner            */
            s->peersSendingToUs, TOF_seeders,      /* Seeder           */
            s->peersGettingFromUs, TOF_leechers,  /* Leecher          */
            100.0 * TOF_ratio,                   /* ratio            */
            TOF_seedLimit,                      /* seedlimit        */
            s->uploadedEver,                   /* uploaded bytes   */
            s->downloadedEver,                /* downloaded bytes */
            info->totalSize                  /* global size      */
        );
        fclose(TOF_statFp);
        
    }
    else
    {
        sprintf( TOF_message, "Error opening stat-file for writting: %s\n", TOF_statFile );
        TOF_print( TOF_message );
    }
}

static int TOF_processCommands(tr_session * h)
{
    /*   return values:
     *   0 :: do not shutdown transmission
     *   1 :: shutdown transmission
     */

    /* Now Process the CommandFile */

    int  commandCount = 0;
    int  isNewline;
    long fileLen;
    long index;
    long startPos;
    long totalChars;
    char currentLine[128];
    char *fileBuffer;
    char *fileCurrentPos;

    /* Try opening the CommandFile */
    TOF_cmdFp = NULL;
    TOF_cmdFp = fopen(TOF_cmdFile, "r");

    /* File does not exist */
    if( TOF_cmdFp == NULL )
        return 0;

    sprintf( TOF_message, "Processing command-file %s...\n", TOF_cmdFile );
    TOF_print( TOF_message );

    // get length
    fseek(TOF_cmdFp, 0L, SEEK_END);
    fileLen = ftell(TOF_cmdFp);
    rewind(TOF_cmdFp);

    if ( fileLen >= TOF_CMDFILE_MAXLEN || fileLen < 1 )
    {
        if( fileLen >= TOF_CMDFILE_MAXLEN )
            sprintf( TOF_message, "Size of command-file too big, skip. (max-size: %d)\n", TOF_CMDFILE_MAXLEN );
        else
            sprintf( TOF_message, "No commands found in command-file.\n" );

        TOF_print( TOF_message );
        /* remove file */
        remove(TOF_cmdFile);
        goto finished;
    }

    fileBuffer = calloc(fileLen + 1, sizeof(char));
    if (fileBuffer == NULL)
    {
        TOF_print( "Not enough memory to read command-file\n" );
        /* remove file */
        remove(TOF_cmdFile);
        goto finished;
    }

    fread(fileBuffer, fileLen, 1, TOF_cmdFp);
    fclose(TOF_cmdFp);
    remove(TOF_cmdFile);
    TOF_cmdFp = NULL;
    totalChars = 0L;
    fileCurrentPos = fileBuffer;

    while (*fileCurrentPos)
    {
        index = 0L;
        isNewline = 0;
        startPos = totalChars;
        while (*fileCurrentPos)
        {
            if (!isNewline)
            {
                if ( *fileCurrentPos == 10 )
                    isNewline = 1;
            }
            else if (*fileCurrentPos != 10)
            {
                break;
            }
            ++totalChars;
            if ( index < 127 )
                currentLine[index++] = *fileCurrentPos++;
            else
            {
                fileCurrentPos++;
                break;
            }
        }

        if ( index > 1 )
        {
            commandCount++;
            currentLine[index - 1] = '\0';

            if (TOF_execCommand(h, currentLine))
            {
                free(fileBuffer);
                return 1;
            }
        }
    }

    if (commandCount == 0)
        TOF_print( "No commands found in command-file.\n" );

    free(fileBuffer);

    finished:
        return 0;
}

static int TOF_execCommand(tr_session *h, char *s)
{
    int i, uploadLimit, downloadLimit;
    int len = strlen(s);
    char opcode;
    char workload[len];

    opcode = s[0];
    for (i = 0; i < len - 1; i++)
        workload[i] = s[i + 1];
    workload[len - 1] = '\0';

    switch (opcode)
    {
        case 'q':
            TOF_print( "command: stop-request, setting shutdown-flag...\n" );
            return 1;

        case 'u':
            if (strlen(workload) < 1)
            {
                TOF_print( "invalid upload-rate...\n" );
                return 0;
            }

            uploadLimit = atoi(workload);
            sprintf( TOF_message, "command: setting upload-rate to %d...\n", uploadLimit );
            TOF_print( TOF_message );

            tr_sessionSetSpeedLimit( h, TR_UP,   uploadLimit );
            tr_sessionSetSpeedLimitEnabled( h, TR_UP,   uploadLimit > 0 );
            return 0;

        case 'd':
            if (strlen(workload) < 1)
            {
                TOF_print( "invalid download-rate...\n" );
                return 0;
            }

            downloadLimit = atoi(workload);
            sprintf( TOF_message, "command: setting download-rate to %d...\n", downloadLimit );
            TOF_print( TOF_message );

            tr_sessionSetSpeedLimit( h, TR_DOWN, downloadLimit );
            tr_sessionSetSpeedLimitEnabled( h, TR_DOWN, downloadLimit > 0 );
            return 0;

        case 'w':
            if (strlen(workload) < 1)
            {
                TOF_print( "invalid die-when-done flag...\n" );
                return 0;
            }

            switch (workload[0])
            {
                case '0':
                    TOF_print( "command: setting die-when-done to 0\n" );
                    TOF_dieWhenDone = 0;
                    break;
                case '1':
                    TOF_print( "command: setting die-when-done to 1\n" );
                    TOF_dieWhenDone = 1;
                    break;
                default:
                    sprintf( TOF_message, "invalid die-when-done flag: %c...\n", workload[0] );
                    TOF_print( TOF_message );
            }
            return 0;

        case 'l':
            if (strlen(workload) < 1)
            {
                TOF_print( "invalid sharekill ratio...\n" );
                return 0;
            }

            TOF_seedLimit = atoi(workload);
            sprintf( TOF_message, "command: setting sharekill to %d...\n", TOF_seedLimit );
            TOF_print( TOF_message );
            return 0;

        default:
            sprintf( TOF_message, "op-code unknown: %c\n", opcode );
            TOF_print( TOF_message );
    }
    return 0;
}

static int TOF_writeAllowed ( void )
{
    /* We want to write status every <TOF_displayInterval> seconds,
       but we also want to start in the first round */
    if( TOF_checkCmd == 1 ) return 1;
    return 0;
}

static void TOF_free ( void )
{
    free(TOF_cmdFile);
    free(TOF_statFile);
    if(strcmp(TOF_owner,"n/a") == 0) free(TOF_owner);
}

/* -END- */

