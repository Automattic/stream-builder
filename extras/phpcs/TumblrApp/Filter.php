<?php

namespace TumblrApp;

use PHP_CodeSniffer\Filters;

/**
 * Project-root relative path filter.
 */
final class Filter extends Filters\Filter
{
    /**
     * Cached root path for the project.
     *
     * @var string
     */
    private $root_path;

    /** @inheritDoc */
    public function __construct($iterator, ...$args)
    {
        parent::__construct($iterator, ...$args);

        $this->ruleset->ignorePatterns = array_map(function ($patterns) {
            if (!is_array($patterns)) {
                // This is not a sniff pattern, but rather a type of a global pattern.
                return $patterns;
            }

            return self::replaceDotWithCaretInPatterns($patterns);
        }, $this->ruleset->ignorePatterns);

        $this->ruleset->ignorePatterns = self::replaceDotWithCaretInPatterns($this->ruleset->ignorePatterns);

        // We assume that phpcs.xml is at the root.
        foreach ($this->config->standards as $path) {
            if ('phpcs.xml' === basename($path)) {
                $this->root_path = dirname($path);
                break;
            }
        }
    }

    /**
     * Replaces dots with carets in patterns.
     *
     * Ignore patterns come as pattern/type associations:
     * "./app/foo.php" => "absolute"
     * "./extras/bar/*" => "absolute"
     *
     * Where . is assumed to be the start of the path, yet interpreted in
     * a regex as a single character. We replace it with a caret to ensure
     * it matches the start of the path. We should end with:
     *
     * "^/app/foo.php" => "absolute"
     * "^/extras/bar/*" => "absolute"
     *
     * @param array<string, string> $patterns Input patterns.
     * @return array<string, string>
     */
    private static function replaceDotWithCaretInPatterns(array $patterns): array
    {
        return array_combine(array_map(function (string $pattern) {
            if ($pattern[0] === '.') {
                $pattern[0] = '^';
            }

            return $pattern;
        }, array_keys($patterns)), $patterns);
    }

    /** @inheritDoc */
    protected function shouldIgnorePath($path)
    {
        if (strpos($path, $this->root_path) === 0) {
            // Remove the root path from the beginning just once
            $path = substr_replace($path, '', 0, strlen($this->root_path));
        }

        return parent::shouldIgnorePath($path);
    }
}
