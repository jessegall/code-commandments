using System;
using System.Collections.Generic;
using System.IO;
using System.Threading.Tasks;

namespace Shop;

public static class Flow
{
    // @label pItems parameter 44
    // @label pGate parameter 58
    // @label pName parameter 71
    // @label numbersReturn type-expression 19
    // @label intArg type-expression 31
    // @contract fact/function/generator = true
    // @contract role/function/parameters -> [pItems, pGate, pName]
    // @contract role/function/returnType -> numbersReturn
    // @contract role/function/body -> numbersBody
    // @contract role/type-expression/typeArguments at 19 -> [intArg]
    public static IEnumerable<int> Numbers(int[]? items, object gate, string? name)
    // @label numbersBody block
    {
        // @label firstDecl declarator
        // @label itemsIndex index
        // @label zeroRepl literal 34
        // @label itemsRef reference
        // @label keyZero literal 28
        // @contract kind/variable
        // @contract fact/variable/form = "var"
        // @contract role/variable/declarators -> [firstDecl]
        // @contract kind/fallback
        // @contract fact/fallback/trigger = "null"
        // @contract role/fallback/subject -> itemsIndex
        // @contract role/fallback/replacement -> zeroRepl
        // @contract kind/index
        // @contract role/index/subject -> itemsRef
        // @contract role/index/key -> keyZero
        // @contract fact/index/nullConditional = true
        var first = items?[0] ?? 0;
        // @label totalType type-expression 9
        // @label firstOperand reference
        // @label castType type-expression 22
        // @label castNode cast
        // @label oneLit literal
        // @contract role/variable/declaredType -> totalType
        // @contract kind/cast
        // @contract fact/cast/form = "checked"
        // @contract role/cast/operand -> firstOperand
        // @contract role/cast/targetType -> castType
        // @contract kind/binary
        // @contract fact/binary/operator = "add"
        // @contract role/binary/left -> castNode
        // @contract role/binary/right -> oneLit
        // @contract fact/*/resolvedType on reference = {"kind":"keyword","name":"int"}
        int total = (int)first + 1;
        // @label itemText text
        // @label totalRef reference 47
        // @contract fact/cast/form = "safe"
        // @contract kind/interpolation
        // @contract role/interpolation/parts -> [itemText, totalRef]
        // @contract kind/text
        // @contract fact/text/value = "item "
        // @contract fact/*/resolvedType on reference at 21 = {"kind":"keyword","name":"string","nullable":true}
        var label = name as string ?? $"item {total}";
        // @label posCmp comparison
        // @label negUnary unary 33
        // @label plusUnary unary 42
        // @label negOperand reference 34
        // @contract kind/conditional
        // @contract role/conditional/condition -> posCmp
        // @contract role/conditional/consequent -> negUnary
        // @contract role/conditional/alternative -> plusUnary
        // @contract kind/comparison
        // @contract kind/unary at 33
        // @contract fact/unary/operator at 33 = "negate"
        // @contract fact/unary/operator at 42 = "plus"
        // @contract role/unary/operand at 33 -> negOperand
        var shown = total > 0 ? -total : +total;
        // @contract fact/unary/operator = "not"
        var no = !(total > 0);
        // @contract fact/unary/operator = "assert-present"
        // @contract fact/access/form = "instance"
        // @contract fact/*/resolvedType on reference at 22 = {"kind":"keyword","name":"string","nullable":true}
        var length = name!.Length;
        // @contract fact/assignment/mode = "increment"
        // @contract fact/assignment/prefix = true
        ++total;

        // @label gateRes reference
        // @contract kind/resource-scope
        // @contract fact/resource-scope/form = "lock"
        // @contract role/resource-scope/resources -> [gateRes]
        // @contract role/resource-scope/body -> lockBody
        lock (gate)
        // @label lockBody block
        // @contract kind/block
        // @contract role/block/statements -> [assignStmt]
        {
            // @label assignStmt expression-statement
            // @label assignExpr assignment
            // @label assignTarget reference 13
            // @label bitNot unary
            // @contract role/expression-statement/expression -> assignExpr
            // @contract role/assignment/targets -> [assignTarget]
            // @contract role/assignment/values -> [bitNot]
            // @contract fact/assignment/mode = "assign"
            // @contract fact/unary/operator = "bit-not"
            total = ~total;
        }

        // @label gateRef25 reference
        // @label stringT type-expression
        // @label textBinding binding
        // @label thenBlock25 block
        // @contract kind/type-test
        // @contract fact/type-test/form = "is"
        // @contract role/type-test/subject -> gateRef25
        // @contract role/type-test/testedType -> stringT
        // @contract role/type-test/binding -> textBinding
        // @contract role/if/then -> thenBlock25
        if (gate is string text) { }
        // @contract fact/type-test/negated = true
        if (gate is not Stream) { }
        if (gate is IDisposable) { }

        int parsed;
        // @contract fact/argument/passing at 29 = "out"
        int.TryParse(label, out parsed);
        // @contract fact/argument/passing at 14 = "reference"
        // @contract fact/argument/passing at 25 = "in"
        Swap(ref total, in shown);

        // @contract fact/resource-scope/form = "using"
        using (var reader = new StringReader(label))
        {
            // @contract kind/no-op
            ;
        }

        // @label hashCall call
        // @contract kind/yield
        // @contract role/yield/value -> hashCall
        // @contract fact/unary/operator = "typeof"
        yield return typeof(int).GetHashCode();
        // @label doneRef reference
        // @contract kind/goto
        // @contract role/goto/destination -> doneRef
        // @contract link/goto/label -> doneLabel
        goto done;
    // @label doneLabel label
    // @label doneBinding binding
    // @contract kind/label
    // @contract role/label/binding -> doneBinding
    done:
        yield break;
    }

    // @contract fact/function/async = true
    public static async Task Drain(IAsyncEnumerable<int> source)
    {
        // @label streamDecl declarator
        // @contract fact/resource-scope/async = true
        // @contract role/resource-scope/resources -> [streamDecl]
        await using (var stream = new MemoryStream())
        {
        }

        // @label sourceRef reference
        // @label numberBinding binding
        // @contract fact/loop/async = true
        // @contract role/loop/iterable -> sourceRef
        // @contract role/loop/bindings -> [numberBinding]
        // @contract role/loop/body -> eachBody
        await foreach (var number in source)
        // @label eachBody block
        {
        }
    }

    // @contract fact/parameter/passing at 30 = "reference"
    // @contract fact/parameter/passing at 41 = "in"
    private static void Swap(ref int a, in int b) { }
}
