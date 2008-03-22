from BTL import buffer
from BTL import ebencode
from BTL.reactor_magic import reactor
from twisted.internet import protocol
from BTL.protocol import SmartReconnectingClientFactory
from BTL.decorate import decorate_func


class RepeaterProtocol(protocol.Protocol):

    def connectionMade(self):
        self.factory.children.add(self)

    def connectionLost(self, reason):
        self.factory.children.remove(self)
        

def EBRPC_ReplicationServer(port, ebrpc):
    factory = protocol.Factory()
    factory.children = set()
    factory.protocol = RepeaterProtocol
    def render(request):
        request.content.seek(0, 0)
        if hasattr(request.content, 'getvalue'):
            c = request.content.getvalue()
            for child in factory.children:
                # don't worry, write is non-blocking
                child.transport.write(ebencode.make_int(len(c)))
                child.transport.write(c)
        else:
            c = buffer.Buffer()
            while True:
                b = request.content.read(100000)
                if len(b) == 0:
                    break
                c.write(b)
            c = str(c)
            for child in factory.children:
                # don't worry, write is non-blocking
                child.transport.write(ebencode.make_int(len(c)))
                child.transport.write(c)
            request.content.seek(0, 0)                    
    ebrpc.render = decorate_func(render, ebrpc.render)
    reactor.listenTCP(port, factory)
    

class ReplicationListener(protocol.Protocol):

    def connectionMade(self):
        self.length = None
        self.buffer = buffer.Buffer()

    def dataReceived(self, data):
        self.buffer.write(data)

        while True:

            if self.length is None:
                try:
                    self.length, pos = ebencode.read_int(self.buffer, 0)
                except IndexError:
                    return
                self.buffer.drop(pos)
                
            if self.length is not None:
                # look for payload
                if len(self.buffer) < self.length:
                    break
                self.payloadReceived(self.buffer[:self.length])
                self.buffer.drop(self.length)
                self.length = None

    def payloadReceived(self, payload):
        """Override this for when each payload is received.
        """
        raise NotImplementedError


class RegurgitatingReplicationListener(ReplicationListener):

    def payloadReceived(self, payload):
        from BTL import ebrpc

        args, functionPath = ebrpc.loads(payload)
        args, kwargs = args
        function = self.factory.ebrpc._getFunction(functionPath)
        function(*args, **kwargs)


def EBRPC_ListenerAdaptor(ebrpc):
    factory = SmartReconnectingClientFactory()
    factory.ebrpc = ebrpc
    factory.protocol = RegurgitatingReplicationListener
    return factory
    
    

if __name__ == '__main__':
    from BTL.reactor_magic import reactor

    ## server
    from BTL import twisted_ebrpc, replication
    from twisted.web import server
    class Server(twisted_ebrpc.EBRPC):
        def ebrpc_ping(self, *args):
            print 'server got: ping(%s)' % repr(args)
            assert args == (1002,)
            return args
    r = Server()
    replication.EBRPC_ReplicationServer(9000, r)
    reactor.listenTCP(7080, server.Site(r))


    ## listener 1 (simple)
    from twisted.internet.protocol import ClientFactory
    from BTL import replication
    from BTL.ebencode import ebdecode
    class PrintingReplicationListener(replication.ReplicationListener):
        def payloadReceived(self, payload):
            payload = ebdecode(payload)
            print 'listener got:', payload
            assert payload == {'a': [[1002], {}], 'q': 'ping', 'y': 'q'}
    factory = ClientFactory()
    factory.protocol = PrintingReplicationListener
    reactor.connectTCP('127.0.0.1', 9000, factory)


    ## listener 2 (ebrpc)  
    from BTL import twisted_ebrpc, replication
    from twisted.web import server
    class Server(twisted_ebrpc.EBRPC):
        def ebrpc_ping(self, *args):
            print 'listener got: ping(%s)' % repr(args)
            assert args == (1002,)
            return args
    r = Server()
    r = replication.EBRPC_ListenerAdaptor(r)
    reactor.connectTCP('127.0.0.1', 9000, r)


    ## client
    from BTL.twisted_ebrpc import AsyncServerProxy
    df = AsyncServerProxy("http://127.0.0.1:7080").ping(1002)
    def done(r):
        print 'client got:', r
        assert r == [1002]
        reactor.callLater(0.5, reactor.stop)
    df.addCallback(done)
    df.addErrback(done)


    reactor.run()
