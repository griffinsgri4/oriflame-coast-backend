<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Setting;

class SettingsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $settings = [
            // General Settings
            [
                'key' => 'site_name',
                'value' => 'Oriflame Coast Region',
                'type' => 'string',
                'category' => 'general',
                'description' => 'The name of the website'
            ],
            [
                'key' => 'site_description',
                'value' => 'Premium beauty products and business opportunities',
                'type' => 'string',
                'category' => 'general',
                'description' => 'A brief description of the website'
            ],
            [
                'key' => 'timezone',
                'value' => 'UTC',
                'type' => 'string',
                'category' => 'general',
                'description' => 'Default timezone for the application'
            ],
            [
                'key' => 'currency',
                'value' => 'USD',
                'type' => 'string',
                'category' => 'general',
                'description' => 'Default currency for the application'
            ],
            [
                'key' => 'language',
                'value' => 'en',
                'type' => 'string',
                'category' => 'general',
                'description' => 'Default language for the application'
            ],

            // Notification Settings
            [
                'key' => 'email_notifications',
                'value' => 'true',
                'type' => 'boolean',
                'category' => 'notification',
                'description' => 'Enable email notifications'
            ],
            [
                'key' => 'push_notifications',
                'value' => 'true',
                'type' => 'boolean',
                'category' => 'notification',
                'description' => 'Enable push notifications'
            ],
            [
                'key' => 'sms_notifications',
                'value' => 'false',
                'type' => 'boolean',
                'category' => 'notification',
                'description' => 'Enable SMS notifications'
            ],
            [
                'key' => 'order_notifications',
                'value' => 'true',
                'type' => 'boolean',
                'category' => 'notification',
                'description' => 'Enable order status notifications'
            ],
            [
                'key' => 'marketing_emails',
                'value' => 'true',
                'type' => 'boolean',
                'category' => 'notification',
                'description' => 'Enable marketing email notifications'
            ],

            // Security Settings
            [
                'key' => 'two_factor_auth',
                'value' => 'false',
                'type' => 'boolean',
                'category' => 'security',
                'description' => 'Enable two-factor authentication'
            ],
            [
                'key' => 'session_timeout',
                'value' => '30',
                'type' => 'integer',
                'category' => 'security',
                'description' => 'Session timeout in minutes'
            ],
            [
                'key' => 'password_expiry',
                'value' => '90',
                'type' => 'integer',
                'category' => 'security',
                'description' => 'Password expiry in days'
            ],
            [
                'key' => 'login_attempts',
                'value' => '5',
                'type' => 'integer',
                'category' => 'security',
                'description' => 'Maximum login attempts before lockout'
            ],
            [
                'key' => 'account_lockout',
                'value' => '15',
                'type' => 'integer',
                'category' => 'security',
                'description' => 'Account lockout duration in minutes'
            ],

            // Email Settings
            [
                'key' => 'smtp_host',
                'value' => 'smtp.gmail.com',
                'type' => 'string',
                'category' => 'email',
                'description' => 'SMTP server hostname'
            ],
            [
                'key' => 'smtp_port',
                'value' => '587',
                'type' => 'integer',
                'category' => 'email',
                'description' => 'SMTP server port'
            ],
            [
                'key' => 'smtp_username',
                'value' => '',
                'type' => 'string',
                'category' => 'email',
                'description' => 'SMTP username'
            ],
            [
                'key' => 'smtp_password',
                'value' => '',
                'type' => 'string',
                'category' => 'email',
                'description' => 'SMTP password'
            ],
            [
                'key' => 'from_email',
                'value' => 'noreply@oriflame-coast.com',
                'type' => 'string',
                'category' => 'email',
                'description' => 'Default from email address'
            ],
            [
                'key' => 'from_name',
                'value' => 'Oriflame Coast Region',
                'type' => 'string',
                'category' => 'email',
                'description' => 'Default from name'
            ],
        ];

        foreach ($settings as $setting) {
            Setting::updateOrCreate(
                ['key' => $setting['key']],
                $setting
            );
        }
    }
}
