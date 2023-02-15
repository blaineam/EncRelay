<?php
if (PHP_SAPI != "cli") {
    die();
}

$options = getopt(
    "p:d:",
    ["passphrase:", "directory:"]
);

$passphrase = isset($options['p']) ? $options['p'] : $options['passphrase'];

$directory = isset($options['d']) ? $options['d'] : $options['directory'];

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
echo PHP_EOL."Scanning Directory for files";
$files = [$directory, ...rglob($directory."/*.{mp4,jpg,png,gif,jpeg,webm}", GLOB_BRACE)];
$filesCount = count($files);
echo PHP_EOL."File list determined with {$filesCount} total files";
foreach($files as $filePath) {
    if(
        is_file($filePath) 
        && strstr(MediaCrypto::getMime($filePath), 'image') === false 
        && strstr(MediaCrypto::getMime($filePath), 'video') === false
    ) {
        MediaCrypto::decrypt($passphrase, $filePath, true);
    }
    $progress++;
    $percent = round(($progress/$filesCount) * 100, 2);
    if($percent - $lastPercent >= 2) {
        echo PHP_EOL.PHP_EOL. "Overall Progress: {$percent}%".PHP_EOL.PHP_EOL;
        $lastPercent = $percent;
    }
}