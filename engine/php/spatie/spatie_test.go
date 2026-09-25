package spatie

import (
	"encoding/json"
	"testing"

	"github.com/jessegall/code-commandments/engine"
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
