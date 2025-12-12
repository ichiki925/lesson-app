<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>レッスン予約完了</title>
</head>
<body>
    <h1>レッスン予約が完了しました</h1>

    <p>{{ $reservation->student_name }} 様</p>

    <p>レッスンの予約が完了しました。</p>

    <h2>予約内容</h2>
    <ul>
        <li>レッスン日: {{ \Carbon\Carbon::parse($reservation->lessonSlot->date)->format('Y年m月d日') }}</li>
        <li>開始時刻: {{ \Carbon\Carbon::parse($reservation->lessonSlot->start_time)->format('H:i') }}</li>
    </ul>

    <p>予約ID: {{ $reservation->id }}</p>

    <hr>

    <h3>キャンセルについて</h3>
    <p>キャンセルをご希望の場合は、以下のリンクからお手続きください。</p>
    <p>キャンセル用URL: {{ config('app.url') }}/cancel/{{ $reservation->cancel_token }}</p>

    <hr>

    <p>ご不明な点がございましたら、お気軽にお問い合わせください。</p>

    <p>TimeBook</p>
</body>
</html>