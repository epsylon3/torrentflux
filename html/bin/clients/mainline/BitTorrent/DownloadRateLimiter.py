# Written by David Harrison

from BTL.platform import bttime as time
from BTL.rand_tools import iter_rand_pos
import logging
logger = logging.getLogger( "DownloadRateLimiter" )
log = logger.debug
from twisted.internet import task

class ThrottleListener(object):
    def throttle_connections(self):
        pass
    def unthrottle_connections(self):
        pass

class DownloadRateLimiter( object ):
    """DownloadRateLimiter implements a leaky bucket.
       """

    def __init__(self, interval, max_download_rate ):
        """@param add_task: called to schedule periodic rate adjustements.
           @param interval: time between rate adjustments.
           @param max_download_rate: in Bytes per second.
           """
        assert type(interval) in (int,float,long) and interval > 0.0
        assert type(max_download_rate) in (int, float, long) and \
               max_download_rate > 0.0
        self._interval = interval
        self._max_download_rate = max_download_rate
        self._throttle_listeners = set()
        self._token_bucket = 0    # number of bytes that can be sent.
        self._prev_time = None

        token_size = max_download_rate * interval 
        self._max_token_bytes = 2 * token_size # > 1.*token_size ensures enough for 
                                               # continuous transmission except for really 
                                               # bursty sources.

        # start update interval timer.
        self._timer = task.LoopingCall(self.end_of_interval)
        self._timer.start(interval) 

    def set_parameters( self, max_download_rate ):  # more parameters?
        self._max_download_rate = max_download_rate
        token_size = max_download_rate * self._interval 
        self._max_token_bytes = 2 * token_size

    def add_throttle_listener( self, listener ):
        self._throttle_listeners.add(listener)

    def remove_throttle_listener( self, listener ):
        if listener in self._throttle_listeners:
            self._throttle_listeners.remove(listener)

    def throttle(self):
        #log( "throttle" )
        for l in iter_rand_pos(self._throttle_listeners):
            l.throttle_connections()

    def unthrottle(self):
        #log( "unthrottle" )
        for l in iter_rand_pos(self._throttle_listeners):
            l.unthrottle_connections()
            # arg. resume actually flushes the buffers in iocpreactor, so we
            # have to check the state constantly
            if self._token_bucket <= 0:
                break
                    
    def update_rate(self, bytes):
        #log( "data_came_in: bytes=%d, token_bucket=%d" %
        #     (bytes,self._token_bucket))
        old = self._token_bucket
        self._token_bucket -= bytes

        # Here we throttle the connections whenver the token bucket
        # becomes less than empty. 
        if self._token_bucket - bytes <= 0 and old > 0:
            self.throttle()

    def end_of_interval(self):
        # Note: at low bitrates it is possible and correct for the
        # token bucket to have significantly negative values.  At low
        # bit rates, the interval length can be on the same order
        # or even larger than the packet burst interarrival times, the token
        # size becomes smaller than zero due to a burst of packet
        # arrivals.  This is okay.  The observed rate will sawtooth around
        # the correct rate.
        # compute token size based on the time that really elapsed.
        now = time()
        if self._prev_time is None:
            token = int(self._max_download_rate*self._interval)
        else:
            token = int(self._max_download_rate*(now-self._prev_time))
        self._prev_time = time()
        
        # update token bucket.
        self._token_bucket += token
        if self._token_bucket > self._max_token_bytes:
            self._token_bucket = self._max_token_bytes
        
        # if token bucket not in deficit then safe to begin sending requests.
        if self._token_bucket > 0:
            self.unthrottle()
        else:
            self.throttle()




