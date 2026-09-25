package php_test

import (
	"os"
	"os/exec"
	"path/filepath"
	"slices"
	"strings"
	"testing"

	"github.com/jessegall/code-commandments/engine/php"
	"github.com/jessegall/code-commandments/engine/php/prose"
	"github.com/jessegall/code-commandments/engine/php/shop"
)

func TestCommentTextReadsAsPhpReadsIt(t *testing.T) {
	for _, php := range shop.PhpProse(t) {
		got := struct {
			history, strawman, inline bool
			words, references         []string
			paragraphs                int
		}{prose.History(php.Text), prose.Strawman(php.Text), phpInline(php.Text), prose.Words(php.Text), phpReferences(php.Text), phpParagraphs(php.Text)}
		if got.history != php.History {
			t.Errorf("history %v, PHP %v: %q", got.history, php.History, php.Text)
		}
		if got.strawman != php.Strawman {
			t.Errorf("strawman %v, PHP %v: %q", got.strawman, php.Strawman, php.Text)
		}
		if got.inline != php.Inline {
			t.Errorf("inline %v, PHP %v: %q", got.inline, php.Inline, php.Text)
		}
		if !slices.Equal(got.words, php.Words) {
			t.Errorf("words %v, PHP %v: %q", got.words, php.Words, php.Text)
		}
		if !slices.Equal(got.references, php.References) && len(got.references)+len(php.References) > 0 {
			t.Errorf("references %v, PHP %v: %q", got.references, php.References, php.Text)
		}
		if got.paragraphs != php.Paragraphs {
			t.Errorf("paragraphs %d, PHP %d: %q", got.paragraphs, php.Paragraphs, php.Text)
		}
	}
}

func phpInline(text string) bool         { return php.DocblockIsInline(text) }
func phpReferences(text string) []string { return php.DocReferences(text) }
func phpParagraphs(text string) int      { return php.DocParagraphs(text) }

func TestTheBridgeMarksCommentedOutCodeAsPhpReadsIt(t *testing.T) {
	code := map[string]bool{}
	var probes []string
	for _, php := range shop.PhpProse(t) {
		code[php.Text] = php.Code
		if strings.HasPrefix(php.Text, "//") || strings.HasPrefix(php.Text, "#") {
			probes = append(probes, php.Text)
		}
	}
	source := filepath.Join(t.TempDir(), "Probes.php")
	if err := os.WriteFile(source, []byte("<?php\n\nfunction probes(): void\n{\n    "+strings.Join(probes, "\n    ")+"\n    return;\n}\n"), 0o644); err != nil {
		t.Fatal(err)
	}
	if _, err := exec.LookPath("php"); err != nil {
		t.Fatal("this test runs the PHP bridge, and php is not on PATH: install PHP 8.4+ and run composer install")
	}
	stream, err := php.Here().Stream(source)
	if err != nil {
		t.Fatal(err)
	}
	marked := 0
	for _, file := range stream.Files {
		for _, comment := range file.Comments {
			flagged := comment.Extras != nil && comment.Extras.PHP != nil && comment.Extras.PHP.Code
			if flagged != code[comment.Text] {
				t.Errorf("the bridge marks code %v, PHP reads %v: %q", flagged, code[comment.Text], comment.Text)
			}
			if flagged {
				marked++
			}
		}
	}
	if marked == 0 {
		t.Error("the probes hold commented-out code, and the bridge marks none")
	}
}
