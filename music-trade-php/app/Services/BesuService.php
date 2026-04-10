<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class BesuService
{
    protected string $rpcUrl;
    protected int $chainId;
    protected int $requestId = 1;

    public function __construct()
    {
        $this->rpcUrl  = config('besu.rpc_url');
        $this->chainId = (int) config('besu.chain_id');
    }

    /**
     * JSON-RPC 요청 공통 메서드
     */
    public function call(string $method, array $params = []): mixed
    {
        $payload = [
            'jsonrpc' => '2.0',
            'method'  => $method,
            'params'  => $params,
            'id'      => $this->requestId++,
        ];

        try {
            $response = Http::timeout(10)
                ->post($this->rpcUrl, $payload);

            $body = $response->json();

            if (isset($body['error'])) {
                Log::error('Besu RPC Error', ['method' => $method, 'error' => $body['error']]);
                throw new \RuntimeException('RPC Error: ' . $body['error']['message']);
            }

            return $body['result'] ?? null;

        } catch (\Exception $e) {
            Log::error('Besu Connection Error', ['message' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * 현재 블록 번호 조회
     */
    public function getBlockNumber(): int
    {
        $hex = $this->call('eth_blockNumber');
        return hexdec($hex);
    }

    /**
     * eth_call (읽기 전용 컨트랙트 호출)
     */
    public function ethCall(string $to, string $data, string $from = null): string
    {
        $params = [
            'to'   => $to,
            'data' => $data,
        ];
        if ($from) {
            $params['from'] = $from;
        }

        return $this->call('eth_call', [$params, 'latest']) ?? '0x';
    }

    /**
     * 트랜잭션 전송 (이미 서명된 rawTx)
     */
    public function sendRawTransaction(string $signedTx): string
    {
        return $this->call('eth_sendRawTransaction', [$signedTx]);
    }

    /**
     * 트랜잭션 영수증 조회
     */
    public function getTransactionReceipt(string $txHash): ?array
    {
        return $this->call('eth_getTransactionReceipt', [$txHash]);
    }

    /**
     * nonce 조회
     */
    public function getNonce(string $address): int
    {
        $hex = $this->call('eth_getTransactionCount', [$address, 'latest']);
        return hexdec($hex);
    }

    /**
     * gas price 조회
     */
    public function getGasPrice(): string
    {
        return $this->call('eth_gasPrice') ?? '0x0';
    }

    /**
     * eth_estimateGas
     */
    public function estimateGas(array $txParams): int
    {
        $hex = $this->call('eth_estimateGas', [$txParams]);
        return hexdec($hex);
    }

    public function getChainId(): int
    {
        return $this->chainId;
    }
}
