<?php

declare(strict_types=1);

namespace App\Exceptions\Api;

class TimeEntryEditWindowExpiredApiException extends ApiException
{
    public const string KEY = 'time_entry_edit_window_expired';
}
