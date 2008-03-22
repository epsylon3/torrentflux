from cStringIO import StringIO
from gzip import GzipFile
from twisted.web.http import parseContentRange # package management sucks! if you have trouble with this line, stop using it!
from twisted.web import error
from twisted.web import client
from twisted.python import failure
from BTL.reactor_magic import reactor

class ProgressHTTPDownloader(client.HTTPDownloader):

    def __init__(self, url, file, progressCallback, *a, **kw):
        client.HTTPDownloader.__init__(self, url, file, *a, **kw)
        self.progressCallback = progressCallback
        self.written = 0

    def gotHeaders(self, headers):
        self.response_headers = headers
        client.HTTPDownloader.gotHeaders(self, headers)
        self.contentLength = headers.get("content-length", None)
        if self.contentLength is not None:
            self.contentLength = int(self.contentLength[0])
        
    def pagePart(self, data):
        if not self.file:
            return
        try:
            self.file.write(data)
            self.written += len(data)
            if self.progressCallback:
                self.progressCallback(self.written, self.contentLength)
        except IOError:
            #raise
            self.file = None
            self.deferred.errback(failure.Failure())


class HTTPPageUnGzip(client.HTTPPageGetter):

    decode = False
    # there are a lot of broken trackers out there...
    delimiter = '\n'

    def handleHeader(self, key, value):
        if not self.decode:
            if key.lower() == 'content-encoding' and value.lower() == 'gzip':
                self.decode = True
        return client.HTTPPageGetter.handleHeader(self, key, value)
                        
    def handleResponse(self, response):
        if self.quietLoss:
            return
        if self.failed:
            self.factory.noPage(
                failure.Failure(
                    error.Error(
                        self.status, self.message, response)))
        elif self.length != None and self.length != 0:
            self.factory.noPage(failure.Failure(
                client.PartialDownloadError(self.status, self.message, response)))
        else:
            if self.decode:
                s = StringIO()
                s.write(response)
                s.seek(-1)
                g = GzipFile(fileobj=s, mode='rb')
                try:
                    response = g.read()
                except IOError:
                    self.factory.noPage(failure.Failure(
                        client.PartialDownloadError(self.status, self.message, response)))
                    self.transport.loseConnection()
                    return
                g.close()
            self.factory.page(response)
        # server might be stupid and not close connection.
        self.transport.loseConnection()

    def lineReceived(self, line):
        return client.HTTPPageGetter.lineReceived(self, line.rstrip('\r'))

    
class HTTPProxyUnGzipClientFactory(client.HTTPClientFactory):

    protocol = HTTPPageUnGzip

    def __init__(self, url, method='GET', postdata=None, headers=None,
                 agent="Twisted PageGetter", timeout=0, cookies=None,
                 followRedirect=1, proxy=None):
        if headers is None:
            headers = {}
        headers['Accept-encoding'] = 'gzip'
        self.proxy = proxy
        client.HTTPClientFactory.__init__(self, url, method=method,
                                          postdata=postdata, headers=headers,
                                          agent=agent, timeout=timeout,
                                          cookies=cookies,
                                          followRedirect=followRedirect)
    
    def setURL(self, url):
        client.HTTPClientFactory.setURL(self, url)
        if self.proxy:
            self.path = "%s://%s:%s%s" % (self.scheme,  
                                          self.host,  
                                          self.port,  
                                          self.path)
        

def getPageFactory(url,
                   agent="BitTorrent client",
                   bindAddress=None,
                   contextFactory=None,
                   proxy=None,
                   timeout=120):
    """Download a web page as a string.

    Download a page. Return a deferred, which will callback with a
    page (as a string) or errback with a description of the error.

    See HTTPClientFactory to see what extra args can be passed.
    """
    scheme, host, port, path = client._parse(url)
    if proxy:
        host, port = proxy.split(':')
        port = int(port)
    factory = HTTPProxyUnGzipClientFactory(url, agent=agent, proxy=proxy)
    if scheme == 'https':
        from twisted.internet import ssl
        if contextFactory is None:
            contextFactory = ssl.ClientContextFactory()
        reactor.connectSSL(host, port, factory, contextFactory,
                           bindAddress=bindAddress,
                           timeout=timeout)
    else:
        reactor.connectTCP(host, port, factory,
                           bindAddress=bindAddress,
                           timeout=timeout)
    return factory


def downloadPageFactory(url, file, progressCallback=None,
                        agent="BitTorrent client",
                        bindAddress=None,
                        contextFactory=None):
    """Download a web page to a file.

    @param file: path to file on filesystem, or file-like object.
    """
    scheme, host, port, path = client._parse(url)
    factory = ProgressHTTPDownloader(url, file,
                                     progressCallback=progressCallback,
                                     agent=agent,
                                     supportPartial=0)
    if scheme == 'https':
        from twisted.internet import ssl
        if contextFactory is None:
            contextFactory = ssl.ClientContextFactory()
        reactor.connectSSL(host, port, factory, contextFactory,
                           bindAddress=bindAddress)
    else:
        reactor.connectTCP(host, port, factory,
                           bindAddress=bindAddress)
    return factory

