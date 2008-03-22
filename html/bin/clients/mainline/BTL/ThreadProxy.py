from BTL.defer import Deferred, defer_to_thread

class ThreadProxy(object):
    __slots__ = ('obj', 'local_queue_task', 'thread_queue_task')
    def __init__(self, obj, local_queue_task, thread_queue_task):
        self.obj = obj
        self.local_queue_task = local_queue_task
        self.thread_queue_task = thread_queue_task

    def __gen_call_wrapper__(self, f):
        def call_wrapper(*a, **kw):
            return defer_to_thread(self.local_queue_task, self.thread_queue_task,
                                   f, *a, **kw)
        return call_wrapper

    def __getattr__(self, attr):
        a = getattr(self.obj, attr)
        if callable(a):
            return self.__gen_call_wrapper__(a)
        return a

    def call_with_obj(self, _f, *a, **k):
        w = self.__gen_call_wrapper__(_f)
        return w(self.obj, *a, **k)


