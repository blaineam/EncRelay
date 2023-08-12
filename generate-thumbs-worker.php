<?php

if (PHP_SAPI != "cli") {
    die();
}

$options = getopt(
    "p:f:",
    ["passphrase:", "file:"]
);

$passphrase = isset($options['p']) ? $options['p'] : $options['passphrase'];

$file = isset($options['f']) ? $options['f'] : $options['file'];

if(empty($passphrase) || empty($file) || !is_file($file)) {
    die('Please provide a --passphrase and a --file paramerter');
}

include __DIR__.DIRECTORY_SEPARATOR.'MediaCrypto.php';

use MediaCrypto\MediaCrypto;

MediaCrypto::decrypt($passphrase, $file, true);
$destination = dirname($file) . DIRECTORY_SEPARATOR . basename($file, ".gif") . ".jpg";
$cmd = "convert '{$file}[0]' -monitor -sampling-factor 4:2:0 -strip -interlace JPEG -colorspace sRGB -resize 1000 -compress JPEG -quality 70 '$destination'";
echo PHP_EOL.$cmd;
echo PHP_EOL. shell_exec($cmd);
MediaCrypto::encrypt($passphrase, $destination, true);
MediaCrypto::encrypt($passphrase, $file, true);