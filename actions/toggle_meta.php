<?php
require_once '../includes/db.php';

$id = filter_input(INPUT_GET, 'id', FILTER_SANITIZE_NUMBER_INT);

if ($id) {
    // Busca o status atual para inverter
    $stmt = $pdo->prepare("SELECT status FROM metas WHERE id = :id");
    $stmt->execute([':id' => $id]);
    $meta = $stmt->fetch();

    if ($meta) {
        $novoStatus = ($meta['status'] === 'ativa') ? 'concluida' : 'ativa';
        
        $update = $pdo->prepare("UPDATE metas SET status = :status WHERE id = :id");
        $update->execute([':status' => $novoStatus, ':id' => $id]);
        
        header('Location: ../metas.php?status=updated');
        exit;
    }
}

header('Location: ../metas.php');
exit;