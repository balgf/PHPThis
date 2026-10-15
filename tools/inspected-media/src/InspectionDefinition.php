<?php

declare(strict_types=1);

namespace CatalogImageRecipe;

final readonly class InspectionDefinition
{
    private function __construct(public string $image, public string $value, public string $sourceFingerprint) {}

    /** @return list<string> */
    public static function sources(): array
    {
        return [
            'inspection-policy.json', 'Dockerfile', 'inspector.php', 'bootstrap.php', 'console.php', 'public/index.php',
            'src/Failures.php', 'src/BoundedPng.php', 'src/PngHeader.php', 'src/ImageId.php',
            'src/CatalogPrincipal.php', 'src/CatalogClock.php', 'src/ImageRecord.php', 'src/CatalogRoot.php',
            'src/CatalogLock.php', 'src/PngInspector.php', 'src/PendingImageUpload.php', 'src/CatalogSettings.php',
            'src/CatalogImages.php', 'src/InspectionDefinition.php', 'src/DockerPngInspector.php',
            'src/Configuration.php', 'src/ReferenceEnvironment.php', 'src/CatalogPolicy.php',
            'src/UploadCatalogImage.php', 'src/DownloadCatalogImage.php', 'src/CatalogHttp.php',
        ];
    }

    public static function fromImageId(mixed $image, string $sourceRoot): self
    {
        if (!is_string($image) || preg_match('/\Asha256:[0-9a-f]{64}\z/D', $image) !== 1) {
            throw new \InvalidArgumentException('An immutable inspector image is required.');
        }
        $fingerprint = self::sourceFingerprint($sourceRoot);
        return new self($image, hash('sha256', $image . "\n" . $fingerprint), $fingerprint);
    }

    public static function sourceFingerprint(string $sourceRoot): string
    {
        $context = hash_init('sha256');
        foreach (self::sources() as $path) {
            $digest = @hash_file('sha256', $sourceRoot . '/' . $path);
            if (!is_string($digest)) { throw new CatalogUnavailable('Inspection definition incomplete.'); }
            hash_update($context, $path . ' ' . $digest . "\n");
        }
        return hash_final($context);
    }
}
