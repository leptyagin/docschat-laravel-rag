<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Admin\DashboardQuery;
use App\Services\Admin\ManagedBot;
use App\Services\Admin\UnansweredQuestions;
use Illuminate\Contracts\View\View;

final class DashboardController extends Controller
{
    private const int TOP_UNANSWERED = 5;

    public function __invoke(ManagedBot $managedBot, DashboardQuery $dashboard, UnansweredQuestions $unanswered): View
    {
        $bot = $managedBot->get();

        return view('admin.dashboard', [
            'stats' => $dashboard->stats($bot),
            'unanswered' => $unanswered->grouped($bot, self::TOP_UNANSWERED),
        ]);
    }
}
