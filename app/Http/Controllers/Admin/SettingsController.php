<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Actions\Admin\UpdateBotSettings;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateSettingsRequest;
use App\Services\Admin\ManagedBot;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

final class SettingsController extends Controller
{
    public function edit(ManagedBot $managedBot): View
    {
        return view('admin.settings', ['bot' => $managedBot->get()]);
    }

    public function update(UpdateSettingsRequest $request, ManagedBot $managedBot, UpdateBotSettings $updateSettings): RedirectResponse
    {
        $updateSettings->handle(
            $managedBot->get(),
            $request->string('name')->toString(),
            $request->string('greeting')->toString(),
            $request->string('color')->toString(),
            $request->string('fallback_contact')->toString(),
            $request->suggestedQuestions(),
            $request->embedOrigins(),
            $request->boolean('documents_public'),
        );

        return back()->with('status', 'Settings saved.');
    }
}
