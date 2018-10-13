Used to monitor http status of websites and notify if needed

To run, edit the index.php with your info you want and then run the index.php file

# Install with composer
```bash
mkdir monitor-http-status
cd monitor-http-status
composer require surebert/monitor-http-status:dev-master
cp ./vendor/surebert/monitor-http-status/examples/service.php .
```

# Running as a service
There is an example script for running as a command line tool with arguments in src/examples/service.php

```bash
mkdir ./logs
php service.php -v -l=./logs -e=some@email.com -u=https://somesite.com,https://some-other.com
```

# Compiling to phar
You could take the service example and archive it into a phar file for easy use as a linux system service

First move out one directory
```bash
//copy the service.php file to the working directory you checked out into above
cp ./vendor/surebert/monitor-http-status/examples/service.php .
//move out one directory 
cd ../
//create a create-phar file and give enter the following code
nano create-phar.php
```

```php
<?php

$pharFile = 'monitor-http-status.phar';

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

// Create the default stub from main.php entrypoint
$defaultStub = $phar->createDefaultStub('service.php');

// Add the rest of the apps files
$phar->buildFromDirectory(__DIR__ . '/monitor-http-status');

// Customize the stub to add the shebang
$stub = "#!/usr/bin/php \n" . $defaultStub;

// Add the stub
$phar->setStub($stub);

$phar->stopBuffering();

// Plus - compressing it into gzip
$phar->compressFiles(Phar::GZ);

// Make the file executable
chmod(__DIR__ . '/'.$pharFile, 0770);

echo "$pharFile successfully created" . PHP_EOL;
```

Now archive the application with phar
```bash
php -dphar.readonly=0  create-phar.php
```

Test file to see instructions
```
./monitor-http-status.phar
```

If it works, move to /usr/local/bin if desired
```
sudo mv monitor-http-status.phar /usr/local/bin
sudo chmod 555 /usr/local/bin/monitor-http-status.phar
```

Now you could use it as a service script for init, upstart or systemd

```
/usr/local/bin/monitor-http-status.phar -v -l=/var/log/somepath -e=some@email.com -u=https://somesite.com,https://some-other.com
```

There is an exmaple init.d service script in examples/sysv/etc/init.d/monitor-http-status
# Making your own custom notifier, you could use custom events, PSR3 loggers etc

Then copy the following code into monitor.php
```bash
nano monitor.php
```

```php
//include composer
require __DIR__ . '/vendor/autoload.php';

// create the logger
$logger = new \Monolog\Logger('general'); 

//log to stdout with all levels of debugging you could log only certain levels
//by using a second argument to the StreamHandler
$logger->pushHandler(new \Monolog\Handler\StreamHandler('php://stdout', \Monolog\Logger::DEBUG));

//log to file, only log items above INFO level
$logger->pushHandler(new \Monolog\Handler\RotatingFileHandler('./logs/monitor', \Monolog\Logger::INFO));
 
//create new http status monitor
$monitor = new \sb\Monitor\HttpStatus($logger);

//OPTIONAL set interval between scans, defaults to 5 minutes (300 seconds)
$monitor->setInterval(300);

//OPTIONAL set status codes to ignore 200 is the default but 401 could also be useful
$monitor->setHttpStatusCodesToIgnore([200]);

//OPTIONAL sets connect and response timeouts in seconds, defaults to 10 and 30
$monitor->setTimeouts(20, 30);

//OPTIONAL what to do when an http or transport error is found
//second argument is  min timeout between notifications
$monitor->onNotification(function($message, $logger){
    
        $to = 'some-email@some-site.com';
        $logger->info('Attempting to send mail to ' . $to);
        $sent = mail($to, "Site Access Error", $message);
        if ($sent) {
            $logger->info("Emails sent to " . $email_list);
        } else {
            $logger->error("Could not send emails to " . $email_list);
        }
}, 900);

//REQUIRED set the URLS to monitor
$monitor->setUrls([
    'https://somesite.com/test.php',
    'https://other-site.com'
]);

//start monitor and check site 
$monitor->begin();
```

Then run the php file
```
php ./monitor.php
```