<?php

namespace App\Service;

final class BankEncryptionService
{
    private string $key;

    public function __construct(string $bankEncryptionKey)
    {
        $decoded = base64_decode($bankEncryptionKey, true);
        $this->key = $decoded !== false ? $decoded : $bankEncryptionKey;

        if (strlen($this->key) !== 32) {
            throw new \RuntimeException('BANK_ENCRYPTION_KEY doit contenir une cle de 32 octets encodee en base64.');
        }
    }

    public function encrypt(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $iv = random_bytes(12);
        $tag = '';
        $ciphertext = openssl_encrypt($value, 'aes-256-gcm', $this->key, OPENSSL_RAW_DATA, $iv, $tag);
        if ($ciphertext === false) {
            throw new \RuntimeException('Le chiffrement bancaire a echoue.');
        }

        return 'v1:' . base64_encode($iv . $tag . $ciphertext);
    }

    public function decrypt(?string $payload): string
    {
        if ($payload === null || $payload === '') {
            return '';
        }

        if (!str_starts_with($payload, 'v1:')) {
            return $payload;
        }

        $raw = base64_decode(substr($payload, 3), true);
        if ($raw === false || strlen($raw) < 29) {
            throw new \RuntimeException('Donnee bancaire chiffree invalide.');
        }

        $iv = substr($raw, 0, 12);
        $tag = substr($raw, 12, 16);
        $ciphertext = substr($raw, 28);
        $plain = openssl_decrypt($ciphertext, 'aes-256-gcm', $this->key, OPENSSL_RAW_DATA, $iv, $tag);
        if ($plain === false) {
            throw new \RuntimeException('Le dechiffrement bancaire a echoue.');
        }

        return $plain;
    }
}
