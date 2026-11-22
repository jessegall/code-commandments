<?php

namespace Shop\Http\Controllers;

use Illuminate\Routing\Controller;
use Inertia\Inertia;
use Inertia\Response;
use Shop\Http\Pages\CourierBoardPage;

/**
 * Shows the courier board, building its props into a local before rendering them.
 */
final class CourierBoardController extends Controller
{
    public function show(): Response
    {
        $props = CourierBoardPage::from([])->toArray();

        return Inertia::render('courier/Board', $props);
    }
}
