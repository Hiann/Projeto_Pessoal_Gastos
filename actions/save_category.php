<?php
require_once '../includes/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome = filter_input(INPUT_POST, 'nome', FILTER_SANITIZE_SPECIAL_CHARS);
    $tipo = $_POST['tipo'];
    $cor = $_POST['cor'];

    if ($nome && $tipo) {
        $stmt = $pdo->prepare("INSERT INTO categorias (nome, tipo, cor_hex) VALUES (?, ?, ?)");
        $stmt->execute([$nome, $tipo, $cor]);
        
        header('Location: ../configuracoes.php?status=success');
        exit;
    }
}
header('Location: ../configuracoes.php?status=error');