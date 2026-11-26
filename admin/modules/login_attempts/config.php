<?php
return [
    'label' => 'Login attempts', 'title' => 'Tentativas de login',
    'description' => 'Consulta tentativas e bloqueios de autenticação.', 'table' => 'login_attempts', 'readonly' => true,
    'columns' => [
        ['name' => 'ip_address', 'label' => 'IP'], ['name' => 'attempts', 'label' => 'Tentativas'],
        ['name' => 'last_attempt', 'label' => 'Última tentativa'], ['name' => 'blocked_until', 'label' => 'Bloqueado até'],
    ],
];
