# A till receipt framed in a box: a fixed top and bottom around lines the till computes.


def banner(lines: list[str], width: int) -> str:
    rule = "=" * width
    # @sin AssembledTemplate
    return '\n'.join([f'+{rule}+', *(f'|{line.center(width)}|' for line in lines), f'+{rule}+', 'THANK YOU'])
