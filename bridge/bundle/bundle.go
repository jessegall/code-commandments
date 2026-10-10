// Package bundle carries a folder of sources in the binary and writes it out once per version of them under the
// cache folder, where the runtime that runs them finds them as files.
package bundle

import (
	"archive/tar"
	"bytes"
	"compress/gzip"
	"crypto/sha1"
	"encoding/hex"
	"errors"
	"io"
	"io/fs"
	"os"
	"path"
	"path/filepath"
	"sort"
	"time"
)

// Bundle is a folder of sources the binary carries.
type Bundle struct {
	name  string
	files func() (map[string][]byte, error)
	key   func() ([]byte, error)
}

// Embedded is the bundle of the files under root in an embedded filesystem.
func Embedded(name string, sources fs.FS, root string) Bundle {
	files := func() (map[string][]byte, error) { return Files(sources, root) }
	key := func() ([]byte, error) {
		files, err := files()
		if err != nil {
			return nil, err
		}
		paths := make([]string, 0, len(files))
		for name := range files {
			paths = append(paths, name)
		}
		sort.Strings(paths)
		var all bytes.Buffer
		for _, name := range paths {
			all.WriteString(name)
			all.Write(files[name])
		}

		return all.Bytes(), nil
	}

	return Bundle{name: name, files: files, key: key}
}

// Archived is the bundle a gzipped tar holds, keyed by the archive itself so a run never unpacks it to find its
// folder.
func Archived(name string, archive []byte) Bundle {
	return Bundle{
		name:  name,
		files: func() (map[string][]byte, error) { return Unpack(archive) },
		key:   func() ([]byte, error) { return archive, nil },
	}
}

// Folder is where this version of the bundle lives, written out whole on its first use: a folder is renamed into
// place only once every file is in it, so a run never reads one half written.
func (b Bundle) Folder() (string, error) {
	key, err := b.key()
	if err != nil {
		return "", err
	}
	sum := sha1.Sum(key)
	cache, err := CacheFolder()
	if err != nil {
		return "", err
	}
	folder := filepath.Join(cache, "code-commandments", b.name, hex.EncodeToString(sum[:])[:16])
	if _, err := os.Stat(folder); err == nil {
		return folder, nil
	}
	files, err := b.files()
	if err != nil {
		return "", err
	}
	if err := os.MkdirAll(filepath.Dir(folder), 0o755); err != nil {
		return "", err
	}
	draft, err := os.MkdirTemp(filepath.Dir(folder), ".unpacking-")
	if err != nil {
		return "", err
	}
	defer os.RemoveAll(draft)
	for name, content := range files {
		target := filepath.Join(draft, filepath.FromSlash(name))
		if err := os.MkdirAll(filepath.Dir(target), 0o755); err != nil {
			return "", err
		}
		if err := os.WriteFile(target, content, 0o644); err != nil {
			return "", err
		}
	}
	if err := os.Rename(draft, folder); err != nil {
		if _, held := os.Stat(folder); held != nil {
			return "", err
		}
	}

	return folder, nil
}

// TreesFolder is where a language's bridge keeps each file's tree between runs, beside the bundles written out.
func TreesFolder(language string) (string, error) {
	cache, err := CacheFolder()
	if err != nil {
		return "", err
	}

	folder := filepath.Join(cache, "code-commandments", "trees", language)
	sweep(folder, time.Now())

	return folder, nil
}

// CacheFolder is $XDG_CACHE_HOME, else ~/.cache.
func CacheFolder() (string, error) {
	if cache := os.Getenv("XDG_CACHE_HOME"); cache != "" {
		return cache, nil
	}
	home, err := os.UserHomeDir()
	if err != nil {
		return "", err
	}

	return filepath.Join(home, ".cache"), nil
}

// Files is every file under root in the filesystem, by its path below root.
func Files(sources fs.FS, root string) (map[string][]byte, error) {
	files := map[string][]byte{}
	err := fs.WalkDir(sources, root, func(name string, entry fs.DirEntry, err error) error {
		if err != nil || entry.IsDir() {
			return err
		}
		content, err := fs.ReadFile(sources, name)
		if err != nil {
			return err
		}
		relative := name
		if root != "." {
			relative = name[len(root)+1:]
		}
		files[relative] = content

		return nil
	})

	return files, err
}

// Pack is the files as a gzipped tar that holds nothing but their paths and contents, in path order, so the same
// files always pack the same.
func Pack(files map[string][]byte) ([]byte, error) {
	paths := make([]string, 0, len(files))
	for name := range files {
		paths = append(paths, name)
	}
	sort.Strings(paths)
	var archive bytes.Buffer
	zipped, err := gzip.NewWriterLevel(&archive, gzip.BestCompression)
	if err != nil {
		return nil, err
	}
	packed := tar.NewWriter(zipped)
	for _, name := range paths {
		if err := packed.WriteHeader(&tar.Header{Name: name, Mode: 0o644, Size: int64(len(files[name])), Format: tar.FormatPAX}); err != nil {
			return nil, err
		}
		if _, err := packed.Write(files[name]); err != nil {
			return nil, err
		}
	}
	if err := errors.Join(packed.Close(), zipped.Close()); err != nil {
		return nil, err
	}

	return archive.Bytes(), nil
}

// Unpack is the files a gzipped tar holds, by path.
func Unpack(archive []byte) (map[string][]byte, error) {
	zipped, err := gzip.NewReader(bytes.NewReader(archive))
	if err != nil {
		return nil, err
	}
	packed := tar.NewReader(zipped)
	files := map[string][]byte{}
	for {
		header, err := packed.Next()
		if err == io.EOF {
			return files, nil
		}
		if err != nil {
			return nil, err
		}
		if header.Typeflag != tar.TypeReg || !fs.ValidPath(header.Name) || path.Clean(header.Name) != header.Name {
			continue
		}
		content, err := io.ReadAll(packed)
		if err != nil {
			return nil, err
		}
		files[header.Name] = content
	}
}
