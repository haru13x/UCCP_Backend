<!DOCTYPE html>
<html>
<head>
    <title>Registration Status Update</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
        }
        .header {
            text-align: center;
            padding: 20px 0;
            background-color: {{ $status === 'approved' ? '#4CAF50' : '#f44336' }};
            color: white;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        .content {
            background-color: #f9f9f9;
            padding: 20px;
            border-radius: 5px;
            border: 1px solid #ddd;
        }
        .footer {
            text-align: center;
            margin-top: 20px;
            padding: 20px 0;
            color: #666;
            font-size: 0.9em;
        }
        .button {
            display: inline-block;
            padding: 10px 20px;
            background-color: {{ $status === 'approved' ? '#4CAF50' : '#f44336' }};
            color: white;
            text-decoration: none;
            border-radius: 5px;
            margin-top: 15px;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>Registration {{ ucfirst($status) }}</h1>
    </div>
    
    <div class="content">
        <p>Dear {{ $name }},</p>
        
        @if($status === 'approved')
            <p>Your registration request has been approved! You can now log in to your account using your email and password.</p>
            <p>Welcome to our community!</p>
            <center>
                <a href="{{ env('FRONTEND_URL') }}/login" class="button">Login Now</a>
            </center>
        @else
            <p>We regret to inform you that your registration request has been declined.</p>
            <p><strong>Reason for decline:</strong></p>
            <p>{{ $reason }}</p>
            <p>If you believe this was a mistake or would like to submit a new registration request with updated information, please feel free to do so.</p>
        @endif
    </div>

    <div class="footer">
        <p>This is an automated message. Please do not reply to this email.</p>
        <p>&copy; {{ date('Y') }} UCCP. All rights reserved.</p>
    </div>
</body>
</html>