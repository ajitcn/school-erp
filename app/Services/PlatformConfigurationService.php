<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

/**
 * Reads and securely stores the platform-wide email and payment provider settings.
 */
class PlatformConfigurationService
{
    private ?array $settingsCache = null;

    private const SECRET_KEYS = [
        'mail_username',
        'mail_password',
        'twilio_account_sid',
        'twilio_auth_token',
        'razorpay_key',
        'razorpay_secret',
    ];

    /**
     * Return the email settings for the administrator form without revealing secrets.
     *
     * @return array<string, mixed>
     */
    public function formSettings(): array
    {
        $mail = $this->mailSettings();
        $twilio = $this->twilioSettings();
        $razorpay = $this->razorpaySettings();

        return [
            'mail_mailer' => $mail['mailer'],
            'mail_host' => $mail['host'],
            'mail_port' => $mail['port'],
            'mail_scheme' => $mail['scheme'],
            'mail_from_address' => $mail['from_address'],
            'mail_from_name' => $mail['from_name'],
            'mail_username_configured' => filled($mail['username']),
            'mail_password_configured' => filled($mail['password']),
            'mail_smtp_ready' => filled($mail['host']) && filled($mail['from_address']),
            'twilio_account_sid_configured' => filled($twilio['account_sid']),
            'twilio_auth_token_configured' => filled($twilio['auth_token']),
            'twilio_sms_from' => $twilio['sms_from'],
            'twilio_whatsapp_from' => $twilio['whatsapp_from'],
            'twilio_ready' => filled($twilio['account_sid']) && filled($twilio['auth_token']),
            'razorpay_key_configured' => filled($razorpay['key']),
            'razorpay_secret_configured' => filled($razorpay['secret']),
            'razorpay_currency' => $razorpay['currency'],
            'razorpay_ready' => filled($razorpay['key']) && filled($razorpay['secret']),
        ];
    }

    /**
     * Validate input upstream, then persist changed settings and encrypted credentials.
     *
     * Empty secret fields keep their current value unless the matching clear flag is set.
     *
     * @param  array<string, mixed>  $data
     */
    public function save(array $data, User $actor): void
    {
        if (! Schema::hasTable('platform_settings')) {
            throw ValidationException::withMessages([
                'configuration' => 'Apply the platform settings migration before saving provider settings.',
            ]);
        }

        $plainKeys = [
            'mail_mailer', 'mail_host', 'mail_port', 'mail_scheme', 'mail_from_address', 'mail_from_name',
            'twilio_sms_from', 'twilio_whatsapp_from', 'razorpay_currency',
        ];

        DB::transaction(function () use ($data, $actor, $plainKeys): void {
            foreach ($plainKeys as $key) {
                if (array_key_exists($key, $data)) {
                    $this->store($key, $data[$key] === null ? '' : (string) $data[$key], false, $actor->id);
                }
            }

            foreach (self::SECRET_KEYS as $key) {
                if (filter_var($data['clear_'.$key] ?? false, FILTER_VALIDATE_BOOLEAN)) {
                    $this->store($key, '', true, $actor->id);

                    continue;
                }

                if (filled($data[$key] ?? null)) {
                    $this->store($key, (string) $data[$key], true, $actor->id);
                }
            }
        });

        $this->settingsCache = null;
    }

    /** Apply saved SMTP values to Laravel before a direct or queued mail is sent. */
    public function applyMailConfiguration(): void
    {
        $mail = $this->mailSettings();
        $smtp = config('mail.mailers.smtp', []);

        config([
            'mail.default' => $mail['mailer'],
            'mail.mailers.smtp' => array_merge($smtp, [
                'transport' => 'smtp',
                'scheme' => $mail['scheme'] ?: null,
                'url' => null,
                'host' => $mail['host'],
                'port' => $mail['port'],
                'username' => $mail['username'],
                'password' => $mail['password'],
            ]),
            'mail.from.address' => $mail['from_address'],
            'mail.from.name' => $mail['from_name'],
        ]);

        Mail::purge();
    }

    /** Return Twilio settings, preferring encrypted platform overrides to environment values. */
    public function twilioSettings(): array
    {
        return [
            'account_sid' => $this->get('twilio_account_sid', config('services.twilio.account_sid')),
            'auth_token' => $this->get('twilio_auth_token', config('services.twilio.auth_token')),
            'sms_from' => $this->get('twilio_sms_from', config('services.twilio.sms_from')),
            'whatsapp_from' => $this->get('twilio_whatsapp_from', config('services.twilio.whatsapp_from')),
        ];
    }

    /** Return Razorpay settings, preferring encrypted platform overrides to environment values. */
    public function razorpaySettings(): array
    {
        return [
            'key' => $this->get('razorpay_key', config('services.razorpay.key')),
            'secret' => $this->get('razorpay_secret', config('services.razorpay.secret')),
            'currency' => $this->get('razorpay_currency', config('services.razorpay.currency', 'INR')),
        ];
    }

    /** Return values used by Laravel's mail transport. */
    private function mailSettings(): array
    {
        return [
            'mailer' => $this->get('mail_mailer', config('mail.default', 'log')),
            'host' => $this->get('mail_host', config('mail.mailers.smtp.host')),
            'port' => $this->get('mail_port', config('mail.mailers.smtp.port', 587)),
            'scheme' => $this->get('mail_scheme', config('mail.mailers.smtp.scheme')),
            'username' => $this->get('mail_username', config('mail.mailers.smtp.username')),
            'password' => $this->get('mail_password', config('mail.mailers.smtp.password')),
            'from_address' => $this->get('mail_from_address', config('mail.from.address')),
            'from_name' => $this->get('mail_from_name', config('mail.from.name')),
        ];
    }

    /** Read a setting while distinguishing an explicit blank override from no database value. */
    private function get(string $key, mixed $fallback = null): mixed
    {
        $settings = $this->settings();

        return array_key_exists($key, $settings) ? $settings[$key] : $fallback;
    }

    /** Load platform configuration once per service instance and decrypt private values. */
    private function settings(): array
    {
        if ($this->settingsCache !== null) {
            return $this->settingsCache;
        }

        if (! Schema::hasTable('platform_settings')) {
            return $this->settingsCache = [];
        }

        $this->settingsCache = DB::table('platform_settings')
            ->get(['key', 'value', 'is_encrypted'])
            ->mapWithKeys(fn ($setting): array => [
                $setting->key => $setting->is_encrypted
                    ? Crypt::decryptString((string) $setting->value)
                    : $setting->value,
            ])
            ->all();

        return $this->settingsCache;
    }

    /** Insert or update one setting without writing plaintext secrets to the database. */
    private function store(string $key, string $value, bool $encrypted, int $userId): void
    {
        $existing = DB::table('platform_settings')->where('key', $key)->exists();
        $record = [
            'value' => $encrypted ? Crypt::encryptString($value) : $value,
            'is_encrypted' => $encrypted,
            'updated_by' => $userId,
            'updated_at' => now(),
        ];

        if ($existing) {
            DB::table('platform_settings')->where('key', $key)->update($record);

            return;
        }

        DB::table('platform_settings')->insert($record + [
            'key' => $key,
            'created_at' => now(),
        ]);
    }
}
