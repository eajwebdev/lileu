<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Message;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MessageController extends Controller
{
    public function index(Request $request): Response
    {
        $reseller = $request->user()->reseller;

        // Opening the thread clears the reseller's unread badge.
        Message::where('reseller_id', $reseller->id)
            ->where('author_role', 'admin')
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return Inertia::render('Portal/Messages', [
            'messages' => $reseller->messages()
                ->with('order:id,order_number')
                ->oldest()
                ->get()
                ->map(fn (Message $m) => [
                    'id' => $m->id,
                    'body' => $m->body,
                    'author_role' => $m->author_role,
                    'order_number' => $m->order?->order_number,
                    'sent_at' => $m->created_at->format('M j, Y g:i A'),
                ]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'body' => ['required', 'string', 'max:2000'],
            'reseller_order_id' => ['nullable', 'integer', 'exists:reseller_orders,id'],
        ]);

        $reseller = $request->user()->reseller;

        Message::create([
            'reseller_id' => $reseller->id,
            'reseller_order_id' => $data['reseller_order_id'] ?? null,
            'user_id' => $request->user()->id,
            'author_role' => 'reseller',
            'body' => $data['body'],
        ]);

        return back()->with('success', 'Message sent.');
    }
}
