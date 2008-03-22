################################################################################
# $Id$
# $Date$
# $Revision$
################################################################################
#                                                                              #
# LICENSE                                                                      #
#                                                                              #
# This program is free software; you can redistribute it and/or                #
# modify it under the terms of the GNU General Public License (GPL)            #
# as published by the Free Software Foundation; either version 2               #
# of the License, or (at your option) any later version.                       #
#                                                                              #
# This program is distributed in the hope that it will be useful,              #
# but WITHOUT ANY WARRANTY; without even the implied warranty of               #
# MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the                 #
# GNU General Public License for more details.                                 #
#                                                                              #
# To read the license please visit http://www.gnu.org/copyleft/gpl.html        #
#                                                                              #
#                                                                              #
################################################################################
# standard-imports
import sys
import os
# fluazu
from fluazu.output import printMessage, printError, getOutput, printException
from fluazu.TransferFile import TransferFile
from fluazu.StatFile import StatFile
################################################################################

""" ------------------------------------------------------------------------ """
""" Transfer                                                                 """
""" ------------------------------------------------------------------------ """
class Transfer(object):

    """ tf states """
    TF_STOPPED = 0
    TF_RUNNING = 1
    TF_NEW = 2
    TF_QUEUED = 3

    """ azu states """
    AZ_DOWNLOADING = 4
    AZ_ERROR = 8
    AZ_PREPARING = 2
    AZ_QUEUED = 9
    AZ_READY = 3
    AZ_SEEDING = 5
    AZ_STOPPED = 7
    AZ_STOPPING = 6
    AZ_WAITING = 1

    """ azu -> flu map """
    STATE_MAP = { \
        AZ_DOWNLOADING: TF_RUNNING, \
        AZ_ERROR: TF_STOPPED, \
        AZ_PREPARING: TF_RUNNING, \
        AZ_QUEUED: TF_RUNNING, \
        AZ_READY: TF_STOPPED, \
        AZ_SEEDING: TF_RUNNING, \
        AZ_STOPPED: TF_STOPPED, \
        AZ_STOPPING: TF_RUNNING, \
        AZ_WAITING: TF_STOPPED \
    }

    """ -------------------------------------------------------------------- """
    """ __init__                                                             """
    """ -------------------------------------------------------------------- """
    def __init__(self, tf_pathTransfers, flu_pathTransfers, file):
        self.state = Transfer.TF_STOPPED
        self.state_azu = Transfer.AZ_STOPPED
        self.tf_pathTransfers = tf_pathTransfers
        self.flu_pathTransfers = flu_pathTransfers
        self.name = file
        self.fileTorrent = self.tf_pathTransfers + file
        self.fileMeta = self.flu_pathTransfers + file

        # file-vars
        self.fileStat = self.fileTorrent + ".stat"
        self.fileCommand = self.fileTorrent + ".cmd"
        self.fileLog = self.fileTorrent + ".log"
        self.filePid = self.fileTorrent + ".pid"

        # meta-file-object
        self.tf = None

        # stat-object
        self.sf = None

        # initialize
        self.initialize()

    """ -------------------------------------------------------------------- """
    """ initialize                                                           """
    """ -------------------------------------------------------------------- """
    def initialize(self):

        # out
        printMessage("initializing transfer %s ..." % self.name)

        # meta-file
        printMessage("loading transfer-file %s ..." % self.fileMeta)
        self.tf = TransferFile(self.fileMeta)

        # stat-file
        printMessage("loading statfile %s ..." % self.fileStat)
        self.sf = StatFile(self.fileStat)

        # verbose
        printMessage("transfer loaded.")

        # return
        return True

    """ -------------------------------------------------------------------- """
    """ update                                                               """
    """ -------------------------------------------------------------------- """
    def update(self, download):

        # azu-state
        self.state_azu = download.getState()

        # set state
        self.state = Transfer.STATE_MAP[self.state_azu]

        # only when running
        if self.state == Transfer.TF_RUNNING:

            # stat
            self.statRunning(download)

    """ -------------------------------------------------------------------- """
    """ start                                                                """
    """ -------------------------------------------------------------------- """
    def start(self, download):
        self.log("starting transfer %s (%s) ..." % (str(self.name), str(self.tf.transferowner)))

        # stat
        self.statStartup(download)

        # write pid
        self.writePid()

        # start transfer
        try:
            download.restart()
        except:
            self.log("exception when starting transfer :")
            printException()

        # refresh
        download.refresh_object()

        # set state
        self.state = Transfer.STATE_MAP[download.getState()]

        # set rates
        self.setRateU(download, int(self.tf.max_upload_rate))
        self.setRateD(download, int(self.tf.max_download_rate))

        # log
        self.log("transfer started.")

        # return
        return True

    """ -------------------------------------------------------------------- """
    """ stop                                                                 """
    """ -------------------------------------------------------------------- """
    def stop(self, download):
        self.log("stopping transfer %s (%s) ..." % (str(self.name), str(self.tf.transferowner)))

        # stat
        self.statShutdown(download)

        # stop transfer
        retVal = True
        try:
            download.stop()
            retVal = True
        except:
            self.log("exception when stopping transfer :")
            printException()
            retVal = False

        # delete pid
        self.deletePid()

        # log
        self.log("transfer stopped.")

        # states
        self.state = Transfer.TF_STOPPED
        self.state_azu = Transfer.AZ_STOPPED

        # return
        return retVal

    """ -------------------------------------------------------------------- """
    """ isRunning                                                            """
    """ -------------------------------------------------------------------- """
    def isRunning(self):
        return (self.state == Transfer.TF_RUNNING)

    """ -------------------------------------------------------------------- """
    """ processCommandStack                                                  """
    """ -------------------------------------------------------------------- """
    def processCommandStack(self, download):
        if os.path.isfile(self.fileCommand):

            # process file
            self.log("Processing command-file %s ..." % self.fileCommand)
            try:

                # read file to mem
                f = open(self.fileCommand, 'r')
                data = f.read()
                f.close()

                # delete file
                try:
                    os.remove(self.fileCommand)
                except:
                    self.log("Failed to delete command-file : %s" % self.fileCommand)

                # exec commands
                if len(data) > 0:
                    commands = data.split("\n")
                    if len(commands) > 0:
                        for command in commands:
                            if len(command) > 0:
                                # stop reading a quit-command
                                if self.execCommand(download, command):
                                    # stop it
                                    self.stop(download)
                                    # return
                                    return True
                    else:
                        self.log("No commands found.")
                else:
                    self.log("No commands found.")

            except:
                self.log("Failed to read command-file : %s" % self.fileCommand)
        return False

    """ -------------------------------------------------------------------- """
    """ execCommand                                                          """
    """ -------------------------------------------------------------------- """
    def execCommand(self, download, command):

        opCode = command[0]

        # q
        if opCode == 'q':
            self.log("command: stop-request, setting shutdown-flag...")
            return True

        # u
        elif opCode == 'u':
            if len(command) < 2:
                self.log("invalid rate.")
                return False
            rateNew = command[1:]
            self.log("command: setting upload-rate to %s ..." % rateNew)
            # set rate
            if self.setRateU(download, rateNew):
                # update meta-object
                self.tf.max_upload_rate = rateNew
                self.tf.write()
            # return
            return False

        # d
        elif opCode == 'd':
            if len(command) < 2:
                self.log("invalid rate.")
                return False
            rateNew = command[1:]
            self.log("command: setting download-rate to %s ..." % rateNew)
            # set rate
            if self.setRateD(download, rateNew):
                # update meta-object
                self.tf.max_download_rate = rateNew
                self.tf.write()
            # return
            return False

        # r
        elif opCode == 'r':
            if len(command) < 2:
                self.log("invalid runtime-code.")
                return False
            runtimeNew = command[1]
            rt = ''
            if runtimeNew == '0':
                rt = 'False'
            elif runtimeNew == '1':
                rt = 'True'
            else:
                self.log("runtime-code unknown: %s" % runtimeNew)
                return False
            self.log("command: setting die-when-done to %s" % rt)
            # update meta-object
            self.tf.die_when_done = rt
            self.tf.write()
            return False

        # s
        elif opCode == 's':
            if len(command) < 2:
                self.log("invalid sharekill.")
                return False
            sharekillNew = command[1:]
            self.log("command: setting sharekill to %s ..." % sharekillNew)
            # update meta-object
            self.tf.sharekill = sharekillNew
            self.tf.write()
            return False

        # default
        else:
            self.log("op-code unknown: %s" % opCode)
            return False

        return False

    """ -------------------------------------------------------------------- """
    """ setRateU                                                             """
    """ -------------------------------------------------------------------- """
    def setRateU(self, download, rate):
        try:
            download.setUploadRateLimitBytesPerSecond((int(rate) << 10))
            return True
        except:
            printMessage("Failed to set upload-rate.")
            printException()
            return False

    """ -------------------------------------------------------------------- """
    """ setRateD                                                             """
    """ -------------------------------------------------------------------- """
    def setRateD(self, download, rate):
        try:
            download.setMaximumDownloadKBPerSecond(int(rate))
            return True
        except:
            printMessage("Failed to set download-rate.")
            printException()
            return False

    """ -------------------------------------------------------------------- """
    """ statStartup                                                          """
    """ -------------------------------------------------------------------- """
    def statStartup(self, download):
        # set some values
        self.sf.running = Transfer.TF_RUNNING
        self.sf.percent_done = 0
        self.sf.time_left = "Starting..."
        self.sf.down_speed = "0.00 kB/s"
        self.sf.up_speed = "0.00 kB/s"
        self.sf.transferowner = self.tf.transferowner
        self.sf.seeds = ""
        self.sf.peers = ""
        self.sf.sharing = ""
        self.sf.seedlimit = ""
        self.sf.uptotal = 0
        self.sf.downtotal = 0
        try:
            # get size
            try:
                size = str(download.getTorrent().getSize())
                self.sf.size = size
            except:
                printException()
            # write
            return self.sf.write()
        except:
            printException()
            return False

    """ -------------------------------------------------------------------- """
    """ statRunning                                                          """
    """ -------------------------------------------------------------------- """
    def statRunning(self, download):

        # die-when-done
        if self.state_azu == Transfer.AZ_SEEDING and self.tf.die_when_done.lower() == 'true':
            self.log("die-when-done set, setting shutdown-flag...")
            self.stop(download)
            return

        # set some values
        self.sf.running = Transfer.TF_RUNNING
        try:
            try:

                # stats
                if download == None:
                    return
                stats = download.getStats()
                if stats == None:
                    return

                # die-on-seed-limit
                if self.state_azu == Transfer.AZ_SEEDING:
                    sk = float(self.tf.sharekill)
                    if sk > 0:
                        try:
                            shareRatio = (float(stats.getShareRatio())) / 10
                            if shareRatio >= sk:
                                self.log("seed-limit %s reached (%s), setting shutdown-flag..." % (self.tf.sharekill, str(shareRatio)))
                                self.stop(download)
                                return
                        except:
                            printException()

                # completed
                try:
                    pctf = (float(stats.getCompleted())) / 10
                    self.sf.percent_done = str(pctf)
                except:
                    printException()

                # time_left
                try:
                    self.sf.time_left = str(stats.getETA())
                except:
                    self.sf.time_left = '-'

                # down_speed
                try:
                    self.sf.down_speed = "%.1f kB/s" % ((float(stats.getDownloadAverage())) / 1024)
                except:
                    printException()

                # up_speed
                try:
                    self.sf.up_speed = "%.1f kB/s" % ((float(stats.getUploadAverage())) / 1024)
                except:
                    printException()

                # uptotal
                try:
                    self.sf.uptotal = str(stats.getUploaded())
                except:
                    printException()

                # downtotal
                try:
                    self.sf.downtotal = str(stats.getDownloaded())
                except:
                    printException()

            except:
                printException()

            # hosts
            try:
                ps = download.getPeerManager().getStats()
                scrape = download.getLastScrapeResult()

                # seeds
                try:
                    countS = int(scrape.getSeedCount())
                    if (countS < 0):
                        countS = 0
                    countSC = int(ps.getConnectedSeeds())
                    if (countSC < 0):
                        countSC = 0
                    self.sf.seeds = "%d (%d)" % (countSC, countS)
                except:
                    printException()

                # peers
                try:
                    countP = int(scrape.getNonSeedCount())
                    if (countP < 0):
                        countP = 0
                    countPC = int(ps.getConnectedLeechers())
                    if (countPC < 0):
                        countPC = 0
                    self.sf.peers = "%d (%d)" % (countPC, countP)
                except:
                    printException()

            except:
                printException()

            # write
            return self.sf.write()

        except:
            printException()
            return False

    """ -------------------------------------------------------------------- """
    """ statShutdown                                                         """
    """ -------------------------------------------------------------------- """
    def statShutdown(self, download, error = None):

        # set some values
        self.sf.running = Transfer.TF_STOPPED
        self.sf.down_speed = "0.00 kB/s"
        self.sf.up_speed = "0.00 kB/s"
        self.sf.transferowner = self.tf.transferowner
        self.sf.seeds = ""
        self.sf.peers = ""
        self.sf.sharing = ""
        self.sf.seedlimit = ""
        try:

            # stats
            try:
                stats = download.getStats()

                # done
                if download.isComplete():
                    self.sf.percent_done = 100
                    self.sf.time_left = "Download Succeeded!"

                # not done
                else:
                    try:
                        pctf = float(stats.getCompleted())
                        pctf /= 10
                        pcts = "-" + str(pctf)
                        pctf = float(pcts)
                        pctf -= 100
                        self.sf.percent_done = str(pctf)
                    except:
                        printException()
                    self.sf.time_left = "Transfer Stopped"

                # uptotal
                try:
                    self.sf.uptotal = str(stats.getUploaded())
                except:
                    printException()

                # downtotal
                try:
                    self.sf.downtotal = str(stats.getDownloaded())
                except:
                    printException()
            except:
                printException()

            # size
            try:
                self.sf.size = str(download.getTorrent().getSize())
            except:
                printException()

            # error
            if error is not None:
                self.sf.time_left = "Error: %s" % error

            # write
            return self.sf.write()

        except:
            printException()
            return False

    """ -------------------------------------------------------------------- """
    """ writePid                                                             """
    """ -------------------------------------------------------------------- """
    def writePid(self):
        self.log("writing pid-file %s " % self.filePid)
        try:
            pidFile = open(self.filePid, 'w')
            pidFile.write("0\n")
            pidFile.flush()
            pidFile.close()
            return True
        except Exception, e:
            self.log("Failed to write pid-file %s" % self.filePid)
            return False

    """ -------------------------------------------------------------------- """
    """ deletePid                                                            """
    """ -------------------------------------------------------------------- """
    def deletePid(self):
        self.log("deleting pid-file %s " % self.filePid)
        try:
            os.remove(self.filePid)
            return True
        except Exception, e:
            self.log("Failed to delete pid-file %s" % self.filePid)
            return False

    """ -------------------------------------------------------------------- """
    """ log                                                                  """
    """ -------------------------------------------------------------------- """
    def log(self, message):
        printMessage(message)
        try:
            f = open(self.fileLog, "a+")
            f.write(getOutput(message))
            f.flush()
            f.close()
        except Exception, e:
            printError("Failed to write log-file %s" % self.fileLog)
