
<table id="cashesTable" style="border: 1px solid #000; padding: 4px; border-collapse: collapse; padding-top: 1em;">
    <thead style="border: 1px solid #000; padding: 4px;">
        <tr>
            <th colspan="6" style="text-align: center; border: none; "> Expense Calculation Report  
                <span style="float: right; font-size: 10px">Download Date: {{ Carbon\Carbon::now()->format('d-M-Y') }}</span>
            </th>
        </tr>
        <tr>  
            <th style="border: 1px solid #000; padding: 4px;">Sl</th>
            <th style="border: 1px solid #000; padding: 4px;">Date</th> 
            <th style="border: 1px solid #000; padding: 4px;">Name</th>
            <th style="border: 1px solid #000; padding: 4px;">Category</th>
            <th style="border: 1px solid #000; padding: 4px;">Types</th>
            <th style="border: 1px solid #000; padding: 4px;">Rules of Cost</th> 
            <th style="border: 1px solid #000; padding: 4px;">Cash Amount BDT</th> 
        </tr>
    </thead>
    <tbody style="border: 1px solid #000; padding: 4px;">
        @php $sl = 1; 
        $total = 0;
        @endphp
        @foreach ($search_cashes as $cash)
            <tr> 
                <td style="border: 1px solid #000; padding: 4px;">{{ $sl++ }}</td>
                <td style="border: 1px solid #000; padding: 4px;">{{ \Carbon\Carbon::parse($cash->date)->format('d-M-Y') }}</td>
                <td style="border: 1px solid #000; padding: 4px;">{{ $cash->name }}</td>
                <td style="border: 1px solid #000; padding: 4px;">{{ $cash->category->name }}</td>
                <td style="border: 1px solid #000; padding: 4px;">{{ $cash->types }}</td>
                <td style="border: 1px solid #000; padding: 4px;">{{ $cash->rules }}</td>
                <td style="border: 1px solid #000; padding: 4px;">{{ $cash->amount }}</td>
                @php
                    $total += $cash->amount;
                @endphp 
                 
            </tr>
        @endforeach
        <tr>
            <td colspan="6" style="text-align: right; border: 1px solid #000; padding: 4px;">Total</td>
            <td style="border: 1px solid #000; padding: 4px;"> {{ $total }} </td> 
    </tbody>
</table>
