using System;
using System.Threading.Tasks;

namespace Shop;

public sealed class Counter
{
    public const int Limit = 3;

    private int amount;

    // @label twiceFn function
    public static int Twice(int value, int times = 2) => value * times;

    // @contract fact/parameter/collects = "rest"
    public static int Sum(params int[] values) => values.Length;

    // @contract role/type-parameter/constraint -> disposable
    // @label disposable type-expression 48
    public static T First<T>(T item) where T : IDisposable => item;

    public int Next(int start, string? name)
    {
        // @label sumBinding binding
        // @label startRef reference 32
        // @contract fact/*/target on call -> twiceFn
        // @contract fact/*/refers on reference at 19 -> twiceFn
        // @contract fact/*/constantValue on reference at 46 = 3
        // @contract kind/argument at 25
        // @contract fact/argument/label at 25 = "value"
        // @contract fact/argument/passing at 25 = "value"
        // @contract role/argument/value at 25 -> startRef
        var sum = Twice(value: start, times: Limit);

        // @contract fact/assignment/mode = "compound"
        // @contract fact/assignment/operator = "add"
        sum += 1;

        // @contract fact/access/nullConditional = true
        var length = name?.Length;

        // @label leftCmp comparison 20
        // @label rightCmp comparison 31
        // @contract kind/logical
        // @contract fact/logical/operator = "and"
        // @contract role/logical/left -> leftCmp
        // @contract role/logical/right -> rightCmp
        var both = sum > 0 && sum < 9;

        // @contract kind/reference at 30
        // @contract fact/reference/form at 16 = "self"
        // @contract link/reference/binding at 30 -> sumBinding
        return this.amount + sum;
    }

    public async Task<int> Wait(int count)
    {
        // @label delayCall call
        // @contract kind/await
        // @contract role/await/operand -> delayCall
        await Task.Delay(count);

        if (count < 0)
        {
            // @label boom construction
            // @label messageArg argument
            // @contract kind/throw
            // @contract role/throw/value -> boom
            // @contract role/construction/arguments -> [messageArg]
            throw new ArgumentException("negative");
        }

        // @label forInit variable
        // @label forCond comparison
        // @label forStep assignment
        // @contract role/loop/initializer -> [forInit]
        // @contract role/loop/condition -> forCond
        // @contract role/loop/steps -> [forStep]
        // @contract role/loop/body -> forBody
        for (var i = 0; i < count; i++)
        // @label forBody block
        {
            if (i == 1)
            {
                // @contract kind/continue
                continue;
            }

            // @contract kind/break
            break;
        }

        // @contract role/label/statement -> bumpStmt
        again:
        // @label bumpStmt expression-statement
        count++;

        return count;
    }
}
