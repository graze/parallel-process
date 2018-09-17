<?php
/**
 * This file is part of graze/parallel-process.
 *
 * Copyright © 2018 Nature Delivered Ltd. <https://www.graze.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * @license https://github.com/graze/parallel-process/blob/master/LICENSE.md
 * @link    https://github.com/graze/parallel-process
 */

namespace Graze\ParallelProcess;

interface OutputterInterface
{
    /**
     * Get the last message that this thing produced
     *
     * @return string
     */
    public function getLastMessage();

    /**
     * @return string
     */
    public function getLastMessageType();
}
