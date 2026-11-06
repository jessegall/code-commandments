package config

import (
	"bytes"
	"encoding/json"
	"fmt"
	"os"
	"slices"

	"github.com/jessegall/code-commandments/cli"
)

func RenameRule(path, from, to string) (renamed bool, clash string, err error) {
	text, err := os.ReadFile(path)
	if os.IsNotExist(err) {
		return false, "", nil
	}
	if err != nil {
		return false, "", err
	}

	decoder := json.NewDecoder(bytes.NewReader(text))
	decoder.DisallowUnknownFields()
	decoder.UseNumber()

	var read document
	if err := decoder.Decode(&read); err != nil {
		return false, "", &cli.InvalidConfiguration{Reason: "config.json: " + err.Error()}
	}

	lists := []*[]string{&read.Detectors}
	if read.Disable != nil {
		lists = append(lists, &read.Disable.Detectors, &read.Disable.Sins, &read.Disable.Skills)
	}

	for _, list := range lists {
		renamed = replaceName(list, from, to) || renamed
	}

	settings, held := read.Configure[from]
	switch {
	case !held:
	case len(read.Configure[to]) > 0:
		clash = fmt.Sprintf("%s and %s both carry settings of their own, so neither is moved: keep the ones you want under %s and take the others out", from, to, to)
	default:
		read.Configure[to] = settings
		delete(read.Configure, from)
		renamed = true
	}

	if !renamed {
		return false, clash, nil
	}

	written, err := json.MarshalIndent(read, "", "  ")
	if err != nil {
		return false, clash, err
	}

	return true, clash, os.WriteFile(path, append(written, '\n'), 0o644)
}

func replaceName(list *[]string, from, to string) bool {
	at := slices.Index(*list, from)
	if at < 0 {
		return false
	}

	if slices.Contains(*list, to) {
		*list = slices.Delete(*list, at, at+1)

		return true
	}

	(*list)[at] = to

	return true
}
