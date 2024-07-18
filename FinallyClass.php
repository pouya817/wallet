<?php

require 'vendor/autoload.php';

use ParagonIE\ConstantTime\Base64UrlSafe;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use OTPHP\TOTP;
use Exception;

class MnemonicGenerator {
    private $wordlist;

    public function __construct(array $wordlist) {
        $this->wordlist = $wordlist;
    }

    public function generateRandomMnemonic(int $numWords = 12): string {
        $mnemonic = [];
        $wordlistLength = count($this->wordlist);

        for ($i = 0; $i < $numWords; $i++) {
            $index = random_int(0, $wordlistLength - 1);
            $mnemonic[] = $this->wordlist[$index];
        }

        return implode(' ', $mnemonic);
    }

    public function generateSeedPhrase(array $mnemonics): string {
        return implode(' ', $mnemonics);
    }

    public function mnemonicsToPrivateKey(array $mnemonics): string {
        $mnemonicsStr = $this->generateSeedPhrase($mnemonics);
        $seed = hash('sha256', $mnemonicsStr, true);
        return Base64UrlSafe::encode($seed);
    }

    public function mnemonicsToPublicKey(array $mnemonics): string {
        $mnemonicsStr = $this->generateSeedPhrase($mnemonics);
        $seed = hash('sha256', $mnemonicsStr, true);
        return Base64UrlSafe::encode(hash('sha256', $seed, true));
    }

    public function publicKeyToAddress(string $publicKey): string {
        // Example: Generate an address format expected by Toncenter API
        return substr(hash('sha256', $publicKey), 0, 32); // Adjust length and format as per Toncenter API requirements
    }
}

class TonBlockchainClient {
    public function __construct() {
    $this->client = new Client(['base_uri' => 'https://toncenter.com/api/v2/']);
}

    public function sendToTonBlockchain(string $mnemonic, string $privK, string $pubK) {
        $message = [
            'mnemonic' => $mnemonic,
            'privateKey' => $privK,
            'publicKey' => $pubK
        ];

        $bocMessage = base64_encode(json_encode($message)); // Placeholder for BOC conversion

        $response = $this->client->post('wallets', [
            'json' => ['boc' => $bocMessage]
        ]);

        $responseBody = json_decode($response->getBody(), true);

        if ($response->getStatusCode() === 200) {
            echo "Keys sent successfully: " . print_r($responseBody, true) . "\n";
        } else {
            echo "Error sending keys: " . $responseBody['error'] . "\n";
        }
    }

    public function getWalletBalance($address) {
        try {
            $response = $this->client->get("getAddressInformation", [
                'query' => ['address' => $address]
            ]);
            return json_decode($response->getBody(), true);
        } catch (RequestException $e) {
            if ($e->hasResponse()) {
                return json_decode($e->getResponse()->getBody()->getContents(), true);
            }
            return ['error' => $e->getMessage()];
        }
    }

    public function sendTon($privateKey, $to, $amount) {
        $response = $this->client->post('sendTransaction', [
            'json' => [
                'privateKey' => $privateKey,
                'to' => $to,
                'amount' => $amount,
            ]
        ]);
        return json_decode($response->getBody(), true);
    }

    public function getTransactionHistory($address) {
        $response = $this->client->get("getTransactions", [
            'query' => ['address' => $address]
        ]);
        return json_decode($response->getBody(), true);
    }

    public function receiveToken($privateKey, $tokenID, $amount) {
        $response = $this->client->post('receiveToken', [
            'json' => [
                'privateKey' => $privateKey,
                'tokenID' => $tokenID,
                'amount' => $amount,
            ]
        ]);
        return json_decode($response->getBody(), true);
    }

    public function importTonToken($privateKey, $contractAddress, $walletAddress) {
        $data = [
            'privateKey' => $privateKey,
            'contractAddress' => $contractAddress,
            'walletAddress' => $walletAddress,
        ];
        $response = $this->client->post('importToken', ['json' => $data]);
        return json_decode($response->getBody(), true);
    }

    public function callSmartContractFunction($privateKey, $contractAddress, $contractABI, $functionName, $params = []) {
        $data = [
            'privateKey' => $privateKey,
            'contractAddress' => $contractAddress,
            'contractABI' => $contractABI,
            'functionName' => $functionName,
            'params' => $params,
        ];
        $response = $this->client->post('callContractFunction', ['json' => $data]);
        return json_decode($response->getBody(), true);
    }

    public function verify(string $seedPhrase): array {
        $mnemonics = explode(' ', $seedPhrase);
        $mnemonicGenerator = new MnemonicGenerator([]);
        $privateKey = $mnemonicGenerator->mnemonicsToPrivateKey($mnemonics);
        $publicKey = $mnemonicGenerator->mnemonicsToPublicKey($mnemonics);

        // Get wallet balance
        $balanceResponse = $this->getWalletBalance($publicKey);
        $balance = $balanceResponse['result'];

        // Get transaction history
        $historyResponse = $this->getTransactionHistory($publicKey);
        $history = $historyResponse['result'];

        return [
            'privateKey' => $privateKey,
            'publicKey' => $publicKey,
            'balance' => $balance,
            'transactionHistory' => $history
        ];
    }

    public function resetWallet() {
        // Clear session or any other user data
        session_start();
        session_unset();
        session_destroy();

        // Redirect to the login/signup page
        header("Location: index.php"); // Adjust the path as needed
        exit();
    }

    public function signIn($seedPhrase): array {
        return $this->verify($seedPhrase);
    }

    public function signUp(): array {
        $mnemonicGenerator = new MnemonicGenerator([
            "abandon", "ability", "able", "about", "above", "absent", "absorb", "abstract", "absurd", "abuse", "access", "accident",
            // Add the rest of the BIP-39 wordlist here...
        ]);

        $mnemonic = $mnemonicGenerator->generateRandomMnemonic();
        $mnemonics = explode(' ', $mnemonic);
        $privateKey = $mnemonicGenerator->mnemonicsToPrivateKey($mnemonics);
        $publicKey = $mnemonicGenerator->mnemonicsToPublicKey($mnemonics);
        $seedPhrase = $mnemonicGenerator->generateSeedPhrase($mnemonics);
        $address = $mnemonicGenerator->publicKeyToAddress($publicKey);

        return [
            'mnemonic' => $mnemonic,
            'privateKey' => $privateKey,
            'publicKey' => $publicKey,
            'seedPhrase' => $seedPhrase,
            'address' => $address
        ];
    }
}

// Example usage
$client = new TonBlockchainClient();
$signUpData = $client->signUp();
print_r($signUpData);

// Check wallet balance
$balanceData = $client->getWalletBalance($signUpData['address']);
print_r($balanceData);
