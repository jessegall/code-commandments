### Never swallow a failure

A `catch` whose body is empty, or only returns `null`, `false`, an empty string or an empty collection,
turns a failure into a value that looks like an answer. The caller cannot tell "nothing there" from
"something broke". Let the failure travel, or catch it at one named boundary that logs it and moves on.

### Catch only to do something

A `catch` whose whole body throws back the failure it caught does what writing no clause does. Delete
it, or give it work: roll back, release what was held, or throw a failure of your own with this one as
its cause.

### A wrapped failure keeps its cause

When a `catch` throws a new failure in place of the caught one, hand the caught one over as the cause
(`previous: $e`, `new X(msg, e)`, `{ cause: e }`, `raise X(...) from e`). Passing only its message
drops the trace that says where it started.

### Throw a named failure, and let it write its own message

`throw new Exception("…")` and `raise RuntimeError("…")` say nothing a caller can catch by name. Give
the failure a type of its own, and a named constructor that takes the values involved and writes the
sentence, so the message is written once, where the failure is defined, not at every throw.
