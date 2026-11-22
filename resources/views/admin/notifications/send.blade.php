@extends('layouts.app')

@section('content')
    <div class="notification-broadcast-page">
        <x-admin.page-header
            title="Send notification"
            description="Send an in-app alert to users in your tenant."
        />

        @unless ($broadcastStorageReady)
            <div class="alert alert-warning" role="status">
                Notification history is not ready yet. Ask the platform administrator to apply the latest database migrations before sending broadcasts.
            </div>
        @endunless

        <div class="notification-send-guidance mb-4" role="note">
            <span class="notification-send-guidance-icon" aria-hidden="true">
                <i class="fa-regular fa-bell"></i>
            </span>
            <div>
                <strong>In-app delivery</strong>
                <p class="mb-0">Recipients will see this in their notification inbox and topbar bell.</p>
            </div>
            <a href="{{ route('admin.notification-templates') }}" class="btn btn-sm btn-outline-secondary ml-auto">Manage templates</a>
        </div>

        <x-admin.card>
            <div class="notification-compose-heading">
                <div>
                    <h2 class="h5 mb-1">Compose notification</h2>
                    <p class="text-muted mb-0">Choose the audience, then enter a title and message.</p>
                </div>
                <span class="notification-recipient-count">
                    <i class="fa-solid fa-users mr-1" aria-hidden="true"></i>
                    {{ number_format($recipients->count()) }} available
                </span>
            </div>

            <form method="post" action="{{ route('admin.notifications.send.store') }}" class="notification-compose-form">
                @csrf

                <div class="form-row">
                    <div class="form-group col-12 col-lg-4">
                        <label for="audience">Send to</label>
                        <select id="audience" name="audience" class="form-control" required data-notification-audience>
                            <option value="all" @selected(old('audience', 'all') === 'all')>All users within my access</option>
                            <option value="selected" @selected(old('audience') === 'selected')>Selected users</option>
                        </select>
                        <small class="form-text text-muted">Your campus permissions limit who you can reach.</small>
                        @error('audience')
                            <small class="text-danger">{{ $message }}</small>
                        @enderror
                    </div>

                    <div class="form-group col-12 col-lg-8 d-none" data-notification-recipient-group>
                        <label for="recipient_ids">Choose users</label>
                        <select id="recipient_ids" name="recipient_ids[]" class="form-control bt-select" multiple data-live-search="true" data-actions-box="true" data-width="100%" data-notification-recipients>
                            @foreach ($recipients as $recipient)
                                <option value="{{ $recipient->id }}" @selected(in_array($recipient->id, old('recipient_ids', [])))>
                                    {{ $recipient->name }} · {{ $recipient->email }} · {{ ucfirst(str_replace('_', ' ', $recipient->role)) }}
                                </option>
                            @endforeach
                        </select>
                        @error('recipient_ids')
                            <small class="text-danger">{{ $message }}</small>
                        @enderror
                        @error('recipient_ids.*')
                            <small class="text-danger">{{ $message }}</small>
                        @enderror
                    </div>

                    <div class="form-group col-12">
                        <label for="subject">Notification title</label>
                        <input id="subject" name="subject" class="form-control" maxlength="180" value="{{ old('subject') }}" required>
                        @error('subject')
                            <small class="text-danger">{{ $message }}</small>
                        @enderror
                    </div>

                    <div class="form-group col-12 mb-0">
                        <label for="message">Message</label>
                        <textarea id="message" name="message" class="form-control" rows="5" maxlength="5000" required data-notification-message>{{ old('message') }}</textarea>
                        <div class="d-flex justify-content-between flex-wrap">
                            <small class="form-text text-muted">Keep it clear and brief. It appears in the inbox and topbar preview.</small>
                            <small class="form-text text-muted"><span data-notification-character-count>0</span> / 5000</small>
                        </div>
                        @error('message')
                            <small class="text-danger">{{ $message }}</small>
                        @enderror
                    </div>
                </div>

                <div class="notification-compose-actions">
                    <a href="{{ route('notifications.index') }}" class="btn btn-link">View notification inbox</a>
                    <button class="btn btn-primary" type="submit" @disabled($recipients->isEmpty() || ! $broadcastStorageReady)>
                        <i class="fa-regular fa-paper-plane mr-2" aria-hidden="true"></i>Send notification
                    </button>
                </div>

                @if ($recipients->isEmpty())
                    <p class="text-muted small mt-3 mb-0">No active users are available in your current tenant and campus access.</p>
                @endif
            </form>
        </x-admin.card>

        <x-admin.card padding="false">
            <div class="card-header">
                <h2 class="h5 mb-1">Broadcast history</h2>
                <p class="text-muted small mb-0">Track how many recipients have opened each notification.</p>
            </div>

            @if ($broadcastStorageReady)
                <div class="table-responsive">
                <x-admin.table>
                    <thead>
                        <tr>
                            <th>Notification</th>
                            <th>Sent by</th>
                            <th>Sent</th>
                            <th>Recipients</th>
                            <th>Read</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($broadcasts as $broadcast)
                            <tr>
                                <td>
                                    <strong>{{ $broadcast->subject }}</strong>
                                    <small class="d-block text-muted">{{ \Illuminate\Support\Str::limit($broadcast->message, 100) }}</small>
                                </td>
                                <td>{{ $broadcast->creator?->name ?? 'Former user' }}</td>
                                <td>{{ $broadcast->created_at->format('d M Y, h:i A') }}</td>
                                <td>{{ number_format($broadcast->recipient_count) }}</td>
                                <td>
                                    {{ number_format($broadcast->read_count) }}
                                    <small class="text-muted">of {{ number_format($broadcast->recipient_count) }}</small>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="py-4">
                                    <x-admin.empty-state title="No notifications sent yet" description="Your sent notifications will appear here with recipient read counts." />
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </x-admin.table>
                </div>

                <div class="p-3">
                    <x-admin.pagination :paginator="$broadcasts" />
                </div>
            @else
                <div class="p-4">
                    <x-admin.empty-state title="Broadcast history unavailable" description="Apply the latest database migrations to enable sending and delivery tracking." />
                </div>
            @endif
        </x-admin.card>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const audience = document.querySelector('[data-notification-audience]');
            const recipientGroup = document.querySelector('[data-notification-recipient-group]');
            const recipients = document.querySelector('[data-notification-recipients]');
            const message = document.querySelector('[data-notification-message]');
            const characterCount = document.querySelector('[data-notification-character-count]');

            if (audience && recipientGroup && recipients) {
                const updateRecipientControl = function () {
                    const selectedUsersRequired = audience.value === 'selected';
                    recipientGroup.classList.toggle('d-none', !selectedUsersRequired);
                    recipients.disabled = !selectedUsersRequired;
                    recipients.required = selectedUsersRequired;

                    if (window.jQuery && jQuery.fn.selectpicker && jQuery(recipients).data('selectpicker')) {
                        jQuery(recipients).selectpicker('refresh');
                    }
                };

                audience.addEventListener('change', updateRecipientControl);
                updateRecipientControl();
            }

            if (message && characterCount) {
                const updateCharacterCount = function () {
                    characterCount.textContent = message.value.length;
                };

                message.addEventListener('input', updateCharacterCount);
                updateCharacterCount();
            }
        });
    </script>
@endsection
