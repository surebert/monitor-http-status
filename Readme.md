Used to monitor http status of websites and notify if needed

To run, edit the index.php with your info you want and then run the index.php file

Install with composer
```bash
composer install surebert/monitor-http-status
```

Then make a PHP file and include the following e.g. monitor.php

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
$monitor->setInterval(2);

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
php monitor.php
```