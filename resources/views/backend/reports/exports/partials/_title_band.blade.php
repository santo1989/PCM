{{--
    Shared title band for Excel report exports. Pass in:
      $title    (string) report name
      $subtitle (string|null) e.g. the period covered / generated-at timestamp
      $colspan  (int) how many columns the table has, so the band spans the full width
--}}
<tr>
    <th colspan="{{ $colspan }}"
        style="background-color:#4F46E5;color:#FFFFFF;font-size:16px;font-weight:bold;text-align:center;padding:12px;border:1px solid #333333;">
        {{ $title }}
    </th>
</tr>
@if (!empty($subtitle))
    <tr>
        <th colspan="{{ $colspan }}"
            style="background-color:#EEF2FF;color:#333333;font-size:11px;font-weight:normal;text-align:center;padding:6px;border:1px solid #333333;">
            {{ $subtitle }}
        </th>
    </tr>
@endif
