# The stocktake count sheet, mailed to the desk that asked for it. The first clause names the failure and
# hands it back untouched; the one below it earns its clause by telling the desk the sheet was held before
# letting the failure travel.


class CountSheet:
    def __init__(self, mailer) -> None:
        self.mailer = mailer

    def post(self, desk: str, sheet: str) -> None:
        try:
            self.mailer.send(desk, sheet)
        # @sin RethrownUnchanged
        except OSError as error:
            raise error

    # @righteous RethrownUnchanged
    def post_counted(self, desk: str, sheet: str) -> None:
        try:
            self.mailer.send(desk, sheet)
        except OSError as error:
            self.mailer.held(desk, sheet)

            raise error
