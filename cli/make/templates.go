package make

import (
	"embed"
	"encoding/json"
	"path"
	"slices"
	"sort"
	"strings"
)

// shipped are the ready rules a commandment can start from.
//
//go:embed templates/*.json
var shipped embed.FS

// Template is a ready rule: what it flags, its sin, and its query for each engine it is written for, or for
// every engine under "*".
type Template struct {
	Name    string                     `json:"-"`
	Summary string                     `json:"summary"`
	Sin     TemplateSin                `json:"sin"`
	Find    map[string]json.RawMessage `json:"find"`
}

// TemplateSin is what a template says of its sin: the symptom and the directive its fix follows.
type TemplateSin struct {
	Description string `json:"description"`
	Rule        string `json:"rule"`
}

// Templates are every ready rule, by name.
func Templates() []Template {
	entries, _ := shipped.ReadDir("templates")

	var templates []Template
	for _, entry := range entries {
		text, _ := shipped.ReadFile(path.Join("templates", entry.Name()))

		var template Template
		if json.Unmarshal(text, &template) == nil {
			template.Name = strings.TrimSuffix(entry.Name(), ".json")
			templates = append(templates, template)
		}
	}

	sort.Slice(templates, func(i, j int) bool { return templates[i].Name < templates[j].Name })

	return templates
}

// TemplateNamed is the ready rule of the name.
func TemplateNamed(name string) (Template, bool) {
	templates := Templates()

	at := slices.IndexFunc(templates, func(template Template) bool { return template.Name == name })
	if at < 0 {
		return Template{}, false
	}

	return templates[at], true
}

// Query is the template's query for the engine; false when it is written for other engines only.
func (t Template) Query(engine Engine) (json.RawMessage, bool) {
	if query, written := t.Find[string(engine)]; written {
		return query, true
	}

	query, everywhere := t.Find["*"]

	return query, everywhere
}

// Engines are the engines the template is written for.
func (t Template) Engines() []Engine {
	var written []Engine
	for _, engine := range Engines {
		if _, found := t.Query(engine); found {
			written = append(written, engine)
		}
	}

	return written
}

// templateNames are the names of every ready rule.
func templateNames() string {
	var names []string
	for _, template := range Templates() {
		names = append(names, template.Name)
	}

	return strings.Join(names, ", ")
}

// engineList names the engines, comma-separated.
func engineList(engines []Engine) string {
	var names []string
	for _, engine := range engines {
		names = append(names, string(engine))
	}

	return strings.Join(names, ", ")
}
