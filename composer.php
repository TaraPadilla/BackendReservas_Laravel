<?php
putenv("COMPOSER_HOME=". __DIR__ ."/vendor/bin");
passthru('composer install 2>&1');
