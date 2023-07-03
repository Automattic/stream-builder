<?php

namespace TumblrExtras\GitDiff\Differs;

interface DifferInterface
{
    /**
     * @return string Return string in official DIFF format
     */
    public function getDiffOutput(): string;
}
