<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>{{ $bot->name }}</title>
</head>
<body>
    <div id="docschat-app"></div>
    <script id="docschat-config" type="application/json">@json($config, JSON_HEX_TAG | JSON_HEX_AMP)</script>
    <script src="{{ asset('widget.js') }}"></script>
</body>
</html>
