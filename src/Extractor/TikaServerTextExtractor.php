<?php

namespace SilverStripe\TextExtraction\Extractor;

use SilverStripe\Assets\File;
use SilverStripe\Core\Environment;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\TextExtraction\Rest\TikaRestClient;

/**
 * Enables text extraction of file content via the Tika Rest Server
 *
 * {@link http://tika.apache.org/1.7/gettingstarted.html}
 */
class TikaServerTextExtractor extends FileTextExtractor
{
    /**
     * Tika server is pretty efficient so use it immediately if available
     *
     * @var integer
     * @config
     */
    private static $priority = 80;

    /**
     * Server endpoint
     *
     * @var string
     * @config
     */
    private static $server_endpoint;

    /**
     * @var TikaRestClient
     */
    protected $client = null;

    /**
     * Cache of supported mime types
     *
     * @var array
     */
    protected $supportedMimes = [];

    /**
     * @return TikaRestClient
     */
    public function getClient()
    {
        if (!$this->client) {
            $this->client = Injector::inst()->createWithArgs(
                TikaRestClient::class,
                [$this->getServerEndpoint()]
            );
        }
        return $this->client;
    }

    /**
     * @return string
     */
    public function getServerEndpoint()
    {
        if ($endpoint = Environment::getEnv('SS_TIKA_ENDPOINT')) {
            return $endpoint;
        }

        // Default to configured endpoint
        return $this->config()->get('server_endpoint');
    }

    /**
     * Get the version of Tika installed, or 0 if not installed
     *
     * @return float version of Tika
     */
    public function getVersion()
    {
        return $this->getClient()->getVersion();
    }

    /**
     * @return boolean
     */
    public function isAvailable()
    {
        $version = $this->getVersion();
        // ensure that the version number has a major, minor and patch number
        // reason being that version_compare('1.7', '1.7.0') will return -1 instead of 0
        for ($i = 0; $i < 2; $i++) {
            if (substr_count($version, '.') < 2) {
                $version .= '.0';
            }
        }
        return $this->getServerEndpoint()
            && $this->getClient()->isAvailable()
            && version_compare($this->getVersion(), '1.7.0') >= 0;
    }

    /**
     * @param  string $extension
     * @return boolean
     */
    public function supportsExtension($extension)
    {
        // Determine support via mime type only
        return false;
    }

    /**
     * @param  string $mime
     * @return boolean
     */
    public function supportsMime($mime)
    {
        if (!$this->supportedMimes) {
            $this->supportedMimes = (array) $this->getClient()->getSupportedMimes();
        }

        // Check if supported (most common / quickest lookup)
        if (isset($this->supportedMimes[$mime])) {
            return true;
        }

        // Check aliases
        foreach ($this->supportedMimes as $info) {
            if (isset($info['alias']) && in_array($mime, $info['alias'])) {
                return true;
            }
        }

        return false;
    }

    public function getContent($file)
    {
        $tempFile = $file instanceof File ? $this->getPathFromFile($file) : $file;
        return $this->getClient()->tika($tempFile);
    }
}
