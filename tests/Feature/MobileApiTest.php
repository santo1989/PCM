<?php

namespace Tests\Feature;

use App\Models\StockSymbol;
use App\Models\StockTrade;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** Made-up data only: this repository is public. */
class MobileApiTest extends TestCase
{
    use RefreshDatabase;

    private function admin(array $extra = []): User
    {
        return User::factory()->create(['role_id' => 1] + $extra);
    }

    private function token(User $user): string
    {
        return $this->postJson('/api/mobile/login', ['email' => $user->email, 'password' => 'password', 'device' => 'test'])->assertCreated()->json('token');
    }

    private function seedShare(): void
    {
        StockSymbol::create(['symbol' => 'AAA', 'company' => 'Sample Co', 'category' => 'A', 'price' => 110]);
        StockTrade::create(['date' => '2026-01-04', 'side' => 'Buy', 'symbol' => 'AAA', 'market' => 'Public', 'qty' => 10, 'rate' => 100, 'commission' => 4]);
    }

    private function bars(): array
    {
        $bars = [];
        for ($i = 0; $i < 40; $i++) {
            $p = 100 + $i * 0.2 + ($i % 3 ? 0.3 : -0.3);
            $bars[] = ['date' => now()->subDays(40 - $i)->toDateString(), 'open' => $p - 0.3, 'high' => $p + 0.4, 'low' => $p - 0.5, 'close' => $p, 'volume' => 1000 + $i * 50];
        }
        return $bars;
    }

    public function test_login_stores_only_a_hash_and_gives_read_access()
    {
        $this->seedShare();
        $token = $this->token($this->admin());

        $this->assertNotSame(explode('|', $token)[1], DB::table('personal_access_tokens')->value('token'));
        $this->withToken($token)->getJson('/api/mobile/portfolio?target=5')->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertJsonPath('lot_summary.target', 0.05)
            ->assertJsonStructure(['today', 'totals', 'holdings', 'lots', 'lot_summary', 'realized', 'trades', 'advice', 'symbols', 'cash', 'dividends', 'price_date', 'lot_targets']);
        $this->withToken($token)->getJson('/api/mobile/status')->assertOk()->assertJsonStructure(['server_time', 'price_date', 'signature']);
    }

    public function test_symbols_given_as_a_list_does_not_crash()
    {
        $token = $this->token($this->admin());
        $this->withToken($token)->getJson('/api/mobile/analysis?symbols[]=AAA')->assertOk();
    }

    public function test_login_limit_counts_per_email_across_addresses()
    {
        $user = $this->admin();
        for ($i = 0; $i < 20; $i++) {
            $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.' . $i])->postJson('/api/mobile/login', ['email' => $user->email, 'password' => 'wrong'])->assertStatus(401);
        }
        $this->withServerVariables(['REMOTE_ADDR' => '10.0.1.1'])->postJson('/api/mobile/login', ['email' => $user->email, 'password' => 'wrong'])->assertStatus(429);
        // another email from a fresh address is not blocked
        $this->withServerVariables(['REMOTE_ADDR' => '10.0.1.2'])->postJson('/api/mobile/login', ['email' => 'other@example.test', 'password' => 'wrong'])->assertStatus(401);
    }

    public function test_wrong_password_and_switched_off_user_cannot_sign_in()
    {
        $user = $this->admin();
        $this->postJson('/api/mobile/login', ['email' => $user->email, 'password' => 'wrong'])->assertStatus(401);
        $off = $this->admin(['is_active' => 0]);
        $this->postJson('/api/mobile/login', ['email' => $off->email, 'password' => 'password'])->assertStatus(401);
    }

    public function test_user_switched_off_later_loses_access()
    {
        $user = $this->admin();
        $token = $this->token($user);
        $user->update(['is_active' => 0]);

        $this->withToken($token)->getJson('/api/mobile/status')->assertStatus(401);
    }

    public function test_no_wrong_or_expired_token_is_refused()
    {
        $this->getJson('/api/mobile/portfolio')->assertStatus(401);
        $this->withToken('1|wrong')->getJson('/api/mobile/portfolio')->assertStatus(401);
        $token = $this->token($this->admin());
        DB::table('personal_access_tokens')->update(['created_at' => now()->subDays(91)]);

        $this->withToken($token)->getJson('/api/mobile/portfolio')->assertStatus(401);
    }

    public function test_a_token_for_another_purpose_is_refused()
    {
        $token = $this->admin()->createToken('other')->plainTextToken;

        $this->withToken($token)->getJson('/api/mobile/portfolio')->assertStatus(401);
    }

    public function test_another_user_cannot_see_the_portfolio()
    {
        $this->seedShare();
        $other = User::factory()->create(['role_id' => 2]);
        $this->postJson('/api/mobile/login', ['email' => $other->email, 'password' => 'password'])->assertStatus(403);
        $token = $other->createToken('mobile:x', ['mobile:read'])->plainTextToken;

        $this->withToken($token)->getJson('/api/mobile/portfolio')->assertStatus(403)->assertJsonMissing(['symbol' => 'AAA']);
    }

    public function test_logout_revokes_the_token()
    {
        $token = $this->token($this->admin());
        $this->withToken($token)->postJson('/api/mobile/logout')->assertOk();
        $this->app['auth']->forgetGuards();

        $this->withToken($token)->getJson('/api/mobile/status')->assertStatus(401);
    }

    public function test_analysis_uses_saved_data_and_never_calls_dse()
    {
        Http::fake();
        $this->seedShare();
        $token = $this->token($this->admin());

        $this->withToken($token)->getJson('/api/mobile/analysis?symbols=BBB')->assertOk()->assertJsonPath('state', 'no_saved_data');
        $this->withToken($token)->getJson('/api/mobile/analysis/AAA')->assertOk()->assertJsonPath('state', 'no_saved_data');

        Cache::put('dse_hist_AAA_' . now()->toDateString(), $this->bars(), 3600);
        Cache::put('dse_fund_AAA', ['symbol' => 'AAA', 'category' => 'Z', 'nav' => 5], 3600);
        $list = $this->withToken($token)->getJson('/api/mobile/analysis?symbols=BBB')->assertOk()->assertJsonPath('state', 'partial')
            ->assertJsonPath('rows.0.symbol', 'AAA')->assertJsonPath('rows.0.verdict', 'AVOID')->assertJsonPath('missing', ['BBB']);
        $this->assertArrayNotHasKey('candles', $list->json('rows.0'));
        $detail = $this->withToken($token)->getJson('/api/mobile/analysis/aaa')->assertOk()->assertJsonPath('state', 'ok')->assertJsonPath('buy_ok', false)
            ->assertJsonStructure(['analysis' => ['candles', 'plan', 'fund'], 'calls', 'advice' => ['insights', 'actions']]);
        $this->assertStringContainsString('do not buy today', strtolower($detail->json('advice.actions.0')));

        Http::assertNothingSent();
    }

    public function test_login_is_limited_to_five_tries_a_minute()
    {
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/mobile/login', ['email' => 'nobody@example.com', 'password' => 'wrong'])->assertStatus(401);
        }
        $this->postJson('/api/mobile/login', ['email' => 'nobody@example.com', 'password' => 'wrong'])->assertStatus(429);
    }
}
