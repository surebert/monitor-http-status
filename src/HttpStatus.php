<?php
/**
 * Used to monitor a site for http status issues or connection problems
 *
 * @author Paul Visco <Paul.Visco@gmail.com>
 */
namespace sb\Monitor;

class HttpStatus implements \Psr\Log\LoggerAwareInterface
{

    /**
     * An array of the sites to monitor
     * @var array
     */
    protected $urls_to_monitor = [];

    /**
     * How long the system waits between scans in seconds
     * @var int
     */
    protected $time_between_scans_in_seconds = 300;

    /**
     * Last time a notification was sent out in unix timestamp
     * @var int
     */
    protected $last_notification = 0;

    /**
     * The logging system to use
     * @var \Psr\Log
     */
    protected $logger;

    /**
     * Which status codes to ignore
     * @var type
     */
    protected $ignore_status_codes = [200];

    /**
     * Time to wait between sending notifications in seconds, default 900 (15 minutes)
     * @var int
     */
    protected $time_between_notfication_in_seconds = 900;

    /**
     * The max connection wait time in seconds
     * @var int
     */
    protected $connect_timeout = 10;

    /**
     * The max response wait time in seconds
     * @var int
     */
    protected $response_timeout = 30;
    
    /**
     * Notification handler
     * @var \Closure
     */
    protected $on_notification_handler = null;

    /**
     * Create a new sites monitors
     * @param \Psr\Log\LoggerInterface $logger PSR 3 log interface for system
     */
    public function __construct(\Psr\Log\LoggerInterface $logger)
    {

        //track errors
        error_reporting(E_ALL);

        //set time limit to forever
        set_time_limit(0);

        $this->logger = $logger;
    }

    /**
     * Create a new sites monitors
     * @param array $urls_to_monitor An array of sites to monitor
     */
    public function setUrls($urls_to_monitor = [])
    {
        $this->urls_to_monitor = $urls_to_monitor;
        $this->logger->info("Changing site URLs to " . implode(" ", array_map(function ($v) {
            return '<' . $v . '>';
        }, $urls_to_monitor)));
    }

    /**
     * Sets the timeout between monitoring intervals
     * @param int $time_between_scans_in_seconds defaults to 300
     */
    public function setInterval($time_between_scans_in_seconds = 300)
    {
        $this->time_between_scans_in_seconds = $time_between_scans_in_seconds;
        $this->logger->info("Changing monitor interval to " . $time_between_scans_in_seconds . " second" . ($this->time_between_scans_in_seconds == 1 ? '' : 's'));
    }

    /**
     * Sets timeouts for connection and response time
     * @param int $connect_timeout default is 10 seconds
     * @param int $response_timeout default is 30 seconds
     */
    public function setTimeouts($connect_timeout = null, $response_timeout = null)
    {
        if (is_int($connect_timeout)) {
            $this->connect_timeout = $connect_timeout;
        }

        if (is_int($response_timeout)) {
            $this->response_timeout = $response_timeout;
        }
    }

    /**
     * Sets the logger
     * @param \Psr\Log\LoggerInterface $logger
     */
    public function setLogger(\Psr\Log\LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    /**
     * Status codes that are ignored
     * @param array $ignore_status_codes
     */
    public function setHttpStatusCodesToIgnore($ignore_status_codes = [200])
    {
        $this->ignore_codes = $ignore_status_codes;
        $this->logger->info("Ignoring HTTP status codes: ".implode(", ", $ignore_status_codes));
    }
    
    /**
     * Sets the notification timeout in seconds
     * @param int $time_between_notfication_in_seconds
     */
    public function setNotificationTimeout($time_between_notfication_in_seconds = null)
    {
        if (is_int($time_between_notfication_in_seconds)) {
            $this->time_between_notfication_in_seconds = $time_between_notfication_in_seconds;
        }
    }

    /**
     * Begins site http status tracking and set the time
     */
    public function begin()
    {
        if (empty($this->urls_to_monitor)) {
            $this->logger->emergency("There are no sites to monitor, exiting...");
            exit;
        }
        
        $this->logger->info("Begin monitoring");

        //start monitor in loop
        while (true) {
            try {
                $this->monitorStatus();
            } catch (\Exception $ex) {
                $this->logger->critical("Monitoring error " . $ex->getMessage());
            }

            //log wait time and sleep
            $this->logger->debug("Sleeping another " . $this->time_between_scans_in_seconds . " second" . ($this->time_between_scans_in_seconds == 1 ? '' : 's'));
            sleep($this->time_between_scans_in_seconds);
        }
    }
    
    /**
     * Sets the function that fires when a notification would fire
     * @param \Closure $on_notification_handler The function to fire when a notification would go out
     * @param int $time_between_notfication_in_seconds Optional timeout, waits 900 seconds by default
     */
    public function onNotification(\Closure $on_notification_handler, $time_between_notfication_in_seconds = null)
    {
        if ($on_notification_handler instanceof \Closure) {
            $this->logger->debug("Setting onNotification handler ".preg_replace("~\s+~", " ", print_r($on_notification_handler, 1)));
            $this->on_notification_handler = $on_notification_handler;
        }
        
        if (is_int($time_between_notfication_in_seconds)) {
            $this->time_between_notfication_in_seconds = $time_between_notfication_in_seconds;
        }
    }

    /**
     * Monitor the sites
     */
    protected function monitorStatus()
    {
        $monitor_messages = [];

        foreach ($this->urls_to_monitor as $url) {
            $include_body_in_response = false;
            if(preg_match("/^(BODY\-)(http.*?)$/", $url, $match){
                $url = $match[1];   
                $include_body_in_response = true;
            }
            $this->logger->debug("Monitoring " . $url);

            //create curl request object
            $ch = curl_init();

            //load the URL
            curl_setopt($ch, CURLOPT_URL, $url);
            //give it a user agent that is easy to identify on the other end
            curl_setopt($ch, CURLOPT_USERAGENT, __CLASS__ . "-bot");

            // Send to remote and return data to caller.
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

            //tell it to return the header
            curl_setopt($ch, CURLOPT_HEADER, true);

            if(!$include_body_in_response){
                //tell it to return no body since we only care about the http headers
                curl_setopt($ch, CURLOPT_NOBODY, true);
            }

            //Time to wait for connection in seconds
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $this->connect_timeout);
            
            //follow redirect to final destination
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);

            //Time to wait for response in seconds
            curl_setopt($ch, CURLOPT_TIMEOUT, $this->response_timeout);

            $result = curl_exec($ch);
            if (curl_errno($ch)) {
                $monitor_messages[$url] = $url . " has an transport error code of " . curl_errno($ch) . " with message <" . trim(curl_error($ch) . ">");
                $this->logger->notice($monitor_messages[$url]);
                continue;
            }
            $status_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            if (!in_array($status_code, $this->ignore_codes)) {
                $monitor_messages[$url] = $url . " has a HTTP status code of " . $status_code;
                $this->logger->alert($monitor_messages[$url], curl_getinfo($ch));
            }
            curl_close($ch);
        }

        if (count($monitor_messages) && time() - $this->last_notification > $this->time_between_notfication_in_seconds) {
            $message = "As of " . date('m/d/y H:i:s') . ", some sites being monitored are having errors:\n\n";
            foreach ($monitor_messages as $error_message) {
                $message .= "\n" . $error_message;
            }
            $this->notify($message);
        }
    }

    /**
     * Updates the last notification time
     */
    protected function updateLastNotificationTime()
    {
        $this->last_notification = time();
    }

    /**
     * Sends notifications to notification emails
     * @param string $message The message to send
     */
    protected function notify($message)
    {
        $this->updateLastNotificationTime();
        
        if (!($this->on_notification_handler instanceof \Closure)) {
            $this->logger->debug('No notification handler set would have notified message ' . preg_replace("/\n+/", " ", $message));
            return;
        }

        ($this->on_notification_handler)($message, $this->logger);
    }
}
