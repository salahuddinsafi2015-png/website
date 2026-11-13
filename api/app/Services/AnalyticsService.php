<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;

/**
 * Google Analytics 4 reporting through the Data API.
 *
 * Reports are cached briefly so a dashboard reload never burns the API quota.
 */
class AnalyticsService
{
    private const API = 'https://analyticsdata.googleapis.com/v1beta/properties';

    public function __construct(private readonly GoogleApiClient $google) {}

    public function propertyId(): string
    {
        return trim((string) Setting::get('analytics.property_id', ''));
    }

    public function configured(): bool
    {
        return $this->propertyId() !== '' && $this->google->configured();
    }

    /**
     * @return array<string, mixed>
     */
    public function overview(int $days = 28): array
    {
        $days = max(1, min(365, $days));

        if ($this->propertyId() === '') {
            return $this->unconfigured('Add your GA4 property ID in Settings → Integrations.');
        }

        if (! $this->google->configured()) {
            return $this->unconfigured(
                'Add a Google service account key in Settings → Integrations, then share the GA4 property with it.',
            );
        }

        try {
            return Cache::remember(
                "insights.analytics.{$this->propertyId()}.{$days}",
                now()->addMinutes(5),
                fn (): array => $this->fetch($days),
            );
        } catch (\Throwable $exception) {
            return [
                'configured' => true,
                'error' => $exception->getMessage(),
                'range' => ['days' => $days],
            ];
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function fetch(int $days): array
    {
        $dateRanges = [[
            'startDate' => $days === 1 ? 'today' : '-'.($days - 1).'days',
            'endDate' => 'today',
        ]];

        $totals = $this->runReport([
            'metrics' => [
                ['name' => 'sessions'],
                ['name' => 'totalUsers'],
                ['name' => 'screenPageViews'],
                ['name' => 'averageSessionDuration'],
            ],
            'dateRanges' => $dateRanges,
            'limit' => 1,
        ]);

        $daily = $this->runReport([
            'dimensions' => [['name' => 'date']],
            'metrics' => [
                ['name' => 'sessions'],
                ['name' => 'totalUsers'],
                ['name' => 'screenPageViews'],
            ],
            'dateRanges' => $dateRanges,
            'orderBys' => [['dimension' => ['dimensionName' => 'date']]],
            'limit' => 400,
        ]);

        $pages = $this->runReport([
            'dimensions' => [['name' => 'pagePath']],
            'metrics' => [['name' => 'screenPageViews']],
            'dateRanges' => $dateRanges,
            'orderBys' => [['metric' => ['metricName' => 'screenPageViews', 'desc' => true]]],
            'limit' => 5,
        ]);

        $sources = $this->runReport([
            'dimensions' => [['name' => 'sessionDefaultChannelGroup']],
            'metrics' => [['name' => 'sessions']],
            'dateRanges' => $dateRanges,
            'orderBys' => [['metric' => ['metricName' => 'sessions', 'desc' => true]]],
            'limit' => 5,
        ]);

        $totalRow = $totals['rows'][0]['metricValues'] ?? [];

        return [
            'configured' => true,
            'property_id' => $this->propertyId(),
            'range' => ['days' => $days],
            'totals' => [
                'sessions' => (int) ($totalRow[0]['value'] ?? 0),
                'users' => (int) ($totalRow[1]['value'] ?? 0),
                'views' => (int) ($totalRow[2]['value'] ?? 0),
                'avg_session_seconds' => (float) ($totalRow[3]['value'] ?? 0),
            ],
            'series' => array_map(fn (array $row) => [
                'date' => $this->formatDate($row['dimensionValues'][0]['value'] ?? ''),
                'sessions' => (int) ($row['metricValues'][0]['value'] ?? 0),
                'users' => (int) ($row['metricValues'][1]['value'] ?? 0),
                'views' => (int) ($row['metricValues'][2]['value'] ?? 0),
            ], $daily['rows'] ?? []),
            'top_pages' => array_map(fn (array $row) => [
                'path' => $row['dimensionValues'][0]['value'] ?? '',
                'views' => (int) ($row['metricValues'][0]['value'] ?? 0),
            ], $pages['rows'] ?? []),
            'sources' => array_map(fn (array $row) => [
                'channel' => $row['dimensionValues'][0]['value'] ?? '',
                'sessions' => (int) ($row['metricValues'][0]['value'] ?? 0),
            ], $sources['rows'] ?? []),
            'generated_at' => now()->toIso8601String(),
        ];
    }

    /**
     * @param  array<string, mixed>  $report
     * @return array<string, mixed>
     */
    private function runReport(array $report): array
    {
        return $this->google->post(
            self::API.'/'.$this->propertyId().':runReport',
            $report,
            GoogleApiClient::ANALYTICS_SCOPE,
        );
    }

    private function formatDate(string $value): string
    {
        // GA4 returns YYYYMMDD; ISO-8601 keeps the series sortable client-side.
        return strlen($value) === 8
            ? substr($value, 0, 4).'-'.substr($value, 4, 2).'-'.substr($value, 6, 2)
            : $value;
    }

    /**
     * @return array<string, mixed>
     */
    private function unconfigured(string $reason): array
    {
        return [
            'configured' => false,
            'reason' => $reason,
        ];
    }
}
