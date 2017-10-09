<?php

namespace Graze\ParallelProcess;

trait TagsTrait
{

    /** @var int[] */
    protected $maxLengths = [];

    /**
     * Parses the rows to determine the key lengths to make a pretty table
     *
     * @param array $data
     */
    protected function updateRowKeyLengths(array $data = [])
    {
        $lengths = array_map('mb_strlen', $data);

        $keys = array_merge(array_keys($lengths), array_keys($this->maxLengths));

        foreach ($keys as $key) {
            if (!isset($this->maxLengths[$key])
                || (isset($lengths[$key]) && $lengths[$key] > $this->maxLengths[$key])
            ) {
                $this->maxLengths[$key] = $lengths[$key];
            }
        }
    }

    /**
     * Format an array of input tags
     *
     * @param array       $data
     * @param string|null $colour
     *
     * @return string
     */
    protected function formatTags(array $data = [], $colour = null)
    {
        $info = [];
        foreach ($data as $key => $value) {
            $length = isset($this->maxLengths[$key]) ? '-' . $this->maxLengths[$key] : '';
            if (!is_null($colour)) {
                $valueFormat = sprintf("<options=bold;fg=%s>%{$length}s</>", $colour, $value);
            } else {
                $valueFormat = sprintf("%{$length}s", $value);
            }
            if (is_int($key)) {
                $info[] = $valueFormat;
            } else {
                $info[] = sprintf("<info>%s</info>: %s", $key, $valueFormat);
            }
        }
        return implode(' ', $info);
    }
}
