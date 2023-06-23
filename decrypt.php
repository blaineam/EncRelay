<?php
if (PHP_SAPI != "cli") {
    die();
}

$options = getopt(
    "p:d:y:c:",
    ["passphrase:", "directory:", "yes:", "cleanup:"]
);

$passphrase = isset($options['p']) ? $options['p'] : $options['passphrase'];

$directory = isset($options['d']) ? $options['d'] : $options['directory'];

$force = isset($options['f']) ? true : isset($options['yes']);

$cleanup = isset($options['c']) ? true : isset($options['cleanup']);

if(empty($passphrase) || empty($directory)) {
    die('Please provide a --passphrase and a --directory paramerter');
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
        $files = [$directory, ...rglob($directory."/*.{mp4,jpg,png,gif,jpeg,webm,js}", GLOB_BRACE)];
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
foreach($files as $filePath) {
    if(
        is_file($filePath)
    ) {
        MediaCrypto::decrypt($passphrase, $filePath, true, $force, $cleanup);
    }
    $progress++;
    file_put_contents($cachePath, json_encode(["progress" => $progress, "files" => $files]));
    $percent = round(($progress/$filesCount) * 100, 2);
    if($percent - $lastPercent >= 2) {
        echo PHP_EOL.PHP_EOL. "Overall Progress: {$percent}%".PHP_EOL.PHP_EOL;
        $lastPercent = $percent;
    }
}

unlink($cachePath);
echo PHP_EOL."Done!";
