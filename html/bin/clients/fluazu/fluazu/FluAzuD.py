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
import time
# fluazu
from fluazu.output import printMessage, printError, printException
from fluazu.Transfer import Transfer
# dopal
from dopal.main import make_connection
from dopal.errors import LinkError
import dopal.aztypes
################################################################################

""" ------------------------------------------------------------------------ """
""" FluAzuD                                                                  """
""" ------------------------------------------------------------------------ """
class FluAzuD(object):

    """ class-fields """
    MAX_RECONNECT_TRIES = 5

    """ -------------------------------------------------------------------- """
    """ __init__                                                             """
    """ -------------------------------------------------------------------- """
    def __init__(self):
        self.running = 1
        self.transfers = []
        self.downloads = {}
        self.pid = '0'

        # tf-settings
        self.tf_path = ''
        self.tf_pathTransfers = ''

        # flu-settings
        self.flu_path = ''
        self.flu_pathTransfers = ''
        self.flu_pathTransfersRun = ''
        self.flu_pathTransfersDel = ''
        self.flu_fileCommand = ''
        self.flu_filePid = ''
        self.flu_fileStat = ''

        # azu-settings
        self.azu_host = '127.0.0.1'
        self.azu_port = 6884
        self.azu_secure = False
        self.azu_user = ''
        self.azu_pass = ''
        self.azu_version_str = ''

        # dopal
        self.connection = None
        self.interface = None
        self.dm = None

    """ -------------------------------------------------------------------- """
    """ run                                                                  """
    """ -------------------------------------------------------------------- """
    def run(self, path, host, port, secure, username, password):
        printMessage("fluazu starting up:")

        # set vars
        self.tf_path = path
        self.tf_pathTransfers = self.tf_path + '.transfers/'
        self.flu_path = self.tf_path + '.fluazu/'
        self.flu_fileCommand = self.flu_path + 'fluazu.cmd'
        self.flu_filePid = self.flu_path + 'fluazu.pid'
        self.flu_fileStat = self.flu_path + 'fluazu.stat'
        self.flu_pathTransfers = self.flu_path + 'cur/'
        self.flu_pathTransfersRun = self.flu_path + 'run/'
        self.flu_pathTransfersDel = self.flu_path + 'del/'
        self.azu_host = host
        self.azu_port = int(port)
        if secure == '1':
            self.azu_secure = True
        else:
            self.azu_secure = False
        self.azu_user = username
        self.azu_pass = password

        # more vars
        printMessage("flu-path: %s" % str(self.flu_path))
        printMessage("azu-host: %s" % str(self.azu_host))
        printMessage("azu-port: %s" % str(self.azu_port))
        printMessage("azu-secure: %s" % str(self.azu_secure))
        if len(self.azu_user) > 0:
            printMessage("azu-user: %s" % str(self.azu_user))
            printMessage("azu-pass: %s" % str(self.azu_pass))

        # initialize
        if not self.initialize():
            printError("there were problems initializing fluazu, shutting down...")
            self.shutdown()
            return 1

        # main
        return self.main()

    """ -------------------------------------------------------------------- """
    """ initialize                                                           """
    """ -------------------------------------------------------------------- """
    def initialize(self):

        # flu

        # check dirs
        if not self.checkDirs():
            printError("Error checking dirs. path: %s" % self.tf_path)
            return False

        # write pid-file
        self.pid = (str(os.getpid())).strip()
        printMessage("writing pid-file %s (%s)" % (self.flu_filePid, self.pid))
        try:
            pidFile = open(self.flu_filePid, 'w')
            pidFile.write(self.pid + "\n")
            pidFile.flush()
            pidFile.close()
        except:
            printError("Failed to write pid-file %s (%s)" % (self.flu_filePid, self.pid))
            return False

        # delete command-file if exists
        if os.path.isfile(self.flu_fileCommand):
            try:
                printMessage("removing command-file %s ..." % self.flu_fileCommand)
                os.remove(self.flu_fileCommand)
            except:
                printError("Failed to delete commandfile %s" % self.flu_fileCommand)
                return False

        # load transfers
        self.loadTransfers()

        # azu
        printMessage("connecting to Azureus-Server (%s:%d)..." % (self.azu_host, self.azu_port))

        # set connection details
        connection_details = {}
        connection_details['host'] = self.azu_host
        connection_details['port'] = self.azu_port
        connection_details['secure'] = self.azu_secure
        if len(self.azu_user) > 0:
            connection_details['user'] = self.azu_user
            connection_details['password'] = self.azu_pass

        # make connection
        try:
            self.connection = make_connection(**connection_details)
            self.connection.is_persistent_connection = True
            self.interface = self.connection.get_plugin_interface()
        except:
            printError("could not connect to Azureus-Server")
            printException()
            return False

        # azureus version
        self.azu_version_str = str(self.connection.get_azureus_version())
        self.azu_version_str = self.azu_version_str.replace(", ", ".")
        self.azu_version_str = self.azu_version_str.replace("(", "")
        self.azu_version_str = self.azu_version_str.replace(")", "")
        printMessage("connected. Azureus-Version: %s" % self.azu_version_str)

        # download-manager
        self.dm = self.interface.getDownloadManager()
        if self.dm is None:
            printError("Error getting Download-Manager object")
            return False

        # write stat-file and return
        return self.writeStatFile()

    """ -------------------------------------------------------------------- """
    """ shutdown                                                             """
    """ -------------------------------------------------------------------- """
    def shutdown(self):
        printMessage("fluazu shutting down...")

        # delete stat-file if exists
        if os.path.isfile(self.flu_fileStat):
            try:
                printMessage("deleting stat-file %s ..." % self.flu_fileStat)
                os.remove(self.flu_fileStat)
            except:
                printError("Failed to delete stat-file %s " % self.flu_fileStat)

        # delete pid-file if exists
        if os.path.isfile(self.flu_filePid):
            try:
                printMessage("deleting pid-file %s ..." % self.flu_filePid)
                os.remove(self.flu_filePid)
            except:
                printError("Failed to delete pid-file %s " % self.flu_filePid)

    """ -------------------------------------------------------------------- """
    """ main                                                                 """
    """ -------------------------------------------------------------------- """
    def main(self):

        # main-loop
        while self.running > 0:

            # check if connection still valid, shutdown if it is not
            if not self.checkAzuConnection():
                # shutdown
                self.shutdown()
                # return
                return 1

            # update downloads
            self.updateDownloads()

            # update transfers
            for transfer in self.transfers:
                if transfer.name in self.downloads:
                    # update
                    transfer.update(self.downloads[transfer.name])

            # inner loop
            for i in range(4):

                # process daemon command stack
                if self.processCommandStack():
                    # shutdown
                    self.running = 0
                    break;

                # process transfers command stacks
                for transfer in self.transfers:
                    if transfer.isRunning():
                        if transfer.processCommandStack(self.downloads[transfer.name]):
                            # update downloads
                            self.updateDownloads()

                # sleep
                time.sleep(1)

        # shutdown
        self.shutdown()

        # return
        return 0

    """ -------------------------------------------------------------------- """
    """ reload                                                               """
    """ -------------------------------------------------------------------- """
    def reload(self):
        printMessage("reloading...")

        # delete-requests
        self.processDeleteRequests()

        # run-requests
        self.processRunRequests()

        # transfers
        self.loadTransfers()

    """ -------------------------------------------------------------------- """
    """ processDeleteRequests                                                """
    """ -------------------------------------------------------------------- """
    def processDeleteRequests(self):
        printMessage("processing delete-requests...")

        # read requests
        requests = []
        try:
            for fileName in os.listdir(self.flu_pathTransfersDel):
                # add
                requests.append(fileName)
                # del file
                delFile = self.flu_pathTransfersDel + fileName
                try:
                    os.remove(delFile)
                except:
                    printError("Failed to delete file : %s" % delFile)
        except:
            return False

        # process requests
        if len(requests) > 0:
            for fileName in requests:
                printMessage("deleting %s ..." % fileName)
                # update downloads
                self.downloads = {}
                self.updateDownloads()
                # remove if needed
                if fileName in self.downloads:
                    # remove transfer
                    self.removeTransfer(fileName)
                # del file
                delFile = self.flu_pathTransfers + fileName
                try:
                    os.remove(delFile)
                except:
                    printError("Failed to delete file : %s" % delFile)

        # return
        return True

    """ -------------------------------------------------------------------- """
    """ processRunRequests                                                   """
    """ -------------------------------------------------------------------- """
    def processRunRequests(self):
        printMessage("processing run-requests...")

        # read requests
        requests = []
        try:
            for fileName in os.listdir(self.flu_pathTransfersRun):
                inputFile = self.flu_pathTransfersRun + fileName
                outputFile = self.flu_pathTransfers + fileName
                # move file + add to requests
                try:
                    # read file to mem
                    f = open(inputFile, 'r')
                    data = f.read()
                    f.close()
                    # delete
                    os.remove(inputFile)
                    # write file
                    f = open(outputFile, 'w')
                    f.write(data)
                    f.flush()
                    f.close()
                    # add
                    requests.append(fileName)
                except:
                    printError("Failed to move file : %s" % inputFile)
        except:
            return False

        # process requests
        if len(requests) > 0:
            try:
                # update downloads
                self.downloads = {}
                self.updateDownloads()
                for fileName in requests:
                    # add if needed
                    if fileName not in self.downloads:
                        try:
                            # add
                            self.addTransfer(fileName)
                        except:
                            printError("exception when adding new transfer %s" % fileName)
                            raise
                    # downloads
                    tries = 0
                    while tries < 5 and fileName not in self.downloads:
                        #if fileName not in self.downloads:
                        printMessage("download %s missing, update downloads..." % fileName)
                        self.updateDownloads()
                        # sleep + increment
                        time.sleep(1)
                        tries += 1
                    # start transfer
                    if fileName in self.downloads:
                        try:
                            transfer = Transfer(self.tf_pathTransfers, self.flu_pathTransfers, fileName)
                            transfer.start(self.downloads[fileName])
                        except:
                            printError("exception when starting new transfer %s" % fileName)
                            raise
                    else:
                        printError("download %s not in azureus-downloads, cannot start it." % fileName)
            except:
                printMessage("exception when processing run-requests:")
                printException()

        # return
        return True

    """ -------------------------------------------------------------------- """
    """ addTransfer                                                          """
    """ -------------------------------------------------------------------- """
    def addTransfer(self, tname):
        printMessage("adding new transfer %s ..." % tname)
        try:
            # transfer-object
            transfer = Transfer(self.tf_pathTransfers, self.flu_pathTransfers, tname)

            # torrent-object
            torrent = self.interface.getTorrentManager().createFromBEncodedFile(transfer.fileTorrent)

            # file-objects
            fileSource = dopal.aztypes.wrap_file(transfer.fileTorrent)
            fileTarget = dopal.aztypes.wrap_file(transfer.tf.savepath)

            # add
            self.dm.addDownload(torrent, fileSource, fileTarget)

            # return
            return True
        except:
            printMessage("exception when adding transfer:")
            printException()
            return False

    """ -------------------------------------------------------------------- """
    """ removeTransfer                                                       """
    """ -------------------------------------------------------------------- """
    def removeTransfer(self, tname):
        printMessage("removing transfer %s ..." % tname)
        try:
            self.downloads[tname].remove()
            return True
        except:
            printMessage("exception when removing transfer:")
            printException()
            return False

    """ -------------------------------------------------------------------- """
    """ loadTransfers                                                        """
    """ -------------------------------------------------------------------- """
    def loadTransfers(self):
        printMessage("loading transfers...")
        self.transfers = []
        try:
            for fileName in os.listdir(self.flu_pathTransfers):
                self.transfers.append(Transfer(self.tf_pathTransfers, self.flu_pathTransfers, fileName))
            return True
        except:
            return False

    """ -------------------------------------------------------------------- """
    """ updateDownloads                                                      """
    """ -------------------------------------------------------------------- """
    def updateDownloads(self):
        azu_dls = self.dm.getDownloads()
        for download in azu_dls:
            tfile = (os.path.split(str(download.getTorrentFileName())))[1]
            self.downloads[tfile] = download

    """ -------------------------------------------------------------------- """
    """ processCommandStack                                                  """
    """ -------------------------------------------------------------------- """
    def processCommandStack(self):
        if os.path.isfile(self.flu_fileCommand):

            # process file
            printMessage("Processing command-file %s ..." % self.flu_fileCommand)
            try:

                # read file to mem
                try:
                    f = open(self.flu_fileCommand, 'r')
                    data = f.read()
                    f.close()
                except:
                    printError("Failed to read command-file : %s" % self.flu_fileCommand)
                    raise

                # delete file
                try:
                    os.remove(self.flu_fileCommand)
                except:
                    printError("Failed to delete command-file : %s" % self.flu_fileCommand)

                # exec commands
                if len(data) > 0:
                    commands = data.split("\n")
                    if len(commands) > 0:
                        for command in commands:
                            if len(command) > 0:
                                try:
                                    # exec, early out when reading a quit-command
                                    if self.execCommand(command):
                                        return True
                                except:
                                    printError("Failed to exec command: %s" % command)
                    else:
                        printMessage("No commands found.")
                else:
                    printMessage("No commands found.")

            except:
                printError("Failed to process command-stack : %s" % self.flu_fileCommand)
        return False

    """ -------------------------------------------------------------------- """
    """ execCommand                                                          """
    """ -------------------------------------------------------------------- """
    def execCommand(self, command):

        # op-code
        opCode = command[0]

        # q
        if opCode == 'q':
            printMessage("command: stop-request, setting shutdown-flag...")
            return True

        # r
        elif opCode == 'r':
            printMessage("command: reload-request, reloading...")
            self.reload()
            return False

        # u
        elif opCode == 'u':
            if len(command) < 2:
                printMessage("invalid rate.")
                return False
            rateNew = command[1:]
            printMessage("command: setting upload-rate to %s ..." % rateNew)
            self.setRateU(int(rateNew))
            return False

        # d
        elif opCode == 'd':
            if len(command) < 2:
                printMessage("invalid rate.")
                return False
            rateNew = command[1:]
            printMessage("command: setting download-rate to %s ..." % rateNew)
            self.setRateD(int(rateNew))
            return False

        # s
        elif opCode == 's':
            try:
                if len(command) < 3:
                    raise
                workLoad = command[1:]
                sets = workLoad.split(":")
                setKey = sets[0]
                setVal = sets[1]
                if len(setKey) < 1 or len(setVal) < 1:
                    raise
                printMessage("command: changing setting %s to %s ..." % (setKey, setVal))
                if self.changeSetting(setKey, setVal):
                    self.writeStatFile()
                return False
            except:
                printMessage("invalid setting.")
                return False

        # default
        else:
            printMessage("op-code unknown: %s" % opCode)
            return False

    """ -------------------------------------------------------------------- """
    """ checkDirs                                                            """
    """ -------------------------------------------------------------------- """
    def checkDirs(self):

        # tf-paths
        if not os.path.isdir(self.tf_path):
            printError("Invalid path-dir: %s" % self.tf_path)
            return False
        if not os.path.isdir(self.tf_pathTransfers):
            printError("Invalid tf-transfers-dir: %s" % self.tf_pathTransfers)
            return False

        # flu-paths
        if not os.path.isdir(self.flu_path):
            try:
                printMessage("flu-main-path %s does not exist, trying to create ..." % self.flu_path)
                os.mkdir(self.flu_path, 0700)
                printMessage("done.")
            except:
                printError("Failed to create flu-main-path %s" % self.flu_path)
                return False
        if not os.path.isdir(self.flu_pathTransfers):
            try:
                printMessage("flu-transfers-path %s does not exist, trying to create ..." % self.flu_pathTransfers)
                os.mkdir(self.flu_pathTransfers, 0700)
                printMessage("done.")
            except:
                printError("Failed to create flu-main-path %s" % self.flu_pathTransfers)
                return False
        if not os.path.isdir(self.flu_pathTransfersRun):
            try:
                printMessage("flu-transfers-run-path %s does not exist, trying to create ..." % self.flu_pathTransfersRun)
                os.mkdir(self.flu_pathTransfersRun, 0700)
                printMessage("done.")
            except:
                printError("Failed to create flu-main-path %s" % self.flu_pathTransfersRun)
                return False
        if not os.path.isdir(self.flu_pathTransfersDel):
            try:
                printMessage("flu-transfers-del-path %s does not exist, trying to create ..." % self.flu_pathTransfersDel)
                os.mkdir(self.flu_pathTransfersDel, 0700)
                printMessage("done.")
            except:
                printError("Failed to create flu-main-path %s" % self.flu_pathTransfersDel)
                return False

        # return
        return True

    """ -------------------------------------------------------------------- """
    """ changeSetting                                                        """
    """ -------------------------------------------------------------------- """
    def changeSetting(self, key, val):
        try:

            # get plugin-config
            config_object = self.interface.getPluginconfig()

            # core-keys
            coreKeys = { \
                'CORE_PARAM_INT_MAX_ACTIVE': config_object.CORE_PARAM_INT_MAX_ACTIVE, \
                'CORE_PARAM_INT_MAX_ACTIVE_SEEDING': config_object.CORE_PARAM_INT_MAX_ACTIVE_SEEDING, \
                'CORE_PARAM_INT_MAX_CONNECTIONS_GLOBAL': config_object.CORE_PARAM_INT_MAX_CONNECTIONS_GLOBAL, \
                'CORE_PARAM_INT_MAX_CONNECTIONS_PER_TORRENT': config_object.CORE_PARAM_INT_MAX_CONNECTIONS_PER_TORRENT, \
                'CORE_PARAM_INT_MAX_DOWNLOAD_SPEED_KBYTES_PER_SEC': config_object.CORE_PARAM_INT_MAX_DOWNLOAD_SPEED_KBYTES_PER_SEC, \
                'CORE_PARAM_INT_MAX_DOWNLOADS': config_object.CORE_PARAM_INT_MAX_DOWNLOADS, \
                'CORE_PARAM_INT_MAX_UPLOAD_SPEED_KBYTES_PER_SEC': config_object.CORE_PARAM_INT_MAX_UPLOAD_SPEED_KBYTES_PER_SEC, \
                'CORE_PARAM_INT_MAX_UPLOAD_SPEED_SEEDING_KBYTES_PER_SEC': config_object.CORE_PARAM_INT_MAX_UPLOAD_SPEED_SEEDING_KBYTES_PER_SEC, \
                'CORE_PARAM_INT_MAX_UPLOADS': config_object.CORE_PARAM_INT_MAX_UPLOADS, \
                'CORE_PARAM_INT_MAX_UPLOADS_SEEDING': config_object.CORE_PARAM_INT_MAX_UPLOADS_SEEDING \
            }
            if key not in coreKeys:
                printMessage("settings-key unknown: %s" % key)
                return False

            # change setting
            try:
                config_object.setIntParameter(coreKeys[key], int(val))
                return True
            except:
                printMessage("Failed to change setting %s to %s" % (key, val))
                printException()
                return False

        except:
            printMessage("Failed to get Plugin-Config.")
            printException()
        return False

    """ -------------------------------------------------------------------- """
    """ writeStatFile                                                        """
    """ -------------------------------------------------------------------- """
    def writeStatFile(self):
        try:

            # get plugin-config
            config_object = self.interface.getPluginconfig()

            # get vars
            coreVars = [ \
                config_object.CORE_PARAM_INT_MAX_ACTIVE, \
                config_object.CORE_PARAM_INT_MAX_ACTIVE_SEEDING, \
                config_object.CORE_PARAM_INT_MAX_CONNECTIONS_GLOBAL, \
                config_object.CORE_PARAM_INT_MAX_CONNECTIONS_PER_TORRENT, \
                config_object.CORE_PARAM_INT_MAX_DOWNLOAD_SPEED_KBYTES_PER_SEC, \
                config_object.CORE_PARAM_INT_MAX_DOWNLOADS, \
                config_object.CORE_PARAM_INT_MAX_UPLOAD_SPEED_KBYTES_PER_SEC, \
                config_object.CORE_PARAM_INT_MAX_UPLOAD_SPEED_SEEDING_KBYTES_PER_SEC, \
                config_object.CORE_PARAM_INT_MAX_UPLOADS, \
                config_object.CORE_PARAM_INT_MAX_UPLOADS_SEEDING \
            ]
            coreParams = {}
            for coreVar in coreVars:
                try:
                    coreParams[coreVar] = config_object.getIntParameter(coreVar, 0)
                except:
                    coreParams[coreVar] = 0
                    printException()

            # write file
            try:
                f = open(self.flu_fileStat, 'w')
                f.write("%s\n" % self.azu_host)
                f.write("%d\n" % self.azu_port)
                f.write("%s\n" % self.azu_version_str)
                for coreVar in coreVars:
                    f.write("%d\n" % coreParams[coreVar])
                f.flush()
                f.close()
                return True
            except:
                printError("Failed to write statfile %s " % self.flu_fileStat)
                printException()

        except:
            printMessage("Failed to get Plugin-Config.")
            printException()
        return False

    """ -------------------------------------------------------------------- """
    """ setRateU                                                             """
    """ -------------------------------------------------------------------- """
    def setRateU(self, rate):
        try:
            config_object = self.interface.getPluginconfig()
            config_object.set_upload_speed_limit(rate)
            return True
        except:
            printMessage("Failed to set upload-rate.")
            printException()
            return False

    """ -------------------------------------------------------------------- """
    """ setRateD                                                             """
    """ -------------------------------------------------------------------- """
    def setRateD(self, rate):
        try:
            config_object = self.interface.getPluginconfig()
            config_object.set_download_speed_limit(rate)
            return True
        except:
            printMessage("Failed to set download-rate.")
            printException()
            return False

    """ -------------------------------------------------------------------- """
    """ checkAzuConnection                                                   """
    """ -------------------------------------------------------------------- """
    def checkAzuConnection(self):

        # con valid
        try:
            if self.connection.is_connection_valid():
                return True
            else:
                raise

        # con not valid
        except:

            # out
            printMessage("connection to Azureus-server lost, reconnecting to %s:%d ..." % (self.azu_host, self.azu_port))

            # try to reconnect
            for i in range(FluAzuD.MAX_RECONNECT_TRIES):

                # sleep
                time.sleep(i << 2)

                # out
                printMessage("reconnect-try %d ..." % (i + 1))

                # establish con
                try:
                    self.connection.establish_connection(True)
                    printMessage("established connection to Azureus-server")
                except:
                    printError("Error establishing connection to Azureus-server")
                    printException()
                    continue

                # interface
                try:
                    self.interface = self.connection.get_plugin_interface()
                except LinkError, error:
                    printError("Error getting interface object")
                    printException()
                    self.interface = None
                    continue

                # download-manager
                try:
                    self.dm = None
                    self.dm = self.interface.getDownloadManager()
                    if self.dm is None:
                        raise
                    else:
                        return True
                except:
                    printError("Error getting Download-Manager object")
                    continue

            # seems like azu is down. give up
            printError("no connection after %d tries, i give up, azu is gone" % FluAzuD.MAX_RECONNECT_TRIES)
            return False
