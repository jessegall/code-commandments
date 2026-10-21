# A shelf plan imported from the planner's file. The last clause does nothing but raise again, so nobody
# needed it. The pair below it is the one shape that earns a clause of only `raise`: the narrow one keeps
# the broad one under it from ever seeing an interrupt, which is the whole reason it is written.


def import_plan(planner, path: str) -> int:
    try:
        return planner.read(path)
    # @sin RethrownUnchanged
    except ValueError:
        raise


# @righteous RethrownUnchanged
def import_plan_quietly(planner, path: str) -> int:
    try:
        return planner.read(path)
    except KeyboardInterrupt:
        raise
    except Exception:
        planner.report(path)

        return 0
