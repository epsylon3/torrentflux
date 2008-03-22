

# Author: David Harrison


# This is unicode interface to the platform module.

from BitTorrent import platform
from BTL.platform import efs2

#get_filesystem_encoding   = platform.get_filesystem_encoding
#encode_for_filesystem     = platform.encode_for_filesystem
#decode_from_filesystem    = platform.decode_from_filesystem
#set_config_dir            = platform.set_config_dir
calc_unix_dirs            = platform.calc_unix_dirs
get_free_space            = platform.get_free_space
get_sparse_files_support  = platform.get_sparse_files_support
is_path_too_long          = platform.is_path_too_long
is_sparse                 = platform.is_sparse
get_allocated_regions     = platform.get_allocated_regions
get_max_filesize          = platform.get_max_filesize
create_shortcut           = platform.create_shortcut
remove_shortcut           = platform.remove_shortcut
enforce_shortcut          = platform.enforce_shortcut
enforce_association       = platform.enforce_association
btspawn                   = platform.btspawn
spawn                     = platform.spawn
#get_language              = platform.get_language
smart_gettext_and_install = platform.smart_gettext_and_install
#read_language_file        = platform.read_language_file
#write_language_file       = platform.write_language_file
#install_translation       = platform.install_translation
write_pid_file            = platform.write_pid_file           

#old_open = open
#def open(name, mode='r'):
#     return old_open(efs2(name), mode)
#
#
#def language_path():
#  return decode_from_filesystem(platform.language_path())
#
#def get_dir_root(shellvars, default_to_home=True):
#  return decode_from_filesystem(
#    platform.get_dir_root(shellvars, default_to_home))

def get_temp_dir():
  return decode_from_filesystem(platform.get_temp_dir())

def get_temp_subdir():
  return decode_from_filesystem(platform.get_temp_subdir())

#def get_config_dir():
#  return decode_from_filesystem(platform.get_config_dir())
#
#def get_old_dot_dir():
#  return decode_from_filesystem(platform.get_old_dot_dir())
#
#def get_dot_dir():
#  return decode_from_filesystem(platform.get_dot_dir())
#
#def get_cache_dir():
#  return decode_from_filesystem(platform.get_cache_dir())

def get_home_dir():
  return decode_from_filesystem(platform.get_home_dir())

def get_local_data_dir():
  return decode_from_filesystem(platform.get_local_data_dir())

def get_old_incomplete_data_dir():
  return decode_from_filesystem(platform.get_old_incomplete_data_dir())

def get_incomplete_data_dir():
  return decode_from_filesystem(platform.get_incomplete_data_dir())

def get_save_dir():
  return decode_from_filesystem(platform.get_save_dir())

def get_shell_dir(value):
  return decode_from_filesystem(platform.get_shell_dir(value))

def get_startup_dir():
  return decode_from_filesystem(platform.get_startup_dir())
