<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $classroom->name }} – Live Class</title>
</head>
<body>
    <div id="jitsi-container" style="height:300px;"></div>

    <script src="https://meet.jit.si/external_api.js"></script>
    <script>
        const domain = "meet.jit.si";
        const options = {
            roomName: "{{ $classroom->room_code }}",
            parentNode: document.querySelector('#jitsi-container'),
            userInfo: {
                displayName: "{{ auth()->user()->full_name ?? 'Guest' }}"
            }
        };
        const api = new JitsiMeetExternalAPI(domain, options);
    </script>
</body>
</html>
