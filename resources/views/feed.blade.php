<!-- resources/views/feed.blade.php -->
<!DOCTYPE html>
<html lang="zh-Hant">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>即時資訊流</title>
    @viteReactRefresh
    @vite('resources/js/pages/Feed.jsx')
</head>
<body class="bg-gray-100">
    <div id="feed-app"></div>
</body>
</html>
