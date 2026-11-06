package main

import (
	"crypto/sha256"
	"encoding/hex"
	"io"
	"io/fs"
	"os"
	"path/filepath"
	"slices"
	"sort"
	"strconv"
)

const mark = published + "/.written-from"

func writtenFrom(fixtures string) (string, error) {
	sum := sha256.New()

	binary, err := os.Executable()
	if err != nil {
		return "", err
	}
	if err := hashFile(sum, binary); err != nil {
		return "", err
	}

	if err := hashTree(sum, fixtures); err != nil {
		return "", err
	}

	if err := hashTree(sum, published, mark); err != nil && !os.IsNotExist(err) {
		return "", err
	}

	return hex.EncodeToString(sum.Sum(nil)), nil
}

func hashFile(sum io.Writer, path string) error {
	file, err := os.Open(path)
	if err != nil {
		return err
	}
	defer file.Close()

	_, err = io.Copy(sum, file)

	return err
}

func hashTree(sum io.Writer, root string, apart ...string) error {
	var paths []string
	sizes := map[string]int64{}

	err := filepath.WalkDir(root, func(path string, entry fs.DirEntry, err error) error {
		if err != nil || entry.IsDir() {
			return err
		}
		if slices.Contains(apart, path) {
			return nil
		}
		info, err := entry.Info()
		if err != nil {
			return err
		}
		paths = append(paths, path)
		sizes[path] = info.Size()

		return nil
	})
	if err != nil {
		return err
	}

	sort.Strings(paths)

	for _, path := range paths {
		if _, err := io.WriteString(sum, path+"\x00"+strconv.FormatInt(sizes[path], 10)+"\x00"); err != nil {
			return err
		}
		if err := hashFile(sum, path); err != nil {
			return err
		}
	}

	return nil
}

func alreadyWrittenFrom(key string) bool {
	written, err := os.ReadFile(mark)

	return err == nil && string(written) == key
}

func rememberWrittenFrom(key string) {
	if err := os.MkdirAll(filepath.Dir(mark), 0o755); err == nil {
		os.WriteFile(mark, []byte(key), 0o644)
	}
}

func forgetWrittenFrom() {
	os.Remove(mark)
}
