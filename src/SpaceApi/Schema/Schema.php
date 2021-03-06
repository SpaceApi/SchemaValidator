<?php

namespace SpaceApi\Schema;

class Schema
{
    const SCHEMA_OBJECT = 1;
    const SCHEMA_LATEST = 'latest'; // can be stable or draft
    const SCHEMA_STABLE = 'stable';

    protected $schemaRoot = '';
    protected $schemas = array();
    protected $stableVersion = 0;
    protected $draftVersion = 0;

    /**
     * @var array Sorted list of version numbers
     * @todo write unit test to guarantee that the numbers are sorted
     */
    protected $versions = array();

    function __construct() {

        // never load the file content here, there are use cases where
        // only the version number are needed, to improve the performance
        // we load the files on-demand
        $this->schemaRoot = realpath(
            __DIR__ . '/../../../data/specs'
        );

        $draft_seen = false;
        foreach (glob($this->schemaRoot ."/*.json") as $filename) {
            $is_draft = false;
            if (strpos($filename, '-draft') !== false) {
                // skip empty draft file or if there was already one
                if($draft_seen || filesize($filename) === 0) {
                    continue;
                }
                $is_draft = true;
                $draft_seen = true;
                $filename = str_replace('-draft', '', $filename);
            }

            $filename = basename($filename);
            $filename = str_replace('.json', '', $filename);
            $version = intval(str_replace('0.', '', $filename));

            if ($is_draft) {
                $this->draftVersion = $version;
            }

            $this->versions[] = $version;
        }

        sort($this->versions);

        $count = count($this->versions);
        if ($count > 0) {
            if ($draft_seen && $count > 1) {
                $this->stableVersion = $this->versions[$count - 2];
            } else {
                $this->stableVersion = $this->versions[$count - 1];
            }
        }
    }

    /**
     * Returns the stable version
     * @return int stable version
     */
    public function getStableVersion() {
        return $this->stableVersion;
    }

    /**
     * Returns the latest version
     * @return int latest version
     */
    public function getLatestVersion() {
        return $this->versions[count($this->versions) - 1];
    }

    /**
     * Returns the draft version
     * @return int draft version
     */
    public function getDraftVersion() {
        return $this->draftVersion;
    }

    /**
     * Returns the available schema versions without the '0.' prefix.
     * @return array available schema versions
     */
    public function getVersions() {
        return $this->versions;
    }

    /**
     * Returns the available schema versions as strings with the '0.' prefix.
     * @return array available schema versions
     */
    public function getVersionStrings() {
        $versions = array();
        foreach ($this->versions as $version) {
            $versions[] = "0.$version";
        }
        return $versions;
    }

    /**
     * Get the schema by a given version. The version number mustn't have
     * the '0.' prefix. The schema is loaded on-demand.
     *
     * @param int|string $version version number or the string 'latest' or 'stable'
     * @param int $flags
     * @return string|object|null schema content, null if no schema could be found
     */
    public function get($version, $flags = 0) {

        if (is_string($version)) {
            if ($version === self::SCHEMA_STABLE) {
                $version = $this->getStableVersion();
            } else if ($version === self::SCHEMA_LATEST) {
                $version = $this->getLatestVersion();
            } else {
                $version = 0;
            }
        }

        if (array_key_exists($version, $this->schemas)) {
            $schema = $this->schemas[$version];
        } else {
            if ($this->isDraftVersion($version)) {
                $filename = "$version-draft.json";
            } else {
                $filename = "$version.json";
            }

            $file_path = $this->schemaRoot . '/'. $filename;
            if (file_exists($file_path)) {
                $schema = file_get_contents($file_path);
                $this->schemas[$version] = $schema;
            } else {
                $schema = null;
            }
        }

        if (! is_null($schema) && $flags & static::SCHEMA_OBJECT) {
            return json_decode($schema);
        }
        return $schema;
    }

    public function isDraftVersion($version) {
        return $this->draftVersion === $version;
    }
}
