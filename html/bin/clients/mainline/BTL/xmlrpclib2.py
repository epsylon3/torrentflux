# by Greg Hazel

import xml
import xmlrpclib
from connection_cache import PyCURL_Cache, cache_set
import pycurllib
pycurllib.set_use_compression(True)

class PyCurlTransport(xmlrpclib.Transport):

    def __init__(self, cache):
        self.host = None
        self.cache = cache

    def request(self, host, handler, request_body, verbose=0):

        for i in xrange(0):

            try:
                return self._request(host, handler, request_body, verbose)
            except:
                pass
        return self._request(host, handler, request_body, verbose)

    def set_connection_params(self, h):
        h.add_header('User-Agent', "xmlrpclib2.py/2.0")
        h.add_header('Connection', "Keep-Alive")
        h.add_header('Content-Type', "application/octet-stream")
        # this timeout is intended to save us from tomcat not responding
        # and locking the site
        h.set_timeout(20)

    def _request(self, host, handler, request_body, verbose=0):
        # issue XML-RPC request

        h = self.cache.get_connection()

        try:
            self.set_connection_params(h)
            
            h.add_data(request_body)

            response = pycurllib.urlopen(h, close=False)

            #errcode, errmsg, headers = h.getreply()
            errcode = response.code
            errmsg = response.msg
            headers = "N/A"

            if errcode != 200:
                raise xmlrpclib.ProtocolError(
                    host + handler,
                    errcode, errmsg,
                    headers
                    )

            self.verbose = verbose

            r = self._parse_response(response)
        finally:
            self.cache.put_connection(h)

        return r

    def _parse_response(self, response):
        # read response from input file/socket, and parse it

        p, u = self.getparser()

        d = response.getvalue()
        try:
            p.feed(d)
        except xml.parsers.expat.ExpatError, e:
            n = xml.parsers.expat.ExpatError("%s : %s" % (e, d))
            try:
                n.code = e.code
                n.lineno = e.lineno
                n.offset = e.offset
            except:
                pass
            raise n

        p.close()

        return u.close()


def new_server_proxy(url):
    c = cache_set.get_cache(PyCURL_Cache, url)
    t = PyCurlTransport(c)
    return xmlrpclib.ServerProxy(url, transport=t)


ServerProxy = new_server_proxy


if __name__ == '__main__':
    s = ServerProxy('http://search.bittorrent.com/xmlrpc.jsp')
    r = s.search("potato", 0, 1, 1)
    print r
    r = s.search("anime", 0, 1, 1)
    print r
    r = s.search("phish", 0, 1, 1)
    print r
    r = s.search("games", 0, 1, 1)
    print r
