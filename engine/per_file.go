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

func Contribution[T any](key any, file *File, build func(*File) T) T {
	at := contribution{analysis: key, file: file.File}

	if held, ok := perFile.Load(at); ok {
		return held.(T)
	}

	CountedContribution()

	held, _ := perFile.LoadOrStore(at, build(file))

	return held.(T)
}

func Forgotten() int {
	count := 0
	perFile.Range(func(key, _ any) bool {
		perFile.Delete(key)
		count++

		return true
	})

	return count
}
