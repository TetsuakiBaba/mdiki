<?php
// Configuration for mdiki

return [
    'password' => 'admin123', // Change this in production
    'mdiki_root' => __DIR__ . '/public/mds',
    'site_title' => 'mdiki', // Site title displayed in the header
    'site_icon' => 'description', // Material Icon name for the logo
    'session_name' => 'mdiki_session',
    'session_lifetime' => 43200, // 12 hours
];
