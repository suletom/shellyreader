# Shelly plug energy meter reader php script

Install/Configure: see script header
USAGE: run as a cron script at every hour to log meter counter!
Example:
* * * * * root php /opt/reader.php
9 9 1 * * root php /opt/reader.php month

- run with "week" or "month" argument to send historically summarized data summary to telegram channel (if available)
