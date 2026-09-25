// Package library publishes the curriculum into a project: every skill the project can use, copied from the
// binary into its skill library (.agents/skills), beside the map of them all and the briefing that names
// them. What it published last time and no longer publishes is taken away; nothing else there is touched.
package library

import (
	_ "embed"
	"errors"
	"io/fs"
	"os"
	"path"
	"path/filepath"
	"slices"
	"strings"

	"github.com/jessegall/code-commandments/cli/atomic"
	"github.com/jessegall/code-commandments/cli/config"
	"github.com/jessegall/code-commandments/cli/workspace"
	"github.com/jessegall/code-commandments/skill"
	"github.com/jessegall/code-commandments/skills"
)

// Dir is the project's skill library, under its root.
const Dir = ".agents/skills"

// generated is the folder of the curriculum rendered from the skills; every other folder beside it is a
// hand-written skill.
const generated = "commandments"

// manifest lists what the last sync published, under the project's shared folder.
const manifest = "published-skills"

// Library is a project's skill library.
type Library struct {
	root     string
	project  config.Config
	previous []string
}

// At is the library of the project at root, publishing for the languages its config says it writes.
func At(root string, project config.Config) Library {
	return Library{root, project, readManifest(root)}
}

// Dir is the library's folder.
func (l Library) Dir() string {
	return filepath.Join(l.root, Dir)
}

// Path is the folder of the skill with the id.
func (l Library) Path(id string) string {
	return filepath.Join(l.Dir(), id)
}

// Publish writes every skill the project can use into the library, the map first, and answers their ids.
func (l Library) Publish() ([]string, error) {
	var ids []string

	if written(atomic.Write(filepath.Join(l.Path(MapID), "SKILL.md"), Map(l.project))) {
		ids = append(ids, MapID)
	}

	for _, each := range Written(skill.Ordered(), l.project) {
		definition := each.Definition()
		from := path.Join(generated, definition.Slug)

		if _, err := fs.Stat(skills.Files, from); err != nil {
			continue
		}

		if copied(from, l.Path(definition.ID())) {
			l.keepWrittenLanguages(filepath.Join(l.Path(definition.ID()), "SKILL.md"))
			ids = append(ids, definition.ID())
		}
	}

	for _, slug := range standalone() {
		if copied(slug, l.Path(skill.IDFor(slug))) {
			ids = append(ids, skill.IDFor(slug))
		}
	}

	l.Reconcile(l.Dir(), ids)

	return ids, writeManifest(l.root, ids)
}

// keepWrittenLanguages drops the examples for languages the project does not write from a published skill.
func (l Library) keepWrittenLanguages(file string) {
	if len(l.project.DisabledLanguages) == 0 {
		return
	}

	text, err := os.ReadFile(file)
	if err != nil {
		return
	}

	atomic.Write(file, KeepSections(string(text), l.project))
}

// Reconcile removes from dir every skill the last sync published that this one did not: renamed, retired,
// or a project's own that is gone. A folder the tool never put there is left where it is.
func (l Library) Reconcile(dir string, current []string) {
	for _, stale := range l.previous {
		if !slices.Contains(current, stale) {
			os.RemoveAll(filepath.Join(dir, stale))
		}
	}
}

// standalone are the hand-written skills the binary ships, by slug: every folder beside the generated one
// that holds a SKILL.md.
func standalone() []string {
	entries, _ := fs.ReadDir(skills.Files, ".")

	var slugs []string

	for _, entry := range entries {
		if !entry.IsDir() || entry.Name() == generated {
			continue
		}

		if _, err := fs.Stat(skills.Files, path.Join(entry.Name(), "SKILL.md")); err == nil {
			slugs = append(slugs, entry.Name())
		}
	}

	return slugs
}

// copied copies the embedded folder into to, recursively, and says whether every file arrived.
func copied(from, to string) bool {
	all := true

	err := fs.WalkDir(skills.Files, from, func(name string, entry fs.DirEntry, err error) error {
		if err != nil {
			return err
		}

		target := filepath.Join(to, filepath.FromSlash(strings.TrimPrefix(strings.TrimPrefix(name, from), "/")))

		if entry.IsDir() {
			return os.MkdirAll(target, 0o775)
		}

		contents, err := fs.ReadFile(skills.Files, name)
		if err != nil || os.WriteFile(target, contents, 0o644) != nil {
			all = false
		}

		return nil
	})

	return err == nil && all
}

func written(err error) bool {
	return err == nil
}

func manifestPath(root string) string {
	return workspace.At(root, "").Shared(manifest)
}

func readManifest(root string) []string {
	text, err := os.ReadFile(manifestPath(root))
	if errors.Is(err, os.ErrNotExist) || err != nil {
		return nil
	}

	var ids []string

	for _, line := range strings.Split(string(text), "\n") {
		if line != "" && !strings.HasPrefix(line, "#") {
			ids = append(ids, line)
		}
	}

	return ids
}

func writeManifest(root string, ids []string) error {
	return atomic.Write(manifestPath(root),
		"# The skills code-commandments published here, so it can retire its own and leave yours\n"+
			"# alone. Regenerated on every sync; deleting it only means a retired skill lingers.\n"+
			strings.Join(ids, "\n")+"\n")
}
