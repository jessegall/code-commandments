package config

import (
	"errors"
	"os"
	"path/filepath"
	"reflect"
	"testing"

	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/cli"
	"github.com/jessegall/code-commandments/cli/source"
)

var deepNested = Rule{Detector, catalog.Frontend, "DeepNestedDetector"}

func TestEveryCallTheConfigMakesIsReadWithoutRunningIt(t *testing.T) {
	path := write(t, `<?php
use JesseGall\CodeCommandments\Config;
use JesseGall\CodeCommandments\Skills;
use JesseGall\CodeCommandments\Sins\Python\DictBag;
use JesseGall\CodeCommandments\Language;
use JesseGall\CodeCommandments\Detectors\Frontend\DeepNestedDetector;

$menu = function (Config $config): void {
    $config->disable(
        // Skills\Backend\Absence::class,
        Skills\Python\Absence::class,
    );
};

return function (Config $config) use ($menu): void {
    $menu($config);
    $config->paths('src', 'resources/js')->exclude('src/Generated');
    $config->disable(DictBag::class, Language::CSharp, 'App\Rules\Old');
    $config->detector(\App\Commandments\NoRawSql::class);
    $config->package(\App\Commandments\Framework::class);
    $config->hook(\JesseGall\CodeCommandments\Hooks\Handlers\SkillReminder::class)->agent(\App\Agents\Aider::class);
    $config->configure(fn (DeepNestedDetector $d) => $d->maxDepth(10)->named(label: 'deep', on: true, list: ['a', -2]));
    $config->configure(function (DeepNestedDetector $d) {
        return $d->maxDepth(3);
    });
};
`)

	config, err := ReadPHP(path)
	if err != nil {
		t.Fatal(err)
	}

	want := Config{
		Paths:             []string{"src", "resources/js"},
		Excluded:          []string{"src/Generated"},
		Disabled:          []Rule{{Skill, catalog.Python, "Absence"}, {Sin, catalog.Python, "DictBag"}, {Detector, "", "Old"}},
		DisabledLanguages: []source.Language{source.CSharp},
		Detectors:         []string{"NoRawSql"},
		Packages:          []string{"Framework"},
		Hooks:             []string{"SkillReminder"},
		Agents:            []string{"Aider"},
		Configurators: []Configurator{
			{deepNested, []Call{
				{"maxDepth", []Arg{{"", 10}}},
				{"named", []Arg{{"label", "deep"}, {"on", true}, {"list", []any{"a", -2}}}},
			}},
			{deepNested, []Call{{"maxDepth", []Arg{{"", 3}}}}},
		},
	}

	if !reflect.DeepEqual(config, want) {
		t.Errorf("read\n%#v\nwant\n%#v", config, want)
	}
}

func TestTheFixtureDeclaresItsLayersThroughConfigure(t *testing.T) {
	config, err := ReadPHP("../../tests/Fixtures/backend/.commandments/config.php")

	if err != nil || len(config.Configurators) != 1 || len(config.Configurators[0].Calls) != 4 {
		t.Fatalf("%+v %v", config, err)
	}

	if call := config.Configurators[0].Calls[3]; call.Method != "layer" || call.Args[1].Name != "mayUse" || len(call.Args[1].Value.([]any)) != 3 {
		t.Errorf("last layer %+v", call)
	}
}

func TestNoConfigFileIsTheEmptyConfig(t *testing.T) {
	if config, err := Load(t.TempDir()); err != nil || !reflect.DeepEqual(config, Config{}) {
		t.Errorf("%+v %v", config, err)
	}
}

func TestWhatCannotBeReadWithoutRunningItIsAnInvalidConfiguration(t *testing.T) {
	_, err := ReadPHP(write(t, "<?php\nreturn function ($config) {\n    $config->exclude(getenv('X'));\n};\n"))

	var invalid *cli.InvalidConfiguration
	if !errors.As(err, &invalid) || invalid.Reason != "line 3 is not something the tool can read without running it — it wants a literal here." {
		t.Errorf("err %v", err)
	}
}

func TestAClassNamesAShippedRuleByKindEngineAndName(t *testing.T) {
	for class, want := range map[string]Rule{
		`JesseGall\CodeCommandments\Skills\Backend\Spatie\SpatieData`:            {Skill, catalog.Backend, "SpatieData"},
		`\JesseGall\CodeCommandments\Sins\Frontend\TypeScript\DuplicateFunction`: {Sin, catalog.TypeScript, "DuplicateFunction"},
		`JesseGall\CodeCommandments\Detectors\Frontend\DuplicateElementDetector`: {Detector, catalog.Frontend, "DuplicateElementDetector"},
		`JesseGall\CodeCommandments\Skills\TypeScript\Absence`:                   {Skill, catalog.TypeScript, "Absence"},
		`JesseGall\CodeCommandments\Detectors\CSharp\DuplicateMethodDetector`:    {Detector, catalog.CSharp, "DuplicateMethodDetector"},
		`JesseGall\CodeCommandments\Sins\Python\DictBag`:                         {Sin, catalog.Python, "DictBag"},
		`JesseGall\CodeCommandments\Hooks\Handlers\JudgeReminder`:                {Hook, "", "JudgeReminder"},
		`JesseGall\CodeCommandments\Agents\CodexAgent`:                           {Agent, "", "CodexAgent"},
	} {
		if got, shipped := RuleOf(class); !shipped || got != want {
			t.Errorf("%s: %+v %v", class, got, shipped)
		}
	}

	for _, class := range []string{`App\Rules\Old`, `JesseGall\CodeCommandments\Config`} {
		if _, shipped := RuleOf(class); shipped {
			t.Errorf("%s read as a shipped rule", class)
		}
	}
}

func write(t *testing.T, contents string) string {
	t.Helper()
	path := filepath.Join(t.TempDir(), "config.php")

	if err := os.WriteFile(path, []byte(contents), 0o644); err != nil {
		t.Fatal(err)
	}

	return path
}

type pointerRule struct{}

func TestARuleRegisteredAsAPointerIsNamedByItsDeclaration(t *testing.T) {
	if byValue, byPointer := ClassOf(Detector, pointerRule{}), ClassOf(Detector, &pointerRule{}); byPointer != byValue {
		t.Errorf("%q is not %q", byPointer, byValue)
	}
}
