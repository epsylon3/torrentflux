# by Greg Hazel

from __future__ import generators

import urllib
import logging

from BTL.DictWithLists import OrderedDict
from BitTorrent.Connector import Connector
from bisect import bisect_right
from urlparse import urlparse
from BitTorrent.HTTPDownloader import parseContentRange

noisy = False

#if noisy:
connection_logger = logging.getLogger("BitTorrent.HTTPConnector")
connection_logger.setLevel(logging.DEBUG)
stream_handler = logging.StreamHandler()
connection_logger.addHandler(stream_handler)
log = connection_logger.debug

class BatchRequests(object):

    def __init__(self):
        self.requests = {}

    # you should add from the perspective of a BatchRequest
    def _add_request(self, filename, begin, length, br):
        r = (filename, begin, length)
        assert r not in self.requests
        self.requests[r] = br

    def got_request(self, filename, begin, data):
        length = len(data)
        r = (filename, begin, length)
        br = self.requests.pop(r)
        br.got_request(filename, begin, length, data)
        return br
        

class BatchRequest(object):
    
    def __init__(self, parent, start):
        self.parent = parent
        self.numactive = 0
        self.start = start
        self.requests = OrderedDict()

    def add_request(self, filename, begin, length):
        r = (filename, begin, length)
        assert r not in self.requests
        self.parent._add_request(filename, begin, length, self)
        self.requests[r] = None
        self.numactive += 1

    def got_request(self, filename, begin, length, data):
        self.requests[(filename, begin, length)] = data
        self.numactive -= 1

    def get_result(self):
        if self.numactive > 0:
            return None
        chunks = []
        for k in self.requests.itervalues():
            chunks.append(k)
        return ''.join(chunks)
        

# kind of like storage wrapper for webserver interaction
class URLage(object):

    def __init__(self, files):
        # a list of bytes ranges and filenames for window-based IO
        self.ranges = []
        self._build_url_structs(files)
        
    def _build_url_structs(self, files):
        total = 0
        for filename, length in files:
            if length > 0:
                self.ranges.append((total, total + length, filename))
            total += length
        self.total_length = total

    def _intervals(self, pos, amount):
        r = []
        stop = pos + amount
        p = max(bisect_right(self.ranges, (pos, )) - 1, 0)
        for begin, end, filename in self.ranges[p:]:
            if begin >= stop:
                break
            r.append((filename, max(pos, begin) - begin, min(end, stop) - begin))
        return r

    def _request(self, host, filename, pos, amount, prefix, append):
        b = pos
        e = b + amount - 1
        f = prefix
        if append:
            f += filename
        s = '\r\n'.join([
            "GET /%s HTTP/1.1" % (urllib.quote(f)),
            "Host: %s" % host,
            "Connection: Keep-Alive",
            "Range: bytes=%s-%s" % (b, e),
            "", ""])
        if noisy: log(s)
        return s    

    def build_requests(self, brs, host, pos, amount, prefix, append):
        r = []
        br = BatchRequest(brs, pos)
        for filename, pos, end in self._intervals(pos, amount):
            s = self._request(host, filename, pos, end - pos, prefix, append)
            br.add_request(filename, pos, end - pos)
            r.append((filename, s))
        return r
        

class HTTPConnector(Connector):
    """Implements the HTTP syntax with a BitTorrent Connector interface. 
       Connection-level semantics are as normal, but the download is always
       unchoked after it's connected."""

    MAX_LINE_LENGTH = 16384
    UNCHOKED_SEED_COUNT = 5
    RATE_PERCENTAGE = 10

    def __init__(self, parent, piece_size, urlage, connection, id, outgoing, log_prefix):
        self.piece_size = piece_size
        self._header_lines = []
        self.manual_close = False
        self.urlage = urlage
        self.batch_requests = BatchRequests()
        # pipeline tracker
        self.request_paths = []
        # range request glue
        self.request_blocks = {}
        scheme, host, path, params, query, fragment = urlparse(id)
        if path and path[0] == '/':
            path = path[1:]
        self.host = host
        self.prefix = path
        self.append = not(len(self.urlage.ranges) == 1 and path and path[-1] != '/')
        Connector.__init__(self, parent, connection, id, outgoing, log_prefix=log_prefix)
        # blarg
        self._buffer = []
        self._buffer_len = 0

    def close(self):
        self.manual_close = True
        Connector.close(self)

    def send_handshake(self):
        if noisy: self.logger.info('connection made: %s' % self.id)
        self.complete = True
        self.parent.connection_handshake_completed(self)

        # ARGH. -G
        def _download_send_request(index, begin, length):
            piece_size = self.download.multidownload.storage.piece_size
            if begin + length > piece_size:
                raise ValueError("Issuing request that exceeds piece size: "
                                 "(%d + %d == %d) > %d" %
                                 (begin, length, begin + length, piece_size))
            self.download.multidownload.active_requests_add(index)
            a = self.download.multidownload.rm._break_up(begin, length)
            for b, l in a:
                self.download.active_requests.add((index, b, l))
            self.request_blocks[(index, begin, length)] = a
            assert self == self.download.connector
            self.send_request(index, begin, length)
        self.download.send_request = _download_send_request

        # prefer full pieces to reduce http overhead
        self.download.prefer_full = True
        self.download._got_have_all()
        self.download.got_unchoke()

    def send_request(self, index, begin, length):
        if noisy:
            self.logger.info("SEND %s %d %d %d" % ('GET', index, begin, length))
        b = (index * self.piece_size) + begin
        r = self.urlage.build_requests(self.batch_requests, self.host, b,
                                       length, self.prefix, self.append)
        for filename, s in r:
            self.request_paths.append(filename)
            self._write(s)

    def send_interested(self):
        pass

    def send_not_interested(self):
        pass

    def send_choke(self):
        self.choke_sent = self.upload.choked

    def send_unchoke(self):
        self.choke_sent = self.upload.choked

    def send_cancel(self, index, begin, length):
        pass

    def send_have(self, index):
        pass

    def send_bitfield(self, bitfield):
        pass
    
    def send_keepalive(self):
        # is there something I can do here?
        pass

    # yields the number of bytes it wants next, gets those in self._message
    def _read_messages(self):
        completing = False
        
        while True:
            self._header_lines = []

            yield None

            line = self._message.upper()
            if noisy: self.logger.info(line)
            l = line.split(None, 2)
            version = l[0]
            status = l[1]
            try:
                message = l[2]
            except IndexError:
                # sometimes there is no message
                message = ""

            if not version.startswith("HTTP"):
                self.protocol_violation('Not HTTP: %r' % self._message)
                return
                
            if status not in ('301', '302', '303', '206'):
                self.protocol_violation('Bad status message: %s' %
                                        self._message)
                return

            headers = {}
            while True:
                yield None
                if len(self._message) == 0:
                    break
                if ':' not in self._message:
                    self.protocol_violation('Bad header: %s' % self._message)
                    return
                header, value = self._message.split(':', 1)
                header = header.lower()
                headers[header] = value.strip()
            if noisy: self.logger.info("incoming headers: %s"  % (headers, ))
            # reset the header buffer so we can loop
            self._header_lines = []

            if status in ('301', '302', '303'):
                url = headers.get('location')
                if not url:
                    self.protocol_violation('No location: %s' % self._message)
                    return
                self.logger.warning("Redirect: %s" % url)
                self.parent.start_http_connection(url)
                return

            filename = self.request_paths.pop(0)
            
            start, end, realLength = parseContentRange(headers['content-range'])
            length = (end - start) + 1
            cl = int(headers.get('content-length', length))
            if cl != length:
                raise ValueError('Got c-l:%d bytes instead of l:%d' % (cl, length))
            yield length
            if len(self._message) != length:
                raise ValueError('Got m:%d bytes instead of l:%d' %
                                 (len(self._message), length))
            
            if noisy:
                self.logger.info("GOT %s %d %d" % ('GET', start, len(self._message)))
            self.got_anything = True
            br = self.batch_requests.got_request(filename, start, self._message)
            data = br.get_result()
            if data:
                index = br.start // self.piece_size
                if index >= self.parent.numpieces:
                    return
                begin = br.start - (index * self.piece_size)
            
                if noisy:
                    self.logger.info("GOT %s %d %d %d" % ('GET', index, begin, length))

                r = (index, begin, length)
                a = self.request_blocks.pop(r)
                for b, l in a:
                    d = buffer(data, b - begin, l)
                    self.download.got_piece(index, b, d)

            if noisy:
                self.logger.info("REMAINING: %d" % len(self.request_blocks))


    def data_came_in(self, conn, s):
        #self.logger.info( "HTTPConnector self=%s received string len(s): %d" % (self,len(s)))
        self.received_data = True
        
        if not self.download:
            # this is really annoying.
            self.sloppy_pre_connection_counter += len(s)
        else:
            l = self.sloppy_pre_connection_counter + len(s)
            self.sloppy_pre_connection_counter = 0
            self.download.fire_raw_received_listeners(l)

        self._buffer.append(s)
        self._buffer_len += len(s)
        #self.logger.info( "_buffer now has length: %s, _next_len=%s" % 
        #    (self._buffer_len, self._next_len ) )

        # not my favorite loop.
        # the goal is: read self._next_len bytes, or if it's None return all
        # data up to a \r\n
        while True:
            if self.closed:
                return
            if self._next_len == None:
                if self._header_lines:
                    d = ''.join(self._buffer)
                    m = self._header_lines.pop(0)
                else:
                    if '\n' not in s:
                        break
                    d = ''.join(self._buffer)
                    header = d.split('\n', 1)[0]
                    self._header_lines.append(header)
                    m = self._header_lines.pop(0)
                if len(m) > self.MAX_LINE_LENGTH:
                    self.protocol_violation('Line length exceeded.')
                    self.close()
                    return
                self._next_len = len(m) + len('\n')
                m = m.rstrip('\r')
            else:
                if self._next_len > self._buffer_len:
                    break
                d = ''.join(self._buffer)
                m = d[:self._next_len]
            s = d[self._next_len:]
            self._buffer = [s]
            self._buffer_len = len(s)
            self._message = m
            try:
                self._next_len = self._reader.next()
            except StopIteration:
                self.close()
                return

    def _optional_restart(self):            
        if noisy: self.logger.info("_optional_restart: got_anything:%s manual_close:%s" % (self.got_anything, self.manual_close))

        if self.manual_close:
            return

        # we disconnect from the http seed in cases where we're getting
        # plenty of bandwidth elsewhere. the first measure is the number
        # of unchoked seeds we're connected to. the second is the
        # percentage of the total rate that the seed makes up.
        md = self.download.multidownload
        seed_count = md.get_unchoked_seed_count()
        # -1 because this http seed it counted still
        seed_count -= 1
        if seed_count > self.UNCHOKED_SEED_COUNT:
            torrent_rate = md.get_downrate()
            scale = (self.RATE_PERCENTAGE / 100.0)
            if self.download.get_rate() < (torrent_rate * scale):
                a = seed_count
                b = self.UNCHOKED_SEED_COUNT
                c = self.download.get_rate()
                d = torrent_rate * scale
                self.logger.info("Swarm performance: %s > %s && %s < %s" % (a, b, c, d))
                return
        
        if noisy: self.logger.info("restarting: %s" % self.id)
        # http keep-alive has a per-connection limit on the number of
        # requests also, it times out. both result it a dropped connection,
        # so re-make it. idealistically, the connection would hang around
        # even if dropped, and reconnect if we needed to make a new request
        # (that way we don't thrash the piece picker everytime we reconnect)
        self.parent.start_http_connection(self.id)
