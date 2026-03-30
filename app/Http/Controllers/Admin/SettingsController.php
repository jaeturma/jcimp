<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use RuntimeException;

class SettingsController extends Controller
{
    public function updateRecaptcha(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'site_key'   => 'required|string|max:255',
            'secret_key' => 'required|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Invalid input', 'errors' => $validator->errors()], 422);
        }

        $this->writeEnvKeys([
            'RECAPTCHA_SITE_KEY'   => $request->input('site_key'),
            'RECAPTCHA_SECRET_KEY' => $request->input('secret_key'),
        ]);

        Artisan::call('config:clear');

        return response()->json(['message' => 'CAPTCHA settings saved successfully.']);
    }

    /**
     * Update SMTP / mail settings.
     *
     * POST /api/admin/settings/smtp
     */
    public function updateSmtp(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'mailer'       => 'required|in:smtp,log,sendmail',
            'host'         => 'nullable|string|max:255',
            'port'         => 'nullable|integer|min:1|max:65535',
            'username'     => 'nullable|string|max:255',
            'password'     => 'nullable|string|max:255',
            'scheme'       => 'nullable|in:tls,ssl,null,',
            'from_address' => 'required|email|max:255',
            'from_name'    => 'required|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Invalid input', 'errors' => $validator->errors()], 422);
        }

        $keys = [
            'MAIL_MAILER'       => $request->input('mailer', 'log'),
            'MAIL_HOST'         => $request->input('host', '127.0.0.1'),
            'MAIL_PORT'         => (string) $request->input('port', '2525'),
            'MAIL_SCHEME'       => $request->input('scheme') ?: 'null',
            'MAIL_USERNAME'     => $request->input('username') ?: 'null',
            'MAIL_FROM_ADDRESS' => $request->input('from_address'),
            'MAIL_FROM_NAME'    => $request->input('from_name'),
        ];

        // Only overwrite password if a new one was provided
        if ($request->filled('password')) {
            $keys['MAIL_PASSWORD'] = $request->input('password');
        }

        $this->writeEnvKeys($keys);
        Artisan::call('config:clear');

        return response()->json(['message' => 'Email settings saved successfully.']);
    }

    /**
     * Send a test email to verify SMTP is working.
     *
     * POST /api/admin/settings/test-email
     */
    public function testEmail(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'to' => 'required|email|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Invalid email address.'], 422);
        }

        try {
            Mail::raw(
                "This is a test email from your Concert Ticketing system.\n\n"
                . "If you received this, your SMTP configuration is working correctly.\n\n"
                . "Sent at: " . now()->toDateTimeString(),
                fn ($msg) => $msg
                    ->to($request->input('to'))
                    ->subject('Test Email — Concert Ticketing System')
            );

            return response()->json(['message' => 'Test email sent successfully. Check your inbox.']);
        } catch (\Throwable $e) {
            return response()->json(['message' => 'Failed to send test email: ' . $e->getMessage()], 422);
        }
    }

    private function writeEnvKeys(array $data): void
    {
        $path = base_path('.env');

        if (! file_exists($path)) {
            throw new RuntimeException('.env file not found.');
        }

        $env = file_get_contents($path);

        foreach ($data as $key => $value) {
            $escapedValue = str_contains($value, ' ') ? '"'.str_replace('"', '\\"', $value).'"' : $value;

            if (preg_match('/^'.$key.'=.*/m', $env)) {
                $env = preg_replace('/^'.$key.'=.*/m', $key.'='.$escapedValue, $env);
            } else {
                $env .= "\n".$key.'='.$escapedValue;
            }
        }

        file_put_contents($path, $env);
    }
}
