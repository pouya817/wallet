<?php
require 'vendor/autoload.php';

use phpseclib3\Crypt\EC;
use GuzzleHttp\Client;

function generateWallet() {
    $privateKey = EC::createKey('secp256k1');
    $publicKey = $privateKey->getPublicKey();

    $privateKeyHex = bin2hex($privateKey->toString('PKCS8'));
    $publicKeyHex = bin2hex($publicKey->toString('PKCS8'));
    $address = substr(hash('sha256', $publicKeyHex), 0, 32);

    return [
        'private_key' => $privateKeyHex,
        'public_key' => $publicKeyHex,
        'address' => $address,
    ];
}

function getWalletBalance($address) {
    $client = new Client();
    $response = $client->get("https://toncenter.com/api/v2/getAddressInformation", [
        'query' => ['address' => $address]
    ]);
    $data = json_decode($response->getBody(), true);
    return $data;
}

function sendTon($privateKey, $to, $amount) {
    $client = new Client();
    $response = $client->post('https://toncenter.com/api/v2/sendTransaction', [
        'json' => [
            'privateKey' => $privateKey,
            'to' => $to,
            'amount' => $amount,
        ]
    ]);
    return json_decode($response->getBody(), true);
}

function sendToken($privateKey, $to, $amount, $tokenID) {
    $client = new Client();
    $response = $client->post('https://toncenter.com/api/v2/sendToken', [
        'json' => [
            'privateKey' => $privateKey,
            'to' => $to,
            'amount' => $amount,
            'tokenID' => $tokenID,
        ]
    ]);
    return json_decode($response->getBody(), true);
}

function getTransactionHistory($address) {
    $client = new Client();
    $response = $client->get("https://toncenter.com/api/v2/getTransactions", [
        'query' => ['address' => $address]
    ]);
    $data = json_decode($response->getBody(), true);
    return $data;
}

function estimateFee($privateKey, $to, $amount) {
    $client = new Client();
    $response = $client->post('https://toncenter.com/api/v2/estimateFee', [
        'json' => [
            'privateKey' => $privateKey,
            'to' => $to,
            'amount' => $amount,
        ]
    ]);
    return json_decode($response->getBody(), true);
}

function waitForTransactionConfirmation($address, $seqno) {
    $client = new Client();
    $currentSeqno = $seqno;
    while ($currentSeqno == $seqno) {
        echo "Waiting for transaction to confirm...\n";
        sleep(1.5);
        $response = $client->get("https://toncenter.com/api/v2/getAddressInformation", [
            'query' => ['address' => $address]
        ]);
        $data = json_decode($response->getBody(), true);
        $currentSeqno = $data['result']['seqno'];
    }
    echo "Transaction confirmed!\n";
}


$wallet = generateWallet();
print_r($wallet);

$address = $wallet['address'];
$privateKey = $wallet['private_key'];
$publicKey = $wallet['public_key'];
$to = "765d9aaa3b2d957c084b6c9d341a8dbc";
$amount = 1;
//$address = "765d9aaa3b2d957c084b6c9d341a8dbc";

$balance = getWalletBalance($address);
print_r($balance);

// $privateKey = $wallet['private_key'];
// $to = 'RECEIVER_TON_ADDRESS';
// $amount = 1; // Amount in TON
// $transaction = sendTon($privateKey, $to, $amount);
// print_r($transaction);


// $tokenID = 'YOUR_TOKEN_ID';
// $amount = 100;
// $transaction = sendToken($privateKey, $to, $amount, $tokenID);
// print_r($transaction);

$history = getTransactionHistory($address);
print_r($history);


//$fee = estimateFee($privateKey, $to, $amount);
//print_r($fee);


//$seqno = 123; // Replace with actual sequence number
//waitForTransactionConfirmation($address, $seqno);
?>