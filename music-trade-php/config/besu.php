<?php

return [
    'rpc_url'          => env('BESU_RPC_URL', 'http://192.168.56.101:8545'),
    'chain_id'         => env('BESU_CHAIN_ID', 1337),
    'contract_address' => env('CONTRACT_ADDRESS'),
];
