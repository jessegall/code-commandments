package fixture

import (
	"path/filepath"

	"github.com/jessegall/code-commandments/contract"
)

func TwiceOver(stream *contract.Stream) *contract.Stream {
	doubled := *stream
	doubled.Files = make([]*contract.File, 0, len(stream.Files)*2)
	doubled.Files = append(doubled.Files, stream.Files...)

	for _, file := range stream.Files {
		again := *file
		again.Path = file.Path + ".twin" + filepath.Ext(file.Path)
		doubled.Files = append(doubled.Files, &again)
	}

	return &doubled
}
