<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <title>{{ __('SLA Performance Report') }}</title>
    <style>
        @page { margin: 28px 32px 44px; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 9px; color: #18181b; }
        table { width: 100%; border-collapse: collapse; }
        h2 { font-size: 12px; margin: 18px 0 6px; }

        .header td { vertical-align: middle; padding: 0; }
        .header { border-bottom: 2px solid #18181b; }
        .header .logo { width: 25%; padding-bottom: 8px; }
        .header .name { text-align: center; }
        .header .logo img { height: 70px; }
        .header .company { font-size: 18px; font-weight: bold; }
        .header .title { font-size: 12px; color: #52525b; margin-top: 2px; }
        .header .meta { width: 25%; text-align: right; color: #52525b; line-height: 1.5; }
        .header .meta strong { color: #18181b; }

        .stats { margin-top: 14px; }
        .stats td { width: 14.28%; border: 1px solid #d4d4d8; padding: 7px 8px; }
        .stats .label { color: #52525b; font-size: 8px; }
        .stats .value { font-size: 15px; font-weight: bold; margin-top: 2px; }

        .data th { background: #18181b; color: #ffffff; text-align: left; padding: 5px 6px; font-size: 8px; }
        .data td { padding: 4px 6px; border-bottom: 1px solid #e4e4e7; }
        .data tr:nth-child(even) td { background: #fafafa; }
        .data .num { text-align: center; }
        .data .breached { color: #dc2626; font-weight: bold; }
        .data .met { color: #059669; }
        .muted { color: #71717a; }
        .keep { page-break-inside: avoid; }

        .footer { position: fixed; bottom: -30px; left: 0; right: 0; font-size: 8px; color: #71717a; }
        .footer .page { text-align: right; }
        .footer .page:after { content: counter(page); }
    </style>
</head>
<body>
    <div class="footer">
        <table>
            <tr>
                <td>{{ $companyName }} · {{ __('SLA Performance Report') }} · {{ __('Generated :date by :name', ['date' => now()->format('d M Y H:i'), 'name' => $generatedBy]) }}</td>
                <td class="page">{{ __('Page') }} </td>
            </tr>
        </table>
    </div>

    <table class="header">
        <tr>
            <td class="logo">
                @if ($logo)
                    <img src="{{ $logo }}" alt="{{ $companyName }}">
                @endif
            </td>
            <td class="name">
                <div class="company">{{ $companyName }}</div>
                <div class="title">{{ __('SLA Performance Report') }}</div>
            </td>
            <td class="meta">
                <strong>{{ __('Period') }}:</strong> {{ $period[0]->format('d M Y') }} – {{ $period[1]->format('d M Y') }}<br>
                <strong>{{ __('Client') }}:</strong> {{ $client ?? __('All clients') }}<br>
                <strong>{{ __('System') }}:</strong> {{ $system ?? __('All systems') }}
            </td>
        </tr>
    </table>

    <table class="stats">
        <tr>
            @foreach ([
                __('Tickets opened') => $summary['opened'],
                __('Resolved') => $summary['resolved'],
                __('SLA breached') => $summary['breached'],
                __('Resolution SLA compliance') => $summary['compliance'] !== null ? $summary['compliance'].'%' : '—',
                __('First-response SLA compliance') => $summary['response_compliance'] !== null ? $summary['response_compliance'].'%' : '—',
                __('Avg. first response') => $summary['avg_response'] ?? '—',
                __('Avg. resolution time') => $summary['avg_resolution'] ?? '—',
            ] as $label => $value)
                <td>
                    <div class="label">{{ $label }}</div>
                    <div class="value">{{ $value }}</div>
                </td>
            @endforeach
        </tr>
    </table>

    @foreach ($breakdowns as $breakdown)
        @continue(empty($breakdown['rows']))

        <div class="keep">
        <h2>{{ __('By :group', ['group' => Str::lower($breakdown['label'])]) }}</h2>
        <table class="data">
            <thead>
                <tr>
                    <th>{{ $breakdown['label'] }}</th>
                    <th class="num">{{ __('Opened') }}</th>
                    <th class="num">{{ __('Resolved') }}</th>
                    <th class="num">{{ __('Still open') }}</th>
                    <th class="num">{{ __('Breached') }}</th>
                    <th class="num">{{ __('SLA compliance') }}</th>
                    <th class="num">{{ __('Avg. resolution') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($breakdown['rows'] as $row)
                    <tr>
                        <td>{{ $row['label'] }}</td>
                        <td class="num">{{ $row['opened'] }}</td>
                        <td class="num">{{ $row['resolved'] }}</td>
                        <td class="num">{{ $row['open'] }}</td>
                        <td class="num">{{ $row['breached'] }}</td>
                        <td class="num">{{ $row['compliance'] !== null ? $row['compliance'].'%' : '—' }}</td>
                        <td class="num">{{ $row['avg_resolution'] ?? '—' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        </div>
    @endforeach

    <h2>{{ __('Tickets') }} ({{ $tickets->count() }})</h2>
    <table class="data">
        <thead>
            <tr>
                <th>{{ __('Reference') }}</th>
                <th>{{ __('Client') }} / {{ __('System') }}</th>
                <th>{{ __('Title') }}</th>
                <th>{{ __('Priority') }}</th>
                <th>{{ __('Status') }}</th>
                <th>{{ __('Assignee') }}</th>
                <th>{{ __('Opened') }}</th>
                <th>{{ __('Resolved') }}</th>
                <th>{{ __('SLA') }}</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($tickets as $ticket)
                @php($state = $ticket->slaState())
                <tr>
                    <td>{{ $ticket->reference }}</td>
                    <td>{{ $ticket->company->name }}<br><span class="muted">{{ $ticket->project->name }}</span></td>
                    <td>{{ Str::limit($ticket->title, 70) }}</td>
                    <td>{{ $ticket->priority->label() }}</td>
                    <td>{{ $ticket->status->label() }}</td>
                    <td>{{ $ticket->assignee->name ?? '—' }}</td>
                    <td>{{ $ticket->created_at->format('d M Y H:i') }}</td>
                    <td>{{ $ticket->resolved_at?->format('d M Y H:i') ?? '—' }}</td>
                    <td class="{{ $state->value }}">{{ $state->label() }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="9" class="num muted">{{ __('No tickets were opened in this period.') }}</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
