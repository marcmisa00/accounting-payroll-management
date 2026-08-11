<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Accounting Portal Verification</title>

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            font-family: "Segoe UI", Arial, sans-serif;
            background: linear-gradient(135deg, #4b79a1, #283e51);
            min-height: 100vh;
            margin: 0;

            display: flex;
            justify-content: center;
            align-items: center;
        }

        .verify-box {
            background: #ffffff;
            width: 380px;
            padding: 40px 35px;
            border-radius: 18px;

            box-shadow: 0 10px 25px rgba(0,0,0,0.2);

            text-align: center;
        }

        h2 {
            margin-top: 0;
            color: #222;
            font-size: 24px;
        }

        p {
            color: #555;
            margin-bottom: 25px;
        }

        input[type="text"] {
            width: 60%;
            padding: 14px;

            font-size: 18px;

            border: 2px solid #ddd;
            border-radius: 10px;

            text-align: center;
        }

        input[type="text"]:focus {
            border-color: #4b79a1;
            outline: none;
        }

        button {
            width: 100%;
            padding: 14px;

            font-size: 17px;

            background: #4b79a1;
            color: white;

            border: none;
            border-radius: 10px;

            cursor: pointer;
            font-weight: 600;
        }

        button:hover {
            background: #3a637f;
        }
    </style>
</head>

<body>

<div class="verify-box">

    <h2>Accounting Portal Verification</h2>

        <p>
            Please enter the 6-digit code from your
            Google Authenticator app.
        </p>

        @if ($has2fa)
            <p style="color: green;">
                ✓ Google Authenticator is registered.
            </p>
        @else
            <p style="color: #c0392b;">
                Google Authenticator is not registered for this account.
            </p>
        @endif

  <form method="POST" action="{{ route('accounting.verify.code') }}">
    @csrf

    <input
        type="text"
        name="code"
        maxlength="6"
        inputmode="numeric"
        placeholder="Enter code"
        required
    >

    <br><br>

    <button type="submit">Verify</button>
</form>

</div>
</body>
</html>