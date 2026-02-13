<?php
require_once '../includes/db.php';
$id = filter_input(INPUT_GET, 'id', FILTER_SANITIZE_NUMBER_INT);

if ($id) {
    // Apaga a categoria (o banco pode reclamar se tiver transações, mas vamos simplificar)
    try {
        $pdo->prepare("DELETE FROM categorias WHERE id = ?")->execute([$id]);
        header('Location: ../configuracoes.php?msg=deleted');
    } catch (Exception $e) {
        header('Location: ../configuracoes.php?msg=error_fk'); // Erro se tiver transações vinculadas
    }
}
exit;