<?php

namespace App\Service;

final class DocumentEncryptionService
{
    private string $key;

    public function __construct(string $documentEncryptionKey)
    {
        $decoded = base64_decode($documentEncryptionKey, true);
        $this->key = $decoded !== false ? $decoded : $documentEncryptionKey;
        if (strlen($this->key) !== 32) {
            throw new \RuntimeException('DOCUMENT_ENCRYPTION_KEY doit contenir une clé dédiée de 32 octets encodée en base64. Le dépôt et la lecture des documents sont désactivés tant que cette clé est absente.');
        }
    }

    public function encrypt(string $value): string
    {
        $iv = random_bytes(12);
        $tag = '';
        $ciphertext = openssl_encrypt($value, 'aes-256-gcm', $this->key, OPENSSL_RAW_DATA, $iv, $tag);
        if ($ciphertext === false) { throw new \RuntimeException('Le chiffrement du document a échoué.'); }
        return 'v1:'.base64_encode($iv.$tag.$ciphertext);
    }

    public function decrypt(string $payload): string
    {
        if (!str_starts_with($payload, 'v1:')) { throw new \RuntimeException('Le document n\'est pas chiffré avec le format attendu.'); }
        $raw = base64_decode(substr($payload, 3), true);
        if ($raw === false || strlen($raw) < 29) { throw new \RuntimeException('Document chiffré invalide.'); }
        $plain = openssl_decrypt(substr($raw, 28), 'aes-256-gcm', $this->key, OPENSSL_RAW_DATA, substr($raw, 0, 12), substr($raw, 12, 16));
        if ($plain === false) { throw new \RuntimeException('Le déchiffrement du document a échoué.'); }
        return $plain;
    }
}
