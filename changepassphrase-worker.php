<?php

if (PHP_SAPI != "cli") {
    die();
}

$options = getopt(
    "p:f:n:",
    ["passphrase:", "file:", "newpassphrase:"]
);

$passphrase = isset($options['p']) ? $options['p'] : $options['passphrase'];
$newpassphrase = isset($options['n']) ? $options['n'] : $options['newpassphrase'];

$file = isset($options['f']) ? $options['f'] : $options['file'];

if(empty($passphrase) || empty($file) || empty($newpassphrase) || !is_file($file)) {
    die('Please provide a --passphrase, --newpassphrase and a --file paramerter');
}


include __DIR__.DIRECTORY_SEPARATOR.'MediaCrypto.php';

use MediaCrypto\MediaCrypto;

MediaCrypto::decrypt($passphrase, $file, true);
MediaCrypto::encrypt($newpassphrase, $file, true);
