<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Record of Processing Activities</title>
    <style>
        body { font-family: sans-serif; font-size: 11px; color: #1a1a1a; }
        h1 { font-size: 16px; margin-bottom: 0; }
        p.meta { color: #555; margin-top: 4px; }
        table { width: 100%; border-collapse: collapse; margin-top: 16px; }
        th, td { border: 1px solid #ccc; padding: 6px 8px; text-align: left; vertical-align: top; }
        th { background-color: #f0f0f0; }
        .empty { color: #888; font-style: italic; }
    </style>
</head>
<body>
    <h1>Record of Processing Activities</h1>
    <p class="meta">Generated {{ $generatedAt->toIso8601String() }} &mdash; Art. 30 GDPR/UK-GDPR, covering all currently active consent purposes.</p>

    <table>
        <thead>
            <tr>
                <th>Purpose</th>
                <th>Lawful basis (Art. 6)</th>
                <th>Categories of personal data</th>
                <th>Categories of data subjects</th>
                <th>Retention period</th>
                <th>Post-expiry action</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $row)
                <tr>
                    <td>{{ $row['purpose_name'] }}</td>
                    <td>{{ $row['lawful_basis'] }}</td>
                    <td>
                        @if ($row['data_category_name'])
                            {{ $row['data_category_name'] }}
                            @if ($row['data_category_description'])
                                &mdash; {{ $row['data_category_description'] }}
                            @endif
                        @else
                            <span class="empty">Not yet classified</span>
                        @endif
                    </td>
                    <td>{{ $row['data_subjects_description'] ?: 'Not yet described' }}</td>
                    <td>
                        @if ($row['retention_period_days'] !== null)
                            {{ $row['retention_period_days'] }} day(s)
                        @else
                            <span class="empty">No retention policy defined</span>
                        @endif
                    </td>
                    <td>{{ $row['post_expiry_action'] ?? '' }}</td>
                </tr>
            @empty
                <tr><td colspan="6" class="empty">No active consent purposes are currently defined.</td></tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
