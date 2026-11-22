package marker

import (
	"bytes"
	"fmt"
	"path"

	"github.com/jessegall/code-commandments/contract"
)

// Source is one marked file of a sample: its path within the sample, its markers and how many lines it has.
type Source struct {
	Path    string
	Markers []Marker
	Lines   int
}

func ReadSource(language contract.Language, path string, source []byte) (Source, []error) {
	markers, faults := Read(language, path, source)

	return Source{Path: path, Markers: markers, Lines: bytes.Count(source, []byte{'\n'}) + 1}, faults
}

func (s Source) Labels() map[string]Marker {
	labels := map[string]Marker{}
	for _, marker := range s.Markers {
		if marker.Label != "" {
			if _, taken := labels[marker.Label]; !taken {
				labels[marker.Label] = marker
			}
		}
	}

	return labels
}

func CheckTargets(sources []Source) []error {
	byPath := map[string]Source{}
	for _, source := range sources {
		byPath[source.Path] = source
	}
	var faults []error
	for _, source := range sources {
		faults = append(faults, duplicateLabels(source)...)
		labels := source.Labels()
		for _, marker := range source.Markers {
			for _, target := range marker.Targets {
				if fault := targetFault(source, marker, target, labels, byPath); fault != nil {
					faults = append(faults, fault)
				}
			}
		}
	}

	return faults
}

func duplicateLabels(source Source) []error {
	var faults []error
	first := map[string]int{}
	for _, marker := range source.Markers {
		if marker.Label == "" {
			continue
		}
		if line, taken := first[marker.Label]; taken {
			faults = append(faults, fmt.Errorf("%s:%d: the label %s is defined twice, first on line %d", source.Path, marker.Line, marker.Label, line))

			continue
		}
		first[marker.Label] = marker.Line
	}

	return faults
}

func targetFault(source Source, marker Marker, target Target, labels map[string]Marker, byPath map[string]Source) error {
	switch {
	case target.Line > 0 && target.Line > source.Lines:
		return fmt.Errorf("%s:%d: line(%d,%s) is past the file's last line %d", source.Path, marker.Line, target.Line, target.Kind, source.Lines)
	case target.Line > 0:
		return nil
	case target.Path != "":
		other, found := byPath[path.Join(path.Dir(source.Path), target.Path)]
		if !found {
			return fmt.Errorf("%s:%d: %s#%s names a source the sample does not hold", source.Path, marker.Line, target.Path, target.Label)
		}
		if _, found := other.Labels()[target.Label]; !found {
			return fmt.Errorf("%s:%d: %s defines no label %s", source.Path, marker.Line, other.Path, target.Label)
		}

		return nil
	}
	if _, found := labels[target.Label]; !found {
		return fmt.Errorf("%s:%d: no label %s in this file", source.Path, marker.Line, target.Label)
	}

	return nil
}
