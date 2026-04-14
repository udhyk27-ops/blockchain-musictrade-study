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
{{--    <link href="{{ asset('css/login.css') }}" rel="stylesheet">--}}
    <style>
        body {
            margin: 0;
            height: 100vh;
        }

        * {
            box-sizing: border-box;
        }

        .wrapper {
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100%;
            padding: 20px;
        }

        .login {
            width: 500px;
        }

        .login input {
            padding: 15px 10px;
            margin-bottom: 10px;
            width: 100%;
        }

        .login button {
            width: 100%;
            height: 50px;
            margin-bottom: 10px;
        }

        a {
            color: #000000;
            text-decoration: none;
            float: right;
        }

    </style>
</head>
<body>

<div class="wrapper">
    <form class="login" action="{{ route('login.store') }}" method="post">
        @csrf
        <h1>블록체인 음악 저작권 정산 시스템</h1>
        <input type="text" name="user_id" placeholder="아이디" value="{{ old('user_id') }}" required autocomplete="off"/>
        <input type="password" name="user_pwd" placeholder="비밀번호" autocomplete="off" required/>
        <button type="submit">
            <span class="state"><b>LOGIN</b></span>
        </button>
        <a href="{{ route('register') }}">회원가입</a>
    </form>
</div>



@error('login')
<script>
    alert("{{ $message }}");
</script>
@enderror
</body>
</html>
