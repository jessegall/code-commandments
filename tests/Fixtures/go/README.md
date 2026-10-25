# The Go fixture

A coherent Go program that is **never run**, only parsed and judged. The markers in it ARE the spec: a
`// @sin Name` comment says the detector for that sin must flag the code the comment leads, `// @righteous Name`
a look-alike it must not flag, and `// @fixed Name` the same code repaired the way the rule says.

Two things are Go's own, and a fixture here has to respect both.

**A marker is its own comment group.** Go groups the lines of a comment, and the harness reads a group's whole
text, so a marker written inside a group of prose says nothing. Write it on its own.

**A marker above a declaration needs a blank line after it.** A `//` group directly above a `func` or a `type` is
that declaration's documentation to Go's own parser, and the tree says so, which would make every marked
declaration read as documented. A blank line between the two leaves the marker a comment of its own. Inside a
body there is no documentation to be, so a marker above a statement needs nothing.

The module is its own, so `go build ./...` from the repository root never reaches it. It is read live rather than
frozen as the PHP fixture is: this bridge runs in the same process as the engine, so reading the whole fixture
costs milliseconds and a frozen copy would only be a second thing to keep current.
