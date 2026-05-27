<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $ballot->title }} — Results</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #111; }
        h1 { font-size: 18px; margin-bottom: 4px; }
        h2 { font-size: 14px; margin-top: 20px; margin-bottom: 8px; }
        table { width: 100%; border-collapse: collapse; margin-top: 8px; }
        th, td { border: 1px solid #ccc; padding: 6px 8px; text-align: left; }
        th { background: #f3f4f6; }
        .meta { color: #555; margin-bottom: 16px; }
    </style>
</head>
<body>
    <h1>{{ $ballot->title }}</h1>
    <p class="meta">
        Status: {{ $ballot->status->label() }}<br>
        @if ($weighted)
            Total weighted votes: {{ $tally['total_votes'] }}
        @else
            Total votes: {{ $tally['total_votes'] }}
        @endif
    </p>

    @if ($quorum['configured'])
        <p class="meta">
            Quorum: {{ $quorum['cast'] }} of {{ $quorum['eligible'] }} eligible
            ({{ $quorum['percent'] }}%) —
            {{ $quorum['met'] ? 'met' : 'not met' }}
            (required {{ number_format($quorum['required'], 1) }}%)
        </p>
    @endif

    <h2>Results</h2>
    <table>
        <thead>
            <tr>
                <th>Option</th>
                <th>{{ $weighted ? 'Weighted votes' : 'Votes' }}</th>
                <th>Share</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($tally['options'] as $option)
                <tr>
                    <td>{{ $option['label'] }}</td>
                    <td>{{ $option['count'] }}</td>
                    <td>{{ $option['percentage'] }}%</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    @if (count($response_details) > 0)
        <h2>Vote breakdown</h2>
        <table>
            <thead>
                <tr>
                    <th>Voter unit</th>
                    <th>Option</th>
                    @if ($weighted)
                        <th>Weight</th>
                    @endif
                    <th>Cast by</th>
                    <th>Cast at</th>
                    <th>Via proxy</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($response_details as $row)
                    <tr>
                        <td>{{ $row['voter_unit_label'] }}</td>
                        <td>{{ $row['option_label'] }}</td>
                        @if ($weighted)
                            <td>{{ $row['weight'] ?? 1 }}</td>
                        @endif
                        <td>{{ $row['cast_by_name'] }}</td>
                        <td>{{ $row['cast_at'] }}</td>
                        <td>{{ ($row['via_proxy'] ?? false) ? 'yes' : 'no' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</body>
</html>
