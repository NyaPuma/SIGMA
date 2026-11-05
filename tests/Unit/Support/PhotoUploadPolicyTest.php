<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Support\PhotoUploadPolicy;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\Base\UnitTestCase;

final class PhotoUploadPolicyTest extends UnitTestCase
{
    #[Test]
    public function it_reads_the_limits_from_configuration(): void
    {
        config([
            'services.custom.upload.allowed_photo_mimes' => ['png', 'webp'],
            'services.custom.upload.max_photo_size_kb' => 512,
            'services.custom.upload.max_photo_width' => 1200,
            'services.custom.upload.max_photo_height' => 1600,
        ]);

        $this->assertSame(['png', 'webp'], PhotoUploadPolicy::allowedTypes());
        $this->assertSame(512, PhotoUploadPolicy::maxSizeKb());
        $this->assertSame(1200, PhotoUploadPolicy::maxWidth());
        $this->assertSame(1600, PhotoUploadPolicy::maxHeight());
    }

    /**
     * @return array<string, array{mixed}>
     */
    public static function unusableConfigurations(): array
    {
        return [
            'null' => [null],
            'zero' => [0],
            'negative' => [-1],
            'blank string' => [''],
            'text' => ['lots'],
            'float below one' => [0.5],
        ];
    }

    #[Test]
    #[DataProvider('unusableConfigurations')]
    public function it_keeps_a_usable_limit_when_configuration_is_bogus(mixed $configured): void
    {
        config(['services.custom.upload.max_photo_size_kb' => $configured]);

        $this->assertSame(2048, PhotoUploadPolicy::maxSizeKb());
    }

    /**
     * @return array<string, array{mixed, list<string>}>
     */
    public static function typeConfigurations(): array
    {
        return [
            'empty list' => [[], ['jpeg', 'jpg', 'png', 'gif', 'webp']],
            'not an array' => ['nope', ['jpeg', 'jpg', 'png', 'gif', 'webp']],
            'null' => [null, ['jpeg', 'jpg', 'png', 'gif', 'webp']],
            'only blanks' => [['', '  '], ['jpeg', 'jpg', 'png', 'gif', 'webp']],
            'trimmed and lowercased' => [[' PNG ', 'WEBP'], ['png', 'webp']],
            'duplicates' => [['jpg', 'jpg', 'png'], ['jpg', 'png']],
            'non strings' => [['png', 42, null], ['png']],
        ];
    }

    #[Test]
    #[DataProvider('typeConfigurations')]
    public function it_always_returns_a_usable_type_list(mixed $configured, array $expected): void
    {
        config(['services.custom.upload.allowed_photo_mimes' => $configured]);

        $this->assertSame($expected, PhotoUploadPolicy::allowedTypes());
    }

    /**
     * @return array<string, array{string|null, string}>
     */
    public static function detectedMimeTypes(): array
    {
        return [
            'jpeg' => ['image/jpeg', 'jpg'],
            'png' => ['image/png', 'png'],
            'gif' => ['image/gif', 'gif'],
            'webp' => ['image/webp', 'webp'],
            'avif' => ['image/avif', 'avif'],
            'unlisted image keeps its subtype' => ['image/tiff-hi', 'tiffhi'],
            'structured suffix' => ['image/svg+xml', 'svgxml'],
            'not an image is neutral' => ['application/x-php', 'img'],
            'empty subtype' => ['image/', 'img'],
            'undetectable mime is neutral' => [null, 'img'],
        ];
    }

    #[Test]
    #[DataProvider('detectedMimeTypes')]
    public function it_names_a_stored_file_after_its_real_content(?string $mime, string $expected): void
    {
        $this->assertSame($expected, PhotoUploadPolicy::extensionFor($mime));
    }
}
