package engine

import "sync"

var perFile sync.Map

// contribution is one analysis's answer about one file, held against the file itself rather than the codebase: a
// view shares its base's files by pointer where the text is unchanged and holds a new one where a worker wrote, so
// an answer kept this way can never speak about text that is gone.
type contribution struct {
	analysis any
	file     any
}
