<?php

require 'vendor/autoload.php';

use ParagonIE\ConstantTime\Base64UrlSafe;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

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
    private $client;

    public function __construct() {
        $this->client = new Client(['base_uri' => 'https://toncenter.com/api/v2/']);
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
