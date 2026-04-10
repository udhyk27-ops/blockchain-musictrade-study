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
    // 블록체인 서비스는 TX 라우트에서만 메서드 인젝션으로 사용
    // → DB 조회 라우트에서 Besu 연결 시도 없음 (지연 없음)

    // ──────────────────────────────────────────────────
    // 대시보드
    // ──────────────────────────────────────────────────

    public function index()
    {
        $songCount = Song::count();
        $users     = User::whereNotNull('F_WALLET_ADDRESS')->get();

        return view('index', [
            'songCount' => $songCount,
            'users'     => $users,
        ]);
    }

    // ──────────────────────────────────────────────────
    // 공통 유틸: receipt 폴링 (최대 $maxWait초 대기)
    // ──────────────────────────────────────────────────

    private function waitForReceipt(BesuService $besu, string $txHash, int $maxWait = 30): ?array
    {
        $deadline = time() + $maxWait;
        while (time() < $deadline) {
            $receipt = $besu->getTransactionReceipt($txHash);
            if ($receipt !== null) {
                return $receipt;
            }
            sleep(1);
        }
        return null;
    }

    /**
     * 로그인 사용자의 지갑 정보 조회 및 유효성 검증
     */
    private function getUserWallet(): array
    {
        $user = Auth::user();
        if (!$user->f_wallet_address || !$user->f_private_key) {
            throw new \RuntimeException('지갑 정보가 없습니다. 관리자에게 문의하세요.');
        }
        return [
            'address'       => $user->f_wallet_address,
            'encrypted_key' => $user->f_private_key,
            'user_no'       => $user->f_no,
        ];
    }

    /**
     * 기본 TX 파라미터 구성
     */
    private function buildTxParams(BesuService $besu, string $from, string $calldata, string $value = '0x0', int $gas = 300000): array
    {
        $nonce    = $besu->getNonce($from);
        $gasPrice = $besu->getGasPrice();

        return [
            'nonce'    => '0x' . dechex($nonce),
            'from'     => $from,
            'to'       => config('besu.contract_address'),
            'gas'      => '0x' . dechex($gas),
            'gasPrice' => $gasPrice,
            'value'    => $value,
            'data'     => $calldata,
            'chainId'  => (int) config('besu.chain_id'),
        ];
    }

    // ──────────────────────────────────────────────────
    // 1. 곡 등록 (서버 사이드 서명 + DB 저장)
    // ──────────────────────────────────────────────────

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
                return response()->json(['success' => false, 'message' => '트랜잭션이 실패했습니다.']);
            }

            // 이벤트 로그에서 songId 파싱 (topics[1])
            $songId = hexdec(ltrim($receipt['logs'][0]['topics'][1] ?? '0x0', '0x'));

            // DB 저장
            Song::create([
                'f_song_id'     => $songId,
                'f_title'       => $request->title,
                'f_producer_no' => $w['user_no'],
                'f_active'      => 1,
                'f_tx_hash'     => $txHash,
                'f_block_number'=> hexdec($receipt['blockNumber']),
                'f_created_at'  => now(),
                'f_updated_at'  => now(),
            ]);

            return response()->json([
                'success' => true,
                'txHash'  => $txHash,
                'songId'  => $songId,
            ]);

        } catch (\Exception $e) {
            Log::error('registerSong failed', ['msg' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    // ──────────────────────────────────────────────────
    // 2. 지분율 설정 (서버 사이드 서명 + DB 저장)
    // ──────────────────────────────────────────────────

    public function setShares(Request $request, ContractService $contract, BesuService $besu, WalletService $wallet)
    {
        $request->validate([
            'song_id'  => 'required|integer|min:1',
            'holders'  => 'required|array|min:1',
            'holders.*.user_no' => 'required|integer',
            'holders.*.role'    => 'required|integer|between:1,5',
            'holders.*.share'   => 'required|integer|min:1|max:10000',
        ]);

        // 합계 검증 (10000 = 100%)
        $total = array_sum(array_column($request->holders, 'share'));
        if ($total !== 10000) {
            return response()->json(['success' => false, 'message' => '지분율 합계가 100%이어야 합니다.']);
        }

        try {
            $w = $this->getUserWallet();

            // DB의 blockchain song_id로 사용자 지갑주소 조회
            $holderUsers = User::whereIn('F_NO', array_column($request->holders, 'user_no'))
                               ->get()
                               ->keyBy('f_no');

            $wallets = [];
            $roles   = [];
            $shares  = [];
            foreach ($request->holders as $h) {
                $holderUser = $holderUsers->get($h['user_no']);
                if (!$holderUser || !$holderUser->f_wallet_address) {
                    return response()->json([
                        'success' => false,
                        'message' => "사용자 #{$h['user_no']}의 지갑주소가 없습니다.",
                    ]);
                }
                $wallets[] = $holderUser->f_wallet_address;
                $roles[]   = (int) $h['role'];
                $shares[]  = (int) $h['share'];
            }

            $calldata = $contract->encodeSetShares(
                $request->song_id, $wallets, $roles, $shares
            );
            $txParams = $this->buildTxParams($besu, $w['address'], $calldata);
            $signedTx = $wallet->signTransaction($w['encrypted_key'], $txParams);
            $txHash   = $besu->sendRawTransaction($signedTx);

            $receipt = $this->waitForReceipt($besu, $txHash);
            if (!$receipt || ($receipt['status'] ?? '0x0') !== '0x1') {
                return response()->json(['success' => false, 'message' => '트랜잭션이 실패했습니다.']);
            }

            $blockNumber = hexdec($receipt['blockNumber']);
            $now         = now();

            // T_SONG.F_ID 조회 (블록체인 songId → 내부 DB PK)
            $song = Song::where('F_SONG_ID', $request->song_id)->first();
            if (!$song) {
                return response()->json(['success' => false, 'message' => 'DB에서 곡을 찾을 수 없습니다.']);
            }

            // 기존 홀더 삭제 후 재등록
            DB::table('T_SONG_HOLDER')->where('F_SONG_ID', $song->f_id)->delete();

            foreach ($request->holders as $i => $h) {
                SongHolder::create([
                    'f_song_id'    => $song->f_id,
                    'f_user_no'    => $h['user_no'],
                    'f_role'       => $h['role'],
                    'f_share'      => $h['share'],
                    'f_tx_hash'    => $txHash,
                    'f_block_number' => $blockNumber,
                    'f_created_at' => $now,
                    'f_updated_at' => $now,
                ]);
            }

            return response()->json(['success' => true, 'txHash' => $txHash]);

        } catch (\Exception $e) {
            Log::error('setShares failed', ['msg' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    // ──────────────────────────────────────────────────
    // 3. 라이선스 구매 (서버 사이드 서명 + DB 저장)
    // ──────────────────────────────────────────────────

    public function purchaseLicense(Request $request, ContractService $contract, BesuService $besu, WalletService $wallet)
    {
        $request->validate([
            'song_id' => 'required|integer|min:1',
            'amount'  => 'required|numeric|min:0.000000000000000001',
        ]);

        try {
            $w = $this->getUserWallet();

            // ETH → wei (bcmath 사용)
            $amountEth = number_format((float) $request->amount, 18, '.', '');
            $amountWei = bcmul($amountEth, bcpow('10', '18', 0), 0);
            $valueHex  = '0x' . base_convert($amountWei, 10, 16);

            $calldata = $contract->encodePurchaseLicense($request->song_id);
            $txParams = $this->buildTxParams($besu, $w['address'], $calldata, $valueHex, 500000);
            $signedTx = $wallet->signTransaction($w['encrypted_key'], $txParams);
            $txHash   = $besu->sendRawTransaction($signedTx);

            $receipt = $this->waitForReceipt($besu, $txHash, 60);
            if (!$receipt || ($receipt['status'] ?? '0x0') !== '0x1') {
                return response()->json(['success' => false, 'message' => '트랜잭션이 실패했습니다.']);
            }

            $blockNumber = hexdec($receipt['blockNumber']);
            $now         = now();

            // T_SONG.F_ID 조회
            $song = Song::where('F_SONG_ID', $request->song_id)->first();
            if (!$song) {
                return response()->json(['success' => false, 'message' => 'DB에서 곡을 찾을 수 없습니다.']);
            }

            // 라이선스 구매 이력 저장
            LicensePurchase::create([
                'f_song_id'    => $song->f_id,
                'f_buyer_no'   => $w['user_no'],
                'f_amount'     => $amountWei,
                'f_tx_hash'    => $txHash,
                'f_block_number' => $blockNumber,
                'f_purchased_at' => $now,
            ]);

            // 정산 이력 파싱 (RoyaltyPaid 이벤트: topics[0]=sig, topics[1]=songId, topics[2]=recipient)
            $royaltyLogs = array_filter(
                $receipt['logs'] ?? [],
                fn($log) => count($log['topics']) >= 3
                         && strtolower($log['address']) === strtolower(config('besu.contract_address'))
            );

            foreach ($royaltyLogs as $log) {
                // topics[2] = recipient address (32바이트 패딩)
                $recipientAddr = '0x' . substr($log['topics'][2], -40);
                $recipientUser = User::where('F_WALLET_ADDRESS', $recipientAddr)->first();
                if (!$recipientUser) continue;

                // data: role(uint8, 32bytes) + amount(uint256, 32bytes)
                $data   = ltrim($log['data'], '0x');
                $role   = hexdec(substr($data, 0, 64));
                $amount = base_convert(substr($data, 64, 64), 16, 10);

                RoyaltyHistory::create([
                    'f_song_id'      => $song->f_id,
                    'f_recipient_no' => $recipientUser->f_no,
                    'f_role'         => $role,
                    'f_amount'       => $amount,
                    'f_tx_hash'      => $txHash,
                    'f_block_number' => $blockNumber,
                    'f_created_at'   => $now,
                ]);
            }

            // 곡 총 수익 업데이트
            $prevRevenue = $song->f_total_revenue ?? '0';
            $newRevenue  = bcadd($prevRevenue, $amountWei, 0);
            DB::table('T_SONG')
                ->where('F_SONG_ID', $request->song_id)
                ->update(['F_TOTAL_REVENUE' => $newRevenue, 'F_UPDATED_AT' => $now]);

            return response()->json([
                'success' => true,
                'txHash'  => $txHash,
                'amount'  => $request->amount . ' AID',
            ]);

        } catch (\Exception $e) {
            Log::error('purchaseLicense failed', ['msg' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    // ──────────────────────────────────────────────────
    // 4. 곡 정보 조회 (DB)
    // ──────────────────────────────────────────────────

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
                'title'        => $song->f_title,
                'producer'     => $producer->f_id ?? '알 수 없음',
                'active'       => (bool) $song->f_active,
                'totalRevenue' => $this->weiToEth($song->f_total_revenue ?? '0') . ' AID',
                'txHash'       => $song->f_tx_hash,
                'blockNumber'  => $song->f_block_number,
            ],
        ]);
    }

    // ──────────────────────────────────────────────────
    // 5. 지분율 조회 (DB)
    // ──────────────────────────────────────────────────

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

    // ──────────────────────────────────────────────────
    // 6. 라이선스 구매 이력 조회 (DB)
    // ──────────────────────────────────────────────────

    public function getLicenseHistory(Request $request)
    {
        $query = LicensePurchase::with(['song', 'buyer']);

        if ($request->filled('song_id')) {
            $song = Song::where('F_SONG_ID', $request->song_id)->first();
            if ($song) {
                $query->where('F_SONG_ID', $song->f_id);
            }
        }

        $history = $query->orderByDesc('F_PURCHASED_AT')
                         ->limit(200)
                         ->get()
                         ->map(fn($lp) => [
                             'songId'      => $lp->song->f_song_id ?? '-',
                             'songTitle'   => $lp->song->f_title ?? '-',
                             'buyer'       => $lp->buyer->f_id ?? '-',
                             'amount'      => $this->weiToEth($lp->f_amount) . ' AID',
                             'txHash'      => $lp->f_tx_hash,
                             'blockNumber' => $lp->f_block_number,
                             'purchasedAt' => $lp->f_purchased_at,
                         ]);

        return response()->json(['success' => true, 'count' => $history->count(), 'history' => $history]);
    }

    // ──────────────────────────────────────────────────
    // 7. 내 정산 이력 조회 (DB)
    // ──────────────────────────────────────────────────

    public function getRoyaltyHistory()
    {
        $userNo = Auth::user()->f_no;

        $history = RoyaltyHistory::where('F_RECIPIENT_NO', $userNo)
                                 ->with('song')
                                 ->orderByDesc('F_CREATED_AT')
                                 ->limit(200)
                                 ->get()
                                 ->map(fn($rh) => [
                                     'songId'      => $rh->song->f_song_id ?? '-',
                                     'songTitle'   => $rh->song->f_title ?? '-',
                                     'role'        => $rh->f_role,
                                     'amount'      => $this->weiToEth($rh->f_amount) . ' AID',
                                     'txHash'      => $rh->f_tx_hash,
                                     'blockNumber' => $rh->f_block_number,
                                     'createdAt'   => $rh->f_created_at,
                                 ]);

        return response()->json(['success' => true, 'count' => $history->count(), 'history' => $history]);
    }

    // ──────────────────────────────────────────────────
    // 8. 전체 곡 수 조회 (DB)
    // ──────────────────────────────────────────────────

    public function getSongCount()
    {
        return response()->json(['success' => true, 'songCount' => Song::count()]);
    }

    // ──────────────────────────────────────────────────
    // 유틸
    // ──────────────────────────────────────────────────

    private function weiToEth(string $wei): string
    {
        if (!$wei || $wei === '0') return '0';
        return rtrim(rtrim(bcdiv($wei, bcpow('10', '18', 0), 8), '0'), '.');
    }
}
