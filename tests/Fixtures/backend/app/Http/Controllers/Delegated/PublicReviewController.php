<?php

namespace Shop\Http\Controllers\Delegated;

use JesseGall\CodeCommandments\Sins\Backend\Laravel\RouteDelegatesToController;
use JesseGall\CodeCommandments\Testing\Sinful;

/**
 * The public review submission wraps the internal ReviewController's publish action. Two routes, one
 * operation — collapse to a single action, or a shared publisher service.
 */
final class PublicReviewController
{
    public function __construct(
        private readonly ReviewController $reviews,
    ) {}

    public function summary(string $title, int $stars): string
    {
        return sprintf('%s rated %d/5', $title, $stars);
    }

    #[Sinful(RouteDelegatesToController::class)]
    public function publish(int $reviewId): string
    {
        return $this->reviews->publish($reviewId);
    }
}
