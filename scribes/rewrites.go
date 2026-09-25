package scribes

import (
	"os"
	"slices"
)

// Rewrites maps each path a rewrite touches to its new content, in the order the paths were first set.
type Rewrites struct {
	paths   []string
	content map[string]string
}

// Set gives a path its new content; a path set before keeps its place.
func (r *Rewrites) Set(path, content string) {
	if r.content == nil {
		r.content = map[string]string{}
	}
	if _, set := r.content[path]; !set {
		r.paths = append(r.paths, path)
	}
	r.content[path] = content
}

// Has says whether the path has new content.
func (r Rewrites) Has(path string) bool {
	_, set := r.content[path]

	return set
}

// Content is the path's new content.
func (r Rewrites) Content(path string) string {
	return r.content[path]
}

// Paths is every path, in order.
func (r Rewrites) Paths() []string {
	return slices.Clone(r.paths)
}

// Len is how many paths change.
func (r Rewrites) Len() int {
	return len(r.paths)
}

// Apply writes every path's new content to disk and returns the paths written.
func (r Rewrites) Apply() ([]string, error) {
	for _, path := range r.paths {
		if err := os.WriteFile(path, []byte(r.content[path]), 0o666); err != nil {
			return nil, err
		}
	}

	return r.Paths(), nil
}

// Contents is every path's new content, keyed by path.
func (r Rewrites) Contents() map[string]string {
	contents := make(map[string]string, len(r.content))
	for path, content := range r.content {
		contents[path] = content
	}

	return contents
}

// with is these rewrites with others laid over them: a path set again keeps its place, a new one comes last.
func (r Rewrites) with(other Rewrites) Rewrites {
	merged := r.clone()
	for _, path := range other.paths {
		merged.Set(path, other.content[path])
	}

	return merged
}

// Equal says whether both set the same paths, in the same order, to the same content.
func (r Rewrites) Equal(other Rewrites) bool {
	if !slices.Equal(r.paths, other.paths) {
		return false
	}
	for _, path := range r.paths {
		if r.content[path] != other.content[path] {
			return false
		}
	}

	return true
}

func (r Rewrites) clone() Rewrites {
	clone := Rewrites{}
	for _, path := range r.paths {
		clone.Set(path, r.content[path])
	}

	return clone
}
