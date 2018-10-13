<?php
$cur_path = getcwd();
if(!is_dir('./bin')){
    mkdir('./bin');
}

$pharFile = './bin/monitor-http-status.phar';

// clean up
if (file_exists($pharFile))
{
    unlink($pharFile);
}

if (file_exists($pharFile . '.gz'))
{
    unlink($pharFile . '.gz');
}

// create phar
$phar = new Phar($pharFile);

// start buffering. Mandatory to modify stub to add shebang
$phar->startBuffering();

//copy the service file into the main folder
copy(__DIR__.'/cli.php', './cli.php');

// Create the default stub from main.php entrypoint
$defaultStub = $phar->createDefaultStub('./cli.php');

//$exclude = '/^install.php$/i';

// Add the rest of the apps files
$phar->buildFromDirectory(getcwd());

// Customize the stub to add the shebang
$stub = "#!/usr/bin/php \n" . $defaultStub;

// Add the stub
$phar->setStub($stub);

$phar->stopBuffering();

// Plus - compressing it into gzip
$phar->compressFiles(Phar::GZ);

//create the command
$destination_file = str_replace('.phar', '', $pharFile);
rename($pharFile, $destination_file);

// Make the file executable
chmod($destination_file, 0770);

//remove cli file
unlink('./cli.php');

echo "$destination_file command successfully created" . PHP_EOL.PHP_EOL;