<?php

// Configuration SMTP directe (sans .env)
return [
    'host' => 'smtp-relay.brevo.com',
    'port' => 587,
    'username' => 'your_smtp_username',
    'password' => 'your_smtp_password',
    'from_email' => 'your_sender_email@example.com', // Remplacez par votre e-mail d'expéditeur validé dans Brevo si nécessaire
    'from_name' => 'SAV AEBDM',
    'secure' => 'tls'
];
