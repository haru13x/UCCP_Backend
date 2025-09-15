<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Event Report</title>
    <style>
        @page {
            margin: 0.8cm;
            size: A4 portrait;
        }

        body {
            font-family: 'DejaVu Sans', sans-serif;
            color: #1e293b;
            line-height: 1.4;
            background: #ffffff;
            margin: 0;
            padding: 0;
        }

        .page {
            width: 100%;
            max-width: 100%;
            margin: 0;
            padding: 8px;
            background: #ffffff;
            page-break-after: always;
            box-sizing: border-box;
        }

        .page:last-child {
            page-break-after: avoid;
        }

        /* Report Header */
        .report-header {
            text-align: center;
            margin-bottom: 12px;
            padding: 12px;
            background: linear-gradient(135deg, #1e40af 0%, #3b82f6 100%);
            color: white;
            border-radius: 6px;
        }

        .report-header h1 {
            font-size: 20px;
            font-weight: bold;
            color: #ffffff;
            margin: 0;
        }

        .report-header p {
            color: #e0e7ff;
            font-size: 12px;
            margin: 6px 0 0;
            font-weight: 500;
        }

        /* Event Card */
        .event-card {
            background: #ffffff;
            border-radius: 8px;
            border: 1px solid #e2e8f0;
            overflow: hidden;
            margin-bottom: 10px;
        }

        .event-header {
            background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
            padding: 12px 16px;
            border-bottom: 1px solid #cbd5e1;
        }

        .event-header h2 {
            font-size: 18px;
            font-weight: 700;
            color: #0f172a;
            margin: 0;
        }

        .event-subtitle {
            font-size: 11px;
            color: #475569;
            margin: 3px 0 0;
            font-weight: 500;
        }

        .event-body {
            padding: 12px 14px;
        }

        /* Info Grid */
        .info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 8px;
            margin-bottom: 10px;
            font-size: 10px;
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
            margin: 10px 0;
        }

        .charts-inline td {
            width: 50%;
            padding: 0 6px;
            vertical-align: top;
        }

        .chart-wrapper {
            text-align: center;
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            padding: 6px;
            height: 220px;
            display: flex;
            flex-direction: column;
        }

        .chart-wrapper img {
            width: 100%;
            height: 180px;
            object-fit: contain;
            border-radius: 4px;
            flex-grow: 1;
        }

        .chart-caption {
            font-size: 10px;
            color: #64748b;
            margin-top: 6px;
            font-weight: 600;
            padding: 4px;
            background: #f8fafc;
            border-radius: 4px;
        }

        /* Description */
        .event-description {
            font-size: 10px;
            color: #334155;
            line-height: 1.4;
            margin: 8px 0;
            padding: 8px;
            background: #f8fafc;
            border-radius: 4px;
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
            font-size: 9px;
            color: #64748b;
            margin-top: 12px;
            padding-top: 6px;
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
                        | {{ $eventData[$event->id]['locationText'] }}
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
                            <span class="info-label">Location:</span>
                            <span class="info-value">{{ $eventData[$event->id]['locationText'] }}</span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Mode:</span>
                            <span class="info-value">
                                {{ $event->eventMode?->eventType?->name ?? 'N/A' }}
                                @if($event->eventMode?->name)
                                    ({{ $event->eventMode?->name }})
                                @endif
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

                    <!-- Reviews and Ratings Section -->
                    @if($eventData[$event->id]['totalReviews'] > 0)
                        <div class="section-title" style="color: #1e293b; background: #f8fafc; padding: 8px; border-radius: 6px; margin: 16px 0 8px;">📊 Reviews & Ratings Overview</div>
                        
                        <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 8px; padding: 12px; margin-bottom: 12px;">
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                                <div style="font-size: 14px; font-weight: 600; color: #0f172a;">
                                    Overall Rating: <span style="color: #059669;">{{ number_format($eventData[$event->id]['averageRating'], 1) }}/5.0</span>
                                </div>
                                <div style="font-size: 12px; color: #64748b;">
                                    Based on {{ $eventData[$event->id]['totalReviews'] }} {{ $eventData[$event->id]['totalReviews'] == 1 ? 'review' : 'reviews' }}
                                </div>
                            </div>
                            
                            <div style="font-size: 11px; color: #475569; line-height: 1.4; margin-bottom: 10px;">
                                This rating reflects the overall satisfaction of participants who attended the event. 
                                The score is calculated from individual ratings across multiple categories including venue quality, 
                                speaker performance, event organization, food service, and accommodation facilities.
                            </div>
                            
                            <!-- Category Ratings -->
                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 8px; font-size: 10px;">
                                @if($eventData[$event->id]['categoryAverages']['venue'] > 0)
                                    <div style="background: #f1f5f9; padding: 6px; border-radius: 4px;">
                                        <strong>Venue:</strong> {{ number_format($eventData[$event->id]['categoryAverages']['venue'], 1) }}/5
                                    </div>
                                @endif
                                @if($eventData[$event->id]['categoryAverages']['speaker'] > 0)
                                    <div style="background: #f1f5f9; padding: 6px; border-radius: 4px;">
                                        <strong>Speaker:</strong> {{ number_format($eventData[$event->id]['categoryAverages']['speaker'], 1) }}/5
                                    </div>
                                @endif
                                @if($eventData[$event->id]['categoryAverages']['events'] > 0)
                                    <div style="background: #f1f5f9; padding: 6px; border-radius: 4px;">
                                        <strong>Event Organization:</strong> {{ number_format($eventData[$event->id]['categoryAverages']['events'], 1) }}/5
                                    </div>
                                @endif
                                @if($eventData[$event->id]['categoryAverages']['foods'] > 0)
                                    <div style="background: #f1f5f9; padding: 6px; border-radius: 4px;">
                                        <strong>Food Service:</strong> {{ number_format($eventData[$event->id]['categoryAverages']['foods'], 1) }}/5
                                    </div>
                                @endif
                                @if($eventData[$event->id]['categoryAverages']['accommodation'] > 0)
                                    <div style="background: #f1f5f9; padding: 6px; border-radius: 4px;">
                                        <strong>Accommodation:</strong> {{ number_format($eventData[$event->id]['categoryAverages']['accommodation'], 1) }}/5
                                    </div>
                                @endif
                            </div>
                        </div>
                    @else
                        <div class="section-title" style="color: #1e293b; background: #f8fafc; padding: 8px; border-radius: 6px; margin: 16px 0 8px;">📊 Reviews & Ratings</div>
                        <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 8px; padding: 12px; margin-bottom: 12px; text-align: center; color: #64748b; font-size: 11px;">
                            No reviews available for this event yet.
                        </div>
                    @endif

                    <!-- Sponsors -->
                 

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