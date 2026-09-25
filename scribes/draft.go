package scribes

import (
	"path/filepath"
	"slices"
	"strconv"
	"strings"

	"github.com/jessegall/code-commandments/engine"
)

// Draft gathers what a scribe would change: edits against existing files and new files, each file
// in the order the draft first touched it.
type Draft struct {
	sources map[string]string
	edited  []string
	edits   map[string][]Edit
	creates Rewrites
}

// NewDraft is an empty draft.
func NewDraft() *Draft {
	return &Draft{sources: map[string]string{}, edits: map[string][]Edit{}}
}

// Edit replaces one span of a file with text; an edit identical to one already drafted is one edit.
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

// Add drafts a new file; a path already drafted is suffixed (Foo.vue becomes Foo2.vue) so nothing clobbers.
func (d *Draft) Add(path, content string) *Draft {
	d.creates.Set(d.free(path), content)

	return d
}

// Rewrites is the new content of every file the draft changes: the new files first, then each edited
// file with its edits applied right to left, an edit overlapping one already applied skipped.
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

// free is a path no new file holds yet, suffixing name.ext to name2.ext, name3.ext … until one is.
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
