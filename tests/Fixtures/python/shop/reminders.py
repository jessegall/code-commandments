# Payment reminders go out while the queue has overdue customers — each pass buried under one test.


def send_reminders(queue, mailer) -> None:
    while queue:
        # @sin LoopInvertedGuard
        if queue.peek().overdue:
            customer = queue.pop()
            mailer.remind(customer.email, customer.balance)
            customer.reminded()
