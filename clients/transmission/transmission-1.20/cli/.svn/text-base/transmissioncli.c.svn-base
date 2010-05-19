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
#include <getopt.h>
#include <signal.h>

#include <libtransmission/transmission.h>
#include <libtransmission/makemeta.h>
#include <libtransmission/metainfo.h> /* tr_metainfoFree */
#include <libtransmission/utils.h> /* tr_wait */


/* macro to shut up "unused parameter" warnings */
#ifdef __GNUC__
#define UNUSED                  __attribute__((unused))
#else
#define UNUSED
#endif

#define TOF_CMDFILE_MAXLEN 65536

const char * USAGE =
"Usage: %s [-car[-m]] [-dfinpsuvELOW] [-h] file.torrent [output-dir]\n\n"
"Options:\n"
"  -h, --help                Print this help and exit\n" 
"  -i, --info                Print metainfo and exit\n"
"  -s, --scrape              Print counts of seeders/leechers and exit\n"
"  -V, --version             Print the version number and exit\n"
"  -c, --create-from <file>  Create torrent from the specified source file.\n"
"  -a, --announce <url>      Used in conjunction with -c.\n"
"  -g, --config-dir <path>   Where to look for configuration files\n"
"  -r, --private             Used in conjunction with -c.\n"
"  -m, --comment <text>      Adds an optional comment when creating a torrent.\n"
"  -d, --download <int> Maximum download rate \n" \
"                       (-1|0 = no limit, -2 = null, default = -1)\n"
"  -f, --finish <script>     Command you wish to run on completion\n" 
"  -n  --nat-traversal       Attempt NAT traversal using NAT-PMP or UPnP IGD\n"
"  -p, --port <int>          Port we should listen on (default = %d)\n"
"  -u, --upload <int>   Maximum upload rate \n" \
"                       (-1|0 = no limit, -2 = null, default = 20)\n"
"  -v, --verbose <int>       Verbose level (0 to 2, default = 0)\n"
"  -y, --recheck             Force a recheck of the torrent data\n"
"\nTorrentflux Commands:\n"
"  -E, --display-interval <int> Time between updates of stat-file (default = %d)\n"
"  -L, --seedlimit <int> Seed-Limit (Percent) to reach before shutdown\n"
"                        (0 = seed forever, -1 = no seeding, default = %d)\n"
"  -O, --owner <string> Name of the owner (default = 'n/a')\n"
"  -W, --die-when-done  Auto-Shutdown when done (0 = Off, 1 = On, default = %d)\n";

static int           showHelp      = 0;
static int           showInfo      = 0;
static int           showScrape    = 0;
static int           showVersion   = 0;
static int           isPrivate     = 0;
static int           verboseLevel  = 0;
static int           bindPort      = TR_DEFAULT_PORT;
static int           uploadLimit   = 20;
static int           downloadLimit = -1;
static char        * torrentPath   = NULL;
static char        * savePath      = ".";
static int           natTraversal  = 0;
static int           recheckData   = 0;
static sig_atomic_t  gotsig        = 0;
static sig_atomic_t  manualUpdate  = 0;

static char          * finishCall   = NULL;
static char          * announce     = NULL;
static char          * configdir    = NULL;
static char          * sourceFile   = NULL;
static char          * comment      = NULL;

/* Torrentflux -START- */
//static volatile char tf_shutdown = 0;
static int           TOF_dieWhenDone     = 0; 
static int           TOF_seedLimit       = 0;
static int           TOF_displayInterval = 5;
static int           TOF_checkCmd        = 0;

static char          * TOF_owner    = NULL;
static char          * TOF_statFile = NULL;
static FILE          * TOF_statFp   = NULL;
static char          * TOF_cmdFile  = NULL;
static FILE          * TOF_cmdFp    = NULL;
static char            TOF_message[512];
/* -END- */

static int  parseCommandLine ( int argc, char ** argv );
static void sigHandler       ( int signal );

/* Torrentflux -START- */
static int TOF_processCommands(tr_handle *h);
static int TOF_execCommand(tr_handle *h, char *s);
static void TOF_print ( char *printmsg );
static void TOF_free ( void );
static int TOF_initStatus ( void );
static void TOF_writeStatus ( const tr_stat *s, const tr_info *info, const int state, const char *status );
static int TOF_initCommand ( void );
static int TOF_writePID ( void );
static void TOF_deletePID ( void );
static int  TOF_writeAllowed ( void );
/* -END- */

/*static char *
getStringRatio( float ratio )
{
    static char string[20];

    if( ratio == TR_RATIO_NA )
        return "n/a";
    snprintf( string, sizeof string, "%.3f", ratio );
    return string;
} */

#define LINEWIDTH 80

static void
torrentStateChanged( tr_torrent   * torrent UNUSED,
                     cp_status_t    status UNUSED,
                     void         * user_data UNUSED )
{
    system( finishCall );
}

int
main( int argc, char ** argv )
{
    int i, error;
    tr_handle  * h;
    const tr_info  *information;
    tr_ctor * ctor;
    const tr_stat * s;
    tr_torrent * tor = NULL;

    char TOF_eta[50];

    /* Get options */
    if( parseCommandLine( argc, argv ) )
    {
        printf( "Transmission %s - http://www.transmissionbt.com/ - modified for Torrentflux-b4rt\n\n",
            LONG_VERSION_STRING );
        printf( USAGE, argv[0], TR_DEFAULT_PORT, 
            TOF_displayInterval, TOF_seedLimit, TOF_dieWhenDone );
        return EXIT_FAILURE;
    }

    if( showVersion )
        return EXIT_SUCCESS;

    if( showHelp )
    {
        printf( "Transmission %s - http://transmission.m0k.org/ - modified for Torrentflux-b4rt\n\n",
            LONG_VERSION_STRING );
        printf( USAGE, argv[0], TR_DEFAULT_PORT, 
            TOF_displayInterval, TOF_seedLimit, TOF_dieWhenDone );
        return EXIT_SUCCESS;
    }

    if( bindPort < 1 || bindPort > 65535 )
    {
        sprintf( TOF_message, "Invalid port '%d'\n", bindPort );
        TOF_print( TOF_message );
        return EXIT_FAILURE;
    }

    /* don't bind the port if we're just running the CLI 
     * to get metainfo or to create a torrent */
    if( showInfo || showScrape || ( sourceFile != NULL ) )
        bindPort = -1;

    if( configdir == NULL )
        configdir = strdup( tr_getDefaultConfigDir( ) );

    // check rate-args to behave like other clients in tfb
    // up
    switch (uploadLimit) {
        case 0:
            uploadLimit = -1;
            break;
        case -2:
            uploadLimit = 0;
            break;
    }
    // down
    switch (downloadLimit) {
        case 0:
            downloadLimit = -1;
            break;
        case -2:
            downloadLimit = 0;
            break;
    }

    /* Initialize libtransmission */
    h = tr_initFull( configdir,
                     "cli",                         /* tag */
                     1,                             /* pex enabled */
                     natTraversal,                  /* nat enabled */
                     bindPort,                      /* public port */
                     TR_ENCRYPTION_PREFERRED,       /* encryption mode */
                     uploadLimit >= 0,              /* use upload speed limit? */
                     uploadLimit,                   /* upload speed limit */
                     downloadLimit >= 0,            /* use download speed limit? */
                     downloadLimit,                 /* download speed limit */
                     TR_DEFAULT_GLOBAL_PEER_LIMIT,
                     verboseLevel + 1,              /* messageLevel */
                     0,                             /* is message queueing enabled? */
                     0,                             /* use the blocklist? */
                     TR_DEFAULT_PEER_SOCKET_TOS );

    if( sourceFile && *sourceFile ) /* creating a torrent */
    {
        int err;
        tr_metainfo_builder * builder = tr_metaInfoBuilderCreate( h, sourceFile );
        tr_makeMetaInfo( builder, torrentPath, announce, comment, isPrivate );
        while( !builder->isDone ) {
            tr_wait( 1000 );
            printf( "." );
        }
        err = builder->result;
        tr_metaInfoBuilderFree( builder );
        return err;
    }

    ctor = tr_ctorNew( h );
    tr_ctorSetMetainfoFromFile( ctor, torrentPath );
    tr_ctorSetPaused( ctor, TR_FORCE, 0 );
    tr_ctorSetDestination( ctor, TR_FORCE, savePath );

    if( showInfo )
    {
        tr_info info;

        if( !tr_torrentParse( h, ctor, &info ) )
        {
            int prevTier = -1;
            tr_file_index_t ff;

            printf( "hash:\t" );
            for( i=0; i<SHA_DIGEST_LENGTH; ++i )
                printf( "%02x", info.hash[i] );
            printf( "\n" );

            printf( "name:\t%s\n", info.name );

            for( i=0; i<info.trackerCount; ++i ) {
                if( prevTier != info.trackers[i].tier ) {
                    prevTier = info.trackers[i].tier;
                    printf( "\ntracker tier #%d:\n", (prevTier+1) );
                }
                printf( "\tannounce:\t%s\n", info.trackers[i].announce );
            }

            printf( "size:\t%"PRIu64" (%"PRIu64" * %d + %"PRIu64")\n",
                    info.totalSize, info.totalSize / info.pieceSize,
                    info.pieceSize, info.totalSize % info.pieceSize );

            if( info.comment[0] )
                printf( "comment:\t%s\n", info.comment );
            if( info.creator[0] )
                printf( "creator:\t%s\n", info.creator );
            if( info.isPrivate )
                printf( "private flag set\n" );

            printf( "file(s):\n" );
            for( ff=0; ff<info.fileCount; ++ff )
                printf( "\t%s (%"PRIu64")\n", info.files[ff].name, info.files[ff].length );

            tr_metainfoFree( &info );
        }

        tr_ctorFree( ctor );
        goto cleanup;
    }


    tor = tr_torrentNew( h, ctor, &error );
    tr_ctorFree( ctor );
    if( tor == NULL )
    {
        sprintf( TOF_message, "Failed opening torrent file `%s'\n", torrentPath );
        TOF_print( TOF_message );
        tr_close( h );
        return EXIT_FAILURE;
    }

    if( showScrape )
    {
        const struct tr_stat * stats;
        const uint64_t start = tr_date( );
        //printf( "Scraping, Please wait...\n" );
        
        do
        {
            stats = tr_torrentStat( tor );
            if( !stats || tr_date() - start > 20000 )
            {
                printf( "0 seeder(s), 0 leecher(s), 0 download(s).\n" );
                goto cleanup;
            }
            tr_wait( 2000 );
        }
        while( stats->completedFromTracker == -1 || stats->leechers == -1 || stats->seeders == -1 );
        
        printf( "%d seeder(s), %d leecher(s), %d download(s).\n",
            stats->seeders, stats->leechers, stats->completedFromTracker );

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
        strcpy(TOF_owner,"n/a");
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
    sprintf( TOF_message, " - bindPort : %d\n", bindPort );
    TOF_print( TOF_message );
    sprintf( TOF_message, " - uploadLimit : %d\n", uploadLimit );
    TOF_print( TOF_message );
    sprintf( TOF_message, " - downloadLimit : %d\n", downloadLimit );
    TOF_print( TOF_message );
    sprintf( TOF_message, " - natTraversal : %d\n", natTraversal );
    TOF_print( TOF_message );
    sprintf( TOF_message, " - displayInterval : %d\n", TOF_displayInterval );
    TOF_print( TOF_message );
    if (finishCall != NULL)
    {
        sprintf( TOF_message, " - finishCall : %s\n", finishCall );
        TOF_print( TOF_message );
    }    
    /* -END- */

    signal( SIGINT, sigHandler );
    signal( SIGHUP, sigHandler );

    tr_torrentSetStatusCallback( tor, torrentStateChanged, NULL );
    tr_torrentStart( tor );

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
    TOF_print( TOF_message );
    
    information = tr_torrentInfo( tor );
    /* -END- */

    for( ;; )
    {
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

        tr_wait( 1000 );

        if( gotsig )
        {
            gotsig = 0;
            tr_torrentStop( tor );
            tr_natTraversalEnable( h, 0 );
        }
        
        if( manualUpdate )
        {
            manualUpdate = 0;
            if ( !tr_torrentCanManualUpdate( tor ) )
                fprintf( stderr, "\rReceived SIGHUP, but can't send a manual update now\n" );
            else {
                fprintf( stderr, "\rReceived SIGHUP: manual update scheduled\n" );
                tr_manualUpdate( tor );
            }
        }
        
        if( recheckData )
        {
            recheckData = 0;
            tr_torrentVerify( tor );
        }

        s = tr_torrentStat( tor );

        if( s->status & TR_STATUS_CHECK_WAIT )
        {
            TOF_writeStatus(s, information, 1, "Waiting to verify local files" );
        }
        else if( s->status & TR_STATUS_CHECK )
        {
            TOF_writeStatus(s, information, 1, "Verifying local files" );
        }
        else if( s->status & TR_STATUS_DOWNLOAD )
        {
            if( TOF_writeAllowed() )
            {
                strcpy(TOF_eta,"");
                if ( s->eta > 0 ) 
                {
                    if ( s->eta < 604800 ) // 7 days
                    {
                        if ( s->eta >= 86400 ) // 1 day
                            sprintf(TOF_eta, "%d:",
                                s->eta / 86400);
                        
                        if ( s->eta >= 3600 ) // 1 hour
                            sprintf(TOF_eta, "%s%02d:",
                                TOF_eta,((s->eta % 86400) / 3600));
                        
                        if ( s->eta >= 60 ) // 1 Minute
                            sprintf(TOF_eta, "%s%02d:",
                                TOF_eta,((s->eta % 3600) / 60));
                                
                        sprintf(TOF_eta, "%s%02d",
                            TOF_eta,(s->eta % 60));
                    }
                    else
                        sprintf(TOF_eta, "-");
                }
                
                if ((s->seeders < -1) && (s->peersConnected == 0))
                    sprintf(TOF_eta, "Connecting to Peers");
                
                TOF_writeStatus(s, information, 1, TOF_eta );
            }
        }
        else if( s->status & TR_STATUS_SEED )
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
                else if ( ( TOF_seedLimit > 0 ) && ( ( s->ratio * 100.0 ) > (float)TOF_seedLimit ) ) 
                {
                    sprintf( TOF_message, "Seed-limit %d%% reached, setting shutdown-flag...\n", TOF_seedLimit );
                    TOF_print( TOF_message );
                    gotsig = 1;
                }
            }
            TOF_writeStatus(s, information, 1, "Download Succeeded" );
        }
        else if( s->status & TR_STATUS_STOPPED )
        {
            break;
        }
        if( s->error )
        {
            sprintf( TOF_message, "error: %s\n", s->errorString );
            TOF_print( TOF_message );
        }
        else if( verboseLevel > 0 )
        {
            fprintf( stderr, "\n" );
        }
    }
    TOF_print("Transmission shutting down...\n");

    /* Try for 5 seconds to delete any port mappings for nat traversal */
    tr_natTraversalEnable( h, 0 );
    for( i = 0; i < 10; i++ )
    {
        const tr_handle_status * hstat = tr_handleStatus( h );
        if( TR_NAT_TRAVERSAL_UNMAPPED == hstat->natTraversalStatus )
        {
            /* Port mappings were deleted */
            break;
        }
        tr_wait( 500 );
    }

    if (s->percentDone >= 1)
        TOF_writeStatus(s, information, 0, "Download Succeeded" );
    else 
        TOF_writeStatus(s, information, 0, "Torrent Stopped" );
            
    TOF_deletePID();
    
    TOF_print("Transmission exit.\n");
    
    TOF_free();

cleanup:
    tr_torrentClose( tor );
    tr_close( h );
    return EXIT_SUCCESS;

failed:
    TOF_free();
    tr_torrentClose( tor );
    tr_close( h );
    return EXIT_FAILURE;
}

static int
parseCommandLine( int argc, char ** argv )
{
    for( ;; )
    {
        static const struct option long_options[] = {
            { "announce",      required_argument, NULL, 'a' },
            { "create-from",   required_argument, NULL, 'c' },
            { "download",      required_argument, NULL, 'd' },
            { "finish",        required_argument, NULL, 'f' },
            { "config-dir",    required_argument, NULL, 'g' },
            { "help",          no_argument,       NULL, 'h' },
            { "info",          no_argument,       NULL, 'i' },
            { "comment",       required_argument, NULL, 'm' },
            { "nat-traversal", no_argument,       NULL, 'n' },
            { "output-dir",    required_argument, NULL, 'o' },
            { "display-interval", required_argument, NULL, 'E' },
            { "seedlimit",        required_argument, NULL, 'L' },
            { "owner",            required_argument, NULL, 'O' },
            { "die-when-done",    required_argument, NULL, 'W' },
            { "port",          required_argument, NULL, 'p' },
            { "private",       no_argument,       NULL, 'r' },
            { "scrape",        no_argument,       NULL, 's' },
            { "upload",        required_argument, NULL, 'u' },
            { "verbose",       required_argument, NULL, 'v' },
            { "version",       no_argument,       NULL, 'V' },
            { "recheck",       no_argument,       NULL, 'y' },
            { 0, 0, 0, 0} };
        int optind = 0;
        int c = getopt_long( argc, argv,
                             "a:c:d:f:g:him:no:p:rsu:v:VyE:L:W:O:",
                             long_options, &optind );
        if( c < 0 )
        {
            break;
        }
        switch( c )
        {
            case 'a': announce = optarg; break;
            case 'c': sourceFile = optarg; break;
            case 'd': downloadLimit = atoi( optarg ); break;
            case 'f': finishCall = optarg; break;
            case 'g': configdir = strdup( optarg ); break;
            case 'h': showHelp = 1; break;
            case 'i': showInfo = 1; break;
            case 'm': comment = optarg; break;
            case 'n': natTraversal = 1; break;
            case 'o': savePath = optarg;
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
            case 'p': bindPort = atoi( optarg ); break;
            case 'r': isPrivate = 1; break;
            case 's': showScrape = 1; break;
            case 'u': uploadLimit = atoi( optarg ); break;
            case 'v': verboseLevel = atoi( optarg ); break;
            case 'V': showVersion = 1; break;
            case 'y': recheckData = 1; break;
            default: return 1;
        }
    }

    if( showHelp || showVersion )
        return 0;

    if( optind >= argc )
        return 1;

    torrentPath = argv[optind];
    return 0;
}

static void sigHandler( int signal )
{
    switch( signal )
    {
        case SIGINT:
            gotsig = 1;
            break;
            
        case SIGHUP:
            manualUpdate = 1;
            break;

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
            s->rateDownload,                          /* Download speed   */
            s->rateUpload,                           /* Upload speed     */
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

static int TOF_processCommands(tr_handle * h)
{
    /*   return values:
     *   0 :: do not shutdown transmission
     *   1 :: shutdown transmission
     */
     
    /* Try opening the CommandFile */
    TOF_cmdFp = NULL;
    TOF_cmdFp = fopen(TOF_cmdFile, "r");

    /* File does not exist */
    if( TOF_cmdFp == NULL )
        return 0;
    
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

static int TOF_execCommand(tr_handle *h, char *s) 
{
    int i;
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

            tr_setGlobalSpeedLimit   ( h, TR_UP,   uploadLimit );
            tr_setUseGlobalSpeedLimit( h, TR_UP,   uploadLimit > 0 );
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

            tr_setGlobalSpeedLimit   ( h, TR_DOWN, downloadLimit );
            tr_setUseGlobalSpeedLimit( h, TR_DOWN, downloadLimit > 0 );
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
