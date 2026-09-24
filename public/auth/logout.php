<?php

declare(strict_types=1);

require_once __DIR__ . '/../../app/bootstrap.php';

sessionBoot();
session_destroy();
session_start();
flash('Kamu sudah keluar. Sampai jumpa! 👋');
header('Location: /');
