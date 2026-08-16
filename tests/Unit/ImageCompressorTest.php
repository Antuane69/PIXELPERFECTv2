<?php

namespace Tests\Unit;

use App\Services\ImageCompressor;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class ImageCompressorTest extends TestCase
{
    public function test_it_resizes_and_compresses_supported_images(): void
    {
        config(['media.images.max_dimension' => 2400]);

        $image = UploadedFile::fake()->image('wide.jpg', 3000, 1500);
        $compressed = (new ImageCompressor)->compressIfImage($image);

        $this->assertNotNull($compressed);
        $this->assertSame('image/jpeg', $compressed['mime_type']);
        $this->assertSame('jpg', $compressed['extension']);

        $decodedImage = imagecreatefromstring($compressed['contents']);

        $this->assertInstanceOf(\GdImage::class, $decodedImage);
        $this->assertSame(2400, imagesx($decodedImage));
        $this->assertSame(1200, imagesy($decodedImage));
        $this->assertNotSame('', $compressed['contents']);
        imagedestroy($decodedImage);
    }

    public function test_it_ignores_non_image_files(): void
    {
        $document = UploadedFile::fake()->create('document.pdf', 20, 'application/pdf');

        $this->assertNull((new ImageCompressor)->compressIfImage($document));
    }
}
