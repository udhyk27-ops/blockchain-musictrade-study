<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

class LoginController extends Controller
{
    /**
     * 로그인 페이지
     */
    public function index()
    {
        return view('login');
    }

    /**
     * 로그인
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([ // 빈 값 확인
           'user_id' => ['required', 'string'],
           'user_pwd' => ['required', 'string'],
        ]);

        $credentials = [
            'F_ID' => $request->user_id,
            'password' => $request->user_pwd,
            'F_STATUS' => 'Y',
        ];

        if (Auth::attempt($credentials)) { // login 인증 - 세션에 ID 저장
            $request->session()->regenerate(); // 세션 재생성
            $wallet_address = Auth::user()->f_wallet_address; // 세션 지갑주소 확인
            if ($wallet_address) {
                session(['wallet_address' => $wallet_address]); // 세션 지갑주소 추가
            }
            return redirect()->intended(route('index')); // intended -> 접근하려 했던 페이지로 보냄
        }

        return back()->withInput($request->only('user_id'))
                     ->withErrors(['login' => '아이디 또는 비밀번호가 일치하지 않습니다.']);
    }

    /**
     * 로그아웃
     */
    public function destroy(): RedirectResponse
    {
        Auth::logout(); // 세션에서 인증 정보 제거
        session()->invalidate(); // 세션 데이터 전체 삭제 + 새 세션 ID 발급
        session()->regenerateToken(); // CSRF 토큰 재발급
        return redirect()->route('login');
    }
}
