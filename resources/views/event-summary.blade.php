<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <title>Print Event QRCode</title>
  <style>
    body {
      font-family: DejaVu Sans, sans-serif;
      background-color: #ffffff;
      color: #2c3e50;
      margin: 0;
      padding: 50px 20px;
    }

    .container {
      max-width: 600px;
      margin: auto;
      text-align: center;
      border: 1px solid #ccc;
      border-radius: 10px;
      padding: 40px 30px;
    }

    .header {
      margin-bottom: 30px;
    }

    .header h1 {
      font-size: 26px;
      margin-bottom: 10px;
      letter-spacing: 1px;
      color: #1a1a1a;
    }

    .event-title {
      font-size: 20px;
      font-weight: 600;
      margin-bottom: 25px;
      color: #444;
    }

    .datetime {
      font-size: 15px;
      line-height: 1.5;
      margin-bottom: 35px;
      color: #333;
    }

    .qr-container {
      display: flex;
      justify-content: center;
    }

    .qr-container img {
      width: 450px;
      height: auto;
      border: 1px solid #ddd;
      padding: 12px;
      border-radius: 8px;
      background-color: #f9f9f9;
    }

    .footer-note {
      margin-top: 40px;
      font-size: 13px;
      color: #999;
    }
  </style>
</head>
<body>
  <div class="container">
    

    <div class="event-title">{{ $event->title }}</div>

    <div class="datetime">
      <div>
        <strong>Start:</strong>
        {{ \Carbon\Carbon::parse($event->start_date)->format('F j, Y') }}
        {{ \Carbon\Carbon::parse($event->start_time)->format('g:i A') }}
      </div>
      <div>
        <strong>End:</strong>
        {{ \Carbon\Carbon::parse($event->end_date)->format('F j, Y') }}
        {{ \Carbon\Carbon::parse($event->end_time)->format('g:i A') }}
      </div>
    </div>

    <div class="qr-container">
      <img src="{{ $qrImage }}" alt="QR Code">
    </div>

    <div class="footer-note">
      Scan the QR code above to view event details or confirm attendance participation.
    </div>
  </div>
</body>
</html>
