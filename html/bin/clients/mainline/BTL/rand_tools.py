import random

def iter_rand_pos(s):
    if not isinstance(s, list):
        s = list(s)
    if len(s) == 0:
        return
    i = random.randrange(len(s))
    for x in xrange(len(s)):
        yield s[x-i]
