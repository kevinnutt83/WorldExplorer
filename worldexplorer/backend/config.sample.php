<?php
// This file is an example. The installer will generate backend/config.php with real values.
$AFTERLIGHT_CONFIG = [
  'base_url' => 'https://movealong.us',
  'db' => [ 'host' => 'localhost', 'port' => 3306, 'name' => 'afterlight', 'user' => 'afterlight', 'pass' => 'changeme' ],
  'ftp' => [ 'host' => 'localhost', 'user' => 'ftpuser', 'pass' => 'ftppass', 'path' => '/public_html/assets' ],
  'theme' => [ 'bg' => '#0f1115', 'fg' => '#e1e6ef', 'accent' => '#4cc9f0' ],
  'security' => [ 'session_name' => 'AL_SESS', 'csrf' => true ],
];
