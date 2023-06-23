<?php

error_reporting(E_ALL ^ E_NOTICE);

if (PHP_SAPI != "cli") {
    die();
}

$options = getopt(
    "p:d:n:",
    ["passphrase:", "directory:", "newpassphrase:"]
);

$passphrase = isset($options['p']) ? $options['p'] : $options['passphrase'];
$newpassphrase = isset($options['n']) ? $options['n'] : $options['newpassphrase'];

$directory = isset($options['d']) ? $options['d'] : $options['directory'];

if(empty($passphrase) || empty($directory) || empty($newpassphrase)) {
    die('Please provide a --passphrase, --newpassphrase and a --directory paramerter');
}


include __DIR__.DIRECTORY_SEPARATOR.'MediaCrypto.php';

use MediaCrypto\MediaCrypto;

function rglob($pattern, $flags = 0)
{
    $files = glob($pattern, $flags);
    foreach (glob(dirname($pattern).'/*', GLOB_ONLYDIR|GLOB_NOSORT) as $dir) {
        $files = array_merge(
            [],
            ...[$files, rglob($dir . "/" . basename($pattern), $flags)]
        );
    }
    return $files;
}

$progress = 0;
$lastPercent = 0;
$cachePath = __DIR__.DIRECTORY_SEPARATOR."filelist.json";
if (!is_file($directory)) {
    if(!is_file($cachePath)) {
        echo PHP_EOL."Scanning Directory for files";
        $files = [$directory, ...rglob($directory."/*.{mp4,jpg,png,gif,jpeg,js,webm}", GLOB_BRACE)];
        file_put_contents($cachePath, json_encode(["progress" => $progress, "files" => $files]));
    } else {
        $cache = json_decode(file_get_contents($cachePath), true);
        $files = $cache["files"];
        $progress = $cache["progress"];
    }
} else {
    $files = [$directory];
}
$filesCount = count($files);
echo PHP_EOL."File list determined with {$filesCount} total files";
function get_processor_cores_number() {
    if (PHP_OS_FAMILY == 'Windows') {
        $cores = shell_exec('echo %NUMBER_OF_PROCESSORS%');
    } else {
        $cores = shell_exec('nproc');
    }

    return (int) $cores;
}
$usableCores = (get_processor_cores_number() / 2);


function runBatch($files, $newpassphrase, $passphrase, $cores) {
    $procs = [];
    $pipes = [];

    $cmd = "php ./changepassphrase-worker.php -p $passphrase -n $newpassphrase -f";

    $desc = [
        0 => [ 'pipe', 'r' ],
        1 => [ 'pipe', 'w' ],
        2 => [ 'pipe', 'a' ],
    ];

    if (count($files) > $cores) {
        throw Exception("batch is too big");
    }

    for ( $i = 0; $i < count($files); $i++ ) {
        $iCmd = $cmd . ' "' . $files[$i] . '"';
        $proc = proc_open($iCmd, $desc, $pipes[ $i ], __DIR__);

        $procs[ $i ] = $proc;
    }

    $stdins = array_column($pipes, 0);
    $stdouts = array_column($pipes, 1);
    $stderrs = array_column($pipes, 2);

    while ( $procs ) {
        foreach ( $procs as $i => $proc ) {
            // @gzhegow > [OR] you can output while script is running (if child never finishes)
            $read = [ $stdins[ $i ] ];
            $write = [ $stdouts[ $i ], $stderrs[ $i ] ];
            $except = [];
            if (stream_select($read, $write, $except, $seconds = 0, $microseconds = 1000)) {
                foreach ( $write as $stream ) {
                    echo stream_get_contents($stream);
                }
            }

            $status = proc_get_status($proc);

            if (false === $status[ 'running' ]) {
                $status = proc_close($proc);
                unset($procs[ $i ]);

                echo 'STATUS: ' . $status . PHP_EOL;
            }

            // @gzhegow > [OR] you can output once command finishes
            // $status = proc_get_status($proc);
            //
            // if (false === $status[ 'running' ]) {
            //     if ($content = stream_get_contents($stderrs[ $i ])) {
            //         echo '[ERROR]' . $content . PHP_EOL;
            //     }
            //
            //     echo stream_get_contents($stdouts[ $i ]) . PHP_EOL;
            //
            //     $status = proc_close($proc);
            //     unset($procs[ $i ]);
            //
            //     echo 'STATUS: ' . $status . PHP_EOL;
            // }
        }

        usleep(1); // give your computer one tick to decide what thread should be used
    }

}

foreach(array_chunk(array_slice($files, $progress), $usableCores) as $index => $filesChunk) {
    echo PHP_EOL."starting batch: $index";
    runBatch($filesChunk, $newpassphrase, $passphrase, $usableCores);
    echo PHP_EOL."Finished batch: $index";
    $progress += $usableCores;
    file_put_contents($cachePath, json_encode(["progress" => $progress, "files" => $files]));
    $percent = round(($progress/$filesCount) * 100, 2);
    if($percent - $lastPercent >= 2) {
        echo PHP_EOL.PHP_EOL. "Overall Progress: {$percent}%".PHP_EOL.PHP_EOL;
        $lastPercent = $percent;
    }
}

unlink($cachePath);
echo PHP_EOL."Done!";
