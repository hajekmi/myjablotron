MyJablotron Jablotron alarm JA100 - alternative API for PHP

https://github.com/hajekmi/myjablotron

Requirements: PHP 5+, cURL lib
Author: Michal Hajek <michal@hajek.net>
Date: 26.1.2018

Helper API to http://www.jablonet.net

Public functions on MyJablotron:
login() - Login to MyJablotron
debug() - Enable or disable debug
sendPGMSignal() - Send signal to PGM output (open or close garage door)
lock() - Lock section
lockBypass() - Lock bypass section (open window active lock section)
unlock() - Unlock section
getKeyboards() - Get all sections on keyboards
getSection() - Get sections to lock or unlock
getPGM() - Get PGM
checkStatusSection() - Check status section - is lock or unlock
getAllStatuses() - Get all response of statuses
getHistory() - Get history (without paging)
getErrors() - Get errors

Files:
myjablotron.class.php - Class MyJablotron
examples.php - Examples
readme.txt - this text
