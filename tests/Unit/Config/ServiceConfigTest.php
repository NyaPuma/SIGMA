<?php

declare(strict_types=1);

namespace Tests\Unit\Config;

use Illuminate\Support\Env;
use PHPUnit\Framework\Attributes\Test;
use Tests\Base\FeatureTestCase;

/**
 * The application reads its tunables from `services.custom.*`, which is defined by
 * config/services.custom.php. Laravel loads config files sorted by name and merges
 * them with array_set(), so `services.php` is applied first and any `custom` tree
 * declared there is replaced wholesale — silently disabling AUTH_TOKEN_EXPIRY_DAYS
 * and the UPLOAD_* keys once.
 */
final class ServiceConfigTest extends FeatureTestCase
{
    #[Test]
    public function it_keeps_the_custom_tree_out_of_the_file_that_would_shadow_it(): void
    {
        $services = require base_path('config/services.php');

        $this->assertArrayNotHasKey('custom', $services);
    }

    #[Test]
    public function it_resolves_every_custom_key_the_application_reads(): void
    {
        $integerKeys = [
            'services.custom.auth.max_attempts',
            'services.custom.auth.lockout_minutes',
            'services.custom.auth.token_expiry_days',
            'services.custom.pagination.default_per_page',
            'services.custom.pagination.admin_per_page',
            'services.custom.tokens.length',
            'services.custom.upload.max_photo_size_kb',
            'services.custom.upload.max_photo_width',
            'services.custom.upload.max_photo_height',
        ];

        foreach ($integerKeys as $key) {
            $this->assertIsInt(config($key), "{$key} must resolve to an int, not the reader fallback");
            $this->assertGreaterThan(0, config($key), "{$key} must not be zero");
        }

        foreach (['services.custom.budget.threshold', 'services.custom.analytics.system_availability', 'services.custom.ai.temperature'] as $key) {
            $this->assertIsFloat(config($key), "{$key} must resolve to a float");
        }

        $this->assertNotEmpty(config('services.custom.ai.model'));
        $this->assertNotEmpty(config('services.custom.notification.mailer'));
        $mimes = config('services.custom.upload.allowed_photo_mimes');

        $this->assertIsArray($mimes);
        $this->assertNotEmpty($mimes, 'services.custom.upload.allowed_photo_mimes must reach the request validation');
        $this->assertContains('jpeg', $mimes);
    }

    #[Test]
    public function it_falls_back_to_a_model_name_when_the_environment_leaves_it_empty(): void
    {
        $repository = Env::getRepository();
        $previous = $repository->get('AI_MODEL');
        $repository->set('AI_MODEL', '');

        try {
            $custom = require base_path('config/services.custom.php');

            $this->assertSame('gpt-4o-mini', $custom['ai']['model']);
        } finally {
            if ($previous === null || $previous === false) {
                $repository->clear('AI_MODEL');
            } else {
                $repository->set('AI_MODEL', $previous);
            }
        }
    }
}
