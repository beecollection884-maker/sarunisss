<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }}</title>
    <style>
        body {
            color: #0f172a;
            font-family: Arial, sans-serif;
            font-size: 12px;
            margin: 24px;
        }

        h1 {
            font-size: 20px;
            margin: 0 0 16px;
        }

        table {
            border-collapse: collapse;
            width: 100%;
        }

        th,
        td {
            border: 1px solid #cbd5e1;
            padding: 7px 8px;
            text-align: left;
            vertical-align: top;
        }

        th {
            background: #f1f5f9;
            font-weight: 700;
        }

        @media print {
            body {
                margin: 12mm;
            }
        }
    </style>
</head>
<body>
    <h1>{{ $title }}</h1>
    <table>
        <thead>
            <tr>
                @foreach ($headers as $header)
                    <th>{{ $header }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $row)
                <tr>
                    @foreach ($row as $value)
                        <td>{{ $value }}</td>
                    @endforeach
                </tr>
            @empty
                <tr>
                    <td colspan="{{ count($headers) }}">Tidak ada data.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
