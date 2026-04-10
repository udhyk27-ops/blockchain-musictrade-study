<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <title>회원가입 - Music Royalty</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: Arial, sans-serif; display: flex; justify-content: center; align-items: center; min-height: 100vh; background: #f5f5f5; }
        .card { background: #fff; padding: 32px 36px; border: 1px solid #ddd; width: 360px; }
        h1 { font-size: 18px; margin-bottom: 20px; text-align: center; }
        label { display: block; font-size: 13px; margin-bottom: 4px; color: #555; }
        input { width: 100%; padding: 8px 10px; border: 1px solid #ccc; font-size: 13px; margin-bottom: 14px; }
        button { width: 100%; padding: 10px; background: #333; color: #fff; border: none; font-size: 14px; cursor: pointer; }
        button:hover { background: #555; }
        .error { color: #c00; font-size: 12px; margin-top: -10px; margin-bottom: 10px; }
        .info { font-size: 12px; color: #888; margin-bottom: 16px; padding: 8px; background: #f9f9f9; border: 1px solid #eee; }
        .link { text-align: center; margin-top: 14px; font-size: 13px; }
        .link a { color: #333; }
        .success { color: green; font-size: 13px; margin-bottom: 12px; }
    </style>
</head>
<body>
<div class="card">
    <h1>회원가입</h1>

    @if(session('success'))
        <p class="success">{{ session('success') }}</p>
    @endif

    <div class="info">
        가입 시 Ethereum 지갑이 자동으로 생성됩니다.<br>
        개인키는 서버에 안전하게 암호화 저장됩니다.
    </div>

    <form method="POST" action="{{ route('register.store') }}">
        @csrf

        <label>아이디</label>
        <input type="text" name="user_id" value="{{ old('user_id') }}"
               placeholder="영문, 숫자, 언더스코어 (최대 32자)" required>
        @error('user_id')
            <p class="error">{{ $message }}</p>
        @enderror

        <label>이름</label>
        <input type="text" name="user_name" value="{{ old('user_name') }}"
               placeholder="실명 또는 닉네임" required>
        @error('user_name')
            <p class="error">{{ $message }}</p>
        @enderror

        <label>이메일</label>
        <input type="email" name="user_mail" value="{{ old('user_mail') }}"
               placeholder="example@email.com" required>
        @error('user_mail')
            <p class="error">{{ $message }}</p>
        @enderror

        <label>비밀번호</label>
        <input type="password" name="user_pwd" placeholder="4자 이상" required>
        @error('user_pwd')
            <p class="error">{{ $message }}</p>
        @enderror

        <label>비밀번호 확인</label>
        <input type="password" name="user_pwd_confirmation" placeholder="비밀번호 재입력" required>

        <button type="submit">가입하기</button>
    </form>

    <p class="link"><a href="{{ route('login') }}">← 로그인으로 돌아가기</a></p>
</div>
</body>
</html>
