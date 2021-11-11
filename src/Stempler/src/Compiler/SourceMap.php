<?php

/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */

declare(strict_types=1);

namespace Spiral\Stempler\Compiler;

use Spiral\Stempler\Loader\LoaderInterface;
use Spiral\Stempler\Loader\Source;

/**
 * Stores and resolves offsets and line numbers between templates.
 */
final class SourceMap implements \Serializable
{
    /** @var array */
    private $paths = [];

    /** @var array */
    private $lines = [];

    /** @var Source[] */
    private $sourceCache;

    /**
     * Get all template paths involved in final template.
     */
    public function getPaths(): array
    {
        $paths = [];

        /** @var Location $loc */
        foreach ($this->lines as $line) {
            if (!in_array($this->paths[$line[0]], $paths, true)) {
                $paths[] = $this->paths[$line[0]];
            }
        }

        return $paths;
    }

    /**
     * Calculate the location of all closest nodes based on a line number in generated source. Recursive until top root
     * template.
     */
    public function getStack(int $line): array
    {
        $found = null;
        foreach ($this->lines as $linen => $ctx) {
            if ($linen <= $line) {
                $found = $ctx;
            }
        }

        if ($found === null) {
            return [];
        }

        $result = [];
        $this->unpack($result, $found);

        return $result;
    }

    /**
     * Compress.
     *
     * @return false|string
     */
    public function serialize()
    {
        return json_encode([
            'paths' => $this->paths,
            'lines' => $this->lines,
        ]);
    }

    /**
     * @param string $serialized
     */
    public function unserialize($serialized): void
    {
        $data = json_decode($serialized, true);

        $this->paths = $data['paths'];
        $this->lines = $data['lines'];
    }

    public static function calculate(string $content, array $locations, LoaderInterface $loader): SourceMap
    {
        $map = new self();

        foreach ($locations as $offset => $location) {
            $line = Source::resolveLine($content, $offset);
            if (!isset($map->lines[$line])) {
                $map->lines[$line] = $map->calculateLine($location, $loader);
            }
        }

        $map->sourceCache = null;

        return $map;
    }

    private function unpack(array &$result, array $line): void
    {
        $result[] = [
            'file' => $this->paths[$line[0]],
            'line' => $line[1],
        ];

        if ($line[2] !== null) {
            $this->unpack($result, $line[2]);
        }
    }

    private function calculateLine(Location $location, LoaderInterface $loader): array
    {
        if (!isset($this->sourceCache[$location->path])) {
            $this->sourceCache[$location->path] = $loader->load($location->path);
        }
        $path = $this->sourceCache[$location->path]->getFilename();

        if (!in_array($path, $this->paths, true)) {
            $this->paths[] = $path;
        }

        return [
            array_search($path, $this->paths),
            Source::resolveLine($this->sourceCache[$location->path]->getContent(), $location->offset),
            $location->parent === null ? null : $this->calculateLine($location->parent, $loader),
        ];
    }
}
