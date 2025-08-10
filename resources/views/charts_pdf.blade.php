<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Event Report</title>
    <style>
        @page {
            margin: 1cm;
        }

        body {
            font-family: 'DejaVu Sans', sans-serif;
            color: #1e293b;
            line-height: 1.5;
            background: #ffffff;
        }

        .page {
            max-width: 23cm;
            margin: 0 auto;
           
            background: #ffffff;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            page-break-after: always;
        }

        .page:last-child {
            page-break-after: avoid;
        }

        /* Report Header */
        .report-header {
            text-align: center;
            margin-bottom: 16px;
            padding-bottom: 8px;
            border-bottom: 2px solid #e2e8f0;
        }

        .report-header h1 {
            font-size: 22px;
            font-weight: bold;
            color: #000000;
            margin: 0;
        }

        .report-header p {
            color: #000000;
            font-size: 12px;
            margin: 4px 0 0;
        }

        /* Event Card */
        .event-card {
            background: #ffffff;
            border-radius: 10px;
            border: 1px solid #e2e8f0;
            overflow: hidden;
        }

        .event-header {
            background: #f8fafc;
            padding: 12px 16px;
            border-bottom: 1px solid #e2e8f0;
        }

        .event-header h2 {
            font-size: 18px;
            font-weight: 600;
            color: #0f172a;
            margin: 0;
        }

        .event-subtitle {
            font-size: 11px;
            color: #64748b;
            margin: 2px 0 0;
        }

        .event-body {
            padding: 14px 16px;
        }

        /* Info Grid */
        .info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
            margin-bottom: 14px;
            font-size: 11.5px;
        }

        .info-label {
            font-weight: 600;
            color: #334155;
        }

        .info-value {
            color: #475569;
        }

        /* Stats */
        .stats-container {
            display: flex;
            gap: 10px;
            margin-bottom: 16px;
            justify-content: start;
        }

        .stat-box {
            flex: 1;
            min-width: 90px;
            padding: 8px 8px;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            text-align: center;
            font-size: 11px;
        }

        .stat-number {
            font-size: 15px;
            font-weight: bold;
            color: #0f172a;
        }

        .stat-label {
            color: #64748b;
        }

        /* Charts Inline */
        .charts-inline {
            width: 100%;
            border-collapse: collapse;
            margin: 14px 0;
        }

        .charts-inline td {
            width: 50%;
            padding: 0 8px;
            vertical-align: top;
        }

        .chart-wrapper {
            text-align: center;
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            padding: 8px;
        }

        .chart-wrapper img {
            width: 100%;
            height: auto;
            border-radius: 4px;
        }

        .chart-caption {
            font-size: 11px;
            color: #64748b;
            margin-top: 4px;
        }

        /* Description */
        .event-description {
            font-size: 12px;
            color: #334155;
            line-height: 1.5;
            margin: 10px 0;
            padding: 10px;
            background: #ffffff;
            border-radius: 6px;
            border: 1px dashed #cbd5e1;
        }

        /* Section Title */
        .section-title {
            font-size: 13px;
            font-weight: 600;
            color: #ffffff;
            margin: 14px 0 6px;
            padding-bottom: 2px;
            border-bottom: 1px solid #cbd5e1;
        }

        /* Programs */
        .list {
            margin: 0;
            padding-left: 18px;
            font-size: 11px;
        }

        .list li {
            margin: 4px 0;
        }

        /* Sponsors */
        .sponsor-list {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
            margin-top: 4px;
        }

        .sponsor-item {
            background: #ffffff;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 10px;
            color: #ffffff;
            border: 1px solid #cbd5e1;
        }

        /* Footer */
        .footer {
            text-align: center;
            font-size: 10px;
            color: #ffffff;
            margin-top: 20px;
            padding-top: 8px;
            border-top: 1px solid #e2e8f0;
        }
    </style>
</head>
<body>

    <!-- Global Report Header -->
    <div class="report-header">
        <h1>Event  Report</h1>
        <p>Period: {{ date('M d, Y', strtotime($fromDate)) }} – {{ date('M d, Y', strtotime($toDate)) }}</p>
    </div>

    @foreach($events as $event)
        <div class="page">
            <div class="event-card">

                <!-- Event Header -->
                <div class="event-header">
                    <h2>{{ $event->title }}</h2>
                    <div class="event-subtitle">
                        {{ \Carbon\Carbon::parse($event->start_date)->format('M d, Y') }} 
                        to {{ \Carbon\Carbon::parse($event->end_date)->format('M d, Y') }}
                        | {{ $event->venue ?? 'N/A' }}
                    </div>
                    <div class="event-subtitle">
                      
                         {{ $event->address ?? 'N/A' }}
                    </div>
                </div>

                <!-- Event Body -->
                <div class="event-body">

                    <!-- Description -->
                 

                    <!-- Info Grid -->
                    <div class="info-grid">
                        <div class="info-item">
                            <span class="info-label">Organizer:</span>
                            <span class="info-value">{{ $event->organizer ?? 'N/A' }}</span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Mode:</span>
                            <span class="info-value">
                                {{-- {{ $event->eventMode?->eventType?->name ?? 'N/A' }} 
                                ({{ $event->eventMode?->name ?? 'N/A' }}) --}}
                            </span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Status:</span>
                            <span class="info-value">
                                {{ $event->status_id == 1 ? 'Active' : 'Completed' }}
                            </span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Total Registered:</span>
                            <span class="info-value">
                                {{ $eventData[$event->id]['registered'] }}
                            </span>
                        </div>
                    </div>

                    <!-- Stats -->
                       @if($event->description)
                        <div class="event-description">
                            <strong>Description:</strong> {{ strip_tags($event->description) }}
                        </div>
                    @endif
                    <!-- Charts Inline -->
                    <table class="charts-inline">
                        <tr>
                            <!-- Attendance Chart -->
                            <td>
                                <div class="chart-wrapper">
                                    <img src="{{ $eventData[$event->id]['attendanceChartUrl'] }}" 
                                         alt="Attendance Chart">
                                    <div class="chart-caption">Attendance Breakdown</div>
                                </div>
                            </td>
                            <!-- Gender Chart -->
                            <td>
                                <div class="chart-wrapper">
                                    <img src="{{ $eventData[$event->id]['genderChartUrl'] }}" 
                                         alt="Gender Distribution">
                                    <div class="chart-caption">Gender Distribution</div>
                                </div>
                            </td>
                        </tr>
                    </table>

                    <!-- Programs -->
                    @if($event->eventPrograms->isNotEmpty())
                        <div class="section-title">Programs & Schedule</div>
                        <ul class="list">
                            @foreach($event->eventPrograms as $program)
                                <li>
                                    <strong>{{ $program->title }}</strong> 
                                    ({{ $program->start_time }} – {{ $program->end_time }})
                                </li>
                            @endforeach
                        </ul>
                    @endif

                    <!-- Sponsors -->
                    @if($event->eventsSponser->isNotEmpty())
                        <div class="section-title">Sponsors</div>
                        <div class="sponsor-list">
                            @foreach($event->eventsSponser as $sponsor)
                                <span class="sponsor-item">{{ $sponsor->sponser?->name ?? 'Unknown' }}</span>
                            @endforeach
                        </div>
                    @endif

                </div>

                <!-- Footer -->
                <div class="footer">
                    Generated on {{ now()->format('M d, Y \a\t H:i') }} | Event ID: {{ $event->id }}
                </div>
            </div>
        </div>
    @endforeach

</body>
</html>