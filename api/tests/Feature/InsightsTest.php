<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class InsightsTest extends TestCase
{
    use RefreshDatabase;

    protected string $credentialsJson = '';

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        Cache::flush();

        $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        $this->assertNotFalse($key);
        openssl_pkey_export($key, $privateKey);

        $this->credentialsJson = json_encode([
            'type' => 'service_account',
            'client_email' => 'cms@example.iam.gserviceaccount.com',
            'private_key' => $privateKey,
        ], JSON_THROW_ON_ERROR);
    }

    protected function userWithRole(string $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    protected function configureAnalytics(): void
    {
        Setting::set('analytics.property_id', '100200300', ['group' => 'analytics']);
        Setting::set('analytics.credentials_json', $this->credentialsJson, [
            'group' => 'analytics',
            'type' => 'string',
        ]);
    }

    protected function fakeGoogle(array $analyticsResponses = [], array $adsenseResponse = []): void
    {
        $sequence = Http::sequence();

        foreach ($analyticsResponses as $response) {
            $sequence->push($response);
        }

        Http::fake([
            'oauth2.googleapis.com/*' => Http::response([
                'access_token' => 'fake-access-token',
                'expires_in' => 3600,
                'token_type' => 'Bearer',
            ]),
            'analyticsdata.googleapis.com/*' => $analyticsResponses
                ? $sequence
                : Http::response(['rows' => []]),
            'adsense.googleapis.com/*' => Http::response($adsenseResponse ?: ['rows' => []]),
        ]);
    }

    public function test_unconfigured_insights_report_the_setup_state(): void
    {
        Sanctum::actingAs($this->userWithRole('administrator'));

        $this->getJson('/api/v1/admin/insights/analytics')
            ->assertOk()
            ->assertJsonPath('data.configured', false)
            ->assertJsonPath('data.reason', 'Add your GA4 property ID in Settings → Integrations.');

        $this->getJson('/api/v1/admin/insights/adsense')
            ->assertOk()
            ->assertJsonPath('data.configured', false);
    }

    public function test_analytics_reports_totals_series_and_breakdowns(): void
    {
        $this->configureAnalytics();
        Sanctum::actingAs($this->userWithRole('administrator'));

        $this->fakeGoogle([
            ['rows' => [['metricValues' => [
                ['value' => '1200'], ['value' => '640'], ['value' => '3100'], ['value' => '95.4'],
            ]]]],
            ['rows' => [
                ['dimensionValues' => [['value' => '20261101']], 'metricValues' => [
                    ['value' => '40'], ['value' => '25'], ['value' => '110'],
                ]],
                ['dimensionValues' => [['value' => '20261102']], 'metricValues' => [
                    ['value' => '55'], ['value' => '30'], ['value' => '120'],
                ]],
            ]],
            ['rows' => [
                ['dimensionValues' => [['value' => '/posts/hello']], 'metricValues' => [['value' => '900']]],
            ]],
            ['rows' => [
                ['dimensionValues' => [['value' => 'Organic Search']], 'metricValues' => [['value' => '700']]],
            ]],
        ]);

        $response = $this->getJson('/api/v1/admin/insights/analytics?days=28')
            ->assertOk()
            ->assertJsonPath('data.configured', true)
            ->assertJsonPath('data.totals.sessions', 1200)
            ->assertJsonPath('data.totals.users', 640)
            ->assertJsonPath('data.totals.views', 3100)
            ->assertJsonPath('data.totals.avg_session_seconds', 95.4)
            ->assertJsonPath('data.series.0.date', '2026-11-01')
            ->assertJsonPath('data.series.1.sessions', 55)
            ->assertJsonPath('data.top_pages.0.path', '/posts/hello')
            ->assertJsonPath('data.sources.0.channel', 'Organic Search');

        // The report must be requested against the configured property with a bearer token.
        Http::assertSent(function ($request) {
            return str_contains($request->url(), '/v1beta/properties/100200300:runReport')
                && $request->hasHeader('Authorization', 'Bearer fake-access-token');
        });
    }

    public function test_adsense_reports_earnings_and_traffic(): void
    {
        Setting::set('adsense.account', 'accounts/pub-1234567890123456', ['group' => 'adsense']);
        Setting::set('analytics.credentials_json', $this->credentialsJson, [
            'group' => 'analytics',
            'type' => 'string',
        ]);
        Sanctum::actingAs($this->userWithRole('administrator'));

        $this->fakeGoogle([], [
            'headers' => [
                ['name' => 'DATE', 'type' => 'DIMENSION'],
                ['name' => 'ESTIMATED_EARNINGS', 'type' => 'STRING', 'currencyCode' => 'USD'],
                ['name' => 'PAGE_VIEWS', 'type' => 'INTEGER'],
                ['name' => 'IMPRESSIONS', 'type' => 'INTEGER'],
                ['name' => 'CLICKS', 'type' => 'INTEGER'],
                ['name' => 'PAGE_VIEWS_RPM', 'type' => 'STRING'],
                ['name' => 'IMPRESSIONS_CTR', 'type' => 'STRING'],
            ],
            'totals' => ['cells' => [
                ['value' => '12.34'], ['value' => '1500'], ['value' => '4200'], ['value' => '18'],
                ['value' => '8.23'], ['value' => '0.0043'],
            ]],
            'rows' => [
                ['cells' => [
                    ['value' => '2026-11-01'], ['value' => '1.20'], ['value' => '200'],
                    ['value' => '500'], ['value' => '3'], ['value' => '8.23'], ['value' => '0.0043'],
                ]],
            ],
        ]);

        $this->getJson('/api/v1/admin/insights/adsense?days=7')
            ->assertOk()
            ->assertJsonPath('data.configured', true)
            ->assertJsonPath('data.currency', 'USD')
            ->assertJsonPath('data.totals.earnings', 12.34)
            ->assertJsonPath('data.totals.page_views', 1500)
            ->assertJsonPath('data.totals.clicks', 18)
            ->assertJsonPath('data.totals.ctr', 0.43)
            ->assertJsonPath('data.series.0.date', '2026-11-01');
    }

    public function test_invalid_credentials_surface_a_readable_error(): void
    {
        Setting::set('analytics.property_id', '123', ['group' => 'analytics']);
        Setting::set('analytics.credentials_json', '{"client_email":"x@example.com","private_key":"not-a-key"}', [
            'group' => 'analytics',
            'type' => 'string',
        ]);
        Sanctum::actingAs($this->userWithRole('administrator'));

        Http::preventStrayRequests();

        $this->getJson('/api/v1/admin/insights/analytics')
            ->assertOk()
            ->assertJsonPath('data.configured', true)
            ->assertJsonPath('data.error', 'The service account private key could not sign a token.');
    }

    public function test_editors_can_view_insights_but_subscribers_cannot(): void
    {
        Sanctum::actingAs($this->userWithRole('subscriber'));
        $this->getJson('/api/v1/admin/insights/analytics')->assertForbidden();
        $this->getJson('/api/v1/admin/insights/adsense')->assertForbidden();

        Sanctum::actingAs($this->userWithRole('editor'));
        $this->getJson('/api/v1/admin/insights/analytics')->assertOk();
    }

    public function test_public_settings_expose_tracking_ids_but_never_credentials(): void
    {
        Setting::set('analytics.measurement_id', 'G-TEST123', ['group' => 'analytics', 'is_public' => true]);
        Setting::set('analytics.credentials_json', $this->credentialsJson, [
            'group' => 'analytics',
            'type' => 'string',
        ]);

        $payload = $this->getJson('/api/v1/settings')->assertOk()->json('data');

        $this->assertSame('G-TEST123', $payload['analytics.measurement_id'] ?? null);
        $this->assertArrayNotHasKey('analytics.credentials_json', $payload);
    }
}
