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

    protected array $abi = [
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
        ['name' => 'purchaseLicense', 'type' => 'function', 'stateMutability' => 'nonpayable',
            'inputs' => [
                ['name' => 'songId', 'type' => 'uint256'],
                ['name' => 'amount', 'type' => 'uint256'],
            ],
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

    public function encodeRegisterSong(string $title): string
    {
        return '0x' . $this->contract->getData('registerSong', $title);
    }

    public function encodeSetShares(int $songId, array $wallets, array $roles, array $shares): string
    {
        return '0x' . $this->contract->getData('setShares', $songId, $wallets, $roles, $shares);
    }

    public function encodePurchaseLicense(int $songId, int $amount): string
    {
        return '0x' . $this->contract->getData('purchaseLicense', $songId, $amount);
    }
}
