<?php

namespace Shop\Services;

use JesseGall\CodeCommandments\Sins\Backend\Laravel\ConfigRead;
use JesseGall\CodeCommandments\Sins\Backend\Laravel\FacadeCall;

use Illuminate\Contracts\Mail\Mailer;
use Illuminate\Support\Facades\Mail;
use JesseGall\CodeCommandments\Testing\Righteous;
use JesseGall\CodeCommandments\Testing\Sinful;

final class NotificationService
{
    public function __construct(private readonly Mailer $mailer) {}

    #[Sinful(ConfigRead::class)]
    #[Sinful(FacadeCall::class)]
    public function notify(string $email, string $type): void
    {
        $template = config('shop.templates.' . $type);

        Mail::raw($template, function ($message) use ($email) {
            $message->to($email);
        });
    }

    #[Righteous(FacadeCall::class)]
    public function notifyClean(string $email, string $template): void
    {
        $this->mailer->raw($template, function ($message) use ($email) {
            $message->to($email);
        });
    }

    /**
     * `::fake()` installs a test double by swapping the container binding — there is no
     * injectable contract form of it, so it is NOT a facade-reach sin (no twin marker:
     * the canonical fix above is injection, not faking).
     */
    public function sandbox(): void
    {
        Mail::fake();
    }
}
