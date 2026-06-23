<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>WNBA Stat Spot | Your Ultimate WNBA Statistics Dashboard</title>
    <meta name="description" content="WNBA Stat Spot - Your ultimate destination for WNBA statistics, player analytics, team data, and game insights. Comprehensive basketball analytics dashboard.">
    <meta name="author" content="WNBA Stat Spot">
    <meta name="keywords" content="WNBA, basketball, statistics, analytics, players, teams, games, dashboard">

    <!-- Favicon -->
    <link rel="shortcut icon" href="/favicon.ico">

    <!-- Font Awesome for icons -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
</head>
<body>
    <div id="app">
        <div class="loading-container" style="display: flex; justify-content: center; align-items: center; height: 100vh; flex-direction: column;">
            <div class="spinner" style="border: 4px solid #f3f3f3; border-top: 4px solid #3498db; border-radius: 50%; width: 40px; height: 40px; animation: spin 2s linear infinite;"></div>
            <p style="margin-top: 20px; color: #666;">Loading WNBA Stat Spot...</p>
        </div>
    </div>

    <style>
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>
</body>
</html>
