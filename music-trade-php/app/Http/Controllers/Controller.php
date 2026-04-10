<?php

namespace App\Http\Controllers;

use App\Services\ContractService;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class Controller
{
    protected ContractService $contract;

    public function __construct(ContractService $contract)
    {
        $this->contract = $contract;
    }

    /**
     * 테스트 페이지
     */
    public function index()
    {
        $songCount = 0;
        $error     = null;

        try {
            $songCount = $this->contract->getSongCount();
        } catch (\Exception $e) {
            $error = $e->getMessage();
        }

        // 지분율 설정 - 유저 목록 DB 조회
        $users = User::whereNotNull('F_WALLET_ADDRESS')->get();

        return view('index', [
            'songCount'       => $songCount,
            'contractAddress' => config('besu.contract_address'),
            'chainId'         => config('besu.chain_id'),
            'users'           => $users,
            'error'           => $error,
        ]);
    }

    /**
     * 곡 정보 조회 (AJAX)
     */
    public function getSongInfo(Request $request)
    {
        $request->validate(['song_id' => 'required|integer|min:1']);
        try {
            $info    = $this->contract->getSongInfo($request->song_id);
            $holders = $this->contract->getHolders($request->song_id);
            return response()->json(['success' => true, 'info' => $info, 'holders' => $holders]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    /**
     * registerSong calldata 반환
     */
    public function encodeRegister(Request $request)
    {
        $request->validate(['title' => 'required|string']);
        try {
            $calldata = $this->contract->encodeRegisterSong($request->title);
            return response()->json(['success' => true, 'calldata' => $calldata]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    /**
     * setShares calldata 반환
     */
    public function encodeSetShares(Request $request)
    {
        $request->validate([
            'song_id' => 'required|integer',
            'wallets' => 'required|array',
            'roles'   => 'required|array',
            'shares'  => 'required|array',
        ]);
        try {
            $calldata = $this->contract->encodeSetShares(
                $request->song_id,
                $request->wallets,
                $request->roles,
                $request->shares
            );
            return response()->json(['success' => true, 'calldata' => $calldata]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    /**
     * purchaseLicense calldata 반환
     */
    public function encodePurchase(Request $request)
    {
        $request->validate(['song_id' => 'required|integer']);
        try {
            $calldata = $this->contract->encodePurchaseLicense($request->song_id);
            $valueHex = '0x' . dechex((int)(floatval($request->amount ?? 0.01) * 1e18));
            return response()->json([
                'success'  => true,
                'calldata' => $calldata,
                'value'    => $valueHex,
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    public function updateWallet(Request $request)
    {
        $request->validate([
            'wallet_address' => ['required', 'string', 'regex:/^0x[0-9a-fA-F]{40}$/', 'unique:t_user,f_wallet_address,' . Auth::id() . ',f_id'],
        ]);

        DB::table('T_USER')
            ->where('F_ID', Auth::user()->f_id)
            ->update(['F_WALLET_ADDRESS' => $request->wallet_address]);

        session(['wallet_address' => $request->wallet_address]); // 세션에 다시 저장
        return response()->json(['success' => true]);
    }
}
