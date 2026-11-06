package scribes

import (
	"path/filepath"
	"slices"
	"strconv"
	"strings"

	"github.com/jessegall/code-commandments/engine"
)

// Draft gathers what a scribe would change: edits against existing files and new files, each file in the order the draft first touched it.
type Draft struct {
	sources map[string]string
	edited  []string
	edits   map[string][]Edit
	creates Rewrites
}

func NewDraft() *Draft {
	return &Draft{sources: map[string]string{}, edits: map[string][]Edit{}}
}

func (d *Draft) Edit(span engine.Span, text string) *Draft {
	edit := Edit{Start: span.Start, End: span.End, Text: text}
	if _, seen := d.sources[span.Path]; !seen {
		d.sources[span.Path] = string(span.Source)
		d.edited = append(d.edited, span.Path)
	}
	if !slices.Contains(d.edits[span.Path], edit) {
		d.edits[span.Path] = append(d.edits[span.Path], edit)
	}

	return d
}

func (d *Draft) Add(path, content string) *Draft {
	d.creates.Set(d.free(path), content)

	return d
}

func (d *Draft) Rewrites() Rewrites {
	rewrites := d.creates.clone()
	for _, path := range d.edited {
		edits := slices.Clone(d.edits[path])
		slices.SortStableFunc(edits, lastFirst)

		source := d.sources[path]
		consumed := len(source) + 1
		for _, edit := range edits {
			if edit.End > consumed {
				continue
			}
			source = edit.AppliedTo(source)
			consumed = edit.Start
		}
		rewrites.Set(path, source)
	}

	return rewrites
}

func (d *Draft) free(path string) string {
	if !d.creates.Has(path) {
		return path
	}
	extension := strings.TrimPrefix(filepath.Ext(path), ".")
	stem, suffix := path, ""
	if extension != "" {
		stem, suffix = strings.TrimSuffix(path, "."+extension), "."+extension
	}

	n := 2
	for d.creates.Has(stem + strconv.Itoa(n) + suffix) {
		n++
	}

	return stem + strconv.Itoa(n) + suffix
}
