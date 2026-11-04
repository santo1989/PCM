<?php

namespace App\Console\Commands;

use App\Models\StockCash;
use App\Models\StockDividend;
use App\Models\StockSymbol;
use App\Models\StockTrade;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Loads the owner's "Stok Informations" workbook (sheets Stocks, Trades, Cash, Dividends) into the
 * stock_* tables. Re-runnable: rows are matched on their sheet row ("Trades!12"), updated, never deleted.
 */
class ImportStockPortfolio extends Command
{
    protected $signature = 'stocks:import {file : Path to the .xlsx workbook} {--dry-run : Show counts without saving}';

    protected $description = 'Import / refresh the DSE stock portfolio from the Stok Informations workbook';

    public function handle()
    {
        $book = IOFactory::load($this->argument('file'));
        $data = ['symbols' => [], 'trades' => [], 'cash' => [], 'dividends' => []];

        $s = $book->getSheetByName('Stocks');
        $priceDate = $this->date($this->value($book->getSheetByName('Settings'), 'B3'));
        for ($r = 4; $r <= $s->getHighestRow(); $r++) {
            if (!$symbol = trim((string) $this->value($s, "A$r"))) continue;
            $price = $this->value($s, "D$r");
            $data['symbols'][] = ['symbol' => $symbol, 'company' => $this->value($s, "B$r"), 'category' => $this->value($s, "C$r") ?: 'A',
                'price' => is_numeric($price) ? $price : null, 'prev_close' => is_numeric($this->value($s, "I$r")) ? $this->value($s, "I$r") : null,
                'price_date' => is_numeric($price) ? $priceDate : null];
        }

        $t = $book->getSheetByName('Trades');
        for ($r = 2; $r <= $t->getHighestRow(); $r++) {
            if (!$symbol = trim((string) $this->value($t, "C$r"))) continue;
            $data['trades'][] = ['source_row' => "Trades!$r", 'date' => $this->date($this->value($t, "A$r")), 'side' => ucfirst(strtolower($this->value($t, "B$r"))),
                'symbol' => $symbol, 'market' => $this->value($t, "D$r") ?: 'Public', 'qty' => (int) $this->value($t, "E$r"),
                'rate' => (float) $this->value($t, "F$r"), 'commission' => (float) $this->value($t, "H$r")];
        }

        $c = $book->getSheetByName('Cash');
        for ($r = 2; $r <= $c->getHighestRow(); $r++) {
            if (!$type = $this->value($c, "B$r")) continue;
            $data['cash'][] = ['source_row' => "Cash!$r", 'date' => $this->date($this->value($c, "A$r")), 'type' => $type, 'particulars' => $this->value($c, "C$r"),
                'cash_in' => (float) $this->value($c, "D$r"), 'cash_out' => (float) $this->value($c, "E$r")];
        }

        $d = $book->getSheetByName('Dividends');
        for ($r = 2; $r <= $d->getHighestRow(); $r++) {
            if (!$symbol = $this->value($d, "B$r")) continue;
            $data['dividends'][] = ['source_row' => "Dividends!$r", 'symbol' => $symbol, 'bo_account' => $this->value($d, "C$r"),
                'record_date' => $this->date($this->value($d, "D$r")), 'percent' => $this->value($d, "E$r"), 'qty' => $this->value($d, "F$r"),
                'gross' => (float) $this->value($d, "G$r"), 'tax' => (float) $this->value($d, "H$r"), 'net' => (float) $this->value($d, "I$r"),
                'received_date' => $this->date($this->value($d, "J$r")), 'status' => $this->value($d, "K$r") ?: 'Receivable'];
        }

        $this->table(['Sheet', 'Rows'], collect($data)->map(fn($rows, $k) => [$k, count($rows)])->values());
        if ($this->option('dry-run')) return self::SUCCESS;

        DB::transaction(function () use ($data) {
            foreach ($data['symbols'] as $row) StockSymbol::updateOrCreate(['symbol' => $row['symbol']], $row);
            foreach (['trades' => StockTrade::class, 'cash' => StockCash::class, 'dividends' => StockDividend::class] as $key => $model) {
                foreach ($data[$key] as $row) $model::updateOrCreate(['source_row' => $row['source_row']], $row);
            }
            \App\Services\StockPortfolio::syncUnrealizedLedger();
        });
        $this->info('Imported. Open Stock Portfolio to check the totals against the workbook Dashboard.');

        return self::SUCCESS;
    }

    /** The value Excel saved: the workbook uses newer functions PhpSpreadsheet cannot recalculate. */
    private function value(Worksheet $sheet, string $cell)
    {
        $c = $sheet->getCell($cell);
        $v = $c->isFormula() ? $c->getOldCalculatedValue() : $c->getValue();

        return $v instanceof \PhpOffice\PhpSpreadsheet\RichText\RichText ? $v->getPlainText() : $v;
    }

    private function date($v): ?string
    {
        if ($v === null || $v === '') return null;

        return is_numeric($v) ? Date::excelToDateTimeObject($v)->format('Y-m-d') : date('Y-m-d', strtotime((string) $v));
    }
}
