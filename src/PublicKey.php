<?php

declare(strict_types=1);

namespace PhpCfdi\Credentials;

use Closure;
use PhpCfdi\Credentials\Internal\Key;
use PhpCfdi\Credentials\Internal\LocalFileOpenTrait;
use RuntimeException;

class PublicKey extends Key
{
    use LocalFileOpenTrait;

    public function __construct(string $source)
    {
        $dataArray = self::callOnPublicKeyWithContents(
            function ($publicKey): array {
                // no need to verify that openssl_pkey_get_details returns false since it is already open
                return openssl_pkey_get_details($publicKey) ?: [];
            },
            $source
        );
        parent::__construct($dataArray);
    }

    public static function openFile(string $filename): self
    {
        return new self(static::localFileOpen($filename));
    }

    /**
     * Verify the signature of some data
     *
     * @param string $data
     * @param string $signature
     * @param int $algorithm
     *
     * @return bool
     *
     * @throws RuntimeException when openssl report an error on verify
     */
    public function verify(string $data, string $signature, int $algorithm = OPENSSL_ALGO_SHA256): bool
    {
        return $this->callOnPublicKey(
            function ($publicKey) use ($data, $signature, $algorithm): bool {
                $verify = $this->openSslVerify($data, $signature, $publicKey, $algorithm);
                if (-1 === $verify) {
                    /** @codeCoverageIgnore Don't know how make openssl_verify returns -1 */
                    throw new RuntimeException('Verify error: ' . openssl_error_string());
                }
                return (1 === $verify);
            }
        );
    }

    /**
     * This method id created to wrap and mock openssl_verify
     * @param string $data
     * @param string $signature
     * @param resource $publicKey
     * @param int $algorithm
     * @return int
     */
    protected function openSslVerify(string $data, string $signature, $publicKey, int $algorithm): int
    {
        return openssl_verify($data, $signature, $publicKey, $algorithm);
    }

    public function callOnPublicKey(Closure $function)
    {
        return $this->callOnPublicKeyWithContents($function, $this->publicKeyContents());
    }

    /**
     * @param Closure $function
     * @param string $publicKeyContents
     * @return mixed
     * @throws RuntimeException when Cannot open public key
     */
    private static function callOnPublicKeyWithContents(Closure $function, string $publicKeyContents)
    {
        $pubKey = openssl_get_publickey($publicKeyContents);
        if (! is_resource($pubKey)) {
            throw new RuntimeException('Cannot open public key: ' . openssl_error_string());
        }
        try {
            return call_user_func($function, $pubKey);
        } finally {
            openssl_free_key($pubKey);
        }
    }
}
