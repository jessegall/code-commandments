# A voucher checker whose docstring repeats its parameters in NumPy style. Beside it, one whose docstring
# gives a type the signature does not, which is information.


# @sin CeremonyDocblock
def is_valid(code: str, used: set[str]) -> bool:
    """
    Parameters
    ----------
    code : str
    used : set[str]
    """
    return code not in used


# @righteous CeremonyDocblock
def normalise(code):
    """
    Args:
        code (str):
    """
    return code.strip().upper()
