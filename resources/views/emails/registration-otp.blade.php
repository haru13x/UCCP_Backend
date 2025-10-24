<!DOCTYPE html>
<html>
<head>
    <title>UCCP Registration OTP</title>
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
            background-color: #4285F4;
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
        .otp-box {
            font-size: 28px;
            font-weight: bold;
            letter-spacing: 4px;
            text-align: center;
            background-color: #fff;
            border: 1px dashed #4285F4;
            padding: 14px 0;
            border-radius: 8px;
            margin: 16px 0;
            color: #222;
        }
        .footer {
            text-align: center;
            margin-top: 20px;
            padding: 20px 0;
            color: #666;
            font-size: 0.9em;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>Your Registration OTP</h1>
    </div>

    <div class="content">
        <p>Dear user,</p>
        <p>Please use the following One-Time Password (OTP) to complete your registration with UCCP:</p>

        <div class="otp-box">{{ $otp }}</div>

        <p>This OTP is valid for <strong>10 minutes</strong>. For your security, do not share this code with anyone.</p>
        <p>If you did not request this, you can safely ignore this email.</p>
    </div>

    <div class="footer">
        <p>This is an automated message. Please do not reply to this email.</p>
        <p>&copy; {{ date('Y') }} UCCP. All rights reserved.</p>
    </div>
</body>
</html>