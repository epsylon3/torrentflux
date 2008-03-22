
def str_exc(e):
    try:
        # python 2.5 does this right!
        s = unicode(e)
    except:
        try:
            s = unicode(e.args[0])
        except:
            s = str(e)
    if ' : ' not in s:
        try:
            s = '%s : %s' % (e.__class__, s)
        except Exception, f:
            s = repr(e)
    return s    

def str_fault(e):
    if hasattr(e, 'faultString'):
        msg = e.faultString
    else:
        msg = str_exc(e)
    return msg

