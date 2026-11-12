package contract

import "path/filepath"

func (s *Stream) TwiceOver() *Stream {
	doubled := *s
	doubled.Files = make([]*File, 0, len(s.Files)*2)
	doubled.Files = append(doubled.Files, s.Files...)

	for _, file := range s.Files {
		again := *file
		again.Path = file.Path + ".twin" + filepath.Ext(file.Path)
		doubled.Files = append(doubled.Files, &again)
	}

	return &doubled
}
