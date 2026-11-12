package bridge

import (
	"encoding/xml"
	"os"
	"path/filepath"
	"slices"
	"strings"

	"github.com/jessegall/code-commandments/walk"
)

const supportOption = "--support="

// Projects is how a language whose files compile together in projects names them: the file that marks a project's
// folder, the projects one references, and the files its build generated that the compiler needs. A language whose
// files are read alone has none, and its bridge is handed only the files it parses.
type Projects struct {
	Marker     string
	Source     string
	Built      []string
	References func(project string) []string
	Generated  func(project string) []string
}

// Asked is the one request every bridge is sent, built in one place: the files it parses, the files among them it
// writes in full, and the files it reads beside them, every one listed through the funnel, so no bridge lists a
// folder.
type Asked struct {
	Files   []string
	Written []string
	Support []string
}

func AskedOf(projects *Projects, files, written []string) Asked {
	return Asked{Files: files, Written: written, Support: projects.SupportOf(files)}
}

func (a Asked) Request() Request {
	return Request{Paths: a.Files, Write: a.Written, Support: a.Support}
}

func (a Asked) Arguments() []string {
	arguments := slices.Clone(a.Files)
	for _, support := range a.Support {
		arguments = append(arguments, supportOption+support)
	}

	return arguments
}

func (p *Projects) Under(paths ...string) Asked {
	return AskedOf(p, p.sources(paths...), nil)
}

func (p *Projects) sources(paths ...string) []string {
	var files []string
	for _, path := range paths {
		files = append(files, slices.DeleteFunc(ListedAs(p.Source, path), func(file string) bool {
			inside, _ := filepath.Rel(path, file)

			return slices.ContainsFunc(strings.Split(filepath.ToSlash(inside), "/"), func(part string) bool { return slices.Contains(p.Built, part) })
		})...)
	}

	return files
}

func (p *Projects) SupportOf(files []string) []string {
	var support []string
	for _, project := range p.Around(files) {
		support = append(support, project)
		support = append(support, p.sources(filepath.Dir(project))...)
		support = append(support, p.Generated(project)...)
	}

	return support
}

func (p *Projects) Around(paths []string) []string {
	if p == nil {
		return nil
	}

	var pending []string
	listed := map[string][]string{}
	for _, path := range paths {
		pending = append(pending, p.enclosing(path, listed)...)
	}

	var closure []string
	for len(pending) > 0 {
		project := pending[0]
		pending = pending[1:]
		if slices.Contains(closure, project) || !walk.IsFile(project) {
			continue
		}
		closure = append(closure, project)
		pending = append(pending, p.References(project)...)
	}

	return closure
}

func (p *Projects) Owner(path string) string {
	marked := p.enclosing(path, map[string][]string{})
	if len(marked) == 0 {
		return ""
	}

	return filepath.Dir(marked[0])
}

func (p *Projects) Folders(paths []string) []string {
	var folders []string
	for _, project := range p.Around(paths) {
		folders = append(folders, filepath.Dir(project))
	}

	return folders
}

func (p *Projects) enclosing(path string, listed map[string][]string) []string {
	if below := walk.Files(path, walk.Excluded{}, hasSuffix(p.Marker)); walk.IsFolder(path) && len(below) > 0 {
		return below
	}

	for above := filepath.Dir(path); ; above = filepath.Dir(above) {
		marked, seen := listed[above]
		if !seen {
			marked = slices.DeleteFunc(walk.Entries(above), lacksSuffix(p.Marker))
			listed[above] = marked
		}
		if len(marked) > 0 {
			return marked
		}
		if walk.IsRepository(above) || filepath.Dir(above) == above {
			return nil
		}
	}
}

var CSharpProjects = &Projects{
	Marker:     ".csproj",
	Source:     ".cs",
	Built:      []string{"bin", "obj"},
	References: projectReferences,
	Generated: func(project string) []string {
		name := strings.TrimSuffix(filepath.Base(project), ".csproj")
		found := walk.Generated(filepath.Join(filepath.Dir(project), "obj"), name+".GlobalUsings.g.cs")
		for above := filepath.Dir(project); len(found) == 0 && filepath.Dir(above) != above; above = filepath.Dir(above) {
			found = walk.Generated(filepath.Join(above, "artifacts", "obj", name), name+".GlobalUsings.g.cs")
		}

		return found
	},
}

func projectReferences(project string) []string {
	file, err := os.Open(project)
	if err != nil {
		return nil
	}
	defer file.Close()

	var referenced []string
	decoder := xml.NewDecoder(file)
	for {
		token, err := decoder.Token()
		if err != nil {
			return referenced
		}
		element, starts := token.(xml.StartElement)
		if !starts || element.Name.Local != "ProjectReference" {
			continue
		}
		for _, attribute := range element.Attr {
			if attribute.Name.Local == "Include" {
				referenced = append(referenced, filepath.Clean(filepath.Join(filepath.Dir(project), strings.ReplaceAll(attribute.Value, `\`, "/"))))
			}
		}
	}
}

func hasSuffix(suffix string) func(path string) bool {
	return func(path string) bool { return strings.HasSuffix(path, suffix) }
}

func lacksSuffix(suffix string) func(path string) bool {
	return func(path string) bool { return !strings.HasSuffix(path, suffix) }
}
