@extends('layouts.app')

@section('content')
    <x-admin.page-header
        title="Platform settings"
        description="Configure the global email, SMS, WhatsApp, and subscription payment providers."
    />

    <div class="alert alert-warning d-flex align-items-start" role="note">
        <i class="fa-solid fa-lock mr-3 mt-1" aria-hidden="true"></i>
        <div>
            <strong>Credentials are protected</strong>
            <p class="mb-0">Provider secrets are encrypted in the database and never shown after saving. Keep the application key backed up; it is required to decrypt these settings.</p>
        </div>
    </div>

    <form method="post" action="{{ route('platform.settings.update') }}">
        @csrf
        @method('put')

        <x-admin.card>
            <div class="d-flex align-items-start justify-content-between flex-wrap mb-4">
                <div>
                    <h2 class="h5 mb-1">Email delivery</h2>
                    <p class="text-muted mb-0">SMTP settings are used for invitations, password resets, and email notifications.</p>
                </div>
                <x-admin.badge :tone="$settings['mail_smtp_ready'] ? 'success' : 'warning'">
                    {{ $settings['mail_smtp_ready'] ? 'Sender configured' : 'Needs configuration' }}
                </x-admin.badge>
            </div>

            <div class="form-row">
                <div class="form-group col-md-4">
                    <label for="mail_mailer">Mail delivery</label>
                    <select id="mail_mailer" name="mail_mailer" class="form-control" required>
                        <option value="smtp" @selected(old('mail_mailer', $settings['mail_mailer']) === 'smtp')>SMTP server</option>
                        <option value="log" @selected(old('mail_mailer', $settings['mail_mailer']) === 'log')>Application log (development)</option>
                        <option value="sendmail" @selected(old('mail_mailer', $settings['mail_mailer']) === 'sendmail')>Server sendmail</option>
                    </select>
                    @error('mail_mailer') <small class="text-danger">{{ $message }}</small> @enderror
                </div>
                <div class="form-group col-md-5">
                    <label for="mail_host">SMTP host</label>
                    <input id="mail_host" name="mail_host" class="form-control" value="{{ old('mail_host', $settings['mail_host']) }}" autocomplete="off">
                    @error('mail_host') <small class="text-danger">{{ $message }}</small> @enderror
                </div>
                <div class="form-group col-md-3">
                    <label for="mail_port">Port</label>
                    <input id="mail_port" name="mail_port" type="number" min="1" max="65535" class="form-control" value="{{ old('mail_port', $settings['mail_port']) }}">
                    @error('mail_port') <small class="text-danger">{{ $message }}</small> @enderror
                </div>
                <div class="form-group col-md-4">
                    <label for="mail_scheme">Encryption</label>
                    <select id="mail_scheme" name="mail_scheme" class="form-control">
                        <option value="">Provider default</option>
                        <option value="tls" @selected(old('mail_scheme', $settings['mail_scheme']) === 'tls')>TLS / STARTTLS</option>
                        <option value="ssl" @selected(old('mail_scheme', $settings['mail_scheme']) === 'ssl')>SSL</option>
                    </select>
                    @error('mail_scheme') <small class="text-danger">{{ $message }}</small> @enderror
                </div>
                <div class="form-group col-md-4">
                    <label for="mail_from_address">From email</label>
                    <input id="mail_from_address" name="mail_from_address" type="email" class="form-control" value="{{ old('mail_from_address', $settings['mail_from_address']) }}" autocomplete="off">
                    @error('mail_from_address') <small class="text-danger">{{ $message }}</small> @enderror
                </div>
                <div class="form-group col-md-4">
                    <label for="mail_from_name">From name</label>
                    <input id="mail_from_name" name="mail_from_name" class="form-control" value="{{ old('mail_from_name', $settings['mail_from_name']) }}" autocomplete="off">
                    @error('mail_from_name') <small class="text-danger">{{ $message }}</small> @enderror
                </div>
                <div class="form-group col-md-6">
                    <label for="mail_username">SMTP username</label>
                    <input id="mail_username" name="mail_username" class="form-control" autocomplete="new-password" placeholder="{{ $settings['mail_username_configured'] ? 'Saved securely · leave blank to keep' : 'Optional' }}">
                    <small class="form-text text-muted">{{ $settings['mail_username_configured'] ? 'A username is saved.' : 'No username saved.' }}</small>
                    @error('mail_username') <small class="text-danger">{{ $message }}</small> @enderror
                    <div class="custom-control custom-checkbox mt-2">
                        <input id="clear_mail_username" name="clear_mail_username" type="checkbox" value="1" class="custom-control-input">
                        <label class="custom-control-label" for="clear_mail_username">Clear saved username</label>
                    </div>
                </div>
                <div class="form-group col-md-6">
                    <label for="mail_password">SMTP password</label>
                    <input id="mail_password" name="mail_password" type="password" class="form-control" autocomplete="new-password" placeholder="{{ $settings['mail_password_configured'] ? 'Saved securely · leave blank to keep' : 'Optional' }}">
                    <small class="form-text text-muted">{{ $settings['mail_password_configured'] ? 'A password is saved.' : 'No password saved.' }}</small>
                    @error('mail_password') <small class="text-danger">{{ $message }}</small> @enderror
                    <div class="custom-control custom-checkbox mt-2">
                        <input id="clear_mail_password" name="clear_mail_password" type="checkbox" value="1" class="custom-control-input">
                        <label class="custom-control-label" for="clear_mail_password">Clear saved password</label>
                    </div>
                </div>
            </div>
        </x-admin.card>

        <x-admin.card>
            <div class="d-flex align-items-start justify-content-between flex-wrap mb-4">
                <div>
                    <h2 class="h5 mb-1">SMS and WhatsApp</h2>
                    <p class="text-muted mb-0">Configure Twilio to deliver tenant notification templates through SMS or WhatsApp.</p>
                </div>
                <x-admin.badge :tone="$settings['twilio_ready'] ? 'success' : 'slate'">
                    {{ $settings['twilio_ready'] ? 'Twilio configured' : 'Optional' }}
                </x-admin.badge>
            </div>

            <div class="form-row">
                <div class="form-group col-md-6">
                    <label for="twilio_account_sid">Twilio Account SID</label>
                    <input id="twilio_account_sid" name="twilio_account_sid" class="form-control" autocomplete="off" placeholder="{{ $settings['twilio_account_sid_configured'] ? 'Saved securely · leave blank to keep' : 'AC…' }}">
                    @error('twilio_account_sid') <small class="text-danger">{{ $message }}</small> @enderror
                    <div class="custom-control custom-checkbox mt-2">
                        <input id="clear_twilio_account_sid" name="clear_twilio_account_sid" type="checkbox" value="1" class="custom-control-input">
                        <label class="custom-control-label" for="clear_twilio_account_sid">Clear saved SID</label>
                    </div>
                </div>
                <div class="form-group col-md-6">
                    <label for="twilio_auth_token">Twilio Auth Token</label>
                    <input id="twilio_auth_token" name="twilio_auth_token" type="password" class="form-control" autocomplete="new-password" placeholder="{{ $settings['twilio_auth_token_configured'] ? 'Saved securely · leave blank to keep' : 'Enter auth token' }}">
                    @error('twilio_auth_token') <small class="text-danger">{{ $message }}</small> @enderror
                    <div class="custom-control custom-checkbox mt-2">
                        <input id="clear_twilio_auth_token" name="clear_twilio_auth_token" type="checkbox" value="1" class="custom-control-input">
                        <label class="custom-control-label" for="clear_twilio_auth_token">Clear saved token</label>
                    </div>
                </div>
                <div class="form-group col-md-6">
                    <label for="twilio_sms_from">SMS sender number</label>
                    <input id="twilio_sms_from" name="twilio_sms_from" class="form-control" value="{{ old('twilio_sms_from', $settings['twilio_sms_from']) }}" placeholder="+15551234567">
                    @error('twilio_sms_from') <small class="text-danger">{{ $message }}</small> @enderror
                </div>
                <div class="form-group col-md-6">
                    <label for="twilio_whatsapp_from">WhatsApp sender</label>
                    <input id="twilio_whatsapp_from" name="twilio_whatsapp_from" class="form-control" value="{{ old('twilio_whatsapp_from', $settings['twilio_whatsapp_from']) }}" placeholder="whatsapp:+15551234567">
                    @error('twilio_whatsapp_from') <small class="text-danger">{{ $message }}</small> @enderror
                </div>
            </div>
            <p class="small text-muted mb-0">Recipients must have phone numbers in international E.164 format. WhatsApp requires an approved sender in Twilio.</p>
        </x-admin.card>

        <x-admin.card>
            <div class="d-flex align-items-start justify-content-between flex-wrap mb-4">
                <div>
                    <h2 class="h5 mb-1">Subscription payments</h2>
                    <p class="text-muted mb-0">Razorpay is used for online plan purchases and renewals.</p>
                </div>
                <x-admin.badge :tone="$settings['razorpay_ready'] ? 'success' : 'slate'">
                    {{ $settings['razorpay_ready'] ? 'Razorpay configured' : 'Optional' }}
                </x-admin.badge>
            </div>

            <div class="form-row">
                <div class="form-group col-md-5">
                    <label for="razorpay_key">Razorpay Key ID</label>
                    <input id="razorpay_key" name="razorpay_key" class="form-control" autocomplete="off" placeholder="{{ $settings['razorpay_key_configured'] ? 'Saved securely · leave blank to keep' : 'Enter key ID' }}">
                    @error('razorpay_key') <small class="text-danger">{{ $message }}</small> @enderror
                    <div class="custom-control custom-checkbox mt-2">
                        <input id="clear_razorpay_key" name="clear_razorpay_key" type="checkbox" value="1" class="custom-control-input">
                        <label class="custom-control-label" for="clear_razorpay_key">Clear saved key ID</label>
                    </div>
                </div>
                <div class="form-group col-md-5">
                    <label for="razorpay_secret">Razorpay Key Secret</label>
                    <input id="razorpay_secret" name="razorpay_secret" type="password" class="form-control" autocomplete="new-password" placeholder="{{ $settings['razorpay_secret_configured'] ? 'Saved securely · leave blank to keep' : 'Enter key secret' }}">
                    @error('razorpay_secret') <small class="text-danger">{{ $message }}</small> @enderror
                    <div class="custom-control custom-checkbox mt-2">
                        <input id="clear_razorpay_secret" name="clear_razorpay_secret" type="checkbox" value="1" class="custom-control-input">
                        <label class="custom-control-label" for="clear_razorpay_secret">Clear saved secret</label>
                    </div>
                </div>
                <div class="form-group col-md-2">
                    <label for="razorpay_currency">Currency</label>
                    <input id="razorpay_currency" name="razorpay_currency" class="form-control text-uppercase" maxlength="3" value="{{ old('razorpay_currency', $settings['razorpay_currency']) }}" required>
                    @error('razorpay_currency') <small class="text-danger">{{ $message }}</small> @enderror
                </div>
            </div>
            <p class="small text-muted mb-0">Use Razorpay test keys until the complete checkout flow is verified. Keep the key secret private.</p>
        </x-admin.card>

        <div class="d-flex justify-content-end mb-4">
            <button type="submit" class="btn btn-primary">Save platform settings</button>
        </div>
    </form>

    <x-admin.card>
        <div class="d-flex align-items-start justify-content-between flex-wrap">
            <div>
                <h2 class="h5 mb-1">Test email delivery</h2>
                <p class="text-muted mb-0">Send a short test message after saving the mail settings.</p>
            </div>
        </div>
        <form method="post" action="{{ route('platform.settings.test-email') }}" class="form-row align-items-end mt-3">
            @csrf
            <div class="form-group col-md-8 mb-md-0">
                <label for="test_email">Send test to</label>
                <input id="test_email" name="test_email" type="email" class="form-control" value="{{ old('test_email') }}" autocomplete="email" required>
                @error('test_email') <small class="text-danger">{{ $message }}</small> @enderror
            </div>
            <div class="form-group col-md-4 mb-0">
                <button class="btn btn-outline-primary" type="submit">Send test email</button>
            </div>
        </form>
    </x-admin.card>
@endsection
