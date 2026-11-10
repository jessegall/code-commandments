// Package library publishes the curriculum into a project: every skill the project can use, copied from the binary into its skill library (.agents/skills), beside the map of them all and the briefing that names them.
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
	"github.com/jessegall/code-commandments/cli/binary"
	"github.com/jessegall/code-commandments/cli/config"
	"github.com/jessegall/code-commandments/cli/custom"
	"github.com/jessegall/code-commandments/cli/workspace"
	"github.com/jessegall/code-commandments/skill"
	"github.com/jessegall/code-commandments/skills"
)

const Dir = ".agents/skills"

const generated = "commandments"

const manifest = "published-skills"

const commandsHeading = "## Commands"

// Library is a project's skill library.
type Library struct {
	root            string
	judged          string
	project         config.Config
	previous        []string
	withoutCommands bool
}

func At(root string, project config.Config) Library {
	return For(root, root, project)
}

func For(root, judged string, project config.Config) Library {
	return Library{root, judged, project, readManifest(root), false}
}

func (l Library) WithoutCommands() Library {
	l.withoutCommands = true

	return l
}

func (l Library) Dir() string {
	return filepath.Join(l.root, Dir)
}

func (l Library) Path(id string) string {
	return filepath.Join(l.Dir(), id)
}

func (l Library) Publish() ([]string, error) {
	var ids []string

	if written(atomic.Write(filepath.Join(l.Path(MapID), "SKILL.md"), l.index())) {
		ids = append(ids, MapID)
	}

	for _, each := range Written(skill.Ordered(), l.project) {
		definition := each.Definition()
		from := path.Join(generated, definition.Slug)

		if _, err := fs.Stat(skills.Files, from); err != nil {
			continue
		}

		if l.copied(from, l.Path(definition.ID())) {
			l.keepWrittenLanguages(filepath.Join(l.Path(definition.ID()), "SKILL.md"))
			ids = append(ids, definition.ID())
		}
	}

	for _, slug := range standalone() {
		if l.copied(slug, l.Path(skill.IDFor(slug))) {
			ids = append(ids, skill.IDFor(slug))
		}
	}

	for _, own := range custom.InForce(l.judged).Skills {
		id := own.Definition().ID()

		if len(Written([]skill.Skill{own}, l.project)) > 0 && copiedFrom(os.DirFS(own.Dir), ".", l.Path(id), l.naming) {
			ids = append(ids, id)
		}
	}

	l.Reconcile(l.Dir(), ids)

	return ids, writeManifest(l.root, ids)
}

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

func (l Library) Reconcile(dir string, current []string) {
	for _, stale := range l.previous {
		if !slices.Contains(current, stale) {
			os.RemoveAll(filepath.Join(dir, stale))
		}
	}
}

func (l Library) Withdraw() error {
	l.Reconcile(l.Dir(), nil)

	return writeManifest(l.root, nil)
}

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

func (l Library) index() string {
	if l.withoutCommands {
		return MapWithoutCommands(l.judged, l.project)
	}

	return Map(l.judged, l.project)
}

func (l Library) copied(from, to string) bool {
	return copiedFrom(skills.Files, from, to, l.shipped)
}

func (l Library) shipped(text []byte) []byte {
	named := l.naming(text)
	if !l.withoutCommands {
		return named
	}

	return []byte(withoutCommandsSection(string(named)))
}

func withoutCommandsSection(text string) string {
	var kept []string
	dropping := false

	for _, line := range strings.Split(text, "\n") {
		switch {
		case line == commandsHeading:
			dropping = true
		case strings.HasPrefix(line, "## "):
			dropping = false
		}

		if !dropping {
			kept = append(kept, line)
		}
	}

	return strings.Join(kept, "\n")
}

func (l Library) naming(text []byte) []byte {
	return []byte(strings.NewReplacer(
		Binary, binary.Invocation(l.judged),
		Judging, binary.Judging(l.judged),
	).Replace(string(text)))
}

func copiedFrom(files fs.FS, from, to string, naming func([]byte) []byte) bool {
	all := true
	published := map[string]bool{}

	err := fs.WalkDir(files, from, func(name string, entry fs.DirEntry, err error) error {
		if err != nil {
			return err
		}

		relative := name
		if from != "." {
			relative = strings.TrimPrefix(strings.TrimPrefix(name, from), "/")
		}

		target := filepath.Join(to, filepath.FromSlash(relative))

		if entry.IsDir() {
			return os.MkdirAll(target, 0o775)
		}

		published[target] = true
		contents, err := fs.ReadFile(files, name)
		if err != nil || os.WriteFile(target, naming(contents), 0o644) != nil {
			all = false
		}

		return nil
	})
	if err != nil {
		return false
	}

	filepath.WalkDir(to, func(path string, entry fs.DirEntry, err error) error {
		if err == nil && !entry.IsDir() && !published[path] {
			os.Remove(path)
		}

		return nil
	})

	return all
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
