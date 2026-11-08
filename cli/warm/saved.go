package warm

import (
	"compress/gzip"
	"encoding/json"
	"fmt"
	"maps"
	"os"
	"path/filepath"
	"slices"

	"github.com/jessegall/code-commandments/contract"
)

const savedHead = "head.json"

// Saved is a held tree read back from disk, with what each file was when the tree read it.
type Saved struct {
	Streams []*contract.Stream
	Marks   map[string]Known
}

type savedDescription struct {
	Binary  string
	Roots   []string
	Marks   map[string]Known
	Streams int
}

func (b *Base) Save(folder, binary string) error {
	draft := folder + ".draft"
	os.RemoveAll(draft)

	if err := os.MkdirAll(draft, 0o755); err != nil {
		return err
	}

	for index, stream := range b.streams {
		if err := writeSavedStream(savedStreamPath(draft, index), stream); err != nil {
			os.RemoveAll(draft)

			return err
		}
	}

	standing := maps.Clone(b.marks)
	maps.DeleteFunc(standing, func(_ string, known Known) bool { return known.Mark == "" })

	head, err := json.Marshal(savedDescription{Binary: binary, Roots: b.roots, Marks: standing, Streams: len(b.streams)})
	if err != nil {
		return err
	}

	if err := os.WriteFile(filepath.Join(draft, savedHead), head, 0o644); err != nil {
		return err
	}

	os.RemoveAll(folder)

	return os.Rename(draft, folder)
}

func LoadSaved(folder, binary string, roots []string) (Saved, error) {
	raw, err := os.ReadFile(filepath.Join(folder, savedHead))
	if err != nil {
		return Saved{}, err
	}

	var head savedDescription
	if err := json.Unmarshal(raw, &head); err != nil {
		return Saved{}, err
	}

	if head.Binary != binary {
		return Saved{}, fmt.Errorf("it was saved by %s and this is %s", head.Binary, binary)
	}

	if !slices.Equal(head.Roots, roots) {
		return Saved{}, fmt.Errorf("it covers %d roots other than the %d read now", len(head.Roots), len(roots))
	}

	saved := Saved{Marks: head.Marks}

	for index := range head.Streams {
		stream, err := readSavedStream(savedStreamPath(folder, index))
		if err != nil {
			return Saved{}, fmt.Errorf("stream %d: %w", index, err)
		}

		saved.Streams = append(saved.Streams, stream)
	}

	return saved, nil
}

func savedStreamPath(folder string, index int) string {
	return filepath.Join(folder, fmt.Sprintf("stream-%d.ndjson.gz", index))
}

func writeSavedStream(path string, stream *contract.Stream) error {
	file, err := os.Create(path)
	if err != nil {
		return err
	}
	defer file.Close()

	compressed, err := gzip.NewWriterLevel(file, gzip.BestSpeed)
	if err != nil {
		return err
	}

	if err := contract.Write(compressed, stream); err != nil {
		return err
	}

	return compressed.Close()
}

func readSavedStream(path string) (*contract.Stream, error) {
	file, err := os.Open(path)
	if err != nil {
		return nil, err
	}
	defer file.Close()

	compressed, err := gzip.NewReader(file)
	if err != nil {
		return nil, err
	}
	defer compressed.Close()

	return contract.ReadAll(compressed)
}
