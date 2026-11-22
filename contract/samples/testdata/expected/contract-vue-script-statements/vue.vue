<script lang="ts">
interface Source {
    name: string
    read(): Promise<string>
    close(): void
}

export function describe(code: number, flag: boolean): string {
    // @contract kind/dispatch
    // @label codeSwitch dispatch
    // @contract fact/dispatch/form = "switch"
    // @contract role/dispatch/arms -> [zeroArm, oneArm, twoArm, defaultArm]
    switch (code) {
        // @label zeroArm arm
        // @contract kind/arm
        // @contract fact/arm/fallsThrough = true
        case 0:
        // @label oneArm arm
        case 1:
            return 'low'
        // @label twoArm arm
        // @contract role/arm/body -> twoBody
        case 2:
            // @label twoBody block
            // @contract role/block/statements -> [flagStmt, caseBreak]
            // @label flagStmt expression-statement
            // @contract kind/assignment
            // @contract fact/assignment/mode = "assign"
            // @contract role/assignment/targets -> [flagTarget]
            // @label flagTarget reference 13
            // @contract role/assignment/values -> [negation]
            // @label negation unary 20
            // @contract kind/unary
            // @contract fact/unary/operator = "not"
            // @contract role/unary/operand -> flagOperand
            // @label flagOperand reference 21
            flag = !flag
            // @label caseBreak break
            // @contract kind/break
            // @contract link/break/destination -> codeSwitch
            break
        // @label defaultArm arm
        // @contract fact/arm/default = true
        default:
            return 'high'
    }

    // @contract kind/conditional
    // @contract role/conditional/condition -> flagCondition
    // @label flagCondition reference
    // @contract role/conditional/consequent -> flaggedLit
    // @label flaggedLit literal 19
    // @contract role/conditional/alternative -> plainLit
    // @label plainLit literal 31
    return flag ? 'flagged' : 'plain'
}

export function firstMatch(rows: number[][], wanted: number): number {
    // @contract fact/variable/form = "let"
    // @contract fact/unary/operator = "negate"
    let found = -1
    let total = 0

    // @contract kind/label
    // @label searchLabel label
    // @contract role/label/binding -> searchBinding
    // @label searchBinding binding 5
    // @contract role/label/statement -> rowLoop
    // @label rowLoop loop 13
    // @contract kind/loop at 13
    // @contract fact/loop/form at 13 = "for-each"
    // @contract role/loop/bindings at 13 -> [rowBinding]
    // @label rowBinding binding 24
    // @contract role/loop/iterable at 13 -> rowsIterable
    // @label rowsIterable reference 31
    // @contract role/loop/body at 13 -> rowBody
    // @label rowBody block 37
    search: for (const row of rows) {
        for (const cell of row) {
            // @contract kind/if
            // @contract role/if/condition -> cellCompare
            // @label cellCompare comparison
            // @contract role/if/then -> thenBlock
            // @label thenBlock block 34
            // @contract role/if/else -> elseIf
            // @contract kind/comparison
            // @contract fact/comparison/operator = "identical"
            // @contract fact/comparison/operators = ["identical"]
            // @contract role/comparison/operands -> [cellLeft, wantedRight]
            // @label cellLeft reference 17
            // @label wantedRight reference 26
            // @contract link/comparison/left -> cellLeft
            // @contract link/comparison/right -> wantedRight
            if (cell === wanted) {
                found = cell
                // @contract role/break/label -> breakLabel
                // @label breakLabel reference
                break search
            // @label elseIf if
            } else if (cell < 0) {
                // @contract kind/continue
                // @contract role/continue/label -> continueLabel
                // @label continueLabel reference
                continue search
            }
        }
    }

    // @contract kind/loop
    // @contract fact/loop/form = "for"
    // @contract role/loop/initializer -> [lowVars]
    // @label lowVars variable 10
    // @contract kind/variable
    // @contract fact/variable/form = "let"
    // @contract role/variable/declarators -> [lowDecl, highDecl]
    // @label lowDecl declarator 14
    // @label highDecl declarator 23
    // @contract role/loop/condition -> lowHigh
    // @label lowHigh comparison 34
    // @contract role/loop/steps -> [lowStep, highStep]
    // @label lowStep assignment 46
    // @label highStep assignment 53
    // @contract fact/assignment/mode at 46 = "increment"
    // @contract fact/assignment/prefix at 46 = true
    // @contract fact/assignment/mode at 53 = "decrement"
    for (let low = 0, high = 10; low < high; ++low, --high) {
        // @contract fact/assignment/mode = "compound"
        // @contract fact/assignment/operator = "add"
        found += low
        total = total + low * high
    }

    // @contract kind/loop
    // @contract fact/loop/form = "do-while"
    // @contract role/loop/condition -> doCondition
    do {
        total -= 1
    // @label doCondition comparison
    } while (total > 0)

    // @contract kind/loop
    // @contract fact/loop/form = "while"
    // @contract role/loop/body -> emptyBody
    // @label emptyBody no-op
    // @contract kind/no-op
    while (found < 0) ;

    return found + total
}

// @contract fact/function/async = true
export async function load(source: Source): Promise<string> {
    // @contract kind/try
    // @contract role/try/body -> tryBody
    // @label tryBody block
    // @contract role/try/handlers -> [catchHandler]
    // @contract role/try/finally -> finallyBody
    try {
        // @contract kind/await
        // @contract role/await/operand -> readCall
        // @label readCall call
        return await source.read()
    // @label catchHandler handler
    // @contract kind/handler
    // @contract fact/handler/catchForm = "catch-all"
    // @contract role/handler/binding -> errorBinding
    // @label errorBinding binding
    // @contract role/handler/body -> catchBody
    // @label catchBody block
    } catch (error) {
        // @contract kind/throw
        // @contract role/throw/value -> errorNew
        // @label errorNew construction
        throw new Error(`cannot read ${source.name}: ${error}`)
    // @label finallyBody block
    } finally {
        source.close()
    }
}
</script>
