<?php

namespace App\Services;

use App\Models\VentureInvoice;
use App\Models\VentureSchedule;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;

/** Freelance & Business invoices, amounts still due, bill reminders and the printed letterhead. */
class VentureBilling
{
    // Letterhead (name, address, phone, bank details) is personal data: kept in storage/, never in the repo.
    private const PROFILE = 'ventures/letterhead.json';

    public static function itemsTotal(array $items): float
    {
        return round(array_sum(array_map(fn($i) => (float) ($i['qty'] ?? 1) * (float) ($i['rate'] ?? 0), $items)), 2);
    }

    public static function status(float $total, float $paid, ?string $dueDate, string $today): string
    {
        if ($paid >= $total - 0.005) return 'Paid';
        if ($dueDate && $dueDate < $today) return 'Overdue';

        return $paid > 0 ? 'Partly paid' : 'Due';
    }

    /** Next number in the owner's style: 2026-05-001 = year-month-sequence within the year. */
    public static function nextNumber(string $date, array $existing): string
    {
        $year = substr($date, 0, 4);
        $seq = 0;
        foreach ($existing as $n) {
            if (preg_match('/^' . $year . '-\d{2}-(\d+)$/', $n, $m)) $seq = max($seq, (int) $m[1]);
        }

        return sprintf('%s-%s-%03d', $year, substr($date, 5, 2), $seq + 1);
    }

    /** Pure: schedules whose reminder window has opened (or that are past due) on $today. */
    public static function dueSchedules(array $schedules, string $today): array
    {
        return array_values(array_filter($schedules, fn($s) => !empty($s['is_active'])
            && Carbon::parse($s['next_due'])->subDays((int) $s['remind_days'])->toDateString() <= $today));
    }

    /** Invoices of a venture (or all) with paid / due / status, newest first. */
    public static function invoices(?string $venture = null): array
    {
        $today = now()->toDateString();

        return VentureInvoice::withSum('payments as paid_raw', 'amount')
            ->when($venture, fn($q) => $q->where('venture', $venture))
            ->orderByDesc('date')->orderByDesc('id')->get()
            ->map(function ($i) use ($today) {
                $paid = (float) $i->paid_raw;
                // A cash memo is a receipt for money taken on the spot: it is never "due".
                $status = $i->type === 'Cash memo' ? 'Paid' : self::status($i->total, $paid, optional($i->due_date)->toDateString(), $today);

                return ['model' => $i, 'paid' => $paid, 'due' => $status === 'Paid' ? 0.0 : max($i->total - $paid, 0), 'status' => $status];
            })->all();
    }

    /** Amount still due per client: [client => [due, overdue, invoices]]. */
    public static function dueByClient(array $invoices): array
    {
        $out = [];
        foreach ($invoices as $i) {
            if ($i['due'] <= 0) continue;
            $c = $i['model']->client;
            $out[$c] ??= ['due' => 0.0, 'overdue' => 0.0, 'invoices' => 0];
            $out[$c]['due'] += $i['due'];
            $out[$c]['overdue'] += $i['status'] === 'Overdue' ? $i['due'] : 0;
            $out[$c]['invoices']++;
        }
        arsort($out);

        return $out;
    }

    /** What needs attention today: bills to send soon and unpaid invoices. Used by the page and the top-bar bell. */
    public static function reminders(): array
    {
        $today = now()->toDateString();
        $schedules = VentureSchedule::where('is_active', true)->orderBy('next_due')->get();
        $due = collect(self::dueSchedules($schedules->map(fn($s) => ['id' => $s->id, 'is_active' => $s->is_active, 'next_due' => $s->next_due->toDateString(), 'remind_days' => $s->remind_days])->all(), $today))->pluck('id');

        return [
            'send' => $schedules->whereIn('id', $due)->values(),
            'unpaid' => collect(self::invoices())->filter(fn($i) => $i['due'] > 0)->values(),
        ];
    }

    public static function profile(string $venture): array
    {
        $all = Storage::exists(self::PROFILE) ? json_decode(Storage::get(self::PROFILE), true) : [];

        return ($all[$venture] ?? []) + ['name' => config("finance.ventures.$venture.label"), 'address' => '', 'phone' => '', 'email' => '', 'payment' => '', 'footer' => 'Thanking you'];
    }

    public static function saveProfile(string $venture, array $profile): void
    {
        $all = Storage::exists(self::PROFILE) ? json_decode(Storage::get(self::PROFILE), true) : [];
        $all[$venture] = $profile;
        Storage::put(self::PROFILE, json_encode($all, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }
}
