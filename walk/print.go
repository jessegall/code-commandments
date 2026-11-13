package walk

import (
	"crypto/sha256"
	"os"
	"sync"
)

// prints holds the print of each file's contents with the mark it had when read: the cache keys a file's line on it and
// the rules remember what they judged by it, so a file is read for its print once however many ask, and again only once
// it changed.
var prints sync.Map

type heldPrint struct {
	mark  string
	print [32]byte
}

// Print is the sha256 of a file's contents, read again only when its size or time changed since it was last read.
func Print(path string) ([32]byte, error) {
	mark := Mark(path)
	if held, ok := prints.Load(path); ok && mark != "" && held.(heldPrint).mark == mark {
		return held.(heldPrint).print, nil
	}
	print, err := PrintNow(path)
	if err != nil {
		return print, err
	}
	prints.Store(path, heldPrint{mark, print})

	return print, nil
}

// PrintNow is the sha256 of a file's contents as they are now, read again, so a run can tell a file that changed while
// it ran.
func PrintNow(path string) ([32]byte, error) {
	text, err := os.ReadFile(path)
	if err != nil {
		return [32]byte{}, err
	}

	return sha256.Sum256(text), nil
}
