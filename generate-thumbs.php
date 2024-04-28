<?php

error_reporting(E_ALL ^ E_NOTICE);

if (PHP_SAPI != 'cli') {
    die();
}

$options = getopt(
    'p:d:',
    ['passphrase:', 'directory:']
);

$passphrase = isset($options['p']) ? $options['p'] : $options['passphrase'];

$directory = isset($options['d']) ? $options['d'] : $options['directory'];

if(empty($passphrase) || empty($directory)) {
    die('Please provide a --passphrase, and a --directory paramerter');
}

include __DIR__ . DIRECTORY_SEPARATOR . 'MediaCrypto.php';

function rglob($pattern, $flags = 0)
{
    $files = glob($pattern, $flags);
    foreach (glob(dirname($pattern) . '/*', GLOB_ONLYDIR | GLOB_NOSORT) as $dir) {
        $files = array_merge(
            [],
            ...[$files, rglob($dir . '/' . basename($pattern), $flags)]
        );
    }
    return $files;
}

$progress = 0;
$lastPercent = 0;
$cachePath = __DIR__ . DIRECTORY_SEPARATOR . 'filelist.json';
if (!is_file($directory)) {
    if(!is_file($cachePath)) {
        echo PHP_EOL . 'Scanning Directory for gif files';
        $files = [$directory, ...rglob($directory . '/*.{gif}', GLOB_BRACE)];
        file_put_contents($cachePath, json_encode(['progress' => $progress, 'files' => $files]));
    } else {
        $cache = json_decode(file_get_contents($cachePath), true);
        $files = $cache['files'];
        $progress = $cache['progress'];
    }
} else {
    $files = [$directory];
}
$filesCount = count($files);
echo PHP_EOL . "File list determined with {$filesCount} total files";
function get_processor_cores_number()
{
    if (PHP_OS_FAMILY == 'Windows') {
        $cores = shell_exec('echo %NUMBER_OF_PROCESSORS%');
    } else {
        $cores = shell_exec('nproc');
    }

    return (int) $cores;
}

$usableCores = round(get_processor_cores_number() * 0.7);

$tasks = array_slice($files, $progress);

$procs = [];
$pipes = [];

$cmd = "php ./generate-thumbs-worker.php -p $passphrase -f ";

$desc = [
    0 => [ 'pipe', 'r' ],
    1 => [ 'pipe', 'w' ],
    2 => [ 'pipe', 'a' ],
];
while ($procs || count($tasks) > 0) {
    if(count($procs) < $usableCores) {
        $usedKeys = array_keys($procs);
        $possibleKeys = range(0, $usableCores);
        $freeKeys = array_diff($possibleKeys, $usedKeys);
        foreach($freeKeys as $i) {
            $taskFile = array_shift($tasks);
            while (is_file(str_replace('.gif', '.jpg', $taskFile))) {
                $taskFile = array_shift($tasks);
            }
            $iCmd = $cmd . ' "' . $taskFile . '"';
            $proc = proc_open($iCmd, $desc, $pipes[$i], __DIR__);
            $procs[$i] = $proc;
        }
    }
    $stdins = array_column($pipes, 0);
    $stdouts = array_column($pipes, 1);
    $stderrs = array_column($pipes, 2);
    foreach ($procs as $i => $proc) {
        $status = proc_get_status($proc);
        if (false === $status[ 'running' ]) {
            if ($content = stream_get_contents($stderrs[ $i ])) {
                echo '[ERROR]' . $content . PHP_EOL;
            }
            echo stream_get_contents($stdouts[ $i ]) . PHP_EOL;
            $status = proc_close($proc);
            unset($procs[ $i ]);
            $progress++;
            file_put_contents($cachePath, json_encode(['progress' => $progress, 'files' => $files]));
            $percent = round(($progress / $filesCount) * 100, 2);
            if($percent - $lastPercent >= 2) {
                echo PHP_EOL . PHP_EOL . "Overall Progress: {$percent}%" . PHP_EOL . PHP_EOL;
                $lastPercent = $percent;
            }
        }
    }
    usleep(1);
}

unlink($cachePath);
echo PHP_EOL . 'Done!';
