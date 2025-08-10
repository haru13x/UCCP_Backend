<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Detailed Event Summary Report</title>
  <style>
    body {
      font-family: 'DejaVu Sans', sans-serif;
      font-size: 12px;
      color: #333;
      background: #fff;
      margin: 0;
      padding: 20px;
      line-height: 1.6;
    }

    h2 {
      text-align: center;
      color: #1a1a1a;
      font-size: 20px;
      margin-bottom: 5px;
      border-bottom: 2px solid #005b9f;
      padding-bottom: 10px;
      font-weight: bold;
    }

    h3 {
      color: #005b9f;
      margin-top: 20px;
      font-size: 16px;
      border-left: 4px solid #005b9f;
      padding-left: 10px;
      font-weight: bold;
    }

    p { margin: 4px 0; }
    strong { color: #000; font-weight: bold; }
    hr { border: 1px solid #ddd; margin: 15px 0; }

    table {
      width: 100%;
      border-collapse: collapse;
      margin: 10px 0;
      font-size: 11px;
    }

    th, td {
      border: 1px solid #000;
      padding: 5px 6px;
      text-align: left;
    }

    th {
      background-color: #f2f2f2;
      font-weight: bold;
    }

    tr:nth-child(even) {
      background-color: #f9f9f9;
    }

    .chart-container {
      display: flex;
      flex-wrap: wrap;
      gap: 15px;
      margin: 15px 0;
    }

    .chart-box {
      flex: 1;
      min-width: 220px;
      border: 1px solid #ccc;
      padding: 8px;
      border-radius: 6px;
      background: #fff;
    }

    .chart-title {
      font-weight: bold;
      color: #005b9f;
      margin-bottom: 8px;
      text-align: center;
      font-size: 13px;
    }

    .legend {
      display: flex;
      justify-content: center;
      gap: 8px;
      margin-top: 6px;
      font-size: 10px;
    }

    .legend-item {
      display: flex;
      align-items: center;
      gap: 3px;
    }

    .legend-color {
      width: 10px;
      height: 10px;
      border-radius: 50%;
    }

    @page {
      margin: 20px;
    }

    @media print {
      body {
        margin: 0;
        padding: 10px;
        font-size: 10px;
      }
    }
  </style>
</head>
<body>

  <h2>📊 Detailed Event Summary Report</h2>
  <p><strong>From:</strong> {{ $fromDate }} &nbsp;&nbsp; <strong>To:</strong> {{ $toDate }}</p>

  @foreach($events as $event)
  <hr>
  <h3>🎯 {{ $event->title }}</h3>

  <p>
    <strong>📅 Date:</strong> {{ $event->start_date }} ({{ $event->start_time }} – {{ $event->end_time }})<br>
    <strong>📍 Venue:</strong> {{ $event->venue ?? 'N/A' }}<br>
    <strong>📬 Address:</strong> {{ $event->address ?? 'N/A' }}<br>
    <strong>👤 Organizer:</strong> {{ $event->organizer ?? 'N/A' }}<br>
    <strong>📌 Status:</strong> {{ $event->status_id == 1 ? '✅ Active' : '❌ Cancelled' }}<br>
    <strong>📝 Description:</strong> {{ $event->description ?? 'No description' }}
  </p>

  @php
    $registrations = $event->event_registrations ?? [];
    $registered =56;
    $attended = 3444;

    // Count gender
    $maleCount = 1;
    $femaleCount = 10;

    // Calculate pie chart angles (static)
    $totalGender = $maleCount + $femaleCount;
    $maleAngle = $totalGender ? ($maleCount / $totalGender) * 360 : 0;
    $femaleAngle = $totalGender ? ($femaleCount / $totalGender) * 360 : 0;

    // For SVG: calculate end points
    $maleX = 50 + 50 * cos(deg2rad($maleAngle));
    $maleY = 50 - 50 * sin(deg2rad($maleAngle));
    $largeArc = $maleAngle > 180 ? 1 : 0;
  @endphp

  <p>
    <strong>✅ Total Registered:</strong> {{ $registered }}<br>
    <strong>👉 Total Attended:</strong> {{ $attended }}
  </p>

  @if($registered > 0)
    <!-- Registration Table -->
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
            $fullName = trim($reg['details']['first_name'] . ' ' . ($reg['details']['middle_name'] ?? '') . ' ' . $reg['details']['last_name']);
            $sex = $reg['details']['sex']['name'] ?? 'N/A';
            $isAttend = $reg['is_attend'] ? '✅ Yes' : '❌ No';
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

    <!-- Charts -->
    <div class="chart-container">
      <!-- Pie Chart: Gender -->
      <div class="chart-box">
        <div class="chart-title">Gender Distribution</div>
        <svg width="100%" height="150" viewBox="0 0 100 100">
          @if($maleCount > 0)
            <path d="M50,50 L50,0 A50,50 0 {{ $largeArc }},1 {{ $maleX }},{{ $maleY }} Z" fill="#1976d2" />
          @endif
          @if($femaleCount > 0)
            <path d="M50,50 L{{ $maleX }},{{ $maleY }} A50,50 0 {{ $femaleAngle > 180 ? 1 : 0 }},1 50,0 Z" fill="#e91e63" transform="rotate({{ $maleAngle }} 50 50)" />
          @endif
        </svg>
        <div class="legend">
          @if($maleCount > 0)
            <div class="legend-item">
              <div class="legend-color" style="background-color: #1976d2;"></div>
              <span>Male ({{ $maleCount }})</span>
            </div>
          @endif
          @if($femaleCount > 0)
            <div class="legend-item">
              <div class="legend-color" style="background-color: #e91e63;"></div>
              <span>Female ({{ $femaleCount }})</span>
            </div>
          @endif
        </div>
      </div>

      <!-- Bar Chart: Registration vs Attendance -->
      <div class="chart-box">
        <div class="chart-title">Registration vs Attendance</div>
        <svg width="100%" height="130" viewBox="0 0 100 100">
          @php
            $max = max($registered, $attended, 1);
            $regHeight = (70 * $registered) / $max;
            $attHeight = (70 * $attended) / $max;
            $regY = 70 - $regHeight;
            $attY = 70 - $attHeight;
          @endphp
          <rect x="20" y="{{ $regY }}" width="20" height="{{ $regHeight }}" fill="#4CAF50" />
          <rect x="60" y="{{ $attY }}" width="20" height="{{ $attHeight }}" fill="#FF9800" />
          <text x="30" y="90" font-size="8" text-anchor="middle" fill="#000">Reg: {{ $registered }}</text>
          <text x="70" y="90" font-size="8" text-anchor="middle" fill="#000">Att: {{ $attended }}</text>
        </svg>
        <div class="legend">
          <div class="legend-item">
            <div class="legend-color" style="background-color: #4CAF50;"></div>
            <span>Registered</span>
          </div>
          <div class="legend-item">
            <div class="legend-color" style="background-color: #FF9800;"></div>
            <span>Attended</span>
          </div>
        </div>
      </div>
    </div>
  @else
    <p style="color: #666; font-style: italic;">No registrations for this event.</p>
  @endif

  @endforeach
</body>
</html>