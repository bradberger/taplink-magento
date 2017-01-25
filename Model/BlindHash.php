<?php
namespace TapLink\BlindHashing\Model;

class BlindHash extends \Magento\Framework\Encryption\Encryptor implements \Magento\Framework\Encryption\EncryptorInterface
{

    const DEFAULT_SALT_LENGTH = 64;
    const HASH_VERSION_SHA512 = 3;
    const HASH_VERSION_LATEST = 3;

    /**
     * @var array map of hash versions
     */
    private $hashVersionMap = [
        parent::HASH_VERSION_MD5 => 'md5',
        parent::HASH_VERSION_SHA256 => 'sha256',
        self::HASH_VERSION_SHA512 => 'sha512',
    ];

    /** {}
     * Generate a [salted] hash.
     *
     * $salt can be:
     * false - salt is not used
     * true - random salt of the default length will be generated
     * integer - random salt of specified length will be generated
     * string - actual salt value to be used
     *
     * @param string $password
     * @param bool|int|string $salt
     * @return string
     */
    public function getHash($password, $salt = false, $version = self::HASH_VERSION_LATEST)
    {

        if ($salt === false) {
            return $this->hash($password, $version);
        }

        if ($salt === true) {
            $salt = self::DEFAULT_SALT_LENGTH;
        }

        if (is_integer($salt)) {
            $salt = $this->random->getRandomString($salt);
        }

        // The hash to send to TapLink is the SHA512-HMAC(salt, password)
        $res = $this->taplink->newPassword(hash_hmac('sha512', $salt, $password));
        if ($res->error) {
            throw new TapLinkException($res->error);
        }

        // TODO handle potential version upgrades from the API.
        // The format is <hash2hex>:<salt>:<hash_version>:<taplink.version>
        return implode(parent::DELIMITER, [$res->hash2hex, $salt, self::HASH_VERSION_LATEST, $res->versionId]);
    }

    public function hash($data, $version = self::HASH_VERSION_LATEST)
    {
        return hash($this->hashVersionMap[$version], $data);
    }

    /**
     * Validate hash against hashing method (with or without salt)
     *
     * @param string $password
     * @param string $hash
     * @return bool
     * @throws \Exception
     */
    public function validateHash($password, $hash)
    {
        return $this->isValidHash($password, $hash);
    }

    /**
     * Validate hash against hashing method (with or without salt)
     *
     * @param string $password
     * @param string $hash
     * @return bool
     * @throws \Exception
     */
    public function isValidHash($password, $hash)
    {
        // Get the pieces of the puzzle.
        list($expectedHash2Hex, $salt, $version, $tapLinkVersion) = explode(self::DELIMITER, $hash);
        $version = (int) $version;

        if ($version <= 1) {
            // TODO Upgrade to blind hashes.
            return parent::isValidHash($password, $hash);
        }

        // This is a TapLink Blind hash
        $res = $this->taplink->verifyPassword(hash_hmac('sha512', $salt, $password), $expectedHash2Hex, $tapLinkVersion);
        if ($res->error) {
            throw new TapLinkException($res->error);
        }

        // TODO upgrade of TapLink version
        if ($res->newVersionId) {

        }

        return $res->matched;
    }

    /**
     * Validate hashing algorithm version
     *
     * @param string $hash
     * @param bool $validateCount
     * @return bool
     */
    public function validateHashVersion($hash, $validateCount = false) {

        list(, , $version, $tapLinkVersion) = explode(parent::DELIMITER, $hash);
        $version = (int) $version;

        // Magento hash version, not blind hash, so let parent handle.
        if ($version <= 1) {
            return parent::validateHashVersion($hash, $validateCount);
        }

        // Return whether version and taplink version are okay
        return $version === self::CURRENT_VERSION && (int) $tapLinkVersion <= 3;
    }
}
