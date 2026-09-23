<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $merchant->name }} | Billing Dashboard</title>
    <style>
        :root { color-scheme: light; --ink: #17221f; --muted: #68736f; --line: #dce4df; --paper: #f5f7f2; --card: #fff; --accent: #176b5b; --accent-soft: #e0f0e9; --warning: #a34b28; }
        * { box-sizing: border-box; }
        body { margin: 0; background: var(--paper); color: var(--ink); font-family: Georgia, 'Times New Roman', serif; }
        .shell { width: min(1180px, calc(100% - 40px)); margin: 0 auto; padding: 44px 0 64px; }
        .masthead { display: flex; align-items: end; justify-content: space-between; gap: 24px; border-bottom: 1px solid var(--line); padding-bottom: 28px; }
        .eyebrow { margin: 0 0 8px; color: var(--accent); font: 700 12px/1.2 Arial, sans-serif; letter-spacing: .14em; text-transform: uppercase; }
        h1, h2, p { margin-top: 0; }
        h1 { margin-bottom: 10px; font-size: clamp(34px, 5vw, 58px); line-height: .98; font-weight: 500; letter-spacing: 0; }
        .merchant-meta { margin: 0; color: var(--muted); font: 14px/1.5 Arial, sans-serif; }
        .merchant-meta strong { color: var(--ink); }
        .grid { display: grid; grid-template-columns: repeat(12, 1fr); gap: 18px; margin-top: 22px; }
        .card { grid-column: span 4; background: var(--card); border: 1px solid var(--line); padding: 24px; box-shadow: 0 10px 28px rgba(23, 34, 31, .04); }
        .card.wide { grid-column: span 8; }
        .card.full { grid-column: 1 / -1; }
        .label { margin-bottom: 18px; color: var(--muted); font: 700 11px/1.2 Arial, sans-serif; letter-spacing: .12em; text-transform: uppercase; }
        .metric { margin: 0; color: var(--accent); font-size: 38px; line-height: 1; font-weight: 500; }
        .caption { margin: 10px 0 0; color: var(--muted); font: 13px/1.5 Arial, sans-serif; }
        .cycle { display: grid; grid-template-columns: repeat(2, 1fr); gap: 18px; }
        .cycle dt { color: var(--muted); font: 12px/1.4 Arial, sans-serif; }
        .cycle dd { margin: 4px 0 0; font-size: 19px; }
        .cycle .span { grid-column: span 2; }
        table { width: 100%; border-collapse: collapse; font: 14px/1.4 Arial, sans-serif; }
        th { color: var(--muted); font-size: 11px; letter-spacing: .08em; text-align: left; text-transform: uppercase; }
        th, td { border-bottom: 1px solid var(--line); padding: 12px 8px; }
        th:first-child, td:first-child { padding-left: 0; }
        th:last-child, td:last-child { padding-right: 0; text-align: right; }
        .drop td:last-child { color: var(--warning); font-weight: 700; }
        .empty { color: var(--muted); font: 14px/1.5 Arial, sans-serif; }
        @media (max-width: 800px) { .masthead { display: block; } .card, .card.wide { grid-column: 1 / -1; } }
    </style>
</head>
<body>
<main class="shell">
    <header class="masthead">
        <div>
            <p class="eyebrow">Merchant operations</p>
            <h1>Subscription Billing Dashboard</h1>
        </div>
        <p class="merchant-meta">Merchant: <strong>{{ $merchant->name }}</strong><br>Merchant ID: <strong>{{ $merchant->id }}</strong></p>
    </header>

    <section class="grid" aria-label="Billing overview">
        <article class="card wide">
            <p class="label">Current billing cycle</p>
            <dl class="cycle">
                <div><dt>Start</dt><dd>{{ $cycle['start']->format('F j, Y') }}</dd></div>
                <div><dt>End</dt><dd>{{ $cycle['end']->format('F j, Y') }}</dd></div>
                <div><dt>Days elapsed</dt><dd>{{ $cycle['days_elapsed'] }}</dd></div>
                <div><dt>Days remaining</dt><dd>{{ $cycle['days_remaining'] }}</dd></div>
            </dl>
        </article>
        <article class="card">
            <p class="label">Projected overage revenue</p>
            <p class="metric">{{ $merchant->plans()->first()?->currency ?? 'USD' }} {{ $projected_overage }}</p>
            <p class="caption">Estimated for the current billing cycle</p>
        </article>

        <article class="card wide">
            <p class="label">Top 5 customers by usage</p>
            @if ($top_customers->isEmpty())
                <p class="empty">No usage recorded for the current month.</p>
            @else
                <table>
                    <thead><tr><th>Customer</th><th>Usage</th></tr></thead>
                    <tbody>
                    @foreach ($top_customers as $customer)
                        <tr><td>{{ $customer->name }}</td><td>{{ number_format($customer->usage_total) }}</td></tr>
                    @endforeach
                    </tbody>
                </table>
            @endif
        </article>
        <article class="card">
            <p class="label">Customers with &gt;50% usage drop</p>
            @if ($usage_drops->isEmpty())
                <p class="empty">No significant usage drops detected.</p>
            @else
                <table class="drop">
                    <thead><tr><th>Customer</th><th>Change</th></tr></thead>
                    <tbody>
                    @foreach ($usage_drops as $drop)
                        <tr><td>{{ $drop->name }}</td><td>{{ $drop->change_percent }}%</td></tr>
                    @endforeach
                    </tbody>
                </table>
            @endif
        </article>
    </section>
</main>
</body>
</html>
