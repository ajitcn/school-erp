@extends('layouts.app')

@section('content')
    <x-admin.page-header
        title="Notification templates"
        description="Choose delivery channels and customize email, SMS, WhatsApp, and in-app messages."
    />

    <x-admin.card>
        <h2 class="h5">Create or update a template</h2>

        <form method="post" action="{{ route('admin.notification-templates.save') }}">
            @csrf

            <div class="form-row">
                <div class="form-group col-md-6">
                    <label for="event">Event key</label>
                    <input
                        id="event"
                        name="event"
                        class="form-control"
                        value="{{ old('event') }}"
                        placeholder="payment.received"
                        required
                    >
                </div>

                <div class="form-group col-md-6">
                    <label for="channel">Channel</label>
                    <select id="channel" name="channel" class="form-control" required>
                        <option value="in_app" @selected(old('channel', 'in_app') === 'in_app')>In-app</option>
                        <option value="email" @selected(old('channel') === 'email')>Email</option>
                        <option value="sms" @selected(old('channel') === 'sms')>SMS</option>
                        <option value="whatsapp" @selected(old('channel') === 'whatsapp')>WhatsApp</option>
                    </select>
                </div>

                <div class="form-group col-12">
                    <label for="subject">Subject <span class="text-muted">(email)</span></label>
                    <input id="subject" name="subject" class="form-control" value="{{ old('subject') }}">
                </div>

                <div class="form-group col-12">
                    <label for="body">Message body</label>
                    <textarea
                        id="body"
                        name="body"
                        rows="4"
                        class="form-control"
                        placeholder="Payment received: @{{student}} · ₹@{{amount}}"
                        required
                    >{{ old('body') }}</textarea>
                    <small class="form-text text-muted">
                        Insert values with placeholders such as @{{name}}. Events include attendance.absent, payment.received, and fee.due_reminder. Fee reminders support @{{student}}, @{{fee_plan}}, @{{amount}}, @{{due_date}}, and @{{due_status}}.
                    </small>
                </div>

                <div class="form-group col-12 mb-0">
                    <div class="custom-control custom-checkbox">
                        <input
                            id="is_active"
                            type="checkbox"
                            class="custom-control-input"
                            name="is_active"
                            value="1"
                            @checked(old('is_active', true))
                        >
                        <label class="custom-control-label" for="is_active">Active</label>
                    </div>
                </div>
            </div>

            <button class="btn btn-primary" type="submit">Save template</button>
        </form>
    </x-admin.card>

    <x-admin.card padding="false">
        <div class="card-header">
            <h2>Configured templates</h2>
        </div>

        <x-admin.table>
            <thead>
                <tr>
                    <th>Event</th>
                    <th>Channel</th>
                    <th>Subject</th>
                    <th>Message</th>
                    <th>Status</th>
                    <th class="text-right">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($templates as $template)
                    <tr>
                        <td>{{ $template->event }}</td>
                        <td>{{ ucfirst($template->channel) }}</td>
                        <td>{{ $template->subject ?: '—' }}</td>
                        <td class="text-truncate" style="max-width: 320px">{{ $template->body }}</td>
                        <td>{{ $template->is_active ? 'Active' : 'Disabled' }}</td>
                        <td class="text-right">
                            <form method="post" action="{{ route('admin.notification-templates.delete', $template->event) }}">
                                @csrf
                                @method('delete')
                                <button class="btn btn-sm btn-outline-danger" type="submit">Delete</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="p-4 text-center text-muted">
                            No notification templates have been configured.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </x-admin.table>
    </x-admin.card>
@endsection
