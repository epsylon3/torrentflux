"""A generic resource for publishing objects via BRPC.

Requires BRPC

API Stability: semi-stable
"""
from __future__ import nested_scopes

__version__ = "$Revision$"[11:-2]

# System Imports
import brpc
import urlparse
from cStringIO import StringIO
from gzip import GzipFile

pipeline_debug = False

version = "1.0"
from BTL.platform import app_name
from BTL.reactor_magic import reactor
from BTL.exceptions import str_exc
from BTL.protocol import SmartReconnectingClientFactory
from BTL.brpclib import ServerProxy

import twisted.web
if twisted.web.__version__ < '0.6.0':
    raise ImportError("BTL.twisted_brpc requires twisted.web 0.6.0 or greater,"
                      " from Twisted 2.4.0.\nYou appear to have twisted.web "
                      "version %s installed at:\n%s" % (twisted.web.__version__,
                                                        twisted.web.__file__))

from twisted.web import resource, server
from twisted.internet import protocol
from twisted.python import log, reflect, failure
from twisted.web import http
from twisted.internet import defer

# Useful so people don't need to import brpc directly
Fault = brpc.Fault

class NoSuchFunction(Fault):
    """There is no function by the given name."""
    pass


class Handler:
    """Handle a BRPC request and store the state for a request in progress.

    Override the run() method and return result using self.result,
    a Deferred.

    We require this class since we're not using threads, so we can't
    encapsulate state in a running function if we're going  to have
    to wait for results.

    For example, lets say we want to authenticate against twisted.cred,
    run a LDAP query and then pass its result to a database query, all
    as a result of a single BRPC command. We'd use a Handler instance
    to store the state of the running command.
    """

    def __init__(self, resource, *args):
        self.resource = resource # the BRPC resource we are connected to
        self.result = defer.Deferred()
        self.run(*args)

    def run(self, *args):
        # event driven equivalent of 'raise UnimplementedError'
        try:
            raise NotImplementedError("Implement run() in subclasses")
        except:
            self.result.errback(failure.Failure())

def parse_accept_encoding(header):
    a = header.split(',')
    l = []
    for i in a:
        i = i.strip()
        if ';' not in i:
            type = i
            # hmmm
            l.append(('1', type))
        else:
            type, q = i.split(';')
            type = type.strip()
            q = q.strip()
            junk, q = q.split('=')
            q = q.strip()
            if q != '0':
                l.append((q, type))
    l.sort()
    l.reverse()
    l = [ t for q, t in l ]
    return l



class BRPC(resource.Resource):
    """A resource that implements BRPC.

    You probably want to connect this to '/RPC2'.

    Methods published can return BRPC serializable results, Faults,
    Binary, Boolean, DateTime, Deferreds, or Handler instances.

    By default methods beginning with 'brpc_' are published.

    Sub-handlers for prefixed methods (e.g., system.listMethods)
    can be added with putSubHandler. By default, prefixes are
    separated with a '.'. Override self.separator to change this.
    """

    # Error codes for Twisted, if they conflict with yours then
    # modify them at runtime.
    NOT_FOUND = 8001
    FAILURE = 8002

    isLeaf = 1
    separator = '.'

    def __init__(self):
        resource.Resource.__init__(self)
        self.subHandlers = {}

    def putSubHandler(self, prefix, handler):
        self.subHandlers[prefix] = handler

    def getSubHandler(self, prefix):
        return self.subHandlers.get(prefix, None)

    def getSubHandlerPrefixes(self):
        return self.subHandlers.keys()

    def _err(self, *a, **kw):
        log.err(*a, **kw)

    def render(self, request):
        request.setHeader('server', "%s/%s" % (app_name, version))
        request.content.seek(0, 0)
        args, functionPath = brpc.loads(request.content.read())
        args, kwargs = args
        request.functionPath = functionPath
        try:
            function = self._getFunction(functionPath)
        except Fault, f:
            self._cbRender(f, request)
        else:
            request.setHeader("content-type", "application/octet-stream")
            defer.maybeDeferred(function, *args, **kwargs).addErrback(
                self._ebRender
            ).addCallback(
                self._cbRender, request
            )
        return server.NOT_DONE_YET

    def _cbRender(self, result, request):
        if isinstance(result, Handler):
            result = result.result
        if not isinstance(result, Fault):
            result = (result,)

        try:
            s = brpc.dumps(result, methodresponse=1)
        except Exception, e:
            f = Fault(self.FAILURE,
                      "function:%s can't serialize output: %s" %
                      (request.functionPath, str_exc(e)))
            self._err(f)
            s = brpc.dumps(f, methodresponse=1)

        encoding = request.getHeader("accept-encoding")
        if encoding:
            encodings = parse_accept_encoding(encoding)
            if 'gzip' in encodings or '*' in encodings:
                sio = StringIO()
                g = GzipFile(fileobj=sio, mode='wb', compresslevel=9)
                g.write(s)
                g.close()
                s = sio.getvalue()
                request.setHeader("Content-Encoding", "gzip")

        request.setHeader("content-length", str(len(s)))
        request.write(s)
        request.finish()

    def _ebRender(self, failure):
        self._err(failure)
        if isinstance(failure.value, Fault):
            return failure.value
        return Fault(self.FAILURE, "An unhandled exception occurred: %s" %
                                   failure.getErrorMessage())

    def _getFunction(self, functionPath):
        """Given a string, return a function, or raise NoSuchFunction.

        This returned function will be called, and should return the result
        of the call, a Deferred, or a Fault instance.

        Override in subclasses if you want your own policy. The default
        policy is that given functionPath 'foo', return the method at
        self.brpc_foo, i.e. getattr(self, "brpc_" + functionPath).
        If functionPath contains self.separator, the sub-handler for
        the initial prefix is used to search for the remaining path.
        """
        if functionPath.find(self.separator) != -1:
            prefix, functionPath = functionPath.split(self.separator, 1)
            handler = self.getSubHandler(prefix)
            if handler is None: raise NoSuchFunction(self.NOT_FOUND, "no such subHandler %s" % prefix)
            return handler._getFunction(functionPath)

        f = getattr(self, "brpc_%s" % functionPath, None)
        if not f:
            raise NoSuchFunction(self.NOT_FOUND, "function %s not found" % functionPath)
        elif not callable(f):
            raise NoSuchFunction(self.NOT_FOUND, "function %s not callable" % functionPath)
        else:
            return f

    def _listFunctions(self):
        """Return a list of the names of all brpc methods."""
        return reflect.prefixedMethodNames(self.__class__, 'brpc_')


class BRPCIntrospection(BRPC):
    """Implement the BRPC Introspection API.

    By default, the methodHelp method returns the 'help' method attribute,
    if it exists, otherwise the __doc__ method attribute, if it exists,
    otherwise the empty string.

    To enable the methodSignature method, add a 'signature' method attribute
    containing a list of lists. See methodSignature's documentation for the
    format. Note the type strings should be BRPC types, not Python types.
    """

    def __init__(self, parent):
        """Implement Introspection support for an BRPC server.

        @param parent: the BRPC server to add Introspection support to.
        """

        BRPC.__init__(self)
        self._brpc_parent = parent

    def brpc_listMethods(self):
        """Return a list of the method names implemented by this server."""
        functions = []
        todo = [(self._brpc_parent, '')]
        while todo:
            obj, prefix = todo.pop(0)
            functions.extend([ prefix + name for name in obj._listFunctions() ])
            todo.extend([ (obj.getSubHandler(name),
                           prefix + name + obj.separator)
                          for name in obj.getSubHandlerPrefixes() ])
        return functions

    brpc_listMethods.signature = [['array']]

    def brpc_methodHelp(self, method):
        """Return a documentation string describing the use of the given method.
        """
        method = self._brpc_parent._getFunction(method)
        return (getattr(method, 'help', None)
                or getattr(method, '__doc__', None) or '')

    brpc_methodHelp.signature = [['string', 'string']]

    def brpc_methodSignature(self, method):
        """Return a list of type signatures.

        Each type signature is a list of the form [rtype, type1, type2, ...]
        where rtype is the return type and typeN is the type of the Nth
        argument. If no signature information is available, the empty
        string is returned.
        """
        method = self._brpc_parent._getFunction(method)
        return getattr(method, 'signature', None) or ''

    brpc_methodSignature.signature = [['array', 'string'],
                                        ['string', 'string']]


def addIntrospection(brpc):
    """Add Introspection support to an BRPC server.

    @param brpc: The brpc server to add Introspection support to.
    """
    brpc.putSubHandler('system', BRPCIntrospection(brpc))


class Query(object):

    def __init__(self, path, host, method, user=None, password=None, *args):
        self.path = path
        self.host = host
        self.user = user
        self.password = password
        self.method = method
        self.payload = brpc.dumps(args, method)
        self.deferred = defer.Deferred()
        self.decode = False

class QueryProtocol(http.HTTPClient):

    # All current queries are pipelined over the connection at
    # once. When the connection is made, or as queries are made
    # while a connection exists, queries are all sent to the
    # server.  Pipelining limits can be controlled by the caller.
    # When a query completes (see parseResponse), if there are no
    # more queries then an idle timeout gets sets.
    # The QueryFactory reopens the connection if another query occurs.
    #
    # twisted_brpc does currently provide a mechanism for 
    # per-query timeouts.   This could be added with another
    # timeout_call mechanism that calls loseConnection and pops the
    # current query with an errback.
    timeout = 300   # idle timeout.

    def log(self, msg, *a):
        print "%s: %s: %r" % (self.peer, msg, a)

    def connectionMade(self):
        http.HTTPClient.connectionMade(self)
        self.current_queries = []
        self.timeout_call = None
        if pipeline_debug:
            p = self.transport.getPeer()
            p = "%s:%d" % (p.host, p.port)
            self.peer = (id(self.transport), p)
        self.factory.connectionMade(self)

    def _cancelTimeout(self):
        if self.timeout_call and self.timeout_call.active():
            self.timeout_call.cancel()
        self.timeout_call = None

    def connectionLost(self, reason):
        http.HTTPClient.connectionLost(self, reason)
        if pipeline_debug: self.log('connectionLost', reason.getErrorMessage())
        self._cancelTimeout()
        if self.current_queries:
            # queries failed, put them back
            if pipeline_debug: self.log('putting back', [q.method for q in self.current_queries])
            self.factory.prependQueries(self.current_queries)
        self.factory.connectionLost(self)

    def sendCommand(self, command, path):
        self.transport.write('%s %s HTTP/1.1\r\n' % (command, path))

    def setLineMode(self, rest):
        # twisted is stupid.
        self.firstLine = 1
        return http.HTTPClient.setLineMode(self, rest)
    
    def sendQuery(self):
        self._cancelTimeout()

        query = self.factory.popQuery()
        if pipeline_debug: self.log('sending', query.method)
        self.current_queries.append(query)
        self.sendCommand('POST', query.path)
        self.sendHeader('User-Agent', 'BTL/BRPC 1.0')
        self.sendHeader('Host', query.host)
        self.sendHeader('Accept-encoding', 'gzip')
        self.sendHeader('Connection', 'Keep-Alive')
        self.sendHeader('Content-type', 'application/octet-stream')
        self.sendHeader('Content-length', str(len(query.payload)))
        #if query.user:
        #    auth = '%s:%s' % (query.user, query.password)
        #    auth = auth.encode('base64').strip()
        #    self.sendHeader('Authorization', 'Basic %s' % (auth,))
        self.endHeaders()
        self.transport.write(query.payload)

    def parseResponse(self, contents):
        query = self.current_queries.pop(0)
        if pipeline_debug: self.log('responded', query.method)

        if not self.current_queries:
            assert not self.factory.anyQueries()
            assert not self.timeout_call
            self.timeout_call = reactor.callLater(self.timeout,
                                                  self.transport.loseConnection)

        try:
            response = brpc.loads(contents)
        except Exception, e:
            query.deferred.errback(failure.Failure())
            del query.deferred
        else:
            query.deferred.callback(response[0][0])
            del query.deferred

    def badStatus(self, status, message):
        query = self.current_queries.pop(0)
        if pipeline_debug: self.log('failed', query.method)
        try:
            raise ValueError(status, message)
        except:
            query.deferred.errback(failure.Failure())
        del query.deferred
        self.transport.loseConnection()

    def handleStatus(self, version, status, message):
        if status != '200':
            self.badStatus(status, message)

    def handleHeader(self, key, val):
        if not self.current_queries[0].decode:
            if key.lower() == 'content-encoding' and val.lower() == 'gzip':
                self.current_queries[0].decode = True

    def handleResponse(self, contents):
        if self.current_queries[0].decode:
            s = StringIO()
            s.write(contents)
            s.seek(-1)
            g = GzipFile(fileobj=s, mode='rb')
            contents = g.read()
            g.close()
        self.parseResponse(contents)


class QueryFactory(object):

    def __init__(self):
        self.queries = []
        self.instance = None

    def connectionMade(self, instance):
        self.instance = instance
        if pipeline_debug: print 'connection made %s' % str(instance.peer)
        while self.anyQueries():
            self.instance.sendQuery()

    def connectionLost(self, instance):
        assert self.instance == instance
        if pipeline_debug: print 'connection lost %s' % str(instance.peer)
        self.instance = None

    def prependQueries(self, queries):
        self.queries = queries + self.queries

    def popQuery(self):
        return self.queries.pop(0)

    def anyQueries(self):
        return bool(self.queries)

    def addQuery(self, query):
        self.queries.append(query)
        if pipeline_debug: print 'addQuery: %s %s' % (self.instance, self.queries)
        if self.instance:
            self.instance.sendQuery()

    def disconnect(self):
        if not self.instance:
            return
        if not hasattr(self.instance, 'transport'):
            return
        self.instance.transport.loseConnection()        
        

class PersistantSingletonFactory(QueryFactory, SmartReconnectingClientFactory):

    def clientConnectionFailed(self, connector, reason):
        if pipeline_debug: print 'clientConnectionFailed %s' % str(connector)
        return SmartReconnectingClientFactory.clientConnectionFailed(self, connector, reason)

    def clientConnectionLost(self, connector, unused_reason):
        self.started = False
        if not self.anyQueries():
            self.continueTrying = False
        return SmartReconnectingClientFactory.clientConnectionLost(self, connector, unused_reason)


class SingletonFactory(QueryFactory, protocol.ClientFactory):

    def clientConnectionFailed(self, connector, reason):
        if pipeline_debug: print 'clientConnectionFailed %s' % str(connector)
        queries = list(self.queries)
        del self.queries[:]
        for query in queries:
            query.deferred.errback(reason)
        self.started = False


class Proxy:
    """A Proxy for making remote BRPC calls.

    Pass the URL of the remote BRPC server to the constructor.

    Use proxy.callRemote('foobar', *args) to call remote method
    'foobar' with *args.

    """

    def __init__(self, url, user=None, password=None, retry_forever = True):
        """
        @type url: C{str}
        @param url: The URL to which to post method calls.  Calls will be made
        over SSL if the scheme is HTTPS.  If netloc contains username or
        password information, these will be used to authenticate, as long as
        the C{user} and C{password} arguments are not specified.

        @type user: C{str} or None
        @param user: The username with which to authenticate with the server
        when making calls.  If specified, overrides any username information
        embedded in C{url}.  If not specified, a value may be taken from C{url}
        if present.

        @type password: C{str} or None
        @param password: The password with which to authenticate with the
        server when making calls.  If specified, overrides any password
        information embedded in C{url}.  If not specified, a value may be taken
        from C{url} if present.
        """
        scheme, netloc, path, params, query, fragment = urlparse.urlparse(url)
        netlocParts = netloc.split('@')
        if len(netlocParts) == 2:
            userpass = netlocParts.pop(0).split(':')
            self.user = userpass.pop(0)
            try:
                self.password = userpass.pop(0)
            except:
                self.password = None
        else:
            self.user = self.password = None
        hostport = netlocParts[0].split(':')
        self.host = hostport.pop(0)
        try:
            self.port = int(hostport.pop(0))
        except:
            self.port = None
        self.path = path
        if self.path in ['', None]:
            self.path = '/'
        self.secure = (scheme == 'https')
        if user is not None:
            self.user = user
        if password is not None:
            self.password = password

        if not retry_forever:
            _Factory = SingletonFactory
        else:
            _Factory = PersistantSingletonFactory 
        self.factory = _Factory()
        self.factory.started = False
        self.factory.protocol = QueryProtocol

    def callRemote(self, method, *args, **kwargs):
        if pipeline_debug: print 'callRemote to %s : %s' % (self.host, method)
        args = (args, kwargs)
        query = Query(self.path, self.host, method, self.user,
                      self.password, *args)
        self.factory.addQuery(query)

        if pipeline_debug: print 'factory started: %s' % self.factory.started
        if not self.factory.started:
            self.factory.started = True
            def connect(host):
                if self.secure:
                    if pipeline_debug: print 'connecting to %s' % str((host, self.port or 443))
                    from twisted.internet import ssl
                    reactor.connectSSL(host, self.port or 443,
                                       self.factory, ssl.ClientContextFactory(),
                                       timeout=60)
                else:
                    if pipeline_debug: print 'connecting to %s' % str((host, self.port or 80))
                    reactor.connectTCP(host, self.port or 80, self.factory,
                                       timeout=60)
            df = reactor.resolve(self.host)
            df.addCallback(connect)
            df.addErrback(query.deferred.errback)
        return query.deferred


class AsyncServerProxy(object):

    def __init__(self, base_url, username=None, password=None, debug=False, 
                 retry_forever = True):
        self.base_url = base_url
        self.username = username
        self.password = password
        self.proxy = Proxy(self.base_url, self.username, self.password, retry_forever)
        self.debug = debug

    def __getattr__(self, attr):
        return self._make_call(attr)

    def _make_call(self, methodname):
        return lambda *a, **kw : self._method(methodname, *a, **kw)

    def _method(self, methodname, *a, **kw):
        # in case they have changed
        self.proxy.user = self.username
        self.proxy.password = self.password
        if self.debug:
            print ('callRemote:', self.__class__.__name__,
                   self.base_url, methodname, a, kw)
        df = self.proxy.callRemote(methodname, *a, **kw)
        return df

class EitherServerProxy(object):
    SYNC = 0
    ASYNC = 1
    SYNC_DEFERRED = 2   # BE CAREFUL to call getResult() on the returned Deferred!

    """Server Proxy that supports both asynchronous and synchronous calls."""
    
    def __init__(self, base_url, username = None, password = None, debug = False,
            async = ASYNC, retry_forever = True ):
        """
           The EitherServerProxy can make either synchronous or asynchronous calls.
           The default is specified by the async parameter to __init__, but each
           individual call can override the default behavior by passing 'async' as 
           a boolean keyword argument to any method call.  The async keyword
           argument can also be set to None.  However, passing async as 
           None means simply 'use default behavior'.  When calling with async=SYNC, 
           you should not be in the same thread as the reactor or you risk 
           blocking the reactor.

           @param async: determines whether the default is asynchronous or blocking calls."""
        assert async in [SYNC, ASYNC, SYNC_DEFERRED]
        self.async = async 
        self.async_proxy = AsyncServerProxy( base_url, username, password, debug, 
                                             retry_forever = retry_forever )

        # HERE HACK. retry_forever is not supported by ServerProxy.
        self.sync_proxy = ServerProxy( base_url )

    def __getattr__(self, attr):
        return self._make_call(attr)

    def _make_call(self, methodname):
        return lambda *a, **kw : self._method(methodname, *a, **kw)

    def _method(self, methodname, *a, **kw ):
        async = kw.pop('async', self.async)
        if async is None:
            async = self.async
        if async == ASYNC:
            df = self.async_proxy._method(methodname, *a, **kw)
        elif async == SYNC_DEFERRED:
            df = defer.execute(getattr(self.sync_proxy, methodname), *a, **kw)
        else:
            return self.sync_proxy.__getattr__(methodname)(*a, **kw)
        return df

SYNC = EitherServerProxy.SYNC
ASYNC = EitherServerProxy.ASYNC
SYNC_DEFERRED = EitherServerProxy.SYNC_DEFERRED 

__all__ = ["BRPC", "Handler", "NoSuchFunction", "Fault", "Proxy", "AsyncServerProxy", "EitherServerProxy"]
