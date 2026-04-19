<?php

declare(strict_types=1);

namespace App\Services;

use RuntimeException;

final class EncryptionHelper
{
    private const CIPHER    = 'aes-256-gcm';
    private const IV_LENGTH = 12;
    private const TAG_LENGTH = 16;

    /**
     * Encrypts plaintext using AES-256-GCM.
     * Output format: base64(iv[12] . tag[16] . ciphertext)
     *
     * @throws RuntimeException if the hex key is invalid or encryption fails
     */
    public function encrypt(string $plaintext, string $hexKey): string
    {
        $key = $this->parseKey($hexKey);
        $iv  = random_bytes(self::IV_LENGTH);
        $tag = '';

        $ciphertext = openssl_encrypt(
            $plaintext,
            self::CIPHER,
            $key,
            OPENSSL_RAW_DATA,
            $iv,
            $tag,
            '',
            self::TAG_LENGTH,
        );

        if ($ciphertext === false) {
            throw new RuntimeException('Encryption failed.');
        }

        return base64_encode($iv . $tag . $ciphertext);
    }

    /**
     * Decrypts a blob produced by `encrypt()`.
     *
     * @throws RuntimeException if decryption or authentication fails
     */
    public function decrypt(string $encoded, string $hexKey): string
    {
        $key  = $this->parseKey($hexKey);
        $blob = base64_decode($encoded, true);

        if ($blob === false || strlen($blob) < self::IV_LENGTH + self::TAG_LENGTH + 1) {
            throw new RuntimeException('Invalid encrypted blob.');
        }

        $iv         = substr($blob, 0, self::IV_LENGTH);
        $tag        = substr($blob, self::IV_LENGTH, self::TAG_LENGTH);
        $ciphertext = substr($blob, self::IV_LENGTH + self::TAG_LENGTH);

        $plaintext = openssl_decrypt(
            $ciphertext,
            self::CIPHER,
            $key,
            OPENSSL_RAW_DATA,
            $iv,
            $tag,
        );

        if ($plaintext === false) {
            throw new RuntimeException('Decryption failed — data may be tampered or key is wrong.');
        }

        return $plaintext;
    }

    /**
     * Encrypts a file in-place by reading the source and writing to the destination path.
     */
    public function encryptFile(string $sourcePath, string $destPath, string $hexKey): string
    {
        $plaintext = file_get_contents($sourcePath);
        if ($plaintext === false) {
            throw new RuntimeException("Cannot read source file: {$sourcePath}");
        }

        $encrypted = $this->encrypt($plaintext, $hexKey);
        if (file_put_contents($destPath, $encrypted) === false) {
            throw new RuntimeException("Cannot write encrypted file: {$destPath}");
        }

        return hash('sha256', $encrypted);
    }

    /**
     * Decrypts a file produced by `encryptFile()` back to plaintext.
     */
    public function decryptFile(string $encryptedPath, string $destPath, string $hexKey): void
    {
        $encoded = file_get_contents($encryptedPath);
        if ($encoded === false) {
            throw new RuntimeException("Cannot read encrypted file: {$encryptedPath}");
        }

        $plaintext = $this->decrypt($encoded, $hexKey);
        if (file_put_contents($destPath, $plaintext) === false) {
            throw new RuntimeException("Cannot write decrypted file: {$destPath}");
        }
    }

    private function parseKey(string $hexKey): string
    {
        if (strlen($hexKey) !== 64 || ! ctype_xdigit($hexKey)) {
            throw new RuntimeException('Encryption key must be a 64-character hex string (32 bytes).');
        }
        return hex2bin($hexKey);
    }
}
