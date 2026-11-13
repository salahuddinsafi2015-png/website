<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;

/**
 * AdSense earnings and traffic through the AdSense Management API.
 */
class AdSenseService
{
    private const API = 'https://adsense.googleapis.com/v1';

    public function __construct(private readonly GoogleApiClient $google) {}

    public function account(): string
    {
        $account = trim((string) Setting::get('adsense.account', ''));

        if ($account === '') {
            return '';
        }

        return str_contains($account, '/')
            ? $account
            : 'accounts/'.preg_replace('/^accounts\//', '', $account);
    }

    public function configured(): bool
    {
        return $this->account() !== '' && $this->google->configured();
    }

    /**
     * @return array<string, mixed>
     */
    public function overview(int $days = 28): array
    {
        $days = max(1, min(365, $days));

        if ($this->account() === '') {
            return $this->unconfigured(
                'Add your AdSense account (accounts/pub-…) in Settings → Integrations.',
            );
        }

        if (! $this->google->configured()) {
            return $this->unconfigured(
                'Add a Google service account key in Settings → Integrations, then grant it AdSense access.',
            );
        }

        try {
            return Cache::remember(
                "insights.adsense.{$this->account()}.{$days}",
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
        $metrics = [
            'ESTIMATED_EARNINGS',
            'PAGE_VIEWS',
            'IMPRESSIONS',
            'CLICKS',
            'PAGE_VIEWS_RPM',
            'IMPRESSIONS_CTR',
        ];

        $end = now()->toDateString();
        $start = now()->subDays($days - 1)->toDateString();

        $report = $this->google->get(
            self::API.'/'.$this->account().'/reports',
            [
                'dateRange' => 'CUSTOM',
                'startDate.year' => (int) substr($start, 0, 4),
                'startDate.month' => (int) substr($start, 5, 2),
                'startDate.day' => (int) substr($start, 8, 2),
                'endDate.year' => (int) substr($end, 0, 4),
                'endDate.month' => (int) substr($end, 5, 2),
                'endDate.day' => (int) substr($end, 8, 2),
                'dimensions' => ['DATE'],
                'metrics' => $metrics,
                'orderBy' => ['DATE'],
            ],
            GoogleApiClient::ADSENSE_SCOPE,
        );

        $headers = array_map(
            fn (array $header) => (string) ($header['name'] ?? ''),
            $report['headers'] ?? [],
        );

        return [
            'configured' => true,
            'account' => $this->account(),
            'currency' => $this->currency($report['headers'] ?? []),
            'range' => ['days' => $days],
            'totals' => $this->totals($report['totals']['cells'] ?? [], $headers),
            'series' => array_map(
                fn (array $row) => $this->seriesPoint($row['cells'] ?? [], $headers),
                $report['rows'] ?? [],
            ),
            'generated_at' => now()->toIso8601String(),
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $cells
     * @param  array<int, string>  $headers
     * @return array<string, float|int>
     */
    private function totals(array $cells, array $headers): array
    {
        $metricHeaders = array_values(array_filter($headers, fn (string $name) => $name !== 'DATE'));
        $values = [];

        // Totals carry metric cells only; dimension headers are not included.
        foreach ($cells as $index => $cell) {
            $values[$metricHeaders[$index] ?? "metric_{$index}"] = $cell['value'] ?? 0;
        }

        return [
            'earnings' => round((float) ($values['ESTIMATED_EARNINGS'] ?? 0), 2),
            'page_views' => (int) ($values['PAGE_VIEWS'] ?? 0),
            'impressions' => (int) ($values['IMPRESSIONS'] ?? 0),
            'clicks' => (int) ($values['CLICKS'] ?? 0),
            'rpm' => round((float) ($values['PAGE_VIEWS_RPM'] ?? 0), 2),
            'ctr' => round((float) ($values['IMPRESSIONS_CTR'] ?? 0) * 100, 2),
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $cells
     * @param  array<int, string>  $headers
     * @return array<string, mixed>
     */
    private function seriesPoint(array $cells, array $headers): array
    {
        $values = [];

        foreach ($cells as $index => $cell) {
            $values[$headers[$index] ?? "field_{$index}"] = $cell['value'] ?? 0;
        }

        return [
            'date' => (string) ($values['DATE'] ?? ''),
            'earnings' => round((float) ($values['ESTIMATED_EARNINGS'] ?? 0), 2),
            'page_views' => (int) ($values['PAGE_VIEWS'] ?? 0),
            'impressions' => (int) ($values['IMPRESSIONS'] ?? 0),
            'clicks' => (int) ($values['CLICKS'] ?? 0),
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $headers
     */
    private function currency(array $headers): string
    {
        foreach ($headers as $header) {
            if (! empty($header['currencyCode'])) {
                return (string) $header['currencyCode'];
            }
        }

        return '';
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
