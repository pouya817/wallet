<?php
require 'vendor/autoload.php';


use Olifanton\Mnemonic\TonMnemonic;
use Olifanton\Mnemonic\Wordlist\Bip39English;
use OTPHP\TOTP;

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

function sendTon($privateKey, $to, $amount, $secret, $totpCode) {
    $totp = TOTP::createFromSecret($secret);
    if (!$totp->verify($totpCode)) {
        throw new Exception('Invalid TOTP code');
    }

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


function sendToken($privateKey, $to, $amount, $tokenID, $secret, $totpCode) {
    $totp = TOTP::createFromSecret($secret);
    if (!$totp->verify($totpCode)) {
        throw new Exception('Invalid TOTP code');
    }

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
function generate(): void
{
    $randWord = Bip39English::WORDS[array_rand(Bip39English::WORDS)];
    $mnemonic = TonMnemonic::generate($randWord);
    print_r($mnemonic);
}

//$wallet = generateWallet();
//print_r($wallet);
//
//$address = $wallet['address'];
//$privateKey = $wallet['private_key'];
//$publicKey = $wallet['public_key'];

$publicKey = "2d2d2d2d2d424547494e2050524956415445204b45592d2d2d2d2d0d0a4d494745416745414d42414742797147534d343941674547425375424241414b42473077617749424151516766502f68324863325a2f644c6646426c3568657a0d0a44375877466d74675a627861367764764163634f3249716852414e43414152436b45684941756547792f6835414c7972573031566c65535148334b31386a59740d0a473241596b5a366a30515050757a4233376849784b44676f3258696f4c4f49347879554733472f3839376d792f594a622b635a380d0a2d2d2d2d2d454e442050524956415445204b45592d2d2d2d2d";
$privateKey = "2d2d2d2d2d424547494e205055424c4943204b45592d2d2d2d2d0d0a4d465977454159484b6f5a497a6a3043415159464b34454541416f44516741455170424953414c6e68737634655143387131744e565a586b6b423979746649320d0a4c527467474a47656f3945447a377377642b34534d5367344b4e6c3471437a694f4d636c427478762f50653573763243572f6e4766413d3d0d0a2d2d2d2d2d454e44205055424c4943204b45592d2d2d2d2d";
$address = "0191bf94289acb97bac008d57aeea6bc";
$to = "0e227bca0fca37bc4f7b7ffce8684a43";
$amount = 1;


$balance = getWalletBalance($address);
print_r($balance);

//$phrase = generate();
//print_r($phrase);

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



$secret = 'DAPSOZEEHCQWD355LBYRLTOZ2OULTVJYXMBWJMDTRD4ESB2ZL45J4BRD4DNWRNLHWAR2IFWC7ZL3NBQ46ABRQT7DBPWHZXYOVPH7BUY';

// TOTP code provided by the user
$totpCode = '180474';

// Example for sending Ton with TOTP verification
try {
    $transaction = sendTon($privateKey, $to, $amount, $secret, $totpCode);
    print_r($transaction);
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}

?>