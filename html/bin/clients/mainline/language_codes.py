import sys
from BTL.language import language_names

if '-a' in sys.argv:
    from BTL.language import unfinished_language_names
    language_names.update(unfinished_language_names)

languages = language_names.keys()
languages.sort()

print ' '.join(languages)
