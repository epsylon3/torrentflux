#!/usr/bin/env python
################################################################################
# $Id$
# $Revision$
# $Date$
################################################################################
#
# Written by Bram Cohen
# see LICENSE.txt for license information
#
################################################################################
#
# tftornado.py - use BitTornado with torrentflux-b4rt
# http://tf-b4rt.berlios.de/
#
################################################################################
from BitTornado import PSYCO
if PSYCO.psyco:
    try:
        import psyco
        assert psyco.__version__ >= 0x010100f0
        psyco.full()
    except:
        pass
from BitTornado.download_bt1 import BT1Download, defaults, parse_params, get_usage, get_response
from BitTornado.RawServer import RawServer, UPnP_ERROR
from random import seed
from socket import error as socketerror
from BitTornado.bencode import bencode
from BitTornado.natpunch import UPnP_test
from threading import Event
from os.path import abspath, isfile
from os import getpid, remove
from sys import argv, stdout
import sys
from sha import sha
from time import strftime
from BitTornado.clock import clock
from BitTornado import createPeerID, version

assert sys.version >= '2', "Install Python 2.0 or greater"
try:
    True
except:
    True = 1
    False = 0

PROFILER = False

if __debug__: LOGFILE=open(argv[3]+"."+str(getpid()),"w")

def traceMsg(msg):
    try:
        if __debug__:
           LOGFILE.write(msg + "\n")
           LOGFILE.flush()
    except:
        return

#------------------------------------------------------------------------------#
# tfb static methods                                                           #
#------------------------------------------------------------------------------#

def fmttime(n):
    """ fmttime """
    # short format :
    return fmttimeshort(n)
    # long format :
    # return fmttimelong(n)

def fmttimeshort(n):
    """ fmttimeshort """
    if n == 0:
        return 'complete!'
    try:
        n = int(n)
        assert n >= 0 and n < 5184000  # 60 days
    except:
        return '<unknown>'
    m, s = divmod(n, 60)
    h, m = divmod(m, 60)
    d, h = divmod(h, 24)
    if d >= 7:
        return '-'
    elif d > 0:
        return '%dd %02d:%02d:%02d' % (d, h, m, s)
    else:
        return '%02d:%02d:%02d' % (h, m, s)

def fmttimelong(n):
    """ fmttimelong """
    if n == 0:
        return 'complete!'
    try:
        n = int(n)
        assert n >= 0 and n < 5184000  # 60 days
    except:
        return '<unknown>'
    m, s = divmod(n, 60)
    h, m = divmod(m, 60)
    d, h = divmod(h, 24)
    y, d = divmod(d, 365)
    dec, y = divmod(y, 10)
    cent, dec = divmod(dec, 10)
    if cent > 0:
        return '%dcent %ddec %dy %dd %02d:%02d:%02d' % (cent, dec, y, d, h, m, s)
    elif dec > 0:
        return '%ddec %dy %dd %02d:%02d:%02d' % (dec, y, d, h, m, s)
    elif y > 0:
        return '%dy %dd %02d:%02d:%02d' % (y, d, h, m, s)
    elif d > 0:
        return '%dd %02d:%02d:%02d' % (d, h, m, s)
    else:
        return '%02d:%02d:%02d' % (h, m, s)

def transferLog(message, ts):
    """ transferLog """
    try:
        FILE = open(transferLogFile,"a+")
        if not ts:
            FILE.write(message)
        else:
            FILE.write(strftime('[%Y/%m/%d - %H:%M:%S]') + " " + message)
        FILE.flush()
        FILE.close()
    except Exception, e:
        sys.stderr.write("Failed to write log-file : " + transferLogFile + "\n")

#------------------------------------------------------------------------------#
# HeadlessDisplayer                                                            #
#------------------------------------------------------------------------------#
class HeadlessDisplayer:
    def __init__(self):
        self.done = False
        self.file = ''
        self.running = '1'
        self.percentDone = ''
        self.timeEst = 'Connecting to Peers'
        self.downloadTo = ''
        self.downRate = ''
        self.upRate = ''
        self.shareRating = ''
        self.percentShare = ''
        self.upTotal = 0
        self.downTotal = 0
        self.seedStatus = ''
        self.peerStatus = ''
        self.seeds = ''
        self.peers = ''
        self.errors = []
        self.last_update_time = -1
        self.autoShutdown = 'False'
        self.user = 'unknown'
        self.size = 0
        self.shareKill = '100'
        self.distcopy = ''
        self.stoppedAt = ''
        self.dow = None
        self.displayCounter = 0

    def finished(self):
        if __debug__: traceMsg('finished - begin')
        self.done = True
        self.percentDone = '100'
        self.timeEst = 'Download Succeeded!'
        self.downRate = ''
        self.display()
        if self.autoShutdown == 'True':
            self.upRate = ''
            if self.stoppedAt == '':
                self.writeStatus()
            if __debug__: traceMsg('finished - end - raising ki')
            raise KeyboardInterrupt
        if __debug__: traceMsg('finished - end')

    def failed(self):
        if __debug__: traceMsg('failed - begin')
        self.done = True
        self.percentDone = '0'
        self.timeEst = 'Download Failed!'
        self.downRate = ''
        self.display()
        if self.autoShutdown == 'True':
            self.upRate = ''
            if self.stoppedAt == '':
                self.writeStatus()
            if __debug__:traceMsg('failed - end - raising ki')
            raise KeyboardInterrupt
        if __debug__: traceMsg('failed - end')

    def error(self, errormsg):
        self.errors.append(errormsg)
        # log error
        transferLog("error: " + errormsg + "\n", True)

    def chooseFile(self, default, size, saveas, dir):
        self.file = '%s (%.1f MB)' % (default, float(size) / (1 << 20))
        self.size = size
        if saveas != '':
            default = saveas
        self.downloadTo = abspath(default)
        return default

    def newpath(self, path):
        self.downloadTo = path

    def scrub_errs(self):
        new_errors = []

        try:
            if self.errors:
                last_errMsg = ''
                errCount = 0
                for err in self.errors:
                    try:
                        if last_errMsg == '':
                            last_errMsg = err
                        elif last_errMsg == err:
                            errCount += 1
                        elif last_errMsg != err:
                            if errCount > 0:
                                new_errors.append(last_errMsg + ' (x' + str(errCount+1) + ')')
                            else:
                                new_errors.append(last_errMsg)
                            errCount = 0
                            last_errMsg = err
                    except:
                        if __debug__: traceMsg('scrub_errs - Failed scrub')
                        pass

            try:
                if len(new_errors) > 0:
                    if last_errMsg != new_errors[len(new_errors)-1]:
                        if errCount > 0:
                            new_errors.append(last_errMsg + ' (x' + str(errCount+1) + ')')
                        else:
                            new_errors.append(last_errMsg)
                    else:
                        if errCount > 0:
                            new_errors.append(last_errMsg + ' (x' + str(errCount+1) + ')')
                        else:
                            new_errors.append(last_errMsg)
            except:
                if __debug__: traceMsg('scrub_errs - Failed during scrub last Msg ')
                pass

            if len(self.errors) > 100:
                while len(self.errors) > 100 :
                    del self.errors[0:99]
                self.errors = new_errors

        except:
            if __debug__: traceMsg('scrub_errs - Failed during scrub Errors')
            pass

        return new_errors

    def display(self, dpflag = Event(), fractionDone = None, timeEst = None,
        downRate = None, upRate = None, activity = None,
        statistics = None,  **kws):
        if self.last_update_time + 0.1 > clock() and fractionDone not in (0.0, 1.0) and activity is not None:
            return
        self.last_update_time = clock()
        if fractionDone is not None:
            self.percentDone = str(float(int(fractionDone * 1000)) / 10)
        if timeEst is not None:
            self.timeEst = fmttime(timeEst)
        if activity is not None and not self.done:
            self.timeEst = activity
        if downRate is not None:
            self.downRate = '%.1f kB/s' % (float(downRate) / (1 << 10))
        if upRate is not None:
            self.upRate = '%.1f kB/s' % (float(upRate) / (1 << 10))
        if statistics is not None:
           if (statistics.shareRating < 0) or (statistics.shareRating > 100):
               self.shareRating = 'oo  (%.1f MB up / %.1f MB down)' % (float(statistics.upTotal) / (1<<20), float(statistics.downTotal) / (1<<20))
               self.downTotal = statistics.downTotal
               self.upTotal = statistics.upTotal
           else:
               self.shareRating = '%.3f  (%.1f MB up / %.1f MB down)' % (statistics.shareRating, float(statistics.upTotal) / (1<<20), float(statistics.downTotal) / (1<<20))
               self.downTotal = statistics.downTotal
               self.upTotal = statistics.upTotal
           if not self.done:
              self.seedStatus = '%d seen now, plus %.3f distributed copies' % (statistics.numSeeds,0.001*int(1000*statistics.numCopies))
              self.seeds = (str(statistics.numSeeds))
           else:
              self.seedStatus = '%d seen recently, plus %.3f distributed copies' % (statistics.numOldSeeds,0.001*int(1000*statistics.numCopies))
              self.seeds = (str(statistics.numOldSeeds))

           self.peers = '%d' % (statistics.numPeers)
           self.distcopy = '%.3f' % (0.001*int(1000*statistics.numCopies))
           self.peerStatus = '%d seen now, %.1f%% done at %.1f kB/s' % (statistics.numPeers,statistics.percentDone,float(statistics.torrentRate) / (1 << 10))

        dpflag.set()

        # process command-stack
        die = self.processCommandStack()
        # shutdown if requested
        if die:
            self.execShutdown()
            return;

        # ratio- + limit- checks / shutdown / stat
        if self.stoppedAt == '':
            die = False
            downRate = self.downTotal
            die = False
            if downRate == 0 and self.upTotal > 0:
                downRate = self.size
            if self.done:
                self.percentDone = '100'
                downRate = self.size
                if self.autoShutdown == 'True':
                    transferLog("die-when-done set, setting shutdown-flag...\n", True)
                    die = True
            if self.upTotal > 0:
                self.percentShare = '%.1f' % ((float(self.upTotal)/float(downRate))*100)
            else:
                self.percentShare = '0.0'
            if self.done and self.percentShare is not '' and self.autoShutdown == 'False':
                if (float(self.percentShare) >= float(self.shareKill)) and (self.shareKill != '0'):
                    transferLog("seed-limit "+str(self.shareKill)+" reached, setting shutdown-flag...\n", True)
                    die = True
                    self.upRate = ''
            elif (not self.done) and (self.timeEst == 'complete!') and (self.percentDone == '100.0'):
                if (float(self.percentShare) >= float(self.shareKill)) and (self.shareKill != '0'):
                    transferLog("seed-limit "+str(self.shareKill)+" reached, setting shutdown-flag...\n", True)
                    die = True
                    self.upRate = ''
            # shutdown / write stat-file
            if die:
                self.execShutdown()
            else:
                # write every 5 secs
                if self.displayCounter < 5:
                    self.displayCounter += 1
                else:
                    self.displayCounter = 0
                    # write stat-file
                    self.writeStatus()

    def processCommandStack(self):
        """ processCommandStack """
        if isfile(transferCommandFile):
            # process file
            transferLog("Processing command-file " + transferCommandFile + "...\n", True)
            try:
                # read file to mem
                f = open(transferCommandFile, 'r')
                commands = f.readlines()
                f.close
                # remove file
                try:
                    remove(transferCommandFile)
                except:
                    transferLog("Failed to remove command-file : " + transferCommandFile + "\n", True)
                    pass
                # exec commands
                if len(commands) > 0:
                    for command in commands:
                        command = command.replace("\n", "")
                        if len(command) > 0:
                            # exec, early out when reading a quit-command
                            if self.execCommand(command):
                                return True
                else:
                    transferLog("No commands found.\n", True)
            except:
                transferLog("Failed to read command-file : " + transferCommandFile + "\n", True)
                pass
        return False

    def execCommand(self, command):
        """ execCommand """
        opCode = command[0]

        # q
        if opCode == 'q':
            transferLog("command: stop-request, setting shutdown-flag...\n", True)
            return True

        # u
        elif opCode == 'u':
            if len(command) < 2:
                transferLog("invalid rate.\n", True)
                return False
            rateNew = command[1:]
            transferLog("command: setting upload-rate to " + rateNew + "...\n", True)
            self.dow.setUploadRate(int(rateNew))
            return False

        # d
        elif opCode == 'd':
            if len(command) < 2:
                transferLog("invalid rate.\n", True)
                return False
            rateNew = command[1:]
            transferLog("command: setting download-rate to " + rateNew + "...\n", True)
            self.dow.setDownloadRate(int(rateNew))
            return False

        # r
        elif opCode == 'r':
            if len(command) < 2:
                transferLog("invalid runtime-code.\n", True)
                return False
            runtimeNew = command[1]
            rt = ''
            if runtimeNew == '0':
                rt = 'False'
            elif runtimeNew == '1':
                rt = 'True'
            else:
                transferLog("runtime-code unknown: " + runtimeNew + "\n", True)
                return False
            transferLog("command: setting die-when-done to " + rt + "...\n", True)
            self.autoShutdown = rt
            return False

        # s
        elif opCode == 's':
            if len(command) < 2:
                transferLog("invalid sharekill.\n", True)
                return False
            sharekillNew = command[1:]
            transferLog("command: setting sharekill to " + sharekillNew + "...\n", True)
            self.shareKill = sharekillNew
            return False

        # default
        else:
            transferLog("op-code unknown: " + opCode + "\n", True)
            return False

    def execShutdown(self):
        """ execShutdown """
        transferLog("initializing shutdown...\n", True)
        # write stat
        if self.stoppedAt == '':
            if self.percentDone == '100':
                self.stoppedAt = '100'
            else:
                self.stoppedAt = str((float(self.percentDone)+100)*-1)
                self.timeEst = 'Torrent Stopped'
        self.running = '0'
        self.upRate = ''
        self.downRate = ''
        self.percentDone = self.stoppedAt
        self.writeStatus()
        # quit
        transferLog("tornado shutting down...\n", True)
        raise KeyboardInterrupt

    def writeStatus(self):
        """ writeStatus """
        lcount = 0
        while 1:
            lcount += 1
            try:
                f = open(transferStatFile, 'w')
                f.write(self.running + '\n')
                f.write(self.percentDone + '\n')
                f.write(self.timeEst + '\n')
                f.write(self.downRate + '\n')
                f.write(self.upRate + '\n')
                f.write(self.user + '\n')
                f.write(self.seeds + '+' + self.distcopy + '\n')
                f.write(self.peers + '\n')
                f.write(self.percentShare + '\n')
                f.write(self.shareKill + '\n')
                f.write(str(self.upTotal) + '\n')
                f.write(str(self.downTotal) + '\n')
                f.write(str(self.size))
                f.flush()
                f.close()
                break
            except:
                transferLog("Failed to open stat-file for writing : " + transferStatFile + "\n", True)
                if lcount > 30:
                    break
                pass

#------------------------------------------------------------------------------#
# run                                                                          #
#------------------------------------------------------------------------------#
def run(autoDie, shareKill, userName, params):

    try:

        h = HeadlessDisplayer()
        h.autoShutdown = autoDie
        h.shareKill = shareKill
        h.user = userName

        while 1:
            try:
                config = parse_params(params)
            except ValueError, e:
                print 'error: ' + str(e) + '\nrun with no args for parameter explanations'
                break
            if not config:
                print get_usage()
                break

            # log what we are starting up
            transferLog("tornado starting up :\n", True)
            transferLog(" - torrentfile : " + config['responsefile'] + "\n", True)
            transferLog(" - userName : " + userName + "\n", True)
            transferLog(" - transferStatFile : " + transferStatFile + "\n", True)
            transferLog(" - transferCommandFile : " + transferCommandFile + "\n", True)
            transferLog(" - transferLogFile : " + transferLogFile + "\n", True)
            transferLog(" - transferPidFile : " + transferPidFile + "\n", True)
            transferLog(" - autoDie : " + autoDie + "\n", True)
            transferLog(" - shareKill : " + shareKill + "\n", True)
            transferLog(" - minport : " + str(config['minport']) + "\n", True)
            transferLog(" - maxport : " + str(config['maxport']) + "\n", True)
            transferLog(" - max_upload_rate : " + str(config['max_upload_rate']) + "\n", True)
            transferLog(" - max_download_rate : " + str(config['max_download_rate']) + "\n", True)
            transferLog(" - min_uploads : " + str(config['min_uploads']) + "\n", True)
            transferLog(" - max_uploads : " + str(config['max_uploads']) + "\n", True)
            transferLog(" - min_peers : " + str(config['min_peers']) + "\n", True)
            transferLog(" - max_initiate : " + str(config['max_initiate']) + "\n", True)
            transferLog(" - max_connections : " + str(config['max_connections']) + "\n", True)
            transferLog(" - super_seeder : " + str(config['super_seeder']) + "\n", True)
            transferLog(" - security : " + str(config['security']) + "\n", True)
            transferLog(" - auto_kick : " + str(config['auto_kick']) + "\n", True)
            if 'crypto_allowed' in config:
                transferLog(" - crypto_allowed : " + str(config['crypto_allowed']) + "\n", True)
            if 'crypto_only' in config:
                transferLog(" - crypto_only : " + str(config['crypto_only']) + "\n", True)
            if 'crypto_stealth' in config:
                transferLog(" - crypto_stealth : " + str(config['crypto_stealth']) + "\n", True)
            transferLog(" - priority : " + str(config['priority']) + "\n", True)
            transferLog(" - alloc_type : " + str(config['alloc_type']) + "\n", True)
            transferLog(" - alloc_rate : " + str(config['alloc_rate']) + "\n", True)
            transferLog(" - buffer_reads : " + str(config['buffer_reads']) + "\n", True)
            transferLog(" - write_buffer_size : " + str(config['write_buffer_size']) + "\n", True)
            transferLog(" - check_hashes : " + str(config['check_hashes']) + "\n", True)
            transferLog(" - max_files_open : " + str(config['max_files_open']) + "\n", True)
            transferLog(" - upnp_nat_access : " + str(config['upnp_nat_access']) + "\n", True)

            # remove command-file if exists
            if isfile(transferCommandFile):
                try:
                    transferLog("removing command-file " + transferCommandFile + "...\n", True)
                    remove(transferCommandFile)
                except:
                    pass

            # write pid-file
            currentPid = (str(getpid())).strip()
            transferLog("writing pid-file : " + transferPidFile + " (" + currentPid + ")\n", True)
            try:
                pidFile = open(transferPidFile, 'w')
                pidFile.write(currentPid + "\n")
                pidFile.flush()
                pidFile.close()
            except Exception, e:
                transferLog("Failed to write pid-file, shutting down : " + transferPidFile + " (" + currentPid + ")" + "\n", True)
                break

            myid = createPeerID()
            seed(myid)

            doneflag = Event()
            def disp_exception(text):
                print text
            rawserver = RawServer(doneflag, config['timeout_check_interval'],
                              config['timeout'], ipv6_enable = config['ipv6_enabled'],
                              failfunc = h.failed, errorfunc = disp_exception)
            upnp_type = UPnP_test(config['upnp_nat_access'])
            while True:
                try:
                    listen_port = rawserver.find_and_bind(config['minport'], config['maxport'],
                                config['bind'], ipv6_socket_style = config['ipv6_binds_v4'],
                                upnp = upnp_type, randomizer = config['random_port'])
                    break
                except socketerror, e:
                    if upnp_type and e == UPnP_ERROR:
                        print 'WARNING: COULD NOT FORWARD VIA UPnP'
                        upnp_type = 0
                        continue
                    print "error: Couldn't listen - " + str(e)
                    h.failed()
                    return

            response = get_response(config['responsefile'], config['url'], h.error)
            if not response:
                break

            infohash = sha(bencode(response['info'])).digest()

            h.dow = BT1Download(h.display, h.finished, h.error, disp_exception, doneflag,
                        config, response, infohash, myid, rawserver, listen_port)

            if not h.dow.saveAs(h.chooseFile, h.newpath):
                break

            if not h.dow.initFiles(old_style = True):
                break

            if not h.dow.startEngine():
                h.dow.shutdown()
                break
            h.dow.startRerequester()
            h.dow.autoStats()

            if not h.dow.am_I_finished():
                h.display(activity = 'connecting to peers')

            # log that we are done with startup
            transferLog("tornado up and running.\n", True)

            # listen forever
            rawserver.listen_forever(h.dow.getPortHandler())

            # shutdown
            h.display(activity = 'shutting down')
            h.dow.shutdown()
            break

        try:
            rawserver.shutdown()
        except:
            pass

        if not h.done:
            h.failed()

    finally:
        transferLog("removing pid-file : " + transferPidFile + "\n", True)
        try:
            remove(transferPidFile)
        except:
            transferLog("Failed to remove pid-file : " + transferPidFile + "\n", True)
            pass

#------------------------------------------------------------------------------#
# __main__                                                                     #
#------------------------------------------------------------------------------#
if __name__ == '__main__':
    if argv[1:] == ['--version']:
        print version
        sys.exit(0)

    # check argv-length
    if len(argv) < 5:
        print "Error : missing arguments, exiting. \n"
        sys.exit(0)

    # get/set stat-file
    transferStatFile = argv[4] + ".stat"

    # get/set cmd-file
    transferCommandFile = argv[4] + ".cmd"

    # get/set log-file
    transferLogFile = argv[4] + ".log"

    # get/set pid-file
    transferPidFile = argv[4] + ".pid"

    if PROFILER:
        import profile, pstats
        p = profile.Profile()
        p.runcall(run, argv[1],argv[2],argv[3],argv[5:])
        log = open('profile_data.'+strftime('%y%m%d%H%M%S')+'.txt','a')
        normalstdout = sys.stdout
        sys.stdout = log
        # pstats.Stats(p).strip_dirs().sort_stats('cumulative').print_stats()
        pstats.Stats(p).strip_dirs().sort_stats('time').print_stats()
        sys.stdout = normalstdout
    else:
        run(argv[1],argv[2],argv[3],argv[5:])

    # log exit
    transferLog("tornado exit.\n", True)

