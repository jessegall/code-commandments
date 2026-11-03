package config

import (
	"bytes"
	"encoding/json"
	"fmt"
	"os"
	"slices"

	"github.com/jessegall/code-commandments/cli"
)

// RenameRule is config.json with every mention of one rule's name standing for another's: the lists that name it,
// and the per-rule settings keyed by it. It is what a migration owes a project whose rule files it moved, since a
// name is how a config reaches a rule everywhere.
//
// The settings are MOVED, not merged. Where the surviving rule already has settings of its own, nothing is
// touched and the clash is answered, because two sets of settings for one rule is a question only the project can
// answer and a migration that guessed would lose one of them silently. That is exactly the loss this exists to
// prevent: a rule renamed and its `judges` left behind reads as a rule that was never configured.
//
// A project with no config.json has nothing to rename and says so by answering false.
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

	// Every key the format has is on the document, since a key it does not have is refused when the config is
	// read, so writing it back whole loses nothing a reader could have meant.
	return true, clash, os.WriteFile(path, append(written, '\n'), 0o644)
}

// replaceName puts to where from stood in the list, said once, and says whether the list held from at all. The place is
// kept, so a config a person ordered on purpose reads as they left it.
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
