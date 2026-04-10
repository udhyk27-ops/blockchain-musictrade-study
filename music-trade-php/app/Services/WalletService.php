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
     * 새 사용자 지갑 생성
     * Ethereum 규격에 맞는 개인키 & 공개키 & 주소 생성
     *
     * 개인키 = 32바이트 랜덤 숫자 - (secp256k1 공개키 → keccak256 → 주소 도출)
     * 공개키 = secp256k1 타원곡선으로 개인키에서 도출 - 여기서만 쓰이고 버림
     * 주소 = 공개키를 keccak256 해시한 뒤 마지막 20바이트 앞에 0x 붙인 것
     *
     * Besu가 트랜잭션을 받으면 서명에서 공개키를 역산해서 주소를 검증하기 때문에 공개키로 주소를 도출해서 DB저장이 필요함
     *
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
