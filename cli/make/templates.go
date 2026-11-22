package make

import (
	"embed"
	"encoding/json"
	"fmt"
	"path"
	"slices"
	"strings"

	"github.com/jessegall/code-commandments/catalog"
)

//go:embed templates/*.json
var shipped embed.FS

// Template is a ready rule: what it flags, its sin, the engines it is written for (every engine when it names none),
// and the one query each of them judges it by.
type Template struct {
	Name    string          `json:"-"`
	Summary string          `json:"summary"`
	Engine  []string        `json:"engine"`
	Sin     TemplateSin     `json:"sin"`
	Find    json.RawMessage `json:"find"`
}

// TemplateSin is what a template says of its sin: the symptom and the directive its fix follows.
type TemplateSin struct {
	Description string `json:"description"`
	Rule        string `json:"rule"`
}

func Templates() []Template {
	entries, err := shipped.ReadDir("templates")
	if err != nil {
		panic(fmt.Errorf("the shipped templates cannot be listed: %w", err))
	}

	var templates []Template
	for _, entry := range entries {
		text, err := shipped.ReadFile(path.Join("templates", entry.Name()))
		if err != nil {
			panic(fmt.Errorf("the shipped template %s cannot be read: %w", entry.Name(), err))
		}

		var template Template
		if err := json.Unmarshal(text, &template); err != nil {
			panic(fmt.Errorf("the shipped template %s is no template: %w", entry.Name(), err))
		}

		template.Name = strings.TrimSuffix(entry.Name(), ".json")
		templates = append(templates, template)
	}

	slices.SortFunc(templates, func(a, b Template) int { return strings.Compare(a.Name, b.Name) })

	return templates
}

func TemplateNamed(name string) (Template, bool) {
	templates := Templates()

	at := slices.IndexFunc(templates, func(template Template) bool { return template.Name == name })
	if at < 0 {
		return Template{}, false
	}

	return templates[at], true
}

func (t Template) Engines() []Engine {
	if len(t.Engine) == 0 {
		return Engines
	}

	var written []Engine
	for _, name := range t.Engine {
		if engine, known := catalog.EngineNamed(name); known {
			written = append(written, engine)
		}
	}

	return written
}

func (t Template) Judges(engine Engine) bool {
	return slices.Contains(t.Engines(), engine)
}

func templateNames() string {
	var names []string
	for _, template := range Templates() {
		names = append(names, template.Name)
	}

	return strings.Join(names, ", ")
}

func engineList(engines []Engine) string {
	var names []string
	for _, engine := range engines {
		names = append(names, string(engine))
	}

	return strings.Join(names, ", ")
}
