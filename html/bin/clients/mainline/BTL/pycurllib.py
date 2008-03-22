# A (very incomplete) urllib2-looking interface to pycurl
# by: Greg Hazel

import os
import sys
import pycurl
from cStringIO import StringIO
from BaseHTTPServer import BaseHTTPRequestHandler
from urllib import urlencode

DEBUG = False

global CA_PATH
CA_PATH = None

http_bindaddr = None
user_agent = None
_pycurl_compression = 'zlib' in pycurl.version
use_compression = True
use_cert_authority = False
timeout = 30
connect_timeout = 30

class StringIO2(object):
    def __init__(self):
        self.s = StringIO()
    def __getattr__(self, attr):
        return getattr(self.s, attr)

def set_use_cert_authority(use):
    global CA_PATH
    global use_cert_authority
    use_cert_authority = use
    if use_cert_authority:
        for path in sys.path:
            cert = os.path.join(path, "ca-bundle.crt")
            if os.path.exists(cert):
                CA_PATH = cert
                break
            cert = os.path.join(path, "curl-ca-bundle.crt")
            if os.path.exists(cert):
                CA_PATH = cert
                break
        else:
            raise ImportError("Certificate Authority never found!")


def set_http_bindaddr(bindaddr):
    global http_bindaddr
    http_bindaddr = bindaddr

def set_user_agent(new_user_agent):
    global user_agent
    user_agent = new_user_agent

def set_use_compression(use):
    global use_compression
    use_compression = use

def set_timeout(t):
    global timeout
    timeout = t

def set_connect_timeout(t):
    global connect_timeout
    connect_timeout = t

def urlopen(req, close=True):
    if isinstance(req, str):
        req = Request(req)

    response = StringIO2()

    if DEBUG:
        req.c.setopt(req.c.VERBOSE, 1)

    req.c.setopt(req.c.WRITEFUNCTION, response.write)

    if req.headers:
        req.c.setopt(req.c.HTTPHEADER, req._make_headers())

    req.c.perform()
    response.seek(-1)

    #print repr(response.getvalue())

    response.code = req.c.getinfo(pycurl.RESPONSE_CODE)
    response.code = int(response.code)
    try:
        response.msg = BaseHTTPRequestHandler.responses[response.code][0]
    except:
        response.msg = "No Reason"

    response.content_type = req.c.getinfo(pycurl.CONTENT_TYPE)

    if close:
        req.c.close()

    return response

class Request(object):
    def __init__(self, url):
        self.c = pycurl.Curl()
        self.headers = {}
        self.c.setopt(self.c.URL, url)

        if use_cert_authority:
            self.c.setopt(pycurl.CAINFO, CA_PATH)
            self.c.setopt(pycurl.CAPATH, CA_PATH)
            #self.c.setopt(pycurl.CA_BUNDLE, CA_PATH)
        else:
            self.c.setopt(pycurl.SSL_VERIFYHOST, 0)
            self.c.setopt(pycurl.SSL_VERIFYPEER, 0)

        if http_bindaddr:
            self.c.setopt(self.c.INTERFACE, http_bindaddr)

        if user_agent:
            self.c.setopt(pycurl.USERAGENT, user_agent)

        if use_compression:
            if _pycurl_compression:
                # If a zero-length string is set, then an Accept-Encoding header
                # containing all supported encodings is sent.
                self.c.setopt(pycurl.ENCODING, "")
            # someday, gzip manually with GzipFile
            #else:
            #    self.add_header("Accept-Encoding", "gzip")

        if timeout:
            self.c.setopt(self.c.TIMEOUT, timeout)
        if connect_timeout:
            self.c.setopt(self.c.CONNECTTIMEOUT, timeout)


    def set_url(self, url):
        self.c.setopt(self.c.URL, url)

    def set_timeout(self, timeout):
        self.c.setopt(self.c.TIMEOUT, timeout)

    def set_connect_timeout(self, timeout):
        self.c.setopt(self.c.CONNECTTIMEOUT, timeout)

    def add_header(self, var, val):
        self.headers[var] = val

    def _make_headers(self):
        headers = []

        for k,v in self.headers.iteritems():
            headers.append(("%s: %s" % (k, v)))
        # turn off nasty bad evil crap
        headers.append("Expect:")

        return headers

    def add_data(self, data):
        self.data = StringIO2()
        self.data.write(data)
        self.data.seek(-1)

        self.c.setopt(pycurl.POST, 1)
        self.c.setopt(self.c.READFUNCTION, self.data.read)
        self.c.setopt(self.c.POSTFIELDSIZE, len(data))

