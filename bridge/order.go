package bridge

import (
	"slices"

	"github.com/jessegall/code-commandments/contract"
	"github.com/jessegall/code-commandments/walk"
)

func InWalkOrder(stream *contract.Stream, paths []string) {
	at := map[string]int{}

	for _, path := range paths {
		for _, file := range walk.Files(path, walk.Excluded{}, func(string) bool { return true }) {
			if real := walk.Real(file); real != "" {
				if _, seen := at[real]; !seen {
					at[real] = len(at)
				}
			}
		}
	}

	ranks := make(map[*contract.File]int, len(stream.Files))
	for _, file := range stream.Files {
		ranks[file] = rank(at, file.Path)
	}

	slices.SortStableFunc(stream.Files, func(a, b *contract.File) int {
		return ranks[a] - ranks[b]
	})
}

func rank(at map[string]int, path string) int {
	if i, met := at[walk.Real(path)]; met {
		return i
	}

	return len(at)
}
