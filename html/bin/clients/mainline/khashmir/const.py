# The contents of this file are subject to the BitTorrent Open Source License
# Version 1.1 (the License).  You may not copy or use this file, in either
# source code or executable form, except in compliance with the License.  You
# may obtain a copy of the License at http://www.bittorrent.com/license/.
#
# Software distributed under the License is distributed on an AS IS basis,
# WITHOUT WARRANTY OF ANY KIND, either express or implied.  See the License
# for the specific language governing rights and limitations under the
# License.

# magic id to use before we know a peer's id
NULL_ID =  20 * '\0'

# Kademlia "K" constant, this should be an even number
K = 8

# SHA1 is 160 bits long
HASH_LENGTH = 160

# checkpoint every this many seconds
CHECKPOINT_INTERVAL = 60 * 5 # five minutes

# how often to find our own nodes
FIND_CLOSE_INTERVAL = 60 * 15 # fifteen minutes

### SEARCHING/STORING
# concurrent krpc calls per find node/value request!
CONCURRENT_REQS = K

# how many hosts to post to
STORE_REDUNDANCY = 3


###  ROUTING TABLE STUFF
# how many times in a row a node can fail to respond before it's booted from the routing table
MAX_FAILURES = 3

# never ping a node more often than this
MIN_PING_INTERVAL = 60 * 15 # fifteen minutes

# refresh buckets that haven't been touched in this long
BUCKET_STALENESS = 60 * 15 # fifteen minutes


###  KEY EXPIRER
# time before expirer starts running
KEINITIAL_DELAY = 15 # 15 seconds - to clean out old stuff in persistent db

# time between expirer runs
KE_DELAY = 60 * 5 # 5 minutes

# expire entries older than this
KE_AGE = 60 * 30 # 30 minutes


## krpc errback codes
KRPC_TIMEOUT = 20

KRPC_ERROR = 1
KRPC_ERROR_METHOD_UNKNOWN = 2
KRPC_ERROR_RECEIVED_UNKNOWN = 3
KRPC_ERROR_TIMEOUT = 4
KRPC_SOCKET_ERROR = 5

KRPC_CONNECTION_CACHE_TIME = KRPC_TIMEOUT * 2


## krpc erorr response codes
KERR_ERROR = (201, "Generic Error")
KERR_SERVER_ERROR = (202, "Server Error")
KERR_PROTOCOL_ERROR = (203, "Protocol Error")
KERR_METHOD_UNKNOWN = (204, "Method Unknown")
KERR_INVALID_ARGS = (205, "Invalid Argements")
KERR_INVALID_TOKEN = (206, "Invalid Token")
