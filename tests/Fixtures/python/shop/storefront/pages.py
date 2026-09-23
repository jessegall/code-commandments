# A storefront page that dodged the circular-import error by moving its import into the function body — the
# arrow is still there.


def results_page(query: str) -> str:
    # @sin NamespaceCycle
    from shop.search import engine
    return engine.render(query)
