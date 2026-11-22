package bridges

import (
	"encoding/json"
	"os"
	"path/filepath"
	"slices"
	"strings"
	"testing"

	"github.com/jessegall/code-commandments/cli/scan"
	"github.com/jessegall/code-commandments/cli/source"
	"github.com/jessegall/code-commandments/contract"
)

var memberSources = map[source.Language][2]string{
	source.PHP:        {"<?php\n\nenum Kind: string\n{\n    case Food = 'food';\n}\n", "<?php\n\nfinal class Cart\n{\n    public function kind(): string\n    {\n        return Kind::Food->value;\n    }\n}\n"},
	source.TypeScript: {"export enum Kind { Food = 'food' }\n", "import { Kind } from './one'\n\nexport const kind = Kind.Food\n"},
	source.Python:     {"from enum import Enum\n\n\nclass Kind(Enum):\n    Food = \"food\"\n", "from one import Kind\n\n\ndef kind() -> Kind:\n    return Kind.Food\n"},
	source.Go:         {"package shop\n\ntype Kind struct{ Food string }\n", "package shop\n\nfunc kind(k Kind) string { return k.Food }\n"},
	source.CSharp:     {"namespace Shop;\n\npublic enum Kind { Food }\n", "namespace Shop;\n\npublic static class Cart\n{\n    public static Kind Of() => Kind.Food;\n}\n"},
}

func TestEveryBridgeWritesAFileAsIfEveryFileWereWritten(t *testing.T) {
	for _, held := range scan.Bridges {
		language, texts, known := languageWith(held, memberSources)
		if !known {
			t.Errorf("no sources to ask the %s bridge with", held.Label)

			continue
		}
		t.Run(held.Label, func(t *testing.T) {
			t.Parallel()
			defer proved(t, language, "promise/read-declaration-resolves")
			project := t.TempDir()
			placedFile(t, project, "go.mod", "module shop\n\ngo 1.22\n")
			extension := "." + contract.ExtensionsOf(language.Written())[0]
			declaring, reading := filepath.Join(project, "one"+extension), filepath.Join(project, "two"+extension)
			placedFile(t, project, "one"+extension, texts[0])
			placedFile(t, project, "two"+extension, texts[1])

			readingAlone, err := held.ReadWriting([]string{project}, []string{declaring, reading}, []string{reading})
			if err != nil {
				t.Skipf("the %s bridge could not be reached here: %v", held.Label, err)
			}
			bothWritten, err := held.ReadWriting([]string{project}, []string{declaring, reading}, []string{declaring, reading})
			if err != nil {
				t.Fatal(err)
			}

			food := foodSymbol(t, fileNamed(bothWritten, "one"+extension))
			if !resolvesTo(fileNamed(readingAlone, "two"+extension), food) {
				t.Errorf("written alone, %s does not resolve Food to %s, which %s declares", reading, food, declaring)
			}
			alone, both := nodesOf(t, fileNamed(readingAlone, "two"+extension)), nodesOf(t, fileNamed(bothWritten, "two"+extension))
			if alone != both {
				t.Errorf("%s is written one way when %s is only read and another when both are written:\nalone %s\nboth  %s", reading, declaring, alone, both)
			}
		})
	}
}

var overrideSources = map[source.Language][2]string{
	source.PHP:        {"<?php\n\nabstract class Base\n{\n    abstract public function run(): void;\n}\n", "<?php\n\nfinal class Job extends Base\n{\n    public function run(): void {}\n}\n"},
	source.TypeScript: {"export abstract class Base {\n    abstract run(): void\n}\n", "import { Base } from './one'\n\nexport class Job extends Base {\n    run(): void {}\n}\n"},
	source.Python:     {"class Base:\n    def run(self) -> None:\n        pass\n", "from one import Base\n\n\nclass Job(Base):\n    def run(self) -> None:\n        pass\n"},
	source.Go:         {"package shop\n\ntype Runner interface{ Run() }\n\nfunc start(r Runner) { r.Run() }\n", "package shop\n\ntype Job struct{}\n\nfunc (Job) Run() {}\n"},
	source.CSharp:     {"namespace Shop;\n\npublic abstract class Base\n{\n    public abstract void Run();\n}\n", "namespace Shop;\n\npublic sealed class Job : Base\n{\n    public override void Run() {}\n}\n"},
}

func TestEveryBridgeNamesWhatAWrittenMethodOverrides(t *testing.T) {
	for _, held := range scan.Bridges {
		language, texts, known := languageWith(held, overrideSources)
		if !known {
			t.Errorf("no sources to ask the %s bridge with", held.Label)

			continue
		}
		t.Run(held.Label, func(t *testing.T) {
			t.Parallel()
			defer proved(t, language, "promise/obligation-across-files")
			project := t.TempDir()
			placedFile(t, project, "go.mod", "module shop\n\ngo 1.22\n")
			extension := "." + contract.ExtensionsOf(language.Written())[0]
			declaring, overriding := filepath.Join(project, "one"+extension), filepath.Join(project, "two"+extension)
			placedFile(t, project, "one"+extension, texts[0])
			placedFile(t, project, "two"+extension, texts[1])

			stream, err := held.ReadWriting([]string{project}, []string{declaring, overriding}, []string{declaring, overriding})
			if err != nil {
				t.Skipf("the %s bridge could not be reached here: %v", held.Label, err)
			}
			base := symbolNamed(fileNamed(stream, "one"+extension), "run", "Run")
			if base == "" {
				t.Fatalf("%s declares no method run with a symbol", declaring)
			}
			readAlone, err := held.ReadWriting([]string{project}, []string{declaring, overriding}, []string{overriding})
			if err != nil {
				t.Fatal(err)
			}
			for _, written := range []*contract.Stream{stream, readAlone} {
				if !obliged(fileNamed(written, "two"+extension), base) {
					t.Errorf("the method run in %s names no obligation to %s", overriding, base)
				}
			}
		})
	}
}

var parameterSources = map[source.Language]string{
	source.PHP:        "<?php\n\nfunction doubled(int $total): int\n{\n    return $total * 2;\n}\n",
	source.TypeScript: "export function doubled(total: number): number {\n    return total * 2\n}\n",
	source.Python:     "def doubled(total: int) -> int:\n    return total * 2\n",
	source.Go:         "package shop\n\nfunc doubled(total int) int { return total * 2 }\n",
	source.CSharp:     "namespace Shop;\n\npublic static class Sums\n{\n    public static int Doubled(int total) => total * 2;\n}\n",
}

func TestEveryBridgeLinksAReadToItsLocalBinding(t *testing.T) {
	for _, held := range scan.Bridges {
		var language source.Language
		var text string
		for _, each := range held.Languages {
			if found, known := parameterSources[each]; known {
				language, text = each, found

				break
			}
		}
		if text == "" {
			t.Errorf("no source to ask the %s bridge with", held.Label)

			continue
		}
		t.Run(held.Label, func(t *testing.T) {
			t.Parallel()
			defer proved(t, language, "promise/read-links-binding")
			project := t.TempDir()
			placedFile(t, project, "go.mod", "module shop\n\ngo 1.22\n")
			name := "one." + contract.ExtensionsOf(language.Written())[0]
			placedFile(t, project, name, text)
			path := filepath.Join(project, name)

			stream, err := held.ReadWriting([]string{project}, []string{path}, []string{path})
			if err != nil {
				t.Skipf("the %s bridge could not be reached here: %v", held.Label, err)
			}
			file := fileNamed(stream, name)
			if file == nil {
				t.Fatal("the file was not written")
			}
			nodes := file.Nodes()
			bindings := map[int]bool{}
			for _, node := range nodes {
				if node.Kind == "binding" && node.Facts != nil && node.Name == "total" {
					bindings[node.ID] = true
				}
			}
			for _, node := range nodes {
				if node.Kind == "reference" && node.Facts != nil && node.Name == "total" {
					if linked := node.Linked("binding"); len(linked) != 1 || !bindings[linked[0]] {
						t.Errorf("the read of total at %v links to %v, not to the parameter's binding %v", node.Span, linked, bindings)
					}

					return
				}
			}
			t.Fatal("no reference named total was written")
		})
	}
}

func symbolNamed(file *contract.File, names ...string) string {
	if file == nil {
		return ""
	}
	for _, node := range file.Nodes() {
		if node.Facts != nil && node.Kind == "function" && slices.Contains(names, node.Name) && node.Symbol != "" {
			return node.Symbol
		}
	}

	return ""
}

func obliged(file *contract.File, base string) bool {
	return file != nil && slices.ContainsFunc(file.Nodes(), func(node *contract.Node) bool {
		return node.Facts != nil && node.Kind == "function" && slices.ContainsFunc(node.Obligations, func(obligation contract.Obligation) bool {
			return obligation.Symbol == base && obligation.Origin == "project"
		})
	})
}

func languageWith(held scan.Bridge, texts map[source.Language][2]string) (source.Language, [2]string, bool) {
	for _, each := range held.Languages {
		if found, known := texts[each]; known {
			return each, found, true
		}
	}

	return "", [2]string{}, false
}

func fileNamed(stream *contract.Stream, name string) *contract.File {
	for _, file := range stream.Files {
		if filepath.Base(file.Path) == name {
			return file
		}
	}

	return nil
}

func foodSymbol(t *testing.T, file *contract.File) string {
	t.Helper()
	if file == nil {
		t.Fatal("the file declaring Food was not written")
	}
	for _, node := range file.Nodes() {
		if node.Facts != nil && node.Name == "Food" && node.Symbol != "" {
			return node.Symbol
		}
	}
	t.Fatal("nothing in the declaring file names Food with a symbol")

	return ""
}

func resolvesTo(file *contract.File, symbol string) bool {
	return file != nil && slices.ContainsFunc(file.Nodes(), func(node *contract.Node) bool {
		return node.Facts != nil && (node.Refers == symbol || node.Target != nil && node.Target.Symbol == symbol)
	})
}

func nodesOf(t *testing.T, file *contract.File) string {
	t.Helper()
	if file == nil {
		t.Fatal("the reading file was not written")
	}
	shown, err := json.Marshal(file.Nodes())
	if err != nil {
		t.Fatal(err)
	}

	return string(shown)
}

func placedFile(t *testing.T, root, relative, text string) {
	t.Helper()
	path := filepath.Join(root, relative)
	if err := os.MkdirAll(filepath.Dir(path), 0o755); err != nil {
		t.Fatal(err)
	}
	if err := os.WriteFile(path, []byte(text), 0o644); err != nil {
		t.Fatal(err)
	}
}

func proved(t *testing.T, language source.Language, promise string) {
	if !t.Skipped() {
		witnessed.add(language.Written(), map[string]bool{promise: !t.Failed()})
	}
}

var importCases = []struct {
	language contract.Language
	files    map[string]string
	reading  string
	reached  string
	items    []string
}{
	{contract.PHP, map[string]string{"one.php": "<?php\n\nnamespace Shop;\n\nfinal class Money {}\n", "two.php": "<?php\n\nnamespace App;\n\nuse Shop\\Money;\n\nfinal class Cart\n{\n    public function total(): Money\n    {\n        return new Money();\n    }\n}\n"}, "two.php", "one.php", []string{"fact/import/resolves"}},
	{contract.Python, map[string]string{"one.py": "class Money:\n    pass\n", "two.py": "from one import Money\n\n\ndef total() -> Money:\n    return Money()\n"}, "two.py", "one.py", []string{"fact/import/resolves"}},
	{contract.TypeScript, map[string]string{"one.ts": "export class Money {}\n", "two.ts": "import { Money } from './one'\n\nexport const total = new Money()\n"}, "two.ts", "one.ts", []string{"fact/import/resolves"}},
	{contract.Vue, map[string]string{"one.vue": "<template>\n    <p>one</p>\n</template>\n", "two.vue": "<script setup lang=\"ts\">\nimport One from './one.vue'\n</script>\n\n<template>\n    <One />\n</template>\n"}, "two.vue", "one.vue", []string{"fact/import/resolves", "fact/element/resolves"}},
	{contract.Go, map[string]string{"go.mod": "module shop\n\ngo 1.22\n", "money/money.go": "package money\n\ntype Money int\n", "two.go": "package shop\n\nimport \"shop/money\"\n\nvar total money.Money\n"}, "two.go", "money", []string{"fact/import/resolves"}},
}

func TestEveryBridgeResolvesAnImportToTheFileItReads(t *testing.T) {
	for _, each := range importCases {
		t.Run(string(each.language), func(t *testing.T) {
			t.Parallel()
			project, err := filepath.EvalSymlinks(t.TempDir())
			if err != nil {
				t.Fatal(err)
			}
			var read []string
			for name, text := range each.files {
				placedFile(t, project, name, text)
				if _, known := contract.LanguageOfPath(name); known {
					read = append(read, filepath.Join(project, name))
				}
			}
			stream, err := bridgeOf[each.language].ReadWriting([]string{project}, read, []string{filepath.Join(project, each.reading)})
			if err != nil {
				t.Skipf("the %s bridge could not be reached here: %v", each.language, err)
			}
			file := fileNamed(stream, each.reading)
			if file == nil {
				t.Fatalf("the %s bridge wrote no %s", each.language, each.reading)
			}
			reached, resolving := filepath.Join(project, each.reached), map[string]bool{}
			for _, node := range file.Nodes() {
				if node.Resolves == reached {
					resolving[node.Kind] = true
				}
			}
			for _, item := range each.items {
				kind := strings.Split(item, "/")[1]
				witnessed.add(each.language, map[string]bool{item: resolving[kind]})
				if !resolving[kind] {
					t.Errorf("no %s of %s resolves to %s", kind, each.reading, reached)
				}
			}
		})
	}
}
