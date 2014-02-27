<?php

for (;;) {
    $code = false;

    xdebug_break();

    if (false === $code) {
        break;
    }

    unset($result);

    try {
        $result = eval($code);
    } catch (\Exception $exception) {
        continue;
    }

    unset($exception);
}
