<?php
// Configuration for mdiki

return [
    'password' => 'admin123', // Change this in production
    'mdiki_root' => __DIR__ . '/public/mds',
    'site_title' => 'mdiki', // Site title displayed in the header
    'site_icon' => 'description', // Material Icon name for the logo
    'session_name' => 'mdiki_session',
    'session_lifetime' => 43200, // 12 hours
    'default_license' => 'Mdiki is distributed under the MIT License',
    'max_upload_size' => 2, // Maximum upload size in MB
    'data_dir' => __DIR__ . '/.mdiki-data',
    'edit_lock_lifetime' => 120, // seconds
    'anonymous_edit_inactivity_lifetime' => 70, // seconds
    'version' => '1.2.0',
];
