package scan

import (
	"bufio"
	"bytes"
	"compress/gzip"
	"encoding/json"
	"fmt"
	"io"
	"maps"
	"os"
	"path/filepath"
	"slices"

	"github.com/jessegall/code-commandments/bridge"
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

// Units reads the files of the bridge that reads in units, cut a project at a time.
func (s Sources) Units(complete func(*engine.Codebase) error) (*Units, error) {
	for _, held := range Bridges {
		if held.InUnits {
			return s.unitsOf(held, complete)
		}
	}

	return nil, nil
}

func (s Sources) unitsOf(held Bridge, complete func(*engine.Codebase) error) (*Units, error) {
	var files []string
	for _, language := range held.Languages {
		files = append(files, resolved(s.byLanguage[language])...)
	}
	roots := resolved(s.roots)
	if len(files) == 0 {
		return nil, nil
	}
	units, err := readUnits(held, roots, files, func(codebase *engine.Codebase) error { return complete(s.rooted(codebase)) })
	if leftUnread(err, len(files)) {
		return nil, nil
	}
	if err != nil {
		return nil, err
	}
	units.rooted = s.rooted

	return units, nil
}

// readUnits asks the bridge once more when it was unavailable, as a bridge still starting its service is; its reach
// names the failure.
func readUnits(held Bridge, roots, files []string, complete func(*engine.Codebase) error) (*Units, error) {
	units, err := askUnits(held, roots, files, complete)
	if err == nil || !isUnavailable(err) {
		return units, err
	}

	return askUnits(held, roots, files, complete)
}

// askUnits reads the files through the one cache: each project's kept lines are cut into its unit beside the lines the
// bridge writes for its misses, and a run whose files all stand starts no bridge.
func askUnits(held Bridge, roots, files []string, complete func(*engine.Codebase) error) (*Units, error) {
	kept := linesKeptFor(held, roots)
	records, header, misses := kept.standingRecords(files)
	for wave := 1; wave < wavesAtMost; wave++ {
		first, later := kept.deferred(misses)
		if len(later) == 0 {
			break
		}
		if _, err := held.ask(roots, files, first, func() {}); err != nil {
			return nil, err
		}
		kept.forget(first)
		records, header, misses = kept.standingRecords(files)
	}

	folder, err := os.MkdirTemp("", "commandments-units-")
	if err != nil {
		return nil, err
	}
	units := &Units{folder: folder}
	cutter := &cutter{units: units, projects: held.Projects, owners: map[string]string{}, complete: complete, kept: kept, standing: map[string][]string{}}
	for file, record := range records {
		owner := cutter.ownerOf(file)
		cutter.standing[owner] = append(cutter.standing[owner], record)
	}
	if err := cutter.read(held, roots, files, misses, header); err != nil {
		units.Close()

		return nil, err
	}

	return units, nil
}

// read has the bridge write the misses, cut as they come, then cuts the projects none of whose files missed.
func (c *cutter) read(held Bridge, roots, files, misses []string, header *contract.Header) error {
	if len(misses) == 0 {
		c.header = c.kept.standing(*header, roots)
		c.units.header, _ = json.Marshal(contract.Line{Header: &c.header})
		c.units.header = append(c.units.header, '\n')
		c.joinProgram(nil)
	} else {
		asker, err := held.Reach.Open(held, roots)
		if err != nil {
			return err
		}
		defer asker.Close()
		c.kept.printedFirst(files)
		defer c.kept.settle()
		if err := asker.AskEach(bridge.AskedOf(held.Projects, files, misses).Request(), c.take); err != nil {
			return err
		}
	}
	if err := c.close(); err != nil {
		return err
	}
	for _, owner := range slices.Sorted(maps.Keys(c.standing)) {
		if err := c.start(owner); err != nil {
			return err
		}
		if err := c.close(); err != nil {
			return err
		}
	}

	return nil
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
	projects *bridge.Projects
	owners   map[string]string
	complete func(*engine.Codebase) error
	header   contract.Header
	owner    string
	open     bool
	disk     *os.File
	zipped   *gzip.Writer
	buffered *bufio.Writer
	files    []*contract.File
	kept     *keptLines
	// standing are the records of each project's files whose kept line still stands, cut into its unit as it starts.
	standing map[string][]string
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
		c.kept.keep(c.header, line.File, raw)

		return c.cut(line.File, raw)
	case line.Program != nil:
		c.joinProgram(line.Program)
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

	return c.cutStanding(owner)
}

// cutStanding cuts the kept lines of the project just started into its unit, read from their records.
func (c *cutter) cutStanding(owner string) error {
	if len(c.standing[owner]) == 0 {
		return nil
	}
	decoder := contract.KeptUnder(c.header)
	for _, record := range c.standing[owner] {
		held, err := used(record)
		if err != nil {
			continue
		}
		raw := c.kept.unmarked(held)
		file, err := decoder.File(raw)
		if err != nil {
			continue
		}
		if err := c.cut(file, raw); err != nil {
			return err
		}
	}
	delete(c.standing, owner)

	return nil
}

func (c *cutter) cut(file *contract.File, raw []byte) error {
	c.files = append(c.files, file)
	if _, err := c.buffered.Write(raw); err != nil {
		return err
	}

	return c.buffered.WriteByte('\n')
}

// joinProgram holds the program line every kept run wrote, joined with the one this run's bridge wrote.
func (c *cutter) joinProgram(fresh *contract.Program) {
	raw, _ := json.Marshal(contract.Line{Program: c.kept.program(fresh)})
	c.units.program = append(raw, '\n')
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
	owner := c.projects.Owner(path)
	c.owners[folder] = owner

	return owner
}
