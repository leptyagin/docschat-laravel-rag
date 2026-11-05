<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Admin\ManagedBot;
use App\Services\Admin\UnansweredQuestions;
use Illuminate\Contracts\View\View;

final class UnansweredController extends Controller
{
    public function __invoke(ManagedBot $managedBot, UnansweredQuestions $unanswered): View
    {
        return view('admin.unanswered', ['groups' => $unanswered->grouped($managedBot->get())]);
    }
}
