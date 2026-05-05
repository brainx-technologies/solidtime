<?php

declare(strict_types=1);

namespace App\Exceptions\Api;

class PdfExportRowLimitExceededApiException extends ApiException
{
    public const string KEY = 'pdf_export_row_limit_exceeded';
}
