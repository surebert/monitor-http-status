Used to monitor http status of websites and notify if needed

# Install with composer
```bash
mkdir monitor-http-status;
cd monitor-http-status;
composer require surebert/monitor-http-status:dev-master
```

# Running as a service
You can convert this into a command line tool using the installer

```bash
php vendor/surebert/monitor-http-status/installation/install.php
```

Afterwards you will find a monitor-http-status command in the ./bin directory

You could copy this to somewhere in your path e.g. /usr/local/bin/monitor-http-status
 if you want to be able to use it from elsewhere

This example copies the command to /usr/local/bin and makes it runnable by all users

```
sudo cp ./bin/monitor-http-status /usr/local/bin/monitor-http-status
sudo chmod a+rx /usr/local/bin/monitor-http-status
```

To run, make your log base directory and then run the command
You may have to change the ownership on the directory depending on who you are running as

```bash
./bin/monitor-http-status -v -e=some@email.com -u=https://somesite.com,https://some-other.com
```

# Running as a service with logging to central log
If you want to run this command as a service 

## SysV CentOS 6
The installation/services/sysv/etc/init.d/monitor-http-status file can be used as a system service
simply copy the file into /etc/init.d/monitor-http-status on your server and make it executable

```bash
sudo cp vendor/surebert/monitor-http-status/installation/sysv/etc/init.d/monitor-http-status /etc/init.d/ ;
sudo chmod +x /etc/init.d/monitor-http-status
```
To run, make your log base directory and then run the command
You may have to change the ownership on the directory depending on who you are running as

```bash
sudo mkdir /var/log/monitor-http-status;
sudo chmod -R 777 /var/log/monitor-http-status;
./bin/monitor-http-status -v -l=/var/log/monitor-http-status -e=some@email.com -u=https://somesite.com,https://some-other.com
```

Then edit the file to watch the URLs you want and to send to the email you want near the top of the file
```bash
sudo nano /etc/init.d/monitor-http-status
```

Make sure that you make the log directory writeable
```bash
sudo mkdir /var/log/monitor-http-status
```

Then test the command 
```
sudo service monitor-http-status start
```

If everything works out, you can set it to start on server boot
```
sudo chkconfig monitor-http-status on
```

# Building your own version of the command

You could easily change the command functionality by editing the cli.php file
in installation/cli.php before compiling into the command above

For example you could replace the notification function with something that sends SMS

You could replace file based logging with something decentralized

When done just rerun, the install command from the base directory