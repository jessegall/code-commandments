A wrong value is almost always wrong where it was made. The call site that fails is only where the
problem showed up; adding a check there leaves the next caller to fail the same way. The same goes for
objects and state in C#: creating an object shouldn't change anything outside it, and state that changes
belongs on an instance someone owns, so every effect happens somewhere you can see.