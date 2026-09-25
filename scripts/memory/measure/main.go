// Command measure reads a codebase the way judge reads it and says what holding it costs: the nodes it holds, the
// heap they take once garbage is collected, the bytes that makes per node, and the heap's peak while every
// detector runs. With -each it reads a C# solution one project folder at a time and reports the largest.
// Run it under the agent limits: GOMEMLIMIT=3GiB GOMAXPROCS=2 go run ./scripts/memory/measure <path>.
package main

import (
	"flag"
	"fmt"
	"io/fs"
	"os"
	"path/filepath"
	"reflect"
	"runtime"
	"runtime/debug"
	"runtime/pprof"
	"strings"
	"sync/atomic"
	"time"

	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/cli/scan"
	"github.com/jessegall/code-commandments/cli/source"
	"github.com/jessegall/code-commandments/contract"
	"github.com/jessegall/code-commandments/detectors"
	"github.com/jessegall/code-commandments/engine"
	_ "github.com/jessegall/code-commandments/registry"
)

func main() {
	each := flag.Bool("each", false, "read each C# project folder alone")
	heap := flag.String("heap", "", "write a heap profile of the loaded codebase here")
	fields := flag.Bool("fields", false, "count how many nodes set each field")
	judged := flag.String("judged", "", "write a heap profile once the detectors ran, the analyses they built still held")
	flag.Parse()

	if flag.NArg() != 1 {
		fmt.Fprintln(os.Stderr, "usage: measure [-each] [-heap file] <path>")
		os.Exit(2)
	}

	parts := []string{flag.Arg(0)}
	if *each {
		parts = projectFolders(flag.Arg(0))
	}

	var peak atomic.Uint64
	go sample(&peak)

	largest := reading{}
	for _, part := range parts {
		read, err := measure(part, *heap, *judged, *fields)
		if err != nil {
			fmt.Fprintln(os.Stderr, err)
			os.Exit(1)
		}
		if *each {
			fmt.Printf("%s\t%s\n", strings.TrimPrefix(part, flag.Arg(0)+"/"), read)
		}
		if read.heap > largest.heap {
			largest = read
		}
		*heap, *judged = "", ""
	}

	fmt.Printf("largest\t%s\npeak heap while judging\t%s\n", largest, mib(peak.Load()))
}

// reading is one codebase's cost.
type reading struct {
	files, nodes int
	heap         uint64
	load         time.Duration
}

func (r reading) String() string {
	perNode := uint64(0)
	if r.nodes > 0 {
		perNode = r.heap / uint64(r.nodes)
	}

	return fmt.Sprintf("files %d  nodes %d  heap %s  %d B/node  load %s", r.files, r.nodes, mib(r.heap), perNode, r.load.Round(time.Second))
}

// measure loads the path, weighs what it holds, then runs every detector over it.
func measure(path, heap, judged string, fields bool) (reading, error) {
	started := time.Now()
	codebase, err := scan.Walk([]string{path}, source.Excluded{}).Load()
	if err != nil {
		return reading{}, err
	}
	read := reading{load: time.Since(started)}
	for _, file := range codebase.Files() {
		read.files++
		read.nodes += len(file.Nodes())
	}
	read.heap = settled()
	fmt.Fprintf(os.Stderr, "loaded %s\t%s\n", path, read)

	if fields {
		population(codebase)
	}

	if heap != "" {
		if err := profile(heap); err != nil {
			return reading{}, err
		}
	}

	held := map[contract.Language]bool{}
	for _, file := range codebase.Files() {
		held[file.Language()] = true
	}
	for _, engine := range catalog.Engines {
		if !reads(engine, held) {
			continue
		}
		for _, detector := range detectors.Of(engine) {
			detector.Find(codebase)
		}
	}
	if judged != "" {
		runtime.GC()
		if err := profile(judged); err != nil {
			return reading{}, err
		}
	}
	runtime.KeepAlive(codebase)

	return read, nil
}

// engineLanguages are the languages each engine's rules judge, as judge selects them.
var engineLanguages = map[catalog.Engine][]contract.Language{
	catalog.Backend:    {contract.PHP},
	catalog.Frontend:   {contract.Vue, contract.TypeScript},
	catalog.TypeScript: {contract.Vue, contract.TypeScript},
	catalog.Python:     {contract.Python},
	catalog.CSharp:     {contract.CSharp},
}

// reads says whether the engine judges any language the codebase holds.
func reads(engine catalog.Engine, held map[contract.Language]bool) bool {
	for _, language := range engineLanguages[engine] {
		if held[language] {
			return true
		}
	}

	return false
}

// population prints the share of nodes that set each field of the node, and how many distinct values the
// strings among them take.
func population(codebase *engine.Codebase) {
	kind := reflect.TypeOf(contract.Node{})
	set := make([]int, kind.NumField())
	distinct := make([]map[string]bool, kind.NumField())
	total := 0
	for _, file := range codebase.Files() {
		for _, node := range file.Nodes() {
			total++
			value := reflect.ValueOf(node).Elem()
			for i := range kind.NumField() {
				if !kind.Field(i).IsExported() || value.Field(i).IsZero() {
					continue
				}
				set[i]++
				if text, isString := value.Field(i).Interface().(string); isString {
					if distinct[i] == nil {
						distinct[i] = map[string]bool{}
					}
					distinct[i][text] = true
				}
			}
		}
	}
	for i := range kind.NumField() {
		if kind.Field(i).IsExported() {
			fmt.Fprintf(os.Stderr, "%-10s %5.1f%%  %d distinct\n", kind.Field(i).Name, 100*float64(set[i])/float64(total), len(distinct[i]))
		}
	}
}

// settled is the heap in use once every collectable byte is collected.
func settled() uint64 {
	runtime.GC()
	debug.FreeOSMemory()
	var stats runtime.MemStats
	runtime.ReadMemStats(&stats)

	return stats.HeapInuse
}

func profile(path string) error {
	file, err := os.Create(path)
	if err != nil {
		return err
	}
	defer file.Close()

	return pprof.WriteHeapProfile(file)
}

// sample keeps the largest heap seen, every tenth of a second.
func sample(peak *atomic.Uint64) {
	var stats runtime.MemStats
	for range time.Tick(100 * time.Millisecond) {
		runtime.ReadMemStats(&stats)
		if stats.HeapInuse > peak.Load() {
			peak.Store(stats.HeapInuse)
		}
	}
}

func mib(bytes uint64) string {
	return fmt.Sprintf("%.0f MiB", float64(bytes)/(1<<20))
}

// projectFolders is the folder of every C# project under the root, as the per-project parity reads them.
func projectFolders(root string) []string {
	var folders []string
	filepath.WalkDir(root, func(path string, entry fs.DirEntry, err error) error {
		if err != nil || !entry.IsDir() {
			return nil
		}
		if name := entry.Name(); path != root && (strings.HasPrefix(name, ".") || name == "bin" || name == "obj" || name == "node_modules") {
			return filepath.SkipDir
		}
		if projects, _ := filepath.Glob(filepath.Join(path, "*.csproj")); len(projects) > 0 {
			folders = append(folders, path)

			return filepath.SkipDir
		}

		return nil
	})

	return folders
}
