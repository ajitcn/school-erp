<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Services\PlatformConfigurationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;
use Throwable;

/**
 * Maintains global email, messaging-provider, and payment-provider settings.
 */
class PlatformSettingsController extends Controller
{
    /** Show provider settings and only reveal whether secrets are stored. */
    public function index(PlatformConfigurationService $configuration): View
    {
        return view('platform.settings', [
            'settings' => $configuration->formSettings(),
            'settingsStorageReady' => Schema::hasTable('platform_settings'),
        ]);
    }

    /** Validate and securely save platform-wide provider configuration. */
    public function update(Request $request, PlatformConfigurationService $configuration): RedirectResponse
    {
        $data = $request->validate([
            'mail_mailer' => ['required', 'in:smtp,log,sendmail'],
            'mail_host' => ['nullable', 'required_if:mail_mailer,smtp', 'string', 'max:180'],
            'mail_port' => ['nullable', 'required_if:mail_mailer,smtp', 'integer', 'between:1,65535'],
            'mail_scheme' => ['nullable', 'in:tls,ssl'],
            'mail_username' => ['nullable', 'string', 'max:255'],
            'mail_password' => ['nullable', 'string', 'max:1000'],
            'mail_from_address' => ['nullable', 'required_if:mail_mailer,smtp', 'email', 'max:255'],
            'mail_from_name' => ['nullable', 'string', 'max:180'],
            'clear_mail_username' => ['nullable', 'boolean'],
            'clear_mail_password' => ['nullable', 'boolean'],
            'twilio_account_sid' => ['nullable', 'string', 'max:255'],
            'twilio_auth_token' => ['nullable', 'string', 'max:255'],
            'twilio_sms_from' => ['nullable', 'string', 'max:80'],
            'twilio_whatsapp_from' => ['nullable', 'string', 'max:80'],
            'clear_twilio_account_sid' => ['nullable', 'boolean'],
            'clear_twilio_auth_token' => ['nullable', 'boolean'],
            'razorpay_key' => ['nullable', 'string', 'max:255'],
            'razorpay_secret' => ['nullable', 'string', 'max:255'],
            'razorpay_currency' => ['required', 'string', 'size:3', 'alpha'],
            'clear_razorpay_key' => ['nullable', 'boolean'],
            'clear_razorpay_secret' => ['nullable', 'boolean'],
        ]);

        $configuration->save($data, $request->user());
        AuditLog::create([
            'user_id' => $request->user()->id,
            'action' => 'platform.configuration.updated',
            'auditable_type' => 'platform_configuration',
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'new_values' => ['updated_keys' => array_values(array_diff(array_keys($data), [
                'mail_password', 'mail_username', 'twilio_account_sid', 'twilio_auth_token', 'razorpay_key', 'razorpay_secret',
            ]))],
        ]);

        return back()->with('success', 'Platform provider settings saved.');
    }

    /** Send a short verification email to an address entered by the platform admin. */
    public function testEmail(Request $request, PlatformConfigurationService $configuration): RedirectResponse
    {
        $data = $request->validate(['test_email' => ['required', 'email', 'max:255']]);

        try {
            $configuration->applyMailConfiguration();
            Mail::raw('This is a test email from the School ERP platform configuration page.', function ($mail) use ($data): void {
                $mail->to($data['test_email'])->subject('School ERP email configuration test');
            });
        } catch (Throwable $exception) {
            Log::warning('Platform email configuration test failed.', [
                'user_id' => $request->user()->id,
                'exception' => $exception::class,
            ]);

            return back()->withErrors(['test_email' => 'The test email could not be sent. Check the mail settings and server logs.']);
        }

        $message = config('mail.default') === 'log'
            ? 'Test message written to the Laravel log. Select SMTP to deliver it to '.$data['test_email'].'.'
            : 'Test email sent to '.$data['test_email'].'.';

        return back()->with('success', $message);
    }
}
