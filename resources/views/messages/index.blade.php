@extends('layouts.app')

@section('content')
    <x-admin.page-header
        title="Messages"
        description="Announcements shared with your audience and accessible campuses."
    />

    @unless ($readTrackingEnabled)
        <div class="alert alert-info mb-0" role="status">
            Messages are available. Read receipts are temporarily unavailable.
        </div>
    @endunless

    <x-admin.card padding="false">
        <div class="card-header">
            <h2 class="h5 mb-1">Current messages</h2>
            <p class="text-muted small mb-0">Only published announcements addressed to your role and campus appear here.</p>
        </div>

        <div class="p-3">
            @forelse ($messages as $message)
                <article class="border rounded p-3 p-md-4 mb-3" aria-labelledby="message-{{ $message->id }}">
                    @php($readAt = $readAtByNotice->get($message->id)?->read_at)
                    <div class="d-flex align-items-start justify-content-between flex-wrap mb-2">
                        <div>
                            <div class="d-flex align-items-center flex-wrap">
                                <h3 id="message-{{ $message->id }}" class="h5 mb-1 mr-2">{{ $message->title }}</h3>
                                @if (! $readTrackingEnabled)
                                    <x-admin.badge tone="slate">Published</x-admin.badge>
                                @elseif ($readAt)
                                    <x-admin.badge tone="slate">Read</x-admin.badge>
                                @else
                                    <x-admin.badge tone="info">Unread</x-admin.badge>
                                @endif
                            </div>
                            <span class="text-muted small">
                                {{ $message->branch?->name ?? 'All campuses' }}
                                · {{ match ($message->audience_type) {
                                    'staff' => 'Staff',
                                    'students' => $message->student_scope === 'selected' ? 'Selected students' : 'All students',
                                    'parents' => 'Parents',
                                    default => 'Everyone',
                                } }}
                            </span>
                        </div>
                        <time class="text-muted small" datetime="{{ $message->published_at->toIso8601String() }}">
                            {{ $message->published_at->format('d M Y, h:i A') }}
                        </time>
                    </div>
                    <p class="mb-0" style="white-space: pre-line">{{ $message->body }}</p>
                    @if ($message->expires_at)
                        <p class="text-muted small mt-3 mb-0">Available until {{ $message->expires_at->format('d M Y') }}</p>
                    @endif
                    <div class="d-flex justify-content-end mt-3">
                        @if (! $readTrackingEnabled)
                            <span class="text-muted small">Read receipts unavailable</span>
                        @elseif ($readAt)
                            <small class="text-muted">Read {{ $readAt->format('d M Y, h:i A') }}</small>
                        @else
                            <form method="post" action="{{ route('messages.read', $message) }}">
                                @csrf
                                <button class="btn btn-sm btn-outline-primary" type="submit">Mark as read</button>
                            </form>
                        @endif
                    </div>
                </article>
            @empty
                <x-admin.empty-state title="No current messages" description="Published announcements for your campuses will appear here." />
            @endforelse
        </div>

        <div class="px-3 pb-3">
            <x-admin.pagination :paginator="$messages" />
        </div>
    </x-admin.card>
@endsection
