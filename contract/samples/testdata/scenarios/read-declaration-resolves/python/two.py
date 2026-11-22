from one import Kind, Ticket


def kind() -> Kind:
    return Kind.Food  # @contract promise/read-declaration-resolves -> one.py#food


def counted(ticket: Ticket) -> str:
    return ticket.counter  # @contract promise/read-declaration-resolves -> one.py#counter
