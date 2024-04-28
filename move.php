<?php
if (PHP_SAPI != 'cli') {
    die();
}

$options = getopt(
    'd:',
    ['destination:']
);

$dest = isset($options['d']) ? $options['d'] : $options['destination'];
if(empty($dest) || !is_dir($dest)) {
    die('Please provide a --destination paramerter');
}

$cachePath = __DIR__ . DIRECTORY_SEPARATOR . 'move.json';
if (!is_file($cachePath)) {
    die('Please copy your filelist.json to move.json and run again');
}

$cache = json_decode(file_get_contents($cachePath), true);
$files = $cache['files'];
foreach($files as $file) {
    $target = $dest . basename(dirname($file)) . DIRECTORY_SEPARATOR . basename($file);
    if (!is_file($target) && is_file($file)) {
        echo PHP_EOL . 'Copying file to: ' . $target;
        $outcome = copy($file, $target);
        if ($outcome == false) {
            echo PHP_EOL . 'unexpected copy failure stopping here.';
            exit(1);
        }
    }
}
echo PHP_EOL . 'DONE!';
