# Copyright(c) 2007. BitTorrent, Inc. All rights reserved.
# author: David Harrison

from ConfigParser import RawConfigParser
from optparse import OptionParser
from BTL.translation import _

class ConfigOptionParser(RawConfigParser, OptionParser):
    def __init__(self, usage, default_section, config_file = None):
        """This is an option parser that reads defaults from a config file.
           It also allows specification of types for each option (unlike our mess
           that is mainline BitTorrent), and is only a slight extension on the
           classes provided in the Python standard libraries (unlike the
           wheel reinvention in mainline).

           @param usage: usage string for this application.
           @param default_section: section in the config file containing configuration
             for this service.  This is a default that can be overriden for
             individual options by passing section as a kwarg to add_option.
        """
        self._default_section = default_section
        OptionParser.__init__(self,usage)
        RawConfigParser.__init__(self)
        if config_file:
            self.read(config_file)

    def add_option(self, *args,**kwargs):
        if 'section' in kwargs:
            section = kwargs['section']
            del kwargs['section']
        else:
            section  = self._default_section
        if "dest" in kwargs:
            if not self.has_option(section, kwargs["dest"]):
                if not kwargs.has_key("default"):
                    raise Exception(
                        _("Your .conf file is invalid.  It does not specify "
                          "a value for %s.\n  %s:\t%s\n") %
                        (kwargs["dest"],kwargs["dest"],kwargs["help"]))
            else:
                if not kwargs.has_key("value_type"):
                    kwargs["default"]=self.get(section, kwargs["dest"])
                else:
                    if kwargs["value_type"] == "float":
                        kwargs["default"] = float(self.get(section, kwargs["dest"] ))
                    elif kwargs["value_type"] == "int":
                        kwargs["default"] = int(self.get(section, kwargs["dest"] ))
                    elif kwargs["value_type"] == "bool":
                        v = self.get(section, kwargs["dest"])
                        if v == "True":
                            kwargs["default"] = True
                        elif v == "False":
                            kwargs["default"] = False
                        else:
                            raise Exception( "Boolean value must be either 'True' or 'False'.")
                    elif kwargs["value_type"] == "str":
                        # canonicalize strings.
                        v = self.get(section, kwargs["dest"])
                        v = v.strip('"').strip()
                        kwargs["default"] = v
                    elif kwargs["value_type"] == "list":
                        v = self.get(section, kwargs["dest"])
                        kwargs["default"] = v.split(",")
                    else:
                        raise Exception( "Option has unrecognized type: %s" % kwargs["value_type"] )
            if kwargs.has_key("value_type"):
                del kwargs["value_type"]
        OptionParser.add_option(self,*args,**kwargs)
