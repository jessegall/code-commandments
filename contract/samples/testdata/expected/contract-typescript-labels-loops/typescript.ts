export function firstMatch(rows: number[][], wanted: number): number {
    let found = -1

    // @contract kind/label
    // @contract role/label/binding -> searchbind
    // @contract role/label/statement -> outerloop
    // @contract kind/binding at 5
    // @contract fact/*/name on binding at 5 = "search"
    // @contract role/loop/iterable -> rowsref
    // @contract role/loop/bindings -> rowbind
    // @contract role/loop/body -> outerbody
    // @label searchbind binding 5
    // @label outerloop loop
    // @label rowsref reference
    // @label rowbind binding 24
    // @label outerbody block
    search: for (const row of rows) {
        // @label cellb binding
        for (const cell of row) {
            // @contract role/if/then -> thenblock
            // @contract kind/comparison
            // @contract fact/comparison/operator = "identical"
            // @contract fact/comparison/operators = ["identical"]
            // @contract role/comparison/operands -> [cmpa, cmpb]
            // @contract link/comparison/left -> cmpa
            // @contract link/comparison/right -> cmpb
            // @label thenblock block
            // @label cmpa reference 17
            // @label cmpb reference 26
            if (cell === wanted) {
                // @contract fact/assignment/mode = "assign"
                // @contract role/assignment/targets -> foundwrite
                // @contract role/assignment/values -> cellread
                // @contract link/reference/binding at 25 -> cellb
                // @label foundwrite reference 17
                // @label cellread reference 25
                found = cell
                // @contract kind/break
                // @contract role/break/label -> breaklabel
                // @label breaklabel reference
                break search
            } else if (cell < 0) {
                // @contract kind/continue
                // @contract role/continue/label -> continuelabel
                // @label continuelabel reference
                continue search
            }
        }
    }

    // @contract role/loop/initializer -> initvar
    // @contract role/loop/condition -> loopcond
    // @contract role/loop/steps -> [stepup, stepdown]
    // @contract role/loop/body -> forbody
    // @contract kind/variable
    // @contract role/variable/declarators -> [lowdecl, highdecl]
    // @contract fact/assignment/mode at 46 = "increment"
    // @contract fact/assignment/prefix at 46 = true
    // @label initvar variable
    // @label loopcond comparison
    // @label stepup assignment 46
    // @label stepdown assignment 53
    // @label forbody block
    // @label lowdecl declarator 14
    // @label highdecl declarator 23
    for (let low = 0, high = 10; low < high; ++low, --high) {
        found += low
    }

    // @contract kind/no-op
    // @contract role/loop/body -> whilenoop
    // @label whilenoop no-op
    while (found < 0) ;

    return found
}
