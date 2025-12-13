<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>パスワードリセット</title>
</head>
<body>
    <h1>パスワードリセットのご案内</h1>

    <p>{{ $teacher->name }} 様</p>

    <p>パスワードリセットのリクエストを受け付けました。</p>

    <p>以下のリンクをクリックして、新しいパスワードを設定してください。</p>

    <p>
        <a href="{{ config('app.url') }}/reset-password?token={{ $token }}&email={{ $teacher->email }}">
            パスワードをリセットする
        </a>
    </p>

    <p>または、以下のURLをブラウザにコピー＆ペーストしてください：</p>
    <p>{{ config('app.url') }}/reset-password?token={{ $token }}&email={{ $teacher->email }}</p>

    <hr>

    <p><strong>このリンクは1時間有効です。</strong></p>

    <p>※ このメールに心当たりがない場合は、無視してください。</p>

    <p>TimeBook</p>
</body>
</html>