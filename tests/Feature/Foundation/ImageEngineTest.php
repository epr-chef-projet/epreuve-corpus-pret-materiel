<?php

declare(strict_types=1);

namespace Tests\Feature\Foundation;

use Imagick;
use ImagickException;
use Intervention\Image\Drivers\Imagick\Driver;
use Intervention\Image\Encoders\WebpEncoder;
use Intervention\Image\ImageManager;
use Tests\TestCase;

final class ImageEngineTest extends TestCase
{
    public function test_imagick_driver_encodes_local_webp_variants_without_enlargement(): void
    {
        $native = new Imagick;
        $native->newImage(1600, 800, 'white');
        $native->setImageFormat('PNG');
        $native->setImageProperty('comment', 'private-metadata-probe');
        $bytes = $native->getImageBlob();
        $manager = new ImageManager(Driver::class, autoOrientation: true, decodeAnimation: false, strip: true);
        foreach ([320 => 160, 1280 => 640] as $width => $height) {
            $image = $manager->decodeBinary($bytes)->scaleDown(width: $width);
            $encoded = (string) $image->encode(new WebpEncoder(quality: 80));
            $check = new Imagick;
            $check->readImageBlob($encoded);
            $this->assertSame('WEBP', $check->getImageFormat());
            $this->assertSame($width, $check->getImageWidth());
            $this->assertSame($height, $check->getImageHeight());
            $this->assertSame([], $check->getImageProperties('comment'));
        }
        $small = $manager->createImage(100, 50)->scaleDown(width: 320);
        $this->assertSame(100, $small->width());
        $this->assertSame(50, $small->height());
    }

    public function test_svg_decoder_is_refused_by_the_actual_coder_policy(): void
    {
        $this->expectException(ImagickException::class);
        (new Imagick)->readImageBlob('<svg xmlns="http://www.w3.org/2000/svg" width="10" height="10"><rect width="10" height="10"/></svg>');
    }

    public function test_configured_memory_map_disk_and_thread_budgets_are_effective(): void
    {
        $this->assertLessThanOrEqual(128 * 1024 * 1024, Imagick::getResourceLimit(Imagick::RESOURCETYPE_MEMORY));
        $this->assertLessThanOrEqual(128 * 1024 * 1024, Imagick::getResourceLimit(Imagick::RESOURCETYPE_MAP));
        $this->assertLessThanOrEqual(256 * 1024 * 1024, Imagick::getResourceLimit(Imagick::RESOURCETYPE_DISK));
        $this->assertSame(
            1.0,
            (float) Imagick::getResourceLimit(Imagick::RESOURCETYPE_THREAD),
        );
    }
}
