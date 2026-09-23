# Running every checkout plugin in turn — and quietly skipping any that fails, so a broken plugin
# looks exactly like one that had nothing to do.


def run_plugins(plugins, order) -> None:
    for plugin in plugins:
        try:
            plugin.on_checkout(order)
        # @sin SwallowedException
        except Exception:
            continue
