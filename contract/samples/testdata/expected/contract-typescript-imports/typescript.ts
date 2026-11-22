// @contract fact/import/form = "module"
// @contract role/import/alias -> namespacealias
// @label namespacealias binding
import * as helpers from './helpers'
// @contract fact/import/form = "names"
// @contract fact/import/typeOnly = true
// @contract fact/import/source = "./item"
// @contract kind/import-binding
// @contract role/import/bindings -> itemimport
// @label itemimport import-binding
import type { Item } from './item'

// @contract fact/function/form = "arrow"
// @contract role/function/parameters -> iparam
// @contract role/function/returnType -> iret
// @contract role/function/body -> icall
// @contract kind/call
// @contract role/call/callee -> iacc
// @contract role/call/arguments -> iarg
// @contract kind/argument
// @contract role/argument/value -> iref
// @contract kind/access
// @contract kind/reference at 44
// @contract fact/reference/form at 44 = "name"
// @contract fact/*/name on reference at 44 = "helpers"
// @label iparam parameter
// @label iret type-expression 34
// @label icall call
// @label iacc access
// @label iarg argument
// @label iref reference 57
export const run = (item: Item): string => helpers.name(item)
