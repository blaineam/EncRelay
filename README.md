# EncRelay
A simple means to have a server encrypt arbitrary binary files so a static html and js can decrypt them on demand.

## Use Case
For services that store user data like social media accounts, it would be nice if when an archive of user data is requested the site asks the user for a passphrase used to access the content contained inside the archive that typically would contain binary data files like images or videos and then the service would use that passphrase to encrypt the contents of the archive and provide the end user with a static html with js webpage that they can use offline to access the content of their archived user data.

This satisfies a current concern with sites that store a lot of data from a user because if the contents of the archive were ever compromised so would their online activity be compromised. This simple tooling lets the service provider use an in memory passphrase to encrypt and decrypt the data on the fly so that as soon as the sessions are closed both server side encrypting the payload and client site decrypting the payload, the data will automatically remain in it's encrypted state.

## How to use

1. Clone the repo.
2. Use the following command to have the server encrypt media in a temporary location, NOTE: the encrypt and decrypt modify files in place, use with caution and always have a backup of any production date.
    1. `php -d memory_limit=128M encrypt.php -p="<user_supplied_passphrase>" -d <directory_to_temp_media_files>`
    2. if you want to decrypt the data the decrypt.php script takes the same set of arguments as the encrypt.php script does.
3. Use the code in index.html to build a static html and js webpage to access the media files for the archive.

## Demo
You can clone the repo and open the demo just keep in mind local file access needs to be allowed for the encrypted sample data to show up.

## Credit
The encryption was solved by another MIT licensed repo, I reused that code to allow the transmission of ArrayBuffers or Byte Arrays from PHP to JS so that it can efficiently transmit large files in chunks reducing server and browser memory usages to allow large binary files to function in Chrome at least.
[https://github.com/brainfoolong/cryptojs-aes-php](https://github.com/brainfoolong/cryptojs-aes-php)

I never saw anyone talk about having php send binary data to js using ArrayBuffers so that the payload can be sent and or read in an encrypted manner.

