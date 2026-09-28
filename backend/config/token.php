<?php

return [
    // JWT 配置（生产环境请务必在 .env 中更换 KEY）
    'key' => env('JWT.KEY', 'jianzhanla-admin-secret-key-change-me'),
    // token 有效期（秒），默认 7 天
    'expire' => (int) env('JWT.EXPIRE', 604800),
    // 签发者
    'issuer' => 'jianzhanla-admin',
    // 签名算法
    'algorithm' => 'HS256',
];
