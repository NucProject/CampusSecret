@echo off
set PHP_FCGI_MAX_REQUESTS=1000

echo Starting PHP FastCGI...

D:\php\php-cgi.exe -b 127.0.0.1:9000 -c D:\php\php.ini
