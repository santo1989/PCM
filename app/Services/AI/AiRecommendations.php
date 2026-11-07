<?php

namespace App\Services\AI;

use Illuminate\Support\Facades\DB;

/**
 * Free-AI recommendations for any page: the page passes its computed metrics plus rule-based
 * insights/actions (shown instantly), and ReportExplainer rewrites them with Gemini/Groq in a
 * follow-up request (resolve()), so a slow or failing AI never delays the page. One answer is
 * cached per page and set of numbers, so the AI is asked again only when the figures change.
 * The same number check applies: an AI answer quoting anything not in the metrics is discarded.
 */
class AiRecommendations
{
    // shortcut: one cached row per page per change of its figures, never pruned; delete run_id='pages' rows older than a few months if the table grows large.
    private const RUN = 'pages'; // analytics_insights.run_id for page recommendations

    /** @return array{id: int, pending: bool, insights: string[], actions: string[], source: string, model: ?string, rejected: ?string} */
    public static function get(string $page, array $metrics, array $template): array
    {
        $report = substr($page, 0, 17) . ':' . substr(md5(json_encode($metrics)), 0, 12);
        $row = DB::table('analytics_insights')->where('run_id', self::RUN)->where('report', $report)->first();
        if (!$row) {
            $provider = config('services.ai.provider', 'none');
            $aiOn = in_array($provider, ['gemini', 'groq'], true) && config("services.ai.{$provider}_key");
            $id = DB::table('analytics_insights')->insertGetId(['run_id' => self::RUN, 'report' => $report, 'source' => 'template', 'model' => null,
                'metrics' => json_encode(['page' => $page, 'metrics' => $metrics, 'template' => $template]),
                'insights' => json_encode(array_values($template['insights'])), 'actions' => json_encode(array_values($template['actions'])),
                'rejected' => $aiOn ? 'pending' : null, 'created_at' => now(), 'updated_at' => now()]);
            $row = DB::table('analytics_insights')->find($id);
        }

        return self::shape($row);
    }

    /** Ask the AI for a pending page recommendation (called by the page in the background). */
    public static function resolve(int $id): ?array
    {
        $row = DB::table('analytics_insights')->where('run_id', self::RUN)->find($id);
        if (!$row) return null;
        if ($row->rejected === 'pending') {
            $in = json_decode($row->metrics, true);
            $e = ReportExplainer::explain($in['page'], $in['metrics'], $in['template']);
            DB::table('analytics_insights')->where('id', $id)->update(['source' => $e['source'], 'model' => $e['model'], 'rejected' => $e['rejected'],
                'insights' => json_encode($e['insights']), 'actions' => json_encode($e['actions']), 'updated_at' => now()]);
            $row = DB::table('analytics_insights')->find($id);
        }

        return self::shape($row);
    }

    private static function shape(object $row): array
    {
        return ['id' => $row->id, 'pending' => $row->rejected === 'pending', 'source' => $row->source, 'model' => $row->model,
            'rejected' => $row->rejected === 'pending' ? null : $row->rejected,
            'insights' => json_decode($row->insights, true), 'actions' => json_decode($row->actions, true)];
    }
}
