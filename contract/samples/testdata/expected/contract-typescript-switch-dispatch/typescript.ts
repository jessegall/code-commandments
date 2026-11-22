export function describe(code: number, flag: boolean): string {
    // @contract kind/dispatch
    // @contract fact/dispatch/form = "switch"
    // @contract role/dispatch/subject -> codevalue
    // @contract role/dispatch/arms -> [arm0, arm1, arm2, armdefault]
    // @label codevalue reference
    switch (code) {
        // @contract kind/arm
        // @contract fact/arm/fallsThrough = true
        // @contract kind/pattern
        // @contract role/arm/patterns -> pat0
        // @label arm0 arm
        // @label pat0 pattern
        case 0:
        // @contract fact/pattern/form = "value"
        // @contract role/pattern/value -> pat1lit
        // @label arm1 arm
        // @label pat1lit literal
        case 1:
            return 'low'
        // @contract role/arm/body -> arm2body
        // @label arm2 arm
        case 2:
            // @contract fact/assignment/mode = "assign"
            // @contract role/assignment/targets -> flagwrite
            // @contract role/assignment/values -> notflag
            // @contract kind/unary
            // @contract fact/unary/operator = "not"
            // @contract role/unary/operand -> flagread
            // @label arm2body block
            // @label flagwrite reference 13
            // @label notflag unary
            // @label flagread reference 21
            flag = !flag
            break
        // @contract fact/arm/default = true
        // @label armdefault arm
        default:
            return 'high'
    }

    // @contract kind/conditional
    // @contract role/conditional/condition -> condflag
    // @contract role/conditional/consequent -> condyes
    // @contract role/conditional/alternative -> condno
    // @label condflag reference
    // @label condyes literal 19
    // @label condno literal 31
    return flag ? 'flagged' : 'plain'
}
