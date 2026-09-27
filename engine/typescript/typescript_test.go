package typescript_test

import (
	"slices"
	"testing"

	"github.com/jessegall/code-commandments/engine/frontend/frontendtest"
	"github.com/jessegall/code-commandments/engine/typescript"
)

func TestAnExpressionKnowsItsChainsAndHowDeepItReaches(t *testing.T) {
	codebase := frontendtest.FromSource(t, map[string]string{"reach.ts": `
declare const order: any, form: any, list: any[]
export const a = format(order.customer.name, order.total)
export const b = (form.email.value).length
export const c = list.map((row) => row.id + offset)
`})
	call := typescript.Of(frontendtest.Where(t, codebase, "CallExpression", "format(order.customer.name, order.total)"))
	if chains := call.Chains(); len(chains) != 2 || !slices.Equal(chains[0], []string{"order", "customer", "name"}) {
		t.Errorf("the call reads the chains %v", chains)
	}
	if call.MemberDepth() != 2 {
		t.Errorf("order.customer.name reaches %d deep", call.MemberDepth())
	}
	through := typescript.Of(frontendtest.Where(t, codebase, "PropertyAccessExpression", "(form.email.value).length"))
	if through.MemberDepth("value", "length") != 1 {
		t.Errorf("value and length add hops: %d", through.MemberDepth("value", "length"))
	}
	mapped := typescript.Of(frontendtest.Where(t, codebase, "CallExpression", "list.map((row) => row.id + offset)"))
	if roots := mapped.Roots(); !slices.Equal(roots, []string{"list", "offset"}) {
		t.Errorf("the roots are %v; an arrow's own parameter is not one", roots)
	}
}

func TestAnEqualityAgainstALiteralNamesItsSubject(t *testing.T) {
	codebase := frontendtest.FromSource(t, map[string]string{"test.ts": `
declare const order: { status: string }
export const paid = order.status === 'paid'
`})
	test := typescript.Of(frontendtest.Where(t, codebase, "BinaryExpression", "order.status === 'paid'"))
	if !test.IsEquality() || !test.Left().IsReference() || !test.Right().IsLiteral() {
		t.Error("an equality of a member against a string literal reads as something else")
	}
	if test.Left().Source() != "order.status" || test.Right().LiteralValue() != "paid" {
		t.Errorf("the subject is %q and the key %q", test.Left().Source(), test.Right().LiteralValue())
	}
}

func TestAFieldKnowsWhetherItIsOptionalAndWhichReadsDefendIt(t *testing.T) {
	codebase := frontendtest.FromSource(t, map[string]string{"printer.ts": `
export class Printer {
    queue?: string[] = []
    lastError: Error | undefined
    status: string = 'idle'
    label(): string | undefined {
        return this.status?.trim() ?? this.lastError?.message
    }
}
`})
	queue := typescript.Of(frontendtest.Named(t, codebase, "PropertyDeclaration", "queue"))
	lastError := typescript.Of(frontendtest.Named(t, codebase, "PropertyDeclaration", "lastError"))
	status := typescript.Of(frontendtest.Named(t, codebase, "PropertyDeclaration", "status"))
	if !queue.IsOptional() || !lastError.IsOptional() || status.IsOptional() {
		t.Error("optionality is not the ? token or a type admitting undefined")
	}
	if !queue.Initializer().Exists() || lastError.Initializer().Exists() {
		t.Error("initialisers are misread")
	}
	read := typescript.Of(frontendtest.Where(t, codebase, "PropertyAccessExpression", "this.status?.trim"))
	if read.OwnFieldRead() != "status" || read.OwnField("status").Name() != "status" {
		t.Errorf("this.status?.trim defends %q", read.OwnFieldRead())
	}
}

func TestABodyIsFingerprintedByItsCodeAndShapedByItsForm(t *testing.T) {
	codebase := frontendtest.FromSource(t, map[string]string{"price.ts": `
export function postal(kilos: number): number {
    let cents = 695 + Math.ceil(kilos) * 120
    if (kilos > 20) {
        cents = Math.round(cents * 1.15)
    }
    return cents
}
export function postalAgain(kilos: number): number {
    let cents = 695 + Math.ceil(kilos) * 120
    if (kilos > 20) {
        cents = Math.round(cents * 1.15)
    }
    return cents
}
export function courier(weight: number): number {
    let price = 950 + Math.ceil(weight) * 85
    if (weight > 30) {
        price = Math.round(price * 1.1)
    }
    return price
}
export function colour(status: string): string {
    if (status === 'paid') {
        return 'green'
    }
    return 'grey'
}
`})
	postal := typescript.Of(frontendtest.Named(t, codebase, "FunctionDeclaration", "postal"))
	again := typescript.Of(frontendtest.Named(t, codebase, "FunctionDeclaration", "postalAgain"))
	courier := typescript.Of(frontendtest.Named(t, codebase, "FunctionDeclaration", "courier"))
	if postal.BodyHash() != again.BodyHash() || postal.BodyHash() == courier.BodyHash() {
		t.Error("the same body does not fingerprint alike, or different numbers do")
	}
	if postal.BodyShape() != courier.BodyShape() {
		t.Error("bodies differing only in names and numbers are not one shape")
	}
	if postal.BodyWeight() < 20 {
		t.Errorf("the pricing body weighs %d", postal.BodyWeight())
	}
	colour := typescript.Of(frontendtest.Named(t, codebase, "FunctionDeclaration", "colour"))
	if !colour.IsLiteralLookup() || postal.IsLiteralLookup() {
		t.Error("a table of literal answers is not told from computed code")
	}
}

func TestAnObjectTypeNamesItsFields(t *testing.T) {
	codebase := frontendtest.FromSource(t, map[string]string{"types.ts": `
export interface Customer { firstName: string; lastName: string; email?: string }
export type Order = { id: string; total: number }
export type Status = 'paid' | 'open'
export const { a, b: [c] } = { a: 1, b: [2] }
`})
	customer := typescript.Of(frontendtest.Named(t, codebase, "InterfaceDeclaration", "Customer"))
	order := typescript.Of(frontendtest.Named(t, codebase, "TypeAliasDeclaration", "Order"))
	status := typescript.Of(frontendtest.Named(t, codebase, "TypeAliasDeclaration", "Status"))
	if !customer.IsObjectType() || !order.IsObjectType() || status.IsObjectType() {
		t.Error("an object type is not told from a union")
	}
	if !slices.Equal(customer.FieldNames(), []string{"firstName", "lastName", "email"}) || len(order.FieldNames()) != 2 {
		t.Errorf("the fields read are %v and %v", customer.FieldNames(), order.FieldNames())
	}
	statement := typescript.Of(frontendtest.Where(t, codebase, "VariableStatement", "export const { a, b: [c] } = { a: 1, b: [2] }"))
	if names := statement.DeclaredNames(); !slices.Equal(names, []string{"a", "c"}) {
		t.Errorf("the destructuring declares %v", names)
	}
}

// TestABodyWeighsWhatThePhpEngineWeighs holds BodyWeight to the PHP engine's own weights for the same bodies: a called
// plain name weighs one more, as it did not, which left worldwatchmarket's context hooks a point under the near-duplicate
// floor.
func TestABodyWeighsWhatThePhpEngineWeighs(t *testing.T) {
	for body, php := range map[string]int{"const c = f(C)": 6, "const c = x.f(C)": 6, "const c = f(C)(D)": 8, "const c = new F(C)": 6, "if (c === null) {\n    y()\n  }": 9,
		"y(A)": 5, "y()": 4, "const c = f()": 5, "const c = f(A, B)": 7, "const c = `${x}: y ${z}`": 3, "const c = v as T": 3} {
		codebase := frontendtest.FromSource(t, map[string]string{"weight.ts": "export function a () {\n  " + body + "\n}\n"})
		if weight := typescript.Of(frontendtest.Named(t, codebase, "FunctionDeclaration", "a")).BodyWeight(); weight != php {
			t.Errorf("%q weighs %d, the PHP engine %d", body, weight, php)
		}
	}
}

// TestACastReadsAsTheValueCast holds two bodies that differ only in the type a value is cast to to one shape, as the PHP
// engine reads them: worldwatchmarket's requireRatioAttr and requireSideAttr are one function written twice.
func TestACastReadsAsTheValueCast(t *testing.T) {
	codebase := frontendtest.FromSource(t, map[string]string{"casts.ts": "export function a () {\n  return v as Ratio\n}\n\nexport function b () {\n  return v as Side\n}\n"})
	if typescript.Of(frontendtest.Named(t, codebase, "FunctionDeclaration", "a")).BodyShape() != typescript.Of(frontendtest.Named(t, codebase, "FunctionDeclaration", "b")).BodyShape() {
		t.Error("two casts to different types read as different code")
	}
}

// TestUndefinedReadsAsTheConstantItIs holds a body answering undefined apart from one answering a name, as the PHP
// engine holds them: worldwatchmarket's two lookup helpers differ only there.
func TestUndefinedReadsAsTheConstantItIs(t *testing.T) {
	codebase := frontendtest.FromSource(t, map[string]string{"answers.ts": "export function a (key: string) {\n  return key.length ? key : undefined\n}\n\nexport function b (key: string) {\n  return key.length ? key : key\n}\n"})
	if typescript.Of(frontendtest.Named(t, codebase, "FunctionDeclaration", "a")).BodyShape() == typescript.Of(frontendtest.Named(t, codebase, "FunctionDeclaration", "b")).BodyShape() {
		t.Error("undefined reads as one more name")
	}
}

// TestAnOptionalChainIsNoCopyOfAPlainOne holds a body reading a?.b apart from one reading a.b, as the PHP engine
// holds them: smart-farmers-pos's two stock counters differ only there.
func TestAnOptionalChainIsNoCopyOfAPlainOne(t *testing.T) {
	codebase := frontendtest.FromSource(t, map[string]string{"chains.ts": "export function a (v: { s?: number[] }) {\n  return v?.s\n}\n\nexport function b (v: { s?: number[] }) {\n  return v.s\n}\n"})
	if typescript.Of(frontendtest.Named(t, codebase, "FunctionDeclaration", "a")).BodyShape() == typescript.Of(frontendtest.Named(t, codebase, "FunctionDeclaration", "b")).BodyShape() {
		t.Error("an optional chain reads as a plain one")
	}
}
