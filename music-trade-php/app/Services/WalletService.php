<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Web3p\EthereumTx\Transaction;
use Web3p\EthereumUtil\Util as EthUtil;

class WalletService
{
    private EthUtil $util;

    public function __construct()
    {
        $this->util = new EthUtil();
    }

    /**
     * 새 Ethereum 지갑 생성
     * 개인키 → secp256k1 공개키 → keccak256 → 주소 도출
     * 개인키는 Laravel encrypt()로 암호화하여 반환
     *
     * @return array{address: string, encrypted_private_key: string}
     */
    public function createWallet(): array
    {
        // 32바이트 랜덤 개인키 생성
        $privateKey = bin2hex(random_bytes(32));

        // 공개키 도출 (비압축형, 04 prefix 제외한 128자)
        $publicKey = $this->util->privateKeyToPublicKey($privateKey);

        // Ethereum 주소 도출 (0x + keccak256(공개키)의 마지막 20바이트)
        $address = '0x' . $this->util->publicKeyToAddress($publicKey);

        return [
            'address'               => $address,
            'encrypted_private_key' => encrypt($privateKey),
        ];
    }

    /**
     * Ethereum 트랜잭션 서명 후 RLP-인코딩된 rawTx 반환
     *
     * @param  string $encryptedPrivateKey  DB에 저장된 암호화 개인키
     * @param  array  $txData               [nonce, from, to, gas, gasPrice, value, data, chainId]
     * @return string                       0x-prefixed signed raw transaction
     */
    public function signTransaction(string $encryptedPrivateKey, array $txData): string
    {
        $privateKey = decrypt($encryptedPrivateKey);

        try {
            $tx = new Transaction($txData);
            $tx->sign($privateKey);
            return '0x' . $tx->serialize();
        } finally {
            // 메모리에서 개인키 즉시 제거
            $privateKey = str_repeat('0', strlen($privateKey));
            unset($privateKey);
        }
    }
}
