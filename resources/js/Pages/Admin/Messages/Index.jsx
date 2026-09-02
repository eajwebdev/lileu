import { Link, router, useForm } from '@inertiajs/react';
import { useEffect, useRef } from 'react';
import clsx from 'clsx';
import { MessageCircle, Send } from 'lucide-react';
import AdminLayout from '@/Layouts/AdminLayout';
import { Button, Card, EmptyState, Textarea } from '@/Components/Lileu/ui';

export default function Index({ threads, active, messages }) {
    const bottom = useRef(null);
    const { data, setData, post, processing, reset } = useForm({ body: '' });

    useEffect(() => {
        bottom.current?.scrollIntoView({ behavior: 'smooth' });
    }, [messages.length, active?.id]);

    const submit = (e) => {
        e.preventDefault();

        if (!active) return;

        post(route('admin.messages.store', active.id), {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => reset('body'),
        });
    };

    return (
        <AdminLayout title="Messages" subtitle="Order questions and updates, in one thread per reseller.">
            {threads.length === 0 ? (
                <EmptyState
                    icon={MessageCircle}
                    title="No conversations yet"
                    description="When a reseller messages you from their portal, the thread opens here."
                />
            ) : (
                <div className="grid gap-4 lg:grid-cols-[19rem_1fr] lg:items-start">
                    {/* Threads */}
                    <Card className="max-h-[74vh] overflow-y-auto p-2">
                        {threads.map((thread) => (
                            <button
                                key={thread.id}
                                type="button"
                                onClick={() =>
                                    router.get(
                                        route('admin.messages.index', { reseller: thread.id }),
                                        {},
                                        { preserveState: true },
                                    )
                                }
                                className={clsx(
                                    'block w-full rounded-xl px-3 py-3 text-left transition',
                                    active?.id === thread.id ? 'bg-blush-100' : 'hover:bg-cream-100',
                                )}
                            >
                                <div className="flex items-center justify-between gap-2">
                                    <span className="truncate font-medium text-chocolate-700">{thread.name}</span>
                                    {thread.unread > 0 && (
                                        <span className="shrink-0 rounded-full bg-cherry px-1.5 py-0.5 text-[10px] font-bold text-white">
                                            {thread.unread}
                                        </span>
                                    )}
                                </div>
                                <p className="mt-0.5 truncate text-xs text-chocolate-400">{thread.preview}</p>
                                <p className="mt-0.5 text-[11px] text-chocolate-300">{thread.last_at}</p>
                            </button>
                        ))}
                    </Card>

                    {/* Conversation */}
                    <Card className="flex h-[74vh] flex-col overflow-hidden">
                        {active && (
                            <div className="flex items-center justify-between gap-3 border-b border-cream-200 px-5 py-3.5">
                                <div>
                                    <p className="font-display font-semibold text-chocolate-700">{active.name}</p>
                                    <p className="font-mono text-[11px] text-chocolate-300">
                                        {active.code} · {active.phone}
                                    </p>
                                </div>
                                <Link
                                    href={route('admin.resellers.show', active.id)}
                                    className="text-sm font-semibold text-chocolate-500 transition hover:text-chocolate-700"
                                >
                                    View profile
                                </Link>
                            </div>
                        )}

                        <div className="flex-1 space-y-3 overflow-y-auto p-4 sm:p-5">
                            {messages.map((message) => {
                                const mine = message.author_role === 'admin';

                                return (
                                    <div key={message.id} className={clsx('flex', mine ? 'justify-end' : 'justify-start')}>
                                        <div
                                            className={clsx(
                                                'max-w-[76%] rounded-2xl px-4 py-3 text-sm leading-relaxed shadow-soft',
                                                mine
                                                    ? 'rounded-br-md bg-chocolate-700 text-cream-100'
                                                    : 'rounded-bl-md bg-blush-50 text-chocolate-700',
                                            )}
                                        >
                                            {message.order_number && (
                                                <p
                                                    className={clsx(
                                                        'mb-1 font-mono text-[11px]',
                                                        mine ? 'text-blush-200' : 'text-blush-500',
                                                    )}
                                                >
                                                    Re: {message.order_number}
                                                </p>
                                            )}
                                            <p className="whitespace-pre-wrap">{message.body}</p>
                                            <p
                                                className={clsx(
                                                    'mt-1.5 text-[11px]',
                                                    mine ? 'text-cream-200/50' : 'text-chocolate-300',
                                                )}
                                            >
                                                {message.sent_at}
                                            </p>
                                        </div>
                                    </div>
                                );
                            })}
                            <div ref={bottom} />
                        </div>

                        <form onSubmit={submit} className="border-t border-cream-300 bg-cream-50 p-3 sm:p-4">
                            <div className="flex items-end gap-2">
                                <Textarea
                                    rows={1}
                                    value={data.body}
                                    onChange={(e) => setData('body', e.target.value)}
                                    onKeyDown={(e) => {
                                        if (e.key === 'Enter' && !e.shiftKey) {
                                            e.preventDefault();
                                            submit(e);
                                        }
                                    }}
                                    placeholder={active ? `Reply to ${active.name}…` : 'Select a conversation'}
                                    disabled={!active}
                                    className="max-h-32 min-h-11 resize-none"
                                />
                                <Button
                                    type="submit"
                                    disabled={processing || !active || !data.body.trim()}
                                    className="h-11 px-4"
                                >
                                    <Send className="h-4 w-4" />
                                    <span className="hidden sm:inline">Send</span>
                                </Button>
                            </div>
                        </form>
                    </Card>
                </div>
            )}
        </AdminLayout>
    );
}
