<?php

namespace MediaCrypto;

use finfo;

class MediaCrypto {
    public static function getMime($filePath) {
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        return $finfo->file($filePath);
    }

    public static function encrypt(
        string $passphrase, 
        string $path,
        bool $enableOutput = false,
        int $chunkSize = null,
    ) {
        $memory_limit = self::return_bytes(ini_get('memory_limit'));

        if ($enableOutput) {
            echo "Encrypting: {$path}".PHP_EOL;
        }

        if(!is_null($chunkSize)) {
            $chunkSize = min($memory_limit / 8, $chunkSize);
        } else {
            $chunkSize = min($memory_limit / 8, 4096);
        }

        $tempName = tempnam(sys_get_temp_dir(), "MedCrypt_");
        $read = fopen($path,'r');
        $write = fopen($tempName,'w');
        $total = filesize($path);
        $progress = 0;
        $loggedProgress = 0;
        while(!feof($read)) {
            $chunk = self::encryptChunk(array_values(unpack('c*', fread($read, $chunkSize ))), $passphrase);
            fwrite($write, $chunk."\n");
            $progress += $chunkSize;
            if ($enableOutput) {
                $output = min(round(($progress/$total) * 10, 3), 10);
                if($loggedProgress + 1 < $output) {
                    echo  ($output * 10) . "% ";
                    $loggedProgress = floor($output);
                }
            }
        }

        if ($enableOutput) {
            echo "100.0% " . PHP_EOL;
        }

        fclose($read);
        fclose($write);
        copy($tempName, $path);
        unlink($tempName);
    }

    public static function decrypt(
        string $passphrase, 
        string $path,
        bool $enableOutput = false
    ) {

        if ($enableOutput) {
            echo "Decrypting: {$path}".PHP_EOL;
        }

        $tempName = tempnam(sys_get_temp_dir(), "MedCrypt_");
        $read = fopen($path,'r');
        $write = fopen($tempName,'w');
        $total = filesize($path);
        $progress = 0;
        $loggedProgress = 0;
        while(!feof($read)) {
            $line = rtrim(fgets($read), "\r\n");
            if(strlen($line) > 0) {
                $decrypted = self::decryptChunk($line, $passphrase);
                $chunk = pack('c*', ...$decrypted);
                fwrite($write, $chunk);

                if ($enableOutput) {
                    $progress += strlen($line);
                    $output = min(round(($progress/$total) * 10, 3), 10);
                    if($loggedProgress + 1 < $output) {
                        echo  ($output * 10) . "% ";
                        $loggedProgress = floor($output);
                    }
                }
            }
        }

        if ($enableOutput) {
            echo "100.0% " . PHP_EOL;
        }

        fclose($read);
        fclose($write);
        copy($tempName, $path);
        unlink($tempName);
    }

    private static function encryptChunk($value, string $passphrase) {
        $salt = openssl_random_pseudo_bytes(8);
        $salted = '';
        $dx = '';
        while (strlen($salted) < 48) {
            $dx = md5($dx . $passphrase . $salt, true);
            $salted .= $dx;
        }
        $key = substr($salted, 0, 32);
        $iv = substr($salted, 32, 16);
        $encrypted_data = openssl_encrypt(json_encode($value), 'aes-256-cbc', $key, true, $iv);
        $data = ["ct" => base64_encode($encrypted_data), "iv" => bin2hex($iv), "s" => bin2hex($salt)];
        return json_encode($data);
    }

    private static function decryptChunk(string $jsonStr, string $passphrase) {
        $json = json_decode($jsonStr, true);
        $salt = hex2bin($json["s"]);
        $iv = hex2bin($json["iv"]);
        $ct = base64_decode($json["ct"]);
        $concatedPassphrase = $passphrase . $salt;
        $md5 = [];
        $md5[0] = md5($concatedPassphrase, true);
        $result = $md5[0];
        for ($i = 1; $i < 3; $i++) {
            $md5[$i] = md5($md5[$i - 1] . $concatedPassphrase, true);
            $result .= $md5[$i];
        }
        $key = substr($result, 0, 32);
        $data = openssl_decrypt($ct, 'aes-256-cbc', $key, true, $iv);
        return json_decode($data, true);
    }

    private static function return_bytes($val) {
        $val = trim($val);
        $last = strtolower($val[strlen($val)-1]);
        $val = substr($val, 0, -1);
        switch($last) {
            case 'g':
                $val *= 1024;
            case 'm':
                $val *= 1024;
            case 'k':
                $val *= 1024;
        }
        return $val;
    }
}