<?php

namespace App\Services\AI;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Turns a report's computed metrics into 3-5 plain-language insights and 1-3 actions.
 * The AI only explains: it receives aggregated metrics (no notes, names or account numbers),
 * and its answer is rejected unless every number in it appears in those metrics. Without a
 * provider, or on any failure/rejection, the rule-based template text is used instead.
 *
 * Providers (free tiers): AI_PROVIDER=gemini|groq|none, GEMINI_API_KEY / GROQ_API_KEY.
 */
class ReportExplainer
{
    /** @return array{insights: string[], actions: string[], source: string, model: ?string, rejected: ?string} */
    public static function explain(string $report, array $metrics, array $template): array
    {
        $provider = config('services.ai.provider', 'none');
        $fallback = $template + ['source' => 'template', 'model' => null, 'rejected' => null];
        if (!in_array($provider, ['gemini', 'groq'], true) || !config("services.ai.{$provider}_key")) {
            return $fallback;
        }
        $model = config("services.ai.{$provider}_model");
        try {
            $text = $provider === 'gemini' ? self::gemini(self::prompt($report, $metrics), $model) : self::groq(self::prompt($report, $metrics), $model);
            $answer = self::parse($text);
            $bad = self::unknownNumbers(implode(' ', array_merge($answer['insights'], $answer['actions'])), $metrics);
            if ($bad) {
                return ['rejected' => 'numbers not in the data: ' . implode(', ', array_slice($bad, 0, 5))] + $fallback;
            }
            return $answer + ['source' => $provider, 'model' => $model, 'rejected' => null];
        } catch (Throwable $e) {
            // The message can include the request URL; log only the class, never the key or data.
            Log::warning("AI explanation for $report failed: " . get_class($e));
            return ['rejected' => 'provider error: ' . get_class($e)] + $fallback;
        }
    }

    public static function prompt(string $report, array $metrics): string
    {
        return "You explain a personal finance report (currency BDT) to its owner.\n"
            . "Rules:\n"
            . "- Use ONLY the numbers in the JSON below. Do not calculate, estimate or invent any other number.\n"
            . "- Write each number as it appears or rounded to a whole number, with thousands separators allowed (e.g. 12,345). No 'k' or 'lakh'.\n"
            . "- Write 3 to 5 short insights and 1 to 3 concrete actions, in plain English.\n"
            . "- Reply with JSON only: {\"insights\": [\"...\"], \"actions\": [\"...\"]}\n\n"
            . "Report: $report\nData:\n" . json_encode($metrics, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }

    private static function gemini(string $prompt, string $model): string
    {
        $r = Http::timeout(config('services.ai.timeout', 20))->retry(2, 1000)
            ->withHeaders(['x-goog-api-key' => config('services.ai.gemini_key')]) // header, so the key never sits in a logged URL
            ->post("https://generativelanguage.googleapis.com/v1beta/models/$model:generateContent", [
                'contents' => [['parts' => [['text' => $prompt]]]],
                'generationConfig' => ['temperature' => 0.2, 'responseMimeType' => 'application/json'],
            ])->throw();
        return (string) $r->json('candidates.0.content.parts.0.text');
    }

    private static function groq(string $prompt, string $model): string
    {
        $r = Http::timeout(config('services.ai.timeout', 20))->retry(2, 1000)
            ->withToken(config('services.ai.groq_key'))
            ->post('https://api.groq.com/openai/v1/chat/completions', [
                'model' => $model,
                'temperature' => 0.2,
                'response_format' => ['type' => 'json_object'],
                'messages' => [['role' => 'user', 'content' => $prompt]],
            ])->throw();
        return (string) $r->json('choices.0.message.content');
    }

    /** The model's JSON reply -> ['insights' => 3-5 strings, 'actions' => 1-3 strings]; throws if malformed. */
    public static function parse(string $text): array
    {
        $json = json_decode(trim(preg_replace('/^```(json)?|```$/m', '', $text)), true);
        $strings = fn($v) => is_array($v) ? array_values(array_filter(array_map(fn($s) => is_string($s) ? trim($s) : '', $v))) : [];
        $insights = $strings($json['insights'] ?? null);
        $actions = $strings($json['actions'] ?? null);
        if (count($insights) < 3 || count($actions) < 1) {
            throw new \UnexpectedValueException('AI reply is not the expected JSON');
        }
        return ['insights' => array_slice($insights, 0, 5), 'actions' => array_slice($actions, 0, 3)];
    }

    /**
     * Numbers in $text that do not appear in $metrics. A match may be rounded (±1, or ±1% for
     * large values) or shown as a percentage of a ratio.
     */
    public static function unknownNumbers(string $text, array $metrics): array
    {
        // Every number in the JSON the AI was shown: values, dates and field names ('within_7_days').
        preg_match_all('/\d+(?:\.\d+)?/', json_encode($metrics), $m);
        $known = array_map('floatval', $m[0]);
        preg_match_all('/(?<![\w.])-?\d[\d,]*(?:\.\d+)?(?:\s?(k|lakh|lac)\b)?/i', $text, $m, PREG_SET_ORDER);
        $bad = [];
        foreach ($m as $match) {
            $raw = $match[0];
            $unit = ['k' => 1e3, 'lakh' => 1e5, 'lac' => 1e5][strtolower($match[1] ?? '')] ?? 1;
            $x = abs((float) str_replace(',', '', $raw)) * $unit;
            $ok = false;
            foreach ($known as $k) {
                foreach ([abs($k), abs($k) * 100] as $v) {
                    if (abs($x - $v) <= max(1, 0.01 * $v)) {
                        $ok = true;
                        break 2;
                    }
                }
            }
            if (!$ok) {
                $bad[] = $raw;
            }
        }
        return $bad;
    }
}
