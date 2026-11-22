def convert(value, failure):
    try:
        return int(value)
    # @contract fact/handler/catchForm = "typed"
    # @contract role/handler/caughtTypes -> value_err
    # @contract role/handler/binding -> err_b
    # @contract role/handler/body -> h_body
    # @label value_err type-expression 12
    # @label err_b binding 26
    except ValueError as error:
        # @label h_body block
        # @contract kind/throw
        # @contract role/throw/value -> fail_ref
        # @contract role/throw/cause -> cause_ref
        # @label fail_ref reference 15
        # @label cause_ref reference 28
        raise failure from error
    # @contract fact/handler/catchForm = "catch-all"
    except:
        raise


def gather(tasks):
    try:
        run(tasks)
    # @contract fact/handler/group = true
    except* OSError as group:
        return group
