<?php
declare(strict_types=1);

return [
    'dsn' => 'mysql:host=localhost;dbname=blog_comments;charset=utf8mb4',
    'user' => 'blog_comments',
    'password' => 'CHANGE_ME',
    // Origine esatta del blog, senza slash finale (schema + dominio + eventuale porta).
    'blog_origin' => 'https://www.example.com',
    'moderation' => true,
];
