<?php

namespace Tests\Unit;

use App\Services\AI\ReportExplainer;
use PHPUnit\Framework\TestCase;

class ReportExplainerTest extends TestCase
{
    private const METRICS = ['month' => '2026-09', 'income' => 61124, 'spending' => 38553.4, 'savings_rate_percent' => 37,
        'mase' => 0.61, 'chance_empty_within_7_days_percent' => 40, 'share' => 0.25];

    public function test_numbers_must_come_from_the_metrics()
    {
        $ok = 'In 2026-09 you earned 61,124 and spent 38,553 (37% kept). The model MASE is 0.61; 40% chance within 7 days; 25% share.';
        $this->assertSame([], ReportExplainer::unknownNumbers($ok, self::METRICS));

        $invented = 'You could save 15,000 more, about 90k a month; income is about 61k.';
        $this->assertSame(['15,000', '90k'], ReportExplainer::unknownNumbers($invented, self::METRICS), '61k is a fair rounding of 61,124; 90k is invented');
    }

    public function test_parse_accepts_fenced_json_and_rejects_short_answers()
    {
        $reply = "```json\n{\"insights\": [\"a\", \"b\", \"c\", \"d\", \"e\", \"f\"], \"actions\": [\"x\"]}\n```";
        $this->assertSame(['insights' => ['a', 'b', 'c', 'd', 'e'], 'actions' => ['x']], ReportExplainer::parse($reply));

        $this->expectException(\UnexpectedValueException::class);
        ReportExplainer::parse('{"insights": ["only one"], "actions": []}');
    }

    public function test_prompt_contains_only_the_given_metrics()
    {
        $p = ReportExplainer::prompt('executive', self::METRICS);
        $this->assertStringContainsString('"income": 61124', $p);
        $this->assertStringContainsString('Use ONLY the numbers', $p);
    }
}
