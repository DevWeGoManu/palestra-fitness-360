<?php
require_once __DIR__ . '/../bootstrap.php';

$user = current_user();
json_response(['user' => $user, 'csrf_token' => csrf_token()]);
