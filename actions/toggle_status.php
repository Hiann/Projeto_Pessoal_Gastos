<?php
require_once '../includes/db.php';
header('Content-Type: application/json'); // Avisa que é um JSON

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

$response = ['success' => false];

if ($id) {
    // 1. Descobre o status atual
    $stmt = $pdo->prepare("SELECT status FROM transacoes WHERE id = :id");
    $stmt->bindValue(':id', $id);
    $stmt->execute();
    $transacao = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($transacao) {
        // 2. Inverte
        $novo_status = ($transacao['status'] === 'pago') ? 'pendente' : 'pago';

        // 3. Salva
        $update = $pdo->prepare("UPDATE transacoes SET status = :status WHERE id = :id");
        $update->bindValue(':status', $novo_status);
        $update->bindValue(':id', $id);
        
        if ($update->execute()) {
            $response['success'] = true;
            $response['new_status'] = $novo_status; // Devolve o novo status ('pago' ou 'pendente')
        }
    }
}

echo json_encode($response);
exit;