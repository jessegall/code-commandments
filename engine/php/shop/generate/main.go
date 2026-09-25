// Command generate writes engine/php/testdata: the shop's stream through the bridge, the PHP engine's answers
// through the oracle, both gzipped, what every backend rule states about itself, and the digest of the sources
// they came from.
package main

import (
	"bytes"
	"compress/gzip"
	"fmt"
	"os"
	"os/exec"
	"path/filepath"

	"github.com/jessegall/code-commandments/engine/php/shop"
)

func main() {
	if err := generate(); err != nil {
		fmt.Fprintln(os.Stderr, err)
		os.Exit(1)
	}
}

func generate() error {
	testdata := shop.Testdata()
	answers := filepath.Join(testdata, "answers")
	if err := os.RemoveAll(answers); err != nil {
		return err
	}
	if err := os.MkdirAll(answers, 0o755); err != nil {
		return err
	}
	stream, err := run("php", filepath.Join(shop.Repository(), "bridge", "php", "bridge.php"), "--rename="+shop.Fixture()+"="+shop.Root, shop.Fixture())
	if err != nil {
		return err
	}
	if err := zipped(filepath.Join(testdata, "shop.jsonl.gz"), stream); err != nil {
		return err
	}
	written, err := os.MkdirTemp("", "oracle")
	if err != nil {
		return err
	}
	defer os.RemoveAll(written)
	if _, err := run("php", filepath.Join(shop.Repository(), "engine", "php", "oracle", "oracle.php"), shop.Fixture(), written); err != nil {
		return err
	}
	questions, err := filepath.Glob(filepath.Join(written, "*.jsonl"))
	if err != nil {
		return err
	}
	for _, question := range questions {
		lines, err := os.ReadFile(question)
		if err != nil {
			return err
		}
		if err := zipped(filepath.Join(answers, filepath.Base(question)+".gz"), lines); err != nil {
			return err
		}
	}
	definitions, err := run("php", filepath.Join(shop.Repository(), "engine", "php", "oracle", "definitions.php"))
	if err != nil {
		return err
	}
	if err := os.WriteFile(filepath.Join(testdata, "definitions.json"), definitions, 0o644); err != nil {
		return err
	}
	digest, err := shop.Digest()
	if err != nil {
		return err
	}

	return os.WriteFile(filepath.Join(testdata, "shop.digest"), []byte(digest+"\n"), 0o644)
}

func run(name string, arguments ...string) ([]byte, error) {
	var out, failure bytes.Buffer
	command := exec.Command(name, arguments...)
	command.Stdout = &out
	command.Stderr = &failure
	if err := command.Run(); err != nil {
		return nil, fmt.Errorf("%s %v: %w\n%s", name, arguments, err, failure.String())
	}

	return out.Bytes(), nil
}

// zipped writes a gzip with no name or time in its header, so the same bytes zip the same way.
func zipped(path string, content []byte) error {
	var out bytes.Buffer
	writer, err := gzip.NewWriterLevel(&out, gzip.BestCompression)
	if err != nil {
		return err
	}
	if _, err := writer.Write(content); err != nil {
		return err
	}
	if err := writer.Close(); err != nil {
		return err
	}

	return os.WriteFile(path, out.Bytes(), 0o644)
}
