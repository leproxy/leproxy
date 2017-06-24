<?php

$out = 'leproxy.out.php';

system('composer install --no-dev --classmap-authoritative');
$classes = require __DIR__ . '/vendor/composer/autoload_classmap.php';
$includes = require __DIR__ . '/vendor/composer/autoload_files.php';
system('composer install');

echo 'Loading ' . count($classes) . ' classes to determine load order...';

// register autoloader which remembers load order
$ordered = array();
spl_autoload_register(function ($name) use ($classes, &$ordered) {
    require $classes[$name];
    $ordered[$name] = $classes[$name];
});

// use actual include file instead of include wrapper
foreach ($includes as $i => $path) {
    $includes[$i] = str_replace('_include.php', '.php', $path);

    require $path;
}

// load each class (and its superclasses once) into memory
foreach ($classes as $class => $path) {
    class_exists($class, true);
}

echo ' DONE' . PHP_EOL;

// resulting list of all includes and ordered class list
$files = array_merge($includes, $ordered);
echo 'Concatenating ' . count($files) . ' files into ' . $out . '...';
system('head -n2 leproxy.php > ' . escapeshellarg($out));

foreach ($files as $file) {
    $file = substr($file, strlen(__DIR__) + 1);
    system('(echo "# ' . $file . '"; grep -v "<?php" ' . escapeshellarg($file) . ') >> ' . escapeshellarg($out));
}

$file = 'leproxy.php';
system('(echo "# ' . $file . '"; egrep -v "^<\?php|^require " ' . escapeshellarg($file) . ') >> ' . escapeshellarg($out));
echo ' DONE' . PHP_EOL;
