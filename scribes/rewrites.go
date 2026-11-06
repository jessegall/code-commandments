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

func (r *Rewrites) Set(path, content string) {
	if r.content == nil {
		r.content = map[string]string{}
	}
	if _, set := r.content[path]; !set {
		r.paths = append(r.paths, path)
	}
	r.content[path] = content
}

func (r Rewrites) Has(path string) bool {
	_, set := r.content[path]

	return set
}

func (r Rewrites) Content(path string) string {
	return r.content[path]
}

func (r Rewrites) Paths() []string {
	return slices.Clone(r.paths)
}

func (r Rewrites) Len() int {
	return len(r.paths)
}

func (r Rewrites) Apply() ([]string, error) {
	for _, path := range r.paths {
		if err := os.WriteFile(path, []byte(r.content[path]), 0o666); err != nil {
			return nil, err
		}
	}

	return r.Paths(), nil
}

func (r Rewrites) Contents() map[string]string {
	contents := make(map[string]string, len(r.content))
	for path, content := range r.content {
		contents[path] = content
	}

	return contents
}

func (r Rewrites) with(other Rewrites) Rewrites {
	merged := r.clone()
	for _, path := range other.paths {
		merged.Set(path, other.content[path])
	}

	return merged
}

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
