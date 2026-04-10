<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\WalletService;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class LoginController extends Controller
{
    protected WalletService $wallet;

    public function __construct(WalletService $wallet)
    {
        $this->wallet = $wallet;
    }

    // ──────────────────────────────────────────────────
    // 로그인
    // ──────────────────────────────────────────────────

    public function index()
    {
        return view('login');
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'user_id'  => ['required', 'string'],
            'user_pwd' => ['required', 'string'],
        ]);

        $credentials = [
            'F_ID'      => $request->user_id,
            'password'  => $request->user_pwd,
            'F_STATUS'  => 'Y',
        ];

        if (Auth::attempt($credentials)) {
            $request->session()->regenerate();
            return redirect()->intended(route('index'));
        }

        return back()->withInput($request->only('user_id'))
                     ->withErrors(['login' => '아이디 또는 비밀번호가 일치하지 않습니다.']);
    }

    public function destroy(): RedirectResponse
    {
        Auth::logout();
        session()->invalidate();
        session()->regenerateToken();
        return redirect()->route('login');
    }

    // ──────────────────────────────────────────────────
    // 회원가입
    // ──────────────────────────────────────────────────

    public function registerForm()
    {
        return view('register');
    }

    public function register(Request $request): RedirectResponse
    {
        $request->validate([
            'user_id'   => ['required', 'string', 'max:32', 'regex:/^[a-zA-Z0-9_]+$/',
                            'unique:T_USER,F_ID'],
            'user_name' => ['required', 'string', 'max:50'],
            'user_mail' => ['required', 'email', 'max:128'],
            'user_pwd'  => ['required', 'string', 'min:4', 'confirmed'],
        ]);

        // 서버에서 지갑 자동 생성
        $walletData = $this->wallet->createWallet();

        DB::table('T_USER')->insert([
            'F_ID'             => $request->user_id,
            'F_PASSWORD'       => bcrypt($request->user_pwd),
            'F_NAME'           => $request->user_name,
            'F_MAIL'           => $request->user_mail,
            'F_WALLET_ADDRESS' => $walletData['address'],
            'F_PRIVATE_KEY'    => $walletData['encrypted_private_key'],
            'F_CREATED_AT'     => now(),
            'F_UPDATED_AT'     => now(),
            'F_STATUS'         => 'Y',
            'F_ROLE'           => 'user',
        ]);

        return redirect()->route('login')
                         ->with('success', '회원가입이 완료되었습니다. 로그인해주세요.');
    }
}
