<?php

namespace Portavice\FileSanitizer;

use Portavice\FileSanitizer\Contracts\SanitizerInterface;
use Portavice\FileSanitizer\Contracts\ScannerInterface;
use Portavice\FileSanitizer\Dto\Issue;
use Portavice\FileSanitizer\Dto\SanitizeReport;
use Portavice\FileSanitizer\Dto\ScanReport;
use Portavice\FileSanitizer\Enums\IssueSeverity;
use Portavice\FileSanitizer\Sanitizer\AudioSanitizer;
use Portavice\FileSanitizer\Sanitizer\HtmlSanitizer;
use Portavice\FileSanitizer\Sanitizer\ImageSanitizer;
use Portavice\FileSanitizer\Sanitizer\PdfSanitizer;
use Portavice\FileSanitizer\Sanitizer\SvgSanitizer;
use Portavice\FileSanitizer\Sanitizer\TextLikeSanitizer;
use Portavice\FileSanitizer\Sanitizer\VideoSanitizer;
use Portavice\FileSanitizer\Scanner\PatternScanner;
use Portavice\FileSanitizer\Support\MimeDetector;
use RuntimeException;

final class FileSanitizer
{
    /** @var list<SanitizerInterface> */
    private array $sanitizers;

    public function __construct(private readonly ?MimeDetector $mimeDetector = null, private readonly ?ScannerInterface $scanner = null, ?array $sanitizers = null)
    {
        $this->sanitizers = $sanitizers ?? [new SvgSanitizer(), new HtmlSanitizer(), new ImageSanitizer(), new PdfSanitizer(), new TextLikeSanitizer(), new AudioSanitizer(), new VideoSanitizer()];
    }

    /**
     * @return array{mimeType:string, scan:ScanReport, sanitize:SanitizeReport}
     */
    public function process(string $inputPath, bool|string|null $outputPath = null, bool $sanitizeAlways = false): array
    {
        if (is_bool($outputPath)) {
            $sanitizeAlways = $outputPath;
            $outputPath = null;
        }

        if (!is_file($inputPath)) {
            throw new RuntimeException(sprintf('Input file not found: %s', $inputPath));
        }

        $mimeType = ($this->mimeDetector ?? new MimeDetector())->detect($inputPath);
        $scan = ($this->scanner ?? new PatternScanner())->scan($inputPath, $mimeType);
        $outputPath ??= $this->defaultOutputPath($inputPath);
        if (!$scan->safe && !$sanitizeAlways) {
            return ['mimeType' => $mimeType, 'scan' => $scan, 'sanitize' => new SanitizeReport($outputPath, false, $scan->issues, ['skipped' => true])];
        }
        $sanitizer = $this->resolveSanitizer($mimeType, $inputPath);
        if ($sanitizer === null) {
            if (!copy($inputPath, $outputPath)) {
                throw new RuntimeException('Could not copy unsupported file to output path.');
            }
            $issues = $scan->issues;
            $issues[] = new Issue('no_sanitizer', 'No specialized sanitizer exists for this file type; original file was copied after scanning.', IssueSeverity::Warning);
            return ['mimeType' => $mimeType, 'scan' => $scan, 'sanitize' => new SanitizeReport($outputPath, false, $issues, ['copied_original' => true])];
        }
        $sanitize = $sanitizer->sanitize($inputPath, $outputPath, $sanitizeAlways);
        if (!$scan->safe) {
            $sanitize = new SanitizeReport($sanitize->outputPath, $sanitize->metadataRemoved, [...$scan->issues, ...$sanitize->issues], [...$sanitize->context, 'sanitized_despite_scan_issues' => true]);
        }
        return ['mimeType' => $mimeType, 'scan' => $scan, 'sanitize' => $sanitize];
    }

    public function sanitizeAlways(string $inputPath, ?string $outputPath = null): array
    {
        return $this->process($inputPath, $outputPath, true);
    }

    private function resolveSanitizer(string $mimeType, string $path): ?SanitizerInterface
    {
        foreach ($this->sanitizers as $sanitizer) {
            if ($sanitizer->supports($mimeType, $path)) {
                return $sanitizer;
            }
        }
        return null;
    }

    private function defaultOutputPath(string $inputPath): string
    {
        $extension = pathinfo($inputPath, PATHINFO_EXTENSION);
        return substr($inputPath, 0, -strlen($extension) - ($extension !== '' ? 1 : 0)) . '.sanitized' . ($extension !== '' ? '.' . $extension : '');
    }
}
