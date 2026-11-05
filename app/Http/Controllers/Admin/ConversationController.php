<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\MessageRole;
use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\Message;
use App\Services\Admin\ManagedBot;
use Illuminate\Contracts\View\View;

final class ConversationController extends Controller
{
    public function index(ManagedBot $managedBot): View
    {
        return view('admin.conversations.index', [
            'conversations' => $managedBot->get()->conversations()
                ->withCount('messages')
                ->withSum('messages', 'cost_usd')
                ->addSelect(['first_question' => Message::query()
                    ->select('content')
                    ->whereColumn('conversation_id', 'conversations.id')
                    ->where('role', MessageRole::User)
                    ->orderBy('id')
                    ->limit(1)])
                ->latest()
                ->orderByDesc('id')
                ->paginate(20),
        ]);
    }

    public function show(Conversation $managedConversation): View
    {
        return view('admin.conversations.show', [
            'conversation' => $managedConversation,
            'messages' => $managedConversation->messages()->orderBy('id')->get(),
        ]);
    }
}
