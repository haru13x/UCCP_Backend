<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Detailed Event Report</title>
    <style>
        body { font-family: Arial, sans-serif; font-size: 12px; }
        h2, h3 { margin-top: 20px; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { border: 1px solid #000; padding: 6px; text-align: left; }
        th { background-color: #f2f2f2; }
        img { margin-top: 10px; }
    </style>
</head>
<body>
    <h2>Event Summary Report</h2>
    <p><strong>From:</strong> {{ $fromDate }} &nbsp;&nbsp; <strong>To:</strong> {{ $toDate }}</p>

    @foreach($events as $event)
    <hr>
    <h3>{{ $event->title }}</h3>
    <p>
        <strong>Start:</strong> {{ $event->start_date }} {{ $event->start_time }}<br>
        <strong>End:</strong> {{ $event->end_date }} {{ $event->end_time }}<br>
        <strong>Status:</strong> {{ $event->status_id == 1 ? 'Active' : 'Inactive' }}<br>
        <strong>Organizer:</strong> {{ $event->organizer ?? 'N/A' }}<br>
        <strong>Venue:</strong> {{ $event->venue ?? 'N/A' }}<br>
        <strong>Address:</strong> {{ $event->address ?? 'N/A' }}<br>
        <strong>Description:</strong> {{ $event->description ?? 'No description' }}
    </p>

    @php
        $registrations = $event->event_registrations ?? [];
        $registered = count($registrations);
        $attended = collect($registrations)->where('is_attend', 1)->count();
    @endphp

    <p>
        <strong>Total Registered:</strong> {{ $registered }}<br>
        <strong>Total Attended:</strong> {{ $attended }}
    </p>

    @if($registered > 0)
    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>Full Name</th>
                <th>Sex</th>
                <th>Registered Time</th>
                <th>Attended</th>
            </tr>
        </thead>
        <tbody>
            @foreach($registrations as $i => $reg)
                @php
                    $fullName = $reg['details']['first_name'] . ' ' . $reg['details']['last_name'];
                    $sex = $reg['details']['sex']['name'] ?? 'N/A';
                    $isAttend = $reg['is_attend'] ? 'Yes' : 'No';
                @endphp
                <tr>
                    <td>{{ $i + 1 }}</td>
                    <td>{{ $fullName }}</td>
                    <td>{{ ucfirst($sex) }}</td>
                    <td>{{ $reg['registered_time'] ?? 'N/A' }}</td>
                    <td>{{ $isAttend }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    {{-- Charts --}}
    <h4>Graphs</h4>
    <p><strong>Attendee Gender Breakdown:</strong></p>
    <img src="{{ $event->gender_chart_base64 }}" width="300" alt="Gender Pie Chart">

    <p><strong>Registration vs Attendance:</strong></p>
    <img src="{{ $event->bar_chart_base64 }}" width="300" alt="Bar Chart">
    @else
        <p>No registrations for this event.</p>
    @endif

    @endforeach
</body>
</html>
