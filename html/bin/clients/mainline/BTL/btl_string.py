
# author: David Harrison

def split( s, delimiter = ' ', quote=['"',"'"], keep_quote = True):
    """analogous to str.split() except it supports quoted strings.

       Delimiter can be any positive length string.

       A quote begins on any character in 'quote', and ends on that
       same character.  A quoted string is not split even if it
       contains character c or other quote characters in the quote
       argument.

       Iff keep_quote is true then quote's leading and trailing
       quote characters are left in the strings in the returned list."""
    assert type(s) == str
    assert type(delimiter) == str and len(delimiter) >= 1, "c='%s'" % c
    l = []
    sub = []
    quoted = None
    i = 0
    while i < len(s):
        c = s[i]
        # check for end-quote
        if quoted:
            if c == quoted:
              quoted = None
              if keep_quote:
                sub.append(c)
            else:
              sub.append(c)
        # check for start-quote.
        elif c in quote:
            quoted = c
            if keep_quote:
                sub.append(c)
        elif s[i:i+len(delimiter)] != delimiter:
            sub.append(c)
        else:
            i += (len(delimiter)-1)
            l.append("".join(sub))
            sub = []
        i += 1
    l.append("".join(sub))
    return l


def remove(s,c):
  l = [i for i in s if i != c]
  return "".join(l)

def printable(s):
    """make a string printable.  Converts all non-printable ascii characters and all
       non-space whitespace to periods.  This keeps a string to a fixed width when
       printing it.  This is not meant for canonicalization.  It is far more
       restrictive since it removes many things that might be representable.
       It is appropriate for generating debug output binary strings that might
       contain ascii substrings, like peer-id's.  It explicitly excludes quotes
       and double quotes so that the string can be enclosed in quotes.
       """
    l = []
    for c in s:
        if ord(c) >= 0x20 and ord(c) < 0x7F and c != '"' and c != "'":
            l.append(c)
        else:
            l.append('.')
    return "".join(l)

def str2(s, default = "<not str convertable>" ):
    """converts passed object to a printable string, to repr, or
       returns provided default in that order of precendence."""
    try:
        return printable(str(s))
    except:
        try:
            return repr(s)
        except:
            return default



if __name__ == "__main__":
    assert split( "" ) == [''], split( "" )
    assert split( "a b c" ) == ['a','b','c'], split( "a b c" )
    assert split( "a" ) == ['a'], split( "a" )
    assert split( " a", ',' ) == [' a'], split( " a", ',')
    assert split( "a,b,c", ',' ) == ['a','b','c'], split( "a,b,c", ',' )
    assert split( "a,b,", ',' ) == ['a','b',''], split( "a,b,", ',' )
    assert split( "'a',b", ',' ) == ["'a'",'b'], split( "'a',b", ',' )
    assert split( "'a,b'", ',' ) == ["'a,b'"], split( "'a,b'", ',' )
    assert split( "a,'b,\"cd\",e',f", ',', keep_quote=False) == ['a', 'b,"cd",e', 'f']
    assert split( 'a,"b,\'cd\',e",f', ',', keep_quote=False) == ['a', "b,'cd',e", 'f']
    assert split( "a - b - c", " - " ) == ['a','b','c'], split( "a - b - c", " - " )
    s = "Aug 19 06:26:29 tracker-01 hypertracker.event - 6140 - INFO - ihash=ed25f"
    assert split( s, ' - ' ) == ['Aug 19 06:26:29 tracker-01 hypertracker.event',
                                 '6140', 'INFO', 'ihash=ed25f'], split( s, ' - ')

    assert str2('foo') == 'foo'
    assert str2(u'foo') == 'foo'
    assert str2(None) == "None"

    print "passed all tests"

