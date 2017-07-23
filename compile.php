<?php

// use first argument as output file or use "leproxy-{version}.php"
$out = isset($argv[1]) ? $argv[1] : ('leproxy-' . exec('git describe --always --dirty || echo dev') . '.php');

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
chmod($out, 0755);
echo ' DONE (' . filesize($out) . ' bytes)' . PHP_EOL;

echo 'Optimizing resulting file...';
$small = '';
$all = token_get_all(file_get_contents($out));
foreach ($all as $i => $token) {
    if (is_array($token) && ($token[0] === T_COMMENT || $token[0] === T_DOC_COMMENT)) {
        // remove all comments
        unset($all[$i]);
    } elseif (is_array($token) && $token[0] === T_PUBLIC) {
        // get next non-whitespace token after `public` visibility
        $next = $all[$i + 1];
        if (is_array($next) && $next[0] === T_WHITESPACE) {
            $next = $all[$i + 2];
        }

        if (is_array($next) && $next[0] === T_VARIABLE) {
            // use shorter variable notation `public $a` => `var $a`
            $all[$i] = array(T_VAR, 'var');
        } else {
            // remove unneeded public identifier `public static function a()` => `static function a()`
            unset($all[$i]);
        }
    } elseif (is_array($token) && $token[0] === T_LNUMBER) {
        // Use shorter integer notation `0x0F` => `15` and `011` => `9`.
        // Technically, hex codes may be shorter for very large ints, but adding
        // another 2 leading chars is rarely worth it.
        // Optimizing floats is not really worth it, as they have many special
        // cases, such as e-notation and we would lose types for `0.0` => `0`.
        $all[$i][1] = (string)intval($token[1], 0);
    } elseif (is_array($token) && $token[0] === T_NEW) {
        // remove unneeded parenthesis for constructors without args `new a();` => `new a;`

        // search next non-whitespace/non-comment token
        $next = function ($i) use (&$all) {
            $next = $all[++$i];
            while (is_array($next) && ($next[0] === T_COMMENT || $next[0] === T_DOC_COMMENT || $next[0] === T_WHITESPACE)) {
                $next = $all[++$i];
            }
            return $i;
        };

        // jump over next token (class name), then next must be open parenthesis, followed by closing
        $open = $next($next($i));
        $close = $next($open);
        if ($all[$open] === '(' && $all[$close] === ')') {
            unset($all[$open], $all[$close]);
        }
    }
}
$all = array_values($all);
foreach ($all as $i => $token) {
    if (is_array($token) && $token[0] === T_WHITESPACE) {
        if (strpos($token[1], "\n") !== false) {
            $token = strpos("()[]<>=+-*/%|,.:?!'\"\n", substr($small, -1)) === false ? "\n" : '';
        } else {
            $last = substr($small, -1);
            $next = isset($all[$i + 1]) ? substr(is_array($all[$i + 1]) ? $all[$i + 1][1] : $all[$i + 1], 0, 1) : ' ';

            $token = (strpos('()[]{}<>;=+-*/%&|,.:?!@\'"' . "\r\n", $last) !== false || strpos('()[]{}<>;=+-*/%&|,.:?!@\'"' . '$', $next) !== false) ? '' : ' ';
        }
    }

    $small .= isset($token[1]) ? $token[1] : $token;
}
file_put_contents($out, $small);
echo ' DONE (' . strlen($small) . ' bytes)' . PHP_EOL;
