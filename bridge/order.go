package bridge

import (
	"os"
	"path/filepath"
	"slices"

	"github.com/jessegall/code-commandments/contract"
	"github.com/jessegall/code-commandments/listing"
)

func InWalkOrder(stream *contract.Stream, paths []string) {
	at := map[string]int{}

	for _, path := range paths {
		walk(path, at)
	}

	ranks := make(map[*contract.File]int, len(stream.Files))
	for _, file := range stream.Files {
		ranks[file] = rank(at, file.Path)
	}

	slices.SortStableFunc(stream.Files, func(a, b *contract.File) int {
		return ranks[a] - ranks[b]
	})
}

func walk(path string, at map[string]int) {
	info, err := os.Stat(path)
	if err != nil {
		return
	}

	if !info.IsDir() {
		if real := resolved(path); real != "" {
			if _, seen := at[real]; !seen {
				at[real] = len(at)
			}
		}

		return
	}

	for _, name := range listing.Of(path) {
		walk(filepath.Join(path, name), at)
	}
}

func rank(at map[string]int, path string) int {
	if i, met := at[resolved(path)]; met {
		return i
	}

	return len(at)
}

func resolved(path string) string {
	real, err := filepath.EvalSymlinks(path)
	if err != nil {
		return path
	}

	absolute, err := filepath.Abs(real)
	if err != nil {
		return real
	}

	return absolute
}
