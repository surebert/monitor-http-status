<?php
$usage = <<<USAGE

-----------------
php service.php -v -l=/var/log/somepath -e=some@email.com -u=https://somesite.com,https://some-other.com

    -v    Optional verbose logs to stdout
    -l    Log path to log to
    -e    Email to send to, can be comma delimited list
    -u    Comma delimited list of URLS to monitor
    -n    Notification timeout - min time between notifications in seconds
    -i    Monitor Interval - time between checks in seconds
-----------------

USAGE;

//include composer
require __DIR__ . '/vendor/autoload.php';

// create the logger
$logger = new \Monolog\Logger('general'); 

$shortopts = "l::";  //optional log path
$shortopts .= "e::"; //optional email
$shortopts .= "v::"; //optional debug to stdout
$shortopts .= "u:"; //required url comma delimited
$shortopts .= "n::"; //notification timeout default 900 (15 min)
$shortopts .= "i::"; //monitor interval in seconds default 300 (5 min)

$options = getopt($shortopts);
if(empty($options)){
    die($usage);
}

if(!isset($options['u'])){
    die("You have to passing in urls as a comma delimited list with -u arg");    
}

$log_path = null;
if(isset($options['l'])){
    $log_path = $options['l'];
    if(!is_dir($log_path)){
        die("Log directory ".$log_path." does not exist\n");
    }
    
    //log to file, only log items above INFO level
    $logger->pushHandler(new \Monolog\Handler\RotatingFileHandler($log_path.'/log', \Monolog\Logger::DEBUG));
}

//log to stdout with all levels of debugging you could log only certain levels
//by using a second argument to the StreamHandler
if(isset($options['v'])){
    $logger->pushHandler(new \Monolog\Handler\StreamHandler('php://stdout', \Monolog\Logger::DEBUG));
}

//create new http status monitor
$monitor = new \sb\Monitor\HttpStatus($logger);

//OPTIONAL set interval between scans, defaults to 5 minutes (300 seconds)
$monitor->setInterval($options['i'] ?? 300);

//OPTIONAL set status codes to ignore 200 is the default but 401 could also be useful
$monitor->setHttpStatusCodesToIgnore([200]);

//OPTIONAL sets connect and response timeouts in seconds, defaults to 10 and 30
$monitor->setTimeouts(20, 30);

//OPTIONAL what to do when an http or transport error is found
//second argument is  min timeout between notifications

if(isset($options['e'])){
    $email = $options['e'];
    $monitor->onNotification(function($message, $logger) use($email){
    
        $logger->info('Attempting to send mail to ' . $email);
        
        $subject = "Site Access Error";

        $sent = mail($email, $subject, $message);
        if ($sent) {
            $logger->info("Email sent to " . $email);
        } else {
            $logger->error("Could not send email to " . $email);
        }
   }, $options['n'] ?? 900);
}
//REQUIRED set the URLS to monitor
$monitor->setUrls(explode(",", $options['u']));

//start monitor and check site 
$monitor->begin();
