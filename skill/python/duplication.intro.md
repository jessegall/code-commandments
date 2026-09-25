A function written twice is one decision living in two places. The day it has to
change, one copy gets the fix and the other keeps the bug — and nothing in either file
says the other exists. In a Python codebase it happens one module at a time: each
handler grows its own `_read_json`, each command its own `_format_row`, until the
behaviour the program depends on is spread across files that do not know about each other.