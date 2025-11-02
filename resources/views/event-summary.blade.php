<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>{{ $event->title ?? 'Event Summary' }}</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
  <style>
    :root {
      --bg: #f7f8fb;
      --card: #ffffff;
      --text: #111827;
      --muted: #6b7280;
      --primary: #1976d2;
      --accent: #f59e0b;
      --danger: #ef4444;
      --success: #10b981;
      --border: #eef0f5;
    }
    * { box-sizing: border-box; }
    body {
      margin: 0;
      font-family: 'Inter', system-ui, -apple-system, Segoe UI, Roboto, sans-serif;
      background: var(--bg);
      color: var(--text);
    }
    .container {
      max-width: 1200px;
      margin: 24px auto;
      padding: 0 16px;
    }
    .header {
      margin-bottom: 24px;
    }
    .title {
      font-size: 24px;
      font-weight: 700;
      margin-bottom: 4px;
    }
    .subtitle {
      color: var(--muted);
      font-size: 14px;
    }
    
    /* Main Layout Grid */
    .main-layout {
      display: grid;
      grid-template-columns: 1fr; /* Default to 1 column for mobile */
      gap: 24px;
    }

    /* Main Content Area */
    .main-content {
      display: flex;
      flex-direction: column;
      gap: 24px;
    }
    
    /* Sidebar Area */
    .sidebar {
      display: flex;
      flex-direction: column;
      gap: 24px;
    }

    /* Card Base Style */
    .card {
      background: var(--card);
      border-radius: 12px;
      padding: 24px;
      box-shadow: 0 4px 12px rgba(0,0,0,0.04);
      border: 1px solid var(--border);
    }
    .card-title {
      margin: 0 0 16px;
      font-size: 16px;
      font-weight: 600;
    }

    /* Metrics Grid */
    .metrics-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
      gap: 16px;
    }
    .metric {
      background: #fff;
      border-radius: 10px;
      padding: 16px;
      border: 1px solid var(--border);
    }
    .metric .label {
      font-size: 13px;
      color: var(--muted);
      margin-bottom: 8px;
    }
    .metric .value {
      font-size: 22px;
      font-weight: 700;
    }
    .metric .value.success { color: var(--success); }
    .metric .value.accent { color: var(--accent); }

    /* Charts Grid */
    .charts-grid {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 16px;
    }
    
    /* Full-width chart card (for the bar chart) */
    .chart-full-width {
      grid-column: 1 / -1;
    }

    /* QR Code Card */
    .qr-card {
      text-align: center;
    }
    .qr-card img {
      max-width: 350px; /* Makes the QR code "big" */
      width: 100%;
      height: 350px;
      border-radius: 8px;
      border: 1px solid var(--border);
      margin-bottom: 16px;
    }
    .qr-card-text {
      font-size: 14px;
      color: var(--muted);
      line-height: 1.6;
    }

    /* Responsive */
    @media (min-width: 900px) {
      .main-layout {
        /* 2/3 for main content, 1/3 for sidebar */
        grid-template-columns: 2fr 1fr; 
      }
    }
    
    @media (max-width: 600px) {
      .charts-grid {
        grid-template-columns: 1fr; /* Stack charts on small screens */
      }
      .metrics-grid {
        grid-template-columns: 1fr 1fr; /* 2x2 grid on mobile */
      }
    }
  </style>
</head>
<body>
  @php
    $ev = $event ?? null;
    $summary = $summary ?? [];
    
    // Gender Data
    $gender = $summary['gender'] ?? ['male' => 0, 'female' => 0, 'other' => 0];
    $total_registrants = ($gender['male'] ?? 0) + ($gender['female'] ?? 0) + ($gender['other'] ?? 0);

    // Attendance Data
    $attendance = $summary['attendance'] ?? ['present' => 0, 'absent' => 0];
    $total_attendance_base = ($attendance['present'] ?? 0) + ($attendance['absent'] ?? 0);
    $attendance_rate = $total_attendance_base > 0 ? round((($attendance['present'] ?? 0) / $total_attendance_base) * 100, 1) : 0;

    // Review Data
    $reviews = $summary['reviews'] ?? [
      'count' => 0,
      'avg_rating' => 0,
      'category_averages' => [
        'venue' => 0, 'speaker' => 0, 'events' => 0, 'foods' => 0, 'accommodation' => 0
      ],
    ];
  @endphp

  <div class="container">
    
    <div class="header">
      <div class="title">{{ $ev->title ?? 'Event Summary' }}</div>
      
      <div class="subtitle">
        {{ isset($ev->start_date) ? date('M j, Y', strtotime($ev->start_date)) : '' }}
        {{ isset($ev->start_time) ? date('g:i A', strtotime($ev->start_time)) : '' }}
        –
        {{ isset($ev->end_date) ? date('M j, Y', strtotime($ev->end_date)) : '' }}
        {{ isset($ev->end_time) ? date('g:i A', strtotime($ev->end_time)) : '' }}
      </div>
      <div class="subtitle">{{ $ev->venue ?? 'No venue' }}</div>
        <div class="subtitle">{{ $ev->address ?? 'No address' }}</div>
    </div>

    <div class="main-layout">
      
    
      
      <div class="sidebar">
        <div class="card qr-card">
          <h3 class="card-title">Event QR Code</h3>
          
          @if(!empty($qrPublicPath) && file_exists($qrPublicPath))
            <img src="{{ $qrPublicPath }}" alt="Event QR Code"/>
          @elseif(!empty($qrUrl))
            <img src="{{ $qrUrl }}" alt="Event QR Code"/>
          @else
            <div class="metric">
              <div class="label">QR Code</div>
              <div class="value" style="color: var(--danger); font-size: 16px;">Not Generated</div>
            </div>
          @endif
          
          <p class="qr-card-text">
            Use this QR code for attendee check-in or to share the public event page.
          </p>
        </div>
      </div>
      
    </div>
  </div>

  <script>
    // These variables are now safely populated by the @php block
    const gender = {!! json_encode($gender) !!};
    const attendance = {!! json_encode($attendance) !!};
    const categoryAverages = {!! json_encode($reviews['category_averages']) !!};

    const makeDoughnut = (ctx, labels, data, colors, title='') => new Chart(ctx, {
      type: 'doughnut',
      data: {
        labels,
        datasets: [{ data, backgroundColor: colors, borderWidth: 0, hoverOffset: 4 }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { 
          legend: { position: 'bottom', labels: { padding: 20 } }, 
          title: { display: !!title, text: title, font: { size: 16 } } 
        },
        cutout: '65%',
      }
    });

    const makeBar = (ctx, labels, data, colors) => new Chart(ctx, {
      type: 'bar',
      data: {
        labels,
        datasets: [{
          label: 'Avg Rating', 
          data, 
          backgroundColor: colors, 
          borderRadius: 6, 
          maxBarThickness: 40
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        scales: { 
          y: { 
            beginAtZero: true, 
            suggestedMax: 5, 
            grid: { drawBorder: false } 
          },
          x: {
            grid: { display: false }
          }
        },
        plugins: { 
          legend: { display: false } 
        }
      }
    });

    // Gender Chart
    const genderCtx = document.getElementById('genderChart');
    if (genderCtx) {
      makeDoughnut(genderCtx,
        ['Male','Female','Other'],
        [gender.male || 0, gender.female || 0, gender.other || 0],
        ['#3b82f6','#f43f5e','#10b981']
      );
    }

    // Attendance Chart
    const attCtx = document.getElementById('attendanceChart');
    if (attCtx) {
      makeDoughnut(attCtx,
        ['Present','Unattended'],
        [attendance.present || 0, attendance.absent || 0],
        ['#10b981','#ef4444']
      );
    }

    // Review Categories Chart
    const revCtx = document.getElementById('reviewChart');
    if (revCtx) {
      const labels = Object.keys(categoryAverages || {});
      const values = Object.values(categoryAverages || {});
      makeBar(revCtx, 
        labels.length ? labels : ['Venue','Speaker','Events','Foods','Accommodation'],
        values.length ? values : [0,0,0,0,0],
        ['#2563eb','#f59e0b','#10b981','#f43f5e','#8b5cf6']
      );
    }
  </script>
</body>
</html>