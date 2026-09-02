import { useForm, usePage } from '@inertiajs/react';
import { useEffect, useRef } from 'react';
import clsx from 'clsx';
import { MessageCircle, Send } from 'lucide-react';
import PortalLayout from '@/Layouts/PortalLayout';
import { Button, EmptyState, Textarea } from '@/Components/Lileu/ui';

export default function Messages({ messages }) {
    const { brand } = usePage().props;
    const bottom = useRef(null);
    const { data, setData, post, processing, reset, errors } = useForm({ body: '' });

    useEffect(() => {
        bottom.current?.scrollIntoView({ behavior: 'smooth' });
    }, [messages.length]);

    const submit = (e) => {
        e.preventDefault();
        post(route('portal.messages.store'), { onSuccess: () => reset('body'), preserveScroll: true });
    };

    return (
        <PortalLayout title={`Chat with ${brand?.name}`} subtitle="Ask about an order, a delivery, or anything else.">
            <div className="card flex h-[70vh] flex-col overflow-hidden">
                <div className="flex-1 space-y-3 overflow-y-auto p-4 sm:p-6">
                    {messages.length === 0 ? (
                        <EmptyState
                            icon={MessageCircle}
                            title="No messages yet"
                            description="Say hello — we usually reply within the day."
                        />
                    ) : (
                        messages.map((message) => {
                            const mine = message.author_role === 'reseller';

                            return (
                                <div key={message.id} className={clsx('flex', mine ? 'justify-end' : 'justify-start')}>
                                    <div
                                        className={clsx(
                                            'max-w-[78%] rounded-2xl px-4 py-3 text-sm leading-relaxed shadow-soft',
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
                        })
                    )}
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
                            placeholder="Write a message…"
                            className="max-h-32 min-h-11 resize-none"
                        />
                        <Button type="submit" disabled={processing || !data.body.trim()} className="h-11 px-4">
                            <Send className="h-4 w-4" />
                            <span className="hidden sm:inline">Send</span>
                        </Button>
                    </div>
                    {errors.body && <p className="mt-1.5 text-xs font-medium text-cherry">{errors.body}</p>}
                </form>
            </div>
        </PortalLayout>
    );
}
