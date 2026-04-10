<?php

namespace App\Http\Controllers;

use App\Models\LicensePurchase;
use App\Models\RoyaltyHistory;
use App\Models\Song;
use App\Models\SongHolder;
use App\Models\User;
use App\Services\BesuService;
use App\Services\ContractService;
use App\Services\WalletService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class Controller
{
    public function index()
    {
        $users = User::whereNotNull('F_WALLET_ADDRESS')->get();
        return view('index', ['users' => $users]);
    }

    private function waitForReceipt(BesuService $besu, string $txHash, int $maxWait = 30): ?array
    {
        $deadline = time() + $maxWait;
        while (time() < $deadline) {
            $receipt = $besu->getTransactionReceipt($txHash);
            if ($receipt !== null) return $receipt;
            sleep(1);
        }
        return null;
    }

    private function getUserWallet(): array
    {
        $user = Auth::user();
        if (!$user->f_wallet_address || !$user->f_private_key) {
            throw new \RuntimeException('지갑 정보가 없습니다.');
        }
        return [
            'address'       => $user->f_wallet_address,
            'encrypted_key' => $user->f_private_key,
            'user_no'       => $user->f_no,
        ];
    }

    private function buildTxParams(BesuService $besu, string $from, string $calldata, int $gas = 300000): array
    {
        return [
            'nonce'    => '0x' . dechex($besu->getNonce($from)),
            'from'     => $from,
            'to'       => config('besu.contract_address'),
            'gas'      => '0x' . dechex($gas),
            'gasPrice' => $besu->getGasPrice(),
            'value'    => '0x0',
            'data'     => $calldata,
            'chainId'  => (int) config('besu.chain_id'),
        ];
    }

    // 1. 곡 등록
    public function registerSong(Request $request, ContractService $contract, BesuService $besu, WalletService $wallet)
    {
        $request->validate(['title' => 'required|string|max:200']);

        try {
            $w        = $this->getUserWallet();
            $calldata = $contract->encodeRegisterSong($request->title);
            $txParams = $this->buildTxParams($besu, $w['address'], $calldata);
            $signedTx = $wallet->signTransaction($w['encrypted_key'], $txParams);
            $txHash   = $besu->sendRawTransaction($signedTx);

            $receipt = $this->waitForReceipt($besu, $txHash);
            if (!$receipt || ($receipt['status'] ?? '0x0') !== '0x1') {
                return response()->json(['success' => false, 'message' => '트랜잭션 실패']);
            }

            $songId = hexdec(ltrim($receipt['logs'][0]['topics'][1] ?? '0x0', '0x'));

            Song::create([
                'f_song_id'      => $songId,
                'f_title'        => $request->title,
                'f_producer_no'  => $w['user_no'],
                'f_active'       => 0,
                'f_tx_hash'      => $txHash,
                'f_block_number' => hexdec($receipt['blockNumber']),
                'f_created_at'   => now(),
                'f_updated_at'   => now(),
            ]);

            return response()->json(['success' => true, 'txHash' => $txHash, 'songId' => $songId]);

        } catch (\Exception $e) {
            Log::error('registerSong failed', ['msg' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    // 2. 지분율 설정
    public function setShares(Request $request, ContractService $contract, BesuService $besu, WalletService $wallet)
    {
        $request->validate([
            'song_id'           => 'required|integer|min:1',
            'holders'           => 'required|array|min:1',
            'holders.*.user_no' => 'required|integer',
            'holders.*.role'    => 'required|integer|between:1,5',
            'holders.*.share'   => 'required|integer|min:1|max:10000',
        ]);

        if (array_sum(array_column($request->holders, 'share')) !== 10000) {
            return response()->json(['success' => false, 'message' => '지분율 합계가 100%이어야 합니다.']);
        }

        try {
            $w           = $this->getUserWallet();
            $holderUsers = User::whereIn('F_NO', array_column($request->holders, 'user_no'))
                ->get()->keyBy('f_no');

            $wallets = $roles = $shares = [];
            foreach ($request->holders as $h) {
                $holderUser = $holderUsers->get($h['user_no']);
                if (!$holderUser?->f_wallet_address) {
                    return response()->json(['success' => false, 'message' => "사용자 #{$h['user_no']}의 지갑주소가 없습니다."]);
                }
                $wallets[] = $holderUser->f_wallet_address;
                $roles[]   = (int) $h['role'];
                $shares[]  = (int) $h['share'];
            }

            $calldata = $contract->encodeSetShares($request->song_id, $wallets, $roles, $shares);
            $txParams = $this->buildTxParams($besu, $w['address'], $calldata);
            $signedTx = $wallet->signTransaction($w['encrypted_key'], $txParams);
            $txHash   = $besu->sendRawTransaction($signedTx);

            $receipt = $this->waitForReceipt($besu, $txHash);
            if (!$receipt || ($receipt['status'] ?? '0x0') !== '0x1') {
                return response()->json(['success' => false, 'message' => '트랜잭션 실패']);
            }

            $song = Song::where('F_SONG_ID', $request->song_id)->first();
            if (!$song) {
                return response()->json(['success' => false, 'message' => 'DB에서 곡을 찾을 수 없습니다.']);
            }

            $blockNumber = hexdec($receipt['blockNumber']);
            $now         = now();

            DB::table('T_SONG_HOLDER')->where('F_SONG_ID', $song->f_id)->delete();

            foreach ($request->holders as $h) {
                SongHolder::create([
                    'f_song_id'      => $song->f_id,
                    'f_user_no'      => $h['user_no'],
                    'f_role'         => $h['role'],
                    'f_share'        => $h['share'],
                    'f_tx_hash'      => $txHash,
                    'f_block_number' => $blockNumber,
                    'f_created_at'   => $now,
                    'f_updated_at'   => $now,
                ]);
            }

            Song::where('F_SONG_ID', $request->song_id)
                ->update(['F_ACTIVE' => 1, 'F_UPDATED_AT' => $now]);

            return response()->json(['success' => true, 'txHash' => $txHash]);

        } catch (\Exception $e) {
            Log::error('setShares failed', ['msg' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    // 3. 라이선스 구매
    public function purchaseLicense(Request $request, ContractService $contract, BesuService $besu, WalletService $wallet)
    {
        $request->validate(['song_id' => 'required|integer|min:1']);

        try {
            $w        = $this->getUserWallet();
            $calldata = $contract->encodePurchaseLicense($request->song_id, 1);
            $txParams = $this->buildTxParams($besu, $w['address'], $calldata, 500000);
            $signedTx = $wallet->signTransaction($w['encrypted_key'], $txParams);
            $txHash   = $besu->sendRawTransaction($signedTx);

            $receipt = $this->waitForReceipt($besu, $txHash, 60);
            if (!$receipt || ($receipt['status'] ?? '0x0') !== '0x1') {
                return response()->json(['success' => false, 'message' => '트랜잭션 실패']);
            }

            $song = Song::where('F_SONG_ID', $request->song_id)->first();
            if (!$song) {
                return response()->json(['success' => false, 'message' => 'DB에서 곡을 찾을 수 없습니다.']);
            }

            $blockNumber = hexdec($receipt['blockNumber']);
            $now         = now();

            LicensePurchase::create([
                'f_song_id'      => $song->f_id,
                'f_buyer_no'     => $w['user_no'],
                'f_amount'       => '1',
                'f_tx_hash'      => $txHash,
                'f_block_number' => $blockNumber,
                'f_purchased_at' => $now,
            ]);

            // 정산 이력 파싱
            $royaltyLogs = array_filter(
                $receipt['logs'] ?? [],
                fn($log) => count($log['topics']) >= 3
                    && strtolower($log['address']) === strtolower(config('besu.contract_address'))
            );

            foreach ($royaltyLogs as $log) {
                $recipientAddr = '0x' . substr($log['topics'][2], -40);
                $recipientUser = User::where('F_WALLET_ADDRESS', $recipientAddr)->first();
                if (!$recipientUser) continue;

                $data = ltrim($log['data'], '0x');
                $role = hexdec(substr($data, 0, 64));

                RoyaltyHistory::create([
                    'f_song_id'      => $song->f_id,
                    'f_recipient_no' => $recipientUser->f_no,
                    'f_role'         => $role,
                    'f_amount'       => '1',
                    'f_tx_hash'      => $txHash,
                    'f_block_number' => $blockNumber,
                    'f_created_at'   => $now,
                ]);
            }

            return response()->json(['success' => true, 'txHash' => $txHash]);

        } catch (\Exception $e) {
            Log::error('purchaseLicense failed', ['msg' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    // 4. 곡 정보 조회
    public function getSongInfo(Request $request)
    {
        $request->validate(['song_id' => 'required|integer|min:1']);

        $song = Song::where('F_SONG_ID', $request->song_id)->first();
        if (!$song) {
            return response()->json(['success' => false, 'message' => '곡을 찾을 수 없습니다.']);
        }

        $producer = User::where('F_NO', $song->f_producer_no)->first();

        return response()->json([
            'success' => true,
            'info'    => [
                'title'       => $song->f_title,
                'producer'    => $producer->f_id ?? '알 수 없음',
                'active'      => (bool) $song->f_active,
                'txHash'      => $song->f_tx_hash,
                'blockNumber' => $song->f_block_number,
            ],
        ]);
    }

    // 5. 지분율 조회
    public function getHolders(Request $request)
    {
        $request->validate(['song_id' => 'required|integer|min:1']);

        $song = Song::where('F_SONG_ID', $request->song_id)->first();
        if (!$song) {
            return response()->json(['success' => false, 'message' => '곡을 찾을 수 없습니다.']);
        }

        $holders = SongHolder::where('F_SONG_ID', $song->f_id)
            ->with('user')
            ->get()
            ->map(fn($h) => [
                'user_id' => $h->user->f_id ?? '알 수 없음',
                'wallet'  => $h->user->f_wallet_address ?? '-',
                'role'    => $h->f_role,
                'share'   => ($h->f_share / 100) . '%',
            ]);

        return response()->json(['success' => true, 'holders' => $holders]);
    }

    // 6. 라이선스 구매 이력
    public function getLicenseHistory(Request $request)
    {
        $query = LicensePurchase::with(['song', 'buyer']);

        if ($request->filled('song_id')) {
            $song = Song::where('F_SONG_ID', $request->song_id)->first();
            if ($song) $query->where('F_SONG_ID', $song->f_id);
        }

        $history = $query->orderByDesc('F_PURCHASED_AT')
            ->limit(200)
            ->get()
            ->map(fn($lp) => [
                'songId'      => $lp->song->f_song_id ?? '-',
                'songTitle'   => $lp->song->f_title ?? '-',
                'buyer'       => $lp->buyer->f_id ?? '-',
                'txHash'      => $lp->f_tx_hash,
                'blockNumber' => $lp->f_block_number,
                'purchasedAt' => $lp->f_purchased_at,
            ]);

        return response()->json(['success' => true, 'count' => $history->count(), 'history' => $history]);
    }

    // 7. 내 정산 이력
    public function getRoyaltyHistory()
    {
        $history = RoyaltyHistory::where('F_RECIPIENT_NO', Auth::user()->f_no)
            ->with('song')
            ->orderByDesc('F_CREATED_AT')
            ->limit(200)
            ->get()
            ->map(fn($rh) => [
                'songId'      => $rh->song->f_song_id ?? '-',
                'songTitle'   => $rh->song->f_title ?? '-',
                'role'        => $rh->f_role,
                'txHash'      => $rh->f_tx_hash,
                'blockNumber' => $rh->f_block_number,
                'createdAt'   => $rh->f_created_at,
            ]);

        return response()->json(['success' => true, 'count' => $history->count(), 'history' => $history]);
    }

    // 8. 전체 곡 수
    public function getSongCount()
    {
        return response()->json(['success' => true, 'songCount' => Song::count()]);
    }
}
