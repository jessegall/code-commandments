// Command generate writes the shop's streams in engine/php/testdata, gzipped, through the PHP and frontend bridges:
// the live fixture's, with the digest of the sources they came from, and the frozen fixture's the PHP engine's
// answers in testdata/oracle are about. The answers themselves are never written again.
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
	if err := streams(shop.Fixture(), shop.Testdata()); err != nil {
		return err
	}
	frozen, err := shop.Frozen()
	if err != nil {
		return err
	}
	if err := streams(filepath.Join(frozen, "backend"), shop.Oracle()); err != nil {
		return err
	}
	digest, err := shop.Digest()
	if err != nil {
		return err
	}

	return os.WriteFile(filepath.Join(shop.Testdata(), "shop.digest"), []byte(digest+"\n"), 0o644)
}

// streams writes the fixture's backend and frontend streams into the folder, its paths renamed under the shop's root.
func streams(fixture, folder string) error {
	backend, err := run("php", filepath.Join(shop.Repository(), "bridge", "php", "bridge.php"), "--rename="+fixture+"="+shop.Root, fixture)
	if err != nil {
		return err
	}
	if err := zipped(filepath.Join(folder, "shop.jsonl.gz"), backend); err != nil {
		return err
	}
	frontend, err := run("node", filepath.Join(shop.Repository(), "bridge", "frontend", "dist", "bridge.mjs"), "--rename="+fixture+"="+shop.Root, fixture)
	if err != nil {
		return err
	}

	return zipped(filepath.Join(folder, "shop-frontend.jsonl.gz"), frontend)
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
