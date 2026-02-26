<?php
require_once '../includes/db.php';

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$url_inicio = filter_input(INPUT_GET, 'data_inicio', FILTER_SANITIZE_SPECIAL_CHARS);
$url_fim = filter_input(INPUT_GET, 'data_fim', FILTER_SANITIZE_SPECIAL_CHARS);

if ($id) {
    $stmt = $pdo->prepare("SELECT hash_pedido, parcela_atual FROM transacoes WHERE id = :id");
    $stmt->bindValue(':id', $id);
    $stmt->execute();
    $item = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($item && !empty($item['hash_pedido'])) {
        // É UMA TRANSAÇÃO EM GRUPO
        if ($item['parcela_atual'] == 1) {
            // Se for a parcela 1, apaga o grupo inteiro baseado na hash
            $delete = $pdo->prepare("DELETE FROM transacoes WHERE hash_pedido = :hash");
            $delete->bindValue(':hash', $item['hash_pedido']);
            $delete->execute();
        } else {
            // Se tentar apagar a parcela 2, 3, etc... o sistema bloqueia
            header('Location: ../index.php?status=erro_delete_parcela');
            exit;
        }
    } else {
        // É UMA TRANSAÇÃO ÚNICA: Apaga normalmente
        $delete = $pdo->prepare("DELETE FROM transacoes WHERE id = :id");
        $delete->bindValue(':id', $id);
        $delete->execute();
    }
}

$url_retorno = '../index.php?status=deleted';

if ($url_inicio && $url_fim) {
    $url_retorno .= "&data_inicio={$url_inicio}&data_fim={$url_fim}";
}
header('Location: ' . $url_retorno);
exit;