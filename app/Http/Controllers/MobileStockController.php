<?php

namespace App\Http\Controllers;

use App\Http\Middleware\MobileToken;
use App\Models\StockCash;
use App\Models\StockDividend;
use App\Models\StockSymbol;
use App\Models\StockTrade;
use App\Models\User;
use App\Services\StockAnalysis;
use App\Services\StockPortfolio;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

/**
 * Read-only API for the Android stock app. It serves what PCM has already saved:
 * nothing here may contact dse.com.bd (use StockAnalysis::cached, never warm/history/fundamentals).
 */
class MobileStockController extends Controller
{
    // Checked when the email is unknown, so response time does not reveal which emails exist.
    private const DUMMY_HASH = '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi';

    public function login(Request $request)
    {
        $v = Validator::make($request->only('email', 'password', 'device'), [
            'email' => 'required|string|email|max:255',
            'password' => 'required|string|max:255',
            'device' => 'nullable|string|max:80',
        ]);
        if ($v->fails()) return $this->json(['message' => 'Enter your email and password.'], 422);

        $user = User::where('email', $request->input('email'))->first();
        $match = Hash::check($request->input('password'), $user->password ?? self::DUMMY_HASH);
        if (!$user || !$match || (int) $user->is_active !== 1) {
            return $this->json(['message' => 'These details do not match an active account.'], 401);
        }
        if (!$user->can('Admin')) return $this->json(['message' => 'This account cannot use the stock app.'], 403);

        $token = $user->createToken('mobile:' . ($request->input('device') ?: 'android'), [MobileToken::ABILITY]);

        return $this->json(['token' => $token->plainTextToken, 'token_type' => 'Bearer',
            'expires_at' => $token->accessToken->created_at->addDays(MobileToken::DAYS)->toIso8601String(), 'user' => ['name' => $user->name]], 201);
    }

    public function logout(Request $request)
    {
        $request->attributes->get('mobile_token')->delete();

        return $this->json(['message' => 'Signed out.']);
    }

    public function status()
    {
        $parts = [];
        foreach ([StockTrade::class, StockSymbol::class, StockCash::class, StockDividend::class] as $model) {
            $row = $model::query()->toBase()->selectRaw('COUNT(*) c, MAX(updated_at) m')->first();
            $parts[] = $row->c . '|' . $row->m;
        }

        return $this->json(['server_time' => now()->toIso8601String(), 'price_date' => $this->priceDate(array_column(StockPortfolio::load()['holdings'], 'symbol')),
            'signature' => md5(implode(',', $parts))]);
    }

    public function portfolio(Request $request)
    {
        $target = (float) $request->query('target', 3) / 100;
        $p = StockPortfolio::load(null, in_array($target, StockPortfolio::LOT_TARGETS) ? $target : 0.03);
        $p['advice'] = StockPortfolio::advice($p)['template'];
        $p['symbols'] = StockSymbol::orderBy('symbol')->get(['symbol', 'company', 'category', 'price', 'prev_close', 'price_date']);
        $p['cash'] = StockCash::orderByDesc('date')->orderByDesc('id')->get(['id', 'date', 'type', 'particulars', 'cash_in', 'cash_out']);
        $p['dividends'] = StockDividend::orderByDesc('record_date')->get(['id', 'symbol', 'record_date', 'percent', 'qty', 'gross', 'tax', 'net', 'status', 'received_date']);
        $p['price_date'] = $this->priceDate(array_column($p['holdings'], 'symbol'));
        $p['lot_targets'] = StockPortfolio::LOT_TARGETS;

        return $this->json($p);
    }

    public function analysis(Request $request)
    {
        $held = collect(StockPortfolio::load()['holdings'])->keyBy('symbol');
        $symbols = $held->keys()->merge(StockAnalysis::watchlist(is_string($request->query('symbols')) ? $request->query('symbols') : null))->unique()->values()->all();
        $data = StockAnalysis::cached($symbols);
        $rows = [];
        $missing = [];
        foreach ($symbols as $s) {
            $bars = $data[$s]['bars'] ?: StockAnalysis::scanBars($s);
            $a = StockAnalysis::analyze($bars, $data[$s]['fund'], $this->holding($held->get($s)));
            if (!$a['ok']) {
                $missing[] = $s;
                continue;
            }
            unset($a['candles']); // sent only with one share's details
            $rows[] = ['symbol' => $s, 'held' => $held->has($s)] + $a;
        }
        usort($rows, fn($a, $b) => [!$a['held'], -$a['score']] <=> [!$b['held'], -$b['score']]);

        return $this->json(['state' => !$rows ? 'no_saved_data' : ($missing ? 'partial' : 'ok'), 'rows' => $rows, 'missing' => $missing,
            'message' => !$rows ? 'PCM has no saved analysis data today. Open Stock Analysis in PCM.' : null]);
    }

    public function analysisDetail(string $symbol)
    {
        $symbol = strtoupper($symbol);
        $d = StockAnalysis::cached([$symbol])[$symbol];
        $bars = $d['bars'] ?: StockAnalysis::scanBars($symbol);
        $h = collect(StockPortfolio::load()['holdings'])->firstWhere('symbol', $symbol);
        $a = StockAnalysis::analyze($bars, $d['fund'], $this->holding($h));
        if (!$a['ok']) {
            return $this->json(['state' => 'no_saved_data', 'symbol' => $symbol, 'message' => "PCM has no saved price history for {$symbol} today."]);
        }
        $calls = StockAnalysis::candleCalls($bars, $a['fund']);
        $advice = StockAnalysis::popupAdvice($symbol, $a, $calls);

        return $this->json(['state' => 'ok', 'symbol' => $symbol, 'analysis' => $a, 'calls' => (object) $calls, 'buy_ok' => $advice['buy_ok'],
            'advice' => ['insights' => $advice['template']['insights'], 'actions' => $advice['template']['actions']]]);
    }

    private function holding(?array $h): ?array
    {
        return $h ? ['qty' => $h['qty'], 'avg_cost' => $h['avg_cost']] : null;
    }

    private function priceDate(array $symbols): ?string
    {
        $date = DB::table('stock_symbols')->whereIn('symbol', $symbols)->max('price_date');

        return $date ? substr((string) $date, 0, 10) : null;
    }

    private function json(array $data, int $status = 200)
    {
        return response()->json($data, $status)->header('Cache-Control', 'no-store');
    }
}
