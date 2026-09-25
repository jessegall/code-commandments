A wrong value is almost always wrong where it was born. The call site that trips over it is only
where it surfaced; patching there adds a check, the next caller trips on the same problem, and the root cause
is never fixed. In Python the same move applies to objects and state: build an object without changing the
world, and keep changing state on an instance someone owns, so every effect has a place you can see.