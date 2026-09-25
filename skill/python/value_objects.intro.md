A dict with string keys is a record nobody declared. Every reader re-learns its shape from
the code that happened to build it, a typo in a key is a `KeyError` at run time instead of an
error in the editor, and nothing says which keys are always there.