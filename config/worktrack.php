<?php

return [
    'reminder_timezone' => env('WORKTRACK_REMINDER_TIMEZONE', 'Asia/Manila'),
    'mysql_dump_binary' => env(
        'WORKTRACK_MYSQLDUMP_BINARY',
        PHP_OS_FAMILY === 'Windows' ? 'C:/xampp/mysql/bin/mysqldump.exe' : 'mysqldump',
    ),
    'mysql_binary' => env(
        'WORKTRACK_MYSQL_BINARY',
        PHP_OS_FAMILY === 'Windows' ? 'C:/xampp/mysql/bin/mysql.exe' : 'mysql',
    ),
    'backup_retention' => (int) env('WORKTRACK_BACKUP_RETENTION', 14),
    'backup_timeout' => (int) env('WORKTRACK_BACKUP_TIMEOUT', 300),
];
