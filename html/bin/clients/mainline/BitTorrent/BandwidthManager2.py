# The contents of this file are subject to the BitTorrent Open Source License
# Version 1.1 (the License).  You may not copy or use this file, in either
# source code or executable form, except in compliance with the License.  You
# may obtain a copy of the License at http://www.bittorrent.com/license/.
#
# Software distributed under the License is distributed on an AS IS basis,
# WITHOUT WARRANTY OF ANY KIND, either express or implied.  See the License
# for the specific language governing rights and limitations under the
# License.

# By Greg Hazel and David Harrison

import sys
if __name__ == "__main__":
  sys.path = [".."] + sys.path
  
debug = True
stats = True     # collect statistics and dump to files.
import os        # for stats
import platform  # for stats
import time      # for stats
import math
import socket
import itertools
from BitTorrent.obsoletepythonsupport import set
from BitTorrent.RTTMonitor import RTTMonitor, Win32Icmp, UnixIcmp
from BitTorrent.HostIP import get_host_ips
from BitTorrent.platform import bttime, get_temp_subdir
from BitTorrent.Lists import SizedList, LargestDropSizedList, \
     RandomDropSizedList

stats_dir = None
if stats:
    stats_dir = os.path.join(platform.get_temp_subdir(), "stats")
    os.mkdir(stats_dir)
    if debug: print "BandwidthManager: stats_dir = %s" % stats_dir

class NodeFeeder(object):
    """Every few minutes, this will obtain the set of known peers from
       the running torrents and then tell the RTTMonitor to retraceroute
       to these peers to reidentify the common path."""

    def __init__(self, add_task, get_remote_endpoints, rttmonitor):
        self.add_task = add_task
        self.get_remote_endpoints = get_remote_endpoints
        self.rttmonitor = rttmonitor
        self.add_task(3, self._collect_nodes)

    def _collect_nodes(self):
        addrs = self.get_remote_endpoints()
        print "_collect_nodes: addrs=",addrs
        
        local_ips = get_host_ips()
    
        ips = set()
        for (ip, port) in addrs:
            if ip is not None and ip != "0.0.0.0" and ip not in local_ips:
                ips.add(ip)
        
        self.rttmonitor.set_nodes_restart(ips)

        delay = 5
        if len(ips) > 0:
            delay = 300
    
        self.add_task(delay, self._collect_nodes)


def _copy_gnuplot(fname):
    if os.getenv("BT"):
        src = os.path.join(os.getenv("BT"), "test",
                           "test_BandwdithManager", fname )
        dst = os.path.join( stats_dir, fname )
        shutil.copy(src,dst)

def ratio_sum_lists(a, b):
    tx = float(sum(a))
    ty = float(sum(b))
    return tx / max(ty, 0.0001)

def mean(l):
    N = len(l)
    total = float(sum(l))    
    return total/N

def median(l):
    assert len(l)> 0
    if len(l) == 1:
        return l[0]
    elif len(l) % 2 == 0:  # even number of elements.
        return (l[len(l)/2-1] + l[len(l)/2])/2.
    else:                  # odd number of elements.
        return l[len(l)/2]
        
        
def variance(l):

    N = len(l)
    x = 0
    
    total = float(sum(l))
    
    mean = total/N
    
    # Subtract the mean from each data item and square the difference.
    # Sum all the squared deviations.
    for i in l:
        x += (i - mean)**2.0
    
    try:
        if False:
            # Divide sum of squares by N-1 (sample variance).
            variance = x / (N - 1)
        else:
            # Divide sum of squares by N (population variance).
            variance = x / N
    except ZeroDivisionError:
        variance = 0

    return variance

def standard_deviation(l):
    return math.sqrt(variance)

class Tracer(object):
    """Baseclass for sampler wrapper objects."""
    def __init__( self, func ):
        self._func = func
    def __call__(self, *args, **kwargs):
        return self._func(*args, **kwargs)

class StreamTracer(Tracer):
    """outputs monitored samples to a stream."""
    def __init__( self, func, stream ):
        Tracer.__init__(self,func)
        self._stream = stream
        self._i = 0
    def __call__(self, *args, **kwargs):
        val = self._func( *args, **kwargs)
        self._stream.write("%f\t%f\n" % (bttime(),val))
        self._i = (self._i+1) % 8  # flush a little more often.
        if self._i == 0: 
            self._stream.flush()
        return val

class MinWindow(object):
    """Maintains a list of the 'window_size' smallest
       samples seen.  After every_nth sample, the smallest is dropped.
       Dropping allows the sampler to eventually recover from spurious
       outliers.  This class is meant to be derived.  See
       MedianOfMinWindow, etc.
       """        
    def __init__(self, window_size, every_nth ):
        self._window = LargestDropSizedList( window_size )
        self._i = 0
        self._every_nth = every_nth

    def __getitem__(self, i):
        return self._window[i]
        
    def __len__( self ):
        return len(self._window)

    def __str__(self):
        return str(self._window)
    
    def __iter__(self):
        return self._window.__iter__()
    
    def update(self, sample):
        self._i = (self._i+1)%self._every_nth
        if self._i == 0:  # drop smallest sample.
          self._window.popleft()

        self._window.insort( sample )
        
    def timeout(self):
        """If a timeout occurs then it is possible that our propagation
           estimate is too large.  We therefore drop the top sample."""
        if len(self._window) > 1:
            self._window.pop()              # drop largest sample.

        
class MedianOfMinWindow(MinWindow):
    """Computes the median of a MinWindow.
       By using the median of the smallest, we add some
       additional resistance to outliers.

       If fp is provided then the medians of the min window
       are output to a file."""        
    def __init__( self, window_size, every_nth ):
        MinWindow.__init__(self,window_size, every_nth)
        
    def __call__( self, sample = None):            
        if sample is not None: self.update(sample)
        #print "MedianOfMinWindow:"
        #for i in xrange(len(self._window)):
        #    print " ", self[i]
        return median(self)  

class AverageOfMinWindow(MinWindow):
    """Computes the average of a MinWindow.  By using the average of
       the smallest, we add some additional resistance to outliers."""
    def __init__( self, window_size, every_nth ):
        MinWindow.__init__(self, window_size, every_nth)
            
    def __call__( self, sample = None):
        if sample is not None: self.update(sample)
        return mean(self._window)
        
class MaxWindow(object):
    """Maintains a list of the 'window_size' largest
       samples seen.  After every_nth sample, the largest is dropped.
       Dropping allows the sampler to eventually recover from spurious
       outliers.  This class is meant to be derived.  See
       MedianOfMaxWindow, etc."""        
    def __init__(self, window_size, every_nth ):
        self._window = LargestDropSizedList( window_size ) # reverse w/ -sample
        self._i = 0
        self._every_nth = every_nth

    def __len__( self ):
        return len(self._window)
        
    def __str__(self):
        l = list(self._window)
        l.reverse()
        l = [-x for x in l]
        return str(l)
    
    def __iter__(self):
        class Iterator:
            def __init__(self,minwindow):
                self._minwindow = minwindow
                self._i = 0
            def next(self):
                if self._i < len(self._minwindow):
                    val = self._minwindow[self._i]
                    self._i += 1
                    return val
                raise StopIteration()
        return Iterator(self)

    def __eq__(self,l):
        if isinstance(l,MaxWindow):
            return l._window == self._window
        elif isinstance(l,list):
            return l == [x for x in self]
        else:
            return False

    def __ne__(self,l):
        return not l == self
          
    def __getitem__(self,i):
        """retrieves the ith element in the window where element at index 0
           is the smallest."""
        return -self._window[len(self._window)-i-1]
    
    def update(self, sample):
        self._i = (self._i+1)%self._every_nth
        if self._i == 0:  # drop largest sample.
          self._window.popleft()

        # maintain order.  last element should be smallest so insert -sample.
        self._window.insort(-sample)


class MedianOfMaxWindow(MaxWindow):
    """Computes the median of a MaxWindow.
       By using the median of the largest, we add some
       resistance to outliers."""        
    def __init__( self, window_size, every_nth ):
        MaxWindow.__init__(self,window_size, every_nth)        
            
    def __call__( self, sample = None ):            
        if sample is not None: self.update(sample)
        return median(self)

        
class AverageOfMaxWindow(MaxWindow):
    """Computes the average of a MaxWindow.  By using the average of
       the largest, we add some resistance to outliers."""
    def __init__( self, window_size, every_nth ):
        MaxWindow.__init__(self, window_size, every_nth)
    
    def __call__( self, sample = None ):
        if sample is not None: self.update(sample)
        return mean(self._window)

class AverageOfLastWindow(object):
    def __init__( self, window_size ):
        self._window = SizedList(window_size)

    def __call__( self, sample = None ):
        if sample is not None:
            self._window.append(sample)
        return mean(self._window)

class MedianOfLastWindow(object):
    def __init__( self, window_size ):
        self._window = RandomDropSizedList(window_size)

    def __call__( self, sample = None ):
        """If passed no sample then returns current median."""

        if sample is not None:
            # maintain order.
            self._window.insort(sample)
               # randomly expels one sample to keep size.  Dropping a random
               # sample exhibits no bias (as opposed to dropping smallest or
               # largest as done with MinWindow and MaxWindow)
        return median(self._window)

class EWMA(object):
    def __init__( self, alpha, init_avg = None ):
        """Exponentially Weighted Moving Average (EWMA) functor.
           @param alpha: the weight used in the EWMA using 'smaller is
              slower' convention.
           @param init_avg: starting value of the average.  If None then
              the average is only defined after the first sample and in
              the first call is set to the sample.

           """
        self._alpha = alpha
        self._avg = init_avg

    def __call__( self, sample = None ):
        """Computes the moving average after taking into account the passed
           sample.  If passed nothing then it just returns the current average
           value."""
        if sample == None:
            if self._avg == None:
                raise ValueError( "Tried to retrieve value from EWMA before "
                                  "first sample." )                
        else:
            if self._avg == None:
                self._avg = sample
            else:
                self._avg = (1-self._alpha) * self._avg + self._alpha * sample
        return self._avg


class BinaryCongestionEstimator(object):
    """Abstract baseclass for congestion estimators that measure congestion
       based on rate, rtt, and/or loss.  It's up to the
       subclass to decide whether congestion is occuring."""

    def timeout(self):
        """Called when a timeout occurs."""
        pass

    def __call__( self, rtt, uprate ):
        pass

class VarianceCongestionEstimator(BinaryCongestionEstimator):
    """Congestion is assumed iff the stddev exceeds a threshold
       fraction of the maximum standard deviation."""
    # OBJECTION:  Variance maximization works so long as the rate remains
    # below (to the left) of the peak in the variance versus rate curve.
    # In this regime, increasing rate causes an increase in variance.
    # When measured variance exceeds a threshold the system backs off causing
    # the variance to diminish.  The system oscillates about this 
    # optimal point.
    #
    # HOWEVER, the system behaves quite differently when rates are high, i.e.,
    # close to the bottleneck capacity.  The graph of delay versus send rate
    # looks similar to a bellcurve.  Our system increases the send rate
    # whenever the variance is below a threshold and decreases when 
    # above the threshold.  This is the correct behavior when on the
    # left-hand side of the bell curve.  However, it is the OPPOSITE
    # of the desired behavior when to the right of the peak of the
    # bell curve.  As a result, when rates get too high the system
    # continues to increase send rate until loss occurs.
    #
    # When loss occurs, the system backs off.  When the control law is 
    # AIMDControlLaw, the system backs off multiplicatively.  This backoff
    # moves the system to the left on the bell curve.  If the move is large
    # enough the system climbs over the hump and the system resumes
    # the proper behavior of increasing rate whenever variance is below
    # a threshold.  If however, the backoff is insufficient to reach
    # the peak of the bell curve then the system slides back to the right
    # and resumes increasing send rate until loss occurs.
    #
    # This system is not guaranteed to converge on the equilibrium from all
    # feasible points.     
    #    -- David Harrison
    def __init__(self, window_size):
        self._window = SizedList(window_size)
        self._window_size = window_size
        self._max_var = 0.0
        if stats:
            delay_var_vs_time = os.path.join( stats_dir,
                                        "delay_var_vs_time.plotdata" )
            self._var_fp = open( delay_var_vs_time, "w" )
            max_var_vs_time = os.path.join( stats_dir, 
              "max_var_vs_time.plotdata" )
            self._max_var_fp = open( max_var_vs_time, "w" )
            _copy_gnuplot( "var_vs_time.gnuplot" )

    def timeout(self):
        self._max_var *= 0.64  # FUDGE. same as using 0.8 * max stddev.
        if stats:
            self._max_var_fp.write( "%f\t%f\n" % (bttime(), self._max_var) )
        

    def __call__( self, rtt, rate ):
        self._window.append(rtt)
        var = variance(self._window)
        if stats:
            self._var_fp.write( "%f\t%f\n" % (bttime(), var) )
        if var > self._max_var: 
            self._max_var = var
            if stats:
                self._max_var_fp.write( "%f\t%f\n" % (bttime(), self._max_var))

        # won't signal congestion until we have at least a full window's
        # worth of samples.
        if self._window < self._window_size: 
            return False 
        if var > (self._max_var * 0.64): # FUDGE
            return True
        else:
            return False

class ChebyshevCongestionEstimator(BinaryCongestionEstimator):
    """This congestion estimator first estimates the variance
       and mean of the conditional delay distribution for the condition
       when the bottleneck is uncongested.  We then test for the
       congested state based on delay samples exceeding a threshold.
       We set the threshold based on the Chebyshev Inequality:       
       
       Chebysev Inequality:
           P[(X-u)^2 >= k^2] <= sigma^2 / k^2           (1)

       More details are given in source code comments."""
    #
    #  Here u and sigma are the conditional mean and stddev, X is a single
    #  sample.  We thus can bound the probability of a single sample exceeding
    #  a threshold.  If we set the delay threshold to k such that we trigger
    #  congestion detection whenever the delay threshold is exceeded then
    #  this inequality can be interpreted as an UPPER BOUND ON THE PROBABILITY
    #  OF A FALSE POSITIVE CONGESTION DETECTION.
    #
    #  Because we are dealing with a single bottleneck, we can know the
    #  min (propagation) delay and max (buffer full) delay.  We thus know
    #  the worst case variance occurs when half the samples are at the upper
    #  bound and half at the lower bound resulting in
    #  
    #                                 2
    #                        (max-min)
    #      var_sample approx  -------                  (2)
    #                            4
    #
    #  Substituting (2) into (1) yields
    #                                    2
    #                           (max-min)
    #      P[(X-u)^2 >= k^2] <= ---------              (3)
    #                             4 k^2
    #  
    #  When the worst-case variance is achieved, the mean u = (max-min)/2.
    #  If we then set the threshold equal to max then k = (max-min)/2 and 
    #  (3) becomes
    #                           
    #      P[(X-u)^2 >= k^2] <= 1                      (4)
    #                             
    #  This means that in the worst-case, the Chebyshev inequality provides
    #  a useless bound.  However, for anything less than the worst-case,
    #  the false positive probability is less than 1.  But wait, how is it
    #  useful to have a false positive probability near 1?
    #
    #  If delay samples are independent when in the uncongested state,
    #  the probability that n consecutive samples exceed the theshold
    #  becomes,
    #  
    #      P[For n consecutive samples, (X-u)^2 >= k^2] <= (sigma^2 / k^2)^n
    #                                                  (5)
    #  
    #  If we only signal congestion when n consecutive samples are
    #  above the threshold and if sigma^2 / k^2 < 1, then we can set
    #  the probability of a false positive to anything we like by
    #  setting n sufficiently large.
    #  
    #  I argue that during the uncongested state, the samples should be
    #  approximately independent, because when the network is
    #  uncongested, delay is not driven by queueing or at least not
    #  by queueing that persists across a significant portion of our
    #  sample window.
    #  
    #  To allow for the most distance above and below the threshold,
    #  we try to set the threshold to (max-min)/2, but we will increase
    #  the threshold if necessary should the number of consecutive samples
    #  needed become too large (i.e., exceed max_consecutive) or if
    #  the conditional mean exceeds (max-min)/2.
    #  
    #      k = thresh - u
    #      k = (max-min)/2 - u
    #      let P = P[For n consecutive samples,(X-u)^2 >= k^2] = false_pos_prob
    #
    #                     2n
    #                sigma
    #      P <=  -----------------
    #             max-min        2n
    #           ( --------  - u )
    #                2
    #
    #      log P <=  2n log ( sigma / ((max-min)/2 - u))
    #
    #            1              log P
    #      n >=  -  ------------------------------  .     (6) 
    #            2  log (sigma / ((max-min)/2 - u)
    #
    #  We then turn >= in (6) to an assignment to derive the n used in
    #  detecting congestion.  If n exceeds max_consecutive then we adjust
    #  the threshold keeping n at max_consecutive.
    #
    #                    2n
    #               sigma
    #      P >= ---------------
    #                        2n
    #           (thresh - u )
    #
    #  
    #                 sigma
    #      thresh >=  ------- + u                          (7)
    #                 P^(1/2n)
    #
    #  The threshold is not allowed to exceed max_thresh of the way
    #  between min and max.  When threshold reaches this point, the
    #  thresholds become less meaningful and the performance of the
    #  congestion estimator is likely to suffer.
    #
    # Implementation Complexity:
    #  The computational burden of this congestion estimator is
    #  significantly higher than the TCP Reno/Tahoe loss based or TCP Vegas
    #  delay-based congestion estimators, but this algorithm is applied
    #  on the aggregate of ALL connections passing through the access point.
    #  State maintainence for the 100+ connections created by BitTorrent
    #  dwarfs the computational burden of this estimator.
    def __init__(self, window_size, drop_every_nth,
                 false_pos_prob, max_consecutive, max_thresh, ewma ):
        assert drop_every_nth > 0
        assert false_pos_prob > 0.0 and false_pos_prob < 1.0
        assert max_consecutive > 1
        assert max_thresh > 0.0 and max_thresh < 1.0
        assert ewma > 0.0 and ewma < 1.0

        # parameters
        self._window_size = window_size
        self._false_pos_prob = false_pos_prob
        self._max_consecutive = max_consecutive
        self._thresh = max_thresh

        # estimators
        self._window = SizedList(window_size)
        self._propagation_estimator = \
            MedianOfMinWindow(window_size, drop_every_nth)
        self._delay_on_full_estimator = \
            MedianOfMaxWindow(window_size, drop_every_nth)
        self._cond_var = EWMA(alpha = ewma)   # variance when uncongested.
        self._cond_mean = EWMA(alpha = ewma)  # mean when uncongested.

        # counters
        self._init_samples = 0  # count of first samples.
        self._consecutive = 0   # consecutive samples above the threshold.

        # computed thresholds
        self._n = None
        self._thresh = None

        if stats:
            prop_vs_time = os.path.join( stats_dir, "prop_vs_time.plotdata" )
            fp = open( prop_vs_time, "w" )
            self._propagation_estimator = \
                StreamTracer( self._propagation_estimator, fp )

            full_vs_time = os.path.join( stats_dir, "full_vs_time.plotdata" )
            fp = open( full_vs_time, "w" )
            self._delay_on_full_estimator = \
                StreamTracer( self._delay_on_full_estimator, fp )

            cmean_vs_time = os.path.join( stats_dir, "cmean_vs_time.plotdata" )
            fp = open( cmean_vs_time, "w" )
            self._cond_mean = StreamTracer( self._cond_mean, fp )

            cvar_vs_time = os.path.join( stats_dir, "cvar_vs_time.plotdata" )
            fp = open( cvar_vs_time, "w" )
            self._cond_var = StreamTracer( self._cond_var, fp )

            thresh_vs_time = os.path.join(stats_dir,"thresh_vs_time.plotdata")
            self._thfp = open( thresh_vs_time, "w" )

            n_v_time = os.path.join( stats_dir, "n_vs_time.plotdata" )
            self._nfp = open( n_vs_time, "w" )
        
    def timeout(self):
        self._delay_on_full_estimator.timeout()

    def __call__( self, rtt, rate ):
        self._window.append(rtt)
        full = self._delay_on_full_estimator(rtt)
        prop = self._propagation_estimator(rtt)
        if ( self._init_samples < self._window_size ):
            # too few samples to determine whether there is congestion...
            self._init_samples += 1
            return False

        # enough samples to initialize conditional estimators.
        elif self._init_samples == self._window_size:
            self._init_samples += 1
            self._update(rtt)
            return False

        assert self._n is not None and self._thresh is not None
        epsilon = ( full - prop ) * 0.05

        # if delay is within epsilon of the propagation delay then
        # assume that we are in the uncongested state.  We use the
        # window's middle sample to reduce bias.
        if self._window[len(self._window)/2] < prop + epsilon:
            self._update(rtt)   # updates thresholds.

        if rtt > self._thresh:
            self._consecutive += 1
            if self._consecutive >= self._n:
                self._consecutive = 0  # don't generate multiple detections
                                       # for single period of congestion unless
                                       # it persists across separate trials
                                       # of n samples each.
                return True   # congestion detected
        else:
            self._consecutive = 0
        return False          # no congestion detected
        
            
    def _update(self, rtt):
        """update thresholds when delay is within epsilon of the
           estimated propagation delay."""
        
        var = self._cond_var(variance(self._window))
        u = self._cond_mean(mean(self._window))

        #         1              log P
        #   n >=  -  ------------------------------  .     (6) 
        #         2  log (sigma / ((max-min)/2 - u)

        sigma = math.sqrt(var)
        max = self._delay_on_full_estimator()
        min = self._propagation_estimator()
        p = self._false_pos_prob
        thresh = (max-min)/2
        if thresh > u:
            n = int(0.5 * math.log(p) / math.log(sigma/(thresh-u)))
            if n < 1:
                n = 1
        
        if thresh <= u or n > self._max_consecutive:
            n = self._max_consecutive
            
            #             sigma
            # thresh >=  ------- + u                       (7)
            #            P^(1/2n)
            thresh = sigma / p**(0.5*n) + u
            if thresh > self._max_thresh:

                # this is a bad state.  if we are forced to set thresh to
                # max thresh then the rate of false positives will
                # inexorably increase.  What else to do?
                thresh = self._max_thresh

        self._thresh = thresh
        self._n = n
        if stats:
            self._thfp.write( "%f\t%f\n" % (bttime(), self._thresh) )
            self._nfp.write( "%f\t%d\n" % (bttime(), self._n) )
    
class VegasishCongestionEstimator(BinaryCongestionEstimator):
    """Delay-based congestion control with static threshold
       set at 1/2 distance between propagation delay and delay on full
       buffer."""
    def __init__(self, window_size, drop_every_nth ):
        self._rtt_estimator = AverageOfLastWindow(window_size)
        self._propagation_estimator = \
            MedianOfMinWindow(window_size, drop_every_nth)
        self._delay_on_full_estimator = \
            MedianOfMaxWindow(window_size, drop_every_nth)
        
    def __call__(self, rtt, rate):
        middle_rtt = ((self._propagation_estimator(rtt) +
                       self._delay_on_full_estimator(rtt)) / 2.0 )
        if rtt > middle_rtt:
            return True
        else:
            return False

class BinaryControlLaw(object):
    """Increases or decreases rate limit based on a binary congestion
       indication"""

    def __call__( self, is_congested, rate ):
        """Passed rate is the averaged upload rate.  Returned rate
           is a rate limit."""
        pass

class AIADControlLaw(BinaryControlLaw):
    """Additive Increase with Additive Decrease"""
    def __init__( self, increase_delta, decrease_delta ):
        assert type(increase_delta) in [float,int,long] and \
               increase_delta > 0.0
        assert type(decrease_delta) in [float,int,long] and \
               increase_delta > 0.0
        self._increase_delta = increase_delta
        self._decrease_delta = decrease_delta
        self._ssthresh = 1.e50  # infinity
           
    def __call__( self, is_congested, rate ):
        """Passed rate is the averaged upload rate.  Returned rate
           is a rate limit."""
        if is_congested:
            limit = rate - self._decrease_delta
            rate = max(rate, 1000)
            self._ssthresh = limit     # congestion resets slow-start threshold
        elif rate > self._ssthresh - self._increase_delta:
            self._ssthresh += self._increase_delta

        # allow slow-start
        limit = min( self._ssthresh, 2.0 * rate )
        return rate            
        
class AIMDControlLaw(BinaryControlLaw):
    """Rate-based Additive Increase with Multiplicative Decrease w/ 
       multiplicative slow-start."""
    def __init__( self, increase_delta, decrease_factor ):
        assert type(increase_delta) in [float,int,long] and \
               increase_delta > 0.0
        assert type(decrease_factor) == float and decrease_factor > 0.0 and \
               decrease_factor < 1.0
        self._increase_delta = increase_delta
        self._decrease_factor = decrease_factor
        self._ssthresh = 1.e50  # infinity
           
    def __call__( self, is_congested, rate ):
        """Passed rate is the averaged upload rate.  Returned rate
           is a rate limit."""
        if is_congested:
            print "CONGESTION!"
            limit = rate * self._decrease_factor
            self._ssthresh = limit     # congestion resets slow-start threshold
        elif rate > self._ssthresh - self._increase_delta:
            self._ssthresh += self._increase_delta

        # allow slow-start
        limit = min( self._ssthresh, 2.0 * rate )

        if debug:
            print "AIMD: time=%f rate=%f ssthresh=%f limit=%f" % \
                (bttime(), rate, self._ssthresh, limit)

        return limit            

class StarvationPrevention(object):
    """Baseclass for objects that bound rate limit to prevent starvation."""
    def __call__( self, rate ):  # may have to add additional args.
        """Passed rate is current average rate.  Returned rate is a rate
           limit."""
        pass
    
class FixedStarvationPrevention(StarvationPrevention):
   def __init__( self, lower_rate_bound ):
       self._lower_rate_bound = lower_rate_bound

   def __call__( self, rate ):
       print "starvation rate=", max(rate,self._lower_rate_bound)
       return max( rate, self._lower_rate_bound )
    
class BandwidthManager(object):
    """Controls allocation of bandwidth between foreground and background
       traffic.  Currently all BitTorrent traffic is considered background.
       Background traffic is subjected to a global rate limit that is
       reduced during congestion to allow foreground traffic to takeover.

       A 'starvation prevention' building block applies a lower bound.
       """    
    def __init__(self, external_add_task, config, set_config,
                       get_remote_endpoints, get_rates):
        if debug:
            if config['bandwidth_management']:
                print "bandwidth management is up."
            else:
                print "!@#!@#!@#!@#!@# bandwidth management is down."
        
        self.external_add_task = external_add_task
        self.config = config
        self.set_config = set_config
        self.get_rates = get_rates
        if os.name == 'nt':
            icmp_impl = Win32Icmp()
        elif os.name == 'posix':
            icmp_impl = UnixIcmp(external_add_task, config['xicmp_port'])
        def got_new_rtt(rtt):
            print "got_new_rtt, rtt=", rtt
            self.external_add_task(0, self._inspect_rates, rtt)
        self.rttmonitor = RTTMonitor(got_new_rtt, icmp_impl)
        self.nodefeeder = NodeFeeder(add_task=external_add_task,
                                     get_remote_endpoints=get_remote_endpoints,
                                     rttmonitor=self.rttmonitor)

        self.start_time = bttime()

        if config['control_law'] == 'aimd':
            self.control_law = AIMDControlLaw(config['increase_delta'],
                                              config['decrease_factor'])
        elif config['control_law'] == 'aiad':
            self.control_law = AIADControlLaw(config['increase_delta'],
                                              config['decrease_delta'])

        # This configurability is temporary during testing/tuning.  --Dave
        if config['congestion_estimator'] == "chebyshev":
            self.congestion_estimator = ChebyshevCongestionEstimator(
                config['window_size'], config['drop_every_nth'],
                config['cheby_max_probability'],
                config['cheby_max_consecutive'],
                config['cheby_max_threshold'], config['ewma'])
            
        elif config['congestion_estimator'] == "variance":
            self.congestion_estimator = VarianceCongestionEstimator(
                config['window_size'])
        elif config['congestion_estimator'] == "vegasish":
            self.congestion_estimator = VegasishCongestionEstimator(
                config['window_size'], config['drop_every_nth'])
        else:
            raise BTFailure(_("Unrecognized congestion estimator '%s'.") %
                config['congestion_estimator'])

        self.starvation_prevention = FixedStarvationPrevention(
            config['min_upload_rate_limit'] )

        if stats:
            rlimit_vs_time = \
                           os.path.join( stats_dir, "rlimit_vs_time.plotdata" )
            fp = open( rlimit_vs_time, "w" )
            self.control_law = StreamTracer(self.control_law, fp)
            _copy_gnuplot( "rlimit_vs_time.gnuplot" )

            # samples are max(min_upload_rate_limit,rate).
            slimit_vs_time = \
                           os.path.join( stats_dir, "slimit_vs_time.plotdata" )
            fp = open( slimit_vs_time, "w" )
            self.starvation_prevention = StreamTracer(
                self.starvation_prevention, fp)
            
            delay_vs_time = os.path.join( stats_dir, "delay_vs_time.plotdata" )
            self.dfp = open( delay_vs_time, "w" )
            _copy_gnuplot( "delay_vs_time.gnuplot" )

    #def congestion_estimator_vegas_greg(self, rtt, rate):
    #
    #    middle_rtt = ((self.propagation_estimator(rtt) +
    #                   self.delay_on_full_estimator(rtt)) / 2.0 )
    #    if t > middle_rtt and c < 0.5:
    #        rate *= 0.5
    #        if debug:
    #            print type, "down to", rate
    #    else:
    #        rate += 1000 # hmm
    #        if debug:
    #            print type, "up to", rate
    #    return rate            

    #def congestion_estimator_ratio(self, type, t, p, min_p, max_p, rate):
    #    ratio = p / max_p
    #    if debug:
    #        print "RATIO", ratio
    #    if ratio < 0.5:
    #        rate = ratio * self.max_rates[type]
    #        if debug:
    #            print type.upper(), "SET to", rate
    #    else:
    #        rate += rate * (ratio/10.0) # hmm
    #        if debug:
    #            print type.upper(), "UP to", rate
    #            
    #    return max(rate, 1000)

    #def congestion_estimator_stddev(self, type, std, max_std, rate):
    #    if std > (max_std * 0.80): # FUDGE
    #        rate *= 0.80 # FUDGE
    #        if debug:
    #            print type.upper(), "DOWN to", rate
    #    else:
    #        rate += 1000 # FUDGE
    #        if debug:
    #            print type.upper(), "UP to", rate
    #
    #    return max(rate, 1000) # FUDGE
    
    #def _affect_rate(self, type, std, max_std, rate, set):
    #    rate = self._congestion_estimator_stddev(type, std, max_std, rate)
    #
    #    rock_bottom = False
    #    if rate <= 1000:
    #        if debug:
    #            print "Rock bottom"
    #        rock_bottom = True
    #        rate = 1000
    #
    #    set(int(rate))
    #    if stats:
    #        print "BandwidthManager._affect_rate(%f)" % rate
    #        self.rfp.write( "%d\t%d\n" % (bttime(),int(rate)) )
    #        self.sdevfp.write( "%d\t%f\n" % (bttime(), std ) )
    #
    #    return rock_bottom

    def _inspect_rates(self, t = None):
        """Called whenever an RTT sample arrives. If t == None then
           a timeout occurred."""
        if t == None:
            t = self.rttmonitor.get_current_rtt()

        if t == None:
            # this makes timeouts reduce the maximum std deviation
            self.congestion_estimator.timeout()
            return

        if debug:
            print "BandwidthManager._inspect_rates: %d" % t
        if stats:
            self.dfp.write( "%f\t%f\n" % (bttime(),t) )

        if not self.config['bandwidth_management']:
            return

        # TODO: slow start should be smarter than this
        #if self.start_time < bttime() + 20:
        #    self.config['max_upload_rate'] = 10000000
        #    self.config['max_dowload_rate'] = 10000000

        #if t < 3:
        #    # I simply don't believe you. Go away.
        #    return

        tup = self.get_rates() 
        if tup == None:
            return
        uprate, downrate = tup

        # proceed through the building blocks.  (We can swap in various
        # implementations of each based on config).
        is_congested = self.congestion_estimator(t,uprate)
        rate_limit = self.control_law(is_congested,uprate)
        rate_limit = self.starvation_prevention(rate_limit)
        self._set_rate_limit(rate_limit)

    def _set_rate_limit(self,rate_limit):
        self.set_config('max_upload_rate', rate_limit)

if __name__ == "__main__":
    # perform unit tests.

    n_tests = 0
    n_tests_passed = 0

    n_tests += 1
    medmin = MedianOfMinWindow(3, 7)
    if medmin(5) != 5:  # [5], i = 1
        print "FAILED. Median [5] %s should be 5, but it is %d." % \
            (medmin,medmin())
    else:
        n_tests_passed += 1

    n_tests += 1
    m = medmin(6)   # [5,6], i = 2
    if medmin._window != [5,6] or m < 5.5-0.01 or m > 5.5 + 0.01:
        print ( "FAILED. medmin should be [5,6] with median 5.5, but it is "
                "%s with median %f." % (medmin,medmin()))
    else:
        n_tests_passed += 1

    n_tests += 1
    m = medmin(7)  # [5,6,7], i = 3
    if medmin._window != [5,6,7] or m < 6-0.01 or m > 6+0.01:
        print ( "FAILED. medmin should be [5,6,7] with median 6, but it is %s"
                " with median %f." ) % (medmin,medmin())
    else:
        n_tests_passed += 1

    n_tests += 1
    medmin(8)       # [5,6,7], discard 8, i = 4
    medmin(9)       # [5,6,7], discard 9, i = 5
    m = medmin(10)  # [5,6,7], discard 10, i = 6
    if medmin._window != [5,6,7] or m < 6-0.01 or m > 6+0.01:
        print ( "FAILED. After inserting [5..10], we should've dropped 8,9 "
                "and 10 leaving us with [5,6,7], but the list is %s with  "
                "median %d." ) % (medmin,medmin())
    else:
        n_tests_passed += 1

    n_tests += 1
    m = medmin(11)
    if medmin._window != [6,7,11] or m < 7-0.01 or m > 7+0.01:
        print ( "FAILED.  After inserting [5..11], we should've dropped "
                "8,9 and 10, but when 11 is added this is the 'every_nth'"
                " and thus we should've dropped 5 instead leaving us with "
                "[6,7,11] and thus a median of 7." )
    else:
        n_tests_passed += 1
                
    n_tests += 1
    avgmin = AverageOfMinWindow(3,7)
    m = avgmin(2)
    if m < 2-0.01 or m > 2+0.01:    # [2], i = 1
        print ("FAILED. Average of [2] should be 2, but average of %s is "
               "%f." ) % (avgmin, avgmin(2))
    else:
        n_tests_passed += 1

    n_tests += 1
    m = avgmin()        # [2], i = 1
    if m < 2-0.01 or m > 2+0.01:
        print ( "FAILED.  Average after inserting no new elements should "
                "remain unchanged at 2, but it is %d." ) % val
    else:
        n_tests_passed += 1

    n_tests += 1
    avgmin(3)             # [2,3], i = 2
    val = avgmin(5)       # [2,3,5], i = 3
    if avgmin._window != [2,3,5] or val < 10/3.-0.01 or val > 10/3.+0.01:
        print ( "FAILED: after avgmin(3), avgmin's window should contain "
                "[2,3,5] and have average %f, but it is %s with average %f." %
                ( (2+3+5)/3., avgmin, avgmin() ) )
    else:
        n_tests_passed += 1

    n_tests += 1
    val = avgmin(0)      # [0,2,3], i = 4
    if avgmin._window != [0,2,3] or val < 5/3.-.01 or val > 5/3.+0.01:
        print ( "FAILED: avgmin's window should contain [0,2,3] and have "
                "average %f, but it is %s with average %f." %
                ( (0+2+3)/3., avgmin._window, avgmin() ) )
    else:
        n_tests_passed += 1

    n_tests += 1
    avgmin(2)            # [0,2,2], i = 5
    avgmin(4)            # [0,2,2], i = 6, discarded 4
    avgmin(1)            # [1,2,2], i = 7, dropped smallest 0
    if avgmin._window != [1,2,2] or val < 5/3.-0.01 or val > 5/.3+0.01:
        print ( "FAILED: avgmin's window should contain [1,2,2] and have "
                "average %f, but it is %s with average %f." %
                ( (1+2+2)/3., avgmin, avgmin() ) )
    else:
        n_tests_passed += 1

    n_tests += 1
    medmax = MedianOfMaxWindow(3,7)
    val = medmax(2)          # i = 1
    if medmax != [2] or val < 2-0.01 or val > 2+0.01:
        print ( "FAILED: medmax should be [2] and median 2, but medmax is %s "
                "and median is %f." % (medmax, medmax()) )
    else:
        n_tests_passed += 1

    n_tests += 1
    medmax(4)
    val = medmax(1)          # i = 3
    if medmax != [1,2,4] or val < 2-0.01 or val > 2+0.01:
        print ( "FAILED: medmax should be [1,2,4] and median 2, but medmax "
                "is %s and median is %f." % (medmax, medmax()) )
    else:
        n_tests_passed += 1

    n_tests += 1
    val = medmax(5)         # i = 4
    if medmax != [2,4,5] or val < 4-0.01 or val > 4+0.01:
        print ( "FAILED: medmax should be [2] and median 2, but medmax is "
                "%s and median is %f." % (medmax, medmax()) )
    else:
        n_tests_passed += 1

    n_tests += 1
    medmax(1)         # i = 5
    medmax(1)         # i = 6
    val = medmax(3)   # i = 7
    if medmax != [2,3,4] or val < 3-0.01 or val > 3+0.01:
        print ( "FAILED: medmax should be [3] and median 3, but medmax is "
                "%s and median is %f." % (medmax,medmax()) )
    else:
        n_tests_passed += 1
        
    if n_tests == n_tests_passed:
        print "Passed all %d tests." % n_tests
