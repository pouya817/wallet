<?php
require 'vendor/autoload.php';

use OTPHP\TOTP;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;

function generateSecretForUser($username) {
    $totp = TOTP::generate();
    $totp->setLabel($username);
    $secret = $totp->getSecret();

    $totp = TOTP::createFromSecret($secret);
    echo "The current OTP is: {$totp->now()}\n";
//    // Generate QR code
//    $qrCode = QrCode::create($totp->getProvisioningUri());
//    $writer = new PngWriter();
//    $result = $writer->write($qrCode);
//
//    // Save the secret key in your database
//    // saveSecretKeyToDatabase($username, $secret);
//
//    // Display the QR code
//    header('Content-Type: '.$result->getMimeType());
//    echo $result->getString();
//
//    // Return the secret for further use if needed
    return $secret;
}

// Example usage during user registration
$username = 'example_user';
$secret = generateSecretForUser($username);
echo "Save this secret securely: " . $secret;
?>
