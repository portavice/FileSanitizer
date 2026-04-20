<?php

namespace Portavice\FileSanitizer\Contracts;

use Portavice\FileSanitizer\Dto\ScanReport;

interface ScannerInterface
{
    public function scan(string $path, string $mimeType): ScanReport;
}
