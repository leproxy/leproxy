<?php

system('composer install --no-dev --classmap-authoritative');
$files = require __DIR__ . '/vendor/composer/autoload_classmap.php';
system('composer install');

//system('cat leproxy.php > leproxy.out.php');
system('egrep -v "^require " leproxy.php > leproxy.out.php');

foreach ($files as $file) {
    $file = substr($file, strlen(__DIR__) + 1);
    //system('cat ' . escapeshellarg($file) . ' >> leproxy.out.php');
    //system('grep -v "<?php" ' . escapeshellarg($file) . ' >> leproxy.out.php');
    system('(echo "# ' . $file . '"; grep -v "<?php" ' . escapeshellarg($file) . ') >> leproxy.out.php');
}
