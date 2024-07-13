<?php
require 'vendor/autoload.php';

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

// Your mnemonic words
$mnemonics = ['broken', 'decade', 'unit', 'bird', 'enrich', 'great', 'nurse', 'offer', 'rescue',
    'sound', 'pole', 'true', 'dignity', 'buyer', 'provide', 'boil', 'connect', 'universe',
    'model', 'add', 'obtain', 'hire', 'gift', 'swim'];

// Function to convert mnemonics to private key (This is an example and should be replaced with a real implementation)
function mnemonicsToPrivateKey($mnemonics): string {
    $mnemonics_str = implode(' ', $mnemonics);
    $seed = hash('sha256', $mnemonics_str, true);
    return base64_encode($seed);
}

// Function to convert mnemonics to public key (This is an example and should be replaced with a real implementation)
function mnemonicsToPublicKey($mnemonics): string {
    $mnemonics_str = implode(' ', $mnemonics);
    $seed = hash('sha256', $mnemonics_str, true);
    return base64_encode(hash('sha256', $seed, true));
}

$priv_k = mnemonicsToPrivateKey($mnemonics);
$pub_k = mnemonicsToPublicKey($mnemonics);

// Create a Guzzle client
$client = new Client([
    'base_uri' => 'https://toncenter.com/api/v2/sendBocReturnHash', // Example base URI, replace with the actual API base URI
    'timeout'  => 10.0,
]);

try {
    // Create a new wallet
    $response = $client->request('POST', 'wallet/create', [
        'json' => [
            'version' => 'v3r2',
            'workchain' => 0,
            'publicKey' => $pub_k
        ]
    ]);

    $wallet_data = json_decode($response->getBody(), true);
    $wallet_address = $wallet_data['address'];

    // Create an internal transfer message
    $to_addr = 'new_wallet_address';
    $amount = 0.02;
    $seqno = 1; // Replace with the actual wallet seqno

    $response = $client->request('POST', 'wallet/createTransferMessage', [
        'json' => [
            'from' => $wallet_address,
            'to' => $to_addr,
            'amount' => $amount,
            'seqno' => $seqno,
            'privateKey' => $priv_k
        ]
    ]);

    $transfer_msg = json_decode($response->getBody(), true);
    $boc = $transfer_msg['boc'];
    $boc_base64 = base64_encode($boc);

    echo "BOC: " . $boc_base64 . "\n";

    // Send the BOC to the blockchain
    $response = $client->request('POST', 'wallet/sendBoc', [
        'json' => [
            'boc' => $boc_base64
        ]
    ]);

    $send_result = json_decode($response->getBody(), true);
    echo "Send Result: " . $send_result['result'] . "\n";

} catch (GuzzleException $e) {
    // Handle Guzzle exceptions
    echo 'Request failed: ' . $e->getMessage() . "\n";
}

