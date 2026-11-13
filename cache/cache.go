// Package cache says how long whatever the tool keeps between runs is kept once nothing uses it: the parsed trees, the
// detectors' memory, mypy's cache and the edit windows. Every use resets an entry's clock, so only an entry untouched
// for the whole time is pruned. A project chooses the hours in its config; the run that reads the config hands them on
// through the environment, so the bridges it starts prune by the same hours.
package cache

import (
	"os"
	"path/filepath"
	"runtime"
	"strconv"
	"time"
)

const (
	Variable = "COMMANDMENTS_CACHE_HOURS"

	Folder = "COMMANDMENTS_CACHE_FOLDER"

	DefaultHours = 12

	Default = DefaultHours * time.Hour
)

func KeptFor() time.Duration {
	hours, err := strconv.ParseFloat(os.Getenv(Variable), 64)
	if err != nil || hours <= 0 {
		return Default
	}

	return time.Duration(hours * float64(time.Hour))
}

func KeepFor(hours float64) {
	if hours > 0 {
		os.Setenv(Variable, strconv.FormatFloat(hours, 'f', -1, 64))
	}
}

func KeepIn(folder string) {
	if folder != "" {
		os.Setenv(Folder, folder)
	}
}

func KeptIn() string {
	return os.Getenv(Folder)
}

// UserFolder is the user's cache folder: $XDG_CACHE_HOME, else %LOCALAPPDATA% on Windows, else ~/.cache.
func UserFolder() (string, error) {
	if folder := os.Getenv("XDG_CACHE_HOME"); folder != "" {
		return folder, nil
	}
	if folder := os.Getenv("LOCALAPPDATA"); folder != "" && runtime.GOOS == "windows" {
		return folder, nil
	}
	home, err := os.UserHomeDir()
	if err != nil {
		return "", err
	}

	return filepath.Join(home, ".cache"), nil
}

// Ours is the folder in the user's cache where the tool keeps what it fetches, unpacks and builds.
func Ours() (string, error) {
	user, err := UserFolder()
	if err != nil {
		return "", err
	}

	return filepath.Join(user, "code-commandments"), nil
}
