<?php

declare(strict_types=1);

namespace App\Livewire\Manufacturing\Concerns;

trait StatsCacheVersion
{
    protected function statsCacheVersion($query): string
    {
        $lastUpdated = (clone $query)->max('updated_at');

        if (! $lastUpdated) {
            return 'none';
        }

        if (is_string($lastUpdated)) {
            $timestamp = strtotime($lastUpdated);

            return $timestamp !== false ? (string) $timestamp : md5($lastUpdated);
        }

        return (string) $lastUpdated->getTimestamp();
    }
}
