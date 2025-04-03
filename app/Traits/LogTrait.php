<?php

namespace App\Traits;

use Illuminate\Support\Facades\Log;

trait LogTrait
{
    protected function logInfo(string $message, array $context = [])
    {
        Log::info($message, array_merge($context, [
            'class' => get_class($this),
            'method' => debug_backtrace()[1]['function']
        ]));
    }

    protected function logError(string $message, \Exception $exception, array $context = [])
    {
        Log::error($message, array_merge($context, [
            'class' => get_class($this),
            'method' => debug_backtrace()[1]['function'],
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString()
        ]));
    }

    protected function logWarning(string $message, array $context = [])
    {
        Log::warning($message, array_merge($context, [
            'class' => get_class($this),
            'method' => debug_backtrace()[1]['function']
        ]));
    }
} 