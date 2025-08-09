<?php

arch()
    ->expect(['dd', 'dump', 'ray', 'var_dump'])
    ->not->toBeUsed();
