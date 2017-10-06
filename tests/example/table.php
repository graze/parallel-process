<?php

/**
 * This file is part of graze/parallel-process.
 *
 * Copyright (c) 2017 Nature Delivered Ltd. <https://www.graze.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * @license https://github.com/graze/parallel-process/blob/master/LICENSE.md
 * @link    https://github.com/graze/parallel-process
 */

$composer = require_once __DIR__ . '/../../vendor/autoload.php';
$composer->setUseIncludePath(true);

use Graze\ParallelProcess\Table;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Process\Process;

$output = new ConsoleOutput(ConsoleOutput::VERBOSITY_VERY_VERBOSE);

$pool = new \Graze\ParallelProcess\Pool();
$pool->setMaxSimultaneous(3);
$table = new Table($output, $pool);
for ($i = 0; $i < 5; $i++) {
    $time = $i + 5;
    $table->add(new Process(sprintf('for i in `seq 1 %d` ; do date ; sleep 1 ; done', $time)), ['sleep' => $time]);
}
$table->run();
