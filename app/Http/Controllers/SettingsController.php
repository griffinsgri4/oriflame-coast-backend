<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Setting;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class SettingsController extends Controller
{
    /**
     * Get all settings grouped by category
     */
    public function index(): JsonResponse
    {
        try {
            $settings = [
                'general' => Setting::getByCategory('general'),
                'notification' => Setting::getByCategory('notification'),
                'security' => Setting::getByCategory('security'),
                'email' => Setting::getByCategory('email'),
            ];

            // Provide default values if settings don't exist
            $defaultSettings = [
                'general' => [
                    'site_name' => 'Oriflame Coast',
                    'site_description' => 'Premium beauty and wellness products',
                    'timezone' => 'UTC',
                    'currency' => 'USD',
                    'language' => 'en',
                ],
                'notification' => [
                    'email_notifications' => true,
                    'push_notifications' => true,
                    'sms_notifications' => false,
                    'order_notifications' => true,
                    'marketing_emails' => true,
                ],
                'security' => [
                    'two_factor_auth' => false,
                    'session_timeout' => 30,
                    'password_expiry' => 90,
                    'login_attempts' => 5,
                    'account_lockout' => 15,
                ],
                'email' => [
                    'smtp_host' => '',
                    'smtp_port' => 587,
                    'smtp_username' => '',
                    'smtp_password' => '',
                    'from_email' => 'noreply@oriflamecoast.com',
                    'from_name' => 'Oriflame Coast',
                ],
            ];

            // Merge with defaults
            foreach ($defaultSettings as $category => $defaults) {
                $settings[$category] = array_merge($defaults, $settings[$category]->toArray());
            }

            return response()->json([
                'success' => true,
                'data' => $settings
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch settings',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update settings
     */
    public function update(Request $request): JsonResponse
    {
        try {
            $settings = $request->all();

            foreach ($settings as $category => $categorySettings) {
                if (is_array($categorySettings)) {
                    foreach ($categorySettings as $key => $value) {
                        $type = $this->getSettingType($value);
                        $settingKey = $category . '.' . $key;
                        
                        Setting::setValue(
                            $settingKey,
                            is_array($value) ? json_encode($value) : $value,
                            $type,
                            $category,
                            $this->getSettingDescription($settingKey)
                        );
                    }
                }
            }

            return response()->json([
                'success' => true,
                'message' => 'Settings updated successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update settings',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Upload a branding image and persist its URL into settings (admin-only route)
     */
    public function upload(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'key' => 'required|string',
                'image' => 'required|file|mimes:jpg,jpeg,png,webp|max:5120',
            ]);

            $allowedKeys = [
                'general.site_logo_url',
                'general.hero_banner_url',
            ];
            $key = $validated['key'];
            if (!in_array($key, $allowedKeys, true)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid key for image upload',
                ], 422);
            }

            $file = $request->file('image');
            $baseName = $key === 'general.site_logo_url' ? 'site-logo' : 'hero-banner';
            $ext = $file->getClientOriginalExtension();
            $filename = $baseName . '-' . time() . '.' . $ext;

            // Store in public disk under branding
            $path = $file->storeAs('branding', $filename, 'public');
            $url = asset('storage/' . $path);

            // Persist setting
            Setting::setValue($key, $url, 'string', 'general', $this->getSettingDescription($key));

            return response()->json([
                'success' => true,
                'message' => 'Image uploaded successfully',
                'data' => [
                    'key' => $key,
                    'url' => $url,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to upload image',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get a specific setting by key
     */
    public function getSetting($key): JsonResponse
    {
        try {
            $value = Setting::getValue($key);
            
            return response()->json([
                'success' => true,
                'data' => [
                    'key' => $key,
                    'value' => $value
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch setting',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Determine the type of a setting value
     */
    private function getSettingType($value): string
    {
        if (is_bool($value)) {
            return 'boolean';
        } elseif (is_int($value)) {
            return 'integer';
        } elseif (is_array($value)) {
            return 'json';
        } else {
            return 'string';
        }
    }

    /**
     * Get description for a setting key
     */
    private function getSettingDescription($key): string
    {
        $descriptions = [
            'general.site_name' => 'The name of the website',
            'general.site_description' => 'A brief description of the website',
            'general.timezone' => 'Default timezone for the application',
            'general.currency' => 'Default currency for transactions',
            'general.language' => 'Default language for the application',
            'notification.email_notifications' => 'Enable email notifications',
            'notification.push_notifications' => 'Enable push notifications',
            'notification.sms_notifications' => 'Enable SMS notifications',
            'notification.order_notifications' => 'Enable order status notifications',
            'notification.marketing_emails' => 'Enable marketing email campaigns',
            'security.two_factor_auth' => 'Enable two-factor authentication',
            'security.session_timeout' => 'Session timeout in minutes',
            'security.password_expiry' => 'Password expiry in days',
            'security.login_attempts' => 'Maximum login attempts before lockout',
            'security.account_lockout' => 'Account lockout duration in minutes',
            'email.smtp_host' => 'SMTP server hostname',
            'email.smtp_port' => 'SMTP server port',
            'email.smtp_username' => 'SMTP username',
            'email.smtp_password' => 'SMTP password',
            'email.from_email' => 'Default from email address',
            'email.from_name' => 'Default from name',
        ];

        return $descriptions[$key] ?? '';
    }
}
