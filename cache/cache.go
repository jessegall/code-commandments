// Package cache says how long whatever the tool keeps between runs is kept once nothing uses it: the parsed trees, the
// detectors' memory, mypy's cache and the edit windows. Every use resets an entry's clock, so only an entry untouched
// for the whole time is pruned. A project chooses the hours in its config; the run that reads the config hands them on
// through the environment, so the bridges it starts prune by the same hours.
package cache

import (
	"os"
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
