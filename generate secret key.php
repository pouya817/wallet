<?php
require 'vendor/autoload.php';

use ParagonIE\ConstantTime\Base64UrlSafe;
use GuzzleHttp\Client;

// List of BIP-39 words (English)
$bip39_wordlist = [
    "broken", "decade", "unit", "bird", "enrich", "great", "nurse", "offer", "rescue",
    "sound", "pole", "true", "dignity", "buyer", "provide", "boil", "connect", "universe",
    "model", "add", "obtain", "hire", "gift", "swim",
    // Add the rest of the BIP-39 wordlist here...
];

// Function to generate a random BIP-39 mnemonic
function generateRandomMnemonic(array $wordlist, int $num_words = 12): string {
    $mnemonic = [];
    $wordlist_length = count($wordlist);

    for ($i = 0; $i < $num_words; $i++) {
        $index = random_int(0, $wordlist_length - 1);
        $mnemonic[] = $wordlist[$index];
    }

    return implode(' ', $mnemonic);
}

// Function to generate seed phrase from mnemonics
function generateSeedPhrase(array $mnemonics): string {
    return implode(' ', $mnemonics);
}

// Function to convert mnemonics to private key
function mnemonicsToPrivateKey(array $mnemonics): string {
    $mnemonics_str = generateSeedPhrase($mnemonics);
    $seed = hash('sha256', $mnemonics_str, true);
    return Base64UrlSafe::encode($seed);
}

// Function to convert mnemonics to public key
function mnemonicsToPublicKey(array $mnemonics): string {
    $mnemonics_str = generateSeedPhrase($mnemonics);
    $seed = hash('sha256', $mnemonics_str, true);
    return Base64UrlSafe::encode(hash('sha256', $seed, true));
}

// Function to send keys to Ton Blockchain
function sendToTonBlockchain(string $mnemonic, string $priv_k, string $pub_k) {
    $client = new Client(['base_uri' => 'https://tonapi.io/v1/']); // Update with the correct Ton API URL

    // Create BOC message
    $message = [
        'mnemonic' => $mnemonic,
        'privateKey' => $priv_k,
        'publicKey' => $pub_k
    ];

    // Convert message to BOC format (assuming you have a method to convert to BOC)
    // Here we simulate converting to BOC. You should implement the actual conversion.
    $bocMessage = base64_encode(json_encode($message)); // Placeholder for BOC conversion

    // Send request to the API
    $response = $client->post('wallets', [
        'json' => [
            'boc' => $bocMessage // Send BOC formatted message
        ]
    ]);

    $responseBody = json_decode($response->getBody(), true);

    if ($response->getStatusCode() === 200) {
        echo "Keys sent successfully: " . print_r($responseBody, true) . "\n";
    } else {
        echo "Error sending keys: " . $responseBody['error'] . "\n";
    }
}

// Generate a random 12-word mnemonic phrase using BIP-39 wordlist
$mnemonic = generateRandomMnemonic($bip39_wordlist);

// Convert mnemonic to mnemonics array
$mnemonics = explode(' ', $mnemonic);

// Generate private key, public key, and seed phrase
$priv_k = mnemonicsToPrivateKey($mnemonics);
$pub_k = mnemonicsToPublicKey($mnemonics);
$seed_phrase = generateSeedPhrase($mnemonics);

// Output the results
echo "Generated Mnemonic: $mnemonic\n";
echo "Private Key: $priv_k\n";
echo "Public Key: $pub_k\n";
echo "Seed Phrase: $seed_phrase\n";

// Send keys to Ton Blockchain
sendToTonBlockchain($mnemonic, $priv_k, $pub_k);
