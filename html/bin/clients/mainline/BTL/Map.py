
try:
   from CMap import CMap, CIndexedMap
   Map = CMap
   IndexedMap = CIndexedMap
   
except:
   from PMap import PMap, PIndexedMap
   Map = PMap
   IndexedMap = PIndexedMap
   print "Using pure python version of Map.  Please compile CMap.\n"

try:
   from CMultiMap import CMultiMap, CIndexedMultiMap
   MultiMap = CMultiMap
   IndexedMultiMap = CIndexedMultiMap
except:
   print "Warning!! Please compile CMultiMap.  There is no pure "
   print "python version of MultiMap.\n"
