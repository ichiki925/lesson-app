<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>レッスン予約キャンセル</title>
</head>
<body>
    <h1>レッスン予約がキャンセルされました</h1>

    <p>{{ $reservation->student_name }} 様</p>

    <p>以下のレッスン予約がキャンセルされました。</p>

    <h2>キャンセルされた予約内容</h2>
    <ul>
        <li>レッスン日: {{ \Carbon\Carbon::parse($reservation->lessonSlot->date)->format('Y年m月d日') }}</li>
        <li>開始時刻: {{ \Carbon\Carbon::parse($reservation->lessonSlot->start_time)->format('H:i') }}</li>
    </ul>

    <p>予約ID: {{ $reservation->id }}</p>
    <p>キャンセル日時: {{ \Carbon\Carbon::parse($reservation->canceled_at)->format('Y年m月d日 H:i') }}</p>

    <hr>

    <p>またのご利用をお待ちしております。</p>

    <p>TimeBook</p>
</body>
</html>