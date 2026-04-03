<?php

namespace App\Services;

use Web3\Web3;
use Web3\Contract;
use Web3\Providers\HttpProvider;
use Web3\RequestManagers\HttpRequestManager;

class ContractService
{
    protected Web3 $web3;
    protected Contract $contract;
    protected string $contractAddress;

    // ABI 정의
    protected array $abi = [
        // getSongCount
        ['name' => 'getSongCount', 'type' => 'function', 'stateMutability' => 'view',
            'inputs' => [], 'outputs' => [['name' => '', 'type' => 'uint256']]],

        // getSongInfo
        ['name' => 'getSongInfo', 'type' => 'function', 'stateMutability' => 'view',
            'inputs' => [['name' => 'songId', 'type' => 'uint256']],
            'outputs' => [
                ['name' => 'title',        'type' => 'string'],
                ['name' => 'producer',     'type' => 'address'],
                ['name' => 'active',       'type' => 'bool'],
                ['name' => 'totalRevenue', 'type' => 'uint256'],
                ['name' => 'holderCount',  'type' => 'uint256'],
            ]],

        // getHolders
        ['name' => 'getHolders', 'type' => 'function', 'stateMutability' => 'view',
            'inputs' => [['name' => 'songId', 'type' => 'uint256']],
            'outputs' => [
                ['name' => 'wallets', 'type' => 'address[]'],
                ['name' => 'roles',   'type' => 'uint8[]'],
                ['name' => 'shares',  'type' => 'uint256[]'],
            ]],

        // registerSong
        ['name' => 'registerSong', 'type' => 'function', 'stateMutability' => 'nonpayable',
            'inputs' => [['name' => 'title', 'type' => 'string']],
            'outputs' => [['name' => '', 'type' => 'uint256']]],

        // setShares
        ['name' => 'setShares', 'type' => 'function', 'stateMutability' => 'nonpayable',
            'inputs' => [
                ['name' => 'songId',  'type' => 'uint256'],
                ['name' => 'wallets', 'type' => 'address[]'],
                ['name' => 'roles',   'type' => 'uint8[]'],
                ['name' => 'shares',  'type' => 'uint256[]'],
            ], 'outputs' => []],

        // purchaseLicense
        ['name' => 'purchaseLicense', 'type' => 'function', 'stateMutability' => 'payable',
            'inputs' => [['name' => 'songId', 'type' => 'uint256']],
            'outputs' => []],
    ];

    public function __construct()
    {
        $rpcUrl = config('besu.rpc_url');
        $this->contractAddress = config('besu.contract_address');

        $this->web3 = new Web3(new HttpProvider(new HttpRequestManager($rpcUrl, 10)));
        $this->contract = new Contract($this->web3->provider, json_encode($this->abi));
        $this->contract->at($this->contractAddress);
    }

    // =========================================================
    // 읽기 함수 (eth_call)
    // =========================================================

    public function getSongCount(): int
    {
        $result = null;
        $this->contract->call('getSongCount', function ($err, $res) use (&$result) {
            if ($err) throw new \RuntimeException($err->getMessage());
            $result = (int) $res[0]->toString();
        });
        return $result ?? 0;
    }

    public function getSongInfo(int $songId): array
    {
        $result = null;
        $this->contract->call('getSongInfo', $songId, function ($err, $res) use (&$result) {
            if ($err) throw new \RuntimeException($err->getMessage());
            $result = [
                'title'        => $res['title'],
                'producer'     => $res['producer'],
                'active'       => $res['active'],
                'totalRevenue' => $res['totalRevenue']->toString(),
                'holderCount'  => (int) $res['holderCount']->toString(),
            ];
        });
        return $result ?? [];
    }

    public function getHolders(int $songId): array
    {
        $result = null;
        $this->contract->call('getHolders', $songId, function ($err, $res) use (&$result) {
            if ($err) throw new \RuntimeException($err->getMessage());
            $result = [
                'wallets' => $res['wallets'],
                'roles'   => array_map(fn($r) => (int) $r->toString(), $res['roles']),
                'shares'  => array_map(fn($s) => $s->toString(), $res['shares']),
            ];
        });
        return $result ?? [];
    }

    // =========================================================
    // 트랜잭션 calldata 인코딩 (MetaMask 서명용)
    // =========================================================

    /**
     * registerSong calldata 반환
     */
    public function encodeRegisterSong(string $title): string
    {
        return '0x' . $this->contract->getData('registerSong', $title);
    }

    /**
     * setShares calldata 반환
     */
    public function encodeSetShares(int $songId, array $wallets, array $roles, array $shares): string
    {
        return '0x' . $this->contract->getData('setShares', $songId, $wallets, $roles, $shares);
    }

    /**
     * purchaseLicense calldata 반환
     */
    public function encodePurchaseLicense(int $songId): string
    {
        return '0x' . $this->contract->getData('purchaseLicense', $songId);
    }
}
