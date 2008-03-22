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
import os
# fluazu
from fluazu.output import printError
################################################################################

""" ------------------------------------------------------------------------ """
""" TransferFile                                                             """
""" ------------------------------------------------------------------------ """
class TransferFile(object):

    """ -------------------------------------------------------------------- """
    """ __init__                                                             """
    """ -------------------------------------------------------------------- """
    def __init__(self, file):

        # file
        self.file = file

        # fields
        self.transferowner = ""
        self.savepath = ""
        self.max_upload_rate = ""
        self.max_download_rate = ""
        self.max_uploads = ""
        self.superseeder = ""
        self.die_when_done = ""
        self.sharekill = ""
        self.minport = ""
        self.maxport = ""
        self.maxcons = ""
        self.rerequest = ""

        # init
        if self.file is not '':
            self.initialize(self.file)

    """ -------------------------------------------------------------------- """
    """ initialize                                                           """
    """ -------------------------------------------------------------------- """
    def initialize(self, file):

        # file
        self.file = file

        # read in transfer-file + set fields
        if os.path.isfile(self.file):
            try:

                # read file to mem
                f = open(self.file, 'r')
                data = f.read()
                f.close()

                # set fields
                content = data.split("\n")
                if len(content) > 11:
                    self.transferowner = content[0]
                    self.savepath = content[1]
                    self.max_upload_rate = content[2]
                    self.max_download_rate = content[3]
                    self.max_uploads = content[4]
                    self.superseeder = content[5]
                    self.die_when_done = content[6]
                    self.sharekill = content[7]
                    self.minport = content[8]
                    self.maxport = content[9]
                    self.maxcons = content[10]
                    self.rerequest = content[11]
                    return True
                else:
                    printError("Failed to parse transfer-file %s " % self.file)

            except:
                printError("Failed to read transfer-file %s " % self.file)
        return False

    """ -------------------------------------------------------------------- """
    """ write                                                                """
    """ -------------------------------------------------------------------- """
    def write(self):

        # write transfer-file
        try:
            f = open(self.file, 'w')
            f.write(str(self.transferowner) + '\n')
            f.write(str(self.savepath) + '\n')
            f.write(str(self.max_upload_rate) + '\n')
            f.write(str(self.max_download_rate) + '\n')
            f.write(str(self.max_uploads) + '\n')
            f.write(str(self.superseeder) + '\n')
            f.write(str(self.die_when_done) + '\n')
            f.write(str(self.sharekill) + '\n')
            f.write(str(self.minport) + '\n')
            f.write(str(self.maxport) + '\n')
            f.write(str(self.maxcons) + '\n')
            f.write(str(self.rerequest))
            f.flush()
            f.close()
            return True
        except:
            printError("Failed to write transfer-file %s " % self.file)
        return False
