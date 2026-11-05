{{-- Excel (HTML table) download, one table per department, like the legacy page. Expects: $byDepartment --}}
@php
    $fmt = fn ($d) => $d && !in_array($d, ['0000-00-00', '0001-01-01'], true) ? date('M d, Y', strtotime($d)) : '';
    $headers = ['ID', 'Name', 'Job Title', 'Issued On', 'Served On', 'Type of Memo', 'Offense', 'Category',
                'Points', 'Memo No.', 'Incident', 'Suspension Dates', 'Status'];
    $na = fn ($v) => in_array(strtoupper(trim((string) $v)), ['', 'N/A', '-'], true) ? '' : trim((string) $v);
@endphp
<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40">
<head>
    <meta charset="UTF-8">
    <style>
        table { border-collapse: collapse; width: 100%; }
        th, td { border: 1px solid #000; padding: 5px; }
        .dept-header { font-weight: bold; text-align: left; background: #e9ecef; }
        .void { background: #f8d7da; }
        .pending { background: #fff3cd; }
        .served { background: #d4edda; }
        {{-- Keep memo numbers and IDs as text so Excel does not turn them into dates or numbers --}}
        .text { mso-number-format: "\@"; }
    </style>
</head>
<body>
@forelse ($byDepartment as $department => $rows)
    <table>
        <thead>
            <tr><th colspan="{{ count($headers) }}" class="dept-header">{{ $department }}</th></tr>
            <tr>
                @foreach ($headers as $h)
                    <th>{{ $h }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @foreach ($rows as $e)
                <tr class="{{ match ($e->status) { 'Void' => 'void', 'pending' => 'pending', default => 'served' } }}">
                    <td class="text">{{ $e->idno }}</td>
                    <td>{{ trim($e->lastname . ', ' . $na($e->firstname) . ' ' . $na($e->middlename) . ' ' . $na($e->suffix)) }}</td>
                    <td>{{ $e->jobtitle }}</td>
                    <td>{{ $fmt($e->dateissued) }}</td>
                    <td>{{ $fmt($e->dateserved) }}</td>
                    <td>{{ $e->typeofmemo }}</td>
                    <td>{{ $e->typeofoffense }}</td>
                    <td>{{ $e->typecat }}</td>
                    <td>{{ $e->points }}</td>
                    <td class="text">{{ $e->memonumber }}</td>
                    <td class="text">{{ $e->dateofincident }}</td>
                    <td class="text">{{ $e->dateofsuspension }}</td>
                    <td>{{ $e->status }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
    <br>
@empty
    <p>No infractions match your filters.</p>
@endforelse
</body>
</html>
