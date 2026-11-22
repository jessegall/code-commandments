# @contract kind/import-binding at 16
# @contract role/import/bindings -> [b_path, b_sep]
# @contract role/import-binding/alias at 16 -> loc
# @contract fact/import/form = "names"
# @contract fact/import/source = "os"
# @label imp_os import
# @label b_path import-binding 16
# @label b_sep import-binding 34
# @label loc binding 24
from os import path as location, sep
from . import sibling # @label imp_dot import
# @contract fact/import/relativeLevel = 1
from .pkg import thing # @label imp_pkg import

# @contract kind/expression-statement
# @contract role/expression-statement/expression -> counter_assign
# @contract kind/reference
# @contract kind/literal
# @contract fact/literal/literalKind = "integer"
# @label counter_stmt expression-statement
# @label counter_assign assignment
counter = 0


# @contract kind/type
class Counter: # @label counter_type type
    # @contract kind/field
    # @contract role/field/declarators -> fdecl
    # @contract role/field/declaredType -> f_type
    # @contract kind/declarator
    # @contract role/declarator/binding -> limit_b
    # @contract role/declarator/initializer -> limit_v
    # @contract kind/binding
    # @contract kind/type-expression
    # @label fdecl declarator
    # @label limit_b binding
    # @label limit_v literal
    # @label f_type type-expression
    limit: int = 3
    step = 1


def outer(): # @label outer_fn function
    total = 0

    def add():
        # @contract kind/scope-declaration
        # @contract fact/scope-declaration/form = "nonlocal"
        # @contract role/scope-declaration/bindings -> nl_b
        # @label nl_b binding
        nonlocal total
        # @contract fact/assignment/mode = "compound"
        # @contract fact/assignment/operator = "add"
        total += 1

    # @contract fact/scope-declaration/form = "global"
    global counter
    # @contract kind/resource-scope
    # @contract fact/resource-scope/form = "with"
    # @contract role/resource-scope/resources -> [res_a, res_b]
    # @contract role/resource-scope/body -> with_body
    # @contract kind/declarator
    # @label res_a declarator 10
    # @label res_b call 31
    with open("a") as handle, open("b"):
        # @contract kind/no-op
        pass # @label with_body block
    # @contract role/loop/bindings -> item_b
    # @contract role/loop/iterable -> range_call
    # @contract role/loop/body -> for_body
    # @contract role/loop/normalCompletion -> for_else
    # @label item_b binding 9
    # @label range_call construction 17
    # @contract fact/*/target on construction = {"symbol": "builtins.range.__new__", "type": "builtins.range", "name": "__new__"}
    for item in range(3):
        # @contract kind/break
        break # @label for_body block
    else:
        total = 2 # @label for_else block
    # @contract role/loop/condition -> while_cond
    # @contract role/loop/body -> while_body
    # @label while_cond reference 11
    while total:
        total -= 1 # @label while_body block
    # @contract kind/try
    # @contract role/try/body -> try_body
    # @contract role/try/handlers -> exc_h
    # @contract role/try/normalCompletion -> try_else
    # @contract role/try/finally -> try_fin
    try:
        pass # @label try_body block
    # @contract fact/handler/catchForm = "typed"
    # @contract role/handler/caughtTypes -> exc_t
    # @contract role/handler/body -> exc_body
    # @label exc_h handler
    # @label exc_t type-expression 12
    except ValueError:
        pass # @label exc_body block
    else:
        pass # @label try_else block
    finally:
        pass # @label try_fin block


# @contract fact/function/async = true
async def guarded(lock): # @label guarded_fn function
    # @contract fact/resource-scope/async = true
    async with lock:
        pass


def doubled(amount: int) -> int:
    # @contract role/assignment/targets -> [twice_b]
    # @label twice_b binding 5
    twice = amount * 2
    # @contract link/reference/binding at 12 -> twice_b
    return twice
