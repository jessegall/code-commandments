package engine_test

import (
	"testing"

	"github.com/jessegall/code-commandments/engine"
)

func TestATypeIsNamedWholeWhenQualifiedAndByItsLastPartWhenBare(t *testing.T) {
	for _, each := range []struct {
		symbol, want string
		names        bool
	}{
		{`App\Http\Controller`, "Controller", true},
		{`App\Http\Controller`, `App\Http\Controller`, true},
		{`App\Http\Controller`, `\App\Http\Controller`, true},
		{`\App\Http\Controller`, `App\Http\Controller`, true},
		{`App\Http\Controller`, `Other\Controller`, false},
		{`App\Http\Controller`, "Http", false},
		{"global::System.Collections.Generic.List<T>", "List", true},
		{"global::System.Collections.Generic.List<T>", "System.Collections.Generic.List", true},
		{"System.Action`1", "Action", true},
		{"shop.models.Base", "Base", true},
		{"shop.models.Base", "shop.models.Base", true},
		{"shop.models.Base", "models.Base", false},
		{"/app/src/cart.ts#Cart", "Cart", true},
		{"", "Cart", false},
		{"Cart", "", false},
	} {
		if got := engine.NamesType(each.symbol, each.want); got != each.names {
			t.Errorf("NamesType(%q, %q) = %v", each.symbol, each.want, got)
		}
	}
}

func TestANameIsInTheLayerItsLanguageSpellsIt(t *testing.T) {
	php := engine.Layers(map[string][]string{`app\domain`: nil, `App\Domain\Orders`: nil}, `\`, false)
	python := engine.Layers(map[string][]string{"shop.domain": nil}, ".", true)

	for _, each := range []struct {
		stack       engine.LayerStack
		name, layer string
		in          bool
	}{
		{php, `App\Domain\Money`, `App\Domain`, true},
		{php, `App\Domain\Money`, `app\domain`, true},
		{php, `App\Domain\Orders\Line`, `App\Domain`, false},
		{php, `App\Domain\Orders\Line`, `App\Domain\Orders`, true},
		{php, `App\Http`, `App\Domain`, false},
		{python, "shop.domain.money", "shop.domain", true},
		{python, "shop.domain.money", "Shop.Domain", false},
		{python, "shop.domainer", "shop.domain", false},
	} {
		if got := each.stack.InLayer(each.name, each.layer); got != each.in {
			t.Errorf("InLayer(%q, %q) = %v", each.name, each.layer, got)
		}
	}
}
