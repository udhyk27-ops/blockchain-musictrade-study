<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    {{-- 캐시 관련 --}}
    <meta http-equiv="Cache-Control" content="no-store, no-cache, must-revalidate, max-age=0">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">

    <title>MusicTrade</title>
    <link href="{{ asset('css/login.css') }}" rel="stylesheet">

</head>
<body>

<div class="wrapper">
    <form class="login" action="{{ route('login.store') }}" method="post">
        @csrf
        <p class="title">블록체인 음악 저작권 정산 시스템</p>
        <input type="text" name="user_id" placeholder="아이디" value="{{ old('user_id') }}" required/>
        <input type="password" name="user_pwd" placeholder="비밀번호" autocomplete="off" required/>
        <button type="submit">
            <span class="state">LOGIN</span>
        </button>
    </form>
</div>



@error('login')
<script>
    alert("{{ $message }}");
</script>
@enderror
</body>
</html>
