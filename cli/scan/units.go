package scan

import (
	"bufio"
	"bytes"
	"compress/gzip"
	"fmt"
	"io"
	"os"
	"path/filepath"

	"github.com/jessegall/code-commandments/bridge"
	"github.com/jessegall/code-commandments/cli/source"
	"github.com/jessegall/code-commandments/contract"
	"github.com/jessegall/code-commandments/engine"
)

// Units is a C# solution read once through its bridge and cut into one unit per project, so a solution too large to hold whole is judged a project at a time.
type Units struct {
	folder  string
	header  []byte
	program []byte
	units   []unit
	rooted  func(*engine.Codebase) *engine.Codebase
}

// unit is one project's lines on disk.
type unit struct {
	owner string
	path  string
	files int
}

func (s Sources) CSharpUnits(complete func(*engine.Codebase) error) (*Units, error) {
	roots, files := resolved(s.roots), resolved(s.byLanguage[source.CSharp])
	if len(files) == 0 {
		return nil, nil
	}
	units, err := readCSharp(roots, files, func(codebase *engine.Codebase) error { return complete(s.rooted(codebase)) })
	if leftUnread(err, len(files)) {
		return nil, nil
	}
	if err != nil {
		return nil, err
	}
	units.rooted = s.rooted

	return units, nil
}

func readCSharp(roots, files []string, complete func(*engine.Codebase) error) (*Units, error) {
	units, err := askCSharp(roots, files, complete)
	if err == nil {
		return units, nil
	}

	if failed := bridge.RoslynFailure(err); !isUnavailable(failed) {
		return nil, err
	}

	units, again := askCSharp(roots, files, complete)
	if again == nil {
		return units, nil
	}

	return nil, bridge.RoslynFailure(again)
}

func askCSharp(roots, files []string, complete func(*engine.Codebase) error) (*Units, error) {
	asker, err := CSharp.Reach.Open(CSharp, roots)
	if err != nil {
		return nil, err
	}
	defer asker.Close()
	folder, err := os.MkdirTemp("", "commandments-units-")
	if err != nil {
		return nil, err
	}
	units := &Units{folder: folder}
	cutter := &cutter{units: units, owners: map[string]string{}, complete: complete}
	if err := asker.AskEach(CSharp.Request(files), cutter.take); err != nil {
		units.Close()

		return nil, err
	}
	if err := cutter.close(); err != nil {
		units.Close()

		return nil, err
	}

	return units, nil
}

func (u *Units) Count() int {
	return len(u.units)
}

func (u *Units) Files(at int) int {
	return u.units[at].files
}

func (u *Units) Load(at int) (*engine.Codebase, error) {
	held := u.units[at]
	file, err := os.Open(held.path)
	if err != nil {
		return nil, err
	}
	defer file.Close()
	unzipped, err := gzip.NewReader(file)
	if err != nil {
		return nil, err
	}
	lines := []io.Reader{bytes.NewReader(u.header), unzipped}
	if u.program != nil {
		lines = append(lines, bytes.NewReader(u.program))
	}
	lines = append(lines, bytes.NewReader(fmt.Appendf(nil, "{\"trailer\":{\"files\":%d}}\n", held.files)))
	stream, err := contract.ReadAll(io.MultiReader(lines...))
	if err != nil {
		return nil, fmt.Errorf("unit %s: %w", held.owner, err)
	}

	return u.rooted(engine.Load(stream)), nil
}

func (u *Units) Close() error {
	return os.RemoveAll(u.folder)
}

// cutter cuts a stream into units as it is read.
type cutter struct {
	units    *Units
	owners   map[string]string
	complete func(*engine.Codebase) error
	header   contract.Header
	owner    string
	open     bool
	disk     *os.File
	zipped   *gzip.Writer
	buffered *bufio.Writer
	files    []*contract.File
}

func (c *cutter) take(line contract.Line, raw []byte) error {
	switch {
	case line.Header != nil:
		c.header = *line.Header
		c.units.header = append(bytes.Clone(raw), '\n')
	case line.File != nil:
		if owner := c.ownerOf(line.File.Path); !c.open || owner != c.owner {
			if err := c.close(); err != nil {
				return err
			}
			if err := c.start(owner); err != nil {
				return err
			}
		}
		c.files = append(c.files, line.File)
		if _, err := c.buffered.Write(raw); err != nil {
			return err
		}

		return c.buffered.WriteByte('\n')
	case line.Program != nil:
		c.units.program = append(bytes.Clone(raw), '\n')
	}

	return nil
}

func (c *cutter) start(owner string) error {
	disk, err := os.Create(filepath.Join(c.units.folder, fmt.Sprintf("%04d.jsonl.gz", len(c.units.units))))
	if err != nil {
		return err
	}
	c.owner, c.open, c.disk = owner, true, disk
	c.zipped = gzip.NewWriter(disk)
	c.buffered = bufio.NewWriter(c.zipped)
	c.units.units = append(c.units.units, unit{owner: owner, path: disk.Name()})

	return nil
}

func (c *cutter) close() error {
	if !c.open {
		return nil
	}
	c.open = false
	c.units.units[len(c.units.units)-1].files = len(c.files)
	if err := c.buffered.Flush(); err != nil {
		return err
	}
	if err := c.zipped.Close(); err != nil {
		return err
	}
	if err := c.disk.Close(); err != nil {
		return err
	}
	stream := &contract.Stream{Header: c.header, Files: c.files, Trailer: contract.Trailer{Files: len(c.files)}}
	c.files = nil

	return c.complete(engine.Load(stream))
}

func (c *cutter) ownerOf(path string) string {
	folder := filepath.Dir(path)
	if owner, known := c.owners[folder]; known {
		return owner
	}
	owner := bridge.CSharpProjects.Owner(path)
	c.owners[folder] = owner

	return owner
}
