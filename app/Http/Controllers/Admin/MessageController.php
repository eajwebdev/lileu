<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Message;
use App\Models\Reseller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MessageController extends Controller
{
    public function index(Request $request): Response
    {
        $threads = Reseller::query()
            ->has('messages')
            ->with(['messages' => fn ($q) => $q->latest()->limit(1)])
            ->withCount(['messages as unread_count' => fn ($q) => $q
                ->where('author_role', 'reseller')->whereNull('read_at')])
            ->get()
            ->sortByDesc(fn (Reseller $r) => $r->messages->first()?->created_at)
            ->values()
            ->map(fn (Reseller $r) => [
                'id' => $r->id,
                'name' => $r->business_name ?: $r->name,
                'code' => $r->code,
                'unread' => $r->unread_count,
                'preview' => str($r->messages->first()?->body ?? '')->limit(70)->toString(),
                'last_at' => $r->messages->first()?->created_at?->diffForHumans(),
            ]);

        $activeId = (int) ($request->integer('reseller') ?: $threads->first()['id'] ?? 0);
        $active = $activeId ? Reseller::find($activeId) : null;

        if ($active) {
            $active->messages()
                ->where('author_role', 'reseller')
                ->whereNull('read_at')
                ->update(['read_at' => now()]);
        }

        return Inertia::render('Admin/Messages/Index', [
            'threads' => $threads,
            'active' => $active ? [
                'id' => $active->id,
                'name' => $active->business_name ?: $active->name,
                'code' => $active->code,
                'phone' => $active->phone,
            ] : null,
            'messages' => $active
                ? $active->messages()->with('order:id,order_number')->oldest()->get()->map(fn (Message $m) => [
                    'id' => $m->id,
                    'body' => $m->body,
                    'author_role' => $m->author_role,
                    'order_number' => $m->order?->order_number,
                    'sent_at' => $m->created_at->format('M j, Y g:i A'),
                ])
                : [],
        ]);
    }

    public function store(Request $request, Reseller $reseller): RedirectResponse
    {
        $data = $request->validate(['body' => ['required', 'string', 'max:2000']]);

        Message::create([
            'reseller_id' => $reseller->id,
            'user_id' => $request->user()->id,
            'author_role' => 'admin',
            'body' => $data['body'],
        ]);

        return back();
    }
}
