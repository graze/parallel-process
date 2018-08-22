<?php

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
