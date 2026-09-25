package shop_test

import (
	"reflect"
	"slices"
	"strings"
	"testing"

	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/detectors"
	"github.com/jessegall/code-commandments/engine/php/packages"
	"github.com/jessegall/code-commandments/engine/php/shop"
	_ "github.com/jessegall/code-commandments/registry"
	"github.com/jessegall/code-commandments/sins"
	"github.com/jessegall/code-commandments/skill"
)

func TestEveryBackendSkillStatesWhatItsPhpTwinStates(t *testing.T) {
	ported := map[string]skill.Skill{}
	for _, rule := range skill.Every(catalog.Backend) {
		ported[rule.Definition().Slug] = rule
	}
	for _, php := range shop.PhpRules(t).Skills {
		rule, ok := ported[php.Slug]
		if !ok {
			t.Errorf("no Go skill %s", php.Slug)
			continue
		}
		delete(ported, php.Slug)
		sameHome(t, rule, php.Class)
		sameRule(t, php.Slug, rule, php.Unpublished, skill.Definition{
			Slug:                  php.Slug,
			Tier:                  skill.Tier(php.Tier),
			Order:                 php.Order,
			Title:                 php.Title,
			Trigger:               php.Trigger,
			Intro:                 php.Intro,
			Summary:               php.Summary,
			Principle:             php.Principle,
			ExamplesKeepDocblocks: php.ExamplesKeepDocblocks,
			Languages:             php.Languages,
			Related:               related(php),
			References:            references(php),
		}, rule.Definition())
	}
	for slug := range ported {
		t.Errorf("Go skill %s has no PHP twin", slug)
	}
}

func TestEveryBackendSinStatesWhatItsPhpTwinStates(t *testing.T) {
	ported := map[string]sins.Sin{}
	for _, rule := range sins.Every(catalog.Backend) {
		ported[catalog.Name(rule)] = rule
	}
	for _, php := range shop.PhpRules(t).Sins {
		name := php.Class[strings.LastIndex(php.Class, `\`)+1:]
		rule, ok := ported[name]
		if !ok {
			t.Errorf("no Go sin %s", php.Class)
			continue
		}
		delete(ported, name)
		sameHome(t, rule, php.Class)
		definition := rule.Definition()
		if definition.Slug() != php.Skill {
			t.Errorf("%s points at %s, PHP at %s", name, definition.Slug(), php.Skill)
		}
		expected := sins.Definition{
			Name:        php.Name,
			Skill:       definition.Skill,
			Description: php.Description,
			Rule:        php.Rule,
			Scaffolds:   scaffolds(php),
		}
		if php.Suggestion != nil {
			expected.Suggestion = *php.Suggestion
		}
		if php.Requires != nil {
			expected.Requires = sins.Package{Name: php.Requires.Name, Ecosystem: sins.Ecosystem(php.Requires.Ecosystem)}
		}
		sameRule(t, name, rule, php.Unpublished, expected, definition)
	}
	for name := range ported {
		t.Errorf("Go sin %s has no PHP twin", name)
	}
}

func TestEveryPortedBackendDetectorIsItsPhpTwin(t *testing.T) {
	php := map[string]shop.DetectorRule{}
	for _, rule := range shop.PhpRules(t).Detectors {
		php[rule.Name()] = rule
	}
	for _, detector := range detectors.Every(catalog.Backend) {
		name := catalog.Name(detector)
		twin, ok := php[name]
		if !ok {
			t.Errorf("Go detector %s has no PHP twin", name)
			continue
		}
		sameHome(t, detector, twin.Class)
		if got := detector.Sin().Definition().Name; got != twin.Sin {
			t.Errorf("%s finds %s, PHP finds %s", name, got, twin.Sin)
		}
		if catalog.IsUnpublished(detector) != twin.Unpublished {
			t.Errorf("%s is unpublished in one engine only", name)
		}
		for _, capability := range twin.Capabilities {
			carries, known := capabilities[capability]
			if !known {
				t.Errorf("%s declares %s, which has no Go form yet", name, capability)
				continue
			}
			if !carries(detector) {
				t.Errorf("%s does not declare %s, as its PHP twin does", name, capability)
			}
		}
		for capability, carries := range capabilities {
			if carries(detector) && !slices.Contains(twin.Capabilities, capability) {
				t.Errorf("%s declares %s, which its PHP twin does not", name, capability)
			}
		}
	}
}

// capabilities are the PHP detector capabilities that have a Go form, by the PHP interface's name.
var capabilities = map[string]func(detectors.Detector) bool{
	"WholeTree":          is[detectors.WholeTree],
	"Repentable":         is[detectors.Repentable],
	"RunsLast":           is[detectors.RunsLast],
	"RequiresBestDesign": is[detectors.RequiresBestDesign],
	"Exemptable":         is[packages.Exemptable],
	"RecurrenceDetector": is[detectors.Grouped],
	"ChainDetector":      is[detectors.ChainDetector],
	"ConsumesContracts":  is[detectors.ConsumesContracts],
}

func is[C any](detector detectors.Detector) bool {
	_, ok := detector.(C)

	return ok
}

// sameHome fails unless a Go rule lives in the package folder its PHP twin's class does: Laravel\FacadeCall in laravel.
func sameHome(t *testing.T, rule any, class string) {
	t.Helper()
	folder := ""
	if at := strings.LastIndex(class, `\`); at >= 0 {
		folder = "/" + strings.ToLower(class[:at])
	}
	home := reflect.TypeOf(rule).PkgPath()
	if !strings.HasSuffix(home, "/backend"+folder) {
		t.Errorf("%s lives in %s, its PHP twin in %s", catalog.Name(rule), home, class)
	}
}

func sameRule(t *testing.T, name string, rule any, unpublished bool, expected, got any) {
	t.Helper()
	if catalog.IsUnpublished(rule) != unpublished {
		t.Errorf("%s is unpublished in one engine only", name)
	}
	if !reflect.DeepEqual(expected, got) {
		t.Errorf("%s states differently from PHP:\nPHP %#v\nGo  %#v", name, expected, got)
	}
}

func related(php shop.SkillRule) []skill.Relation {
	var related []skill.Relation
	for _, each := range php.Related {
		related = append(related, skill.Relation{Slug: each.Skill, Note: each.Reason})
	}

	return related
}

func references(php shop.SkillRule) []skill.Reference {
	var references []skill.Reference
	for _, each := range php.References {
		references = append(references, skill.Reference{Name: each.Name, Title: each.Title, Body: each.Body})
	}

	return references
}

func scaffolds(php shop.SinRule) []sins.Scaffold {
	var scaffolds []sins.Scaffold
	for _, each := range php.Scaffolds {
		scaffolds = append(scaffolds, sins.Scaffold{Path: each.Path, Stub: each.Stub, Target: sins.ScaffoldTarget(each.Target)})
	}

	return scaffolds
}
