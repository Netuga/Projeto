<?php
return [
    'label' => 'Users', 'title' => 'Contas de utilizador',
    'description' => 'Consulta as contas registadas no site.', 'table' => 'users', 'readonly' => true,
    'columns' => [
        ['name' => 'username', 'label' => 'Username'], ['name' => 'email', 'label' => 'Email'],
        ['name' => 'is_admin', 'label' => 'Admin'], ['name' => 'created_at', 'label' => 'Criada em'],
    ],
];
