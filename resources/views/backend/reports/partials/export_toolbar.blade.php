{{--
    Shared download toolbar for report pages.
    Pass in:
      $excelRoute  (string|null) route name for the Excel export endpoint
      $excelParams (array) query params to forward to the export (filters, year, etc.)
--}}
<div class="d-flex justify-content-end gap-2 mb-3 no-print">
    @isset($excelRoute)
        <a href="{{ route($excelRoute, $excelParams ?? []) }}" class="btn btn-sm btn-outline-success">
            <i class="bi bi-file-earmark-excel"></i> Download Excel
        </a>
    @endisset
    <button type="button" class="btn btn-sm btn-outline-secondary" onclick="window.print()">
        <i class="bi bi-printer"></i> Print / Save as PDF
    </button>
</div>
