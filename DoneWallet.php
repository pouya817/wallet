<?php

require 'vendor/autoload.php';

use Elliptic\EC;
use kornrunner\Keccak;
use GuzzleHttp\Client;
use Normalizer;

// SeedPhrase ==> hash ==> Blockchain Ton ==> private key ==> public key ==> generate Address .

class BIP39 {
    private $wordList;

    public function __construct() {
        $this->loadWordList();
    }

    private function loadWordList() {
        $file = __DIR__ . '/bip39-wordlist.txt';
        $words = file($file, FILE_IGNORE_NEW_LINES);
        if ($words === false) {
            throw new Exception("Couldn't read the word list file.");
        }
        $this->wordList = $words;
    }

    public function generateEntropy($bits = 128) {
        if ($bits % 32 !== 0 || $bits < 128 || $bits > 256) {
            throw new InvalidArgumentException("Bits should be one of the following values: 128, 160, 192, 224, 256.");
        }
        return random_bytes($bits / 8);
    }

    private function calculateChecksum($entropy) {
        $hash = hash('sha256', $entropy, true);
        $hashBits = unpack('H*', $hash)[1];
        $hashBitsBinary = '';
        foreach (str_split($hashBits) as $hex) {
            $hashBitsBinary .= str_pad(base_convert($hex, 16, 2), 4, '0', STR_PAD_LEFT);
        }
        $checksumLength = strlen($entropy) * 8 / 32;
        return substr($hashBitsBinary, 0, $checksumLength);
    }

    public function entropyToMnemonic($entropy) {
        $entropyBits = '';
        foreach (str_split($entropy) as $byte) {
            $entropyBits .= str_pad(decbin(ord($byte)), 8, '0', STR_PAD_LEFT);
        }

        $checksum = $this->calculateChecksum($entropy);
        $entropyBits .= $checksum;

        $mnemonic = [];
        foreach (str_split($entropyBits, 11) as $bits) {
            $index = bindec($bits);
            $mnemonic[] = $this->wordList[$index];
        }

        return implode(' ', $mnemonic);
    }

    public function mnemonicToSeed($mnemonic, $passphrase = '') {
        $salt = 'mnemonic' . $passphrase;
        $mnemonicNormalized = Normalizer::normalize($mnemonic, Normalizer::FORM_NFKD);
        $saltNormalized = Normalizer::normalize($salt, Normalizer::FORM_NFKD);
        $seed = hash_pbkdf2('sha512', $mnemonicNormalized, $saltNormalized, 2048, 64, true);
        return bin2hex($seed);
    }
}

// Generate private key from seed
function generatePrivateKey($seedHex) {
    $ec = new EC('secp256k1');
    $key = $ec->keyFromPrivate($seedHex);
    return $key->getPrivate('hex');
}

// Generate public key from private key
function generatePublicKey($privateKeyHex) {
    $ec = new EC('secp256k1');
    $key = $ec->keyFromPrivate($privateKeyHex);
    $publicKey = $key->getPublic();
    return $publicKey->encode('hex');
}

// Generate wallet address from public key
function generateWalletAddress($publicKeyHex, $workchain = 0) {
    $publicKeyBin = hex2bin($publicKeyHex);
    $hash = Keccak::hash($publicKeyBin, 256);
    $workchainHex = str_pad(dechex($workchain), 2, '0', STR_PAD_LEFT);

    $addressHex = $workchainHex . substr($hash, -64, 64);
    $addressBytes = hex2bin($addressHex);
    $checksum = substr(Keccak::hash($addressBytes, 256), 0, 8);

    return base64UrlEncode(hex2bin($addressHex . $checksum));
}

// Base64 URL encoding
function base64UrlEncode($data) {
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

// Generate mnemonic using BIP39
function generateMnemonic() {
    $bip39 = new BIP39();
    $mnemonic = $bip39->generateEntropy(128);
    return explode(" ", $bip39->entropyToMnemonic($mnemonic));
}

// Network Functions

// Get wallet balance
function getWalletBalance($address) {
    $client = new Client();
    $response = $client->get("https://toncenter.com/api/v2/getAddressInformation", [
        'query' => ['address' => $address]
    ]);
    return json_decode($response->getBody(), true);
}

// Send TON
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

// Send Token
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

// Get transaction history
function getTransactionHistory($address) {
    $client = new Client();
    $response = $client->get("https://toncenter.com/api/v2/getTransactions", [
        'query' => ['address' => $address]
    ]);
    return json_decode($response->getBody(), true);
}

// Estimate fee
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

// Wait for transaction confirmation
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

// Import TON token
function importTonToken($privateKey, $contractAddress, $walletAddress) {
    $client = new Client(['base_uri' => 'https://toncenter.com/api/v2/']);
    $data = [
        'privateKey' => $privateKey,
        'contractAddress' => $contractAddress,
        'walletAddress' => $walletAddress,
    ];
    $response = $client->post('importToken', ['json' => $data]);
    return json_decode($response->getBody(), true);
}

// Call a smart contract function on TON
function callSmartContractFunction($privateKey, $contractAddress, $contractABI, $functionName, $params = []) {
    $client = new Client(['base_uri' => 'https://toncenter.com/api/v2/']);
    $data = [
        'privateKey' => $privateKey,
        'contractAddress' => $contractAddress,
        'contractABI' => $contractABI,
        'functionName' => $functionName,
        'params' => $params,
    ];
    $response = $client->post('callContractFunction', ['json' => $data]);
    return json_decode($response->getBody(), true);
}

// Receive Token
function receiveToken($privateKey, $tokenID, $amount) {
    $client = new Client();
    $response = $client->post('https://toncenter.com/api/v2/receiveToken', [
        'json' => [
            'privateKey' => $privateKey,
            'tokenID' => $tokenID,
            'amount' => $amount,
        ]
    ]);
    return json_decode($response->getBody(), true);
}

// Example usage
$bip39 = new BIP39();
$entropy = $bip39->generateEntropy(128);  // Generate 128-bit entropy
$mnemonic = $bip39->entropyToMnemonic($entropy);
$seed = $bip39->mnemonicToSeed($mnemonic);

$privateKey = generatePrivateKey($seed);
$publicKey = generatePublicKey($privateKey);
$rawAddress = generateWalletAddress($publicKey, $wallet_workchain = 0);

echo "Mnemonic: $mnemonic\n";
echo "Seed: $seed\n";
echo "Private Key: $privateKey\n";
echo "Public Key: $publicKey\n";
echo "Address: $rawAddress\n";

// Example of importing a TON token
$contractAddress = 'contract_address';
$walletAddress = $rawAddress;

$result = importTonToken($privateKey, $contractAddress, $walletAddress);
print_r($result);

// Example of calling a smart contract function
$contractABI = [/* ABI details */];
$functionName = 'transfer';
$params = [
    'to' => 'recipient_address',
    'amount' => 1000
];

$result = callSmartContractFunction($privateKey, $contractAddress, $contractABI, $functionName, $params);
print_r($result);

?>
