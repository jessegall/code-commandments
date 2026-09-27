package backend

import (
	"crypto/sha256"
	"encoding/hex"
	"os"
	"path/filepath"
	"slices"
	"strings"
	"testing"

	"github.com/jessegall/code-commandments/engine/php"
	"github.com/jessegall/code-commandments/scribes"
)

// hintProject is one of PHP's HintsTest projects: its files, beside the Data stub the test writes, and the files a
// scoped run is restricted to.
type hintProject struct {
	name   string
	scoped []string
	files  map[string]string
}

// hintScope is a run's scope: every file, or only the ones named.
type hintScope struct{ files []string }

func (s hintScope) Includes(path string) bool {
	return s.files == nil || slices.Contains(s.files, path)
}
func (s hintScope) IsScoped() bool { return s.files != nil }

// Each of PHP's HintsTest projects, rewritten here and by PHP's DataHintScribe, must come out byte for byte the same.
func TestDataHintRewritesEachHintsProjectAsThePHPToolDoes(t *testing.T) {
	for _, project := range hintProjects {
		t.Run(project.name, func(t *testing.T) {
			dir, err := filepath.EvalSymlinks(t.TempDir())
			if err != nil {
				t.Fatal(err)
			}
			for name, content := range project.files {
				if err := os.WriteFile(filepath.Join(dir, name), []byte(content), 0o644); err != nil {
					t.Fatal(err)
				}
			}
			stub := "<?php\nnamespace Spatie\\LaravelData { class Data {} }\n"
			if err := os.WriteFile(filepath.Join(dir, "Spatie.php"), []byte(stub), 0o644); err != nil {
				t.Fatal(err)
			}
			var scoped []string
			for _, name := range project.scoped {
				scoped = append(scoped, filepath.Join(dir, name))
			}

			want := hintAnswer(t, project, dir)
			codebase, err := php.Here().Scan(dir)
			if err != nil {
				t.Fatal(err)
			}
			rewrites, err := DataHintScribe{}.Maintain(codebase, scribes.Pass{Roots: []string{dir}, Scope: hintScope{files: scoped}})
			if err != nil {
				t.Fatal(err)
			}
			got := rewrites.Contents()
			if len(got) != len(want) {
				t.Fatalf("rewrote %d files, PHP %d", len(got), len(want))
			}
			for path, content := range want {
				if got[path] != content {
					t.Errorf("%s differs:\n--- go\n%s\n--- php\n%s", filepath.Base(path), got[path], content)
				}
			}
		})
	}
}

// hintAnswer is what PHP's DataHintScribe rewrote in the project, by path, as recorded.
func hintAnswer(t *testing.T, project hintProject, dir string) map[string]string {
	t.Helper()
	hintAnswersOnce.Do(loadHintAnswers)
	relative, ok := hintAnswers[project.key()]
	if !ok {
		t.Fatalf("PHP's hints for %s are not recorded", project.name)
	}
	answer := map[string]string{}
	for path, content := range relative {
		answer[dir+"/"+path] = content
	}

	return answer
}

// key names the project by what it holds: its name, its scope and every file.
func (p hintProject) key() string {
	names := make([]string, 0, len(p.files))
	for name := range p.files {
		names = append(names, name)
	}
	slices.Sort(names)
	hash := sha256.New()
	hash.Write([]byte(p.name + "\x00" + strings.Join(p.scoped, ",") + "\x00"))
	for _, name := range names {
		hash.Write([]byte(name + "\x00" + p.files[name] + "\x00"))
	}

	return hex.EncodeToString(hash.Sum(nil))
}

var hintProjects = []hintProject{
	{name: "test_does_not_rename_a_multi_parameter_named_constructor", scoped: nil, files: map[string]string{
		"OutputSocketData.php": `<?php
namespace Demo;
use Spatie\LaravelData\Data;

final class OutputSocketData extends Data
{
    public function __construct(public readonly string $name, public readonly int $size) {}

    public static function make(string $name, int $size): self
    {
        return new self($name, $size);
    }
}`,
		"Builder.php": `<?php
namespace Demo;
class Builder
{
    public function build()
    {
        return OutputSocketData::make(name: 'result', size: 3);
    }
}`,
	}},
	{name: "test_strips_a_named_argument_from_a_single_param_factory_call", scoped: nil, files: map[string]string{
		"CredentialData.php": `<?php
namespace Demo;
use Spatie\LaravelData\Data;
use Demo\Models\Credential;

final class CredentialData extends Data
{
    public function __construct(public readonly string $id) {}

    public static function forCredential(Credential $credential): self
    {
        return self::from(['id' => $credential->id]);
    }
}`,
		"Caller.php": `<?php
namespace Demo;
class Caller
{
    public function show($credential)
    {
        return CredentialData::forCredential(credential: $credential);
    }
}`,
	}},
	{name: "test_renames_non_from_factory_rewrites_call_sites_and_fixes_the_method_tag", scoped: nil, files: map[string]string{
		"CredentialData.php": `<?php
namespace Demo;
use Spatie\LaravelData\Data;
use Demo\Models\Credential;

/**
 * The view of a stored credential.
 *
 * @method static static forCredential(Credential $credential)
 */
final class CredentialData extends Data
{
    public function __construct(public readonly string $id) {}

    public static function forCredential(Credential $credential): self
    {
        return self::from(['id' => $credential->id]);
    }
}`,
		"Caller.php": `<?php
namespace Demo;
class Caller
{
    public function show($credential)
    {
        return CredentialData::forCredential($credential);
    }
}`,
	}},
	{name: "test_documents_a_from_prefixed_factory_without_renaming_it", scoped: nil, files: map[string]string{
		"AgentData.php": `<?php
namespace Demo;
use Spatie\LaravelData\Data;
use Demo\Models\Agent;

final class AgentData extends Data
{
    public function __construct(public readonly int $id) {}

    public static function fromModel(Agent $agent): self
    {
        return self::from(['id' => $agent->id]);
    }
}`,
		"Caller.php": `<?php
namespace Demo;
class Caller
{
    public function show($agent)
    {
        return AgentData::fromModel($agent);
    }
}`,
	}},
	{name: "test_adds_the_conditional_collect_hint_when_the_class_is_collected", scoped: nil, files: map[string]string{
		"RowData.php": `<?php
namespace Demo;
use Spatie\LaravelData\Data;

final class RowData extends Data
{
    public function __construct(public readonly string $value) {}
}`,
		"Importer.php": `<?php
namespace Demo;
class Importer
{
    public function rows(array $rows): array
    {
        return RowData::collect($rows);
    }
}`,
	}},
	{name: "test_synthesises_a_docblock_when_the_class_has_none", scoped: nil, files: map[string]string{
		"TagData.php": `<?php
namespace Demo;
use Spatie\LaravelData\Data;
use Demo\Models\Tag;

final class TagData extends Data
{
    public function __construct(public readonly string $label) {}

    public static function make(Tag $tag): self
    {
        return new self($tag->label);
    }
}`,
	}},
	{name: "test_dry_run_writes_nothing_and_prints_a_diff", scoped: nil, files: map[string]string{
		"CredentialData.php": `<?php
namespace Demo;
use Spatie\LaravelData\Data;
use Demo\Models\Credential;

final class CredentialData extends Data
{
    public function __construct(public readonly string $id) {}

    public static function forCredential(Credential $credential): self
    {
        return self::from(['id' => $credential->id]);
    }
}`,
	}},
	{name: "test_leaves_non_data_classes_alone", scoped: nil, files: map[string]string{
		"Widget.php": `<?php
namespace Demo;
class Widget
{
    public static function ofSize(int $n): self
    {
        return new self;
    }
}`,
	}},
	{name: "test_scoped_run_is_docblock_only_restricted_to_the_given_files", scoped: []string{"OrderData.php"}, files: map[string]string{
		"OrderData.php": `<?php
namespace Demo;
use Spatie\LaravelData\Data;
use Demo\Models\Order;
use Demo\Models\Credential;

final class OrderData extends Data
{
    public function __construct(public readonly int $id) {}

    public static function fromModel(Order $order): self
    {
        return self::from(['id' => $order->id]);
    }

    public static function forCredential(Credential $credential): self
    {
        return self::from(['id' => $credential->id]);
    }
}`,
		"TagData.php": `<?php
namespace Demo;
use Spatie\LaravelData\Data;
use Demo\Models\Tag;

final class TagData extends Data
{
    public function __construct(public readonly string $label) {}

    public static function ofTag(Tag $tag): self
    {
        return new self($tag->label);
    }
}`,
	}},
}
