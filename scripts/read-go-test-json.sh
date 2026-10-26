#!/bin/sh
# The journal runs a reader's script with sh, whatever its shebang says, and keeps a copy of its own, so this is
# the shell entry that hands the stream to the reader itself. It runs from the project's root.
exec python3 scripts/read-go-test-json
