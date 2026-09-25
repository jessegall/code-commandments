package spatie

import (
	"encoding/json"
	"os"
	"os/exec"
	"path/filepath"
	"testing"

	"github.com/jessegall/code-commandments/engine"
	"github.com/jessegall/code-commandments/engine/php"
	"github.com/jessegall/code-commandments/engine/php/internal/shop"
)

func TestTheSpatieClassPredicatesAnswerAsPhpDoes(t *testing.T) {
	codebase := shop.Codebase(t)
	shape := DataClassShapeOf(codebase)
	shop.Parity(t, "spatieclasses", func(answer shop.Answer, node engine.Match) any {
		var ask string
		if err := json.Unmarshal(answer.Ask, &ask); err != nil {
			t.Fatal(err)
		}
		data := Node{}.Decorate(node)
		switch ask {
		case "class":
			class := node.Node().Symbol

			return map[string]any{
				"isDataClass":                   data.IsDataClass(),
				"inDataScope":                   data.InDataScope(),
				"isTypeScriptData":              data.IsTypeScriptData(),
				"isPageObject":                  data.IsPageObject(),
				"hasUnhiddenInjectedService":    data.HasUnhiddenInjectedService(),
				"optionalPublicFieldNames":      data.OptionalPublicFieldNames(),
				"everyConstructorParamOptional": data.EveryConstructorParamOptional(),
				"pageObjectMissingTypeScript":   data.PageObjectMissingTypeScript(),
				"remapsInputNames":              shape.RemapsInputNames(class),
				"isRich":                        shape.IsRich(class),
				"composesMultipleData":          shape.ComposesMultipleData(class),
				"pageObject":                    IsPageObject(codebase, class),
			}
		case "field":
			return map[string]any{
				"nestedWireTypeMissingTypeScript": data.NestedWireTypeMissingTypeScript(),
				"nestedWireTypeFqcn":              orNil(data.NestedWireTypeFqcn()),
				"propertyTypedAsDataCollection":   data.PropertyTypedAsDataCollection(),
				"nullableWireObject":              data.NullableWireObject(),
			}
		case "hook":
			return data.HookMissingComputed()
		case "construction":
			return map[string]any{"isNewData": data.IsNewData(), "onDataClass": data.OnDataClass(), "isRichData": data.IsRichData()}
		}
		t.Fatalf("an unknown ask %s", answer.Ask)

		return nil
	})
}

func orNil(text string) any {
	if text == "" {
		return nil
	}

	return text
}

func TestTheSpatieConstructionPredicatesAnswerAsPhpDoes(t *testing.T) {
	shop.Parity(t, "spatieconstructions", func(answer shop.Answer, node engine.Match) any {
		var ask string
		if err := json.Unmarshal(answer.Ask, &ask); err != nil {
			t.Fatal(err)
		}
		data := Node{}.Decorate(node)
		if ask == "field" {
			return data.AlwaysHandBuiltAtConstruction()
		}
		var slot, factory any
		if found, ok := data.HydrationSlot(); ok {
			slot = []any{found.Owner, found.Property, orNil(found.DeclaredType), found.IsCollection, orNil(found.ElementType), found.ValueInList, found.DestHasCast}
		}
		if found, ok := data.MappedFactory(); ok {
			factory = []any{found.Class, found.Method, orNil(found.ReturnsType), found.ClosesOverContext}
		}

		return map[string]any{
			"slot":                        slot,
			"isHandedConstructedData":     data.IsHandedConstructedData(),
			"isPerItemHydration":          data.IsPerItemHydration(),
			"isInlineProjection":          data.IsInlineProjection(),
			"isConditionalConstruction":   data.IsConditionalConstruction(),
			"isWithinTolerantCatch":       data.IsWithinTolerantCatch(),
			"isKeyedMapAssignment":        data.IsKeyedMapAssignment(),
			"isEnumUnwrapIntoItsOwnSlot":  data.IsEnumUnwrapIntoItsOwnSlot(),
			"fromArgIsArrayLiteral":       data.FromArgIsArrayLiteral(),
			"constructedClass":            orNil(data.ConstructedClass()),
			"hydratesAnAutoBuiltSlot":     data.HydratesAnAutoBuiltSlot(),
			"hydrationSlotHasCast":        data.HydrationSlotHasCast(),
			"mappedFactory":               factory,
			"mappedFactoryDerivesElement": data.MappedFactoryDerivesElement(),
			"constructsNativeCastValue":   data.ConstructsNativeCastValue(),
			"hasSingleArgument":           data.HasSingleArgument(),
			"slotAcceptsNativeCast":       data.SlotAcceptsNativeCast(),
			"isHandKeyRemap":              data.IsHandKeyRemap(),
			"isRedundantToArrayRoundtrip": data.IsRedundantToArrayRoundtrip(),
		}
	})
}

func TestTheSpatieAssignmentPredicatesAnswerAsPhpDoes(t *testing.T) {
	shop.Parity(t, "spatieassignments", func(answer shop.Answer, node engine.Match) any {
		var ask string
		if err := json.Unmarshal(answer.Ask, &ask); err != nil {
			t.Fatal(err)
		}
		data := Node{}.Decorate(node)
		switch ask {
		case "assign":
			return map[string]any{
				"assignedPropertyIsPublicSlot": data.AssignedPropertyIsPublicSlot(),
				"assignmentRhsIsDeferred":      data.AssignmentRhsIsDeferred(),
				"assignedSlotTypeIsDeferred":   data.AssignedSlotTypeIsDeferred(),
				"assignmentReadsScopedState":   data.AssignmentReadsScopedState(),
				"assignedSlotIsEager":          data.AssignedSlotIsEager(),
				"propertyAssignedMoreThanOnce": data.PropertyAssignedMoreThanOnce(),
			}
		case "optional":
			return map[string]any{
				"isOptionalAbsentMarker":   data.IsOptionalAbsentMarker(),
				"isOptionalNullFallback":   data.IsOptionalNullFallback(),
				"isSharedOptionalFactory":  data.IsSharedOptionalFactory(),
				"isReplaceableNewOptional": data.IsReplaceableNewOptional(),
			}
		case "attribute":
			return data.TransformerLacksTsType()
		case "flattens":
			return data.FlattensValueObjectToArray()
		case "transformerOutput":
			return orNil(TransformerOutputIn(node.Codebase()))
		}
		t.Fatalf("an unknown ask %s", answer.Ask)

		return nil
	})
}

func TestTheTransformerOutputIsReadFromTheProjectsOwnConfig(t *testing.T) {
	if _, err := exec.LookPath("php"); err != nil {
		t.Fatal("this test runs the PHP bridge, and php is not on PATH: install PHP 8.4+ and run composer install")
	}
	project, err := filepath.EvalSymlinks(t.TempDir())
	if err != nil {
		t.Fatal(err)
	}
	for name, source := range map[string]string{
		"composer.json":               "{}",
		"config/typescript.php":       "<?php\n$folder = 'types';\nreturn ['output_file' => resource_path('js/' . $folder . '/generated.d.ts')];\n",
		"app/Providers/Unrelated.php": "<?php\nnamespace App\\Providers;\nfinal class Unrelated {}\n",
	} {
		path := filepath.Join(project, name)
		if err := os.MkdirAll(filepath.Dir(path), 0o755); err != nil {
			t.Fatal(err)
		}
		if err := os.WriteFile(path, []byte(source), 0o644); err != nil {
			t.Fatal(err)
		}
	}
	codebase, err := php.Here().Scan(project)
	if err != nil {
		t.Fatal(err)
	}
	if got, want := TransformerOutputIn(codebase), project+"/resources/js/types/generated.d.ts"; got != want {
		t.Fatalf("the output is %q, not %q", got, want)
	}
}
